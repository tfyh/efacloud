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
 * Keep all lists received from efacloudServer up to date and provide their data
 * to the application. Lists will be read from and refreshed from the server and
 * kept in memory only.
 */

var cLists = {

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
	
	/*
	 * The definitions will steer the list load and index build.
	 */
	defs : null,
	
	/*
	 * in order to prevent from panel update during cache reload, use this flag.
	 */
	cacheReloadBusy : false,
	cacheReloadedListCnt : 0,
	
	/**
	 * set the definitions. This will be done once upon startup.
	 */
	setDefinitions : function(listDefinitions) {
		this.defs = listDefinitions;
	},

	/**
	 * Clear the entire memory, used for logbook refresh
	 */
	clear : function() {
		this.csvtables = {};
		this.lists = {};
		this.refreshing = {};
		this.keys = {};
		this.indices = {
			all_ecrids : {}
		},
		this.names = {};
	},

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
		var toRefresh = (listnames.localeCompare("@All") == 0) ? this.defs.listnames
				: listnames.split(/,/g);
		
		
		// read caches first
		this.cacheReloadBusy = true;
		this.cacheReloadedListCnt = 0;
		const now = Date.now();
		for (listindex in toRefresh) {
			let listname = toRefresh[listindex];
			let isLogbook = (listname.localeCompare("efaWeb_logbook") == 0);
			// the logbook must always be reloaded, it may have changed.
			let downloaded = !isLogbook && window.localStorage.getItem(listname + "_downloaded");
			if (downloaded && ((now - downloaded) < (30 * 24 * 3600 * 1000))) {
				if (!this.csvtables[listname]) 
					this.csvtables[listname] = {};
				this.csvtables[listname]["data"] = window.localStorage.getItem(listname + "_data");
				this.csvtables[listname]["updated"] = 0;
				this.updateList(listname);
				this.cacheReloadedListCnt ++;
			} 
		}
		this.cacheReloadBusy = false;
		bPanel.update();
		
		// invalidate all lists.
		toRefresh.forEach(function(listname) {
			cLists.refreshing[listname] = true;
		});
		// refresh all lists either locally or by download
		toRefresh.forEach(function(listname) {
			cLists._downloadList(cLists.defs.setname, listname, lastModifiedSeconds);
		});
	},

	/**
	 * Update the lists in memory based on the server side downloaded csv
	 * tables. This will run the full refresh of thoses lists in memory as well.
	 */
	updateLists : function(listnames) {
		var toRefresh = (listnames.localeCompare("@All") == 0) ? this.defs.listnames
				: listnames.split(/,/g);

		// invalidate all lists.
		toRefresh.forEach(function(listname) {
			cLists.refreshing[listname] = true;
		});
		// refresh all lists either locally or by download
		toRefresh.forEach(function(listname) {
			cLists.updateList(listname);
		});
	},

	/**
	 * Refresh the named lists' references and indices.
	 */
	refreshLists : function(listnames) {
		var toRefresh = (listnames.localeCompare("@All") == 0) ? this.defs.listnames
				: listnames.split(/,/g);

		// invalidate all lists.
		toRefresh.forEach(function(listname) {
			cLists.refreshing[listname] = true;
		});
		// refresh all lists either locally or by download
		toRefresh.forEach(function(listname) {
			cLists._refreshList(listname);
		});
	},

	/**
	 * returns true, when all lists are in a defined state.
	 */
	loadingCompleted : function() {
		if (cLists.cacheReloadBusy)
			return false;
		if (this.cacheReloadedListCnt >= 10)
			return true;
		var completed = true;
		this.defs.listnames.forEach(function(listname) {
			if (cLists.refreshing[listname])
				completed = false;
		});
		return completed;
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
						+ ": " + _("SXiDCM|downloaded") + " "
						+ timestamp.toLocaleDateString($_locale,
								$_dateFormatDayShort) + "<br>";
			} else
				loadedStr += listname * ": " + _("aSbA0d|not loaded.") + "<br>";
		}
		return loadedStr;
	},

	/**
	 * validate a names list. Returns true, if all waters' names are valid.
	 */
	invalidFromForNameList : function(namesList, namesReference) {
		var names = namesList.split(/;/g);
		var invalidFromNameMin = this.defs.neverInvalid;
		for (var i = 0; i < names.length; i++) {
			var invalidFromName = this.invalidFromForNames(names[i].trim(), namesReference);
			invalidFromNameMin = (invalidFromName < invalidFromNameMin) ? invalidFromName : invalidFromNameMin; 
		}
		return invalidFromNameMin;
	},

	/**
	 * get the invalidFrom time for a name or a ;-list of names. Returns 0 on
	 * all errors.
	 */
	invalidFromForNames : function(name, listname) {
		// for waters in destinations and sessions and persons in groups
		if ((name.indexOf(";") >= 0) && cLists.defs.usedForIdLists.includes(listname)) 
			return this.invalidFromForNameList(name, listname);
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
			return this.defs.neverInvalid; // not versionized table
		return invalidFrom;
	},
	
	// merge record data, usually selectively retreived, into the record of the
	// respective list.
	// The record must contain an ecrid field to be identified and there must be
	// a record existing with this ecrid.
	mergeRecord : function(record) {
		var index = cLists.indices.all_ecrids[record.ecrid];
		if (!index)
			return;
		for (field in record) {
			cLists.lists[index.listname][index.row][field] = record[field];
		}
	},
	
	// merge record data, usually selectively retreived, into the record of the
	// respective list.
	// The record must contain an ecrid field to be identified and there must be
	// a record existing with this ecrid.
	addRecord : function(listname, record) {
		cLists.lists[listname].push(record);
		cLists._buildIndicesAndFilter(listname);
	},
	
	/**
	 * Merge a recieved list. Add or update alle list rows as they are within
	 * the csv-tables in memory and delete the csv-table. The target list may be
	 * different. Find it by the all_ecrid index
	 * 
	 */
	updateList : function(listname) {
		var listsUpdated = {};
		// this ensures listname is refreshed, if empty
		listsUpdated[listname] = 0; 
		if (!cLists.lists[listname])
			cLists.lists[listname] = [];
		// the own sessions and open trips shall not go to a separate list,
		// but be added to the logbook
		var listToAddTo = (cLists.defs.listsToDivert[listname]) ? cLists.defs.listsToDivert[listname] : listname;
		// parse download result into associative array
		var listRows = cToolbox.readCsvList(this.csvtables[listname].data);
		listRows.forEach(function(row) {
			var listNrow = cLists.indices.all_ecrids[row.ecrid];
			// record is already existing: overwrite it.
			if (listNrow) {
				var existingRow = cLists.lists[listNrow.listname][listNrow.row];
				cLists.lists[listNrow.listname][listNrow.row] = { ...existingRow, ...row };
				if (!listsUpdated[listNrow.listname]) listsUpdated[listNrow.listname] = 0;
				listsUpdated[listNrow.listname] ++;
			}
			else if (cLists.lists[listToAddTo]) {
				// record is not yet existing. Push it to the list with listname
				cLists.lists[listToAddTo].push(row);
				if (!listsUpdated[listToAddTo]) listsUpdated[listToAddTo] = 0;
				listsUpdated[listToAddTo] ++;
			}
		});
		for (listUpdated of Object.keys(listsUpdated))
			this._refreshList(listUpdated);
	},

	/**
	 * load a csv list from the server, as is provided in data edit forms.
	 */
	readCsv : function(tablename, csv) {
		let listname = tablename;
		cLists.csvtables[listname] = {};
		cLists.csvtables[listname]["downloaded"] = Date.now();
		cLists.csvtables[listname]["updated"] = 0;
		cLists.csvtables[listname]["data"] = csv;
		cLists.updateList(listname);
	},

	/**
	 * #########################################################################
	 */
	/**
	 * "Private" part. Though technically not private, these functions shall not
	 * be used outside the cLists var.
	 */
	/**
	 * #########################################################################
	 */

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
	 * Build all neded specific indices
	 */
	_buildIndicesAndFilter : function(listname) {

		// Filter only relevant records for lists with validity period
		if (this.defs.validityColumns[listname])
			this.lists[listname] = this
					._extractLastValidity(this.lists[listname]);

		// Build generic index for ecrid.
		var ecrids = {};
		if (!this.indices["all_ecrids"]) this.indices["all_ecrids"] = {};
		var r = 0;
		if (!cLists.defs.noEcridLists.includes(listname)) {
			for (row of this.lists[listname]) {
				ecrids[row["ecrid"]] = r;
				if (!cLists.indices.all_ecrids[row["ecrid"]]) 
					cLists.indices.all_ecrids[row["ecrid"]] = { listname : listname, row : r };
				r++;
			}
		}

		// Build generic indices for GUIDs, names and numeric IDs.
		if (this.defs.indexUUIDsFor[listname]) {
			var guids = {};
			var r = 0;
			this.lists[listname].forEach(function(row) {
				guids[row[cLists.defs.indexUUIDsFor[listname]]] = r;
				r++;
			});
			this.indices[listname + "_guids"] = guids;
		}
		if (this.defs.indexNamesFor[listname]) {
			var names = {};
			var r = 0;
			this.lists[listname].forEach(function(row) {
				names[row["Name"]] = r;
				r++;
			});
			this.indices[listname + "_names"] = this._sortIndex(names);
		}
		if (this.defs.indexNumericIdsFor[listname]) {
			var nIds = {};
			var r = 0;
			this.lists[listname].forEach(function(row) {
				nIds[row[cLists.defs.indexNumericIdsFor[listname]]] = r;
				r++;
			});
			this.indices[listname + "_nids"] = this._sortIndex(nIds);
		}
		if (this.defs.indexNumericIdListsFor[listname]) {
			var nIdlists = {};
			var r = 0;
			this.lists[listname].forEach(function(row) {
				if (!nIdlists[row[cLists.defs.indexNumericIdListsFor[listname]]])
					nIdlists[row[cLists.defs.indexNumericIdListsFor[listname]]] = [];
						
				nIdlists[row[cLists.defs.indexNumericIdsFor[listname]]].push(r);
				r++;
			});
			this.indices[listname + "_nidlists"] = this._sortIndex(nIdlists);
		}

		// Logbook indices are rebuild with every session entry and with
		// loading of the own sessions, because they can contain sessions of
		// other logbooks.
		if (cLists.defs.usedForSpecialIndices.includes(listname))
			cLists.defs.buildSpecialIndices(listname);
	},

	/**
	 * Build two lists of names for persons, boats, destinations, waters asf. as
	 * associative array with the key as name and value as GUID. One list is for
	 * those which are still valid, one for those which are by now invalid.
	 */
	_buildNames : function(listname) {
		var names = {};
		var invNames = {};
		var listnameLC = listname.toLowerCase();
		if (!this.defs.namesListFor[listname])
			return;
		var checkValidity = this.defs.validityColumns[listname];
		var now = Date.now();
		r = 0;
		this.lists[listname]
				.forEach(function(row) {
					var guid = row["Id"];
					// boat have variants, each with a separate name.
					if (listnameLC.localeCompare("efaweb_persons") == 0)
						name = cToolbox.fullName(row);
					else
						name = row["Name"];
					var invalidFrom = parseInt(row["InvalidFrom"]);
					if (!checkValidity || (invalidFrom > now))
						names[name] = guid;
					else
						invNames[name] = guid;
					r++;
				});
		this.names[listname + '_names'] = names;
		this.names[listname + '_invNames'] = invNames;
	},

	/**
	 * download a single list. lastModifiedSeconds is the filter for
	 * modifications after this (seconds after 1.1.70).
	 */
	_downloadList : function(setname, listname, lastModifiedSeconds) {
		var record = [ "LastModified;" + lastModifiedSeconds ];
		record.push("Logbookname;" + $_logbookname);
		record.push("setname;" + setname);
		cLists.defs.getListArgs(listname).forEach(function(listArg) {
			record.push(listArg);
		});
		cTxQueue.addNewTxToPending("list", listname, record, 0, null, null);
	},

	/**
	 * refresh all secondary indices asf. for a single list.
	 */
	_refreshList : function(listname) {

		// invalidate the list.
		this.refreshing[listname] = true;

		// collect the keys for the list.
		cLists.keys[listname] = [];
		if (this.lists[listname] && this.lists[listname][0])
			for (key in this.lists[listname][0]) {
				cLists.keys[listname].push(key);
			}

		cLists.defs.addExtraInformation(listname);
		
		this._buildNames(listname);
		this._buildIndicesAndFilter(listname);

		// re-validate the list
		this.refreshing[listname] = false;
		console.log(_("WunktC|refreshed") + " " + listname);

		// lists may be read by a server script, then the panel needs no update.
		if (this.loadingCompleted())
			try {
				bPanel.update();
			} catch (ignored) {
			};
	},

}
