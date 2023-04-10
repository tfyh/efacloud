/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c)
 * 2001-2022 by Nicolas Michael Website: http://efa.nmichael.de/ License: GNU
 * General Public License v2. Module efaCloud: Copyright (c) 2020-2021 by Martin
 * Glade Website: https://www.efacloud.org/ License: GNU General Public License
 * v2
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
<div class='w3-row'><div class='w3-col l1'>;;\
 _no_input;;<h3><b>efaWeb</b> - DEMO OHNE GEWÄHR.<br></h3>;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;\
 _no_input;;Login in das Fahrtenbuch ist nur mit der efaCloudUserID des Nutzers oder Boothaus-PCs und dessen Kennwort möglich.<br>\
 Webnn das Kennwort nicht bekannt ist, wende Dich bitte an den efa-Administrator im Verein.<br><br>;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;*;Account;;efaCloudUser ID;text;;25;50
</div></div><div class='w3-row'><div class='w3-col l1'>;*;Passwort;;efaCloudUser Passwort;password;;25;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;logbookName;;Fahrtenbuch<br>z.B. 2021,  \
 oder JJJJ für das Jahr in einer beliebigen Zeichenkette z.B. 'JJJJ_Training'<br> \
 kann leer gelassen werden, dann wird das aktuelle Kalenderjahr angenommen.;text;;25;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;<br>;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;Einloggen;;submit;formbutton;;
</div></div>;;_no_input;;;;;;
<li><span class='helptext'>;;_help_text;;efaWeb ist zur Zeit ist nur eine Demo.  \
 Ob efaWeb Anwendung weiterentwickelt wird, hängt von Eurem Feedback an 'info@efacloud.org' ab.;;;;
</span></li>;;_help_text;;;;;;`,

		startSession :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l2'>;!;EntryId;;Fahrt Nummer;text;display-bold;8;50
</div><div class='w3-col l2'>;!;Logbookname;;Fahrtenbuch;text;display-bold;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;Date;;Beginn am;date;;12;50
</div><div class='w3-col l2'>;*;StartTime;;Beginn um;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2' id='startSession-BoatId'>;*;BoatId;;Boot;text;;18;50
</div><div class='w3-col l2' id='startSession-boatInfo'>;;_no_input;; ;text;;;
</div></div><div class='w3-row'><div class='w3-col l2' id='div-CoxId'>;;CoxId;;Am Steuer;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;Im Boot:;;;;
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew1Id'>;*;Crew1Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew5Id'>;;Crew5Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew2Id'>;;Crew2Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew6Id'>;;Crew6Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew3Id'>;;Crew3Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew7Id'>;;Crew7Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew4Id'>;;Crew4Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew8Id'>;;Crew8Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2'>;;DestinationId;;Ziel;text;;18;50
</div><div class='w3-col l2'>;;WatersIdList;;Gewässer;text;;15;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;Distance;;Entfernung (z. B. 12 km);text;;18;50
</div><div class='w3-col l2'>;;SessionType;;Art der Fahrt;"select use:SessionTypes";;15;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;Comments;;Bemerkungen;textarea;;2;90%
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;<br>;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;Fahrt beginnen;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`,

		lateEntry :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l2'>;!;EntryId;;Fahrt Nummer;text;display-bold;8;50
