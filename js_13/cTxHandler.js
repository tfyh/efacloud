/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2022 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

/**
 * Handle all transaction responses from efacloudServer including retry on
 * failures.
 */

var cTxHandler = {

	/**
	 * Encode the transaction parameters based on an associative array.
	 */
	recordToCsv : function(record) {
		var doublets = [];
		for (key in record) {
			// skip empty fields to avoid any type mismatch error, e.g by
			// setting an int with ''.
			if (record[key] || (record[key] === 0))
				doublets.push(key + ";" + cToolbox.encodeCsvEntry(record[key]));
		}
		return doublets;
	},

	/**
	 * Response on nop request. This will completely refresh the local memory.
	 */
	nop_resp : function(tx) {
		var responseElements = tx.resultMessage.split(/;/g);
		var responseFields = {};
		for (responseElement of responseElements)
			if (responseElement.indexOf("=") > 0)
				responseFields[responseElement.split("=")[0]] = responseElement.split("=")[1];
		
		// Note: synch periods must never be 0.
		$_synch_check_period = (parseInt(responseFields["synch_check_period"])) ? parseInt(responseFields["synch_check_period"]) : $_synch_check_period;
		$_synch_period = (parseInt(responseFields["synch_period"])) ? parseInt(responseFields["synch_period"]) : $_synch_period;
		$_server_welcome_message = (responseFields["server_welcome_message"]) ? responseFields["server_welcome_message"].replace(/\/\//g, "<br>") : $_server_welcome_message;
		$_max_api_version_server = (responseFields["max_api_version_server"]) ? parseInt(responseFields["max_api_version_server"]) : 1;
		// user concessions are needed to know which boathouses are allowed for the user.
		$_user_concessions = (responseFields["user_concessions"]) ? parseInt(responseFields["user_concessions"]) : 0;
		$_userConfig.init();
		// log to the console
		console.log("NOP-Response received:\n" + $_server_welcome_message.replace(/\<br\>/g, "\n") + "\nMaximum API version of server is V" + $_max_api_version_server);

		$("#client-state").html($_server_welcome_message);
		
		cLists.downloadLists("@All", 0);
	},

	/**
	 * handle the response for an insert statement, i. e. execute the callback
	 * with the transaction provided and reload the changed list from the server
	 */
	insert_resp : function(tx) {
		if (tx.callback)
			tx.callback(tx);
		for (listname of cLists.defs.listsForTables[tx.tablename])
			cLists.downloadLists(listname, parseInt(Date.now() / 1000) - 86400);
		// lists are merged into the existing, therefore one day back is enough
	},

	/**
	 * handle the response for an update statement, identical to the one for
	 * insert_resp.
	 */
	update_resp : function(tx) {
		if (tx.callback)
			tx.callback(tx);
		for (listname of cLists.defs.listsForTables[tx.tablename])
			cLists.downloadLists(listname, parseInt(Date.now() / 1000) - 86400);
		// lists are merged into the existing, therefore one day back is enough
	},

	/**
	 * handle the response for an update statement, identical to the one for
	 * insert_resp.
	 */
	delete_resp : function(tx) {
		if (tx.callback)
			tx.callback(tx);
		for (listname of cLists.defs.listsForTables[tx.tablename])
			cLists.downloadLists(listname, parseInt(Date.now() / 1000) - 86400);
		// lists are merged into the existing, therefore one day back is enough
	},

	/**
	 * handle a list, when returned from the server.
	 */
	list_resp : function(tx) {
		let listname = tx.tablename;
		cLists.csvtables[listname] = {};
		cLists.csvtables[listname]["downloaded"] = Date.now();
		cLists.csvtables[listname]["updated"] = 0;
		cLists.csvtables[listname]["data"] = tx.resultMessage;
		if (! cLists.defs.noCaching.includes(listname)) {
			window.localStorage.setItem(listname + "_data", cLists.csvtables[listname]["data"]);
			window.localStorage.setItem(listname + "_downloaded", cLists.csvtables[listname]["downloaded"]);
		}
		cLists.updateList(listname);
	},

	/**
	 * handle the response for an update statement.
	 */
	select_resp : function(tx) {
		let list = cToolbox.readCsvList(tx.resultMessage);
		// update the corresponding list records
		list.forEach(function(record) {
			cLists.mergeRecord(record);
		});
		// do the requested (UI) callback
		tx.callback(list);
	},

	/**
	 * Handle the error for a transaction container. The transactions go to the
	 * failed queue for: 401 => "Syntax error.", 402 => "Unknown client.", 403 =>
	 * "Authentication failed.". The transactions got to the retry queue for:
	 * 404 => "Server side busy.", 406 => "Overload detected.", 407 => "No data
	 * base connection.", 500 => "Transaction container aborted."
	 * 
	 * Errors 402 => "Unknown client.", 403 => "Authentication failed.", will
	 * clear the stored credentials and trigger reload of the entire page.
	 * 
	 * @param txrc
	 *            container to handle the error for
	 */
	handleContainerError : function(txrc) {
		cTxQueue.queues.txBusy.forEach(function(tx) {
			tx.cresultCode = txrc.cresultCode;
			tx.cresultMessage = txrc.cresultMessage;
			tx.resultAt = Date.now();
		});
		switch (txrc.cresultCode) {
		case 401:
		case 402:
		case 403:
		case 407:
		default:
			cTxQueue.shiftTransactions("txBusy", "txFailed",
					this.ACTION_TX_FAILED, 1);
			break;
		case 404:
		case 406:
		case 500:
			cTxQueue.shiftTransactions("txBusy", "txRetry",
					this.ACTION_TX_RETRY, 1);
			break;
		}
		if (txrc.cresultCode == 406) {
			alert(_("Server Overload detected, more than the allowed actions or errors within the last hour.\n"
					+ "You now have to wait for an hour and should inform the administrator of the application."));
			cTxQueue.paused = Date.now() + 3600000;
			bPanel.update();
		}

		// provide an appropriate error, if the login failed.
		if ((txrc.cresultCode == 402) || (txrc.cresultCode == 403)) 
			location.href = "../pages/error.php";
	},

	// display an error information within the modal 
	showTxError : function(tx) {
		let functionTexts = {
				"insert" : "<p>Das Einfügen des Datensatzes in die Tabelle " + tx.tablename + " war nicht möglich.</p>",
				"update" : "<p>Das Ändern des Datensatzes der Tabelle " + tx.tablename + " war nicht möglich.</p>",
				"delete" : "<p>Das Löschen des Datensatzes der Tabelle " + tx.tablename + " war nicht möglich.</p>"
		}
		cModal.showHtml("<h3>Das hat leider nicht geklappt</h3>" + functionTexts[tx.type] + "<p>Der Server antwortet:<br>" + tx.resultMessage + "</p>");
	},
	
	/**
	 * Handling transaction failures
	 */
	handleTransactionError : function(tx) {
		// cases for retry. A copy of the transaction is appended to the retry
		// queue
		if ((tx.resultCode == 400) // 400 : "XHTTPrequest Error.",
				|| (tx.resultCode == 404) // 404 : "Server side busy.",
				|| (tx.resultCode == 405) // 405 : "Wrong transaction ID.",
				|| (tx.resultCode == 406)) { // 406 : "Overload detected.",
			cTxQueue.shiftTransactions("txBusy", "txRetry",
					cTxQueue.ACTION_TX_RETRY, 1);
		}
		// cases for abortion. Do nothing. The transaction is closed in the
		// calling method.
		if ((tx.resultCode == 304) // 304 : "Transaction forbidden.",
				|| (tx.resultCode == 400) // 400 : "XHTTPrequest Error.",
				|| (tx.resultCode == 401) // 401 : "Syntax error.",
				|| (tx.resultCode == 407) // 407 : "No data base connection.",
				|| (tx.resultCode == 500) // 500 : "Internal server error.",
				|| (tx.resultCode == 501) // 501 : "Transaction invalid.",
				|| (tx.resultCode == 502)) { // 502 : "Transaction failed."
			if (tx.onError)
				tx.onError(tx);
			cTxQueue.shiftTransactions("txBusy", "txFailed",
					cTxQueue.ACTION_TX_ABORT, 1);
		}
	}
}