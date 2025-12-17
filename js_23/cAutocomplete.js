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
 * Provide an autocomplete list for entering boat names, crew names,
 * destinations or waters.
 * 
 * @param input
 *            the input field which triggers the autocomplete feature
 * @param value_options
 *            array of possible autocompletion values, e.g. all boat names
 * @param validator
 *            the validator to handle input changes. Must implement the
 *            validator.handleValue(input) and validator.validateEntry(input,
 *            listname) functions.
 * @param listname
 *            the name of the list to look for the guid.
 * @returns
 */
function autocomplete(input, value_options, validator, listname) {

	var currentFocus;
	$(input).addClass("guid-not-checked");
	$(input).attr("autocomplete", "off");

	function addAutocompleteItem(foundAt, val, i) {
		// create a DIV element for each matching element
		var acItem = document.createElement("DIV");
		// make the matching letters bold
		acItem.innerHTML = value_options[i].substr(0, foundAt) + "<b>"
				+ value_options[i].substr(foundAt, val.length) + "</b>";
		acItem.innerHTML += value_options[i].substr(foundAt + val.length);
		// insert a input field that will hold the current array item's value:
		acItem.innerHTML += "<input type='hidden' value='" + value_options[i]
				+ "'>";
		// execute a function on clicking the item value (DIV element)
		acItem.addEventListener("click", function(e) {
			// insert the value for the autocomplete text field:
			input.value = this.getElementsByTagName("input")[0].value;
			// close the list of autocompleted values, (or any other open lists
			// of autocompleted values
			closeAllLists();
			validator.validateEntry(input, listname);
			validator.handleValue(input);
			// trigger a change event for the Name-to-Id resolving in server
			// forms, if sFormHandler is defined
			try {
				sFormHandler.addChangedInput(input, input.value)
				sFormHandler.onInputChanged();
			} catch (ignored) {
			}
		});
		return acItem;
	}

	// execute a function when someone writes in the text field
	input.addEventListener("input", function(e) {
		var acList;
		var acItem;
		var val = this.value;
		// close any already open lists of autocompleted values
		closeAllLists();
		if (!val)
			return false;
		currentFocus = -1;
		validator.validateEntry(input, listname);
		// create a DIV element that will contain the items (values):
		acList = document.createElement("DIV");
		acListId = this.id + "autocomplete-list";
		acList.setAttribute("id", acListId);
		acList.setAttribute("class", "autocomplete-items");
		// append the DIV element as a child of the autocomplete container:
		this.parentNode.appendChild(acList);
		/* for each item in the array... */
		var countDisplayed = 0;
		for (var i = 0; i < value_options.length; i++) {
			// check if the item starts with the same letters as the text value
			var valUC = val.toUpperCase();
			var foundAt = value_options[i].toUpperCase().indexOf(
					val.toUpperCase());
			if (foundAt >= 0) {
				acItem = addAutocompleteItem(foundAt, val, i);
				if (countDisplayed < $_countDisplayMax)
					acList.appendChild(acItem);
				countDisplayed++;
			}
		}
	});

	// execute a function presses a key on the keyboard
	input.addEventListener("keydown", function(e) {
		var x = document.getElementById(this.id + "autocomplete-list");
		if (x)
			x = x.getElementsByTagName("div");
		if (e.keyCode == 40) {
			// arrow DOWN key is pressed, increase the currentFocus
			currentFocus++;
			// and and make the current item more visible
			addActive(x);
		} else if (e.keyCode == 38) { // up
			// arrow UP key is pressed, decrease the currentFocus
			currentFocus--;
			// and and make the current item more visible
			addActive(x);
		} else if ((e.keyCode == 13) || (e.keyCode == 9)) {
			// If the ENTER key is pressed, prevent the form from being
			// submitted, (original code as from the internet source code.)
			// Skip here, since the form is no real form, just a set of input
			// fields
			// e.preventDefault();
			if (currentFocus > -1) {
				// and simulate a click on the "active" item, if existing
				if (x) {
					validator.handleValue(x[currentFocus]);
					x[currentFocus].click();
				}
			} else {
				// or close the list of autocompleted values, (or any other open
				// lists of autocompleted values)
				validator.handleValue(input);
				closeAllLists();
			}
		}
	});

	// validate preset value
	validator.handleValue(input);
	validator.validateEntry(input, listname);

	// a function to classify an item as "active"
	function addActive(x) {
		if (!x)
			return false;
		/* start by removing the "active" class on all items: */
		removeActive(x);
		if (currentFocus >= x.length)
			currentFocus = 0;
		if (currentFocus < 0)
			currentFocus = (x.length - 1);
		/* add class "autocomplete-active": */
		x[currentFocus].classList.add("autocomplete-active");
	}

	// function to remove the "active" class from all autocomplete items
	function removeActive(x) {
		for (var i = 0; i < x.length; i++) {
			x[i].classList.remove("autocomplete-active");
		}
	}

	// close all autocomplete lists in the document, except the one passed as an
	// argument
	function closeAllLists(elmnt) {
		var x = document.getElementsByClassName("autocomplete-items");
		for (var i = 0; i < x.length; i++) {
			if (elmnt != x[i] && elmnt != input) {
				x[i].parentNode.removeChild(x[i]);
			}
		}
	}

	// execute a function when someone clicks in the document. This is also
	// called at key = 13, 9 (Enter, Tab)
	document.addEventListener("click", function(e) {
		closeAllLists(e.target);
	});

}