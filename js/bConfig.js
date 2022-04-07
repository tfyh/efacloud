/**
 * The full applicatiuon configuration, in particular all forms.
 */
var $_formTemplates = {
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

		startTrip :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l2'>;!;EntryId;;Fahrt Nummer;text;display-only;8;50
</div><div class='w3-col l2'>;!;Logbookname;;Fahrtenbuch;text;display-only;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;Date;;Beginn am;date;;12;50
</div><div class='w3-col l2'>;*;StartTime;;Beginn um;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;BoatId;;Boot;text;;18;50
</div><div class='w3-col l2' id='startTrip-boatInfo'>;;_no_input;; ;text;;;
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
</div><div class='w3-col l2'>;;SessionType;;Art der Fahrt;"select NORMAL=normale Fahrt;TRAININGSCAMP=Trainingsfahrt;REGATTA=Regatta;LATEENTRY=km-Nachtrag";;15;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;Comments;;Bemerkungen;textarea;;2;90%
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;<br>;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;Fahrt beginnen;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`,

		endTrip :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l2'>;!;EntryId;;Fahrt Nummer;text;;8;50
</div><div class='w3-col l2'>;*;Logbookname;!;Fahrtenbuch;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;!;Date;;Beginn am;date;;12;50
</div><div class='w3-col l2'>;!;StartTime;;Beginn um;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;;EndDate;;Ende am;date;;12;50
</div><div class='w3-col l2'>;*;EndTime;;Ende um;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;BoatId;;Boot;text;;18;50
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
</div><div class='w3-col l2'>;;SessionType;;Art der Fahrt;"select NORMAL=normale Fahrt;TRAININGSCAMP=Trainingsfahrt;REGATTA=Regatta;LATEENTRY=km-Nachtrag";;15;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;<br>;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;Fahrt beenden;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`,

		postDamage : 
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l1'>;;_no_input;;<h5>Bitte die Schadensmeldung vollständig eintragen.</h5>;;;;
</div></div><div class='w3-row'><div class='w3-col l2'>;*;BoatId;;Name des Bootes;text;;18;50
</div><div class='w3-col l2'>;*;ReportedByPersonId;;gemeldet durch;text;;18;50
</div></div><div class='w3-row'>div class='w3-col l2'>;*;ReportDate;;entstanden am;date;;18;50
</div><div class='w3-col l2'>;*;ReportTime;;um (Uhrzeit);text;;18;50
</div></div><div class='w3-row'><div class='w3-col l2' style='padding-top:15px'>;;Claim;;Versicherungsschaden?;checkbox;;18;50
</div><div class='w3-col l2'>;*;\
 Severity;;Schwere;"select FULLYUSEABLE=Boot voll benutzbar;LIMITEDUSEABLE=Boot eingeschränkt benutzbar;NOTUSEABLE=Boot nicht benutzbar";;18;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;LogbookText;;Fahrt;select var:lastTrips;;50;100
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
</div></div><div class='w3-row'><div class='w3-col l2'>;*;DateFrom;;Von (Tag);date;call:bReservation.noConflicts;12;50
</div><div class='w3-col l2'>;*;TimeFrom;;Von (Zeit);text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;*;DateTo;;Bis (Tag);date;;12;50
</div><div class='w3-col l2'>;*;TimeTo;;Bis (Zeit);text;;8;50
</div></div><div class='w3-row'><div class='w3-col l2'>;;PersonId;;Für wen?;text;validate:efaWeb_persons;18;50
</div><div class='w3-col l2'>;*;Contact;;Telefon für Rückfragen;text;;12;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;Reason;;Reservierungsgrund;textarea;;4;
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;<br>;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;Jetzt reservieren;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`,

		changeLogbook :
`tags;required;name;value;label;type;class;size;maxlength
<div class='w3-row'><div class='w3-col l1'>;;_no_input;;Ein beliebiges Fahrtenbuch kann gewählt werden,  \
   das dann für die weitere Sitzung gilt, also auch für das Eintragen von neuen Fahrten. Das Standardfahrtenbuch  \
   wird in der Konfiguration der Serveranwndung gesetzt.;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l1'>;*;logbookname;;Fahrtenbuch;text;;8;50
</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;<br>;;;;
</div></div><div class='w3-row'><div class='w3-col l1'>;;submit;Öffnen;;submit;formbutton;;
</div></div>;;_no_input;;;;;;`

}

var $_formNames = [];
var $_formDefs = {};
for (var key in $_formTemplates) {
	$_formDefs[key] = bToolbox.readCsvList($_formTemplates[key]);
	$_formNames.push(key);
}

var $_seatCntText = {
		1 : "Einer, Skiff",
		2 : "Zweier",
		3 : "Dreier, Zweier mit",
		4 : "Vierer, Dreier mit",
		5 : "Fünfer, Vierer mit",
		6 : "Sechser, Fünfer mit",
		9 : "Achter mit"
}

