/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2022 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

/**
 * Validate the input into a form field upon its change.
 */

var efaInputValidator = {

	/*
	 * If the destination changes, distance and waters need to be updated.
	 */
	_handleValueEfaDestination : function(input) {
		var destinationId = cLists.names.efaWeb_destinations_names[input.value];
		if (destinationId) {
			var destination = cLists.lists.efaWeb_destinations[cLists.indices.efaWeb_destinations_guids[destinationId]];
			var distance = destination["Distance"].trim(); // keep unit (
			// km) as part
			// of String.
			var watersIdList = destination["WatersIdList"].split(/;/g);
			var watersNameList = "";
			watersIdList
					.forEach(function(watersId) {
						var water = cLists.lists.efaWeb_waters[cLists.indices.efaWeb_waters_guids[watersId]];
						watersNameList += (water) ? water.Name + ";" : ";";
					});
			watersNameList = watersNameList.substring(0,
					watersNameList.length - 1);
			var inputDistance = $('#cFormInput-Distance')[0];
			inputDistance.value = distance;
			var inputWatersIdList = $('#cFormInput-WatersIdList')[0];
			inputWatersIdList.value = watersNameList;
			efaInputValidator.validateEntry(inputWatersIdList, "efaWeb_waters");
			// trigger a change event for Destination to waters & distance
			// resolving in server forms, if sFormHandler is defined
			try {
				sFormHandler
						.addChangedInput(inputDistance, inputDistance.value);
				sFormHandler.addChangedInput(inputWatersIdList,
						inputWatersIdList.value);
				sFormHandler.onInputChanged();
			} catch (ignored) {
			}
		}
	},
	
	_handleValueEfaBoatId : function(input) {
		const boatIdFields = ["startSession-BoatId", "lateEntry-BoatId", "endSession-BoatId" ];
		var triggerInputParent = $(input).parent();
		var triggerInputParentId = triggerInputParent.attr("id");
		var usesVariant = triggerInputParentId
				&& (boatIdFields.includes(triggerInputParentId));
		var boatId = (usesVariant) ? cLists.names.efaweb_virtual_boatVariants_names[input.value]
				: cLists.names.efaWeb_boats_names[input.value];
		var boat = cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[boatId]];
		// If the Boat name does not resolve to a valid boat, the variant
		// will be -1.
		var variant = oBoat.getVariantIndexForName(boat, input.value);
		var variantInput = $('input[name="BoatVariant"]');
		if (variantInput) {
			var prevVariant = parseInt($(variantInput).val()) - 1;
			if (prevVariant != variant) {
				$(variantInput).val(variant + 1);
				// if the context is efaWeb, sFormHandler does not exist.
				try {
					sFormHandler.addChangedInput(variantInput, variant + 1);
				} catch (ignored) {
				}
			}
		}
		if (!oBoat.coxed[variant] && (variant >= 0))
			$('#div-CoxId').hide();
		else
			$('#div-CoxId').show();
		var seatsCnt = (variant == -1) ? 8 : oBoat.seatsCnt[variant]
		for (var i = 0; i < seatsCnt; i++)
			$('#div-Crew' + (i + 1) + 'Id').show();
		for (i = seatsCnt; i < 24; i++)
			$('#div-Crew' + (i + 1) + 'Id').hide();
		if (cLists.indices.efaWeb_boatstatus_guids) {
			var boatstatus = cLists.lists.efaWeb_boatstatus[cLists.indices.efaWeb_boatstatus_guids[boatId]];
			var boatstatusToUse = oBoatstatus.statusToUse(boatstatus);
			var statusInfo = "";
			if (!boatstatus)
				statusInfo = "";
			else if (boatstatusToUse.localeCompare("ONTHEWATER") == 0)
				statusInfo = "<b>Das Boot ist auf dem Wasser.</b>";
			else if (boatstatusToUse.localeCompare("NOTAVAILABLE") == 0)
				statusInfo = "<b>Das Boot ist nicht verfügbar.</b>";
			else if (boatstatusToUse.localeCompare("HIDE") == 0)
				statusInfo = "<b>Das Boot ist nicht zu verwenden.</b>";
			var openDamages = oDamage.getOpenDamagesFor(boatId);
			$('#startSession-boatInfo').html(
					statusInfo + "<br>" + openDamages);
		}
	},
	
	handleValue : function(input) {
		if (!input.name)
			return;
		/* trigger an input cange for virtual forms in server side edit */
		/* special case: destination selected. Fill distance and water */
		if (input.name.localeCompare("DestinationId") == 0) 
			efaInputValidator._handleValueEfaDestination(input);
		/*
		 * special case: boat selected. Disable irrelavant seats and show status
		 * message
		 */
		else if (input.name.localeCompare("BoatId") == 0) 
			efaInputValidator._handleValueEfaBoatId(input);
	},

	// check the period based validity and add the red-amber-green input field's side bar.
	validateEntry : function(input, listname) {
		if (!input.value)
			return;
		var invalidFrom = cLists.invalidFromForNames(input.value,
				listname);
		if (invalidFrom == 0)
			$(input).removeClass("guid-valid").removeClass(
					"guid-off-period").addClass("guid-not-found").removeClass(
					"guid-not-checked");
		else if (invalidFrom < Math.floor(Date.now()))
			$(input).removeClass("guid-valid").removeClass(
					"guid-not-found").addClass("guid-off-period").removeClass(
					"guid-not-checked");
		else
			$(input).removeClass("guid-off-period").removeClass(
					"guid-not-found").addClass("guid-valid").removeClass(
					"guid-not-checked");
	}
}