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

var oReservation = {
	
	// format the reservation as String
	formatReservation : function(reservation) {
		// Reservation,BoatId,DateFrom,DateTo,TimeFrom,TimeTo,Type,ChangeCount,ecrid
		var boat = cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[reservation["BoatId"]]];
		var html = _("051mwt|No. %1 for boot %2 from ...",
				reservation["Reservation"], boat.Name, cToolbox.dateISO2DE(reservation["DateFrom"]),
				reservation["TimeFrom"], cToolbox.dateISO2DE(reservation["DateTo"]), reservation["TimeTo"],
				reservation["Reason"]);
		return html;
	},
	
	// find the first conflicting reservation. Returns this reservation or
	// false, if non conflicts.
	// getConflictingReservation : function(reservationToCompare) obsolete from
	// 2.3.2_03 onwards, server side checks
	
	// get either true or a conflict message
	// noConflicts : function(reservationToCompare) obsolete from 2.3.2_03
	// onwards, server side checks
	
	// return all future reservations
	getUpcomingReservations : function(count, boatId) {
		// Reservation,BoatId,DateFrom,DateTo,TimeFrom,TimeTo,Type,ChangeCount,ecrid
		var allReservations = cLists.lists.efaWeb_boatreservations;
		var html = "<table><tr><th>" + _("kt6n6T|No.") + "</th><th>" + _("rYAeb3|Boat") + "</th><th>" + _("QRmJ0b|from") + "</th><th>" 
				+ _("YcDgVT|Until") + "</th><th>" + _("xnmmBE|Reason") + "</th></tr>";
		var c = 0;
		var now = Date.now();
		var allBoats = (boatId.localeCompare("@All") == 0);
		for (reservation of allReservations) {
			var reservationEnd = Date.parse(reservation["DateTo"]);
			if ((c < count) && (reservationEnd > now) && (allBoats || (reservation["BoatId"].localeCompare(boatId) == 0))) {
				var boat = cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[reservation["BoatId"]]];
				html += "<tr><td>" + reservation["Reservation"] + "</td><td>" + boat.Name + "</td><td>" + cToolbox.dateISO2DE(reservation["DateFrom"]) 
				+ _("MiKGnA| at ") + reservation["TimeFrom"] + "</td><td>" + cToolbox.dateISO2DE(reservation["DateTo"]) + _("p2If4F| at ")
				+ reservation["TimeTo"] + "</td><td>" + reservation["Reason"] + "</td></tr>\n";
				c++;
			}
		}
		return html + "</table>";
	},

	// mark all boats currently booked with the "CurrentlyBooked" = "true"
	markCurrentlyBookedBoats : function() {
		// Reservation,BoatId,DateFrom,DateTo,TimeFrom,TimeTo,Type,ChangeCount,ecrid
		var allReservations = cLists.lists.efaWeb_boatreservations;
		var now = Date.now();
		var reservedBoats = [];
		for (reservation of allReservations) {
			var reservationStart = Date.parse(reservation["DateFrom"]);
			var reservationEnd = Date.parse(reservation["DateTo"]);
			if ((reservationStart <= now) && (reservationEnd >= now)) {
				var boat = cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[reservation["BoatId"]]];
				boat["CurrentlyBooked"] = "true";
				reservedBoats.push(boat);
			}
		}
		return reservedBoats;
	}

}
