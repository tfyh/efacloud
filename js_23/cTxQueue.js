/**
 *
 *       efaCloud
 *       --------
 *       https://www.efacloud.org
 *
 * Copyright  2018-2024  Martin Glade
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Queue of transaction requests for communication with efacloudServer. Compile
 * and send messages, asynchronously collect of responses or communication
 * errors, parse responses and hand-over the response or error to cTxHandler.
 */

var cTxQueue = {

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
	apiMaxVersion : $_max_api_version_jApp,   
	apiVersion : 1,   
	// the first request always goes with lowest version, then the client shall
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
		// max out the version. $_max_api_version_server value will be set on
		// nop response.
		this.apiVersion = Math.min($_max_api_version_server, $_max_api_version_jApp);
		var plain = this.apiVersion + ";" + this.txcID + ";@" + $_apiUserID + ";" + $_apiSessionID + ";";
		for (tx of txs) {
			plain += tx.ID + ";" + tx.retries + ";" + tx.type + ";" + tx.tablename
					+ ";";
			tx.record.forEach(function(nvp) {
				while (nvp.indexOf(cTxQueue.MESSAGE_SEPARATOR_STRING) >= 0)
					nvp = nvp.replace(cTxQueue.MESSAGE_SEPARATOR_STRING, cTxQueue.MS_REPLACEMENT_STRING);
				plain += nvp + ";";
			});
			plain = plain.substring(0, plain.length - 1) + cTxQueue.MESSAGE_SEPARATOR_STRING;
		}
		plain = plain.substring(0, plain.length - cTxQueue.MESSAGE_SEPARATOR_STRING.length)
		var base64 = window.btoa(unescape(encodeURIComponent(plain)));
		var base64api = base64.replace(/\//g, '-').replace(/\+/g, '*').replace(
				/\=/g, '_');
		console.log(_("xACtaq|V%1 container built: %2 ...", this.apiVersion,
				txs.length, base64api.length));
		return base64api;
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
			arrayToReturn.push(cToolbox.encodeCsvEntry(key) + ";" + cToolbox.encodeCsvEntry("" + record[key]));
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
	 * @params function callback: a function which cann be executed after the
	 *         response was received, e.g. a data refresh
	 * @params function onError: a function which cann be executed if the
	 *         response indicates an error. A log is always posted to the
	 *         cobnsole, but if the user shall be notified, put the respectve
	 *         function here. It must take the transaction itself as function
	 *         argument.
	 * @return added transaction.
	 */
	addNewTxToPending : function(type, tablename, record, retries, callback, onError) {
		// prepare the transaction cache. create a clone of the empty
		// transaction, set values and push it to the queue.
		var tx = Object.assign({}, cTxQueue.emptyTransaction);
		cTxQueue.txID++;
		tx.ID = cTxQueue.txID;
		tx.type = type;
		tx.tablename = tablename;
		tx.record = record;
		tx.retries = retries;
		tx.callback = callback;
		tx.onError = onError;
		cTxQueue.queues.txPending.push(tx);
		console.log(_("c7OduW|Pending queue") + " ++: " + cTxQueue.queues.txPending.length);
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
		var sourceQ = cTxQueue.queues[source];
		var destQ = cTxQueue.queues[destination];
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
				txShifted.resultMessage = _("3nigIB|No server response in re...");
				txShifted.retries++;
				txShifted.closedAt = Date.now();
			} else if (actionToRegister == this.ACTION_TX_CONTAINER_FAILED) {
				txShifted.resultAt = Date.now();
				txShifted.resultCode = 504;
				txShifted.resultMessage = _("7dNVv9|Transaction response con...");
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
		if ((cTxQueue.enabled !== false)
				&& (Date.now() > cTxQueue.paused)
				&& (cTxQueue.queues.txBusy.length == 0)
				&& (cTxQueue.queues.txPending.length > 0)) {
			cTxQueue.shiftTransactions("txPending", "txBusy",
					cTxQueue.ACTION_TX_SEND, 10);
			cTxQueue.sendTxs(cTxQueue.queues.txBusy);
		}
		// move busy transaction into retry queue, if timed out
		// actually this should have already happend within the sendTX callback.
		// This here is just to make sure, nothing is lost, if the callback
		// failed by whatever reason.
		else if (cTxQueue.queues.txBusy.length > 0) {
			var txBusyPeriod = Date.now() - cTxQueue.queues.txBusy[0].sentAt;
			if (txBusyPeriod > (1.1 * $_apiTimeoutMillis)) {
				cTxQueue.shiftTransactions("txBusy", "txRetry",
						this.ACTION_TX_RETRY, 1);
			}
		}
	},

	/**
	 * Move all pending transactions back to the pending queue, when the retry
	 * timer fires.
	 */
	onRetryTimerEvent : function() {
		cTxQueue.millisToNextRetry = $_apiRetryMillis;
		cTxQueue.shiftTransactions("txRetry", "txPending",
				this.ACTION_TX_RETRY, 0);
	},

	/**
	 * parse a server response into a new transaction container.
	 * 
	 * @params String response to be parsed.
	 * @return the transaction container.
	 */
	parseResponse : function(response) {

		var plain = cToolbox.base64apiToUtf8(response);  
		// javascript native atob doesn't work with UTF-8 Strings.
		
		var txc = Object.assign({}, cTxQueue.emptyContainer);
		var plainParts = plain.split(";", 4);
		
		txc.cApiVersion = parseInt(plainParts[0]);
		txc.cID = parseInt(plainParts[1]);
		txc.cresultCode = parseInt(plainParts[2]);
		txc.cresultMessage = plainParts[3];
		// gracefully handle API version incompatibility, if the result ist Ok.
		if (txc.cApiVersion > this.apiMaxVersion) {
			txc.cresultCode = (txc.cresultCode < 302) ? 302 : txc.cresultCode;
			txc.cresultMessage = _("ui3jKY|WARNING: API version of ...",
					txc.cApiVersion, this.apiMaxVersion);
		}
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
		while (txmsStr.length > 0) {
			var indexOfEnd = txmsStr.indexOf(cTxQueue.MESSAGE_SEPARATOR_STRING);
			if (indexOfEnd >= 0) {
				txc.txms.push(txmsStr.substring(0, indexOfEnd));
				txmsStr = txmsStr.substring(indexOfEnd + cTxQueue.MESSAGE_SEPARATOR_STRING.length);
				indexOfEnd = txmsStr.indexOf(cTxQueue.MESSAGE_SEPARATOR_STRING);
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
	 * callback procedure for container success (cTxQueue.handleResponse) and
	 * failure (cTxHandler.handleContainerError). Then sends the request.
	 */
	sendTxs : function(txs) {
		var postRequest = new XMLHttpRequest();
		postRequest.timeout = $_apiTimeoutMillis;
		// provide the callback for a response received
		postRequest.onload = function() {
			var txrc;
			if (postRequest.status == 500) {
				txrc = Object.assign({}, cTxQueue.emptyContainer);
				txrc.resultCode = 500;
				txrc.resultMessage = _("EJM8uI|Server returned status c...");
				cTxHandler.handleContainerError(txrc);
			} else {
				txrc = cTxQueue.parseResponse(postRequest.response, tx);
				cTxQueue.handleResponse(txrc);
			}
		};
		// provide the callback for any error.
		postRequest.onerror = function() {
			txrc = Object.assign({}, cTxQueue.emptyContainer);
			txrc.resultCode = 500;
			txrc.resultMessage = _("cbHhdY|Client post request erro...");
			cTxHandler.handleContainerError(txrc);
		};
		// provide the callback for timeout
		postRequest.ontimeout = function() {
			txrc = Object.assign({}, cTxQueue.emptyContainer);
			txrc.resultCode = 500;
			txrc.resultMessage = _("8ySDU0|Post request time out");
			cTxHandler.handleContainerError(txrc);
		};
		// send the post request
		postRequest.open('POST', $_apiPostURI, true);
		postRequest.setRequestHeader('Content-type',
				'application/x-www-form-urlencoded; charset=UTF-8');
		let now = Date.now();
		for (tx of txs)
			tx.sentAt = now;
		var txc = cTxQueue.buildContainer(txs);
		postRequest.send("txc=" + txc);
	},

	/**
	 * return a queue status String for debugging
	 */
	getStatus : function() {
		var statusStr = "";
		if (cTxQueue.queues.txPending.length > 0) {
			statusStr += _("2V7GRC|Pending:") + " ";
			cTxQueue.queues.txPending.forEach(function(tx) {
				statusStr += "#" + tx.ID + "-" + tx.type + " ";
			});
		} else if (cTxQueue.queues.txBusy.length > 0) {
			statusStr += _("3LxQTm|Processing:") + " #"+ cTxQueue.queues.txBusy[0].ID
					+ "-" + cTxQueue.queues.txBusy[0].type + " "
					+ cTxQueue.queues.txBusy[0].tablename + ".";
		} else if (cTxQueue.queues.txDone.length > 0) {
			statusStr += _("u2Ih6w|Done:") + " " + cTxQueue.queues.txDone.length;
		}
		return statusStr;
	},

	/**
	 * Clear all queues to ensure a fresh restart. Happens on manual login.
	 */
	clearAllQueues : function() {
		cTxQueue.queues.txPending = [];
		cTxQueue.queues.txBusy = [];
		cTxQueue.queues.txRetry = [];
		cTxQueue.queues.txDone = [];
		cTxQueue.queues.txFailed = [];
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
		var sentStr = (tx.sentAt == 0) ? _("b1g10I|not yet ") : sentDate
				.toLocaleDateString($_locale, $_dateFormatDayShort)
				+ " " + sentDate.toLocaleTimeString();
		txStr += _("Z5LcTj|sent: ") + sentStr + " ";
		var closedDate = new Date(tx.closedAt);
		var messageStr = tx.resultMessage;
		if (messageStr.length > 100)
			messageStr = messageStr.substring(0, 97) + "... (" + messageStr.length + " chars)"; 
		txStr += _("YcDS4y|result: ") + tx.resultCode + " " + messageStr + " ";
		if (tx.retries > 0)
			txStr += _("qZaY4N|(needed %1 retries)", tx.retries);
		var closedStr = (tx.closedAt == 0) ? _("ncPBhO|not yet ") : closedDate
				.toLocaleDateString($_locale, $_dateFormatDayShort)
				+ " " + closedDate.toLocaleTimeString();
		txStr += _("ofYJXP|closed: ") + closedStr + " ";
		return txStr;
	},

	/**
	 * log a logString message for diagnosis display and history.
	 */
	logActivity : function(logString) {
		var now = new Date();
		var logstamp = now.toLocaleDateString($_locale, $_dateFormatDayShort)
				+ " " + now.toLocaleTimeString() + ": ";
		cTxQueue.log.unshift(logstamp + logString);
		if (cTxQueue.log.length > $_logLimit)
			cTxQueue.log.splice($_logLimit);
		// TODO und was nun damit? siehe bLog.js
	},

	/**
	 * After parsing the response, handle the result in the tx response
	 * container. May be multiple transactions
	 */
	handleResponse : function(txrc) {
		
		if (txrc.cresultCode >= 400) {
			cTxHandler.handleContainerError(txrc);
			return;
		}
		
		let now = Date.now();
		var tc = 1;
		for (txr of txrc.txrs) {
			tx = cTxQueue.queues.txBusy[0];
			if (tx) {
				if (parseInt(tx.ID) != parseInt(txr.ID)) {
					tx.resultCode = 405; // "Wrong transaction ID."
					tx.resultMessage = _("oaIKyl|Returned transaction is ...", txr.ID, tx.ID);
					tx.resultAt = now;
					tx.closedAt = now;
				} else {
					tx.resultCode = txr.resultCode;
					tx.resultMessage = txr.resultMessage;
					if ((tx.resultCode >= 400) || (tx.resultCode == 304)) {
						// any error. In case of errors, the transaction ID is
						// not checked
						if (!tx.resultMessage)
							tx.resultMessage = _($_resultMessages[tx.resultCode]);
						cTxHandler.handleTransactionError(tx);
					} else {
						// successful transaction
						let resp_f = tx.type + "_resp";
						if (typeof cTxHandler[resp_f] === "function")
							cTxHandler[resp_f](tx);
						else {
							tx.resultCode = 501;
							tx.resultMessage = _("x9Civl|Invalid type °%1° in ser...", tx.type);
						}
					}
					cTxQueue.shiftTransactions("txBusy", "txDone",
							cTxQueue.ACTION_TX_CLOSE, 1);
					console.log(this.txToString(tx));
				}
			} else {
				console.log("WARNING: transaction #%1 of %2 is undefined @cTxQueue.handleResponse.", 
						tx.type, txrc.txrs.length);
			}
			tc ++;
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
var txPendingTimer = setInterval(cTxQueue.onPendingTimerEvent,
		$_apiPendingMillis);
// timer to call the transactions retry queue.
var txRetryTimer = setInterval(cTxQueue.onRetryTimerEvent, $_apiRetryMillis);
