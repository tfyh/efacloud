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

var oSession = {

	buttonRow : '<div class="w3-row">',
	buttonEndSession : '<div class="w3-col l2">'
			+ '<p><span class="formbutton" id="do-getForm_endSession_[ecrid]"><b>[buttonEffect]</b></span>&nbsp;</p></div>',
	buttonUpdateSession : '<div class="w3-col l4">'
			+ '<p style="text-align:right"><span class="formbutton" id="do-getForm_updateSession_[ecrid]">[buttonEffect]</span>&nbsp;</p></div>',
	buttonCancelSession : '<div class="w3-col l4">'
			+ '<p style="text-align:right"><span class="formbutton" id="do-getForm_cancelSession_[ecrid]">[buttonEffect]</span>&nbsp;</p></div>',
	buttonRowEnd : '</div>',

	/**
	 * Set all session Values to default
	 */
	clearSession : function() {
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
	 * get a short descripton of the session, for selection in damage notices
	 * asf.
	 */
	shortDescription : function(session) {
		return session.EntryId + "=" + "#" + session.EntryId + " "
				+ cToolbox.dateISO2DE(session.Date) + " - "
				+ oSession.getBoatName(session) + ", "
				+ oSession.getDestinationName(session)
	},

	/**
	 * get a short descripton of the session, for selection in damage notices
	 * asf.
	 */
	logbookText : function(session) {
		var crew = "";
		for (var i = 1; i <= 8; i++) {
			var crewname = this.getCrewName(session, "Crew" + i);
			if (crewname && crewname.length > 1)
				crew += crewname + ", ";
		}
		if (crew.length > 2)
			crew = crew.substring(0, crew.length - 2);
		return session.EntryId + ": " + cToolbox.dateISO2DE(session.Date) + " "
				+ session.StartTime.substring(0, 5) + " - "
				+ oSession.getBoatName(session) + ", "
				+ oSession.getDestinationName(session) + " ("
				+ session.Distance + "). " + _("pLwP12|Cox:") + " '"
				+ this.getCrewName(session, "Cox") + "', "
				+ _("q2flvV|In the boat") + " '" + crew + "'";
	},

	/**
	 * Format a single session for the logbook display (HTML).
	 */
	formatSession : function(session, full) {
		var startAndEnd = cToolbox.dateISO2DE(session["Date"]) + " "
				+ session["StartTime"].substring(0, 5);
		if (session["EndDate"] && session["EndDate"].length > 1)
			startAndEnd += "<br>bis " + cToolbox.dateISO2DE(session["EndDate"])
					+ " " + session["EndTime"].substring(0, 5);
		else if (session["EndTime"] && session["EndTime"].length > 1)
			startAndEnd += " - " + session["EndTime"].substring(0, 5);
		else
			startAndEnd += " -> ";
		if (full) {
			var coxname = this.getCrewName(session, "Cox");
			var crew = "";
			for (var i = 1; i <= 8; i++) {
				var crewname = this.getCrewName(session, "Crew" + i);
				if (crewname && crewname.length > 1) {
					if (!coxname && (i == 1))
						crew += "<b>" + crewname + "</b><br>";
					else
						crew += crewname + "<br>";
				}
			}
		}
		var destination = oSession.getDestinationName(session);
		if (session["destinationVariantName"])
			destination += ", " + session["destinationVariantName"];
		var isOwnSession = (($_personId.length > 10) && session.AllCrewIds && (session.AllCrewIds.indexOf($_personId) >= 0));
		var entry = (isOwnSession) ? session["EntryId"] + " (Buch: " + session["Logbookname"] + ")": session["EntryId"];
		var html = "<tr><td>" + entry + "</td><td>" + startAndEnd
				+ "</td><td>" + this.getBoatName(session) + "</td>";
		if (full)
			html += "<td>" + coxname + "</td><td>" + crew + "</td>";
		html += "<td>" + destination + "</td><td>" + session["Distance"]
				+ "</td></tr>";
		return html;
	},

	/**
	 * set the parameters of the session to reflect the given entryId
	 */
	setSession : function(entryId) {
		this.clearSession();
		var r = cLists.indices.efaWeb_logbook_nids[entryId];
		for (key in cLists.lists.logbook[r]) {
			oSession[key] = r[key];
		}
	},

	/**
	 * Create an array with all the preset values of a given session. Changes
	 * Guids to names.
	 */
	getFormPreset : function(session, setEndNow) {
		var record = {};
		// Change type of EntryId to String
		if (session["EntryId"])
			record["EntryId"] = "" + session["EntryId"];
		if (session["Logbookname"])
			record["Logbookname"] = session["Logbookname"];
		else
			record["Logbookname"] = $_logbookname;

		// Resolve the boat variant name, if a boatId is provided. Else take the
		// name itself.
		if (session["BoatId"]) {
			var boat = cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[session["BoatId"]]];
			var boatName = (session["BoatVariant"]) ? oBoat.getNames(boat)[session["BoatVariant"] - 1]
					: oBoat.getNames(boat)[0];
			record["BoatId"] = boatName;
		} else if (session["BoatName"]) {
			record["BoatId"] = session["BoatName"];
		}
		// Resolve the cox name, if a coxId is provided. Else take the name
		// itself.
		if (session["CoxId"]) {
			var cox = cLists.lists.efaWeb_persons[cLists.indices.efaWeb_persons_guids[session["CoxId"]]];
			if (cox)
				record["CoxId"] = cox["FullName"];
		} else if (session["CoxName"]) {
			record["CoxId"] = session["CoxName"];
		}
		// Resolve the crew names, if crew Ids are provided. Else take the names
		// themselves.
		for (var i = 1; i < 9; i++) {
			if (session["Crew" + i + "Id"]) {
				var crewmember = cLists.lists.efaWeb_persons[cLists.indices.efaWeb_persons_guids[session["Crew"
						+ i + "Id"]]];
				record["Crew" + i + "Id"] = (crewmember) ? crewmember["FullName"]
						: _("yIasNs|[not found]");
			} else if (session["Crew" + i + "Name"]) {
				record["Crew" + i + "Id"] = session["Crew" + i + "Name"];
			}
		}
		// Resolve the destination name, if a destinationId is provided.
		if (session["DestinationId"]) {
			var destination = cLists.lists.efaWeb_destinations[cLists.indices.efaWeb_destinations_guids[session["DestinationId"]]];
			record["DestinationId"] = (destination) ? destination["Name"]
					: false;
		}
		// If the destination Id is not provided or could not be resolved,
		// because it is a name take the name itself.
		if (!record["DestinationId"] && session["DestinationName"])
			record["DestinationId"] = session["DestinationName"];
		if (session["Distance"]) {
			var distance = session["Distance"].replace("km", "").replace(/ /g,
					"")
					+ " km";
			record["Distance"] = distance;
		}
		// Use the session type
		if (session["SessionType"])
			record["SessionType"] = session["SessionType"];
		// Resolve the waters list names, if a watersIdList is provided. Else
		// take the names themselves.
		if (session["WatersIdList"]) {
			var watersIds = session["WatersIdList"].split(";");
			var watersNameList = "";
			if (watersIds)
				watersIds
						.forEach(function(watersId) {
							var water = cLists.lists.efaWeb_waters[cLists.indices.efaWeb_waters_guids[watersId]];
							watersNameList += water["Name"] + ";";
						});
			if (watersNameList.length > 0)
				watersNameList = watersNameList.substring(0,
						watersNameList.length - 1);
			if (record["WatersIdList"])
				record["WatersIdList"] = watersNameList;
			record["WatersIdList"] = (watersNameList) ? watersNameList
					: _("IaAJhC|[not found]");
		} else if (session["WatersNameList"]) {
			record["WatersNameList"] = session["WatersNameList"];
		}
		// set times.
		if (session["Date"])
			record["Date"] = session["Date"];
		if (session["StartTime"])
			record["StartTime"] = session["StartTime"].substring(0, 5);
		if (setEndNow) {
			record["EndDate"] = cToolbox.dateNow();
			record["EndTime"] = cToolbox.timeNow().substring(0, 5);
		}
		// copy comments
		if (session["Comments"])
			record["Comments"] = session["Comments"];
		return record;
	},

	/**
	 * get the boat name of the session
	 */
	getBoatName : function(session) {
		if (!session.BoatId || session.BoatId < 30)
			return session.BoatName;
		var r = cLists.indices.efaWeb_boats_guids[session.BoatId];
		if (r || r === 0)
			return cLists.lists.efaWeb_boats[r].Name;
		return "???";
	},

	/**
	 * get the crew member full name of the session, based on the prefix: "Cox",
	 * "Crew1", Crew2" asf.
	 */
	getCrewName : function(session, memberPrefix) {
		if (!session[memberPrefix + "Id"]
				|| session[memberPrefix + "Id"].length < 30)
			return session[memberPrefix + "Name"];
		var r = cLists.indices.efaWeb_persons_guids[session[memberPrefix + "Id"]];
		return cLists.lists.efaWeb_persons[r]["FullName"];
	},

	/**
	 * get the destination name the parameters of the session
	 */
	getDestinationName : function(session) {
		var v = (session.DestinationNameVariant) ? ", "
				+ session.DestinationNameVariant : "";
		if (!session.DestinationId || session.DestinationId.length < 30)
			return session.DestinationName + v;
		var r = cLists.indices.efaWeb_destinations_guids[session.DestinationId];
		return (r || (r == 0)) ? cLists.lists.efaWeb_destinations[r].Name + v
				: session.DestinationId;
	},

	/**
	 * get a datasheet for a single session. Mote that the session must be a
	 * complete record, with names etc, as is provided by the efaWeb_opentrips
	 * list.
	 */
	getDatasheet : function(session, allowChange) {
		var s = "<table id='modal-table'><tbody><tr><th>" + _("yFN5nV|Trip") + "</th><th>" + _("i2FITf|Start") 
				+ "</th><th>" + _("mqExyG|Boat") + "</th><th>" + _("kHt6C4|Cox") + "</th><th>" + _("28WNdA|Team") 
				+ "</th><th>" + _("9UjNdu|Destination") + "</th><th>" + _("GwV0aU|Distance") + "</th></tr>";
		s += oSession.formatSession(session, true);
		s += "</tbody></table>";
		
		var isOpen = (session.Open.localeCompare("true") == 0);
		var endButton = (isOpen && allowChange) ? oSession.buttonEndSession.replace(
				"[ecrid]", "" + session.ecrid).replace("[buttonEffect]", _("4h7ttJ|End trip")) : "";
		var updateButton = (isOpen && allowChange) ? oSession.buttonUpdateSession.replace(
				"[ecrid]", "" + session.ecrid).replace("[buttonEffect]", _("SjX5zz|Change trip")) : "";
		var cancelButton = (isOpen && allowChange) ? oSession.buttonCancelSession.replace(
				"[ecrid]", "" + session.ecrid).replace("[buttonEffect]", _("UgbntI|Cancel trip")) : "";
		return s + oSession.buttonRow + endButton + updateButton + cancelButton + oSession.buttonRowEnd;
	},

	/**
	 * The information in the modal has to be refreshed.
	 */
	updateModal : function(sessions) {
		var s = "<table id='modal-table'><tbody><tr><th>" + _("UGK5ph|Trip") + "</th><th>" + _("Z64jAf|Start") 
			+ "</th><th>" + _("ZBJmMh|Boat") + "</th><th>" + _("0j7S8g|Cox") + "</th><th>" + _("E315aG|Team") 
			+ "</th><th>" + _("uaNNl7|Destination") + "</th><th>" + _("7yTSSm|Distance") + "</th></tr>";
		s += oSession.formatSession(sessions[0], true);
		s += "</tbody></table>";
		var endButton = (sessions[0].Open.localeCompare("true") == 0) ? oSession.buttonEndSession
				.replace("[ecrid]", "" + sessions[0].ecrid).replace("[buttonEffect]", _("4h7ttJ|End trip"))
				: "";
		cModal.showHtml(s + endButton);
	},

	/**
	 * Resolve all data obtained by the session form. Add all information to the
	 * record and return it afterwards.
	 */
	resolveSessionData : function(record) {
		// resolve boat name
		var boatVariantName = cForm.inputs["BoatId"];
		var boatId = cLists.names.efaweb_virtual_boatVariants_names[cForm.inputs["BoatId"]];
		var boat = false;
		if (boatId) {
			boat = cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[boatId]];
			record["BoatVariant"] = oBoat.getVariantIndexForName(boat,
					boatVariantName) + 1;
			record["BoatId"] = boatId;
		} else {
			record["BoatId"] = "";
			record["BoatName"] = boatVariantName;
		}

		// resolve cox name
		var allCrewNames = "";
		if (cForm.inputs["CoxId"] && (cForm.inputs["CoxId"].length > 1)) {
			allCrewNames += cForm.inputs["CoxId"] + ", ";
			var coxId = cLists.names.efaWeb_persons_names[cForm.inputs["CoxId"]];
			if (!coxId)
				// if the UUID could not be resolved, use the name.
				record["CoxId"] = cForm.inputs["CoxId"];
			else
				record["CoxId"] = coxId;
		}
		// resolve crew names
		for (var i = 1; i <= 8; i++) {
			if (cForm.inputs["Crew" + i + "Id"]
					&& (cForm.inputs["Crew" + i + "Id"].length > 1)) {
				// the field named "CrewXId" does contain an name, resdolve the
				// UUID
				allCrewNames += cForm.inputs["Crew" + i + "Id"] + ", ";
				var crewId = cLists.names.efaWeb_persons_names[cForm.inputs["Crew"
						+ i + "Id"]];
				if (!crewId) {
					// if the UUID could not be resolved, use the name.
					record["Crew" + i + "Name"] = cForm.inputs["Crew" + i
							+ "Id"];
					// and remove the Id.
					record["Crew" + i + "Id"] = "";
				} else
					record["Crew" + i + "Id"] = crewId
			}
		}
		if (allCrewNames.length > 2)
			allCrewNames = allCrewNames.substring(0, allCrewNames.length - 2);
		record["AllCrewNames"] = allCrewNames;

		// resolve destination
		var destinationInput = cForm.inputs["DestinationId"];
		var destinationId = cLists.names.efaWeb_destinations_names[cForm.inputs["DestinationId"]];
		if (destinationId) {
			record["DestinationId"] = destinationId;
			record["DestinationName"] = "";
		} else {
			record["DestinationName"] = cForm.inputs["DestinationId"];
			record["DestinationId"] = "";
		}

		// resolve waters
		var watersIdList = cForm.inputs["WatersIdList"].split(";");
		record["WatersIdList"] = "";
		record["WatersNameList"] = "";
		for (var i = 0; i < watersIdList.length; i++) {
			var watersId = cLists.names.efaWeb_waters_names[watersIdList[i]];
			if (watersId)
				record["WatersIdList"] += watersId + ";";
			else
				record["WatersNameList"] = cForm.inputs["WatersIdList"];
		}
		if (record["WatersIdList"].length > 1)
			record["WatersIdList"] = record["WatersIdList"].substring(0,
					record["WatersIdList"].length - 1);
		if (record["WatersNameList"].length > 1)
			delete record["WatersIdList"];
		else
			delete record["WatersNameList"];
		return record;
	},

	// return true, if now is between start and end of the session, else false.
	// Ignore the Open flag.
	isActiveSession : function(session, nowInMillis) {
		var sessionStart = Date.parse(session["Date"]);
		var sessionEnd = Date.parse(session["EndDate"]);
		var activeSession = ((sessionStart <= nowInMillis) && (sessionEnd >= nowInMillis));
		return activeSession;
	}

}
