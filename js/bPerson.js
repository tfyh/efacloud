var bPerson = {

	/**
	 * efa inserts invalid persons not as UUID, but as real names. So check all
	 * UUIDs for persons of their validity. Note that in bLists the versionized
	 * data tripRecords were already filtered to those with the latest validity
	 * only.
	 */
	guidsToNamesForInvalidPersons : function(tripRecord) {
		if (tripRecord["CoxId"]) {
			var coxRow = bLists.indices.efaWeb_persons_guids[tripRecord["CoxId"]];
			var cox = (coxRow) ? bLists.lists.efaWeb_persons[coxRow] : false;
			if (cox["invalidFrom"]
					&& (cox["invalidFrom"] < Math.floor(Date.now()))) {
				tripRecord["CoxId"] = "";
				tripRecord["CoxName"] = crewmember["FirstLastName"];
			}
		}
		for (var i = 1; i <= 24; i++) {
			if (tripRecord["Crew" + i + "Id"]) {
				var crewmemberRow = bLists.indices.efaWeb_persons_guids[tripRecord["Crew"
						+ i + "Id"]];
				var crewmember = (crewmemberRow) ? bLists.lists.efaWeb_persons[crewmemberRow]
						: false;
				if (crewmember["InvalidFrom"]
						&& (crewmember["InvalidFrom"] < Math.floor(Date.now()))) {
					tripRecord["Crew" + i + "Id"] = "";
					tripRecord["Crew" + i + "Name"] = crewmember["FirstName"] + " "
							+ crewmember["LastName"];
				}
			}
		}
	}

}