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

// show a reference record, used in ../pages/db_audit.php
function _showRecord(reference) {
	var tablename = reference[0];
	var ecrid = reference[1];
	var getRequest = new XMLHttpRequest();
	var recordHtml = _("6E16M8| ** Display of a data re...", reference[0],
			tablename, ecrid);
	// provide the callback for a response received
	getRequest.onload = function() {
		if (getRequest.status == 500)
			recordHtml += "<p>" + _("XFpiuh|The server returns a gen...")
					+ "</p>";
		else
			recordHtml += "<p>" + getRequest.response + "</p>";
		cModal.showHtml(recordHtml);
	};
	// provide the callback for any error.
	getRequest.onerror = function() {
		recordHtml += "<p>" + _("e5oX3g|Error when requesting da...") + "</p>";
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
	// $('[id^=versionized]') are all ids starting with "versionized", see 
	// https://stackoverflow.com/questions/1206739/find-all-elements-on-a-page-whose-element-id-contains-a-certain-text-using-jquer
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

// redo text display in forms after labnguage resource file was loaded.
function onI18nLoaded() {
	try {
		if (jformIsDefined) {
			sFormHandler.editRecord_prepare();
			sFormHandler.add_new_prepare();
			sFormHandler.versionized_prepare();
			sFormHandler.hide_unused_boat_variants();
			sFormHandler.set_color_probe();
		}
	} catch (e) {
		cModal.showException(e);
	}
}
var jformIsDefined = false;

/**
 * initialization procedures to be performed when document was loaded.
 */
$(document).ready(function() {
	// initialize lookup in forms, if this is a form.
	i18n.init(onI18nLoaded);
	cLists.setDefinitions(efaListDefs);
	jformIsDefined = true;
	try {
		jformIsDefined = (formLookupsNeeded.length >= 0); // this variable
															// will be set by
															// the PHP page.
		// If it is not defined, this will cause an exception, which is then
		// used to know
		// whether a form was defined or not.
	} catch (e) {
		jformIsDefined = false;
	}
	if (jformIsDefined)
		try {
			sFormHandler.initResolver();
		} catch (e) {
			cModal.showException(e);
		}

	_replaceTimestampInputs('LastModified');
	_replaceTimestampInputs('ValidFrom');
	_bindEvents();
});
