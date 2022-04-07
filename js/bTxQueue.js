/**
 * Queue of transaction requests for communication with efacloudServer. Compile
 * and send messages, asynchronously collect of responses or communication
 * errors, parse responses and hand-over the response or error to bTxHandler.
 */

var bTxQueue = {

	// the MESSAGE_SEPARATOR_STRING must not contain any regex special character
	// because it is used in "split()" calls as "regex" argument.
	MESSAGE_SEPARATOR_STRING : "\n|-eFa-|\n",
	MS_REPLACEMENT_STRING : "\n|-efa-|\n",

	ACTION_TX_SEND : 100,
	ACTION_TX_RETRY : 101,
	ACTION_TX_ABORT : 102,
	ACTION_TX_CLOSE : 103,
	ACTION_TX_RESP_MISSING : 104,
	ACTION_TX_CONTAINER_FAILED : 105,
	
	queues : {
		txPending : [],
		txBusy : [],
		txRetry : [],
		txDone : [],
		txFailed : []
	},

	apiMinVersion : 1,   
	apiMaxVersion : 3,   
	apiVersion : 1,   
	// the first request always goes with lowest verion, then the client shal
	// max out the version based on the server response
	
	txID : 42,
	txcID : 42,
	millisToNextRetry : 0,

	// the queue operation must be explicitly enabled.
	enabled : false,
	// the operation may be paused upon overload detection
	paused : 0,
	log : [],

	/**
	 * The transaction prototype to use for communication with the server
	 */
	emptyTransaction : {
		// transaction values
		ID : -1,
		type : "",
		tablename : "",
		record : [],
		// result for container
		cresultCode : -1,
		cresultMessage : "",
		// result for transaction
		resultCode : -1,
		resultMessage : "",
		// workflow
		lock : false,
		callback : null,
		// status control
		sentAt : 0,
		retries : 0,
		resultAt : 0,
		closedAt : 0,
		// cache to know where to put changed Ids to
		listRowPos : 0
	},

	/**
	 * the transaction container prototype to use for communication with the
	 * server
	 */
	emptyContainer : {
		version : 0,
		cID : 0,
		cresultCode : 0,
		cresultMessage : "",
		txms : []
	},

	/**
	 * Build a new container based on the provided ransaction. This Javascript
	 * web app omly uses one transaction per container.
	 * 
	 * @params array txx the transactions to use.
	 */
	buildContainer : function(txs) {
		this.txcID++;
		var plain = this.apiVersion + ";" + this.txcID + ";" + $_efaCloudUserID + ";" + $_efaCloudSessionID + ";";
		for (tx of txs) {
			plain += tx.ID + ";" + tx.retries + ";" + tx.type + ";" + tx.tablename
					+ ";";
			tx.record.forEach(function(nvp) {
				while (nvp.indexOf(bTxQueue.MESSAGE_SEPARATOR_STRING) >= 0)
					nvp = nvp.replace(bTxQueue.MESSAGE_SEPARATOR_STRING, bTxQueue.MS_REPLACEMENT_STRING);
				plain += nvp + ";";
			});
			plain = plain.substring(0, plain.length - 1) + bTxQueue.MESSAGE_SEPARATOR_STRING;
		}
		plain = plain.substring(0, plain.length - bTxQueue.MESSAGE_SEPARATOR_STRING.length)
		var base64 = window.btoa(unescape(encodeURIComponent(plain)));
		var base64efa = base64.replace(/\//g, '-').replace(/\+/g, '*').replace(
				/\=/g, '_')
		return base64efa;
	},

	/**
	 * Transcode an object to a record pairs array used by addNewTxToPending()
	 * and others.
	 * 
	 * @params array record the record to transcode.
	 * @return record an array of key-value pairs, key and value are csv
	 *         encoded: key;value.
	 */
	recordToPairs : function(record) {
		var arrayToReturn = [];
		for (key in record) {
			arrayToReturn.push(bToolbox.encodeCsvEntry(key) + ";" + bToolbox.encodeCsvEntry("" + record[key]));
		}
		return arrayToReturn;
	},
	
	/**
	 * Add a transaction based on the tx type, parameters and retry counter to
	 * the pending queue. Set atFront to true, to add at front, else the new
	 * transaction is appended.
	 * 
	 * @params String type transaction type.
	 * @params String tablename The full parameters String, with ";" delimiters
	 *         asf.
	 * @params String record an array of key-value pairs, key and value are csv
	 *         encoded: key;value. They can just be appended to a csv-encoded
	 *         String.
	 * @params int retries count of retries which this transactions has had.
	 * @return added transaction.
	 */
	addNewTxToPending : function(type, tablename, record, retries, callback) {
		// prepare the transaction cache. create a clone of the empty
		// transaction, set values and push it to the queue.
		var tx = Object.assign({}, bTxQueue.emptyTransaction);
		bTxQueue.txID++;
		tx.ID = bTxQueue.txID;
		tx.type = type;
		tx.tablename = tablename;
		tx.record = record;
		tx.retries = retries;
		tx.callback = callback;
		bTxQueue.queues.txPending.push(tx);
		console.log("Pending queue ++: " + bTxQueue.queues.txPending.length);
		return tx;
	},

	/**
	 * Shift transactions from one queue to another.
	 * 
	 * @params String source source queue, e. g. "txPending".
	 * @params String destination destination queue, e. g. "txBusy".
	 * @params int actionToRegister action to register at the transaction
	 *         object.
	 * @params int count count of transactions to be shifted.
	 */
	shiftTransactions : function(source, destination, actionToRegister, count) {
		// read the retry queue contents.
		var sourceQ = bTxQueue.queues[source];
		var destQ = bTxQueue.queues[destination];
		var i = 0;
		while ((sourceQ.length > 0) && ((i < count) || (i == 0))) {
			var txShifted = sourceQ.splice(0, 1)[0];
			if (actionToRegister == this.ACTION_TX_SEND) {
				txShifted.sentAt = Date.now();
			} else if ((actionToRegister == this.ACTION_TX_ABORT)
					|| (actionToRegister == this.ACTION_TX_CLOSE)) {
				txShifted.resultAt = Date.now();
				txShifted.closedAt = Date.now();
			} else if (actionToRegister == this.ACTION_TX_RETRY) {
				txShifted.resultAt = Date.now();
				txShifted.retries++;
			} else if (actionToRegister == this.ACTION_TX_RESP_MISSING) {
				txShifted.resultAt = Date.now();
				txShifted.resultCode = 503;
				txShifted.resultMessage = "No server response in returned transaction response container.";
				txShifted.retries++;
				txShifted.closedAt = Date.now();
			} else if (actionToRegister == this.ACTION_TX_CONTAINER_FAILED) {
				txShifted.resultAt = Date.now();
				txShifted.resultCode = 504;
				txShifted.resultMessage = "Transaction response container failed.";
				txShifted.closedAt = Date.now();
			}
			destQ.push(txShifted);
			i++;
		}
		console.log(source + " (" + sourceQ.length + ") => " + destination
				+ " (" + destQ.length + ", +" + i + ")");
	},

	/**
	 * Check the queue, when the pending timer fires. If no transaction is busy,
	 * send the first transaction in the queue.
	 */
	onPendingTimerEvent : function() {
		// start transaction, if not busy nor enabled and a transaction is
		// pending
		if ((bTxQueue.enabled !== false)
				&& (Date.now() > bTxQueue.paused)
				&& (bTxQueue.queues.txBusy.length == 0)
				&& (bTxQueue.queues.txPending.length > 0)) {
			bTxQueue.shiftTransactions("txPending", "txBusy",
					bTxQueue.ACTION_TX_SEND, 10);
			bTxQueue.sendTxs(bTxQueue.queues.txBusy);
		}
		// move busy transaction into retry queue, if timed out
		// actually this should have already happend within the sendTX callback.
		// This here is just to make sure, nothing is lost, if the callback
		// failed by whatever reason.
		else if (bTxQueue.queues.txBusy.length > 0) {
			var txBusyPeriod = Date.now() - bTxQueue.queues.txBusy[0].sentAt;
			if (txBusyPeriod > (1.1 * $_apiTimeoutMillis)) {
				bTxQueue.shiftTransactions("txBusy", "txRetry",
						this.ACTION_TX_RETRY, 1);
			}
		}
	},

	/**
	 * Move all pending transactions back to the pending queue, when the retry
	 * timer fires.
	 */
	onRetryTimerEvent : function() {
		bTxQueue.millisToNextRetry = $_apiRetryMillis;
		bTxQueue.shiftTransactions("txRetry", "txPending",
				this.ACTION_TX_RETRY, 0);
	},

	/**
	 * parse a server response into a new transaction container.
	 * 
	 * @params String response to be parsed.
	 * @return the transaction container.
	 */
	parseResponse : function(response) {

		var plain = bToolbox.efaBase64toUtf8(response);  
		// javascript native atob doesn't work with UTF-8 Strings.
		
		var txc = Object.assign({}, bTxQueue.emptyContainer);
		var plainParts = plain.split(";", 4);
		
		// parse version and max out
		txc.version = parseInt(plainParts[0]);
		if ((txc.version > this.apiVersion) && (txc.version <= this.apiMaxVersion))
			this.apiVersion = txc.version; 
		else if (txc.version > this.apiVersion)
			this.apiVersion = this.apiMaxVersion; 
		
		txc.cID = parseInt(plainParts[1]);
		txc.cresultCode = parseInt(plainParts[2]);
		txc.cresultMessage = plainParts[3];
		// syntax error check.
		if (plainParts.length < 4) {
			txc.txms = [];
			return txc;
		}

		// javascript substring is different from java.substring. The last
		// element is not the reaminder, but just the last element of the split
		// operation.
		var txcHeaderLength = plainParts[0].length + plainParts[1].length + plainParts[2].length + plainParts[3].length + 4;
		var txmsStr = plain.substring(txcHeaderLength);
		txc.txms = [];
		while (txmsStr.length >= bTxQueue.MESSAGE_SEPARATOR_STRING.length) {
			var indexOfEnd = txmsStr.indexOf(bTxQueue.MESSAGE_SEPARATOR_STRING);
			if (indexOfEnd >= 0) {
				txc.txms.push(txmsStr.substring(0, indexOfEnd));
				txmsStr = txmsStr.substring(indexOfEnd + bTxQueue.MESSAGE_SEPARATOR_STRING.length);
				indexOfEnd = txmsStr.indexOf(bTxQueue.MESSAGE_SEPARATOR_STRING);
			} else {
				txc.txms.push(txmsStr);
				txmsStr = "";
			}
		}

		// parse transaction responses within container.
		txc["txrs"] = [];
		txc.txms.forEach(function(txm) {
			var txr = {};
			txr["ID"] = (txm.indexOf(";") >= 0) ? parseInt(txm.substring(0, txm.indexOf(";"))) : 0;
			txm = txm.substring(txm.indexOf(";") + 1)
			txr["resultCode"] = (txm.indexOf(";") >= 0) ? parseInt(txm.substring(0, txm.indexOf(";"))) : 500;
			txr["resultMessage"] = (txm.indexOf(";") >= 0) ? txm.substring(txm.indexOf(";") + 1) : "";
			txc.txrs.push(txr);
		});
		return txc;
	},

	/**
	 * wrapper for the send procedure. Creates the post request an assigns the
	 * callback procedure for success and failure. Then sends the request. tx
	 * holds the needed callback function.
	 */
	sendTxs : function(txs) {
		var postRequest = new XMLHttpRequest();
		postRequest.timeout = $_apiTimeoutMillis;
		// provide the callback for a response received
		postRequest.onload = function() {
			var txrc;
			if (postRequest.status == 500) {
				txrc = Object.assign({}, bTxQueue.emptyContainer);
				txrc.resultCode = 500;
				txrc.resultMessage = _("Server returned status code 500");
				bTxHandler.handleContainerError(txrc);
			} else {
				txrc = bTxQueue.parseResponse(postRequest.response, tx);
				bTxQueue.handleResponse(txrc);
			}
		};
		// provide the callback for any error.
		postRequest.onerror = function() {
			txrc = Object.assign({}, bTxQueue.emptyContainer);
			txrc.resultCode = 500;
			txrc.resultMessage = _("Client post request error");
			bTxHandler.handleContainerError(txrc);
		};
		// provide the callback for timeout
		postRequest.ontimeout = function() {
			txrc = Object.assign({}, bTxQueue.emptyContainer);
			txrc.resultCode = 500;
			txrc.resultMessage = _("Post request time out");
			bTxHandler.handleContainerError(txrc);
		};
		// send the post request
		postRequest.open('POST', $_clientInterfaceURI, true);
		postRequest.setRequestHeader('Content-type',
				'application/x-www-form-urlencoded; charset=UTF-8');
		let now = Date.now();
		for (tx of txs)
			tx.sentAt = now;
		var txc = bTxQueue.buildContainer(txs);
		postRequest.send("txc=" + txc);
	},

	/**
	 * return a queue status String for debugging
	 */
	getStatus : function() {
		var statusStr = "";
		if (bTxQueue.queues.txPending.length > 0) {
			statusStr += _("Pending:_");
			bTxQueue.queues.txPending.forEach(function(tx) {
				statusStr += "#" + tx.ID + "-" + tx.type + " ";
			});
		} else if (bTxQueue.queues.txBusy.length > 0) {
			statusStr += _("Processing:_#") + bTxQueue.queues.txBusy[0].ID
					+ "-" + bTxQueue.queues.txBusy[0].type + " "
					+ bTxQueue.queues.txBusy[0].tablename + ".";
		} else if (bTxQueue.queues.txDone.length > 0) {
			statusStr += _("Done:_") + bTxQueue.queues.txDone.length;
		}
		return statusStr;
	},

	/**
	 * Clear all queues to ensure a fresh restart. Happens on manual login.
	 */
	clearAllQueues : function() {
		bTxQueue.queues.txPending = [];
		bTxQueue.queues.txBusy = [];
		bTxQueue.queues.txRetry = [];
		bTxQueue.queues.txDone = [];
		bTxQueue.queues.txFailed = [];
	},

	/**
	 * provide a human readable String for a transaction
	 */
	txToString : function(tx) {
		var txStr = "txID" + tx.ID + "; ";
		var recordStr = "";
		tx.record.forEach(function(nvp) {
			recordStr += nvp + ";"
		});
		if (recordStr.length > 50)
			recordStr = recordStr.substring(0, 47) + "..."
		txStr += tx.type + " " + tx.tablename + "(" + recordStr + "); ";
		var sentDate = new Date(tx.sentAt);
		var sentStr = (tx.sentAt == 0) ? _("not_yet_") : sentDate
				.toLocaleDateString($_locale, $_dateFormatDayShort)
				+ " " + sentDate.toLocaleTimeString();
		txStr += _("sent:_") + sentStr + " ";
		var closedDate = new Date(tx.closedAt);
		var messageStr = tx.resultMessage;
		if (messageStr.length > 100)
			messageStr = messageStr.substring(0, 97) + "... (" + messageStr.length + " chars)"; 
		txStr += _("result:_") + tx.resultCode + " " + messageStr + " ";
		if (tx.retries > 0)
			txStr += _("(needed_$1_retries)", tx.retries);
		var closedStr = (tx.closedAt == 0) ? _("not_yet_") : closedDate
				.toLocaleDateString($_locale, $_dateFormatDayShort)
				+ " " + closedDate.toLocaleTimeString();
		txStr += _("closed:_") + closedStr + " ";
		return txStr;
	},

	/**
	 * Provide some information on erroneous transaction to the user.
	 */
	alertError : function(errorText, erroneousTx) {
		if (!erroneousTx) {
			alert(errorText + _("_for_undefined_transaction."));
		} else {
			var parameters = _("undefined");
			try {
				parameters = erroneousTx.parameters.join();
			} catch (ignored) {
			}
			alert(errorText + _("_For_transaction:_") + erroneousTx.type + "("
					+ parameters + _(")_returned_#") + erroneousTx.resultCode
					+ ": " + erroneousTx.resultMessage);
		}
	},

	/**
	 * log a logString message for diagnosis display and history.
	 */
	logActivity : function(logString) {
		var now = new Date();
		var logstamp = now.toLocaleDateString($_locale, $_dateFormatDayShort)
				+ " " + now.toLocaleTimeString() + ": ";
		bTxQueue.log.unshift(logstamp + logString);
		if (bTxQueue.log.length > $_logLimit)
			bTxQueue.log.splice($_logLimit);
		// TODO und was nun damit? siehe bLog.js
	},

	/**
	 * After parsing the response, handle the result in the tx response
	 * container. May be multiple transactions
	 */
	handleResponse : function(txrc) {
		
		if (txrc.cresultCode >= 400) {
			bTxHandler.handleContainerError(txrc);
			return;
		}
		
		let now = Date.now();
		for (txr of txrc.txrs) {
			tx = bTxQueue.queues.txBusy[0];
			if (parseInt(tx.ID) != parseInt(txr.ID)) {
				tx.resultCode = 405; // "Wrong transaction ID."
				tx.resultMessage = "Returned transaction is #" + txr.ID + ". Expected this #" + tx.ID;
				tx.resultAt = now;
				tx.closedAt = now;
			} else {
				tx.resultCode = txr.resultCode;
				tx.resultMessage = txr.resultMessage;
				if (tx.resultCode >= 400) {
					// any error. In case of errors, the transaction ID is not
					// checked
					if (!tx.resultMessage)
						tx.resultMessage = _($_serverResponseTexts[tx.resultCode]);
					bTxHandler.handleTransactionError(tx);
				} else {
					// successful transaction
					let resp_f = tx.type + "_resp";
					if (typeof bTxHandler[resp_f] === "function")
						bTxHandler[resp_f](tx);
					else {
						tx.resultCode = 501;
						tx.resultMessage = "Invalid type '" + tx.type
								+ "' in server response.";
					}
				}
				bTxQueue.shiftTransactions("txBusy", "txDone",
						bTxQueue.ACTION_TX_CLOSE, 1);
				console.log(this.txToString(tx));
			}
		}
	},

	/**
	 * Clear the non-permamnent queues and disable timer execution.
	 */
	reset : function() {
		this.enabled = false;
		this.queues.txPending = [];
		this.queues.txBusy = [];
	}

}

/**
 * Timer to monitor the queue state and send pendign requests.
 */

// timer to call the transactions pending queue.
var txPendingTimer = setInterval(bTxQueue.onPendingTimerEvent,
		$_apiPendingMillis);
// timer to call the transactions retry queue.
var txRetryTimer = setInterval(bTxQueue.onRetryTimerEvent, $_apiRetryMillis);
