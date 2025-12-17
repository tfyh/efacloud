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
 * All standard statisitcs are collected here. No configurable statisitcs are
 * provided.
 */

var bStatistics = {

	names : false,
	
	getName : function(mode) {
		if (!bStatistics.names)
			bStatistics.names = [
				_("a7nGz6|Boat kilometres in curre..."),
				_("RzxkTt|Boat kilometres in the c..."),
				_("VF7QDt|Rowing kilometres in the..."),
				_("wK7b6i|Rowing kilometres in cur..."),
				_("xwnvtw|Last 50 trips, by start ..."),
				_("CvP0GD|Last 50 trips, by trip n..."),
				_("rog2FV|Pending reservations"), 
				_("j5yRXe|Open boat damage") 
			]; 
		return bStatistics.names[mode];
	},

	/**
	 * Collect all rowed distances for all trips within the current logbook
	 */
	collectRowedDistance : function() {
		var rowers_distances = {};
		cLists.lists.efaWeb_logbook
				.forEach(function(row) {
					var distanceStr = row["Distance"];
					var distance = 0
					if (distanceStr && distanceStr.indexOf("km") >= 0)
						distance = parseInt(distanceStr.replace("km", "")
								.trim());
					else if (distanceStr && distanceStr.indexOf("m") >= 0)
						distance = parseInt(distanceStr.replace("m", "").trim()) / 1000;
					for (key in row) {
						if ((key.indexOf("Cox") >= 0)
								|| (key.indexOf("Crew") >= 0)) {
							if (cToolbox.isGUID(row[key])) {
								if (rowers_distances[row[key]])
									rowers_distances[row[key]] += distance;
								else
									rowers_distances[row[key]] = distance;
							}
						}
					}
				});
		return rowers_distances;
	},

	/**
	 * Collect all distances boats were driven for all trips within the current
	 * logbook
	 */
	collectDrivenDistance : function() {
		var boats_distances = {};
		if (!cLists.lists || !cLists.lists.efaWeb_logbook
				|| (cLists.lists.efaWeb_logbook.constructor !== Array))
			return boats_distances;
		cLists.lists.efaWeb_logbook
				.forEach(function(row) {
					var distanceStr = row["Distance"];
					var distance = 0
					if (distanceStr && distanceStr.indexOf("km") >= 0)
						distance = parseInt(distanceStr.replace("km", "")
								.trim());
					else if (distanceStr && distanceStr.indexOf("m") >= 0)
						distance = parseInt(distanceStr.replace("m", "").trim()) / 1000;
					if (cToolbox.isGUID(row.BoatId)) {
						if (boats_distances[row.BoatId])
							boats_distances[row.BoatId] += distance;
						else
							boats_distances[row.BoatId] = distance;
					}
				});
		return boats_distances;
	},

	/**
	 * Sort rowers or boats distances and return an html table for display. mode
	 * is: sorting for km/names bit 0, aggregation for boats/persons bit 1, e.g.
	 * mode = 3 => persons rowing distance, sorted by names.
	 */
	getDistancesHtml : function(mode) {

		var forNames = !(mode & 1);
		var forPersons = (mode & 2);
		// replace GUIDs by names
		var distances = (forPersons) ? this.collectRowedDistance() : this
				.collectDrivenDistance();
		var aggregatedDistances = [];
		var maxDistance = 0;
		for (guid in distances) {
			var rowersPerson = cLists.lists.efaWeb_persons[cLists.indices.efaWeb_persons_guids[guid]];
			var boat = cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[guid]];
			var name;
			if (forPersons) {
				if ($_last_name_first)
					fullname = rowersPerson["LastName"] + ", "
							+ rowersPerson["FirstName"];
				else
					fullname = rowersPerson["FirstName"] + " "
							+ rowersPerson["LastName"];
			} else
				name = boat.Name;
			if ((forPersons && rowersPerson) || (!forPersons && boat)) {
				var distance = distances[guid];
				if (distance > 0)
					aggregatedDistances.push({
						name : name,
						distance : distance
					});
				if (distance > maxDistance)
					maxDistance = distance;
			}
		}

		// sort
		if (forNames)
			aggregatedDistances.sort(function(a, b) {
				return (a.name.localeCompare(b.name));
			});
		else
			aggregatedDistances.sort(function(a, b) {
				return (b.distance - a.distance);
			});

		// build table
		var s = "<table id='modal-table'><tbody><tr><th style='width:40%'>"
				+ _("bDOscN|Name") + "</th>" + "<th style='width:40%'>" + _("JbTXKT|Distance")
				+ "</th><th style='width:20%'>&nbsp;&nbsp;" + _("Dh0sXn|km")
				+ "</th></tr>";
		aggregatedDistances
				.forEach(function(aggregatedDistance) {
					var percentage = Math.round(100
							* aggregatedDistance.distance / maxDistance);
					s += "<tr><td>"
							+ aggregatedDistance.name
							+ "</td>"
							+ "<td><div class='bar-container'><div class='bar' style='width:"
							+ percentage + "%'>&nbsp;</td>"
							+ "<td>&nbsp;&nbsp;" + aggregatedDistance.distance
							+ " km</td></tr>";
				});
		return s + "</tbody></table>";
	},

	/**
	 * Return an html formatted logbook
	 */
	getSessionsHtml : function(byEntryId, count) {
		var s = "<table id='modal-table'><tbody><tr><th>" + _("KHQUlC|Trip")
				+ "</th><th>" + _("jcQMrv|Time") + "</th><th>" + _("R9ViN9|Boat") + "</th>"
				+ "<th>" + _("nr240s|Destination") + "</th><th>" + _("xyou4Y|km") + "</th></tr>";
		var n = 0;
		if (cLists.indices.efaWeb_logbook_lastByEntryId
				&& cLists.indices.efaWeb_logbook_lastByStart) {
			for (var i = 0; (i < count)
					&& (i < cLists.indices.efaWeb_logbook_lastByEntryId.length); i++) {
				var rpos = (byEntryId) ? cLists.indices.efaWeb_logbook_lastByEntryId[i]
						: cLists.indices.efaWeb_logbook_lastByStart[i];
				var session = cLists.lists.efaWeb_logbook[rpos];
				var isOwnSession = (($_personId.length > 10)
						&& session.AllCrewIds && (session.AllCrewIds
						.indexOf($_personId) >= 0));
				if ($_logbook_allowance_all || isOwnSession) {
					s += oSession.formatSession(session, false);
					n++;
				}
			}
		}
		if (n == 0)
			return "<h5>" + _("v5F8j0|No trips found in logboo...", $_logbookname) 
				+ "</h5><p>" 
				+ _("Uor5pE|Wrong logbook name? If y...") 
				+ "</p>";
		return s + "</tbody></table>";
	}

}
