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

try {

	/**
	 * The full applicatiuon configuration, in particular all forms.
	 */
var $_formTemplates = {

// These are "normal" form templates, with the exception of the class field
// extras: # for adding an id not possible, id is differently used.
// instead 'call:' to explicity call a validation function and 'validate:' to
// force a validation against a list.
		login :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l1'>;;_no_input;;ZknAAM| ** efaWeb ** - DEMO WIT...;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;bBnlvR| ** Login to the logbook...;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;*;Account;;zbSX9F|efaCloudUser ID;text;;25;50
</div></div><div class='w3-row'><div class='w3-col l1'>;*;Passwort;;6lYbjM|efaCloudUser password;password;;25;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;Logbookname;;CJ5hTv| ** logbook ** e.g. 2021...;text;;25;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;HOPydU| ** ;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;fbtgjS|Log in;;submit;formbutton;;
</div></div>;;_no_input;;;;;;
<li><span class='helptext'>;;_help_text;;PfwuLC|efaWeb is currently only...;;;;
</span></li>;;_help_text;;;;;;`,

		startSession :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l2'>;!;EntryId;;h6WIfD|Trip number;text;display-bold;8;50
</div><div class='w3-col l2'>;!;Logbookname;;r0b4Hy|Logbook;text;display-bold;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;Date;;6Vh9NF|Start on;date;;12;50
</div><div class='w3-col l2'>;*;StartTime;;C1Bsg1|Start at;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2' id='startSession-BoatId'>;*;BoatId;;9SKlbu|Boat;text;;18;50
</div><div class='w3-col l2' id='startSession-boatInfo'>;;_no_input;;;text;;;
</div></div><div class='w3-row'><div class='w3-col l2' id='div-CoxId'>;;CoxId;;jCOfC8|Cox;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;TUic0A|In the boat:;;;;
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew1Id'>;*;Crew1Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew5Id'>;;Crew5Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew2Id'>;;Crew2Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew6Id'>;;Crew6Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew3Id'>;;Crew3Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew7Id'>;;Crew7Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew4Id'>;;Crew4Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew8Id'>;;Crew8Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2'>;;DestinationId;;c5gYBM|Destination;text;;18;50
</div><div class='w3-col l2'>;;WatersIdList;;V1zYIE|Waters;text;;15;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;Distance;;2yMlSU|Distance (e.g. 12 km);text;;18;50
</div><div class='w3-col l2'>;;SessionType;;bBjPpk|Type of trip;select use:SessionTypes;;15;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;Comments;;THke0j|Remarks;textarea;;2;90%
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;HjmWZW| ** ;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;fpTiN4|Start trip;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`,

		lateEntry :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l2'>;!;EntryId;;o3t6qP|Trip number;text;display-bold;8;50
</div><div class='w3-col l2'>;!;Logbookname;;kxrdSA|Logbook;text;display-bold;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;Date;;Nfckze|Start on;date;;12;50
</div><div class='w3-col l2'>;*;StartTime;;bK30UC|Start at;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;;EndDate;;JQVD9x|End at;date;;12;50
</div><div class='w3-col l2'>;;EndTime;;nJr5rv|End at;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2' id='lateEntry-BoatId'>;*;BoatId;;a67Dyd|Boat;text;;18;50
</div><div class='w3-col l2' id='startSession-boatInfo'>;;_no_input;;;text;;;
</div></div><div class='w3-row'><div class='w3-col l2' id='div-CoxId'>;;CoxId;;8lIkQ4|Cox;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;YKoKON|In the boat:;;;;
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew1Id'>;*;Crew1Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew5Id'>;;Crew5Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew2Id'>;;Crew2Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew6Id'>;;Crew6Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew3Id'>;;Crew3Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew7Id'>;;Crew7Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew4Id'>;;Crew4Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew8Id'>;;Crew8Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;DestinationId;;0Qq93T|Destination;text;;18;50
</div><div class='w3-col l2'>;;WatersIdList;;dza8cR|Waters;text;;15;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;Distance;;0YFLpN|Distance (e.g. 12 km);text;;18;50
</div><div class='w3-col l2'>;;SessionType;;lDZYQi|Type of trip;select use:SessionTypes;;15;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;Comments;;O6tNqN|Remarks;textarea;;2;90%
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;LzuvqD| ** ;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;vnRXNL|Add kilometres;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`,

		endSession :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l2'>;!;EntryId;;8NzTOB|Trip number;text;display-bold;8;50
</div><div class='w3-col l2'>;;Logbookname;kVI3tK|!;SdGfhU|Logbook;text;display-bold;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;!;Date;;L6rvsh|Start on;date;;12;50
</div><div class='w3-col l2'>;!;StartTime;;kBTQNR|Start at;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;;EndDate;;JH8SKy|End at;date;;12;50
</div><div class='w3-col l2'>;*;EndTime;;1TUhfr|End at;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2' id='endSession-BoatId'>;!;BoatId;;XmM2FP|Boat;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-CoxId'>;;CoxId;;CFgig5|Cox;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;fkjeG8|In the boat:;;;;
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew1Id'>;*;Crew1Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew5Id'>;;Crew5Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew2Id'>;;Crew2Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew6Id'>;;Crew6Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew3Id'>;;Crew3Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew7Id'>;;Crew7Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew4Id'>;;Crew4Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew8Id'>;;Crew8Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;DestinationId;;JOGpo7|Destination;text;;18;50
</div><div class='w3-col l2'>;;WatersIdList;;eVc6qC|Waters;text;;15;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;Distance;;Yrgfjt|Distance (e.g. 12 km);text;;18;50
</div><div class='w3-col l2'>;;SessionType;;ul1Rji|Type of trip;select use:SessionTypes;;15;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;Comments;;rfBQGI|Remarks;textarea;;2;90%
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;dXUBZv| ** ;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;TUlSJq|End trip;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`,

		updateSession :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l2'>;!;EntryId;;mvC9cO|Trip number;text;display-bold;8;50
</div><div class='w3-col l2'>;;Logbookname;E2CDwz|!;VaINZs|Logbook;text;display-bold;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;!;Date;;kQc9LT|Start on;date;;12;50
</div><div class='w3-col l2'>;!;StartTime;;skkCdt|Start at;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;;EndDate;;yl6jQr|End at;date;;12;50
</div><div class='w3-col l2'>;*;EndTime;;gIkHBc|End at;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;!;BoatId;;JbuHVF|Boat;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-CoxId'>;;CoxId;;7wmNgN|Cox;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;A6nZ8o|In the boat:;;;;
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew1Id'>;*;Crew1Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew5Id'>;;Crew5Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew2Id'>;;Crew2Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew6Id'>;;Crew6Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew3Id'>;;Crew3Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew7Id'>;;Crew7Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew4Id'>;;Crew4Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew8Id'>;;Crew8Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;DestinationId;;7eSHFJ|Destination;text;;18;50
</div><div class='w3-col l2'>;;WatersIdList;;WPx45J|Waters;text;;15;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;Distance;;8x1P9Y|Distance (e.g. 12 km);text;;18;50
</div><div class='w3-col l2'>;;SessionType;;ZBqWHN|Type of trip;select use:SessionTypes;;15;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;Comments;;qfNiOq|Remarks;textarea;;2;90%
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;KbBLuc| ** ;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;fNfZHA|Update trip;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`,

		cancelSession :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l2'>;!;EntryId;;TOtJ3P|Trip number;text;display-bold;8;50
</div><div class='w3-col l2'>;;Logbookname;bcRYbz|!;UHtvgt|Logbook;text;display-bold;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;!;Date;;e6eYdw|Start on;date;display-bold;12;50
</div><div class='w3-col l2'>;!;StartTime;;9fffoj|Start at;text;display-bold;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;;EndDate;;QCQnSa|End at;date;display-bold;12;50
</div><div class='w3-col l2'>;*;EndTime;;4ReiZl|End at;text;display-bold;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;BoatId;;dPH3Mp|Boat;text;display-bold;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-CoxId'>;;CoxId;;Hy621r|Cox;text;display-bold;18;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;hsozbd|In the boat:;;;;
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew1Id'>;*;Crew1Id;;;text;display-bold;18;50
</div><div class='w3-col l2' id='div-Crew5Id'>;;Crew5Id;;;text;display-bold;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew2Id'>;;Crew2Id;;;text;display-bold;18;50
</div><div class='w3-col l2' id='div-Crew6Id'>;;Crew6Id;;;text;display-bold;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew3Id'>;;Crew3Id;;;text;display-bold;18;50
</div><div class='w3-col l2' id='div-Crew7Id'>;;Crew7Id;;;text;display-bold;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew4Id'>;;Crew4Id;;;text;display-bold;18;50
</div><div class='w3-col l2' id='div-Crew8Id'>;;Crew8Id;;;text;display-bold;18;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;DestinationId;;jabwQd|Destination;text;display-bold;18;50
</div><div class='w3-col l2'>;;WatersIdList;;qApQ6x|Waters;text;display-bold;15;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;Distance;;DFV5H3|Distance (e.g. 12 km);text;display-bold;18;50
</div><div class='w3-col l2'>;;SessionType;;TBQosa|Type of trip;select use:SessionTypes;display-bold;15;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;Comments;;0ZqZ7H|Remarks;textarea;display-bold;2;90%
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;RIUYXm| ** ;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;ALdpUI|Cancel trip;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`,

		postDamage : 
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l1'>;;_no_input;;lD9JeK|<h5>Please enter the dam...;;;;
</div></div><div class='w3-row'><div class='w3-col l2'>;*;BoatId;;XrnRSE|Name of boat;text;;18;50
</div><div class='w3-col l2'>;*;ReportedByPersonId;;SWNkdA|reported by;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;ReportDate;;Hwcezs|originated on;date;;18;50
</div><div class='w3-col l2'>;*;ReportTime;;ORKVw1|at (time);text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' style='padding-top:15px'>;;Claim;;BPMzAk|Insurance claim?;checkbox;;18;50
</div><div class='w3-col l2'>;*;Severity;;RKsX0G|Severity;"select FULLYUSEABLE=Boat fully usable;LIMITEDUSEABLE=Boat limited usable;NOTUSEABLE=Boat not usable";;18;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;LogbookText;;riKHM5|Trip;select list:lastSessions;;50;100
</div></div><div class='w3-row'><div class='w3-col l1'>;;Description;;9Gd8YN| ** Please describe the ...;textarea;;4;45
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;pjcJ07| ** ;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;VIEH0Z|Submit;;submit;formbutton;;
</div></div>;;_no_input;;;;;;
<li><span class='helptext'>;;_help_text;;83vPn1|You can enter the trip n...;;;;
</span></li>;;_help_text;;;;;;`,

		readDamage :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l1'>;;_no_input;;NK1dHk|<h5>Please specify the b...;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;BoatId;;tusipi|Name of boat;text;;25;50
</div></div><div class='w3-row'><div class='w3-col l2' style='padding-top:15px'>;;AlsoDone;;CiHNBM|Also fixed?;checkbox;;18;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;xmanWk| ** ;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;r9v9Cg|Find;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`,

		postMessage :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l1'>;;_no_input;;SsHYiD|<h5>Please enter the mes...;;;;
</div></div><div class='w3-row'><div class='w3-col l2'>;*;From;;VVqvPA|entered by;text;;18;50
</div><div class='w3-col l2'>;*;To;;Gjq53k|Message is for;"select BOATM=Bootsmeister;ADMIN=Administrator";;18;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;Subject;;0uPvqR|Title;text;;18;50
</div><div class='w3-col l2'>;*;ReplyTo;;T3CqRg|Please reply to (mail);text;;18;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;Text;;G6wVsj| ** Please enter the mes...;textarea;;4;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;Untpor| ** ;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;qMKgTz|Submit;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`,

		bookAboat :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l1'>;;_no_input;;RA89xO|<h5>Reserve a boat once<...;;;;
</div></div><div class='w3-row'><div class='w3-col l2'>;*;BoatId;;hRKeQX|Boat;text;validate:efaWeb_boats;18;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;DateFrom;;LVtdZf|From (day);date;;12;50
</div><div class='w3-col l2'>;*;TimeFrom;;QAA0gV|From (time);text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;DateTo;;pAkBVq|Until (day);date;;12;50
</div><div class='w3-col l2'>;*;TimeTo;;CD7KnS|Until (time);text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;PersonId;;d8C17W|For whom?;text;validate:efaWeb_persons;18;50
</div><div class='w3-col l2'>;;Contact;;l5FrCm|Telephone for queries;text;;12;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;Reason;;N6KBST|Reason for reservation;textarea;;4;
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;ie07jE| ** ;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;FqOHte|Reserve now;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`,

		changeLogbook :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l1'>;;_no_input;;4yJ56h|Please select the logboo...;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l1'>;*;Logbookname;;y2bjFM|Logbook;select use:LogbooksAllowed;;8;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;sx82uN| ** ;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;Zuiu0O|Open;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`

}

var $_formNames = [];
var $_formDefs = {};
for (var key in $_formTemplates) {
	$_formDefs[key] = cToolbox.readCsvList($_formTemplates[key]);
	$_formNames.push(key);
}

// get help texts
const _helptextNames = { modifySessions : "webFahrtenEintragen" };
var $_efaWeb_helptexts = {};
for (_helptextName in _helptextNames) {
	var url = "../helpdocs/" + $_languageCode + "/" + _helptextNames[_helptextName] + ".html";
	jQuery.get(url, function(data) {
			$_efaWeb_helptexts[_helptextName] = data;
	}).fail(function() {
		url = "../helpdocs/de/" + _helptextNames[_helptextName] + ".html";
		jQuery.get(url, function(data) {
			$_efaWeb_helptexts[_helptextName] = data;
		}).fail(function() {
			$_efaWeb_helptexts[_helptextName] = _("9e4GGB|Could not find helptext ...") + " " + _helptextNames[_helptextName];
		});
	});
}

// all parameter sets for every select:use field in every form
var $_params = {};
$_params["SessionTypes"] = $_efaTypes["SESSION"];  

// efa Client settings as passed via divs, i.e. derived from efaCloud Settings
var $_logbooknames = [];
var $_logbooknames_prevYear = [];
// if the sports year start is in the second half of the year, the sports year will use the next years number 
// rather than the current years number when starting.
if (parseInt(efaCloudCfg["sports_year_start"]) > 6) {
	var sportsYearStart = "" + $_currentYear + "-" + efaCloudCfg["sports_year_start"] + "-01";
	sportsYearStart = new Date(sportsYearStart);
	var now = new Date();
	if ((now - sportsYearStart) > 0) $_currentYear++;
}
var $_logbookname = efaCloudCfg["current_logbook"].toString().replace("JJJJ", $_currentYear);
$_logbooknames.push($_logbookname);
$_logbooknames_prevYear.push(efaCloudCfg["current_logbook"].toString().replace("JJJJ", $_currentYear - 1));
for (var l = 2; l < 5; l++) 
	if (efaCloudCfg["current_logbook" + l])
		if (efaCloudCfg["current_logbook" + l].toString().indexOf("JJJJ") >= 0) {
			$_logbooknames.push(efaCloudCfg["current_logbook" + l].replace(/JJJJ/, $_currentYear));
			$_logbooknames_prevYear.push(efaCloudCfg["current_logbook" + l].replace(/JJJJ/, $_currentYear - 1));
		}

var $_sports_year_start = efaCloudCfg["sports_year_start"];
var current_logbook_element = $('.current-logbook')[0];
var $_logbookname = $(current_logbook_element).attr('id');
var logbook_allowance_element = $('.logbook-allowance')[0];
var $_logbook_allowance = $(logbook_allowance_element).attr('id');
var $_logbook_allowance_all = ($_logbook_allowance.toLowerCase().localeCompare("all") == 0);
var personId_element = $('.person-id')[0];
var $_personId = $(personId_element).attr('id');
if ($_personId.length < 10) $_personId = "xxxx-xxxx-xxxx";
// club name setting
var $_clubname = ($_efaProject["Clubs"]) ? $_efaProject["Clubs"][0]["ClubName"] : "";

// configured logbooks
var $_logbooksAvailable = [];
var now = Date.now();

// user specific configuration
var $_userConfig = {
	logbookConcessionFlags : [ 64, 16384, 32768, 65536 ],
	logbooksAllowed : [],
	
	init : function() {
		if ($_logbook_allowance_all) 
			this.logbooksAllowed = $_logbooknames;
		else {
			this.logbooksAllowed = [];
			for (var l = 1; l < 5; l++)
				if (($_user_concessions & this.logbookConcessionFlags[l - 1]) > 0) {
					if ($_logbooknames[l - 1]) 
						this.logbooksAllowed.push($_logbooknames[l - 1]);
					if ($_logbooknames_prevYear[l - 1]) 
						this.logbooksAllowed.push($_logbooknames_prevYear[l - 1]);
				}
		}
		$_params["LogbooksAllowed"] = this.logbooksAllowed;
		var currentLogbookAllowed = false;
		for (var logbookIndex in this.logbooksAllowed) {
			var logbookname = this.logbooksAllowed[logbookIndex];
			if (logbookname.localeCompare($_logbookname) == 0)
				currentLogbookAllowed = true;
		}
		if (!currentLogbookAllowed && (this.logbooksAllowed.length > 0))
			$_logbookname = this.logbooksAllowed[0];
		
	}
}

} catch (e) {
	cModal.showException(e);
}
