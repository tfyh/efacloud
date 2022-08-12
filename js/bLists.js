/**
 * Keep all lists received from efacloudServer up to date and provide their data
 * to the application. Lists will be read from and refreshed from the server and
 * kept in memory only.
 */

var bLists = {

	/**
	 * the following property objects hold a property per bths list. Each
	 * property contains an array. Their values contain, what is explained
	 * within the respective comments.
	 */
	/*
	 * The lists as they were returned from the server, csv-Strings and their
	 * timestamp
	 */
	csvtables : {},
	/*
	 * Per list an array of list rows, each holding a key => value array as list
	 * row.
	 */
	lists : {},
	/*
	 * Set per listname refreshing to true, to know when a refresh has ended.
	 */
	refreshing : {},
	/*
	 * Per list the column headers or keys of the list as array.
	 */
	keys : {},
	/*
	 * per list indices of guids, numericIDs, and names where applicable. Index
	 * names are the list name plus an index suffix, e.g. 'efaWeb_boats_guids'.
	 * The index itself is an object with properties as values (e.g. GUIDs) and
	 * the row position of the respective record in the list being the value.
	 */
	indices : {
		all_ecrids : {}
	},
	/*
	 * Name indices for some of the lists. The name points to a guid rather than
	 * to a list record.
	 */
	names : {},

	/**
	 * Download the named lists' csv server side tables. They may be delta
	 * tables, lastModifiedSeconds is the filter for modifications after this
	 * (seconds after 1.1.70). This will run the full refresh of thoses lists in
	 * memory as well.
	 * 
	 * @param listnames
	 *            comma separated list of thos to be downloaded
	 * @param lastModifiedSeconds
	 *            the lower bound of LastModified timestamps in seconds which
	 *            shall be used to filter the list.
	 */
	downloadLists : function(listnames, lastModifiedSeconds) {
		var toRefresh = (listnames.localeCompare("@All") == 0) ? this._listnames
				: listnames.split(/,/g);

		// invalidate all lists.
		toRefresh.forEach(function(listname) {
			bLists.refreshing[listname] = true;
		});
		// refresh all lists either locally or by download
		toRefresh.forEach(function(listname) {
			bLists._downloadList("efaWeb", listname, lastModifiedSeconds);
		});
	},

	/**
	 * Update the lists in memory based on the server side downloaded csv
	 * tables. This will run the full refresh of thoses lists in memory as well.
	 */
	updateLists : function(listnames) {
		var toRefresh = (listnames.localeCompare("@All") == 0) ? this._listnames
				: listnames.split(/,/g);

		// invalidate all lists.
		toRefresh.forEach(function(listname) {
			bLists.refreshing[listname] = true;
		});
		// refresh all lists either locally or by download
		toRefresh.forEach(function(listname) {
			bLists.updateList(listname);
		});
	},

	/**
	 * Refresh the named lists' references and indices.
	 */
	refreshLists : function(listnames) {
		var toRefresh = (listnames.localeCompare("@All") == 0) ? this._listnames
				: listnames.split(/,/g);

		// invalidate all lists.
		toRefresh.forEach(function(listname) {
			bLists.refreshing[listname] = true;
		});
		// refresh all lists either locally or by download
		toRefresh.forEach(function(listname) {
			bLists._refreshList(listname);
		});
	},

	/**
	 * returns true, when all lists are in a defined state.
	 */
	loadingCompleted : function() {
		var completed = true;
		this._listnames.forEach(function(listname) {
			if (bLists.refreshing[listname])
				completed = false;
		});
		return completed;
	},

	/**
	 * Return a variable used by bForm for building the form.
	 */
	getVar : function(varName) {
		// Return a list of the last trips to choose from, when entering a
		// damage report.
		if (varName.toLowerCase().localeCompare("lastTrips")) {
			var lastTrips = [];
			lastTrips.push("0=bitte auswählen");
			for (var i = 0; i < 50; i++) {
				if (this.indices.efaWeb_logbook_lastByEntryId[i]) {
					var trip = this.lists.efaWeb_logbook[this.indices.efaWeb_logbook_lastByEntryId[i]];
					lastTrips.push(bTrip.shortDescription(trip));
				}
			}
			return lastTrips;
		}
	},

	/**
	 * @return the next free entryId for the logbook. This is no counter. If the
	 *         last EntryId was deleted, it will be reused.
	 */
	nextEntryId : function() {
		var maxEntryId = 0;
		this.lists.efaWeb_logbook.forEach(function(row) {
			if (row.EntryId > maxEntryId)
				maxEntryId = row.EntryId;
		});
		return maxEntryId + 1;
	},

	/**
	 * return a list status String for debugging
	 */
	getStatus : function() {
		var loadedStr = "";
		for (listname in this.csvtables) {
			if (this.csvtables[listname]) {
				var timestamp = new Date();
				timestamp
						.setUTCMilliseconds(this.csvtables[listname].timestamp);
				loadedStr += listname
						+ ": downloaded "
						+ timestamp.toLocaleDateString($_locale,
								$_dateFormatDayShort) + "<br>";
			} else
				loadedStr += listname * ": not loaded.<br>";
		}
		return loadedStr;
	},

	/**
	 * validate a waters list. Returns true, if all waters' names are valid.
	 */
	isValidWatersList : function(watersNameList) {
		var watersNames = watersNameList.split(/;/g);
		var valid = true;
		for (var i = 0; i < watersNames.length; i++) {
			var guid = this.names.efaWeb_waters_names[watersNames[i].trim()];
			valid = valid && guid;
		}
		return valid;
	},

	/**
	 * validate a name for lists: boats, persons, destinations. Returns the
	 * respective last invalidFrom. Returns 0 on all errors.
	 */
	validateName : function(name, listname) {
		if (listname.localeCompare("efaWeb_waters") == 0)
			return (this.isValidWatersList(name)) ? this._neverInvalid : 0;
		var guid = this.names[listname + "_names"][name];
		var valid = (guid);
		if (!valid)
			guid = this.names[listname + "_invNames"][name];
		if (!guid)
			return 0;
		var rowPos = this.indices[listname + "_guids"][guid];
		if (!rowPos && (rowPos != 0))
			return 0;
		var invalidFrom = this.lists[listname][rowPos]["InvalidFrom"];
		if (!invalidFrom)
			return 0;
		return invalidFrom;
	},

	/**
	 * #########################################################################
	 */
	/**
	 * "Private" part. Though technically not private, these functions shall not
	 * be used outside the bLists var.
	 */
	/**
	 * #########################################################################
	 */

	_neverInvalid : 9223372036854775807, // last 3 digits originally 807

	/**
	 * list of efaWeb list names
	 */
	_listnames : [ "efaWeb_boatdamages", "efaWeb_boatreservations", "efaWeb_boats", 
			"efaWeb_boatstatus", "efaWeb_crews", "efaWeb_destinations",
			"efaWeb_fahrtenabzeichen", "efaWeb_groups", 
			"efaWeb_logbook", "efaWeb_opentrips",
			"efaWeb_messages", "efaWeb_persons", "efaWeb_sessiongroups",
			"efaWeb_status", "efaWeb_waters" ],

	/**
	 * list of efaWeb list names
	 */
	_listsForTables : { 
		efa2boatdamages : [ "efaWeb_boatdamages" ],
		efa2boatreservations : [ "efaWeb_boatreservations" ],
		efa2boats : [ "efaWeb_boats" ],
		efa2boatstatus : [ "efaWeb_boatstatus" ],
		efa2crews : [ "efaWeb_crews" ],
		efa2destinations : [ "efaWeb_destinations" ],
		efa2fahrtenabzeichen : [ "efaWeb_fahrtenabzeichen" ],
		efa2groups : [ "efaWeb_groups" ],
		efa2logbook : [ "efaWeb_logbook", "efaWeb_opentrips" ],
		efa2messages : [ "efaWeb_messages" ],
		efa2persons : [ "efaWeb_persons" ],
		efa2sessiongroups : [ "efaWeb_sessiongroups" ],
		efa2status : [ "efaWeb_status" ],
		efa2waters : [ "efaWeb_waters" ],
	},

	/**
	 * list of bths lists which propagate changes to the server side.
	 */
	_serverWriteAllowed : {
		efaWeb_boatdamages : true,
		efaWeb_boatreservations : true,
		efaWeb_boatstatus : true,
		efaWeb_logbook : true,
		efaWeb_messages : true
	},

	/**
	 * list of bths lists with multiple records sharing the same GUID, but
	 * different validity periods.
	 */
	_validityColumns : {
		efaWeb_boats : true,
		efaWeb_destinations : true,
		efaWeb_groups : true,
		efaWeb_persons : true
	},

	/**
	 * list of bths lists for which a "names" index is created.
	 */
	_indexNamesFor : {
		efaWeb_boats : true,
		efaWeb_destinations : true,
		efaWeb_persons : false, // the persons names index is build using full
		// names.
		efaWeb_waters : true,
	},

	/**
	 * list of bths lists for which a "names" index is created.
	 */
	_namesListFor : {
		efaWeb_boats : true,
		efaWeb_destinations : true,
		efaWeb_persons : true,
		efaWeb_waters : true,
	},

	/**
	 * list of bths lists which a numeric ID index is created.
	 */
	_indexNumericIdsFor : {
		efaWeb_boatdamages : "Damage",
		efaWeb_boatreservations : "Reservation",
		efaWeb_logbook : "EntryId",
		efaWeb_messages : "MessageId",
	},

	/**
	 * list of bths lists which a UUID index is created.
	 */
	_indexUUIDsFor : {
		efaWeb_boats : "Id",
		efaWeb_boatstatus : "BoatId",
		efaWeb_destinations : "Id",
		efaWeb_fahrtenabzeichen : "PersonId",
		efaWeb_persons : "Id",
		efaWeb_waters : "Id"
	},

	/**
	 * Sort a list of items with validity marks by Id and filter on the last
	 * valid row per item.
	 */
	_extractLastValidity : function(list) {
		// copy rows to prepare sorting
		var list_sorted = [];
		list.forEach(function(row) {
			list_sorted.push(row);
		});
		// Sort for Id and then sort descending for InvalidFrom
		// This will group the rows per Id with the most recent
		// validity first.
		list_sorted.sort(function(a, b) {
			var guidCompare = a["Id"].localeCompare(b["Id"]);
			if (guidCompare != 0)
				return guidCompare * 10;
			var aInvF = parseInt(a["InvalidFrom"]);
			var bInvF = parseInt(b["InvalidFrom"]);
			if (aInvF < bInvF)
				return 1;
			if (aInvF > bInvF)
				return -1;
			return 0;
		});
		// filter. Only the top row of a GUID group is kept.
		var list_filtered = [];
		var lastRow = {
			Id : "none"
		}
		list_sorted.forEach(function(row) {
			if (row["Id"].localeCompare(lastRow["Id"]) != 0)
				list_filtered.push(row);
			lastRow = row;
		});
		// done.
		return list_filtered;
	},

	/**
	 * Sort an index alphabetically or, if all keys are numeric, numerically
	 */
	_sortIndex : function(index) {
		// copy all keys into a normal (non associative) array.
		var keys = [];
		var sortNumeric = true;
		for ( var key in index) {
			keys.push(key);
			if (isNaN(key))
				sortNumeric = false;
		}
		// sort the array of keys
		keys.sort(function(a, b) {
			if (sortNumeric)
				a - b;
			else
				return a.localeCompare(b);
		});
		// create an new index object with sorted keys.
		var index_sorted = {};
		keys.forEach(function(key) {
			index_sorted[key] = index[key];
		});
		return index_sorted;
	},

	/**
	 * Build indices for the logbook display: last trips by either the EntryId
	 * or the trips start time..
	 */
	_buildLastTripsIndices : function() {
		// standard index for entry IDs is built before this.
		// index for last trips. Prepare.
		var lastTrips = [];
		var r = 0;
		this.lists.efaWeb_logbook.forEach(function(row) {
			lastTrips.push({
				EntryId : row["EntryId"],
				startAt : bToolbox.parseEfaDate(row["Date"], row["StartTime"])
						.valueOf(),
				rpos : r
			});
			r++;
		});
		// build "efaWeb_logbook_lastByEntryId"
		lastTrips.sort(function(a, b) {
			return b.EntryId - a.EntryId;
		});
		var lastByEntryId = [];
		lastTrips.forEach(function(trip) {
			lastByEntryId.push(trip.rpos);
		})
		this.indices["efaWeb_logbook_lastByEntryId"] = lastByEntryId;
		// build "efaWeb_logbook_lastByStart"
		lastTrips.sort(function(a, b) {
			return b.startAt - a.startAt;
		});
		var lastByStart = [];
		lastTrips.forEach(function(trip) {
			lastByStart.push(trip.rpos);
		})
		this.indices["efaWeb_logbook_lastByStart"] = lastByStart;
	},

	/**
	 * Build all neded specific indices
	 */
	_buildIndicesAndFilter : function(listname) {

		// Filter only relevant records for lists with validity period
		if (this._validityColumns[listname])
			this.lists[listname] = this
					._extractLastValidity(this.lists[listname]);

		// Build generic index for ecrid.
		var ecrids = {};
		if (!this.indices["all_ecrids"]) this.indices["all_ecrids"] = {};
		var r = 0;
		for (row of this.lists[listname]) {
			ecrids[row["ecrid"]] = r;
			bLists.indices.all_ecrids[row["ecrid"]] = { listname : listname, row : r };
			r++;
		}

		// Build generic indices for GUIDs, names and numeric IDs.
		if (this._indexUUIDsFor[listname]) {
			var guids = {};
			var r = 0;
			this.lists[listname].forEach(function(row) {
				guids[row[bLists._indexUUIDsFor[listname]]] = r;
				r++;
			});
			this.indices[listname + "_guids"] = guids;
		}
		if (this._indexNamesFor[listname]) {
			var names = {};
			var r = 0;
			this.lists[listname].forEach(function(row) {
				names[row["Name"]] = r;
				r++;
			});
			this.indices[listname + "_names"] = this._sortIndex(names);
		}
		if (this._indexNumericIdsFor[listname]) {
			var nIds = {};
			var r = 0;
			this.lists[listname].forEach(function(row) {
				nIds[row[bLists._indexNumericIdsFor[listname]]] = r;
				r++;
			});
			this.indices[listname + "_nids"] = this._sortIndex(nIds);
		}

		// Build specific name index for persons based non full name
		if (listname.localeCompare("efaWeb_persons") == 0) {
			var names = {};
			var r = 0;
			// pivoting
			this.lists.efaWeb_persons.forEach(function(row) {
				names[row["FirstName"] + " " + row["LastName"]] = r;
				r++;
			});
			this.indices[listname + "_names"] = this._sortIndex(names);
		}

		// Logbook indices are rebuild with every trip entry.
		if (listname.localeCompare("efaWeb_logbook") == 0)
			this._buildLastTripsIndices();
	},

	/**
	 * Build two lists of names for persons, boats, destinations, waters asf. as
	 * associative array with the key as name and value as GUID. One list is for
	 * those which are still valid, one for those which are by now invalid.
	 */
	_buildNames : function(listname) {
		var names = {};
		var invalidNames = {};
		var listnameLC = listname.toLowerCase();
		if (!this._namesListFor[listname])
			return;
		var checkValidity = this._validityColumns[listname];
		var now = Math.floor(Date.now() / 1000);
		this.lists[listname]
				.forEach(function(row) {
					var guid = row["Id"];
					// boat have variants, each with a separate name.
					if (listnameLC.localeCompare("efaweb_persons") == 0)
						name = row["FirstName"] + " " + row["LastName"];
					else
						name = row["Name"];
					var nameList = (listnameLC.localeCompare("efaweb_boats") == 0) ? bBoat
							.getNames(row)
							: [ name ];
					var invalidFrom = parseInt(row["InvalidFrom"]);
					nameList.forEach(function(name) {
						// add either to valid, or to invalid bnames, depending
						// on the invalidFrom timestamp.
						if (!checkValidity || (invalidFrom > now))
							names[name] = guid;
						else
							invalidNames[name] = guid;
					});
				});
		this.names[listname + '_names'] = names;
		this.names[listname + '_invNames'] = invalidNames;
	},

	/**
	 * download a single list. lastModifiedSeconds is the filter for
	 * modifications after this (seconds after 1.1.70).
	 */
	_downloadList : function(setname, listname, lastModifiedSeconds) {
		var record = [ "LastModified;" + lastModifiedSeconds ];
		record.push("logbookname;" + $_logbookname);
		record.push("setname;" + setname);
		bTxQueue.addNewTxToPending("list", listname, record, 0, null);
	},

	/**
	 * Merge a recieved list. Add or update alle list rows as they are within
	 * the csv-tables in memory and delete the csv-table. The target list may be
	 * different. Find it by the all_ecrid index
	 * 
	 */
	updateList : function(listname) {
		var listsUpdated = {};
		listsUpdated[listname] = 0; // this ensures listname is refreshed, if
									// empty
		if (!bLists.lists[listname])
			bLists.lists[listname] = [];
		// parse download result into associative array
		var listRows = bToolbox.readCsvList(this.csvtables[listname].data);
		listRows.forEach(function(row) {
			var listNrow = bLists.indices.all_ecrids[row.ecrid];
			// record is already existing: overwrite it.
			if (listNrow) {
				bLists.lists[listNrow.listname][listNrow.row] = row;
				if (!listsUpdated[listNrow.listname]) listsUpdated[listNrow.listname] = 0;
				listsUpdated[listNrow.listname] ++;
			}
			else {
				// record is not yet existing. Push it to the list with listname
				bLists.lists[listname].push(row);
				if (!listsUpdated[listname]) listsUpdated[listname] = 0;
				listsUpdated[listname] ++;
			}
		});
		for (listUpdated of Object.keys(listsUpdated))
			this._refreshList(listUpdated);
	},

	/**
	 * refresh all secondary indices asf. for a single list.
	 */
	_refreshList : function(listname) {

		// invalidate the list.
		this.refreshing[listname] = true;

		// collect the keys for the list.
		bLists.keys[listname] = [];
		if (this.lists[listname] && this.lists[listname][0])
			for (key in this.lists[listname][0]) {
				bLists.keys[listname].push(key);
			}

		// Provide an extra column for persons for convenience
		if (listname.localeCompare("efaWeb_persons") == 0)
			for (person of this.lists[listname]) {
				person["FullName"] = person["FirstName"] + " "
						+ person["LastName"];
			}

		else if (listname.localeCompare("efaWeb_boats") == 0)
			for (boat of this.lists[listname]) {
				if (!boat["TypeSeats"])
					boat["TypeSeats"] = "1";
				var seats = boat["TypeSeats"].split(/;/g);
					var seatsCnt = parseInt(seats[0].replace(/\D/g, ''));
				if (isNaN(seatsCnt))
					seatsCnt = 0;
				var coxing = boat["TypeCoxing"].split(/;/g);
				// it is assumed that all variants take the same count
				// of people aboard.
				boat["crewNcoxCnt"] = (coxing[0].localeCompare("COXED") == 0) ? seatsCnt + 1
						: seatsCnt;
			}

		else if (listname.localeCompare("efaWeb_logbook") == 0)
			for (trip of this.lists[listname]) {
				if (!trip["Date"])
					trip["Date"] = "01.01.1970";
				if (!trip["StartTime"])
					trip["StartTime"] = "08:00:00";
				if (trip.EntryId)
					trip.EntryId = parseInt(trip.EntryId);
			}

		this._buildNames(listname);
		this._buildIndicesAndFilter(listname);

		// re-validate the list
		this.refreshing[listname] = false;
		console.log("refreshed " + listname);

		if (this.loadingCompleted())
			bPanel.update();
	},

}
