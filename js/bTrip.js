var bTrip = {

	buttonEndTrip : '<div class="w3-row"><div class="w3-col l1">' + 
						'<p class="formbutton" id="do-getForm_endTrip_[EntryId]">Fahrt Beenden</p>' + 
					'</div></div>',

	/**
	 * Set all trip Values to default
	 */
	clearTrip : function() {
		this["EntryId"] = 0;
		this["BoatId"] = "";
		this["BoatName"] = "";
		this["BoatCaptain"] = 0;
		this["BoatVariant"] = 0;
		this["ChangeCount"] = 0;
		this["Comments"] = "";
		this["CoxId"] = "";
		this["CoxName"] = "";
		this["Crew1Id"] = "";
		this["Crew1Name"] = "";
		this["Crew2Id"] = "";
		this["Crew2Name"] = "";
		this["Crew3Id"] = "";
		this["Crew3Name"] = "";
		this["Crew4Id"] = "";
		this["Crew4Name"] = "";
		this["Crew5Id"] = "";
		this["Crew5Name"] = "";
		this["Crew6Id"] = "";
		this["Crew6Name"] = "";
		this["Crew7Id"] = "";
		this["Crew7Name"] = "";
		this["Crew8Id"] = "";
		this["Crew8Name"] = "";
		this["Date"] = "1970-01-01";
		this["DestinationId"] = "";
		this["DestinationName"] = "";
		this["DestinationVariantName"] = "";
		this["Distance"] = "";
		this["EndDate"] = "";
		this["EndTime"] = "00:00:00";
		this["LastModified"] = 0;
		this["SessionType"] = "";
		this["StartTime"] = "00:00:00";
		this["WatersIdList"] = "";
		this["WatersNameList"] = "";
	},

	/**
	 * get a short descripton of the trip, for selection in damage notices asf.
	 */
	shortDescription : function(trip) {
		return trip.EntryId + "=" + "#" + trip.EntryId + " "
				+ bToolbox.dateISO2DE(trip.Date) + " - "
				+ bTrip.getBoatName(trip) + ", "
				+ bTrip.getDestinationName(trip)
	},

	/**
	 * get a short descripton of the trip, for selection in damage notices asf.
	 */
	logbookText : function(trip) {
		var crew = "";
		for (var i = 1; i <= 8; i++) {
			var crewname = this.getCrewName(trip, "Crew" + i);
			if (crewname && crewname.length > 1)
				crew += crewname + ", ";
		}
		if (crew.length > 2)
			crew = crew.substring(0, crew.length - 2);
		return trip.EntryId + ": " + bToolbox.dateISO2DE(trip.Date) + " "
				+ trip.StartTime.substring(0, 5) + " - "
				+ bTrip.getBoatName(trip) + ", "
				+ bTrip.getDestinationName(trip) + " (" + trip.Distance
				+ "). Am Steuer: '" + this.getCrewName(trip, "Cox")
				+ "', im Boot '" + crew + "'";
	},

	/**
	 * Format a single trip for the logbook display (HTML).
	 */
	formatTrip : function(trip, full) {
		var startAndEnd = bToolbox.dateISO2DE(trip["Date"]) + " "
				+ trip["StartTime"].substring(0, 5);
		if (trip["EndDate"] && trip["EndDate"].length > 1)
			startAndEnd += "<br>bis " + bToolbox.dateISO2DE(trip["EndDate"])
					+ " " + trip["EndTime"].substring(0, 5);
		else if (trip["EndTime"] && trip["EndTime"].length > 1)
			startAndEnd += " - " + trip["EndTime"].substring(0, 5);
		else
			startAndEnd += " -> ";
		if (full) {
			var coxname = this.getCrewName(trip, "Cox");
			var crew = "";
			for (var i = 1; i <= 8; i++) {
				var crewname = this.getCrewName(trip, "Crew" + i);
				if (crewname && crewname.length > 1) {
					if (!coxname && (i == 1))
						crew += "<b>" + crewname + "</b><br>";
					else
						crew += crewname + "<br>";
				}
			}
		}
		var destination = bTrip.getDestinationName(trip);
		if (trip["destinationVariantName"])
			destination += ", " + trip["destinationVariantName"];
		var html = "<tr><td>" + trip["EntryId"] + "</td><td>" + startAndEnd
				+ "</td><td>" + this.getBoatName(trip) + "</td>";
		if (full) 
			html += "<td>" + coxname + "</td><td>" + crew + "</td>";
		html += "<td>" + destination + "</td><td>" + trip["Distance"] + "</td></tr>";
		return html;
	},

	/**
	 * set the parameters of the trip to reflect the given entryId
	 */
	setTrip : function(entryId) {
		this.clearTrip();
		var r = bLists.indices.efaWeb_logbook_nids[entryId];
		for (key in bLists.lists.logbook[r]) {
			bTrip[key] = r[key];
		}
	},

	/**
	 * Create an array with all the preset values of a given trip. Changes Guids
	 * to names.
	 */
	getFormPreset : function(trip, setEndNow) {
		var record = {};
		// Change type of EntryId to String
		if (trip["EntryId"])
			record["EntryId"] = "" + trip["EntryId"];
		if (trip["Logbookname"])
			record["Logbookname"] = trip["Logbookname"];
		else
			record["Logbookname"] = $_logbookname;

		// Resolve the boat variant name, if a boatId is provided. Else take the
		// name itself.
		if (trip["BoatId"]) {
			var boat = bLists.lists.efaWeb_boats[bLists.indices.efaWeb_boats_guids[trip["BoatId"]]];
			var boatName = (trip["BoatVariant"]) ? bBoat.getNames(boat)[trip["BoatVariant"] - 1]
					: bBoat.getNames(boat)[0];
			record["BoatId"] = boatName;
		} else if (trip["BoatName"]) {
			record["BoatId"] = trip["BoatName"];
		}
		// Resolve the cox name, if a coxId is provided. Else take the name
		// itself.
		if (trip["CoxId"]) {
			var cox = bLists.lists.efaWeb_persons[bLists.indices.efaWeb_persons_guids[trip["CoxId"]]];
			if (cox)
				record["CoxId"] = cox["FullName"];
		} else if (trip["CoxName"]) {
			record["CoxId"] = trip["CoxName"];
		}
		// Resolve the crew names, if crew Ids are provided. Else take the names
		// themselves.
		for (var i = 1; i < 9; i++) {
			if (trip["Crew" + i + "Id"]) {
				var crewmember = bLists.lists.efaWeb_persons[bLists.indices.efaWeb_persons_guids[trip["Crew"
						+ i + "Id"]]];
				record["Crew" + i + "Id"] = (crewmember) ? crewmember["FullName"]
						: "[nicht gefunden]";
			} else if (trip["Crew" + i + "Name"]) {
				record["Crew" + i + "Id"] = trip["Crew" + i + "Name"];
			}
		}
		// Resolve the destination name, if a destinationId is provided. Else
		// take the name itself.
		if (trip["DestinationId"]) {
			var destination = bLists.lists.efaWeb_destinations[bLists.indices.efaWeb_destinations_guids[trip["DestinationId"]]];
			record["DestinationId"] = (destination) ? destination["Name"]
					: "[nicht gefunden]";
		} else if (trip["DestinationName"]) {
			record["DestinationId"] = trip["DestinationName"];
		}
		// Resolve the waters list names, if a watersIdList is provided. Else
		// take the names themselves.
		if (trip["WatersIdList"]) {
			var watersIds = trip["WatersIdList"].split(";");
			var watersNameList = "";
			if (watersIds)
				watersIds
						.forEach(function(watersId) {
							var water = bLists.lists.efaWeb_waters[bLists.indices.efaWeb_waters_guids[watersId]];
							watersNameList += water["Name"] + ";";
						});
			if (watersNameList.length > 0)
				watersNameList = watersNameList.substring(0,
						watersNameList.length - 1);
			if (record["WatersIdList"])
				record["WatersIdList"] = watersNameList;
			record["WatersIdList"] = (watersNameList) ? watersNameList
					: "[nicht gefunden]";
		} else if (trip["WatersNameList"]) {
			record["WatersNameList"] = trip["WatersNameList"];
		}
		// set times.
		if (trip["Date"])
			record["Date"] = trip["Date"];
		if (trip["StartTime"])
			record["StartTime"] = trip["StartTime"].substring(0, 5);
		if (setEndNow) {
			record["EndDate"] = bToolbox.dateNow();
			record["EndTime"] = bToolbox.timeNow().substring(0, 5);
		}
		return record;
	},

	/**
	 * get the boat name of the trip
	 */
	getBoatName : function(trip) {
		if (!trip.BoatId || trip.BoatId < 30)
			return trip.BoatName;
		var r = bLists.indices.efaWeb_boats_guids[trip.BoatId];
		if (r || r === 0)
			return bLists.lists.efaWeb_boats[r].Name;
		return "???";
	},

	/**
	 * get the crew member full name of the trip, based on the prefix: "Cox",
	 * "Crew1", Crew2" asf.
	 */
	getCrewName : function(trip, memberPrefix) {
		if (!trip[memberPrefix + "Id"] || trip[memberPrefix + "Id"].length < 30)
			return trip[memberPrefix + "Name"];
		var r = bLists.indices.efaWeb_persons_guids[trip[memberPrefix + "Id"]];
		return bLists.lists.efaWeb_persons[r]["FullName"];
	},

	/**
	 * get the destination name the parameters of the trip
	 */
	getDestinationName : function(trip) {
		var v = (trip.DestinationNameVariant) ? ", "
				+ trip.DestinationNameVariant : "";
		if (!trip.DestinationId || trip.DestinationId < 30)
			return trip.DestinationName + v;
		var r = bLists.indices.efaWeb_destinations_guids[trip.DestinationId];
		return (r) ? bLists.lists.efaWeb_destinations[r].Name + v : trip.DestinationId;
	},

	/**
	 * get a datasheet for a single trip.
	 */
	getDatasheet : function(trip) {
		// get the current data. Will refresh the modal after loading
		bTxQueue.addNewTxToPending("select", "efa2logbook", ["ecrid;" + trip["ecrid"]], 0, bTrip.updateModal);
		var s = "<table id='modal-table'><tbody><tr><th>Fahrt</th><th>Start</th><th>Boot</th>"
			+ "<th>Steuermann</th><th>Mannschaft</th><th>Ziel</th><th>km</th></tr>";
		s += bTrip.formatTrip(trip, true);
		s += "</tbody></table>";
		var endButton = bTrip.buttonEndTrip.replace("[EntryId]", ""
				+ trip.EntryId);
		return s + endButton;
	},
	
	/**
	 * The information in the modal has to be refreshed.
	 */
	updateModal : function(trips) {
		var s = "<table id='modal-table'><tbody><tr><th>Fahrt</th><th>Start</th><th>Boot</th>"
			+ "<th>Steuermann</th><th>Mannschaft</th><th>Ziel</th><th>km</th></tr>";
		s += bTrip.formatTrip(trips[0], true);
		s += "</tbody></table>";
		var endButton = bTrip.buttonEndTrip.replace("[EntryId]", ""
				+ trips[0].EntryId);
		bModal.showHtml(s + endButton);
	},
	
	/**
	 * Resolve all data obtained by the trip form. Add all information to the
	 * record and return it afterwards.
	 */
	resolveTripData : function(record) {
		// resolve boat name
		var boatId = bLists.names.efaWeb_boats_names[bForm.inputs["BoatId"]];
		var boat = false;
		if (boatId) {
			record["BoatId"] = boatId;
			boat = bLists.lists.efaWeb_boats[bLists.indices.efaWeb_boats_guids[boatId]];
			record["BoatVariant"] = bBoat.getVariantIndexForName(boat, bForm.inputs["BoatId"]) + 1;
		} else
			record["BoatName"] = bForm.inputs["BoatId"];

		// resolve cox name
		var allCrewNames = "";
		if (bForm.inputs["CoxId"] && (bForm.inputs["CoxId"].length > 1)) {
			allCrewNames += bForm.inputs["CoxId"] + ", ";
			var coxId = bLists.names.efaWeb_persons_names[bForm.inputs["CoxId"]];
			if (!coxId)
				// if the UUID could not be resolved, use the name.
				record["CoxId"] = bForm.inputs["CoxId"];
			else
				record["CoxId"] = coxId;
		}
		// resolve crew names
		for (var i = 1; i <= 8; i++) {
			if (bForm.inputs["Crew" + i + "Id"]
					&& (bForm.inputs["Crew" + i + "Id"].length > 1)) {
				// the field named "CrewXId" does contain an name, resdolve the
				// UUID
				allCrewNames += bForm.inputs["Crew" + i + "Id"] + ", ";
				var crewId = bLists.names.efaWeb_persons_names[bForm.inputs["Crew"
						+ i + "Id"]];
				if (!crewId)
					// if the UUID could not be resolved, use the name.
					record["Crew" + i + "Name"] = bForm.inputs["Crew" + i
							+ "Id"];
				else
					record["Crew" + i + "Id"] = crewId
			}
		}
		if (allCrewNames.length > 2)
			allCrewNames = allCrewNames.substring(0, allCrewNames.length - 2);
		record["AllCrewNames"] = allCrewNames;

		// resolve destination
		var destinationInput = bForm.inputs["DestinationId"];
		var destinationId = bLists.names.efaWeb_destinations_names[bForm.inputs["DestinationId"]];
		if (destinationId) {
			record["DestinationId"] = destinationId;
			record["DestinationName"] = "";
		} else {
			record["DestinationName"] = bForm.inputs["DestinationId"];
			record["DestinationId"] = "";
		}

		// resolve waters
		var watersIdList = bForm.inputs["WatersIdList"].split(";");
		record["WatersIdList"] = "";
		record["WatersNameList"] = "";
		for (var i = 0; i < watersIdList.length; i++) {
			var watersId = bLists.names.efaWeb_waters_names[watersIdList[i]];
			if (watersId)
				record["WatersIdList"] += watersId + ";";
			else
				record["WatersNameList"] = bForm.inputs["WatersIdList"];
		}
		if (record["WatersIdList"].length > 1)
			record["WatersIdList"] = record["WatersIdList"].substring(0,
					record["WatersIdList"].length - 1);
		if (record["WatersNameList"].length > 1)
			delete record["WatersIdList"];
		else
			delete record["WatersNameList"];
		return record;
	}


}