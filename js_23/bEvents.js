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

/**
 * Collection of all static event bindings. Includes the document ready call
 */

/**
 * Refresh the memory and user interface by reloading all data from the server.
 */
function _refreshEfaWeb() {
	// clear all lists
	cLists.clear();
	bPanel.update();
	// no form required, Shortcut to API used. The nop response will trigger all
	// list loading.
	cTxQueue.addNewTxToPending("nop", "any", [ "sleep;0" ], 0, null, null);
}

/**
 * Display the modal with a statistic, here its distances aggregated with
 * barchart type entry.The function name starts with "_" to indicate that this
 * function must not be called from outside the bEvents script.
 */
function _getStatistics(mode) {
	var html = "<h3>" + bStatistics.getName(mode) + "</h3>";
	if (mode == 7)
		cModal.showHtml(html + oDamage.getOpenDamages(100));
	else if (mode == 6)
		cModal.showHtml(html
				+ oReservation.getUpcomingReservations(100, "@All"));
	else if ((mode == 4) || (mode == 5))
		cModal.showHtml(html + bStatistics.getSessionsHtml((mode & 1), 50));
	else
		cModal.showHtml(html + bStatistics.getDistancesHtml(mode));
}

/**
 * Display the modal with a statistic. The function name starts with "_" to
 * indicate that this function must not be called from outside the bEvents
 * script.
 */
function _getForm(formName, parameter) {
	// ecrid parameter is the ecrid of a trip for trip end
	if (parameter && cToolbox.isEcrid(parameter)) {
		var session = bPanel.getOpenSession(parameter);
		bFormHandler[formName + "_do"](oSession.getFormPreset(session, true));
	// non-numeric parameter is the boat name for trip start
	} else if (parameter && (isNaN(parameter) || (parameter === true)))
		bFormHandler[formName + "_do"]({
			BoatId : parameter,
			Date : cToolbox.dateNow(),
			StartTime : cToolbox.timeNow()
		});
	// without parameter reflects a call to any other form.
	else
		bFormHandler[formName + "_do"]();
}

/**
 * Display the modal with a simple help text.
 */
function _getHelpText(helpTextName) {
	cModal.showHtml($_efaWeb_helptexts[helpTextName]);
}

/**
 * Display the modal with a datasheet, being eeither a trip, or an open
 * resevation, or just the status. The function name starts with "_" to indicate
 * that this function must not be called from outside the bEvents script.
 */
function _getDatasheet(datasheetType, recordId) {
	if (datasheetType.localeCompare("boat") == 0) {
		if (bPanel.allBoatsStatus[recordId].session) {
			var updated_session = cLists.lists.efaWeb_logbook[cLists.indices.all_ecrids[bPanel.allBoatsStatus[recordId].session["ecrid"]].row];
			if ($_allowedMenuItems.includes("getForm_endSession"))
				cModal.showHtml(oSession.getDatasheet(updated_session, true));
			else if ($_allowedMenuItems.includes("getForm_showSession"))
				cModal.showHtml(oSession.getDatasheet(updated_session, false));
			else
				cModal.showHtml(_("cuE4RE|Sorry, you are not autho..."));
		} else {
			var boat = cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[recordId]];
			if (boat) {
				if (boat["statusToUseInPanel"].localeCompare("BOOKED") == 0)
					cModal
							.showHtml("<h4>"
									+ _("3fUKdZ|Reservations for")
									+ " "
									+ boat.Name
									+ "</h4>"
									+ oReservation.getUpcomingReservations(10,
											boat.Id));
				else
					cModal.showHtml(oBoat.getDatasheet(boat));
			} else
				cModal.showHtml("<h4>" + _("jWjTrj|Unknown boat") + "</h4>");
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
			boatGroup_items[i].className = boatGroup_items[i].className
					.replace(" w3-hide", "");
			boatGroup_items[i].className += " w3-show";
		} else {
			boatGroup_items[i].className = boatGroup_items[i].className
					.replace(" w3-show", "");
			boatGroup_items[i].className += " w3-hide";
		}
	}
}

// show a list on the Panel
function _showList(type, count) {
	try {
		bPanel.showList(type, count);
	} catch (e) {
		cModal.showException(e);
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
			cModal.showException(e);
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
var uiRefreshTimer = setInterval(
		function() {
			try {
				// refresh status display
				$("#queue-state").html(cTxQueue.getStatus());
				if (cTxQueue.paused > Date.now())
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
								cLists.downloadLists("@All", $_last_synch);
							}
						}
					};
					// provide the callback for any error.
					postRequest.onerror = function() { /* do nothing */
					};
					postRequest.ontimeout = function() { /* do nothing */
					};
					// send the post request
					postRequest.open('POST', $_apiPostURI, true);
					postRequest.setRequestHeader('Content-type',
							'application/x-www-form-urlencoded; charset=UTF-8');
					postRequest.send("lowa=" + $_apiUserID);
					$_last_synch_check = nowSeconds;
				}

				// if due, trigger download synchronisation
				if (($_last_synch + $_synch_period) < nowSeconds) {
					cLists.downloadLists("@All", $_last_synch);
					$_last_synch = nowSeconds;
				}
			} catch (e) {
				cModal.showException(e);
			}
		}, $_uiRefreshMillis);

/**
 * initialization procedures to be performed when document was loaded.
 */
$(document).ready(function() {
	cToolbox.getGetparams();
	if ($_apiUserID < 0)
		// redirect to login for non authorized user.
		window.location.href = "../forms/login.php?goto=../pages/efaWeb.php";
	else {
		i18n.init();
		cLists.setDefinitions(efaListDefs);
		_refreshEfaWeb();
		bindMenuEvents();
		cTxQueue.enabled = true;
	}
});
