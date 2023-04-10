/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2022 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

/**
 * A Modal "window" which is used for all dialogs of the application.
 */

var cModal = {

	// reverencing the modal objects
	modal : document.getElementById('cModal'),
	modal_content : document.getElementById('cModal_content'),
	modal_close : "<span class='closeModal'>&times;</span>",

	// after modal content has changed, events need to be rebound to buttons
	// etc.
	_updateModalCloseBind : function() {
		$('.closeModal').bind('click', function() {
			cModal.modal.style.display = "none";
		});
	},

	// after modal content has changed, events need to be rebound to buttons
	// etc.
	_updateModalSubmitBind : function(withHelp, formHandler) {
		$('#cFormInput-submit').bind('click', function() {
			cForm.readEntered(false);  // values will never end unquoted in SQL-statements 
			var formErrors = cForm.checkValidity();
			if (formErrors && formErrors.length > 1) {
				cModal.showForm(withHelp, formHandler);
			} else {
				cModal.modal.style.display = "none";
				// select the handling function by using the form name.
				formHandler[cForm.formName + "_done"]();
			}
		});
	},

	// bind an event to all modal buttons which are no form input.
	_updateModalButtonsBind : function() {
		$formbuttons = $('.formbutton');  // for debugging: do not inline statement.
		$formbuttons.click(function() {
			var thisElement = $(this);   // for debugging: do not inline statement.
			var id = thisElement.attr("id");
			if (!id)
				return;
			if (id.substring(0, 3).localeCompare("do-") != 0)
				return;
			doAction(id.substring(3));
		});
	},

	// show a help text, use it by <sup class='eventitem'
	// id='showhelptext_xyz'>&#9432</sup> (character should show as 🛈)
	showHelptext : function(reference) {
		var helptext_url = "../helpdocs/" + $_languageCode + "/" + reference + ".html";
		var helptext = "<h3>Oops</h3><p>Der Hilfetext zum Thema " + reference
				+ " konnte unter " + helptext_url
				+ " leider nicht gefunden werden.</p>";
		jQuery.get(helptext_url, function(data) {
			helptext = data;
			if ($_efaTypesHtml) {
				for (typeDef in $_efaTypesHtml) {
					helptext = helptext.replace("{" + typeDef + "}",
							$_efaTypesHtml[typeDef]);
				}
			}
			cModal.showHtml(helptext);
		});
	},

	// put an error into the modal 
	showException : function(e) {
		stacktrace = e.stack;
		stacktraceHtml = stacktrace.replace(/\n/g, "<br>");
		cModal.showHtml("Oops, da ist die Anwendung leider abgestürzt.<br>" + e.toString() + "<br><br>"  
				+ "Wenn sich das wiederholen lässt, "
				+ "kannst Du der Entwicklung helfen mit einem Hinweis auf den Fehler."
				+ "<br><br><b>Aufrufhistorie</b><br>" + stacktraceHtml);
	},
	
	// Show a form within the modal
	showForm : function(withHelp, formHandler) {
		var formHtml = this.modal_close + cForm.getHtml();
		if (withHelp && (cForm.getHelpHtml().length > 0)) formHtml += "<br><br>Hinweise:<br>" + cForm.getHelpHtml();
		$(this.modal_content).html(formHtml);
		// the modal content may now contain a new button, which needs binding.
		this._updateModalSubmitBind(withHelp, formHandler);
		this._updateModalCloseBind();
		this._updateModalButtonsBind();
		this.modal.style.display = "block";
	},

	// Display some html content within the modal. No buttons.
	showHtml : function(html) {
		this.modal_content = document.getElementById('cModal_content');
		$(this.modal_content).html(this.modal_close + html);
		// the modal content may now contain a new button, which needs binding.
		this._updateModalCloseBind();
		this._updateModalButtonsBind();
		this.modal.style.display = "block";
	}

};
