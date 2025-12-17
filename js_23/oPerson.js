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