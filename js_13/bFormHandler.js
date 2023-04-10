/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2022 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

/**
 * Handle the display of forms, and apply he necessary logic to the results
 * provided. For each form the is a form_do and a form_done function. The
 * form_do prepares the form and shows it to the user, the form_done is called
 * when the form is submitted. It provides all data postprocessing, writes the
 * data to the local lists and sends a write transaction to the server.
 */

var bFormHandler = {

	processedData : {},

	/**
	 * Open Form "changeLogbook".
	 */
	changeLogbook_do : function() {
		cForm.init("changeLogbook");
		cForm.presetValue("logbookname", $_logbookname);
		cModal.showForm(true, bFormHandler);
	},

	/**
	 * Form "changeLogbook" is submitted. All memory content is cleared and
	 * refreshed.
	 */
	changeLogbook_done : function() {
		var logbookIndex = (cForm.inputs["logbookname"]) ? cForm.inputs["logbookname"]
				: 0;
		$_logbookname = $_userConfig.logbooksAllowed[logbookIndex];
		_refreshEfaWeb();
	},

	/**
	 * Set basic fields for session form
	 */
	_setSessionInputFields : function() {
		var input = $("#cFormInput-BoatId")[0];
		var options = Object
				.keys(cLists.names.efaweb_virtual_boatVariants_names);
		autocomplete(input, options, efaInputValidator, "efaweb_virtual_boatVariants");
		// The list name "efaweb_virtual_boatVariants" is only used as prefix,
		// no worry that the list itself does not exist
		input = $("#cFormInput-CoxId")[0];
		options = (cLists.names.efaWeb_persons_names) ? Object
				.keys(cLists.names.efaWeb_persons_names) : [];
		autocomplete(input, options, efaInputValidator, "efaWeb_persons");
		for (var i = 1; i <= 8; i++) {
			input = $("#cFormInput-Crew" + i + "Id")[0];
			autocomplete(input, options, efaInputValidator, "efaWeb_persons");
		}
		input = $("#cFormInput-DestinationId")[0];
		options = (cLists.names.efaWeb_destinations_names) ? Object
				.keys(cLists.names.efaWeb_destinations_names) : [];
		autocomplete(input, options, efaInputValidator, "efaWeb_destinations");
		input = $("#cFormInput-WatersIdList")[0];
		options = (cLists.names.efaWeb_waters_names) ? Object
				.keys(cLists.names.efaWeb_waters_names) : [];
		autocomplete(input, options, efaInputValidator, "efaWeb_waters");
	},

	/**
	 * Open Form "startSession".
	 */
	startSession_do : function(preset) {
		cForm.init("startSession");
		cForm.presetValue("EntryId", "" + cLists.defs.nextEntryId());
		cForm.presetValue("Logbookname", $_logbookname);
		if (preset)
			for (key in preset)
				cForm.presetValue(key, preset[key]);
		cModal.showForm(false, bFormHandler);
		this._setSessionInputFields();
	},

	/**
	 * Form "startSession" is submitted. Parse result and store it. Distinguish
	 * a new trip start from a change or the trip end entry by checking for the
	 * existence of the used entry id.
	 */
	startSession_done : function() {

		var record = Object.assign(cForm.inputs);
		// make sure the entry Id is computed at the server side to avoid
		// conflicts.
		delete record.EntryId;

		// resolve UUIDs
		record = oSession.resolveSessionData(record);
		record["BoatCaptain"] = 1; // no option to change default in efaWeb.

		// Add Date, Time and Open flag
		record["Date"] = cForm.inputs["Date"];
		var startTime = cToolbox.format_time_DE(cForm.inputs["StartTime"])
				.substring(0, 5);
		record["StartTime"] = startTime;
		record["Open"] = "true";
		delete record["EndDate"];
		delete record["EndTime"];
		// and switch guid to names for invalid members
		oPerson.guidsToNamesForInvalidPersons(record);

		// system generated fields will be added at the server side from
		// API-level V3 onwards

		// send trip to server. Callback is boat status update.
		var tx;
		var pairs = cTxQueue.recordToPairs(record);
		tx = cTxQueue.addNewTxToPending("insert", "efa2logbook", pairs, 0,
				oBoat.updateBoatStatusOnSession, cTxHandler.showTxError);

	},

	/**
	 * Open Form "lateEntry". This is very similar to start session. Only the
	 * end date / end time data fields are additionally provided.
	 */
	lateEntry_do : function(preset) {
		cForm.init("lateEntry");
		cForm.presetValue("EntryId", "" + cLists.defs.nextEntryId());
		cForm.presetValue("Logbookname", $_logbookname);
		cForm.presetValue("Date", cToolbox.dateNow());
		cForm.presetValue("StartTime", cToolbox.timeNow());
		cForm.presetValue("SessionType", "LATEENTRY");
		if (preset)
			for (key in preset)
				cForm.presetValue(key, preset[key]);
		cModal.showForm(false, bFormHandler);
		this._setSessionInputFields();
		$('#cFormInput-SessionType').attr('disabled', 'disabled');
	},

	/**
	 * Form "lateEntry" is submitted. Parse result and store it. Very similar to
	 * startSession, but the boat status is not changed and the session is not
	 * declared to be open.
	 */
	lateEntry_done : function() {

		var record = Object.assign(cForm.inputs);
		// make sure the entry Id is computed at the server side to avoid
		// conflicts.
		delete record.EntryId;

		// resolve UUIDs
		record = oSession.resolveSessionData(record);
		record["BoatCaptain"] = 1; // no option to change default in efaWeb.

		// Add Date, Time and Open flag
		record["Date"] = cForm.inputs["Date"];
		var startTime = cToolbox.format_time_DE(cForm.inputs["StartTime"])
				.substring(0, 5);
		record["StartTime"] = startTime;
		if (cForm.inputs["EndDate"])
			record["EndDate"] = cForm.inputs["EndDate"];
		else
			delete record["EndDate"];
		var endTime = cToolbox.format_time_DE(cForm.inputs["EndTime"])
				.substring(0, 5);
		if (endTime.toLowerCase().substring(0, 3).localeCompare("nan") == 0)
			record["EndTime"] = "23:59";
		else
			record["EndTime"] = endTime;
		record["Open"] = "";
		// and switch guid to names for invalid members
		oPerson.guidsToNamesForInvalidPersons(record);

		// system generated fields will be added at the server side from
		// API-level V3 onwards

		// send trip to server. Callback is panel update.
		var tx;
		var pairs = cTxQueue.recordToPairs(record);
		tx = cTxQueue.addNewTxToPending("insert", "efa2logbook", pairs, 0,
				bPanel.update, cTxHandler.showTxError);

	},

	/**
	 * Open Form "endSession".
	 */
	endSession_do : function(preset) {
		cForm.init("endSession");
		if (preset)
			for (key in preset)
				cForm.presetValue(key, preset[key]);
		if (!preset || !preset["EntryId"]) {
			alert("Kann die Fahrt nicht beenden, unvollständige Daten.");
			return;
		}
		cModal.showForm(false, bFormHandler);
		this._setSessionInputFields();
	},

	/**
	 * Form "endSession" is submitted. Parse result and store it. Distinguish a
	 * new trip start from a change or the trip end entry by checking for the
	 * existence of the used entry id.
	 */
	endSession_done : function() {

		var record = Object.assign(cForm.inputs);
		var entryId = parseInt(record["EntryId"]);
		var tripRow = cLists.indices.efaWeb_logbook_nids[entryId];
		var trip = cLists.lists.efaWeb_logbook[tripRow];
		if (!trip) {
			alert("Fahrt Nummer " + entryId + " konnte nicht gefunden werden.");
			return;
		}
		record["ecrid"] = trip["ecrid"];

		// resolve UUIDs
		record = oSession.resolveSessionData(record);

		// Add Date, Time and Open flag
		record["Open"] = "false";
		record["EndTime"] = cToolbox.format_time_DE(cForm.inputs["EndTime"])
				.substring(0, 5);
		if (!cForm.inputs["EndDate"]
				|| (cForm.inputs["EndDate"].localeCompare(record["Date"]) == 0))
			delete record["EndDate"];
		else
			record["EndDate"] = cForm.inputs["EndDate"];

		// and switch guid to names for invalid members
		oPerson.guidsToNamesForInvalidPersons(record);

		// send trip to server. Callback is boat status update.
		var tx;
		var pairs = cTxQueue.recordToPairs(record);
		tx = cTxQueue.addNewTxToPending("update", "efa2logbook", pairs, 0,
				oBoat.updateBoatStatusOnSession, cTxHandler.showTxError);
	},

	/**
	 * Open Form "updateSession".
	 */
	updateSession_do : function(preset) {
		cForm.init("updateSession");
		if (preset)
			for (key in preset)
				cForm.presetValue(key, preset[key]);
		if (!preset || !preset["EntryId"]) {
			alert("Kann die Fahrt nicht aktualisieren, unvollständige Daten.");
			return;
		}
		cModal.showForm(false, bFormHandler);
		this._setSessionInputFields();
	},

	/**
	 * Form "updateSession" is submitted. Very similar what to do when starting
	 * a session.
	 */
	updateSession_done : function() {

		var record = Object.assign(cForm.inputs);
		var entryId = parseInt(record["EntryId"]);
		var tripRow = cLists.indices.efaWeb_logbook_nids[entryId];
		var trip = cLists.lists.efaWeb_logbook[tripRow];
		if (!trip) {
			alert("Fahrt Nummer " + entryId + " konnte nicht gefunden werden.");
			return;
		}
		record["ecrid"] = trip["ecrid"];

		// resolve UUIDs
		record = oSession.resolveSessionData(record);

		// Add Date, Time and Open flag
		record["Open"] = "true";
		record["EndTime"] = cToolbox.format_time_DE(cForm.inputs["EndTime"])
				.substring(0, 5);
		if (!cForm.inputs["EndDate"]
				|| (cForm.inputs["EndDate"].localeCompare(record["Date"]) == 0))
			delete record["EndDate"];
		else
			record["EndDate"] = cForm.inputs["EndDate"];

		// and switch guid to names for invalid members
		oPerson.guidsToNamesForInvalidPersons(record);

		// send trip to server. Callback is boat status update.
		var tx;
		var pairs = cTxQueue.recordToPairs(record);
		tx = cTxQueue.addNewTxToPending("update", "efa2logbook", pairs, 0,
				oBoat.updateBoatStatusOnSession, cTxHandler.showTxError);
	},

	/**
	 * Open Form "cancelSession".
	 */
	cancelSession_do : function(preset) {
		cForm.init("cancelSession");
		if (preset)
			for (key in preset)
				cForm.presetValue(key, preset[key]);
		if (!preset || !preset["EntryId"]) {
			alert("Kann die Fahrt nicht abbrechen, unvollständige Daten.");
			return;
		}
		cModal.showForm(false, bFormHandler);
		this._setSessionInputFields();
	},

	/**
	 * Form "updateSession" is submitted. Very similar what to do when starting
	 * a session.
	 */
	cancelSession_done : function() {
		var record = Object.assign(cForm.inputs);
		// distinguish insert from modify
		var entryId = parseInt(record["EntryId"]);
		var tripRow = cLists.indices.efaWeb_logbook_nids[entryId];
		var session = cLists.lists.efaWeb_logbook[tripRow];
		if (!session) {
			alert("Fahrt Nummer " + entryId + " konnte nicht gefunden werden.");
			return;
		}
		// for a deletion it would be sufficient to send the ecrid, but to
		// be able to set the boat status to "AVAILABLE" the BoatId needs to be
		// remembered.
		record["ecrid"] = session["ecrid"];
		// resolve UUIDs
		record = oSession.resolveSessionData(record);

		// send session delete request to server. Callback is boat status
		// update.
		var tx;
		var pairs = cTxQueue.recordToPairs(record);
		tx = cTxQueue.addNewTxToPending("delete", "efa2logbook", pairs, 0,
				oBoat.updateBoatStatusOnCancel, cTxHandler.showTxError);
	},

	/**
	 * Open Form "postDamage".
	 */
	postDamage_do : function() {
		cForm.init("postDamage");
		cForm.presetValue("ReportDate", cToolbox.dateNow());
		cForm.presetValue("ReportTime", cToolbox.timeNow());
		cForm.presetValue("Claim", "");
		cModal.showForm(false, bFormHandler);
		var input = $("#cFormInput-BoatId")[0];
		var options = Object.keys(cLists.names.efaWeb_boats_names);
		autocomplete(input, options, efaInputValidator, "efaWeb_boats");
		input = $("#cFormInput-ReportedByPersonId")[0];
		options = Object.keys(cLists.names.efaWeb_persons_names);
		autocomplete(input, options, efaInputValidator, "efaWeb_persons");
	},

	/**
	 * Form "postDamage" submitted. Parse result and store it.
	 */
	postDamage_done : function() {
		var record = Object.assign(cForm.inputs);
		// resolve names
		record["BoatId"] = cLists.names.efaWeb_boats_names[cForm.inputs["BoatId"]];
		record["ReportedByPersonId"] = cLists.names.efaWeb_persons_names[cForm.inputs["ReportedByPersonId"]];
		delete record["ReportedByPersonName"];
		if (!record["ReportedByPersonId"]) {
			delete record["ReportedByPersonId"];
			record["ReportedByPersonName"] = cForm.inputs["ReportedByPersonId"];
		}
		// retrieve logbook text
		var logbookRow = cLists.indices.efaWeb_logbook_nids[cForm.inputs["LogbookText"]];
		record["LogbookText"] = "";
		if (!logbookRow)
			record["LogbookText"] = "Fahrt #" + cForm.inputs["LogbookText"]
					+ " nicht gefunden.";
		else
			record["LogbookText"] = oSession
					.logbookText(cLists.lists.efaWeb_logbook[logbookRow]);

		// system generated fields will be added at the server side from
		// API-level V3 onwards

		// send damage to server
		var recordCsv = cTxHandler.recordToCsv(record);
		var tx = cTxQueue.addNewTxToPending("insert", "efa2boatdamages",
				recordCsv, 0, null, cTxHandler.showTxError);

		// now adapt boatstatus
		oBoat.updateBoatStatusOnDamage(record);
	},

	/**
	 * Open Form "readDamage". Used to select a damage for display.
	 */
	readDamage_do : function() {
		cForm.init("readDamage");
		cModal.showForm(false, bFormHandler);
		var input = $("#cFormInput-BoatId")[0];
		var options = Object.keys(cLists.names.efaWeb_boats_names);
		autocomplete(input, options, efaInputValidator, "efaWeb_boats");
	},

	/**
	 * Form "readDamage" is submitted. Get the full record first.
	 */
	readDamage_done : function() {
		bFormHandler.processedData = Object.assign(cForm.inputs);
		// resolve names
		bFormHandler.processedData["BoatName"] = cForm.inputs["BoatId"];
		bFormHandler.processedData["BoatId"] = cLists.names.efaWeb_boats_names[cForm.inputs["BoatId"]];
		// get the full record
		cTxQueue.addNewTxToPending("select", "efa2boatdamages", [ "BoatId;"
				+ this.processedData["BoatId"] ], 0,
				bFormHandler.readDamage_done2, null);
	},

	/**
	 * Form "readDamage" is submitted. Display damages selected.
	 */
	readDamage_done2 : function(damageRecords) {
		var i = 0;
		var boatName = bFormHandler.processedData["BoatId"];
		var damages = "<h3>Schadensmeldungen für "
				+ bFormHandler.processedData["BoatName"] + "</h3>";
		if (damageRecords)
			damageRecords
					.forEach(function(row) {
						if ((!row["Fixed"] || bFormHandler.processedData["AlsoDone"])
								&& ((row["BoatId"]
										.localeCompare(bFormHandler.processedData["BoatId"]) == 0))
								&& (row["LastModification"]
										.localeCompare("delete") != 0)) {
							damages += "<p><b>Schaden #" + row["Damage"]
									+ "</b> "
									+ cToolbox.dateISO2DE(row["ReportDate"])
									+ "<br>";
							boatName = cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[row["BoatId"]]]["Name"];
							damages += "Boot: <b>" + boatName
									+ "</b> Nutzbar: " + row["Severity"]
									+ "<br>";
							damages += "<b>Beschreibung</b>: "
									+ row["Description"].replace(/\n/g, "<br>")
									+ "<br>";
							damages += "<b>Behoben</b>: "
									+ ((row["Fixed"]) ? "am "
											+ cToolbox
													.dateISO2DE(row["FixDate"])
											: "nein.") + "</p><hr>";
							i++;
						}
					});
		if (i == 0)
			damages += "<p>keine offenen Schäden.</p>";
		cModal.showHtml(damages);
	},

	/**
	 * Open Form "postMessage".
	 */
	postMessage_do : function() {
		cForm.init("postMessage");
		cModal.showForm(false, bFormHandler);
		var input = $("#cFormInput-From")[0];
		var options = Object.keys(cLists.names.efaWeb_persons_names);
		autocomplete(input, options, efaInputValidator, "efaWeb_persons");
	},

	/**
	 * Form "postMessage" is submitted. Parse result and store it.
	 */
	postMessage_done : function() {
		var record = Object.assign(cForm.inputs);
		// do not resolve "From", no Id used
		// would be var fromId =
		// cLists.names.efaWeb_persons_names[record["From"]];
		record["Date"] = cToolbox.dateNow();
		record["Time"] = cToolbox.timeNow();

		// system generated fields will be added at the server side from
		// API-level V3 onwards

		// send message to server
		var recordCsv = cTxHandler.recordToCsv(record);
		var tx = cTxQueue.addNewTxToPending("insert", "efa2messages",
				recordCsv, 0, null, cTxHandler.showTxError);
	},

	/**
	 * Open Form "bookAboat".
	 */
	bookAboat_do : function() {
		cForm.init("bookAboat");
		cModal.showForm(false, bFormHandler);
		var input = $("#cFormInput-PersonId")[0];
		var options = Object.keys(cLists.names.efaWeb_persons_names);
		autocomplete(input, options, efaInputValidator, "efaWeb_persons");
		input = $("#cFormInput-BoatId")[0];
		var options = Object.keys(cLists.names.efaWeb_boats_names);
		autocomplete(input, options, efaInputValidator, "efaWeb_boats");
	},

	/**
	 * Form "bookAboat" is submitted. Parse result and store it.
	 */
	bookAboat_done : function() {
		// reading will also perform the validity checks configured (see input
		// class definition.)
		var record = Object.assign(cForm.inputs);
		// resolve names
		var personId = cLists.names.efaWeb_persons_names[cForm.inputs["PersonId"]];
		record["PersonId"] = personId;
		var boatId = cLists.names.efaWeb_boats_names[cForm.inputs["BoatId"]];
		record["BoatId"] = boatId;
		record["Type"] = "ONETIME"; // no WEEKLY reservations in efaWeb

		// system generated fields will be added at the server side from
		// API-level V3 onwards

		// send message to server
		var recordCsv = cTxHandler.recordToCsv(record);
		var tx = cTxQueue.addNewTxToPending("insert", "efa2boatreservations",
				recordCsv, 0, null, cTxHandler.showTxError);
	},

}