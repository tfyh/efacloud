/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2022 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

var oPerson = {

	/**
	 * efa inserts invalid persons not as UUID, but as real names. So check all
	 * UUIDs for persons of their validity. Note that in cLists the versionized
	 * data tripRecords were already filtered to those with the latest validity
	 * only.
	 */
	guidsToNamesForInvalidPersons : function(tripRecord) {
		if (tripRecord["CoxId"]) {
			var coxRow = cLists.indices.efaWeb_persons_guids[tripRecord["CoxId"]];
			var cox = (coxRow) ? cLists.lists.efaWeb_persons[coxRow] : false;
			if (cox["invalidFrom"]
					&& (cox["invalidFrom"] < Math.floor(Date.now()))) {
				tripRecord["CoxId"] = "";
				tripRecord["CoxName"] = crewmember["FirstLastName"];
			}
		}
		for (var i = 1; i <= 24; i++) {
			if (tripRecord["Crew" + i + "Id"]) {
				var crewmemberRow = cLists.indices.efaWeb_persons_guids[tripRecord["Crew"
						+ i + "Id"]];
				var crewmember = (typeof crewmemberRow !== 'undefined') ? cLists.lists.efaWeb_persons[crewmemberRow]
						: false;
				if (crewmember["InvalidFrom"]
						&& (crewmember["InvalidFrom"] < Math.floor(Date.now()))) {
					tripRecord["Crew" + i + "Id"] = "";
					var fullname;
					if ($_last_name_first)
						fullname = crewmember["LastName"] + ", " + crewmember["FirstName"];
					else
						fullname = crewmember["FirstName"] + " " + crewmember["LastName"];
					tripRecord["Crew" + i + "Name"] = fullname;
				}
			}
		}
	}
	
}