</div><div class='w3-col l2'>;!;Logbookname;;Fahrtenbuch;text;display-bold;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;Date;;Beginn am;date;;12;50
</div><div class='w3-col l2'>;*;StartTime;;Beginn um;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;;EndDate;;Ende am;date;;12;50
</div><div class='w3-col l2'>;;EndTime;;Ende um;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2' id='lateEntry-BoatId'>;*;BoatId;;Boot;text;;18;50
</div><div class='w3-col l2' id='startSession-boatInfo'>;;_no_input;; ;text;;;
</div></div><div class='w3-row'><div class='w3-col l2' id='div-CoxId'>;;CoxId;;Am Steuer;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;Im Boot:;;;;
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew1Id'>;*;Crew1Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew5Id'>;;Crew5Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew2Id'>;;Crew2Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew6Id'>;;Crew6Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew3Id'>;;Crew3Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew7Id'>;;Crew7Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew4Id'>;;Crew4Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew8Id'>;;Crew8Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;DestinationId;;Ziel;text;;18;50
</div><div class='w3-col l2'>;;WatersIdList;;Gewässer;text;;15;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;Distance;;Entfernung (z. B. 12 km);text;;18;50
</div><div class='w3-col l2'>;;SessionType;;Art der Fahrt;"select use:SessionTypes";;15;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;Comments;;Bemerkungen;textarea;;2;90%
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;<br>;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;Kilometer nachtragen;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`,

		endSession :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l2'>;!;EntryId;;Fahrt Nummer;text;display-bold;8;50
</div><div class='w3-col l2'>;;Logbookname;!;Fahrtenbuch;text;display-bold;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;!;Date;;Beginn am;date;;12;50
</div><div class='w3-col l2'>;!;StartTime;;Beginn um;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;;EndDate;;Ende am;date;;12;50
</div><div class='w3-col l2'>;*;EndTime;;Ende um;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2' id='endSession-BoatId'>;!;BoatId;;Boot;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-CoxId'>;;CoxId;;Am Steuer;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;Im Boot:;;;;
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew1Id'>;*;Crew1Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew5Id'>;;Crew5Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew2Id'>;;Crew2Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew6Id'>;;Crew6Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew3Id'>;;Crew3Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew7Id'>;;Crew7Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew4Id'>;;Crew4Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew8Id'>;;Crew8Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;DestinationId;;Ziel;text;;18;50
</div><div class='w3-col l2'>;;WatersIdList;;Gewässer;text;;15;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;Distance;;Entfernung (z. B. 12 km);text;;18;50
</div><div class='w3-col l2'>;;SessionType;;Art der Fahrt;"select use:SessionTypes";;15;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;Comments;;Bemerkungen;textarea;;2;90%
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;<br>;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;Fahrt beenden;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`,

		updateSession :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l2'>;!;EntryId;;Fahrt Nummer;text;display-bold;8;50
</div><div class='w3-col l2'>;;Logbookname;!;Fahrtenbuch;text;display-bold;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;!;Date;;Beginn am;date;;12;50
</div><div class='w3-col l2'>;!;StartTime;;Beginn um;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;;EndDate;;Ende am;date;;12;50
</div><div class='w3-col l2'>;*;EndTime;;Ende um;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;!;BoatId;;Boot;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-CoxId'>;;CoxId;;Am Steuer;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;Im Boot:;;;;
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew1Id'>;*;Crew1Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew5Id'>;;Crew5Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew2Id'>;;Crew2Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew6Id'>;;Crew6Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew3Id'>;;Crew3Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew7Id'>;;Crew7Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew4Id'>;;Crew4Id;;;text;;18;50
</div><div class='w3-col l2' id='div-Crew8Id'>;;Crew8Id;;;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;DestinationId;;Ziel;text;;18;50
</div><div class='w3-col l2'>;;WatersIdList;;Gewässer;text;;15;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;Distance;;Entfernung (z. B. 12 km);text;;18;50
</div><div class='w3-col l2'>;;SessionType;;Art der Fahrt;"select use:SessionTypes";;15;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;Comments;;Bemerkungen;textarea;;2;90%
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;<br>;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;Fahrt aktualisieren;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`,

		cancelSession :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l2'>;!;EntryId;;Fahrt Nummer;text;display-bold;8;50
</div><div class='w3-col l2'>;;Logbookname;!;Fahrtenbuch;text;display-bold;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;!;Date;;Beginn am;date;display-bold;12;50
</div><div class='w3-col l2'>;!;StartTime;;Beginn um;text;display-bold;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;;EndDate;;Ende am;date;display-bold;12;50
</div><div class='w3-col l2'>;*;EndTime;;Ende um;text;display-bold;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;BoatId;;Boot;text;display-bold;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-CoxId'>;;CoxId;;Am Steuer;text;display-bold;18;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;Im Boot:;;;;
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew1Id'>;*;Crew1Id;;;text;display-bold;18;50
</div><div class='w3-col l2' id='div-Crew5Id'>;;Crew5Id;;;text;display-bold;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew2Id'>;;Crew2Id;;;text;display-bold;18;50
</div><div class='w3-col l2' id='div-Crew6Id'>;;Crew6Id;;;text;display-bold;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew3Id'>;;Crew3Id;;;text;display-bold;18;50
</div><div class='w3-col l2' id='div-Crew7Id'>;;Crew7Id;;;text;display-bold;18;50
</div></div><div class='w3-row'><div class='w3-col l2' id='div-Crew4Id'>;;Crew4Id;;;text;display-bold;18;50
</div><div class='w3-col l2' id='div-Crew8Id'>;;Crew8Id;;;text;display-bold;18;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;DestinationId;;Ziel;text;display-bold;18;50
</div><div class='w3-col l2'>;;WatersIdList;;Gewässer;text;display-bold;15;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;Distance;;Entfernung (z. B. 12 km);text;display-bold;18;50
</div><div class='w3-col l2'>;;SessionType;;Art der Fahrt;"select use:SessionTypes";display-bold;15;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;Comments;;Bemerkungen;textarea;display-bold;2;90%
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;<br>;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;Fahrt abbrechen;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`,

		postDamage : 
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l1'>;;_no_input;;<h5>Bitte die Schadensmeldung vollständig eintragen.</h5>;;;;
</div></div><div class='w3-row'><div class='w3-col l2'>;*;BoatId;;Name des Bootes;text;;18;50
</div><div class='w3-col l2'>;*;ReportedByPersonId;;gemeldet durch;text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;ReportDate;;entstanden am;date;;18;50
</div><div class='w3-col l2'>;*;ReportTime;;um (Uhrzeit);text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' style='padding-top:15px'>;;Claim;;Versicherungsschaden?;checkbox;;18;50
</div><div class='w3-col l2'>;*;\
 Severity;;Schwere;"select FULLYUSEABLE=Boot voll benutzbar;LIMITEDUSEABLE=Boot eingeschränkt benutzbar;NOTUSEABLE=Boot nicht benutzbar";;18;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;LogbookText;;Fahrt;select list:lastSessions;;50;100
