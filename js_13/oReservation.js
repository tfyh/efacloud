/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2022 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

var oReservation = {
	
	// format the reservation as String
	formatReservation : function(reservation) {
		// Reservation,BoatId,DateFrom,DateTo,TimeFrom,TimeTo,Type,ChangeCount,ecrid
		var boat = cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[reservation["BoatId"]]];
		var html = "Nr. " + reservation["Reservation"] + " für Boot " + boat.Name + " ab " + cToolbox.dateISO2DE(reservation["DateFrom"]) 
				+ " um " + reservation["TimeFrom"] + " bis " + cToolbox.dateISO2DE(reservation["DateTo"]) + " um " + reservation["TimeTo"] 
				+ ". Grund: " + reservation["Reason"];
		return html;
	},
	
	// find the first conflicting reservation. Returns this reservation or false, if non conflicts.
	// getConflictingReservation : function(reservationToCompare) obsolete from 2.3.2_03 onwards, server side checks
	
	// get either true or a conflict message
	// noConflicts : function(reservationToCompare) obsolete from 2.3.2_03 onwards, server side checks
	
	// return all future reservations
	getUpcomingReservations : function(count, boatId) {
		// Reservation,BoatId,DateFrom,DateTo,TimeFrom,TimeTo,Type,ChangeCount,ecrid
		var allReservations = cLists.lists.efaWeb_boatreservations;
		var html = "<table><tr><th>Nummer</th><th>Boot</th><th>von</th><th>bis</th><th>Grund</th></tr>";
		var c = 0;
		var now = Date.now();
		var allBoats = (boatId.localeCompare("@All") == 0);
		for (reservation of allReservations) {
			var reservationEnd = Date.parse(reservation["DateTo"]);
			if ((c < count) && (reservationEnd > now) && (allBoats || (reservation["BoatId"].localeCompare(boatId) == 0))) {
				var boat = cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[reservation["BoatId"]]];
				html += "<tr><td>" + reservation["Reservation"] + "</td><td>" + boat.Name + "</td><td>" + cToolbox.dateISO2DE(reservation["DateFrom"]) 
				+ " um " + reservation["TimeFrom"] + "</td><td>" + cToolbox.dateISO2DE(reservation["DateTo"]) + " um " + reservation["TimeTo"] 
				+ "</td><td>" + reservation["Reason"] + "</td></tr>\n";
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