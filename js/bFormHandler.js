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
		bForm.init("changeLogbook", "efa2logbook", 0);
		bForm.presetValue("logbookname", $_logbookname);
		bModal.showForm(true);
	},

	/**
	 * Form "changeLogbook" is submitted. All memory content is cleared and
	 * refreshed.
	 */
	changeLogbook_done : function() {
		$_logbookname = (bForm.inputs["logbookname"]) ? bForm.inputs["logbookname"]
				: $_logbookname;
		$_logbookname = $_logbookname.replace("JJJJ", (new Date()).getFullYear());
		bPanel.listpanelHeader.logbook = "Fahrtenbuch " + $_logbookname;
		_refreshEfaWeb();
	},

	/**
	 * Open Form "startTrip".
	 */
	startTrip_do : function(preset) {
		bForm.init("startTrip", "efaCloudUsers", 0);
		bForm.presetValue("EntryId", "" + bLists.nextEntryId());
		bForm.presetValue("Logbookname", $_logbookname);
		if (preset)
			for (key in preset)
				bForm.presetValue(key, preset[key]);
		bModal.showForm(false);

		var input = $("#bFormInput-BoatId")[0];
		var options = Object.keys(bLists.names.efaWeb_boats_names);
		autocomplete(input, options, "efaWeb_boats");
		input = $("#bFormInput-CoxId")[0];
		options = (bLists.names.efaWeb_persons_names) ? Object
				.keys(bLists.names.efaWeb_persons_names) : [];
		autocomplete(input, options, "efaWeb_persons");
		for (var i = 1; i <= 8; i++) {
			input = $("#bFormInput-Crew" + i + "Id")[0];
			autocomplete(input, options, "efaWeb_persons");
		}
		input = $("#bFormInput-DestinationId")[0];
		options = (bLists.names.efaWeb_destinations_names) ? Object
				.keys(bLists.names.efaWeb_destinations_names) : [];
		autocomplete(input, options, "efaWeb_destinations");
		input = $("#bFormInput-WatersIdList")[0];
		options = (bLists.names.efaWeb_waters_names) ? Object
				.keys(bLists.names.efaWeb_waters_names) : [];
		autocomplete(input, options, "efaWeb_waters");
	},

	/**
	 * Form "startTrip" is submitted. Parse result and store it. Distinguish a
	 * new trip start from a change or the trip end entry by checking for the
	 * existence of the used entry id.
	 */
	startTrip_done : function() {

		var record = Object.assign(bForm.inputs);
		// distinguish insert from modify
		var entryId = parseInt(record["EntryId"]);
		var tripRow = bLists.indices.efaWeb_logbook_nids[entryId];
		if (tripRow || (tripRow == 0)) {
			alert("Fahrt Nummer " + record["EntryId"]
					+ " existiert bereits. Bitte noch einmal versuchen.");
			return;
		}

		// resolve UUIDs
		record = bTrip.resolveTripData(record);

		// Add Date, Time and Open flag
		record["Date"] = bForm.inputs["Date"];
		var startTime = bToolbox.format_efa_time(bForm.inputs["StartTime"])
				.substring(0, 5);
		record["StartTime"] = startTime;
		record["Open"] = "true";
		delete record["EndDate"];
		delete record["EndTime"];
		// and switch guid to names for invalid members
		bPerson.guidsToNamesForInvalidPersons(record);

		// add ecrid, ChangeCount and LastModified
		record["ecrid"] = bToolbox.generateEcrid();
		record["ChangeCount"] = 1;
		record["LastModified"] = Date.now();

		// send trip to server
		var tx;
		var pairs = bTxQueue.recordToPairs(record);
		tx = bTxQueue.addNewTxToPending("insert", "efa2logbook", pairs, 0,
				bPanel.update());

		// now adapt boatstatus, if the boat was resolved
		bBoat.updateBoatStatus(record);
	},

	/**
	 * Open Form "endTrip".
	 */
	endTrip_do : function(preset) {
		bForm.init("endTrip", "efaCloudUsers", 0);
		if (preset)
			for (key in preset)
				bForm.presetValue(key, preset[key]);
		if (!preset || !preset["EntryId"]) {
			alert("Kann die Fahrt nicht beenden, unvollständige Daten.");
			return;
		}

		bModal.showForm(false);

		var input = $("#bFormInput-BoatId")[0];
		var options = Object.keys(bLists.names.efaWeb_boats_names);
		autocomplete(input, options, "efaWeb_boats");
		input = $("#bFormInput-CoxId")[0];
		options = Object.keys(bLists.names.efaWeb_persons_names);
		autocomplete(input, options, "efaWeb_persons");
		for (var i = 1; i <= 8; i++) {
			input = $("#bFormInput-Crew" + i + "Id")[0];
			options = Object.keys(bLists.names.efaWeb_persons_names);
			autocomplete(input, options, "efaWeb_persons");
		}
		input = $("#bFormInput-DestinationId")[0];
		options = Object.keys(bLists.names.efaWeb_destinations_names);
		autocomplete(input, options, "efaWeb_destinations");
		input = $("#bFormInput-WatersIdList")[0];
		options = Object.keys(bLists.names.efaWeb_waters_names);
		autocomplete(input, options, "efaWeb_waters");
	},

	/**
	 * Form "enterTrip" is submitted. Parse result and store it. Distinguish a
	 * new trip start from a change or the trip end entry by checking for the
	 * existence of the used entry id.
	 */
	endTrip_done : function() {

		var record = Object.assign(bForm.inputs);
		// distinguish insert from modify
		var entryId = parseInt(record["EntryId"]);
		var tripRow = bLists.indices.efaWeb_logbook_nids[entryId];
		var trip = bLists.lists.efaWeb_logbook[tripRow];
		if (!trip) {
			alert("Fahrt Nummer " + entryId + " konnte nicht gefunden werden.");
			return;
		}

		// resolve UUIDs
		record = bTrip.resolveTripData(record);

		// Add Date, Time and Open flag
		record["Open"] = "false";
		record["EndTime"] = bToolbox.format_efa_time(bForm.inputs["EndTime"])
				.substring(0, 5);
		if (!bForm.inputs["EndDate"]
				|| (bForm.inputs["EndDate"].localeCompare(record["Date"]) == 0))
			delete record["EndDate"];
		else
			record["EndDate"] = bForm.inputs["EndDate"];

		// increment ChangeCount and update LastModified
		var changeCount = (trip) ? parseInt(trip["ChangeCount"]) + 1 : false;
		record["ChangeCount"] = (changeCount) ? changeCount : 1;
		record["LastModified"] = Date.now();

		// send trip to server
		var tx;
		var pairs = bTxQueue.recordToPairs(record);
		tx = bTxQueue
				.addNewTxToPending("update", "efa2logbook", pairs, 0, null);

		// now adapt boatstatus, if the boat was resolved
		bBoat.updateBoatStatus(record);
	},

	/**
	 * Open Form "postDamage".
	 */
	postDamage_do : function() {
		bForm.init("postDamage", "efaWeb_boatdamages", 0);
		bForm.presetValue("ReportDate", bToolbox.dateNow());
		bForm.presetValue("ReportTime", bToolbox.timeNow());
		bForm.presetValue("Claim", "");
		bModal.showForm(false);
		var input = $("#bFormInput-BoatId")[0];
		var options = Object.keys(bLists.names.efaWeb_boats_names);
		autocomplete(input, options, "efaWeb_boats");
		input = $("#bFormInput-ReportedByPersonId")[0];
		options = Object.keys(bLists.names.efaWeb_persons_names);
		autocomplete(input, options, "efaWeb_persons");
	},

	/**
	 * Form "postDamage" submitted. Parse result and store it.
	 */
	postDamage_done : function() {
		var record = Object.assign(bForm.inputs);
		// resolve names
		record["BoatId"] = bLists.names.efaWeb_boats_names[bForm.inputs["BoatId"]];
		record["ReportedByPersonId"] = bLists.names.efaWeb_persons_names[bForm.inputs["ReportedByPersonId"]];
		delete record["ReportedByPersonName"];
		if (!record["ReportedByPersonId"]) {
			delete record["ReportedByPersonId"];
			record["ReportedByPersonName"] = bForm.inputs["ReportedByPersonId"];
		}
		// retrieve logbook text
		var logbookRow = bLists.indices.efaWeb_logbook_nids[bForm.inputs["LogbookText"]];
		record["LogbookText"] = "";
		if (!logbookRow)
			record["LogbookText"] = "Fahrt #" + bForm.inputs["LogbookText"]
					+ " nicht gefunden.";
		else
			record["LogbookText"] = bTrip
					.logbookText(bLists.lists.efaWeb_logbook[logbookRow]);

		// add ecrid, ChangeCount and LastModified
		record["ecrid"] = bToolbox.generateEcrid();
		record["ChangeCount"] = 1;
		record["LastModified"] = Date.now();

		// send damage to server
		var recordCsv = bTxHandler.recordToCsv(record);
		var tx = bTxQueue.addNewTxToPending("insert", "efa2boatdamages",
				recordCsv, 0, null);
	},

	/**
	 * Open Form "readDamage". Used to select a damage for display.
	 */
	readDamage_do : function() {
		bForm.init("readDamage", "efaWeb_boatdamages", 0);
		bModal.showForm(false);
		var input = $("#bFormInput-BoatId")[0];
		var options = Object.keys(bLists.names.efaWeb_boats_names);
		autocomplete(input, options, "efaWeb_boats");
	},

	/**
	 * Form "readDamage" is submitted. Get the full record first.
	 */
	readDamage_done : function() {
		bFormHandler.processedData = Object.assign(bForm.inputs);
		// resolve names
		bFormHandler.processedData["BoatName"] = bForm.inputs["BoatId"];
		bFormHandler.processedData["BoatId"] = bLists.names.efaWeb_boats_names[bForm.inputs["BoatId"]];
		// get the full record
		bTxQueue.addNewTxToPending("select", "efa2boatdamages", [ "BoatId;"
				+ this.processedData["BoatId"] ], 0,
				bFormHandler.readDamage_done2);
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
										.localeCompare(bFormHandler.processedData["BoatId"]) == 0))) {
							damages += "<p><b>Schaden #" + row["Damage"]
									+ "</b> "
									+ bToolbox.dateISO2DE(row["ReportDate"])
									+ "<br>";
							boatName = bLists.lists.efaWeb_boats[bLists.indices.efaWeb_boats_guids[row["BoatId"]]]["Name"];
							damages += "Boot: <b>" + boatName
									+ "</b> Nutzbar: " + row["Severity"]
									+ "<br>";
							damages += "<b>Beschreibung</b>: "
									+ row["Description"].replace(/\n/g, "<br>")
									+ "<br>";
							damages += "<b>Behoben</b>: "
									+ ((row["Fixed"]) ? "am "
											+ bToolbox
													.dateISO2DE(row["FixDate"])
											: "nein.") + "</p><hr>";
							i++;
						}
					});
		if (i == 0)
			damages += "<p>keine offenen Schäden.</p>";
		bModal.showHtml(damages);
	},

	/**
	 * Open Form "postMessage".
	 */
	postMessage_do : function() {
		bForm.init("postMessage", "efaWeb_messages", 0);
		bModal.showForm(false);
		var input = $("#bFormInput-From")[0];
		var options = Object.keys(bLists.names.efaWeb_persons_names);
		autocomplete(input, options, "efaWeb_persons");
	},

	/**
	 * Form "postMessage" is submitted. Parse result and store it.
	 */
	postMessage_done : function() {
		var record = Object.assign(bForm.inputs);
		// resolve names
		var fromId = bLists.names.efaWeb_persons_names[record["From"]];
		if (fromId)
			record["From"] = fromId;
		record["Date"] = bToolbox.dateNow();
		record["Time"] = bToolbox.timeNow();

		// add ecrid, ChangeCount and LastModified
		record["ecrid"] = bToolbox.generateEcrid();
		record["ChangeCount"] = 1;
		record["LastModified"] = Date.now();

		// send message to server
		var recordCsv = bTxHandler.recordToCsv(record);
		var tx = bTxQueue.addNewTxToPending("insert", "efa2messages",
				recordCsv, 0, null);
		// and append the record to the local list.
		tx.listname = "efaWeb_messages";
		tx.listRowPos = bLists.insert("efaWeb_messages", record);
	},

	/**
	 * Open Form "bookAboat".
	 */
	bookAboat_do : function() {
		bForm.init("bookAboat", "efaWeb_boatreservations", 0);
		bModal.showForm(false);
		var input = $("#bFormInput-PersonId")[0];
		var options = Object.keys(bLists.names.efaWeb_persons_names);
		autocomplete(input, options, "efaWeb_persons");
		input = $("#bFormInput-BoatId")[0];
		var options = Object.keys(bLists.names.efaWeb_boats_names);
		autocomplete(input, options, "efaWeb_boats");
	},

	/**
	 * Form "bookAboat" is submitted. Parse result and store it.
	 */
	bookAboat_done : function() {
		// reading will also perform the validity checks configured (see input class definition.) 
		var record = Object.assign(bForm.inputs);
		// resolve names
		var personId = bLists.names.efaWeb_persons_names[bForm.inputs["PersonId"]];
		record["PersonId"] = personId;
		var boatId = bLists.names.efaWeb_boats_names[bForm.inputs["BoatId"]];
		record["BoatId"] = boatId;

		// add ecrid, ChangeCount and LastModified
		record["ecrid"] = bToolbox.generateEcrid();
		record["ChangeCount"] = 1;
		record["LastModified"] = Date.now();

		// send message to server
		var recordCsv = bTxHandler.recordToCsv(record);
		var tx = bTxQueue.addNewTxToPending("insert", "efa2boatreservations",
				recordCsv, 0, null);
	},

}