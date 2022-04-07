/**
 * All standard statisitcs are collected here. No configurable statisitcs are
 * provided.
 */

var bStatistics = {

	names : [ "Bootskilometer im aktuellen Jahr, sortiert nach Namen",
			"Bootskilometer im aktuellen Jahr, sortiert nach Strecke",
			"Ruderkilometer im aktuellen Jahr, sortiert nach Namen",
			"Ruderkilometer im aktuellen Jahr, sortiert nach Strecke",
			"Letzte 50 Fahrten, nach Fahrtnummer",
			"Letzte 50 Fahrten, nach Startzeit", 
			"Ausstehende Reservierungen",
			"Offene Bootsschäden"],

	/**
	 * Collect all rowed distances for all trips within the current logbook
	 */
	collectRowedDistance : function() {
		var rowers_distances = {};
		bLists.lists.efaWeb_logbook
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
							if (bToolbox.isGUID(row[key])) {
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
		bLists.lists.efaWeb_logbook
				.forEach(function(row) {
					var distanceStr = row["Distance"];
					var distance = 0
					if (distanceStr && distanceStr.indexOf("km") >= 0)
						distance = parseInt(distanceStr.replace("km", "")
								.trim());
					else if (distanceStr && distanceStr.indexOf("m") >= 0)
						distance = parseInt(distanceStr.replace("m", "").trim()) / 1000;
					if (bToolbox.isGUID(row.BoatId)) {
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
			var rowersPerson = bLists.lists.efaWeb_persons[bLists.indices.efaWeb_persons_guids[guid]];
			var boat = bLists.lists.efaWeb_boats[bLists.indices.efaWeb_boats_guids[guid]];
			var name = (forPersons) ? rowersPerson.FirstName + " "
					+ rowersPerson.LastName : boat.Name;
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
		var s = "<table id='modal-table'><tbody><tr><th style='width:40%'>Name</th>"
				+ "<th style='width:40%'>Distanz</th><th style='width:20%'>&nbsp;&nbsp;km</th></tr>";
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
	getTripsHtml : function(byEntryId, count) {
		var s = "<table id='modal-table'><tbody><tr><th>Fahrt</th><th>Zeit</th><th>Boot</th>"
				+ "<th>Ziel</th><th>km</th></tr>";
		var n = 0;
		if (bLists.indices.efaWeb_logbook_lastByEntryId
				&& bLists.indices.efaWeb_logbook_lastByStart) {
			for (var i = 0; (i < count)
					&& (i < bLists.indices.efaWeb_logbook_lastByEntryId.length); i++) {
				var rpos = (byEntryId) ? bLists.indices.efaWeb_logbook_lastByEntryId[i]
						: bLists.indices.efaWeb_logbook_lastByStart[i];
				s += bTrip.formatTrip(bLists.lists.efaWeb_logbook[rpos], false);
				n++;
			}
		}
		if (n == 0)
			return "<h5>Keine Fahrten im Fahrtenbuch " + $_logbookname + " gefunden.</h5><p>Falscher Fahrtenbuchname? "
				+ "Ändern kannst Du es bei entsprechneder Berechtigung im Menü Fahrtenbuch, links</p>";
		return s + "</tbody></table>";
	}

}