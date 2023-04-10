/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2022 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

var oDamage = {
		
	formatDamage : function(damage) {
		// Damage,BoatId,Severity,Description
		var boat = cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[damage["BoatId"]]];
		var html = "Nr. " + damage["Damage"] + " für Boot " + boat.Name + ". Schwere " + damage["Severity"] 
				+ ". Beschreibung: " + damage["Description"];
		return html;
	},
	
	getOpenDamages : function(count) {
		// Damage,BoatId,Severity,Description
		var allDamages = cLists.lists.efaWeb_boatdamages;
		var html = "<table><tr><th>Nummer</th><th>Boot</th><th>Schwere</th><th>Beschreibung</th></tr>";
		var c = 0;
		if (allDamages) {
			for (damage of allDamages) {
				if ((c < count) && !damage["Fixed"] && (damage["Fixed"].localeCompare("true") != 0) 
						&& (damage["Severity"].trim().localeCompare("FULLYUSEABLE") != 0)) {
					var boat = cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[damage["BoatId"]]];
					if (boat)
						html += "<tr><td>" + damage["Damage"] + "</td><td>" + boat.Name + "</td><td>" + damage["Severity"] 
							+ "</td><td>" + damage["Description"] + "</td></tr>\n";
					c++;
				}
			}
		}
		return html + "</table>";
	},

	getOpenDamagesFor : function(boatId) {
		// Damage,BoatId,Severity,Description
		var allDamages = cLists.lists.efaWeb_boatdamages;
		var html = "Offene Bootsschäden:<br>";
		var n = 0;
		for (damage of allDamages) {
			var notFixed = (!damage["Fixed"] && (damage["Fixed"].localeCompare("true") != 0)); 
			var thisBoat = (damage["BoatId"].localeCompare(boatId) == 0);
			var usabilityAffected = (damage["Severity"].trim().localeCompare("FULLYUSEABLE") != 0);
			if (notFixed && thisBoat && usabilityAffected) {
				var boat = cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[damage["BoatId"]]];
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