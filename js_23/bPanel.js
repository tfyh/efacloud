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
			if (oBoatstatus.isOnTheWater(boatStatus) && cLists.indices.efaWeb_logbook_nidlists) {
				let sessionsRows = cLists.indices.efaWeb_logbook_nidlists[boatStatus.EntryNo];
				if (sessionsRows) 
					for (let r of sessionsRows) {
						let session = cLists.lists.efaWeb_logbook[r];
						// sessions unknown get a temporary boat status
						bPanel.allBoatsStatus[boatStatusId]["session"] = session;
					}
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
			(($_logbook_allowance_all) ? _("pVuPbJ|Logbook") + " " + $_logbookname : _("VB2mdP|Own rides this year"))
		          + "</h4></div><div class='w3-col l2'><p style='text-align:right'>";
		html += "<a class='menuitem formbutton' id='do-showList_reservations_25'>" + _("QjeBHO|Reservations") + "</a> ";
		html += "<a class='menuitem formbutton' id='do-showList_damages_25'>" + _("UgusMQ|Damage reports") + "</a> ";
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
			// decide whether the boat is "on the water" based on the boat
			// status
			var boatstatus = this.allBoatsStatus[boatId].boatstatus;
			var isOnTheWater = (boatstatus && (boatstatus["CurrentStatus"] != null) && (boatstatus["CurrentStatus"].localeCompare("ONTHEWATER") == 0));
			var isUnknownBoat = false;
			if (boatstatus && (boatstatus["UnknownBoat"] != null) && (boatstatus["UnknownBoat"].localeCompare("true") == 0))
				isUnknownBoat = true;
			// decide whether the boat is "booked" or "invalid" on the boat
			// record.
			var boat = this.allBoatsStatus[boatId].boat;
			var isValid = (isUnknownBoat && isOnTheWater) || (boat && parseInt(boat["InvalidFrom"]) > now);
			var isBooked = !isUnknownBoat && (boat && (boat["CurrentlyBooked"] != null) && (boat["CurrentlyBooked"].localeCompare("true") == 0));
			var thisAvailable = (oBoatstatus.isAvailable(boatstatus) && !isBooked && !isOnTheWater);
			if (isValid && (available == thisAvailable))
				boatsToShow.push(boat);
		}
		return boatsToShow;
	},
	
	/**
	 * The efaWeb-logbook list may contain trips of multiple logbooks, if own
	 * trips are collected. Find the open trip, which should for a specific
	 * entry Id, only be one.
	 */
	getOpenSession(ecrid) {
		let pointer = cLists.indices.all_ecrids[ecrid];
		return cLists.lists[pointer.listname][pointer.row];
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
				let variantNames = oBoat.getNames(boat);
				for (let variantPosition in variantNames) {
					let variantName = variantNames[variantPosition];
					html += actionForAvailable.replace(
							/\[boatName\]/g, variantName).replace(
							/\[boatType\]/g, oBoat.getBoatTypeText(boat, variantPosition)).replace(
							/\[boatId\]/g, variantName).replace(
							/\[cntOrStatus\]/g, seatTypeShort);
				}
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
			
			// Unknown boats have an issue with timely boatstatus closing. THis is a hack to ensure proper display
			if (isOnTheWater && boatstatus.UnknownBoat && boatstatus.NoOpenSession) {
				isOnTheWater = false;
				boatstatus["CurrentStatus"] = "HIDE";
			}

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
				var cntOrStatusText = oBoatstatus.locValue(boat.statusToUseInPanel);
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
			header += (($_logbook_allowance_all) ? _("mk7MTm|Logbook %1 and possibly ...", $_logbookname)
					: _("O1emyI|Own rides this year"));
			header += "</h4></div><div class='w3-col l2'><p style='text-align:right'>";
			if ($_allowedMenuItems.includes("showList_reservations"))
				header += "<a class='menuitem formbutton' id='do-showList_reservations_25'>" + _("dVZFby|Reservations") + "</a> ";
			if ($_allowedMenuItems.includes("showList_damages"))
				header += "<a class='menuitem formbutton' id='do-showList_damages_25'>" + _("HmMA1O|Damage reports") + "</a> ";
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
							_("zjQaio|Due to an overload respo..."));
			var waitMins = parseInt((cTxQueue.paused - Date.now()) / 60000);
			$('#bths-mainpanel-right').html(
					_("Wir bitten um Geduld für weitere %1 Minuten.", waitMins));
		}
		try {
			var leftPanelContents = bPanel.listAvailableBoats();
			$('#bths-mainpanel-left').html(leftPanelContents);
			var rightPanelContents = bPanel.listNotAvailableBoats();
			$('#bths-mainpanel-right').html(rightPanelContents);
			// show the logbook, the sessions selected are own sessions or
			// all sessions, according to the allowance.
			bPanel.showList("logbook", 50);
			_openBoatGroup("ONTHEWATER");
			// The reservation and damage display are also menus, so redo the
			// event binding here.
			bindMenuEvents();
			// if this was an update based on the cached lists, reset the cached
			// lists counter to reset the loadingCompleted logic now for the
			// downloaded lists
			cLists.cacheReloadedListCnt = 0;
		} catch (e) {
			cModal.showException(e);
		}
	},
	
	// reloads the entire page
	reload : function(deferred) {
		if (!deferred)
			deferred = 1000;
		setTimeout(function(){
			location.reload();
		}, deferred);
	}

}
