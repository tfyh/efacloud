/**
 * Handle all transaction responses from efacloudServer including retry on
 * failures.
 */

var bTxHandler = {

	/**
	 * Encode the transaction parameters based on an associative array.
	 */
	recordToCsv : function(record) {
		var doublets = [];
		for (key in record) {
			// skip empty fields to avoid any type mismatch error, e.g by
			// setting an int with ''.
			if (record[key] || (record[key] === 0))
				doublets.push(key + ";" + bToolbox.encodeCsvEntry(record[key]));
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
		$("#client-state").html($_server_welcome_message);
		
		bLists.downloadLists("@All", 0);
	},

	/**
	 * handle a list, when returned from the server.
	 */
	list_resp : function(tx) {
		let listname = tx.tablename;
		bLists.csvtables[listname] = {};
		bLists.csvtables[listname]["downloaded"] = Date.now();
		bLists.csvtables[listname]["updated"] = 0;
		bLists.csvtables[listname]["data"] = tx.resultMessage;
		bLists.updateList(listname);
	},

	/**
	 * handle the response for an insert statement, i. e. reload the respective list from the server
	 */
	insert_resp : function(tx) {
		for (listname of bLists._listsForTables[tx.tablename])
			bLists.downloadLists(listname, parseInt(Date.now() / 1000) - 30 * 86400);
	},

	/**
	 * handle the response for an update statement.
	 */
	update_resp : function(tx) {
		for (listname of bLists._listsForTables[tx.tablename])
			bLists.downloadLists(listname, parseInt(Date.now() / 1000) - 30 * 86400);
	},

	/**
	 * handle the response for an update statement.
	 */
	select_resp : function(tx) {
		let list = bToolbox.readCsvList(tx.resultMessage);
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
		bTxQueue.queues.txBusy.forEach(function(tx) {
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
			bTxQueue.shiftTransactions("txBusy", "txFailed",
					this.ACTION_TX_FAILED, 1);
			break;
		case 404:
		case 406:
		case 500:
			bTxQueue.shiftTransactions("txBusy", "txRetry",
					this.ACTION_TX_RETRY, 1);
			break;
		}
		if (txrc.cresultCode == 406) {
			alert(_("Server Overload detected, more than the allowed actions or errors within the last hour.\n"
					+ "You now have to wait for an hour and should inform the administrator of the application."));
			bTxQueue.paused = Date.now() + 3600000;
			bPanel.update();
		}

		// reload the page, if the login failed. This will either restart efaWeb or provide an appropriate error.
		if ((txrc.cresultCode == 402) || (txrc.cresultCode == 403)) 
			location.reload();
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
			bTxQueue.shiftTransactions("txBusy", "txRetry",
					bTxQueue.ACTION_TX_RETRY, 1);
		}
		// cases for abortion. Do nothing. The transaction is closed in the
		// calling method.
		if ((tx.resultCode == 400) // 400 : "XHTTPrequest Error.",
				|| (tx.resultCode == 401) // 401 : "Syntax error.",
				|| (tx.resultCode == 407) // 407 : "No data base connection.",
				|| (tx.resultCode == 500) // 500 : "Internal server error.",
				|| (tx.resultCode == 501) // 501 : "Transaction invalid.",
				|| (tx.resultCode == 502)) { // 502 : "Transaction failed."
			bTxQueue.shiftTransactions("txBusy", "txFailed",
					bTxQueue.ACTION_TX_ABORT, 1);
		}
	}
}