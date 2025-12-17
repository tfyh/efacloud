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

var oBoat = {

	descriptions : [],
	names : [],
	coxed : [],
	rigging : [],
	seats : [],
	seatsCnt : [],
	crewNcoxCnt : 0,
	defaultVariant : 1,

	/**
	 * parse a boat record into oBoat fields. IMPORTANT: This must be the same
	 * way variants are declared as in
	 * efa_tables.php->virtual_boatVariants_csv().
	 */
	init : function(boatRecord) {
		if (!boatRecord)
			return;
		// Count the type variants. Descriptions may be empty, while TYPE or NUMSEATS usually are not.
		let typeVariantsCount = (boatRecord["TypeType"]) ? boatRecord["TypeType"].split(/;/g).length 
				: ((boatRecord["TypeSeats"]) ? boatRecord["TypeSeats"].split(/;/g).length : 1);
		this.descriptions = (boatRecord["TypeDescription"]) ? boatRecord["TypeDescription"]
		.split(/;/g)
		: [];
		while (this.descriptions.length < typeVariantsCount) {
			this.descriptions.push("");
		}
		// IMPORTANT: This must be the same way variants are declared as in
		// efa_tables.php->virtual_boatVariants_csv()
		var name = oBoat.getName(boatRecord);
		oBoat.names = [];
		this.descriptions.forEach(function(d) {
			// IMPORTANT: This must be the same way variants are declared as in
			// efa_tables.php->virtual_boatVariants_csv()
			var namePlus = (d) ? name + " (" + d + ")" : name;
			oBoat.names.push(namePlus);
		});
		var coxing = boatRecord["TypeCoxing"].split(/;/g);
		oBoat.coxed = [];
		coxing.forEach(function(c) {
			oBoat.coxed.push(c.localeCompare("COXED") == 0);
		});
		this.rigging = (boatRecord["TypeRigging"]) ? boatRecord["TypeRigging"]
				.split(/;/g) : [ "" ];
		this.defaultVariant = parseInt(boatRecord["DefaultVariant"]) - 1;
		this.seats = boatRecord["TypeSeats"].split(/;/g);
		this.seatsCnt = [];
		this.seats.forEach(function(s) {
			var seatsCnt = parseInt(s.replace(/\D/g, ''));
			oBoat.seatsCnt.push(seatsCnt);
		});
		oBoat.crewNcoxCnt = boatRecord["crewNcoxCnt"];
	},

	/**
	 * Names, one for each variant. This will be "Name (TypeDescription)" in
	 * case a TypeDescription is provided for the Variant, elee "Name"
	 */
	getNames : function(boatRecord) {
		this.init(boatRecord);
		return this.names;
	},

	/**
	 * the boat name. This will be "Name (NameAffix)", if NameAffix is not
	 * empty, "Name" else. getName therefore does return a different name for a
	 * boat than getNames.
	 */
	getName : function(boatRecord) {
		return (boatRecord.NameAffix) ? boatRecord.Name + " ("
				+ boatRecord.NameAffix + ")" : boatRecord.Name;
	},

	/**
	 * Get the index of the variant based on the provided name which includes
	 * the variant's TypeDescription or not, see "getNames()", This is not the
	 * "TypeVariant" value of the variant, but the array index [0...x]
	 */
	getVariantIndexForName : function(boatRecord, name) {
		if (!boatRecord)
			return -1;
		this.init(boatRecord);
		for (var i = 0; i < oBoat.names.length; i++)
			if (name.localeCompare(oBoat.names[i]) == 0)
				return i;
		return 0;
	},

	/**
	 * get a full description of the boat as datasheet to display in the modal.
	 */
	getDatasheet : function(boatRecord) {
		html = "<h4>" + boatRecord["Name"] + "</h4><p>";
		for (boatField in boatRecord) {
			var value = boatRecord[boatField];
			if ((boatField.localeCompare("TypeCoxing") == 0)
					&& $_efaTypes["COXING"])
				value = $_efaTypes["COXING"][value];
			else if ((boatField.localeCompare("TypeSeats") == 0)
					&& $_efaTypes["NUMSEATS"])
				value = $_efaTypes["NUMSEATS"][value];
			else if ((boatField.localeCompare("TypeRigging") == 0)
					&& $_efaTypes["RIGGING"])
				value = $_efaTypes["RIGGING"][value];
			else if (boatField.localeCompare("InvalidFrom") == 0)
				value = cToolbox.format_millis_DE(parseInt(value));
			// hack to avoid undefined for multiple coxings etc.
			if (! value && boatRecord[boatField])
				value = boatRecord[boatField];
			if ($_namesTranslated[boatField]
					&& (boatField.localeCompare("ecrid") != 0))
				html += "<b>" + $_namesTranslated[boatField] + "</b>: " + value
						+ "<br>";
		}
		var boatstatusRowNumber = cLists.indices.efaWeb_boatstatus_guids[boatRecord["Id"]];
		var boatstatusRecord = cLists.lists.efaWeb_boatstatus[boatstatusRowNumber];
		html += "<h4>Bootsstatus</h4><p>";
		for (boatstatusField in boatstatusRecord) {
			if ($_namesTranslated[boatstatusField]
					&& (boatstatusField.localeCompare("Id") != 0)
					&& (boatstatusField.localeCompare("ecrid") != 0)
					&& (boatstatusField.localeCompare("EntryNo") != 0)
					&& (boatstatusField.localeCompare("UnknownBoat") != 0)
					&& (boatstatusField.localeCompare("ShowInList") != 0)
					&& (boatstatusField.localeCompare("ChangeCount") != 0))
				html += "<b>" + $_namesTranslated[boatstatusField] + "</b>: "
						+ boatstatusRecord[boatstatusField] + "<br>";
		}
		return html + "</p>";
	},

	/**
	 * Get the correct seat type text description
	 */
	getSeatTypeText : function(boatRecord, boatVariantIndex) {
		var bTypeSeats = boatRecord["TypeSeats"].split(/;/g);
		var bTypeCoxed = boatRecord["TypeCoxing"].split(/;/g);
		var seatTypeText = "";

		for (var i = 0; i < bTypeSeats.length; i++) {
			var withCox = (bTypeCoxed[i].localeCompare("COXED") == 0) ? " mit"
					: "";
			if ($_efaTypes && $_efaTypes["NUMSEATS"]
					&& $_efaTypes["NUMSEATS"][bTypeSeats[i]])
				seatTypeText += $_efaTypes["NUMSEATS"][bTypeSeats[i]] + withCox
						+ ", ";
		}
		seatTypeText = seatTypeText.substring(0, seatTypeText.length - 2);
		return seatTypeText;
	},

	/**
	 * Get the correct seat type text description
	 */
	getSeatTypeShort : function(boatRecord, boatVariantIndex) {
		var bTypeSeats = boatRecord["TypeSeats"].split(/;/g);
		var bTypeCoxed = boatRecord["TypeCoxing"].split(/;/g);
		var seatTypeShort = "";
		for (var i = 0; i < bTypeSeats.length; i++) {
			seatTypeShort += bTypeSeats[i] + ((bTypeCoxed[i].localeCompare("COXED") == 0) ? "m"
					: "") + ",";
		}
		seatTypeShort = seatTypeShort.substring(0, seatTypeShort.length - 1);
		return seatTypeShort;
	},

	/**
	 * Get the correct boat type text description
	 */
	getBoatTypeText : function(boatRecord, boatVariantIndex) {
		var bTypeType = boatRecord["TypeType"].split(/;/g);
		var bTypeRigging = boatRecord["TypeRigging"].split(/;/g);
		if (!boatVariantIndex && (boatVariantIndex != 0))
			typeTypeText = $_efaTypes["BOAT"][bTypeType[0]];
		else
			typeTypeText = $_efaTypes["BOAT"][bTypeType[boatVariantIndex]]
					+ " "
					+ $_efaTypes["RIGGING"][bTypeRigging[boatVariantIndex]];
		return typeTypeText;
	},
	
	/**
	 * Update the boat status (CurrentStatus) for trip start, trip end or cancel.
	 * 
	 * @param tx
	 *            the transaction executed which contains the trip records key
	 */
	updateBoatStatusOnSessionEvent : function(tx, onSessionUpdate, onSessionCancel) {

		// get trip record
		var tripRecord = {};
		if (!tx.record || (tx.record.length == 0))
			return;
		// use the values provided by the form first
		tx.record.forEach(function(keyValue) {
			tripRecord[keyValue.split(";")[0]] = keyValue.split(";")[1];
		});
		
		if (onSessionUpdate) {
			// update with the server generated keys. This will also adjust a fixed
			// EntryId
			if (tx.resultMessage)
				tx.resultMessage.split(/;/g).forEach(
						function(keyValue) {
							if (keyValue.indexOf("=") > 0)
								// update will respond with "ok." instead of
								// key=value pairs
								tripRecord[keyValue.split("=")[0]] = keyValue
										.split("=")[1];
						});
			// merge with provided ecrid into list
			var index = cLists.indices.all_ecrids[tripRecord.ecrid];
			if (index)
				cLists.mergeRecord(tripRecord);
			else {
				cLists.addRecord("efaWeb_logbook", tripRecord);
			}
		}

		// If the boat is known, there is a boatstatus record.
		var relatedBoatStatus = false;
		for (boatStatusId in bPanel.allBoatsStatus) {
			var boatStatus = bPanel.allBoatsStatus[boatStatusId].boatstatus;
			var sameBoat = (boatStatusId.localeCompare(tripRecord.BoatId) == 0); // known
																					// boat
			if (sameBoat)
				relatedBoatStatus = boatStatus;
		}
		// If the boat is unknown and the trip is ending, find the temporary
		// boatstatus by matching the sessions EntryId.
		if (!relatedBoatStatus)
			for (boatStatusId in bPanel.allBoatsStatus) {
				if (oBoatstatus.isOnTheWater(boatStatus)) {
					// the efaWeb-logbook is always the current one, not
					// multiple year, soe EntryId is unique
					var session = cLists.lists.efaWeb_logbook[cLists.indices.efaWeb_logbook_nids[boatStatus.EntryNo]];
					var sameEntryId = (parseInt(session.EntryId) == parseInt(tripRecord.EntryId)); // same
																									// session
					if (sameEntryId)
						relatedBoatStatus = boatStatus;
				}
			}

		var boatId = (relatedBoatStatus) ? relatedBoatStatus.BoatId
				: tripRecord["BoatId"];
		if (!boatId && !tripRecord["BoatName"])
			return;
		var unknownBoat = (!tripRecord["BoatId"]) || !relatedBoatStatus
				|| (relatedBoatStatus.UnknownBoat.localeCompare("true") == 0);
		var boat = (unknownBoat) ? false
				: cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[boatId]];

		// set fields which shall change of boatstatus record
		record = {};
		if (relatedBoatStatus) {
			// the boat status is known. This is for trip end (then also unknown boats have a status)
			// and for known boats at trip start.
			record["BoatId"] = boatId;
			// get the boat via the panel. Indices may not have the unknown boats at trip end. 
			boat = bPanel.allBoatsStatus[boatId].boat;
			record["BoatText"] = (boat) ? boat.Name
					: relatedBoatStatus["BoatText"];
			record["BaseStatus"] = relatedBoatStatus["BaseStatus"];
			record["ecrid"] = relatedBoatStatus["ecrid"];
		} else {
			record["BoatId"] = (unknownBoat) ? cToolbox.generateGUID() : boatId; // ==
																					// boat.Id
			record["BoatText"] = (unknownBoat) ? tripRecord["BoatName"]
					: boat.Name;
			record["BaseStatus"] = "AVAILABLE"; // for both an unknown boat and
												// a boat with missing
												// boatstatus record
			record["UnknownBoat"] = (unknownBoat) ? "true" : "";
			// ecrid will be generated by the server.
		}

		// insert, update or delete the boat status record
		var action;
		if (onSessionCancel || (tripRecord.Open && tripRecord.Open.localeCompare("false") == 0)) {
			// update or delete boat status record on trip end.
			record["Logbook"] = "";
			record["EntryNo"] = "";
			record["CurrentStatus"] = "AVAILABLE";
			record["Comment"] = "";
			action = (unknownBoat) ? "delete" : "update";
			// Unknown boats have an issue with timely boatstatus closing. THis is a hack to ensure proper display
			if (unknownBoat && relatedBoatStatus)
				relatedBoatStatus.NoOpenSession = true;
		} else {
			// update or insert boat status record on trip start.
			var destination = (tripRecord["DestinationId"]) ? cLists.lists.efaWeb_destinations[cLists.indices.efaWeb_destinations_guids[tripRecord["DestinationId"]]]["Name"]
					: tripRecord["DestinationName"];
			var onTheWaterComment = _("BPGG04|Underway to %1 since %2 ...",
					destination, tripRecord["Date"], tripRecord["StartTime"],tripRecord["AllCrewNames"],tripRecord["EntryId"],
					$_logbookname);
			record["Logbook"] = $_logbookname;
			record["EntryNo"] = tripRecord["EntryId"];
			record["CurrentStatus"] = "ONTHEWATER";
			record["Comment"] = onTheWaterComment;
			action = (relatedBoatStatus) ? "update" : "insert";
		}

		// send boatstatus to server
		var pairs = cTxQueue.recordToPairs(record);
		tx = cTxQueue.addNewTxToPending(action, "efa2boatstatus", pairs, 0,
				bPanel.update(), null);

	},

	/**
	 * Update the boat status (CurrentStatus) to ONTHEWATER for the given
	 * tripRecord or clear it to AVAILABLE, if tripRecord["Open"] is "false".
	 * Also set the EntryNo and the comment where the boat is heading to.
	 * 
	 * @param tx
	 *            the transaction executed which contains the trip records key
	 */
	updateBoatStatusOnSession : function(tx) {
		oBoat.updateBoatStatusOnSessionEvent(tx, true, false);
	},

	/**
	 * Update the boat status (CurrentStatus) to AVAILABLE, if a session was
	 * cancelled.
	 */
	updateBoatStatusOnCancel : function(tx) {
		oBoat.updateBoatStatusOnSessionEvent(tx, false, true);
	},

	/**
	 * Update the boat status for the given damageRecord.
	 */
	updateBoatStatusOnDamage : function(damageRecord) {

		var boatId = damageRecord["BoatId"];
		boat = cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[boatId]];
		// damages must have a BoatId, if not, not status change.
		if (!boat)
			return;

		// set fields which shall change of boatstatus record
		record = {};
		if (damageRecord.Severity.localeCompare("NOTUSEABLE") == 0) {
			record["ShowInList"] = "NOTAVAILABLE";
		}
		var boatStatusRow = cLists.indices.efaWeb_boatstatus_guids[boatId];
		var boatStatus = (boatStatusRow) ? cLists.lists.efaWeb_boatstatus[boatStatusRow]
				: false;
		if (boatStatus.ecrid) 
			record["ecrid"] = boatStatus.ecrid;

		// send boatstatus to server
		var pairs = cTxQueue.recordToPairs(record);
		var action = (boatStatus) ? "update" : "insert";
		tx = cTxQueue.addNewTxToPending(action, "efa2boatstatus", pairs, 0,
				null, null);
	},

}
