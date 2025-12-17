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

var oDamage = {
		
	formatDamage : function(damage) {
		// Damage,BoatId,Severity,Description
		var boat = cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[damage["BoatId"]]];
		var html = _("JaxQHa|No. %1 for boot %2. seve...", damage["Damage"],	
				boat.Name, damage["Severity"] , damage["Description"]);
		return html;
	},
	
	getOpenDamages : function(count) {
		// Damage,BoatId,Severity,Description
		var allDamages = cLists.lists.efaWeb_boatdamages;
		var html = "<table><tr><th>" + _("tVC5cs|No.") +"</th><th>" + _("98KJLf|Boat") +"</th><th>"  
			+ _("kfjKRc|Heavy") +"</th><th>" + _("Shxb5M|Description") +"</th></tr>";
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
		var html = _("Ze3H2S|Open boat damage:") + "<br>";
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
