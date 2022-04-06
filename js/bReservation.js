var bReservation = {
	
	// format the reservation as String
	formatReservation : function(reservation) {
		// Reservation,BoatId,DateFrom,DateTo,TimeFrom,TimeTo,Type,ChangeCount,ecrid
		var boat = bLists.lists.efaWeb_boats[bLists.indices.efaWeb_boats_guids[reservation["BoatId"]]];
		var html = "Nr. " + reservation["Reservation"] + " für Boot " + boat.Name + " ab " + reservation["DateFrom"] 
				+ " um " + reservation["TimeFrom"] + " bis " + reservation["DateTo"] + " um " + reservation["TimeTo"] 
				+ ". Grund: " + reservation["Reason"];
		return html;
	},
	
	// find the first conflicting reservation. Returns this reservation or false, if non conflicts.
	getConflictingReservation : function(reservationToCompare) {
		var startToCompare = Date.parse(reservationToCompare["DateFrom"] + " " + reservationToCompare["TimeFrom"]);
		var endToCompare = Date.parse(reservationToCompare["DateTo"] + " " + reservationToCompare["TimeTo"]);
		var boatIdToCompare = (bToolbox.isGUID(reservationToCompare.BoatId)) ? reservationToCompare.BoatId :
			bLists.names.efaWeb_boats_names[reservationToCompare.BoatId];
		for (reservation of bLists.lists.efaWeb_boatreservations) {
			if (reservation.BoatId && reservation.BoatId.localeCompare(boatIdToCompare) == 0) {
				var start = Date.parse(reservation["DateFrom"] + " " + reservation["TimeFrom"]);
				var end = Date.parse(reservation["DateTo"] + " " + reservation["TimeTo"]);
				var conflictingStart = (startToCompare > start) && (startToCompare < end);
				var conflictingEnd = (endToCompare > start) && (endToCompare < end);
				var greaterPeriod = (startToCompare < start) && (endToCompare > end);
				var isWeekly = false;
				if (isWeekly || greaterPeriod || conflictingEnd || conflictingStart)
					return reservation;
			}
		}
		return false;
	},
	
	// get either true or a conflict message
	noConflicts : function(reservationToCompare) {
		var conflictingReservation = this.getConflictingReservation(reservationToCompare);
		if (conflictingReservation === false)
			return true;
		else
			return "Konflikt mit: " + this.formatReservation(conflictingReservation) +". Bitte ändere die Zeiten oder das Boot.";
	},
	
	// return all future reservations
	getUpcomingReservations : function(count, boatId) {
		// Reservation,BoatId,DateFrom,DateTo,TimeFrom,TimeTo,Type,ChangeCount,ecrid
		var allReservations = bLists.lists.efaWeb_boatreservations;
		var html = "<table><tr><th>Nummer</th><th>Boot</th><th>von</th><th>bis</th><th>Grund</th></tr>";
		var c = 0;
		var now = Date.now();
		var allBoats = (boatId.localeCompare("@All") == 0);
		for (reservation of allReservations) {
			var reservationEnd = Date.parse(reservation["DateTo"]);
			if ((c < count) && (reservationEnd > now) && (allBoats || (reservation["BoatId"].localeCompare(boatId) == 0))) {
				var boat = bLists.lists.efaWeb_boats[bLists.indices.efaWeb_boats_guids[reservation["BoatId"]]];
				html += "<tr><td>" + reservation["Reservation"] + "</td><td>" + boat.Name + "</td><td>" + reservation["DateFrom"] 
				+ " um " + reservation["TimeFrom"] + "</td><td>" + reservation["DateTo"] + " um " + reservation["TimeTo"] 
				+ "</td><td>" + reservation["Reason"] + "</td></tr>\n";
				c++;
			}
		}
		return html + "</table>";
	},

	// mark all boats currently booked with the "statusToUseInPanel" = "BOOKED"
	markCurrentlyBookedBoats : function() {
		// Reservation,BoatId,DateFrom,DateTo,TimeFrom,TimeTo,Type,ChangeCount,ecrid
		var allReservations = bLists.lists.efaWeb_boatreservations;
		var now = Date.now();
		var reservedBoats = [];
		for (reservation of allReservations) {
			var reservationStart = Date.parse(reservation["DateFrom"]);
			var reservationEnd = Date.parse(reservation["DateTo"]);
			if ((reservationStart <= now) && (reservationEnd >= now)) {
				var boat = bLists.lists.efaWeb_boats[bLists.indices.efaWeb_boats_guids[reservation["BoatId"]]];
				boat["statusToUseInPanel"] = "BOOKED";
				reservedBoats.push(boat);
			}
		}
		return reservedBoats;
	}

}