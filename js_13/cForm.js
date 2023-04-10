/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c)
 * 2001-2022 by Nicolas Michael Website: http://efa.nmichael.de/ License: GNU
 * General Public License v2. Module efaCloud: Copyright (c) 2020-2021 by Martin
 * Glade Website: https://www.efacloud.org/ License: GNU General Public License
 * v2
 */

var cForm = {

    formName : "",
	tableName : "",
    index : 0,
	inputs : [],
    validities : [],
    formErrors : "",
    
    _formDefinition : "",
    

    /**
	 * Build a form based on the definition provided in the config.js at
	 * $_formTemplates.
	 * 
	 * @param String
	 *            formName for form definition.
	 */
    init : function(formName) {
        
    	this.formName = formName;
        this.inputs = [];
        this.validities = [];
        this.useValues = [];
        this.formErrors = "";
       	formConfig = cToolbox.readCsvList($_formTemplates[formName]);
        
        // in order to be able to reference the field definition by its name,
		// create a named array.
        iht = 0;
        ins = 0;
        field_index = 0;
        this._formDefinition = [];
        formConfig.forEach(function(fieldDefinition) {
        	// due to line breaks within the definition String, names may have
			// leading blanks. Remove them first
        	fieldDefinition["name"] = fieldDefinition["name"].trim();
            // when creating the named array, take care for special form
			// definition options.
            help_text = (fieldDefinition["name"].indexOf("_help_text") == 0);
            no_input = (fieldDefinition["name"].indexOf("_no_input") == 0);
            submit = (fieldDefinition["type"].indexOf("submit") == 0);
            fieldDefinition["useValue"] = !help_text && !no_input && !submit;
            // make sure all help text definitions have different names in named
			// array. Theirname within the definition is always "_help_text".
			// They will
			// become "_help_text1", "_help_text2" etc. Same with "_no_input".
            if (help_text) {
                iht ++;
                cForm._formDefinition[fieldDefinition["name"] + iht] = fieldDefinition;
            } else if (no_input) {
                ins ++;
                cForm._formDefinition[fieldDefinition["name"] + ins] = fieldDefinition;
            } else {
            	cForm._formDefinition[fieldDefinition["name"]] = cForm._readOptions(
                        fieldDefinition);
            }
            field_index ++;
        });
    },
    
    /**
	 * Read the parameter list for a select field from the data base and extend
	 * numeric size definitions by 'em' as unit.
	 */
    _readOptions:function(fieldDefinition) {
        if (fieldDefinition["type"].trim().toLowerCase().indexOf("select use:") == 0) {
            // use a select parameter as defined in the parameters table
            lookup = fieldDefinition["type"].trim().split(":");
            parameter_name = (lookup.length == 2) ? lookup[1] : "select use syntax error in form definition.";
            select_string = "";
            if ($_params) {
                options = $_params[parameter_name];
                for (option in options) {
                    select_string += option + "=" + options[option] + ";";
                }
            }
            if (select_string.length == 0)
                fieldDefinition["type"] = "parameter_not_initialized=parameter_not_initialized;";
            fieldDefinition["type"] = "select " + select_string.substring(0, select_string.length - 1);
        }
        // add unit "em" if size has no unit. For textarea this is in maxlength.
        if (fieldDefinition["size"] && parseInt(fieldDefinition["size"]).toString()
        		.localeCompare(fieldDefinition["size"].trim()) == 0) {
            fieldDefinition["size"] = fieldDefinition["size"] + "em";
        }
        return fieldDefinition;
    },

    /**
	 * Return a html code of the help text.
	 * 
	 * @return string
	 */
    getHelpHtml:function() {
    	help_html = "";
        for (fkey in this._formDefinition) {
            // the fo defintion contains both the form and some help text,
			// usually displayed within
            // a right border frame. Only the help text shall be returned in
			// this function
        	f = this._formDefinition[fkey];
            if (f.name.indexOf("_help_text") == 0)
            	help_html += f.tags + f.label + "\n";
        }
        return help_html;
    },
    
    // for select and radio dynamic options definition ist avaible and defined here.
    getSelectOptionsArray : function(f) {
		// split type definition into 'select' and options
		options = f.type.substring(f.type.indexOf(" ") + 1);
		options_array = options.split(";");
		// special case: the selection is provided programmatically based on a list
		if (options.substring(0, 4).toLowerCase().localeCompare("list") == 0) 
			options_array = cLists.defs.getOptions(options.substring(5));
		// special case: the selection is provided programmatically based on an object
		if (options.substring(0, 4).toLowerCase().localeCompare("from") == 0) {
			var pathElements = options.substring(5).split(/\./g);
			options_object = window[pathElements[0]];
			for (l = 1; l < pathElements.length; l++)
				options_object = options_object[pathElements[l]];
			options_array = Object.keys(options_object);
		}
		return options_array;
    },
    
    /**
	 * Return a html code of this form based on its definition. Will not return
	 * the help text.
	 * 
	 * @return string the html code of the form for display
	 */
    getHtml:function () {
        if (Object.keys(this._formDefinition).length == 0)
            return "";
        // start the form. The form has no action in itself and shall not reload
		// when submitting. Therefore it is implemented as "div" rather than
		// "form"
        var form = '		<div>\n';
        if (this.formErrors) 
        	form += "<p style=\"color:#A22;\">" + this.formErrors + "<p>";
        // ----------------------------------------------------------
        // Buld form as a table of input fields. Tags define columns
        // ----------------------------------------------------------
        for (let fieldname in this._formDefinition) {
        	var f = this._formDefinition[fieldname];
        	// start the input field with the label
        	var mandatory_flag = (f.required.localeCompare("*") == 0) ? "*" : "";
        	var disabled_flag = (f.required.localeCompare("!") == 0) ? "disabled" : "";
        	var inline_label = (f.type.localeCompare("radio") == 0) ||
                       (f.type.localeCompare("checkbox") == 0) ||
                       (f.type.localeCompare("input") == 0) || 
                       (f.label.length === 0);
        	// the form defintion contains both the form and some help text,
			// usually displayed within a right border frame. The help text
        	// shall not be returned in this function
        	var help_text = (f.name.indexOf("_help_text") >= 0);
        	var no_input = (f.name.indexOf("_no_input") >= 0);
        	// provide border an label styling in case of invalid input.
    		var validity_border_style = "";
        	var validity_label_style_open = "";
        	var validity_label_style_close = "";
        	if (this.validities[f.name] == false) {
        		validity_border_style = "style=\"border:1px solid #A22;border-radius: 0px;\" ";
        		validity_label_style_open = "<span style=\"color:#A22;\">";
        		validity_label_style_close = "</span>";
        	}
        	// show label for input
        	if (! help_text) {
        		if (inline_label) // radio and checkbox
        			form += f.tags;
        		else // includes "_no_input"
        			form += f.tags + validity_label_style_open + mandatory_flag + f.label +
        					validity_label_style_close + "<br>\n";
        	}
        	// now provide the field. Wrap with htmlSpecialChars to prevent from
			// XSS
        	// https://stackoverflow.com/questions/1996122/how-to-prevent-xss-with-html-php
        	var preset = cToolbox.escapeHtml(this.inputs[f.name]);
        	if (! preset && f.value) {
        		if (f.value.indexOf("\$now") == 0) {
        			var d = new Date();
            		preset = d.getFullYear() + "-" + (d.getMonth()+1) + "-" + d.getDate();
        		}
        		else
        			preset = f.value;
        	}
        	// do not use invalid values for preset
        	if (this.validities[f.name] == false)
        		preset = null;
        	// set a unique id for each input to collect the values later.
        	var id_str = ' id="cFormInput-' + f.name + '" ';
        	
        	// special case: select field.
        	if (f.type && f.type.indexOf("select") >= 0) {
        		form += "<select " + validity_border_style;
        		if (f.class && f.class.length > 0)
        			form += 'class="' + f.class + '" ';
        		else
        			form += 'class="forminput" ';
        		if (f.name && f.name.length > 0)
        			form += 'name="' + f.name + '" ';
        		if (f.size && f.size.length > 0)
        			form += 'style="width: ' + f.size + '"' + id_str + disabled_flag + '>' + "\n";
        		else
        			form += disabled_flag + '>' + "\n";
        		options_array = cForm.getSelectOptionsArray(f);
        		// code all options as defined
        		options_array.forEach(function(option) {
        			var nvp = option.split("=");
        			if (nvp.length == 1) nvp.push(nvp[0]); 
        			if (nvp[0].localeCompare(preset) != 0)
        				form += '<option value="' + nvp[0].trim() + '">'
        				        + nvp[1].trim() + "</option>\n";
        			else
        				form += '<option selected value="' + nvp[0].trim() + '">' 
        				        + nvp[1].trim() + "</option>\n";
                 });
                 form += "</select>\n";
            
        	} else if (f.type && f.type.indexOf("radio") >= 0) {
                // special case: radio group (similar to select field case)
                // split type definition into 'radio' and options
         	    options = f.type.substring(f.type.indexOf(" ") + 1);
        		options_array = options.split(";");
        		// code all options as defined
        		options_array.forEach(function(option) {
        			var nvp = option.split("=");
                    form += '<label class="cb-container">' + f.label + "\n";
                    form += '<input type="radio" name="' + f.name + '" value="' + nvp[0] +
                                    validity_border_style;
                    if (nvp[0].localeCompare(preset) != 0)
                        form += '" checked' + id_str + disabled_flag + '>' + nvp[1] + '<br>\n';
                    else
                        form += '"' + id_str + disabled_flag + '>' + nvp[1] + '<br>\n';
                    form += '<span class="cb-radio"></span></label>';
                });
            
            } else if (f.type && f.type.indexOf("checkbox") >= 0) {
                form += '<label class="cb-container">' + f.label + "\n";
                form += '<input type="checkbox" name="' + f.name + '"';
                // In case of a checkbox, set checked for value "on".
                if (f.value.length > 0)
                     form += (f.value.toLowerCase().localeCompare("on") == 0) ? " checked" : "";
                else if (preset && preset.length > 0)
                     form += (preset.toLowerCase().localeCompare("on") == 0) ? " checked" : "";
                form += id_str + disabled_flag + '><span class="cb-checkmark"></span></label>';
            
            } else if (f.type.indexOf("textarea") >= 0) {
                if (f.class.length > 0) {
                    var class_str = 'class="' + f.class + '" ';
                }
                else
                    var class_str = 'class="forminput" ';
                if (!preset) preset = ""; 
                form += '<textarea name="' + f.name + '" cols="' + f.maxlength + '" rows="' +
                         ((f.size) ? f.size : 4) + '" ' + class_str + id_str + disabled_flag + '>' + preset + '</textarea><br>' + "\n";
            
            } else if (! help_text && ! no_input && f.name && (f.name.length > 0)) {
                // default input type.
                form += "<input " + validity_border_style;
                if (f.type && f.type.length > 0) {
                    form += 'type="' + f.type + '" ';
                    if (f.type.toLowerCase().indexOf("submit") == 0)
                    	id_str = ' id="cFormInput-submit" ';
                }
                if ((f.class.length > 0) && (f.class.indexOf("validate:") != 0)
                		 && (f.class.indexOf("call:") != 0)) 
               		form += 'class="' + f.class + '" ';
                else
                    form += 'class="forminput" ';
                if (f.size && f.size.length > 0)
                    form += 'style="width: ' + f.size + '" ';
                if (f.maxlength && f.maxlength.length > 0)
                    form += 'maxlength="' + f.maxlength + '" ';
                if (f.name && f.name.length > 0)
                    form += 'name="' + f.name + '" ';
                // set value.
                if (preset && preset.length > 0)
                    form += 'value="' + preset + '" ';
                else if (f.value && f.value.length > 0)
                    form += 'value="' + f.value + '" ';
                if (inline_label)
                    form += id_str + disabled_flag + ">&nbsp;" + validity_label_style_open + mandatory_flag + f.label +
                            validity_label_style_close + "\n";
                else
                   form += id_str + disabled_flag + ">\n";
            }
        }
        form += "	</div>\n";
        // console.log(form);
        return form;
    },
    
    /**
	 * read all values into the "inputs" of this form object as they were
	 * provided via the post or get method. This function will set all inputs of
	 * the form object, i. e. empty form inputs will delete a previously set
	 * value within a field. Strings are UTF-8 encoded. Not validation applies
	 * at this point.
	 * 
	 * @param string
	 *            replace_insecure_chars (optional) this function replaces "`"
	 *            by the Armenian apostrophe "՚" and ";" by the Greek question
	 *            mark ";". Characters look similar, but have different code
	 *            points so that they will not be interpreted in their
	 *            SQL-function such by any data base. And the same to prevent
	 *            from cross side scripting, "<" is replaced by the math
	 *            preceding character "≺". If you do not want to have these
	 *            replacements, set $replace_insecure_chars to false.. Default
	 *            is true.
	 * @return associative array of read data.
	 */
    readEntered:function (replace_insecure_chars = true) {
        this.inputs = [];
        for (fkey in this._formDefinition) {
        	var f = this._formDefinition[fkey];
        	if (f.useValue) {
        		var id = '#cFormInput-' + f.name;
        		var value = $(id).val();
        		// checkboxes are "on" or "", to be compatible with the server
        		// side PHP implementation.
        		if (f.type && f.type.toLowerCase().localeCompare("checkbox") == 0) 
        			value = ($(id).is(':checked')) ? "on" : "";
        		// replace "undefined" to circumvent execution errors
        		if (!value) 
        			value = "";
        		if (replace_insecure_chars !== false)
        			value = value.replace(/`/g, "\u{055A}").replace(/</g, "\u{227A}").replace(/>/g, "\u{227B}")
        					.replace(/;/g, "\u{037E}");
        		if (this._isDate(f))
        			this.inputs[f.name] = cToolbox.checkAndFormatDate(value);
        		else if (this._isField(f))
        			this.inputs[f.name] = value;
        	}
        }
        return this.inputs;
    },
    
    /**
	 * Check whether this field is a form field
	 * 
	 * @param array
	 *            fieldDefinition field definition
	 * @return boolean true, if this is a form field, false if it is a submit
	 *         field, a help text etc.
	 */
    _isField:function (fieldDefinition) {
        return (fieldDefinition["type"].toLowerCase().localeCompare("submit") != 0) &&
               (fieldDefinition["name"].length > 0) &&
               (fieldDefinition["name"].toLowerCase().indexOf("_help_text") != 0) &&
               (fieldDefinition["name"].toLowerCase().indexOf("_no_input") != 0);
    },
    
    /**
	 * Check whether this field is a date type field
	 * 
	 * @param array
	 *            fieldDefinition field definition
	 * @return boolean true, if this is a date type field, false if it is not.
	 */
    _isDate:function (fieldDefinition) {
        return (fieldDefinition["type"].toLowerCase().localeCompare("date") == 0);
    },
    
    /**
	 * preset all values of the form with those of the provided array with
	 * array(key, value) being put to the form object inputs array (key, value),
	 * if in the form object inputs array such key exists.
	 * 
	 * @param values
	 *            all values to be preset. The method will read through the form
	 *            definition and use values for each matching key which occurs
	 *            in this array. If the array has keys, which are not keys of
	 *            the form, these keys are ignored.
	 */
    presetValues:function (values) {
        for (fieldname in this._formDefinition) {
        	if (typeof values[fieldname] !== 'undefined')
        		cForm.presetValue(fieldname, values[fieldname]);
        }
    },
    
    /**
	 * preset a single value of the form object. If the key is not a field name
	 * of the form, this will have no effect. Value must be a UTF-8 encoded
	 * String.
	 * 
	 * @param $key
	 *            String key of field to be preset
	 * @param $value
	 *            String value to be preset. Must be a UTF-8 encoded String. for
	 *            inputs of type select it can b '~n' with n being the index of
	 *            the value to be selected.
	 */
    presetValue:function (key, value) {
		var f = this._formDefinition[key];
    	if (f) {
    		if (f.type.indexOf("select") >= 0) {
        		options_array = cForm.getSelectOptionsArray(f);
    			if (value.charAt(0) == '~') {
    				pos = intval(value.substring(1)) - 1;
    				this.inputs[key] = options_array[pos].split("=")[1];
    			} else {
    				for (i = 0; i < options_array.length; i++)
    					if (options_array[i].split("=")[0].localeCompare(value) == 0)
    						this.inputs[key] = options_array[i].split("=")[0];
    			}
    		} else
    			this.inputs[key] = value;
    	}
    },
    
    /**
	 * Check the validity of all inputs within the form. Uses the type
	 * declaration in the form to deduct the required data type and the
	 * "required" field to decide, whether a field must be filled or not. Will
	 * also return an error, if the value contains a '<' and the word 'script'
	 * to prevent from cross site scripting.
	 * 
	 * @param password_rule
	 *            leave out or set to 1 for default rule, 0 for no check
	 * @return list of compliance errors found in the form values. Returns empty
	 *         String, if no errors were found.
	 */
    checkValidity:function (password_rule = 1) {
        var formErrors = "";
        for (let key in this.inputs) {
            value = this.inputs[key];
            definition = this._formDefinition[key];
            // check empty inputs. They always comply to the format, if no
            // entry was required.
            if (value.length < 1) {
                // input is empty
                if (definition["required"].length > 0) {
                    // input is required
                    formErrors += 'Bitte bei "' + definition["label"];
                    if (definition["type"].toLowerCase().localeCompare("checkbox") == 0)
                        formErrors += '" den Haken setzen.<br>';
                    else
                        formErrors += '" einen Wert eingeben.<br>';
                    this.validities[key] = false;
                }
            } else {
                // now check provided value on format compliance, if the
                // type parameter is set
                if (definition["type"].length > 0) {
                    type = definition["type"].toLowerCase();
                    if (type.localeCompare("email") == 0) {
                    	var parts = value.split("@");
                    	var isValid = (value.length == 2);
                    	isValid = isValid && (parts[1].length > 5) && (parts[1].indexOf(".") > 0);
                        if (! isValid) {
                            formErrors += 'Bitte bei "' + definition["label"] +
                                           '" eine gültige E-Mail-Adresse eingeben<br>';
                            this.validities[key] = false;
                        }
                    } else if (type.localeCompare("date") == 0) {
                        if (cToolbox.checkAndFormatDate(value) == false) {
                            formErrors += 'Bitte bei "' + definition["label"] +
                                         '" eine gültiges Datum eingeben (nicht "' + $value +
                                         '")<br>';
                            this.validities[key] = false;
                        }
                    } else if ((type.localeCompare("password") == 0) && (password_rule > 0)) {
                        errors = cToolbox.checkPassword(value);
                        if (errors.length > 0) {
                            formErrors += 'Das Passwort ist nicht ausreichend sicher in "' +
                                    definition["label"] + '" ' + errors + '<br>';
                            this.validities[key] = false;
                        }
                    }
                } 
                if ((definition["class"].length > 9) && (definition["class"].indexOf("validate:") == 0)) {
                	var guids = cLists.names[definition["class"].substring(9) + "_names"];
                	var guid = (guids) ? guids[value] : false;
                    if (! guid) {
                        formErrors += 'Der Name "' + value + '" wurde in der Liste "' + definition["class"].substring(9) +
                        	'" nicht gefunden, muss aber dort enthalten sein.<br>';
                        this.validities[key] = false;
                    }
                } else if ((definition["class"].length > 5) && (definition["class"].indexOf("call:") == 0)) {
                	var call = definition["class"].substring(5);
                	var callModule = call.split(".")[0];
                	var callFunction = call.split(".")[1];
                	var approval = (window[callModule] && window[callModule][callFunction]) ? window[callModule][callFunction](this.inputs) : true;
                    if (approval !== true) {
                        formErrors += approval + '<br>';
                        this.validities[key] = false;
                    }
                }
            }
        }
        this.formErrors = formErrors;
        return formErrors;
    }
		
}