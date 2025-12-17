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
		var helptext = "<h3>" + _("4vmcDx|Oops") + "</h3><p>" 
				+ _("0krLhr|Unfortunately, the help ...", 
						reference, helptext_url) + "</p>";
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
		cModal.showHtml(_("5V5NzT| ** Oops, the applicatio...", 
		e.toString()) + stacktraceHtml);
	},
	
	// Show a form within the modal
	showForm : function(withHelp, formHandler) {
		var formHtml = this.modal_close + cForm.getHtml();
		if (withHelp && (cForm.getHelpHtml().length > 0)) formHtml += "<br><br>" + _("WlrZp9|Notes:") + "<br>" + cForm.getHelpHtml();
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
