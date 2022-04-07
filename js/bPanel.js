var bPanel = {

	allBoatsStatus : {},
	currentBoatTrips : {},

	boatGroupHtml : '<div class="w3-bar-block"><span class="w3-bar-item boatitem menuitem" id="do-openBoatGroup_[cntOrStatus]">'
			+ '[cntOrStatusText]&nbsp;<b>&#x25be</b></span></div>',
	startTripHtml : '<div class="w3-bar-block w3-hide w3-medium boatGroup[cntOrStatus]">'
			+ '<span class="w3-bar-item menuitem boatitem" id="do-getForm_startTrip_[boatId]">&nbsp;&nbsp;[boatName]</span></div>',
	bookAboatHtml : '<div class="w3-bar-block w3-hide w3-medium boatGroup[cntOrStatus]">'
			+ '<span class="w3-bar-item menuitem boatitem" id="do-getForm_bookAboat_[boatId]">&nbsp;&nbsp;[boatName]</span></div>',
	showAboatHtml : '<div class="w3-bar-block w3-hide w3-medium boatGroup[cntOrStatus]">'
			+ '<span class="w3-bar-item">&nbsp;&nbsp;[boatName]</span></div>',
	showBoatDatasheetHtml : '<div class="w3-bar-block w3-hide w3-medium boatGroup[cntOrStatus]">'
			+ '<span class="w3-bar-item menuitem boatitem" id="do-getDatasheet_boat_[boatId]">&nbsp;&nbsp;[boatName]</span></div>',
			
	listpanelHeader : { 
			logbook : "Fahrtenbuch " + $_logbookname,
			reservations : "Reservierungen",
			damages : "Schadensmeldungen"
	},
	
	/**
	 * set the availability status for all boats.
	 */
	_setAllBoatsStatus : function() {
		if (!bLists.lists.efaWeb_boats || !bLists.lists.efaWeb_boatstatus)
			return;
		this.allBoatsStatus = {};
		// clear the status for all boats first
		bLists.lists.efaWeb_boats.forEach(function(row) {
			bPanel.allBoatsStatus[row.Id] = false;
		});
		// set the status, if it is set in the boat status list
		bLists.lists.efaWeb_boatstatus.forEach(function(row) {
			if (row.BoatId)
				bPanel.allBoatsStatus[row.BoatId] = row;
		});
		// collect the boats on the water.
		bLists.lists.efaWeb_logbook.forEach(function(row) {
			var boatStatus = bPanel.allBoatsStatus[row.BoatId];
			if (row.BoatId.localeCompare("96ec8eef-4662-4e22-8296-c1428818b735") == 0)
				boatStatus = bPanel.allBoatsStatus[row.BoatId];
			if (row.Open && bBoatstatus.isOnTheWater(boatStatus)) 
				bPanel.currentBoatTrips[row.BoatId] = row;
		});
	},
	
	// get the adaped list panel header based on the last triggered action
	_getlistPanelHeader(action) {
		var html = "<div class='w3-row'><div class='w3-col l2'><h4>" + this.listpanelHeader[action]
		          + "</h4></div><div class='w3-col l2'><p style='text-align:right'>";
		for (otherAction of Object.keys(this.listpanelHeader))
			if (otherAction.localeCompare(action) != 0)
				html += "<a class='menuitem formbutton' id='do-showList_" + otherAction + "_25'>" + this.listpanelHeader[otherAction] + "</a> ";
		return html + "</p></div></div>";
	},

	/**
	 * Provide a HTML formatted list of the available boats, grouped by fitting
	 * persons.
	 */
	listAvailableBoats : function() {
		if (!bLists.loadingCompleted())
			return;
		this._setAllBoatsStatus();
		bReservation.markCurrentlyBookedBoats();

		var boatsToShow = [];
		for (boatId in this.allBoatsStatus) {
			var boatstatus = this.allBoatsStatus[boatId];
			var boat = bLists.lists.efaWeb_boats[bLists.indices.efaWeb_boats_guids[boatId]];
			var isBooked = boat && boat["statusToUseInPanel"] && (boat["statusToUseInPanel"].localeCompare("BOOKED") == 0);
			if (bBoatstatus.isAvailable(boatstatus) && !isBooked) {
				var boat = bLists.lists.efaWeb_boats[bLists.indices.efaWeb_boats_guids[boatId]];
				if (boat && boat.InvalidFrom > Math.floor(Date.now() / 1000))
					boatsToShow
							.push(bLists.lists.efaWeb_boats[bLists.indices.efaWeb_boats_guids[boatId]]);
			}
		}
		// sort for 1. crewNcoxCnt, 2. Name
		boatsToShow.sort(function(a, b) {
			return 100 * (a["crewNcoxCnt"] - b["crewNcoxCnt"])
					+ a["Name"].localeCompare(b["Name"]);
		});
		// build list.
		var currentCoxNcrewCnt = -1;
		var html = "";
		var isAllowedStartTrip = isAllowedAction("getForm_startTrip");
		var isAllowedBookAboat = isAllowedAction("getForm_bookAboat");
		var actionForAvailable = (isAllowedStartTrip) ? this.startTripHtml : ((isAllowedBookAboat) ? this.bookAboatHtml : this.showAboatHtml); 
		boatsToShow.forEach(function(boat) {
			var cntOrStatusText = $_seatCntText[boat["crewNcoxCnt"]];
			if (boat["crewNcoxCnt"] > 0) {
				// show a headline on top of the boat
				if (boat["crewNcoxCnt"] != currentCoxNcrewCnt) {
					currentCoxNcrewCnt = boat["crewNcoxCnt"];
					html += bPanel.boatGroupHtml.replace(
							/\[cntOrStatusText\]/g, cntOrStatusText).replace(
							/\[cntOrStatus\]/g, boat["crewNcoxCnt"]);
				}
				bBoat.getNames(boat).forEach(
						function(name) {
							html += actionForAvailable.replace(
									/\[boatName\]/g, name).replace(
									/\[boatId\]/g, name).replace(
									/\[cntOrStatus\]/g, boat["crewNcoxCnt"]);
						});
			}
		});
		return html;
	},

	/**
	 * Provide a HTML formatted list of the available boats, grouped by fitting
	 * persons.
	 */
	listNotAvailableBoats : function() {
		if (!bLists.loadingCompleted())
			return;
		this._setAllBoatsStatus();
		bReservation.markCurrentlyBookedBoats();
		var boatsToShow = [];
		for (boatId in this.allBoatsStatus) {
			var boatstatus = this.allBoatsStatus[boatId];
			var boat = bLists.lists.efaWeb_boats[bLists.indices.efaWeb_boats_guids[boatId]];
			var isBooked = boat && boat["statusToUseInPanel"] && (boat["statusToUseInPanel"].localeCompare("BOOKED") == 0);
			if (!bBoatstatus.isAvailable(boatstatus) || isBooked) {
				var boat = bLists.lists.efaWeb_boats[bLists.indices.efaWeb_boats_guids[boatId]];
				// if a boat Id was deleted useing the (leer) option and a boat
				// name was entered, the var boat will be undefined.
				if (boat && boat.InvalidFrom > Math.floor(Date.now() / 1000))
					boatsToShow
							.push(bLists.lists.efaWeb_boats[bLists.indices.efaWeb_boats_guids[boatId]]);
			}
		}
		// add the availability status to the boats
		boatsToShow.forEach(function(boat) {
			if (bPanel.allBoatsStatus[boat.Id])
				boat["statusToUseInPanel"] = bBoatstatus
						.statusToUse(bPanel.allBoatsStatus[boat.Id]);
			else
				boat["statusToUseInPanel"] = "HIDE";
		});
		bReservation.markCurrentlyBookedBoats();
		
		// sort for 1. availability status, 2. Name
		boatsToShow.sort(function(a, b) {
			return 100
					* (a["statusToUseInPanel"]
							.localeCompare(b["statusToUseInPanel"]))
					+ a["Name"].localeCompare(b["Name"]);
		});
		// build list.
		var currentBoatStatus = "";
		var html = "";
		boatsToShow.forEach(function(boat) {
			if (bPanel.allBoatsStatus[boat.Id]
					&& (boat.statusToUseInPanel.localeCompare("HIDE") != 0)) {
				var cntOrStatusText = bBoatstatus[boat.statusToUseInPanel];
				// show a headline on top of the boat
				if (cntOrStatusText.localeCompare(currentBoatStatus) != 0) {
					currentBoatStatus = cntOrStatusText;
					html += bPanel.boatGroupHtml.replace(
							/\[cntOrStatusText\]/g, cntOrStatusText).replace(
							/\[cntOrStatus\]/g, boat.statusToUseInPanel);
				}
				
				html += bPanel.showBoatDatasheetHtml.replace(/\[boatId\]/g, boat.Id)
						.replace(/\[boatName\]/g, boat.Name).replace(
								/\[cntOrStatus\]/g, boat.statusToUseInPanel);
			}
		});
		return html;
	},

	/**
	 * show a list in the list panel section
	 */
	showList : function(type, count) {
		$('#bths-listpanel-header').html(this._getlistPanelHeader(type));
		if (type.localeCompare("logbook") == 0)
			$('#bths-listpanel-list').html(bStatistics.getTripsHtml(true, count));
		else if (type.localeCompare("reservations") == 0)
			$('#bths-listpanel-list').html(bReservation.getUpcomingReservations(count, "@All"));
		else if (type.localeCompare("damages") == 0)
			$('#bths-listpanel-list').html(bDamage.getOpenDamages(count));
	},

	/**
	 * Update the panel display, e.g. after a trip was entered.
	 */
	update : function() {
		if (bTxQueue.paused > Date.now()) {
			$('#bths-mainpanel-left')
					.html(
							"Wegen Überlastantwort vom efaCloud-Server ist jetzt leider Pause.");
			var waitMins = parseInt((bTxQueue.paused - Date.now()) / 60000);
			$('#bths-mainpanel-right').html(
					"Wir bitten um Geduld für weitere " + waitMins
							+ " Minuten.");
		}
		try {
			var leftPanelContents = bPanel.listAvailableBoats();
			$('#bths-mainpanel-left').html(leftPanelContents);
			var rightPanelContents = bPanel.listNotAvailableBoats();
			$('#bths-mainpanel-right').html(rightPanelContents);
			// The panels are also menus, so redo the event binding here.
			if (isAllowedAction("showList_logbook_50")) doAction("showList_logbook_50");
			bindMenuEvents();
		} catch (e) {
			showException(e);
		}
	}

}