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
 * A Dialog "window" which is used for all dialogs superseeding the modal. A sort of simple lodal class copy
 */

class TfyhDialog {

	// reverencing the modal objects
	#dialog;
	#dialog_content = "";
	#responseButtons = { 
			3 : "<div class='w3-container style='margin-bottom:5px;'><div class='w3-row' style='overflow:visible'>" 
				+ "<div class='w3-col l3' style='text-align:center;'><span class='formbutton dialogButton' id='buttonLeft'>{0}</span></div>"
				+ "<div class='w3-col l3' style='text-align:center;'><span class='formbutton dialogButton' id='buttonCenter'>{1}</span></div>"
				+ "<div class='w3-col l3' style='text-align:center;'><span class='formbutton dialogButton' id='buttonRight'>{2}</span></div>"
				+ "</div></div>",
				2 : "<div class='w3-container style='margin-bottom:5px;'><div class='w3-row' style='overflow:visible'>" 
					+ "<div class='w3-col l2' style='text-align:center;'><span class='formbutton dialogButton' id='buttonLeft'>{0}</span></div>"
					+ "<div class='w3-col l2' style='text-align:center;'><span class='formbutton dialogButton' id='buttonRight'>{1}</span></div>"
					+ "</div></div>",
				1 : "<div class='w3-container style='margin-bottom:5px;'><div class='w3-row' style='overflow:visible'>" 
					+ "<div class='w3-col l1' style='text-align:center;'><span class='formbutton dialogButton' id='buttonLeft'>{0}</span></div>"
					+ "</div></div>"
	}
	dialogInputs = {};
	
	constructor() {
		this.#dialog = document.getElementById('tfyhDialog');
		this.#dialog_content = document.getElementById('tfyhDialog-content');
	}

	/**
	 * bind an event to all modal buttons and tabs in a dialog use case.
	 */
	#updateDialogButtonsBind (eventHandler, eventProperty) {
		let formbuttons = $('.dialogButton');
		let that = this;
		formbuttons.click(function() {
			// collect inputs
			that.dialogInputs = {};
			$(".dialoginput").each(function(input) {
				that.dialogInputs[$(this).attr("id").replace("cFormInput-", "")] = $(this).val(); 
			});
			let id = $(this).attr("id");
			if (typeof eventHandler === 'function') 
				eventHandler(id, eventProperty);
			that.#dialog.style.display = "none";
		});
	}

	/**
	 * Display some html content within the modal. No buttons.
	 */
	showHtml (html, buttonTexts, eventHandler, eventProperty) {
		let buttonsHtml = this.#responseButtons[buttonTexts.length];
		for (let i = 0; i < buttonTexts.length; i++)
			buttonsHtml = buttonsHtml.replace("{" + i + "}", buttonTexts[i]);
		$(this.#dialog_content).html("<div class='w3-container'>" + html + "</div>" + buttonsHtml);
		this.#updateDialogButtonsBind(eventHandler, eventProperty);
		this.#dialog.style.display = "block";
	}
}

