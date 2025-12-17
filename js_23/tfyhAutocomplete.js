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


class TfyhAutoComplete {

	static #addAutocompleteItem(input, validator, valid_options, tablename, foundAt, val, i) {
		// create a DIV element for each matching element
		let acItem = document.createElement("DIV");
		// make the matching letters bold
		acItem.innerHTML = valid_options[i].substr(0, foundAt) + "<b>"
				+ valid_options[i].substr(foundAt, val.length) + "</b>";
		acItem.innerHTML += valid_options[i].substr(foundAt + val.length);
		// insert a input field that will hold the current array item's value:
		acItem.innerHTML += "<input type='hidden' value='" + valid_options[i]
				+ "'>";
		// execute a function on clicking the item value (DIV element)
		acItem.addEventListener("click", function(e) {
			// insert the value for the autocomplete text field:
			input.value = this.getElementsByTagName("input")[0].value;
			// close the list of autocompleted values, (or any other open lists
			// of autocompleted values
			TfyhAutoComplete.#closeAllLists();
			validator.validateEntry(input, tablename);
			validator.handleValue(input, tablename);
		});
		return acItem;
	}

	// a function to classify an item as "active"
	static #addActive(x, currentFocus) {
		if (!x)
			return false;
		/* start by removing the "active" class on all items: */
		TfyhAutoComplete.#removeActive(x);
		if (currentFocus >= x.length)
			currentFocus = 0;
		if (currentFocus < 0)
			currentFocus = (x.length - 1);
		/* add class "autocomplete-active": */
		x[currentFocus].classList.add("autocomplete-active");
	}

	// function to remove the "active" class from all autocomplete items
	static #removeActive(x) {
		for (let i = 0; i < x.length; i++) {
			x[i].classList.remove("autocomplete-active");
		}
	}

	// close all autocomplete lists in the document, except the one passed as an
	// argument
	static #closeAllLists(input, elmnt) {
		let x = document.getElementsByClassName("autocomplete-items");
		for (let i = 0; i < x.length; i++) {
			if (elmnt != x[i] && elmnt != input) {
				x[i].parentNode.removeChild(x[i]);
			}
		}
	}

	/**
	 * Provide an autocomplete list for entering boat names, crew names,
	 * destinations or waters.
	 * 
	 * @param input
	 *            the input field which triggers the autocomplete feature
	 * @param validator
	 *            the validator to handle input changes. Must implement the
	 *            validator.handleValue(input, tablename) and
	 *            validator.validateEntry(input, tablename) functions.
	 * @param valid_options
	 *            array of possible autocompletion values, e.g. all valid boat
	 *            names
	 * @param tablename
	 *            the name of the table this options list is taken from
	 * @param formHandler
	 *            the name of the form handler. Must provide a function
	 *            submitForm()
	 * @returns
	 */
	static set(input, validator, valid_options, tablename, formHandler) {

		let currentFocus;
		$(input).addClass("uuid-not-checked");
		$(input).addClass("no-submit-on-enter");
		$(input).attr("autocomplete", "off");

		// execute a function when someone writes in the text field
		input.addEventListener("input", function(e) {
			// close any already open lists of autocompleted values
			TfyhAutoComplete.#closeAllLists(this);
			if (!this.value)
				return false;
			currentFocus = -1;
			validator.validateEntry(input, tablename);
			// create a DIV element that will contain the items (values):
			let acList = document.createElement("DIV");
			let acListId = this.id + "autocomplete-list";
			acList.setAttribute("id", acListId);
			acList.setAttribute("class", "autocomplete-items");
			// append the DIV element as a child of the autocomplete container:
			this.parentNode.appendChild(acList);
			/* for each item in the array... */
			let countDisplayed = 0;
			for (let i = 0; i < valid_options.length; i++) {
				// check if the item starts with the same letters as the text
				// value
				let foundAt = valid_options[i].toUpperCase().indexOf(
						this.value.toUpperCase());
				if (foundAt === 0) {
					let acItem = TfyhAutoComplete.#addAutocompleteItem(
							input, validator, valid_options, tablename, 
							foundAt, this.value, i);
					if (countDisplayed < 10)
						acList.appendChild(acItem);
					countDisplayed++;
				}
			}
		});

		// execute a function presses a key on the keyboard
		input.addEventListener("keydown", function(e) {
			let x = document.getElementById(this.id + "autocomplete-list");
			if (x)
				x = x.getElementsByTagName("div");
			if (e.keyCode == 40) {
				// arrow DOWN key is pressed, increase the currentFocus
				currentFocus++;
				// and and make the current item more visible
				TfyhAutoComplete.#addActive(x, currentFocus);
			} else if (e.keyCode == 38) { // up
				// arrow UP key is pressed, decrease the currentFocus
				currentFocus--;
				// and and make the current item more visible
				TfyhAutoComplete.#addActive(x, currentFocus);
			} else if ((e.keyCode == 13) || (e.keyCode == 9)) {
				// If the ENTER key is pressed, prevent the form from being
				// submitted.
				// e.preventDefault();
				if ((currentFocus > -1) && x) {
					// and simulate a click on the "active" item, if existing
					validator.handleValue(x[currentFocus], tablename);
					x[currentFocus].click();
				} else {
					// or close the list of autocompleted values, (or any other
					// open lists of autocompleted values)
					validator.handleValue(input, tablename);
					TfyhAutoComplete.#closeAllLists(this);
					// submit the form on ENTER.
					if (e.keyCode == 13)
						formHandler.submitForm();
				}
			}
		});

		// validate preset value
		validator.handleValue(input, tablename);
		validator.validateEntry(input, tablename);

		// execute a function when someone clicks in the document. This is also
		// called at key = 13, 9 (Enter, Tab)
		document.addEventListener("click", function(e) {
			TfyhAutoComplete.#closeAllLists(input, e.target);
		});

	}

}