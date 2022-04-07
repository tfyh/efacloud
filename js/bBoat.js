var bBoat = {

	descriptions : [],
	names : [],
	coxed : [],
	rigging : [],
	seats : [],
	seatsCnt : [],
	crewNcoxCnt : 0,
	defaultVariant : 1,

	/**
	 * parse a boat record into bBoat fields
	 */
	init : function(boatRecord) {
		if (!boatRecord)
			return;
		this.descriptions = (boatRecord["TypeDescription"]) ? boatRecord["TypeDescription"].split(/;/g) : [ "" ];
		var name = boatRecord["Name"];
		bBoat.names = [];
		this.descriptions.forEach(function(d) {
			var namePlus = (d) ? name + " (" + d + ")" : name;
			bBoat.names.push(namePlus);
		});
		var coxing = boatRecord["TypeCoxing"].split(/;/g);
		bBoat.coxed = [];
		coxing.forEach(function(c) {
			bBoat.coxed.push(c.localeCompare("COXED") == 0);
		});
		this.rigging = (boatRecord["TypeRigging"]) ? boatRecord["TypeRigging"].split(/;/g) : [ "" ];
		this.defaultVariant = parseInt(boatRecord["DefaultVariant"]) - 1;
		this.seats = boatRecord["TypeSeats"].split(/;/g);
		this.seatsCnt = [];
		this.seats.forEach(function(s) {
			var seatsCnt = parseInt(s.replace(/\D/g, ''));
			bBoat.seatsCnt.push(seatsCnt);
		});
		bBoat.crewNcoxCnt = boatRecord["crewNcoxCnt"];
	},

	getNames : function(boatRecord) {
		this.init(boatRecord);
		return this.names;
	},

	getVariantIndexForName : function(boatRecord, name) {
		this.init(boatRecord);
		for (var i = 0; i < bBoat.names.length; i++)
			if (name.localeCompare(bBoat.names[i]) == 0)
				return i;
		return 0;
	},
	
	getDatasheet : function(boatRecord) {
		html = "<h4>" + boatRecord["Name"] + "</h4><p>";
		for (boatField in boatRecord) {
			html += "<b>" + boatField + "</b>: " + boatRecord[boatField] + "<br>";
		}
		var boatstatusRowNumber = bLists.indices.efaWeb_boatstatus_guids[boatRecord["Id"]];
		var boatstatusRecord = bLists.lists.efaWeb_boatstatus[boatstatusRowNumber];
		html += "<h4>Bootsstatus</h4><p>";
		for (boatstatusField in boatstatusRecord) {
			html += "<b>" + boatstatusField + "</b>: " + boatstatusRecord[boatstatusField] + "<br>";
		}
		return html + "</p>";
	},

	/**
	 * Update the boat status for the given tripRecord. Set tripRecord["Open"] to "false" to clear the status. 
	 */
	updateBoatStatus : function(tripRecord) {

		var boatId = tripRecord["BoatId"];
		boat = bLists.lists.efaWeb_boats[bLists.indices.efaWeb_boats_guids[boatId]];
		// trips may be for a foreign boat, then do not update any status
		if (!boat)
			return;

		// collect boatstatus record
		record = {};
		record["BoatId"] = boat.Id;
		if (tripRecord["Open"].localeCompare("false") == 0) {
			record["Logbook"] = "";
			record["EntryNo"] = "";
			record["CurrentStatus"] = "AVAILABLE";
			record["Comment"] = "";
		} else {
			var onTheWaterComment = "Unterwegs nach " + bForm.inputs["DestinationName"]
					+ " seit " + tripRecord["Date"] + " um " + tripRecord["StartTime"]
					+ " mit " + tripRecord["AllCrewNames"];
			record["Logbook"] = $_logbookname;
			record["EntryNo"] = tripRecord["EntryId"];
			record["CurrentStatus"] = "ONTHEWATER";
			record["Comment"] = onTheWaterComment;
		}

		// add ChangeCount and LastModified
		var boatStatusRow = bLists.indices.efaWeb_boatstatus_guids[boatId];
		var boatStatus = (boatStatusRow) ? bLists.lists.efaWeb_boatstatus[boatStatusRow] : false;
		record["ChangeCount"] = (boatStatus) ? parseInt(boatStatus["ChangeCount"]) + 1 : 1;
		record["LastModified"] = Date.now();

		// send boatstatus to server
		var pairs = bTxQueue.recordToPairs(record);
		var action = (boatStatus) ? "update" : "insert";
		tx = bTxQueue.addNewTxToPending(action, "efa2boatstatus", pairs, 0, null);
	},
	
	
}