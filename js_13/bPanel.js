/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c)
 * 2001-2022 by Nicolas Michael Website: http://efa.nmichael.de/ License: GNU
 * General Public License v2. Module efaCloud: Copyright (c) 2020-2021 by Martin
 * Glade Website: https://www.efacloud.org/ License: GNU General Public License
 * v2
 */

var bPanel = {

	allBoatsStatus : {}, // this carries also the reference on the boat and
							// the respective session.

	boatGroupHtml : '<div class="w3-bar-block"><span class="w3-bar-item boatitem menuitem" id="do-openBoatGroup_[cntOrStatus]">'
			+ '[cntOrStatusText]&nbsp;<b>&#x25be</b></span></div>',
	startSessionHtml : '<div class="w3-bar-block w3-hide w3-medium boatGroup[cntOrStatus]">'
			+ '<span class="w3-bar-item menuitem boatitem" id="do-getForm_startSession_[boatId]">&nbsp;&nbsp;[boatName] ([boatType])</span></div>',
	bookAboatHtml : '<div class="w3-bar-block w3-hide w3-medium boatGroup[cntOrStatus]">'
			+ '<span class="w3-bar-item menuitem boatitem" id="do-getForm_bookAboat_[boatId]">&nbsp;&nbsp;[boatName] ([boatType])</span></div>',
	showAboatHtml : '<div class="w3-bar-block w3-hide w3-medium boatGroup[cntOrStatus]">'
			+ '<span class="w3-bar-item">&nbsp;&nbsp;[boatName] ([boatType])</span></div>',
	showBoatDatasheetHtml : '<div class="w3-bar-block w3-hide w3-medium boatGroup[cntOrStatus]">'
			+ '<span class="w3-bar-item menuitem boatitem" id="do-getDatasheet_boat_[boatId]">&nbsp;&nbsp;[boatName]</span></div>',
			
	/**
	 * set the availability status for all boats.
	 */
	_setAllBoatsStatus : function() {
		if (!cLists.lists.efaWeb_boats || !cLists.lists.efaWeb_boatstatus)
			return;
		this.allBoatsStatus = {};
		// clear the status for all boats first
		cLists.lists.efaWeb_boats.forEach(function(row) {
			bPanel.allBoatsStatus[row.Id] = false;
		});
		// set the status, if it is set in the boat status list
		cLists.lists.efaWeb_boatstatus.forEach(function(row) {
			if (row.BoatId) {
				bPanel.allBoatsStatus[row.BoatId] = { boatstatus : row };
				var boat;
				if (row.UnknownBoat && row.UnknownBoat.localeCompare("true") == 0) {
					boat = {
							Id : row.BoatId,
							Name : row.BoatText,
					};
				} else {
					boat = cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[row.BoatId]];
				}
				bPanel.allBoatsStatus[row.BoatId]["boat"] = boat;
			}
		});
		// add bookings and closed sessions
		oReservation.markCurrentlyBookedBoats();
		// collect the boats on the water from the boat's status, link the
		// session to the boat.
		for (boatStatusId in bPanel.allBoatsStatus) {
			var boatStatus = bPanel.allBoatsStatus[boatStatusId].boatstatus;
			if (oBoatstatus.isOnTheWater(boatStatus)) {
				// the efaWeb-logbook is always the current one, not multiple
				// year, soe EntryId is unique
				var session = cLists.lists.efaWeb_logbook[cLists.indices.efaWeb_logbook_nids[boatStatus.EntryNo]];
				bPanel.allBoatsStatus[boatStatusId]["session"] = session;
			}
		}
		// collect the closed sessions that are currently active, which may have
		// the wrong boatstatus.
		var now = Date.now();
		cLists.lists.efaWeb_logbook.forEach(function(session) {
			var boatStatus = bPanel.allBoatsStatus[session.BoatId];
			// this will not include the unknown boats, because there is no
			// BoatId
			if (oSession.isActiveSession(session, now) && session.BoatId) {
				bPanel.allBoatsStatus[session.BoatId]["session"] = session;
				bPanel.allBoatsStatus[session.BoatId]["boatstatus"]["CurrentStatus"] = "ONTHEWATER";
			}
		});
	},
	
	// get the (adapted) list panel header based on the last triggered action.
	// [Adaption no more used.]
	_getlistPanelHeader() {
		var html = "<div class='w3-row'><div class='w3-col l2'><h4>" + 
			(($_logbook_allowance_all) ? "Fahrtenbuch " + $_logbookname : "Eigene Fahrten in diesem Jahr")
		          + "</h4></div><div class='w3-col l2'><p style='text-align:right'>";
		html += "<a class='menuitem formbutton' id='do-showList_reservations_25'>Reservierungen</a> ";
		html += "<a class='menuitem formbutton' id='do-showList_damages_25'>Schadensmeldungen</a> ";
		return html + "</p></div></div>";
	},

	/**
	 * Return the boats to show, either the available ones, or the not available
	 * ones.
	 */
	_boatsToShow : function(available) {
		var boatsToShow = [];
		var now = Date.now();
		for (boatId in this.allBoatsStatus) {
			if (boatId.indexOf("3aa") >= 0)
				boatId = boatId;
			// decide whether the boat is "on the water" based on the boat
			// status
			var boatstatus = this.allBoatsStatus[boatId].boatstatus;
			var isOnTheWater = (boatstatus && boatstatus["CurrentStatus"] && boatstatus["CurrentStatus"].localeCompare("ONTHEWATER") == 0);
			var isUnknownBoat = (boatstatus && boatstatus["UnknownBoat"] && boatstatus["UnknownBoat"].localeCompare("true") == 0);
			// decide whether the boat is "booked" or "invalid" on the boat
			// record.
			var boat = this.allBoatsStatus[boatId].boat;
			var isValid = isUnknownBoat || (boat && parseInt(boat["InvalidFrom"]) > now);
			var isBooked = !isUnknownBoat && (boat && boat["CurrentlyBooked"] && (boat["CurrentlyBooked"].localeCompare("true") == 0));
			var thisAvailable = (oBoatstatus.isAvailable(boatstatus) && !isBooked && !isOnTheWater && !isUnknownBoat);
			if (isValid && (available == thisAvailable))
				boatsToShow.push(boat);
		}
		return boatsToShow;
	},
	
	/**
	 * Provide a HTML formatted list of the available boats, grouped by fitting
	 * persons.
	 */
	listAvailableBoats : function() {
		if (!cLists.loadingCompleted())
			return;
		this._setAllBoatsStatus();
		var boatsToShow = this._boatsToShow(true);

		// sort for 1. crewNcoxCnt, 2. Name
		boatsToShow.sort(function(a, b) {
			return 10000 * (a["TypeSeats"].localeCompare(b["TypeSeats"]))
					+ 100 * (b["TypeCoxing"].localeCompare(a["TypeCoxing"]))
					+ a["Name"].localeCompare(b["Name"]);
		});
		// build list.
		// var currentCoxNcrewCnt = -1;
		var currentTypeSeats = "";
		var html = "";
		var isAllowedStartSession = isAllowedAction("getForm_startSession");
		var isAllowedBookAboat = isAllowedAction("getForm_bookAboat");
		var actionForAvailable = (isAllowedStartSession) ? this.startSessionHtml : ((isAllowedBookAboat) ? this.bookAboatHtml : this.showAboatHtml); 
		boatsToShow.forEach(function(boat) {
			var seatTypeText = oBoat.getSeatTypeText(boat);
			var seatTypeShort = oBoat.getSeatTypeShort(boat);
			if (seatTypeText) {
				// show a headline on top of the boat list
				if (seatTypeShort.localeCompare(currentTypeSeats) !== 0) {
					currentTypeSeats = seatTypeShort;
					html += bPanel.boatGroupHtml.replace(
							/\[cntOrStatusText\]/g, seatTypeText).replace(
							/\[cntOrStatus\]/g, seatTypeShort);
				}
				oBoat.getNames(boat).forEach(
						function(name) {
							var variantIndex = oBoat.getVariantIndexForName(boat, name);
							html += actionForAvailable.replace(
									/\[boatName\]/g, name).replace(
									/\[boatType\]/g, oBoat.getBoatTypeText(boat, variantIndex)).replace(
									/\[boatId\]/g, name).replace(
									/\[cntOrStatus\]/g, seatTypeShort);
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
		if (!cLists.loadingCompleted())
			return;
		this._setAllBoatsStatus();

		var boatsToShow = this._boatsToShow(false);
		boatsToShow.forEach(function(boat) {
			// decide whether the boat is "on the water" based on the boat
			// status
			var boatstatus = bPanel.allBoatsStatus[boat.Id].boatstatus;
			var isOnTheWater = (boatstatus && boatstatus["CurrentStatus"].localeCompare("ONTHEWATER") == 0);
			// decide whether the boat is "booked" or "invalid" on the boat
			// record.
			var boat = bPanel.allBoatsStatus[boat.Id].boat;
			var isBooked = boat && boat["CurrentlyBooked"] && (boat["CurrentlyBooked"].localeCompare("true") == 0);
			boat["statusToUseInPanel"] = (isOnTheWater) ? "ONTHEWATER" : ((isBooked) ? "BOOKED" : oBoatstatus.statusToUse(boatstatus));
		});
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
				var cntOrStatusText = oBoatstatus[boat.statusToUseInPanel];
				// show a headline on top of the boat
				if (cntOrStatusText.localeCompare(currentBoatStatus) != 0) {
					currentBoatStatus = cntOrStatusText;
					html += bPanel.boatGroupHtml.replace(
							/\[cntOrStatusText\]/g, cntOrStatusText).replace(
							/\[cntOrStatus\]/g, boat.statusToUseInPanel);
				}
				
				html += bPanel.showBoatDatasheetHtml.replace(/\[boatId\]/g, boat.Id)
						.replace(/\[boatName\]/g, boat.Name)
						.replace(/\[cntOrStatus\]/g, boat.statusToUseInPanel);
			}
		});
		return html;
	},

	/**
	 * show a list in the list panel section. It used to be adaptable, but
	 * adaption is no more used. Always the "logbook" type assumed (08.10.2022)
	 */
	showList : function(type, count) {
		// display current club name
		$('#sidebar_clubname').html("<br>" + $_clubname);
		// display current logbook name
		$('#sidebar_logbook').html("<br>Fahrtenbuch " + $_logbookname);
		if (type.localeCompare("logbook") == 0) {
			var header = "<div class='w3-row'><div class='w3-col l2'><h4>";
			header += (($_logbook_allowance_all) ? "Fahrtenbuch " + $_logbookname + " und ggf. weitere eigene Fahrten" : "Eigene Fahrten in diesem Jahr");
			header += "</h4></div><div class='w3-col l2'><p style='text-align:right'>";
			if ($_allowedMenuItems.includes("showList_reservations"))
				header += "<a class='menuitem formbutton' id='do-showList_reservations_25'>Reservierungen</a> ";
			if ($_allowedMenuItems.includes("showList_damages"))
				header += "<a class='menuitem formbutton' id='do-showList_damages_25'>Schadensmeldungen</a> ";
			header += "</p></div></div>";
			$('#bths-listpanel-header').html(header);
			$('#bths-listpanel-list').html(bStatistics.getSessionsHtml(false, count));
		}
		else if (type.localeCompare("reservations") == 0)
			cModal.showHtml(oReservation.getUpcomingReservations(count, "@All"));
		else if (type.localeCompare("damages") == 0)
			cModal.showHtml(oDamage.getOpenDamages(count));
	},

	/**
	 * Update the panel display, e.g. after a trip was entered.
	 */
	update : function() {
		if (!cLists.loadingCompleted())
			return;
		if (cTxQueue.paused > Date.now()) {
			$('#bths-mainpanel-left')
					.html(
							"Wegen Überlastantwort vom efaCloud-Server ist jetzt leider Pause.");
			var waitMins = parseInt((cTxQueue.paused - Date.now()) / 60000);
			$('#bths-mainpanel-right').html(
					"Wir bitten um Geduld für weitere " + waitMins
							+ " Minuten.");
		}
		try {
			var leftPanelContents = bPanel.listAvailableBoats();
			$('#bths-mainpanel-left').html(leftPanelContents);
			var rightPanelContents = bPanel.listNotAvailableBoats();
			$('#bths-mainpanel-right').html(rightPanelContents);
			// show the logbook, the sessions selected are own sessions or 
			// all sessions, according to the allowance.
			bPanel.showList("logbook", 50);
			// The reservation and damage display are also menus, so redo the event binding here.
			bindMenuEvents();
			// if this was an update based on the cached lists, reset the cached lists counter
			// to reset the loadingCompleted logic now for the downloaded lists
			cLists.cacheReloadedListCnt = 0;
		} catch (e) {
			cModal.showException(e);
		}
	}

}