</div></div><div class='w3-row'><div class='w3-col l1'>;;Description;;<br>Bitte den Schaden beschreiben: was und wo?;textarea;;4;45
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;<br>;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;Absenden;;submit;formbutton;;
</div></div>;;_no_input;;;;;;
<li><span class='helptext'>;;_help_text;;In der Beschreibung kannst Du die Fahrtnummer angeben.;;;;
</span></li>;;_help_text;;;;;;`,

		readDamage :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l1'>;;_no_input;;<h5>Bitte das Boot angeben, für das die Schadensmeldungen gesucht werden.</h5>;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;BoatId;;Name des Bootes;text;;25;50
</div></div><div class='w3-row'><div class='w3-col l2' style='padding-top:15px'>;;AlsoDone;;Auch die behobenen?;checkbox;;18;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;<br>;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;Finden;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`,

		postMessage :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l1'>;;_no_input;;<h5>Bitte die Nachricht hier eintragen.</h5>;;;;
</div></div><div class='w3-row'><div class='w3-col l2'>;*;From;;eingetragen von;text;;18;50
</div><div class='w3-col l2'>;*;To;;Nachricht ist für;"select BOATM=Bootsmeister;ADMIN=Administrator";;18;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;Subject;;Titel;text;;18;50
</div><div class='w3-col l2'>;*;ReplyTo;;Antwort bitte an (Mail);text;;18;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;Text;;<br>Bitte hier die Nachricht eintragen;textarea;;4;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;<br>;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;Absenden;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`,

		bookAboat :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l1'>;;_no_input;;<h5>Ein Boot einmalig reservieren</h5>;;;;
</div></div><div class='w3-row'><div class='w3-col l2'>;*;BoatId;;Boot;text;validate:efaWeb_boats;18;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;DateFrom;;Von (Tag);date;;12;50
</div><div class='w3-col l2'>;*;TimeFrom;;Von (Zeit);text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;DateTo;;Bis (Tag);date;;12;50
</div><div class='w3-col l2'>;*;TimeTo;;Bis (Zeit);text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;PersonId;;Für wen?;text;validate:efaWeb_persons;18;50
</div><div class='w3-col l2'>;;Contact;;Telefon für Rückfragen;text;;12;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;Reason;;Reservierungsgrund;textarea;;4;
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;<br>;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;Jetzt reservieren;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`,

		changeLogbook :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l1'>;;_no_input;;Bitte wähle das Fahrtenbuch, in welchem Du Eintragungen vornehmen willst.;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l1'>;*;logbookname;;Fahrtenbuch;"select use:LogbooksAllowed";;8;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;<br>;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;Öffnen;;submit;formbutton;;
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
			$_efaWeb_helptexts[_helptextName] = "Could not find helptext for " + _helptextNames[_helptextName];
		});
	});
}

// all parameter sets for every select:use field in every form
var $_params = {};
$_params["SessionTypes"] = $_efaTypes["SESSION"];  

// efa Client settings as passed via divs, i.e. derived from efaCloud Settings
var $_logbooknames = [];
var $_logbooknames_prevYear = [];
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
					this.logbooksAllowed.push($_logbooknames[l - 1]);
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
