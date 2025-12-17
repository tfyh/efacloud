/**
 *
 *       the tools-for-your-hobby framework
 *       ----------------------------------
 *       https://www.tfyh.org
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

class TfyhModal {

	// reverencing the modal objects
	#modal;
	#modal_tabs = "";
	#modal_content = "";
	#modal_close = '<span class="closeModal"> &times; </span>';
	#modal_previous = '<span class="previousModal">	&#x25C2; </span>';
	#modal_tabRow = '<div class="w3-row">{tabs}</div>';
	#modal_tab = '<div class="w3-col l{count} formtab" id="{id}">{tab}</div>';
	#modal_actveTab = '<div class="w3-col l{count} formtab formtab-active" id="{id}">{tab}</div>';
	#shiftDown = false;
	#ctrlDown = false;
	#altDown = false;
	
	#goBack;
	
	constructor() {
		this.#modal = document.getElementById('tfyhModal');
		this.#modal_content = document.getElementById('tfyhModal-content');
	}

	/**
	 * after modal content has changed, events need to be rebound to buttons
	 * etc.
	 */
	#updateModalCloseBind () {
		let that = this;
		$('.closeModal').bind('click', function() {
			if (that.#goBack) 
				that.#goBack(true);
			that.#modal.style.display = "none";
			that.setTabs(); // clear tabs, if this was not a tab clicked.
		});
		$('.previousModal').bind('click', function() {
			if (that.#goBack) 
				that.#goBack(false);
		});
	}

	/**
	 * submit the form. This will be triggerd by click or enter.
	 */
	#submitForm(withHelp, formHandler, form) {
		let formErrors = form.evaluate(false);  // values will never end up
												// unquoted in SQL-statements;
		if (formErrors && formErrors.length > 1) {
			if (form.redo) 
				form.redo();
			else
				this.showForm(withHelp, formHandler, form, formErrors);
		} else {
			this.#modal.style.display = "none";
			this.setTabs(); // clear tabs, if this was not a tab clicked.
			// select the handling function by using the form name.
			formHandler[form.getName() + "_done"]();
		}
	}
	
	/**
	 * bind an event to all modal buttons and tabs in a dialog use case.
	 */
	#updateModalButtonsBind () {
		let formbuttons = $('.formbutton:not(.on-main), .formtab');
		let that = this;
		formbuttons.click(function() {
			let id = $(this).attr("id");
			if (!id)
				return;
			window.handleEvent(id);
			if ($(this).hasClass("formtab"))  {
				$('.formtab').removeClass('formtab-active');
				$('#' + id).addClass("formtab-active");
				$('#' + id).addClass("display-bold");
			} else
				that.setTabs(); // clear tabs, if this was not a tab clicked.
		});
	}

	/**
	 * Bind the submit function to the #cFormInput-submit button and unbind any
	 * previous binding which applied to this button.
	 */
	#updateModalSubmitBind (withHelp, formHandler, form) {
		let that = this;
		$('#cFormInput-submit').unbind('click').bind('click', function() {
			that.#submitForm(withHelp, formHandler, form);
		});
	}

	/**
	 * add the action to exit the form when enter is pressed.
	 */
	#updateModalSpecialKeyBind (withHelp, formHandler, form) {
		let forminputs = $("input[id^=cFormInput]")
		let that = this;
		forminputs.on("keyup", function(event) {
			let input = event.currentTarget;
			if (event.which == 16)
				that.#shiftDown = false;
			else if (event.which == 17)
				that.#ctrlDown = false;
			else if (event.which == 18)
				that.#altDown = false;
			if ((event.which == 13) && !that.#shiftDown 
					&& !that.#ctrlDown && !that.#altDown
					&& !$(input).hasClass("no-submit-on-enter")) {
				that.#submitForm(withHelp, formHandler, form);
			}
		});
		forminputs.on("keydown", function(event) {
			if (event.which == 16)
				that.#shiftDown = true;
			else if (event.which == 17)
				that.#ctrlDown = true;
			else if (event.which == 18)
				that.#altDown = true;
		});
	}

	/**
	 * Display an exception
	 */
	showException (e) {
		let stacktrace = e.stack;
		let stacktraceHtml = stacktrace.replace(/\n/g, "<br>");
		this.showHtml(_("F3yUPA|Oops, unfortunately the ...") + "<br>" + e.toString() + "<br><br>"  
				+ "If this occurs again you can help the development team by notifying this error."
				+ "<br><br><b>" + _("CGKrQ9|Stacktrace") + ":</b><br>" + stacktraceHtml);
	}
	
	/**
	 * Display a form within the modal
	 */
	showForm (withHelp, formHandler, form, previousErrors) {
		let formHtml = this.#modal_close + this.#modal_tabs + form.getHtml(previousErrors);
		if (withHelp && (form.getHelpHtml().length > 0)) formHtml += "<br><br>" + _("EuguaU|Please note") + ":<br>" + form.getHelpHtml();
		$(this.#modal_content).html(formHtml);
		// the modal content may now contain a new button, which needs binding.
		this.#updateModalButtonsBind ();
		this.#updateModalSubmitBind(withHelp, formHandler, form);
		this.#updateModalSpecialKeyBind(withHelp, formHandler, form);
		this.#updateModalCloseBind();
		this.#modal.style.display = "block";
	}
	
	/**
	 * set tabs for the modal. Up to four tabs are allaowed.
	 */
	setTabs (tabs, active) {
		if (!tabs)
			this.#modal_tabs = "";
		else {
			let tabsHtml = "";
			for (let i = 0; i < tabs.length; i++) {
				let template = (i == (active - 1)) ? this.#modal_actveTab : this.#modal_tab;
				tabsHtml += template.replace("{tab}", tabs[i].text).replace("{id}", tabs[i].id).replace("{count}", tabs.length);
			}
			this.#modal_tabs = this.#modal_tabRow.replace("{tabs}", tabsHtml);
		}
	}
	
	/**
	 * hide the modal
	 */
	hide() {
		this.#modal.style.display = "none";
	}

	/**
	 * Display some html content within the modal. No buttons.
	 */
	showHtml (html, goBack) {
		this.#goBack = goBack; // a callback to a function, if previous is clicked.
		$(this.#modal_content).html(this.#modal_close + ((goBack) ? this.#modal_previous : "") + this.#modal_tabs + html);
		// the modal content may now contain a new button, which needs binding.
		this.#updateModalButtonsBind();
		this.#updateModalCloseBind();
		this.#modal.style.display = "block";
	}

	/**
	 * Display some html content which reflects a processing progress and
	 * trigger the next step.
	 */
	showProgress (url, doStep = 1, chunk = 0, from = 0) {
		let that = this;
		let urlPlus = url + "&doStep=" + doStep;
		if (chunk > 0)
			urlPlus += "&chunk=" + chunk + "&from=" + from;
		$.get(urlPlus, function(data) {
			let parts = data.split(";", 2);
			// split is different to Java.String.split. The last element is only
			// returned up to the first separator hit within.
			let doneStep = parseInt(parts[0]);
			let completed = parseInt(parts[1]);
			let progressText = data.substring(parts[0].length + parts[1].length + 2);
			if (isNaN(doneStep) || isNaN(completed)) {
				$(that.#modal_content).html(that.#modal_close + data);
				that.#modal.style.display = "block";
			} else if (progressText.startsWith("idle")) {
				that.#modal.style.display = "none";
				window.location.href = url + "&doStep=0";
			} else {
				$(that.#modal_content).html(that.#modal_close + progressText);
				that.#updateModalCloseBind();
				that.#modal.style.display = "block";
				that.#modal.scrollTop = that.#modal.scrollHeight
				doStep = (completed == 0) ? doneStep + 1 : doneStep;
				if (completed == 0) {
					doStep = doneStep + 1;
					from = 0;
				} else {
					doStep = doneStep;
					from += chunk;
				} 
				that.showProgress (url, doStep, chunk, from);
			}
		})
		.fail(function(data) { 
			that.showHtml("<h3>" + _("kffirf|Server error") + "</h3>" + data.responseText);
		});
	}

}

