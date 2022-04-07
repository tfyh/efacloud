var bDamage = {
		
	formatDamage : function(damage) {
		// Damage,BoatId,Severity,Description
		var boat = bLists.lists.efaWeb_boats[bLists.indices.efaWeb_boats_guids[damage["BoatId"]]];
		var html = "Nr. " + damage["Damage"] + " für Boot " + boat.Name + ". Schwere " + damage["Severity"] 
				+ ". Beschreibung: " + damage["Description"];
		return html;
	},
	
	getOpenDamages : function(count) {
		// Damage,BoatId,Severity,Description
		var allDamages = bLists.lists.efaWeb_boatdamages;
		var html = "<table><tr><th>Nummer</th><th>Boot</th><th>Schwere</th><th>Beschreibung</th></tr>";
		var c = 0;
		for (damage of allDamages) {
			if ((c < count) && !damage["Fixed"] && (damage["Fixed"].localeCompare("true") != 0) 
					&& (damage["Severity"].trim().localeCompare("FULLYUSEABLE") != 0)) {
				var boat = bLists.lists.efaWeb_boats[bLists.indices.efaWeb_boats_guids[damage["BoatId"]]];
				if (boat)
					html += "<tr><td>" + damage["Damage"] + "</td><td>" + boat.Name + "</td><td>" + damage["Severity"] 
						+ "</td><td>" + damage["Description"] + "</td></tr>\n";
				c++;
			}
		}
		return html + "</table>";
	},

	getOpenDamagesFor : function(boatId) {
		// Damage,BoatId,Severity,Description
		var allDamages = bLists.lists.efaWeb_boatdamages;
		var html = "Offene Bootsschäden:<br>";
		var n = 0;
		for (damage of allDamages) {
			var notFixed = (!damage["Fixed"] && (damage["Fixed"].localeCompare("true") != 0)); 
			var thisBoat = (damage["BoatId"].localeCompare(boatId) == 0);
			var usabilityAffected = (damage["Severity"].trim().localeCompare("FULLYUSEABLE") != 0);
			if (notFixed && thisBoat && usabilityAffected) {
				var boat = bLists.lists.efaWeb_boats[bLists.indices.efaWeb_boats_guids[damage["BoatId"]]];
				if (boat) {
					html += damage["Description"] + ": " + damage["Severity"] + "<br>\n";
					n++;
				}
			}
		}
		if (n == 0)
			return "";
		return html;
	}

}