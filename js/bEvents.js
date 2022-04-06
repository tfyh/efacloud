/**
 * Collection of all static event bindings. Includes the document ready call
 */

/**
 * Refresh the memory and user interface by reloading all data from the server.
 */
function _refreshEfaWeb() {
	// display current logbook name
	$('#logbookname').html($_logbookname);
	// clear all lists
	bLists.lists = {};
	// no form required, Shortcut to API used. The nop response will trigger all
	// list loading.
	bTxQueue.addNewTxToPending("nop", "any", [ "sleep;0" ], 0, null);
}

/**
 * Display the modal with a statistic, here its distances aggregated with
 * barchart type entry.The function name starts with "_" to indicate that this
 * function must not be called from outside the bEvents script.
 */
function _getStatistics(mode) {
	var html = "<h3>" + bStatistics.names[mode] + "</h3>";
	if (mode == 7)
		bModal.showHtml(html + bDamage.getOpenDamages(100));
	else if (mode == 6)
		bModal.showHtml(html
				+ bReservation.getUpcomingReservations(100, "@All"));
	else if ((mode == 4) || (mode == 5))
		bModal.showHtml(html + bStatistics.getTripsHtml((mode & 1), 50));
	else
		bModal.showHtml(html + bStatistics.getDistancesHtml(mode));
}

/**
 * Display the modal with a statistic.The function name starts with "_" to
 * indicate that this function must not be called from outside the bEvents
 * script.
 */
function _getForm(formName, parameter) {
	// non-numeric parameter is the boat Guid for trip start
	if (parameter && (isNaN(parameter) || (parameter === true)))
		bFormHandler[formName + "_do"]({
			BoatId : parameter,
			Date : bToolbox.dateNow(),
			StartTime : bToolbox.timeNow()
		});
	// numeric parameter is the EntryId of a trip for trip end.
	else if (parameter) {
		var trip = bLists.lists.efaWeb_logbook[bLists.indices.efaWeb_logbook_nids[parameter]];
		bFormHandler[formName + "_do"](bTrip.getFormPreset(trip, true));
	} else
		bFormHandler[formName + "_do"]();
}

/**
 * Display the modal with a datasheet, being eeither a trip, or an open
 * resevation, or just the status. The function name starts with "_" to indicate
 * that this function must not be called from outside the bEvents script.
 */
function _getDatasheet(datasheetType, recordId) {
	if (datasheetType.localeCompare("boat") == 0) {
		var boat = bLists.lists.efaWeb_boats[bLists.indices.efaWeb_boats_guids[recordId]];
		if (boat) {
			bReservation.markCurrentlyBookedBoats();
			if (bPanel.currentBoatTrips[recordId])
				bModal.showHtml(bTrip
						.getDatasheet(bPanel.currentBoatTrips[recordId]));
			else if (boat["statusToUseInPanel"].localeCompare("BOOKED") == 0)
				bModal.showHtml("<h4>Reservierungen für " + boat.Name + "</h4>"
						+ bReservation.getUpcomingReservations(10, boat.Id));
			else
				bModal.showHtml(bBoat.getDatasheet(boat));
		}
	}
}

/**
 * open or close a boat group in the bths main panel. The function name starts
 * with "_" to indicate that this function must not be called from outside the
 * bEvents script.
 * 
 * @param coxNcrewCnt
 *            count of people fitting into the boat
 */
function _openBoatGroup(coxNcrewCnt) {
	var boatGroup_items = document.getElementsByClassName("boatGroup"
			+ coxNcrewCnt);
	for (var i = 0; i < boatGroup_items.length; i++) {
		if (boatGroup_items[i].className.indexOf("w3-show") == -1) {
			boatGroup_items[i].className += " w3-show";
		} else {
			boatGroup_items[i].className = boatGroup_items[i].className
					.replace(" w3-show", "");
		}
	}
}

// show a list on the Panel
function _showList(type, count) {
	try {
		bPanel.showList(type, count);
    } catch (e) {
		showException(e);
	}
}

// check whether the requested action is allowed (without the prefix "do-")
function isAllowedAction(actionId) {
	var action = actionId.split(/\_/g);
	var l0 = action[0];
	var l1 = (action.length > 1) ? l0 + "_" + action[1] : l0;
	var l2 = (action.length > 2) ? l1 + "_" + action[2] : l1;
	return (($.inArray(l0, $_allowedMenuItems) >= 0)
			|| ($.inArray(l1, $_allowedMenuItems) >= 0) || ($.inArray(l2,
			$_allowedMenuItems) >= 0));
}

// branch to the selected action (without the prefix "do-"), if allowed.
function doAction(actionId) {
	var action = actionId.split(/\_/g);
	if (isAllowedAction(actionId)) {
		try {
			window["_" + action[0]](action[1], action[2]);
		} catch (e) {
			showException(e);
		}
		bindMenuEvents();
	}
}

// will bind all menu events by selecting all .menuitems with #do-...
function bindMenuEvents() {
	$menuitems = $('.menuitem'); // for debugging: do not inline statement.
	$menuitems.unbind();
	$menuitems.click(function() {
		var thisElement = $(this); // for debugging: do not inline statement.
		var id = thisElement.attr("id");
		if (!id)
			return;
		if (id.substring(0, 3).localeCompare("do-") != 0)
			return;
		doAction(id.substring(3));
	});
}

// 1 sec screen update period.
var uiRefreshTimer = setInterval(function() {
	try {
		// refresh status display
		$("#queue-state").html(bTxQueue.getStatus());
		if (bTxQueue.paused > Date.now())
			bPanel.update();

		var nowSeconds = parseInt(Date.now() / 1000);

		// if due, check other write accesses
		if (($_last_synch_check + $_synch_check_period) < nowSeconds) {
			var postRequest = new XMLHttpRequest();
			postRequest.timeout = $_apiTimeoutMillis;
			// provide the callbacks
			postRequest.onload = function() {
				if (postRequest.status == 200) {
					var lowa = parseInt(postRequest.response.split(";")[0]) / 1000;
					if (lowa > $_last_synch) {
						bLists.downloadLists("@All", $_last_synch);
					}
				}
			};
			// provide the callback for any error.
			postRequest.onerror = function() { /* do nothing */
			};
			postRequest.ontimeout = function() { /* do nothing */
			};
			// send the post request
			postRequest.open('POST', $_clientInterfaceURI, true);
			postRequest.setRequestHeader('Content-type',
					'application/x-www-form-urlencoded; charset=UTF-8');
			postRequest.send("lowa=" + $_efaCloudUserID);
			$_last_synch_check = nowSeconds;
		}

		// if due, trigger download synchronisation
		if (($_last_synch + $_synch_period) < nowSeconds) {
			bLists.downloadLists("@All", $_last_synch);
			$_last_synch = nowSeconds;
		}
    } catch (e) {
		showException(e);
	}
}, $_uiRefreshMillis);

/**
 * initialization procedures to be performed when document was loaded.
 */
$(document).ready(function() {
	bToolbox.getGetparams();
	bToolbox.i18n_init($_locale);
	if ($_efaCloudUserID < 0)
		// redirect to login for non authorized user.
		window.location.href = "../forms/login.php?goto=../pages/efaWeb.php";
	else {
		_refreshEfaWeb();
		bindMenuEvents();
		bTxQueue.enabled = true;
	}
});
