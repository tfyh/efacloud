
// Quelle: https://www.w3schools.com/howto/tryit.asp?filename=tryhow_js_autocomplete
/**
 * Provide an autocomplete list for entering boat names, crew names, destinations or waters
 * @param input the input field which triggers the auocomplete feature
 * @param value_options the options to use for autocompletion, e.g. all boat names
 * @param listname the name of the list to look for the guid.
 * @returns
 */
function autocomplete(input, value_options, listname) {
	/*the autocomplete function takes two arguments,
	the text field element and an array of possible autocompleted values:*/

	var currentFocus;
	$(input).addClass("guid-not-checked");
	
	/*execute a function when someone writes in the text field:*/
	input.addEventListener("input", function(e) {
		var acList;
		var acItem;
		var val = this.value;
		/*close any already open lists of autocompleted values*/
		closeAllLists();
		if (!val) { return false;}
		currentFocus = -1;
		validateEntry(input, listname);
		/*create a DIV element that will contain the items (values):*/
		acList = document.createElement("DIV");
		acList.setAttribute("id", this.id + "autocomplete-list");
		acList.setAttribute("class", "autocomplete-items");
		/*append the DIV element as a child of the autocomplete container:*/
		this.parentNode.appendChild(acList);
		/*for each item in the array...*/
		var countDisplayed = 0;
		for (i = 0; i < value_options.length; i++) {
			/*check if the item starts with the same letters as the text field value:*/
			var valUC = val.toUpperCase();
			var foundAt = value_options[i].toUpperCase().indexOf(val.toUpperCase());
			/*check if the item starts with the same letters as the text field value:*/
			if (foundAt >= 0) {
				/*create a DIV element for each matching element:*/
				acItem = document.createElement("DIV");
				/*make the matching letters bold:*/
				acItem.innerHTML = value_options[i].substr(0, foundAt) + "<b>" + value_options[i].substr(foundAt, val.length) + "</b>";
				acItem.innerHTML += value_options[i].substr(foundAt + val.length);
				/*insert a input field that will hold the current array item's value:*/
				acItem.innerHTML += "<input type='hidden' value='" + value_options[i] + "'>";
				/*execute a function when someone clicks on the item value (DIV element):*/
				acItem.addEventListener("click", function(e) {
					/*insert the value for the autocomplete text field:*/
					input.value = this.getElementsByTagName("input")[0].value;
					/*close the list of autocompleted values, (or any other open lists of autocompleted values:*/
					closeAllLists();
					validateEntry(input, listname);
					handleValue(input);
				});
				if (countDisplayed < $_countDisplayMax) acList.appendChild(acItem);
				countDisplayed++;
			}
		}
	});
  
	/*execute a function presses a key on the keyboard:*/
	input.addEventListener("keydown", function(e) {
		var x = document.getElementById(this.id + "autocomplete-list");
		if (x) x = x.getElementsByTagName("div");
		if (e.keyCode == 40) {
			/*If the arrow DOWN key is pressed, increase the currentFocus variable:*/
			currentFocus++;
			/*and and make the current item more visible:*/
			addActive(x);
		} else if (e.keyCode == 38) { //up
			/*If the arrow UP key is pressed, decrease the currentFocus variable:*/
			currentFocus--;
			/*and and make the current item more visible:*/
			addActive(x);
		} else if ((e.keyCode == 13) || (e.keyCode == 9)) {
			/* If the ENTER key is pressed, prevent the form from being submitted, */
			/* not here, since there is no real form, just a set of input fields */
			// e.preventDefault();
			if (currentFocus > -1) {
				/*and simulate a click on the "active" item, if existing:*/
				if (x) {
					handleValue(x[currentFocus]);
					x[currentFocus].click();
				}
			} else {
				/* or close the list of autocompleted values, (or any other open lists of autocompleted values):*/
				handleValue(input);
				closeAllLists();
			}
		}
	});
	
	// validate preset value
	handleValue(input);
	validateEntry(input, listname);
  
	/*a function to classify an item as "active":*/
	function addActive(x) {
		if (!x) return false;
		/*start by removing the "active" class on all items:*/
		removeActive(x);
		if (currentFocus >= x.length) currentFocus = 0;
		if (currentFocus < 0) currentFocus = (x.length - 1);
		/*add class "autocomplete-active":*/
		x[currentFocus].classList.add("autocomplete-active");
	}
  
	/*a function to remove the "active" class from all autocomplete items:*/
	function removeActive(x) {
		for (var i = 0; i < x.length; i++) {
			x[i].classList.remove("autocomplete-active");
		}
	}

	/* changes the valid bar for the input field depending on the input validity */
	function validateEntry(inputToValidate, listname) {
		if (!inputToValidate.value)
			return;
		var invalidFrom = bLists.validateName(inputToValidate.value, listname);
		if (invalidFrom == 0)
			$(inputToValidate).removeClass("guid-valid").removeClass("guid-off-period").addClass("guid-not-found").removeClass("guid-not-checked");
		else if (invalidFrom < Math.floor(Date.now()))
			$(inputToValidate).removeClass("guid-valid").removeClass("guid-not-found").addClass("guid-off-period").removeClass("guid-not-checked");
		else 
			$(inputToValidate).removeClass("guid-off-period").removeClass("guid-not-found").addClass("guid-valid").removeClass("guid-not-checked");
	}

	/* close all autocomplete lists in the document, except the one passed as an argument: */
	function closeAllLists(elmnt) {
		var x = document.getElementsByClassName("autocomplete-items");
		for (var i = 0; i < x.length; i++) {
			if (elmnt != x[i] && elmnt != input) {
				x[i].parentNode.removeChild(x[i]);
			}
		}
	}
	
	/* for some trip entries form values need adaptation before they are submitted.*/
	function handleValue(triggerInput) {
		if (!triggerInput.name)
			return;
		/* special case: destination selected. Fill distance and water */
		if (triggerInput.name.localeCompare("DestinationId") == 0) {
			var destinationId = bLists.names.efaWeb_destinations_names[input.value];
			if (destinationId) {
				var destination = bLists.lists.efaWeb_destinations[bLists.indices.efaWeb_destinations_guids[destinationId]];
				var distance = destination["Distance"].trim();  // keep unit ( km) as part of String.
				var watersIdList = destination["WatersIdList"].split(/;/g);
				var watersNameList = "";
				watersIdList.forEach(function(watersId) {
					var water = bLists.lists.efaWeb_waters[bLists.indices.efaWeb_waters_guids[watersId]];
					watersNameList += (water) ? water.Name + ";" : ";";
				});
				watersNameList = watersNameList.substring(0, watersNameList.length -1);
				$('#bFormInput-Distance').val(distance);
				var inputWatersIdList = $('#bFormInput-WatersIdList')[0]; 
				$('#bFormInput-WatersIdList').val(watersNameList);
				validateEntry(inputWatersIdList, "efaWeb_waters");
			}
		};
		/* special case: boat selected. Disable irrelavant seats and show status message */
		if (triggerInput.name.localeCompare("BoatId") == 0) {
			var boatId = bLists.names.efaWeb_boats_names[input.value];
			var boat = bLists.lists.efaWeb_boats[bLists.indices.efaWeb_boats_guids[boatId]];
			var variant = bBoat.getVariantIndexForName(boat, input.value);
			if (!bBoat.coxed[variant]) 
				$('#div-CoxId').hide();
			else 
				$('#div-CoxId').show();
			for (var i = 0; i < bBoat.seatsCnt[variant]; i++)
				$('#div-Crew' + (i+1) + 'Id').show();
			for (i = bBoat.seatsCnt[variant]; i < 8; i++)
				$('#div-Crew' + (i+1) + 'Id').hide();
			var boatstatus = bLists.lists.efaWeb_boatstatus[bLists.indices.efaWeb_boatstatus_guids[boatId]];
			var boatstatusToUse = bBoatstatus.statusToUse(boatstatus);
			var statusInfo = "";
			if (boatstatusToUse.localeCompare("ONTHEWATER") == 0) statusInfo = "<b>Das Boot ist auf dem Wasser.</b>";
			else if (boatstatusToUse.localeCompare("NOTAVAILABLE") == 0) statusInfo = "<b>Das Boot ist nicht verfügbar.</b>";
			else if (boatstatusToUse.localeCompare("HIDE") == 0) statusInfo = "<b>Das Boot ist nicht zu verwenden.</b>";
			var openDamages = bDamage.getOpenDamagesFor(boatId);
			$('#startTrip-boatInfo').html(statusInfo + "<br>" + openDamages);
		}
	}
  
	/*execute a function when someone clicks in the document. This is also called at key = 13, 9 (Enter, Tab) */
	document.addEventListener("click", function (e) {
		closeAllLists(e.target);
	});
  
}