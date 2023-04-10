var efaListDefs = {

	/**
	 * The list set name
	 */
	setname : "efaWeb",

	/*
	 * The ultimate used validity, 2^64-1
	 */
	neverInvalid : 9223372036854775807, // last 3 digits originally 807

	/**
	 * list of efaWeb list names
	 */
	listnames : [ "efaWeb_boatdamages", "efaWeb_boatreservations",
			"efaWeb_boats", "efaWeb_boatstatus", "efaWeb_crews",
			"efaWeb_destinations", "efaWeb_fahrtenabzeichen", "efaWeb_groups",
			"efaWeb_logbook", "efaWeb_own_sessions", "efaWeb_opentrips",
			"efaWeb_messages", "efaWeb_persons", "efaWeb_sessiongroups",
			"efaWeb_status", "efaWeb_waters" ],
	// note: "efaweb_virtual_boatVariants" is a locally generated list,
	// no download.

	/**
	 * some downloaded lists shall not be added to a list of their own, but
	 * rather merged into an existing list. This is the target definition for
	 * those, which are diverted.
	 */
	listsToDivert : { efaWeb_own_sessions : "efaWeb_logbook", 
		efaWeb_opentrips: "efaWeb_logbook"
	},
	
	/**
	 * all lists which shall not build an ecrid index.
	 */
	noEcridLists : ["efaweb_virtual_boatVariants"],

	/**
	 * list of bths lists which propagate changes to the server side.
	 */
	serverWriteAllowed : {
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
	validityColumns : {
		efaWeb_boats : true,
		efaWeb_destinations : true,
		efaWeb_groups : true,
		efaWeb_persons : true
	},

	/**
	 * list of bths lists for which a "names" index is created.
	 */
	indexNamesFor : {
		efaWeb_boats : true,
		efaweb_virtual_boatVariants : true,
		efaWeb_destinations : true,
		// the persons names index is build using full (= first and last) names.
		efaWeb_persons : false,
		efaWeb_sessiongroups : true,
		efaWeb_status : true,
		efaWeb_waters : true,
	},

	/**
	 * list of bths lists for which a "names" list is created.
	 */
	namesListFor : {
		efaWeb_boats : true,
		efaweb_virtual_boatVariants : true,
		efaWeb_destinations : true,
		efaWeb_persons : true,
		efaWeb_sessiongroups : true,
		efaWeb_status : true,
		efaWeb_waters : true,
	},

	/**
	 * list of bths lists which a numeric ID index is created.
	 */
	indexNumericIdsFor : {
		efaWeb_boatdamages : "Damage",
		efaWeb_boatreservations : "Reservation",
		efaWeb_logbook : "EntryId",
		efaWeb_messages : "MessageId",
	},

	/**
	 * list of bths lists which a UUID index is created.
	 */
	indexUUIDsFor : {
		efaWeb_boats : "Id",
		efaweb_virtual_boatVariants : "Id",
		efaWeb_boatstatus : "BoatId",
		efaWeb_clubwork : "Id",
		efaWeb_destinations : "Id",
		efaWeb_fahrtenabzeichen : "PersonId",
		efaWeb_persons : "Id",
		efaWeb_sessiongroups : "Id",
		efaWeb_statistics : "Id",
		efaWeb_status : "Id",
		efaWeb_waters : "Id"
	},

	/**
	 * list of tables which hav an IdList field
	 */
	usedForIdLists : [ "efaWeb_persons", "efaWeb_waters" ],
	
	/**
	 * list of tables which hav an IdList field
	 */
	usedForSpecialIndices : [ "efaWeb_logbook", "efaWeb_own_sessions" ],

	/**
	 * list of efaWeb list names linked per tablename for the API
	 */
	listsForTables : { 
		efa2boatdamages : [ "efaWeb_boatdamages" ],
		efa2boatreservations : [ "efaWeb_boatreservations" ],
		efa2boats : [ "efaWeb_boats" ], // note:
										// "efaweb_virtual_boatVariants"
										// is a locally generated list, no
										// download.
		efa2boatstatus : [ "efaWeb_boatstatus" ],
		efa2crews : [ "efaWeb_crews" ],
		efa2destinations : [ "efaWeb_destinations" ],
		efa2fahrtenabzeichen : [ "efaWeb_fahrtenabzeichen" ],
		efa2groups : [ "efaWeb_groups" ],
		efa2logbook : [ "efaWeb_logbook", "efaWeb_own_sessions", "efaWeb_opentrips" ],
		efa2messages : [ "efaWeb_messages" ],
		efa2persons : [ "efaWeb_persons" ],
		efa2sessiongroups : [ "efaWeb_sessiongroups" ],
		efa2status : [ "efaWeb_status" ],
		efa2waters : [ "efaWeb_waters" ],
	},

	/**
	 * the lists which will not be cached in permanent local storage
	 */
	// cache lists for faster reload except the persons list, to avoid that
	// person related data are kept in the local storage. Note that the
	// efaWeb_logbook list does not load the AllCrewNames or any other crew name
	// except for the own trips.
	noCaching : [ "efaWeb_persons" ],
	
	/**
	 * Build indices for the logbook display: last sessions by either the
	 * EntryId or the sessions' start time.
	 */
	buildSpecialIndices : function(listname) {
		
		// Build specific name index for persons based non full name
		if (listname.localeCompare("efaWeb_persons") == 0) {
			var names = {};
			var validNames = {};
			var r = 0;
			// pivoting
			cLists.lists.efaWeb_persons.forEach(function(row) {
				names[cToolbox.fullName(row)] = r;
				r++;
			});
			cLists.indices[listname + "_names"] = cLists._sortIndex(names);
		}

		// standard index for entry IDs is built before this.
		// index for last trips. Prepare.
		var lastSessions = [];
		var r = 0;
		cLists.lists.efaWeb_logbook.forEach(function(row) {
			lastSessions.push({
				EntryId : row["EntryId"],
				startAt : cToolbox.parseEfaDate(row["Date"], row["StartTime"])
						.valueOf(),
				rpos : r
			});
			r++;
		});
		// build "efaWeb_logbook_lastByEntryId"
		lastSessions.sort(function(a, b) {
			return b.EntryId - a.EntryId;
		});
		var lastByEntryId = [];
		lastSessions.forEach(function(trip) {
			lastByEntryId.push(trip.rpos);
		})
		cLists.indices["efaWeb_logbook_lastByEntryId"] = lastByEntryId;
		// build "efaWeb_logbook_lastByStart"
		lastSessions.sort(function(a, b) {
			return b.startAt - a.startAt;
		});
		var lastByStart = [];
		lastSessions.forEach(function(trip) {
			lastByStart.push(trip.rpos);
		})
		cLists.indices["efaWeb_logbook_lastByStart"] = lastByStart;
	},

	/**
	 * Add some extra efaWeb information for some in-memory-lists. And add the
	 * virtual boat variants list
	 */
	addExtraInformation : function(listname) {
		// Provide an extra column for persons for convenience
		if (listname.localeCompare("efaWeb_persons") == 0) {
			for (person of cLists.lists[listname]) 
				person["FullName"] = cToolbox.fullName(person);
		}
		else if (listname.localeCompare("efaWeb_boats") == 0) {
			cLists.lists["efaweb_virtual_boatVariants"] = [];
			for (boat of cLists.lists[listname]) {
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
				var nameList = oBoat.getNames(boat);
				nameList.forEach(function(variantName) {
					var boatVariant = Object.assign({}, boat);
					boatVariant["Name"] = variantName;
					cLists.lists["efaweb_virtual_boatVariants"].push(boatVariant);
				})
			}
		}
		else if (listname.localeCompare("efaWeb_logbook") == 0) {
			for (trip of cLists.lists[listname]) {
				if (trip) {
					if (!trip["Date"])
						trip["Date"] = "01.01.1970";
					if (!trip["StartTime"])
						trip["StartTime"] = "08:00:00";
					if (trip.EntryId)
						trip.EntryId = parseInt(trip.EntryId);
				}
			}
		}
		// build index for virtual list of boat variants
		if (listname.localeCompare("efaWeb_boats") == 0) {
			cLists._buildNames("efaweb_virtual_boatVariants");
			cLists._buildIndicesAndFilter("efaweb_virtual_boatVariants");
		}
	},

	/**
	 * Return a set of options used by cForm for building the form.
	 */
	getOptions : function(listname) {
		// Return a list of the last trips to choose from, when entering a
		// damage report.
		if (listname.toLowerCase().localeCompare("lastSessions")) {
			var lastSessions = [];
			lastSessions.push("0=bitte auswählen");
			if (cLists.indices && cLists.indices.efaWeb_logbook_lastByEntryId) {
				for (var i = 0; i < 50; i++) {
					if (cLists.indices.efaWeb_logbook_lastByEntryId[i]) {
						var trip = cLists.lists.efaWeb_logbook[cLists.indices.efaWeb_logbook_lastByEntryId[i]];
						lastSessions.push(oSession.shortDescription(trip));
					}
				}
			}
			return lastSessions;
		}
	},

	/**
	 * @return the next free entryId for the logbook. This is no counter. If the
	 *         last EntryId was deleted, it will be reused.
	 */
	nextEntryId : function() {
		var maxEntryId = 0;
		cLists.lists.efaWeb_logbook.forEach(function(row) {
			if (row.EntryId > maxEntryId)
				maxEntryId = row.EntryId;
		});
		return maxEntryId + 1;
	},

	/**
	 * get all arguments for a list to retrieve
	 */
	getListArgs : function(listname) {
		var listArgs = [];
		if (listname.localeCompare("efaWeb_own_sessions") == 0)
			listArgs.push("listarg1;{PersonId}=" + $_personId);
		return listArgs;
	}

}