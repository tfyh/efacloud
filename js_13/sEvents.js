/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2022 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

// show a reference record, used in ../pages/db_audit.php
function _showRecord(reference) {
	var tablename = reference[0];
	var ecrid = reference[1];
	var getRequest = new XMLHttpRequest();
	var recordHtml = "<h4>Anzeige eines Datensatzes aus der Tabelle "
			+ reference[0]
			+ "</h4><p>Der Datensatz wird so, "
			+ "wie er in der Datenbank hinterlegt ist, angezeigt.<br>Für mehr Information und Anpassen des Datensatzes: "
			+ "<b><a target='_blank' href='../pages/view_record.php?table="
			+ tablename + "&ecrid=" + ecrid
			+ "'>Datensatz in neuem Tab anzeigen</a></b></p>";
	// provide the callback for a response received
	getRequest.onload = function() {
		if (getRequest.status == 500)
			recordHtml += "<p>Der Server gibt einen allgemeinen Fehlercode zurück (500).</p>";
		else
			recordHtml += "<p>" + getRequest.response + "</p>";
		cModal.showHtml(recordHtml);
	};
	// provide the callback for any error.
	getRequest.onerror = function() {
		recordHtml += "<p>Fehler bei der Abfrage der Daten vom Server.</p>";
		cModal.showHtml(recordHtml);
	};
	// send the GET request
	getRequest.open('GET', "../pages/getrecord.php?table=" + tablename
			+ "&ecrid=" + ecrid, true);
	getRequest.send(null);
}

// will bind all event items by selecting all .menuitems with #do-...
function _bindEvents() {
	var eventItems = $('.eventitem'); // for debugging: do not inline
	// statement.
	eventItems.unbind();
	eventItems.click(function() {
		var thisElement = $(this); // for debugging: do not inline statement.
		var id = thisElement.attr("id");
		if (!id)
			return;
		if (id.indexOf("viewrecord_") == 0)
			_showRecord(id.replace("viewrecord_", "").split("_"));
		if (id.indexOf("showhelptext_") == 0)
			cModal.showHelptext(id.replace("showhelptext_", "").split("_"));
	});
	var versionized = $('[id^=versionized]');
	versionized.unbind();
	versionized.on("keyup change", function() {
		sFormHandler.versionized_prepare();
	});
}

// replace a timestamp by a date String.
function _replaceTimestampInputs(inputField) {
	var lastModifiedInputs = $('input[name=' + inputField + ']');
	for (i in lastModifiedInputs) {
		var lastModifiedDate = new Date(parseInt(lastModifiedInputs[i].value));
		var lastModifiedString = lastModifiedDate.toLocaleString();
		var lastModifiedString = lastModifiedDate.toLocaleString();
		lastModifiedInputs[i].value = lastModifiedString;
	}
}

/**
 * initialization procedures to be performed when document was loaded.
 */
$(document).ready(function() {
	// initialize lookup in forms, if this is a form.
	cLists.setDefinitions(efaListDefs);
	var jformIsDefined = true;
	try {
		jformIsDefined = (formLookupsNeeded.length >= 0); // this variable will be set by the PHP page.
		// If it is not defined, this will cause an exception, which is then used to know
		// whether a form was defined or not.
	} catch (e) {
		jformIsDefined = false;
	}
	if (jformIsDefined)
		try {
			sFormHandler.initResolver();
			sFormHandler.editRecord_prepare();
			sFormHandler.add_new_prepare();
			sFormHandler.versionized_prepare();
			sFormHandler.hide_unused_boat_variants();
			sFormHandler.set_color_probe();
		} catch (e) {
			cModal.showException(e);
		}
	_replaceTimestampInputs('LastModified');
	_replaceTimestampInputs('ValidFrom');
	_bindEvents();
});
