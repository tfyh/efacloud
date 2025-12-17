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
 * Handle the display of forms for efaCloud Server data manipulation.
 */

var sFormHandler = {

	resolve : {},
	checkBoxStates : {},

	initResolver : function() {
		resolve = {};
		if (formLookupsNeeded && formTablename) {
			listsUsed = formLookupsNeeded.split(/;/g);
			listsUsed.forEach(function(lookupNeeded) {
				var listName = lookupNeeded.split(":", 2)[0];
				var sourceListName = (listName.localeCompare("efaweb_virtual_boatVariants") == 0) ? "efaWeb_boats" : listName;
				cLists.readCsv(sourceListName, formLookupsCsv[sourceListName]);
				var fieldNames = lookupNeeded.split(":", 2)[1];
				fieldNames.split(/,/g).forEach(function(fieldName) {
					sFormHandler.resolve[fieldName] = listName;
				})
			});
		}
	},
	
	// hide the system generated fields for new record forms
	add_new_prepare : function() {
        if (formIsNewRecord) {
        	$('#vSpanSystemData').hide();
        	$('#editaction').html(_("eazTTs|create new"));
			$("input[type=submit]").val(_("eV5HYx|Create new now"));
        }
	},
	
	// prepare the right version and fields for versionized editing
	versionized_prepare : function() {
		if ($('#versionized-wayOfChange').length == 0)
			return;
		var vWayOfChange = parseInt($('#versionized-wayOfChange').val());
		var vValidityFromDate = $('#versionized-ValidityFromDate').val();
		var vValidityFromTime = $('#versionized-ValidityFromTime').val();
		var fromTime = Date.parse(vValidityFromDate + " " + vValidityFromTime);
		var Name = $('input[name=Name]').val();
		var FirstName = $('input[name=FirstName]').val();
		var LastName = $('input[name=LastName]').val();
		var fullName = (formNameFormat.localeCompare("LASTFIRST") == 0) ? LastName + ", " + FirstName : FirstName + " " + LastName;
        var name = (Name) ? Name : fullName;
        if (formIsNewRecord) {
        	$('#valDateLabel').html(_("CYJVN3|Valid from date"));
        	$('#valTimeLabel').html(_("rjnGAp|Valid from time"));
        	$('#vDiv-wayOfChange').hide();
			$('#vDiv-ValidityFromDate').show();
			$('#vDiv-ValidityFromTime').show();
			$('#versionized-data').show();
			$("input[type=submit]").val(_("ntQ6xA|Create new now"));
        } else if (vWayOfChange === 0) {
			$('#vDiv-ValidityFromDate').hide();
			$('#vDiv-ValidityFromTime').hide();
			$('#versionized-data').show();
			$("input[type=submit]").val(_("pDysSn|Change data for °%1", name));
		} else { 
			$('#vDiv-ValidityFromDate').show();
			$('#vDiv-ValidityFromTime').show();
			$('#versionized-data').hide();
			if (vWayOfChange === 1) 
				$("input[type=submit]").val(_("SI9WWD|Valid for °%1° change", name));
			else {
	        	$('#valDateLabel').html("Abgrenzen zu Datum");
	        	$('#valTimeLabel').html("Abgrenzen zu Zeit");
				$("input[type=submit]").val(_("8ugV2E|Create a new version for...", name));
			}
		} 
	},
	
	// hide unused boat variants
	hide_unused_boat_variants : function() {
		var boatsVariantCount = $('#boats-VariantCount').val()
		if (! boatsVariantCount)
			return;
		boatsVariantCount = parseInt(boatsVariantCount);
		for (var i = 1; i <= 4; i++) {
			if (i <= boatsVariantCount)
				$(".boatvariant-" + i).show()
			else
				$(".boatvariant-" + i).hide()
		}
	},
	
	// prepare the boat-variants
	collect_boat_variants : function() {
		var typeFields = {
				"TypeVariant" : "", 
				"TypeType" : "", 
				"TypeCoxing" : "", 
				"TypeSeats": "", 
				"TypeRigging" : "", 
				"TypeDescription" : ""
		};
		var boatsVariantCount = parseInt($('#boats-VariantCount').val());
		for (typeField in typeFields) {
			for (var i = 1; i <= boatsVariantCount; i++) {
				var value = $('#boats-' + typeField + "-" + i).val();
				if (value && (value.length > 0) && (value.localeCompare("NOENTRY") !== 0))
					typeFields[typeField] += value + ";";
			}
			if (typeFields[typeField].length > 0)
				typeFields[typeField] = typeFields[typeField].substring(0, typeFields[typeField].length -1);
			$('#changed-' + typeField).val(typeFields[typeField]);
		}
	},
	
	// set the back-ground color within a table cell as example
	set_color_probe : function() {
		if (! $('#efa-group-color'))
			return;
		var color = $('#efa-group-color').val();
		if (! color)
			return;
		$('#td_efa_group_color').css("background-color", "#" + color);
	},
	
	// add a member to the group.
	add_group_member : function() {
		var memberNameList = $('#group-MemberIdList').val();
		var memberName = $('#group-LookupPersonId').val();
		if (!memberName)
			return memberNameList;
		if (memberNameList.includes(memberName))
			alert(_("UvnyFU|The person °%1° is alrea...", memberName));
		else {
			var sep = (memberNameList) ? ";" : "";
			memberNameList += sep + memberName; 
			$('#group-MemberIdList').val(memberNameList);
		}
		$('#group-LookupPersonId').val("");
		return memberNameList;
	},
	
	// in some cases the record must not be changed. return an reason, if so, or
	// false, if allowed
	is_change_forbidden : function() {
		if ($("input[name=EntryId]").length > 0) {
			// logbook form
			var openClass = $("input[name=Open]").attr("class");
			if (openClass.localeCompare("checked-off") != 0) {
				$("input[type=submit]").val(_("m0ItOz|The trip is open and the..."));
				$("input[type=submit]").attr("disabled", true);
			}
		}
	},

	/**
	 * prepare the lookup fields for damage entry (persons, boat).
	 */
	editRecord_prepare : function() {
		var inputs = $(":input");
		// start with special case: select the correct boat variant for a
		// sessien record
		var boatVariant = 1;
		inputs.each(function(i, element) {
			var inputName = $(element).attr("name");
			if (inputName && (inputName.localeCompare("BoatVariant") == 0)) 
				boatVariant = parseInt($(element).val());
		});
		var boatVariant = $('input[name="BoatVariant"]').val();
		if (boatVariant)
			boatVariant = parseInt(boatVariant);
		else
			boatVariant = 1;
		// add the autocompletion options and replace UUIDs by names in preset
		inputs
				.each(function(i, element) {
					var inputName = $(element).attr("name");
					var inputVal = $(element).val();
					var inputType = $(element).attr("type");
					var isCheckbox = inputType
							&& (inputType.localeCompare("checkbox") == 0);
					if (sFormHandler.resolve[inputName]) {
						// replace a UUID by the respective name for preset
						if (cToolbox.isGUIDlist(inputVal)) {
							var listname = sFormHandler.resolve[inputName];
							var ids = inputVal.split(/;/g);
							var inputAltVal = "";
							for (id of ids) {
								var index = cLists.indices[listname + "_guids"][id];
								var record = cLists.lists[listname][index];
								if (record) {
									var name = record.Name;
									if (listname.localeCompare("efaWeb_persons") == 0)
										name = (formNameFormat.localeCompare("LASTFIRST") == 0) ? 
												record.LastName + ", " + record.FirstName : record.FirstName + " " + record.LastName;
									if (listname.localeCompare("efaweb_virtual_boatVariants") == 0) {
										boatRecord = cLists.lists.efaWeb_boats[cLists.indices.efaWeb_boats_guids[record.Id]];
										boatVariantsNames = oBoat.getNames(boatRecord);
										name = boatVariantsNames[boatVariant - 1];
									}
									inputAltVal += name + ";";
								} else {
									inputAltVal += "???;";
								}
							}
							if (inputAltVal.length > 0)
								inputAltVal = inputAltVal.substring(0, inputAltVal.length - 1);
							$(this).val(inputAltVal);
						}
						// if not Id preset is given, check for a Name preset to
						// use
						var isList = inputName.endsWith("IdList")
						var nameInputName = (isList) ? inputName.substring(0,
								inputName.length - 6)
								+ "NameList" : inputName.substring(0,
								inputName.length - 2)
								+ "Name";
						var inputAlt = $('input[name=' + nameInputName + ']');
						var inputAltVal = $(inputAlt).val();
						if ((inputVal === '') && (inputAltVal !== ''))
							$(this).val(inputAltVal);
						// add the autocomplete options
						var options = Object
								.keys(cLists.names[sFormHandler.resolve[inputName]
										+ "_names"]);
						autocomplete(inputs[i], options, efaInputValidator,
								sFormHandler.resolve[inputName]);
					} else if ((inputName.localeCompare("LastModification") == 0)
							&& (inputVal.localeCompare("delete") == 0)) {
						$("input[name=submit]").hide();
					} else if (isCheckbox) {
						sFormHandler.checkBoxStates[inputName] = ($(element)
								.attr("class").indexOf("checked-on") == 0);
					}
				});
		// register all changes for form submit
		// this will run in parallel to the autocompletion. Checks there will
		// validate the value.
		inputs
				.on(
						"keyup change",
						function() {
							var changedValue = $(this).val().replace(/\'/g,
									"\\'");
							// workaround for checkboxes with no access to
							// ::after pseudo-element
							var inputName = $(this).attr("name");
							if ((sFormHandler.checkBoxStates[inputName] === true)
									|| (sFormHandler.checkBoxStates[inputName] === false)) {
								sFormHandler.checkBoxStates[inputName] = !sFormHandler.checkBoxStates[inputName];
								changedValue = (sFormHandler.checkBoxStates[inputName] === true) ? "on"
										: "";
							}
							sFormHandler.addChangedInput(this, changedValue);
							sFormHandler.onInputChanged();
						});
		// prepare remainder. functions will check themselves, whether they are
		// to be applied.
		sFormHandler.onInputChanged();
		$('#group-add-member').click(function() {
			var changedValue = sFormHandler.add_group_member();
			sFormHandler.addChangedInput($('#group-MemberIdList'), changedValue);
		});
	},
	
	// all activities to be performed on any input change.
	onInputChanged : function() {
		sFormHandler.collect_boat_variants();
		sFormHandler.hide_unused_boat_variants();
		sFormHandler.set_color_probe();
		sFormHandler.is_change_forbidden();
	},

	// add an additional "changed-input" to keep provide UUID resolving for
	// changed values
	addChangedInput : function(input, changedValue) {
		var inputName = $(input).attr("name").replace("-2", "");
		var changedInput = $("input[name=changed-" + inputName + "]");
		// we (may) have to replace the name by an Id
		if (sFormHandler.resolve[inputName]) {
			var isList = inputName.endsWith("IdList")
			var nameInputName = (isList) ? inputName.substring(0,
					inputName.length - 6)
					+ "NameList" : inputName.substring(0, inputName.length - 2)
					+ "Name";
			var changedNameInput = $("input[name=changed-" + nameInputName
					+ "]");
			// try to resolve first, either all NameList elements to an IdList
			// or Name to Id
			var listname = sFormHandler.resolve[inputName];
			var changedIds = "";
			var record;
			if (isList) {
				changedNames = changedValue.split(/;/g);
				changedNames.forEach(function(cn) {
					// if one Id in the list was matched, drop the unmatched
					// names
					index = cLists.indices[listname + "_names"][cn.trim()];
					if (index || (index == 0)) {
						record = cLists.lists[listname][index];
						changedIds += record.Id + ";";
					}
				});
				if (changedIds)
					changedIds = changedIds.substring(0, changedIds.length - 1);
			} else {
				index = cLists.indices[listname + "_names"][changedValue];
				if (index || (index === 0)) {
					record = cLists.lists[listname][index];
					changedIds = record.Id;
				} else
					changedIds = false;
			}
			if (changedIds) {
				// name was resolved, replace the Id and remove the name
				changedNameValue = "";
				changedValue = changedIds;
			} else {
				changedNameValue = changedValue;
				changedValue = "";
			}
			// here just for names, Id see below.
			if (changedNameInput.length == 0)
				// there is no changed-input field yet, add it.
				$(input).append(
						"<input type='hidden' name=changed-" + nameInputName
								+ " value='" + changedNameValue + "'>");
			else
				// there is already a changed-input field, replace the value
				changedNameInput[0].value = changedNameValue;
		}
		if (changedInput.length == 0)
			// there is no changed-input field yet, add it.
			$(input).append(
					"<input type='hidden' name=changed-" + inputName
							+ " value='" + changedValue + "'>");
		else
			// there is already a changed-input field, replace the value
			changedInput[0].value = changedValue;
		this.showAllChanges();
	},

	// display all changes in the DIV with ID 'changed-values-text'
	showAllChanges : function() {
		var changeText = "";
		var inputs = $(":input");
		// add the autocompletion options and replace UUIDs by names in preset
		inputs.each(function(i, element) {
			var inputName = $(element).attr("name");
			if (inputName && inputName.startsWith("changed-"))
				changeText += inputName.substring(8) + ": " + $(element).val()
						+ "; ";
		});
		if (changeText !== '')
			$('#changed-values-text').html(
					"<br><b>" + _("sOsPSN|is changed:") + "</b><br>" + changeText);
		else
			$("input[type=submit]").hide();
	}

}
