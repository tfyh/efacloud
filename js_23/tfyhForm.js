/**
 * 
 * the tools-for-your-hobby framework ----------------------------------
 * https://www.tfyh.org
 * 
 * Copyright 2018-2024 Martin Glade
 * 
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not
 * use this file except in compliance with the License. You may obtain a copy of
 * the License at
 * 
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations under
 * the License.
 */

class TfyhForm {

	#forDisplay = {};     // previously entered or default values, as strings
							// put to the form.
	#entered = {};  // input values, as strings collected at form submission.
	#parsed = {};   // input values, cleansed and parsed.
	#changed = {};  // true for those input values which were changed. The
					// parsed value may not be changed, evene if the string was.
	redo;           // the function to call when the form has errors and must be
					// redone. Default is a call to modal.showForm.
    validities = {};   // validation results
    formerrors; // validation errors as String
    headline = "<h4>headline</h4>";
    selectCatalog = {};
    mode = null;  // for multipurpose forms (new & edit e.g.) to remember the
					// mode
    
    #formName = "";
    #configItem = null;
    #formDefinition = "";
    #appConfig = {};

    #formTagsShort = {
       		r : "<div class='w3-row'>",
       		R : "<div class='w3-row' style='margin-top:0.6em'>",
    		1 : "<div class='w3-col l1'>",
    		2 : "<div class='w3-col l2'>",
    		3 : "<div class='w3-col l3'>",
    		4 : "<div class='w3-col l4'>",
    		6 : "<div class='w3-col l6'>",
    		"/" : "</div>",
    		"//" : "</div></div>",
    };
    
    #inputToDataType = {
    		text : "string",
    		date : "date",
            checkbox : "string",
    		select : "string",
        	radio : "string",
            radioh : "string",
            textarea : "string"
    }
    
    /* -------------------------------------------------------------- */
    /* ---- INITIALIZATION ------------------------------------------ */
    /* -------------------------------------------------------------- */
    
    /**
	 * Empty constructor. This is done but once. The same form is always reused.
	 */
    constructor (appConfig)
    {
    	this.#appConfig = appConfig;
    }

    /**
	 * Simple getter.
	 */
    getName() {
    	return this.#formName;
    }
    
    /**
	 * Get all valid field names of this form, whithout _no_input, _headline,
	 * _help_text.
	 */
    getFieldNames() {
    	let fieldNames = [];
    	for (let key in this.#formDefinition)
    		if (this.#formDefinition[key].name.substring(0, 1) != "_")
    			fieldNames.push(this.#formDefinition[key].name);
    	return fieldNames;
    }

    /**
	 * Build a form based on the definition provided in the configuration
	 */
    init(formName, configItem, redo) {
        
    	this.#formName = formName;
      	this.#configItem = configItem;
    	this.#entered = {};
        this.#parsed = {};
        this.#changed = {};
        this.validities = {};
        this.redo = redo;
       	let formConfig = TfyhToolbox.csvToObject($_formTemplates[formName]);
       	
       	// inflate the definition if necessary.
       	let isShortDefnition = (typeof formConfig[0].value === 'undefined');
       	if (isShortDefnition) {
	        let expandedFormConfig = [];
       	    for (let fieldDefinition of formConfig) 
       	    	this.#expandShortDefinitionRow(fieldDefinition, expandedFormConfig);
       	    formConfig = expandedFormConfig;
       	    for (let fieldDefinition of formConfig) {
       	        // set defaults first
   	        	fieldDefinition.type = "text";
   	        	fieldDefinition["class"] = "";
   	        	fieldDefinition.size = this.#getWidth(fieldDefinition.tags);
   	        	fieldDefinition.maxlength = "256";
   	        	// now read the field input definitions. They are defined either
				// by the configuration item child of the given name or by its
				// descriptor field of the given name.
   	        	let descriptorToUse;
   	        	if (TfyhData.is_descriptor_field_name(fieldDefinition.name)) {
   	        		// it is a descriptor field, use the own descriptor, if it
					// is a value to be entered (default, current, min, max)
   	        		descriptorToUse = (fieldDefinition.name.startsWith('value')) ? this.#configItem.get_descriptor_clone()
   	        				// or the descriptor field template, if any other
							// descriptor field is asked for
   	        				: TfyhData.get_descriptor_template(
   	   	        					TfyhData.get_descriptor_field_data_type(fieldDefinition.name, this.#configItem.get_type())) 
   	        	} else
   	        		// it is no descriptor field, i. e. it is a child item
   	        		descriptorToUse = (this.#configItem.has_child(fieldDefinition.name))
   	        					// use the child's descriptor, if it exists
	        					? this.#configItem.get_child(fieldDefinition.name).get_descriptor_clone() 
	        							: false;
   	        	if (descriptorToUse) {
   	        		// make sure there is a sensible name displayed.
   	        		// For descriptor edit use the descriptor local name
   	        		if (TfyhData.is_descriptor_field_name(fieldDefinition.name))
   	        			fieldDefinition.label = TfyhData.get_descriptor_field_local_name(fieldDefinition.name);
   	        		else
       	        		fieldDefinition.label = _(descriptorToUse.text_local_name);
       	        	if (descriptorToUse.input_type) 
   	        			fieldDefinition.type = descriptorToUse.input_type;
       	        	if (descriptorToUse.input_class) 
   	        			fieldDefinition["class"] = descriptorToUse.input_class;
       	        	// set reference for select types
       	        	fieldDefinition.column_reference = descriptorToUse.column_reference;
       	        	if (fieldDefinition.column_reference && fieldDefinition.column_reference.startsWith("."))
       	        		fieldDefinition.type = "select $column_reference";
       	        	// set the template's default value
       	        	fieldDefinition.dataType = descriptorToUse.value_type;
       	        	fieldDefinition.value = TfyhData.format(descriptorToUse.value_current, 
       	        			descriptorToUse.value_type);
       	        	// check for list input
       	        	fieldDefinition.isList = (descriptorToUse.column_handling 
       	        			&& (descriptorToUse.column_handling.indexOf("l") >= 0));
   	        	}
   	        	// special case submit button
   	        	if (fieldDefinition["name"].toLowerCase().localeCompare("submit") == 0) {
   	        		fieldDefinition["class"] = "formbutton";
   	        		fieldDefinition["value"] = fieldDefinition["label"];
   	        		delete fieldDefinition["size"];
   	        		delete fieldDefinition["label"];
   	        		delete fieldDefinition["maxlength"];
   	        	}
   	        }
       	}
       	
       	// add default size
   	    for (let fieldDefinition of formConfig) {
   	    	if (! fieldDefinition.size && (fieldDefinition["class"] != "formbutton"))
	        	fieldDefinition.size = this.#getWidth(fieldDefinition.tags);
   	    }
        
        // in order to be able to reference the field definition by its name,
		// create a named array.
       	let iht = 0;
       	let ins = 0;
        this.#formDefinition = [];
        for (let fieldDefinition of formConfig) {
        	// due to line breaks within the definition String, names may have
			// leading blanks. Remove them first
        	fieldDefinition.tags = this.#inflateTags(fieldDefinition.tags.trim());
        	fieldDefinition.name = fieldDefinition.name.trim();
        	// localize the label
        	fieldDefinition.label = _(fieldDefinition.label); 
            // when creating the named array, take care for special form
			// definition options.
        	let help_text = (fieldDefinition.name.indexOf("_help_text") == 0);
        	let no_input = (fieldDefinition.name.indexOf("_no_input") == 0);
        	let submit = (fieldDefinition.type.indexOf("submit") == 0);
        	// find out what data type to use.
        	if (this.#configItem && !fieldDefinition.dataType) {
        		if (this.#configItem.has_child(fieldDefinition.name))
        			fieldDefinition.dataType = this.#configItem.get_child(fieldDefinition.name).get_type();
        		else // fallback: do not parse the value, let the context
						// care.
        			fieldDefinition.dataType = "string";
        	}
        	// localize text for submit button.
        	if (submit)
        		fieldDefinition.value = _(fieldDefinition.value);
        	fieldDefinition.useValue = !help_text && !no_input && !submit;
            // make sure all help text definitions have different names in named
			// array. Theirname within the definition is always "_help_text".
			// They will become "_help_text1", "_help_text2" etc.
            // Same with "_no_input".
            if (help_text) {
                iht ++;
                this.#formDefinition[fieldDefinition["name"] + iht] = fieldDefinition;
            } else if (no_input) {
                ins ++;
                this.#formDefinition[fieldDefinition["name"] + ins] = fieldDefinition;
            } else {
            	this.#formDefinition[fieldDefinition["name"]] = this.#readOptions(
                        fieldDefinition);
            }
        }
    }
    
    /**
	 * Expand a shorthand form configuration row, which does only provide
	 * 'tags;name;label'. an example for a short definition row is:
	 * '_//_r;~uid|column_reference|*text_local_name;'. That will be expanded to
	 * 'tags;required;name;label' with a row per field, i.e. '_//_r_3;~;uid;
	 * (mew line) _/_3;;column_reference; (mew line) _/_3;*;text_local_name;'
	 */
    #expandShortDefinitionRow(rowDefinition, expandedFormConfig) {
    	let names = rowDefinition["name"].split(/\|/g);
    	let tags = rowDefinition["tags"] + "_" + names.length;
    	let label = rowDefinition["label"].trim();
    	let firstName = true;
    	let shortDefinition;
    	for (name of names) {
    		shortDefinition = [];
    		shortDefinition["tags"] = (firstName) ? tags : tags.replace("/_r", "").replace("/_R", "");
    		firstName = false;
    		shortDefinition["required"] = (name.startsWith("*") || name.startsWith("!") || name.startsWith("~")) ? name.substring(0, 1) : "";
    		shortDefinition["name"] = name.substring(shortDefinition["required"].length);
    		if (rowDefinition["label"]) 
    			shortDefinition["label"] = rowDefinition["label"];
    		expandedFormConfig.push(shortDefinition);
    	}
    }
    
    /**
	 * Get the appropriate relative width depending on the column class (l1 ..
	 * l6).
	 */
    #getWidth (tags) {
    	let columnsCnt = "?";
    	if (tags.startsWith("_"))
    		columnsCnt = tags.substring(tags.length - 1);
    	else {
        	let colClassSearchText = "<div class='w3-col l";
    		let colClassPosition = tags.indexOf(colClassSearchText);
    		if (colClassPosition >= 0) {
    			let cCntPos = colClassPosition + colClassSearchText.length
    	    	columnsCnt = tags.substring(cCntPos, cCntPos + 1);
    		}
    	}
    	if (isNaN(columnsCnt))
    		return '96%';
    	return "" + (100 - (parseInt(columnsCnt) * 2)) + "%";
    }
    
    /**
	 * Inflate short tags - whicha always start with "_" - to full tags using
	 * the mapping of this.#formTagsShort.
	 */
    #inflateTags (tags) {
    	if (!tags.startsWith("_"))
    		return tags;
    	let shorts = tags.substring(1).split(/_/g);
    	tags = "";
    	for (let short of shorts)
    		tags += this.#formTagsShort[short];
    	return tags;
    }
    
    /**
	 * Read the parameter list for a select field from the data base and extend
	 * numeric size definitions by 'em' as unit.
	 */
    #readOptions(fieldDefinition) {
        if (fieldDefinition.type.trim().toLowerCase().indexOf("select use:") == 0) {
            // use a select parameter as defined in the parameters table
        	let lookup = fieldDefinition.type.trim().split(":");
        	let parameter_name = (lookup.length == 2) ? lookup[1] : "select use syntax error in form definition.";
        	let select_string = "";
            try {
            	if ($_params) {
                	let options = $_params[parameter_name];
                    for (option in options) {
                        select_string += option + "=" + options[option] + ";";
                    }
                }
            } catch (ignored) {}
            if (select_string.length == 0)
                fieldDefinition.type = "parameter_not_initialized=parameter_not_initialized;";
            fieldDefinition.type = "select " + select_string.substring(0, select_string.length - 1);
        }
        // options list is referenced in the column reference
        if (fieldDefinition.type.trim().toLowerCase().localeCompare("select $column_reference") == 0) {
        	let referencePath = fieldDefinition.column_reference;
        	let referenceItem = this.#appConfig.get_by_path(referencePath);
        	fieldDefinition.type = "select ";
        	if (referenceItem) 
        		for (let childname in referenceItem.get_children()) {
        			let child = referenceItem.get_child(childname);
        			fieldDefinition.type += childname + "=" + child.get_descriptor_field("text_local_name") + ";";
        		}
        	else
    			fieldDefinition.type += "null=" + _("V01GaK|No %1 list found.", referencePath);
        }
        // options list is the list of this items siblings
        if (fieldDefinition.type.trim().toLowerCase().localeCompare("select $catalog") == 0) {
        	fieldDefinition.type = "select ";
        	let selectCatalog = this.selectCatalog[fieldDefinition.name];
        	for (let childname in selectCatalog.get_children()) {
        		let child = selectCatalog.get_child(childname);
        		fieldDefinition.type += childname + "=" + child.get_descriptor_field("text_local_name") + ";";
        	}
        	fieldDefinition.type = fieldDefinition.type.substring(0, fieldDefinition.type.length - 1);
        }
        // add unit "em" if size has no unit. For textarea this is in maxlength.
        if (fieldDefinition.size && parseInt(fieldDefinition.size).toString()
        		.localeCompare(fieldDefinition.size.trim()) == 0) {
            fieldDefinition.size = fieldDefinition.size + "em";
        }
        return fieldDefinition;
    }

    /**
	 * preset all values of the form with those of the provided array with
	 * array(key, value) being put to the form object inputs array (key, value),
	 * if in the form object inputs array such key exists. Values must be UTF-8
	 * encoded Strings.
	 */
    presetValues (values_record, keep_hidden_defaults = false) {
    	for (let fieldname in this.#formDefinition) {
        	if ((typeof values_record[fieldname] !== 'undefined') && (! keep_hidden_defaults ||
                    (f.type && f.type.localeCompare("hidden") != 0)))
        		this.presetValue(fieldname, values_record[fieldname]);
        }
    }
    
    /**
	 * preset a single value of the form object. If the key is not a field name
	 * of the form, this will have no effect. Value must be a UTF-8 encoded
	 * String.
	 */
    presetValue (key, value_str) {
    	let f = this.#formDefinition[key];
    	let undefinedValue = (typeof value_str === 'undefined');
    	if (f) {
    		// create a String as best guess for non-string vales. shoul
			// actually not happen.
    		if ((typeof value_str !== 'string') && ! undefinedValue)
    			value_str = "" + value_str;
    		if ((f.type.indexOf("select") >= 0) && ! undefinedValue) {
        		let options_array = this.#getSelectOptionsArray(f);
    			if (value_str.charAt(0) == '~') {
                    // if the value starts with '~' it refers to the index of
					// the option
    				let pos = intval(value_str.substring(1)) - 1;
    				f.forDisplay = options_array[pos].split("=")[0];
    			} else {
    				f.forDisplay = false;
    				for (let i = 0; i < options_array.length; i++)
    					if (options_array[i].split("=")[0].localeCompare(value_str) == 0)
    						f.forDisplay = options_array[i].split("=")[0];
    				if (f.forDisplay === false) {
    					f.forDisplay = value_str;
    				}
    			}
    		} else {
    			f.forDisplay = (undefinedValue) ? "" : value_str;
    		}
        	f.value	 = f.forDisplay;
    	}
    }
    
    /**
	 * Get the parse as of a field. This is the data type of the respective
	 * configuration item child or, if no such child exists, the data type of a
	 * descriptor field. If no such descriptor field exist neither, "string" is
	 * returned.
	 */
    #getParseAs(fieldname) {
    	let type;
    	if (TfyhData.is_descriptor_field_name(fieldname)) 
    		type = TfyhData.get_descriptor_field_data_type(fieldname, this.#configItem.get_type());
    	else if (this.#formDefinition[fieldname].dataType)
    		type = this.#formDefinition[fieldname].dataType;
    	else
    		// sometimes forms have fields which do not correspond to a record
			// field, but need postprocessing prior to parsing. Kthis must be
			// done by the form handler then. Usual case: A dtaetime vlue
			// entered via a date and a time field.
    		type = "string";
   		return TfyhData.parseAs(type);
    }
    
    /* -------------------------------------------------------------- */
    /* ---- DISPLAY OF FORM ----------------------------------------- */
    /* -------------------------------------------------------------- */
    
    // for select and radio dynamic options definition ist avaible and defined
	// here.
    #getSelectOptionsArray (f) {
		// split type definition into 'select' and options
    	let options = f.type.substring(f.type.indexOf(" ") + 1);
    	let options_array = options.split(";");
		// special case: the selection is provided programmatically based on a
		// list
		if (options.substring(0, 4).toLowerCase().localeCompare("list") == 0) 
			options_array = cLists.defs.getOptions(options.substring(5));
		// special case: the selection is provided programmatically
		else if (options.substring(0, 4).toLowerCase().localeCompare("from") == 0) {
			// based on a configuration item
			let configItem = this.#appConfig.get_by_path(options.substring("from".length + 1));
			options_array = [ "=[empty]" ];
			for (let cname in configItem.get_children()) {
				let text = configItem.get_child(cname).get_descriptor_field("text_local_name");
				options_array.push(cname + "=" + text);
			}
		} else if (options.substring(0, 6).toLowerCase().localeCompare("global") == 0) {
			// use programmatically set global definitions.
			options = globalSelectOptions[options.substring("global".length + 1)];
			options_array = (options) ? options.split(";") : [ "0=???" ];
		}
		return options_array;
    }
    
    /**
	 * Get a single field of a list value. This adds a field to the form
	 * definitions. It is used to create inputs for entering a list value one by
	 * one.
	 */
    getListElementField(fieldname, list, i) {
    	let elementField = fieldname + "_" + i;
    	// copy the field definition
    	this.#formDefinition[elementField] = Object.assign({}, this.#formDefinition[fieldname]);
    	// change element definitions: name and "isList" parameter.
    	let f = this.#formDefinition[elementField];
    	f.name = elementField;
    	f.isList = false;
    	// preset the elements value
    	this.presetValue(elementField, list[i - 1]);
    	// now create the HTML and retrn it.
    	return this.#getFieldHtml(f, true);
    }
    
    /**
	 * Remove a single field, used for those which have been added as list
	 * element fields.
	 */
    removeField(fieldname) {
    	delete this.#formDefinition[fieldname];
    }

    /**
	 * Get a single field of a list value. This is to be able to enter a list
	 * value one by one.
	 */
    getValue(fieldname) {
    	let f = this.#formDefinition[fieldname];
    	return f.value;
    }
    
    /**
	 * Get the html representation of a single field. List fields are retrned as
	 * if they weren't a list field.
	 * 
	 * @param boolen
	 *            Set blankInput = true in order to get just the input fiel, but
	 *            no label.
	 */
    #getFieldHtml(f, blankInput) {
    	let fieldHtml = "";
    	let tags = (blankInput) ? "" : f.tags;
    	// start the input field with the label
    	let mandatory_flag = (f.required.localeCompare("*") == 0) ? "*" : "";
    	let isSubmit = (f.name.indexOf("submit") >= 0);
    	let inline_label = (f.type.localeCompare("radio") == 0) ||
                   (f.type.localeCompare("checkbox") == 0) ||
                   (f.type.localeCompare("input") == 0) || 
                   (!f.label || (f.label.length === 0));
    	// the form defintion contains both the form and some help text,
		// usually displayed within a right border frame. The help text
    	// shall not be returned in this function
    	let help_text = (f.name.indexOf("_help_text") >= 0);
    	let no_input = (f.name.indexOf("_no_input") >= 0);

    	// provide border an label styling. Include case of invalid input.
    	let style_str = "";
    	let validity_label_style_open = "";
    	let validity_label_style_close = "";
    	let hide = (f.isList && ! blankInput) ? "display:none;" : "";
    	let overflowVisible = (isSubmit) ? "overflow:visible;" : "";
    	if (this.validities[f.name] == false) {
        	style_str = 'style="border:1px solid #A22;border-radius: 0px;" ';
    		validity_label_style_open = "<span style=\"color:#A22;\">";
    		validity_label_style_close = "</span>";
        } else if (f.type.indexOf("textarea") >= 0)
            style_str += ' cols="' + ((f.maxlength < 6) ? f.maxlength : 5) 
            	+ '" rows="' + ((parseInt(f.size) < 10) ? parseInt(f.size) : 4) + '" ';
    	else if (f.size && f.size.length > 0)
        	style_str = 'style="width:' + f.size + ';' + hide + overflowVisible + '" ';
    	else if (hide.length > 0)
        	style_str = 'style="' + hide + overflowVisible + '" ';

    	// start with tags and show label for input
    	let inputSectionDiv = "\n  <div id='inputSection_"  + f.name 
    						+ "' style='word-wrap: break-word;overflow:hidden;padding:2px;'>";
        if (no_input && f.label && (f.label.localeCompare("_headline") == 0)) 
			fieldHtml += tags + inputSectionDiv + this.headline + "\n";
        else if (! help_text && ! blankInput) {
    		if (inline_label) // radio and checkbox
    			fieldHtml += tags + inputSectionDiv;
    		else // includes "_no_input" except headline
    			fieldHtml += tags + inputSectionDiv + validity_label_style_open + mandatory_flag 
    					+ ((f.label) ? f.label : "") + validity_label_style_close + "<br>\n";
    	} else if (blankInput)
    		fieldHtml += inputSectionDiv;
    	// now provide the previously entered or programatically provided
		// value. Wrap with htmlSpecialChars to prevent from XSS
        // https://stackoverflow.com/questions/1996122/how-to-prevent-xss-with-html-php
    	if (typeof f.forDisplay == 'undefined') {
    		if (isSubmit) {
    			f.forDisplay = f.value;	
    		} else if (f.value) {
        		if (f.value.indexOf("\$now") == 0) {
                    // special case date of now
        			var d = new Date();
        			f.forDisplay = d.getFullYear() + "-" + (d.getMonth()+1) + "-" + d.getDate();
        		} else
        			f.forDisplay = f.value;
    		}
    	}
    	// do not use invalid values for preset
    	if (this.validities[f.name] == false)
    		f.forDisplay = null;
    	
    	// predefine values for name, style, id and class attributes.
    	let type_str = (f.type.length > 0) ? 'type="' + f.type + '" ' : "";
    	let name_str = (f.name.length > 0) ? 'name="' + f.name + '" ' : "";
    	let id_str = ' id="cFormInput-' + f.name + '" ';
        // do not use the name for the submit button as id
        if (f.type.toLowerCase().indexOf("submit") == 0)
        	id_str = ' id="cFormInput-submit" ';
        // set class String: may be dedicated ID attribute within the class
		// field
        let nativeClass_str = ((f["class"].length > 0) && (f.class.indexOf('#') !== 0)) ? f["class"] + " " : "";
        let inputType_str = (f.type && (f.type.indexOf("select") >= 0)) ? "formselector " : "forminput ";
        let listEntryElement_str = (f.isList) ? "listEntryElement " : ""; 
        let classIsDisplayBold = (f["class"].indexOf("display-bold") >= 0);
        let displayBold_str = ((f.required && (f.required == "~")) && !classIsDisplayBold) ? "display-bold " : "";
        if (isSubmit || (displayBold_str.length > 0) || classIsDisplayBold) 
        	inputType_str = "";
        id_str = ((f["class"].length > 0) && (f.class.indexOf('#') === 0)) ? 'id="' + f["class"].substring(1) + '" ' : id_str;
        let class_str = "class='" + (nativeClass_str + inputType_str + listEntryElement_str + displayBold_str).trim() + "' "; 
        
        let disabled_flag = ((f.required.localeCompare("!") == 0) || (displayBold_str.length > 0)) ? "disabled" : "";
    	
    	// special case: select field.
    	if (f.type && f.type.indexOf("select") >= 0) {
    		fieldHtml += "<select " + name_str + style_str + class_str + id_str + disabled_flag + ">\n";
    		// add options
    		let options_array = this.#getSelectOptionsArray(f);
    		// code all options as defined
    		options_array.forEach(function(option) {
    			let nvp = option.split("=");
    			if (nvp.length == 1) nvp.push(nvp[0]); 
    			let selected = (nvp[0].localeCompare(f.forDisplay) == 0) ? "selected " : "";
    			fieldHtml += '<option ' + selected + 'value="' + nvp[0].trim() + '">' 
			        	+ nvp[1].trim() + "</option>\n";
             });
             fieldHtml += "</select>\n";
        
    	} else if (f.type && f.type.indexOf("radio") >= 0) {
            // special case: radio group (similar to select field case)
            // split type definition into 'radio' and options
     	    options = f.type.substring(f.type.indexOf(" ") + 1);
    		options_array = options.split(";");
    		// code all options as defined
    		options_array.forEach(function(option) {
    			// wrap into radiobutton frame first.
    			let nvp = option.split("=");
    			let checked = (nvp[0].localeCompare(f.forDisplay) != 0) ? "checked " : "";
                fieldHtml += '<label class="cb-container">' + f.label + "\n";
                // no class definitions allowed for radio selections
                fieldHtml += '<input ' + type_str + name_str + style_str + '" value="' + nvp[0]
                     	+ checked + id_str + disabled_flag + '>' + nvp[1] + '<br>\n' + "<br>\n";
                fieldHtml += '<span class="cb-radio"></span></label>';
            });
        
        } else if (f.type && f.type.indexOf("checkbox") >= 0) {
        	let checked = (f.forDisplay && (f.forDisplay.length > 0) 
        			&& (f.forDisplay.localeCompare("false") != 0)) ? 'checked class="checked-on" ' : 'class="checked-off" ';
            fieldHtml += '<label class="cb-container"  style="margin-top:0.5em">' + f.label + "\n";
            // no class definitions allowed for checkboxes
            fieldHtml += '<input ' + type_str + name_str + style_str;
            // In case of a checkbox, set checked for not-empty other than
			// "false"..
            if (f.forDisplay && (f.forDisplay.length > 0) && (f.forDisplay.localeCompare("false") != 0))
                fieldHtml += " checked";
            fieldHtml += id_str + disabled_flag + '><span class="cb-checkmark"></span></label>';
        
        } else if (f.type.indexOf("textarea") >= 0) {
            if (! f.forDisplay) 
            	f.forDisplay = ""; 
            fieldHtml += '<textarea ' + name_str + style_str + class_str + id_str + disabled_flag + '>'
            	+ f.forDisplay + '</textarea><br>' + "\n";
        
        } else if (! help_text && ! no_input && f.name && (f.name.length > 0)) {
            // default input type.
            fieldHtml += "<input " + type_str + name_str + style_str;
            // special case: use of the class field for id definition,
			// validation calls or value display instead of the entry field.
            if (((f.class.length > 0) || (f.required == "~")) && (f.class.indexOf("validate:") != 0)
            		 && (f.class.indexOf("call:") != 0) && (f.class.indexOf("#") != 0)) 
           		fieldHtml += class_str;
            else
                fieldHtml += 'class="forminput' + listEntryElement_str + '" ';
            // maximum length definition
            if (f.maxlength && f.maxlength.length > 0)
                fieldHtml += 'maxlength="' + f.maxlength + '" ';
            // set value.
            if (f.forDisplay && (f.forDisplay.length > 0))
                fieldHtml += 'value="' + f.forDisplay + '" ';
            fieldHtml += id_str + disabled_flag + ">\n";
            // add the inline label.
            if (inline_label && !isSubmit)
                fieldHtml += "&nbsp;" + validity_label_style_open + mandatory_flag + ((f.label) ? f.label : "") +
                        validity_label_style_close + "\n";
        }
    	// add list button for list entries
    	if (f.isList && !blankInput) {
    		if (!f.forDisplay) 
    			f.forDisplay = "";
    		fieldHtml += f.forDisplay + " - <a href='#' onclick=handleEvent(\"editList_"  + f.name + "\")>" + _("7j6J2V|edit list") + "</a>";
    	}
    	return fieldHtml + "</div>";
    }
    
    /**
	 * Return a html code of this form based on its definition. Will not return
	 * the help text. Set previousErrors to some error String to disply the same
	 * form, but with error notice.
	 */
    getHtml (previousErrors) {
    	let formErrors = "";
    	if ((typeof previousErrors !== 'undefined') && (previousErrors.length > 0)) {
    		formErrors = previousErrors;
    		this.presetValues(this.#entered);
    	}
        if (Object.keys(this.#formDefinition).length == 0)
            return "<h4>" + this.#formName + "</h4><p>Empty form template.</p>";
        // start the form. The form has no action in itself and shall not reload
		// when submitting. Therefore it is implemented as "div" rather than
		// "form"
        let form = '		<div>\n';
        if (formErrors) 
        	form += "<p style=\"color:#A22;\">" + formErrors + "<p>";
        // ----------------------------------------------------------
        // Build form as a table of input fields. Tags define columns
        // ----------------------------------------------------------
        for (let fieldname in this.#formDefinition) {
        	let f = this.#formDefinition[fieldname];
        	form += this.#getFieldHtml(f, false);
        }
        form += "	</div>\n";
        // console.log(form);
        return form;
    }
    
    /**
	 * For list editing the input fieldd is hidden and the list and a list edit
	 * link displayed. This is to get the html code for it.
	 */
    updateField(fieldname) {
    	let f = this.#formDefinition[fieldname];
    	let tagsCache = f.tags;
    	f.tags = "";
    	let fieldHtml = this.#getFieldHtml(f, false);
    	f.tags = tagsCache;
		if ($('#inputSection_' + fieldname) && $('#inputSection_' + fieldname).parent())
			$('#inputSection_' + fieldname).parent().html(fieldHtml);
    }
    
    /**
	 * Return a html code of the help text.
	 */
    getHelpHtml() {
    	let help_html = "";
        for (let fkey in this.#formDefinition) {
            // the fo defintion contains both the form and some help text,
			// usually displayed within
            // a right border frame. Only the help text shall be returned in
			// this function
        	let f = this.#formDefinition[fkey];
            if (f.name.indexOf("_help_text") == 0)
            	help_html += f.tags + f.label + "\n";
        }
        return help_html;
    }
    
    /* -------------------------------------------------------------- */
    /* ---- EVALUATION OF FORM ENTRIES ------------------------------ */
    /* -------------------------------------------------------------- */
    
    /**
	 * Three step form evaluation: changed? syntactical check (parsed)?
	 * semantical check (validated)?
	 */
    evaluate(replace_insecure_chars = true) {
    	// read all changed values.
    	this.#readEntered(replace_insecure_chars);
    	// parse and validate the changed values
    	TfyhData.clear_findings();
    	let errors = this.#parseEntered();
    	TfyhValidate.clear_findings();
    	errors += this.#checkValidity();
    	return errors;
    }

    /**
	 * Read a single field.
	 */
    readField(fieldname) {
    	let f = this.#formDefinition[fieldname];
    	if (!f)
    		return null;
		let id = '#cFormInput-' + f.name;
		// read the value
		let input = $(id)[0];
		// in dynamic forms an input may no more exits, e.g. in the list entry
		// dialog
		if (! input)
			return null;
		let value = (f.isList) ? f.forDisplay : input.value;
		// special case: checkboxes are "on" or "", to be compatible
		// with the server side PHP implementation and to detect the
		// change.
		if (f.type && f.type.localeCompare("checkbox") == 0) 
			value = ($(id).is(':checked')) ? "on" : "";
		// special case: selected options have a different way of
		// getting at.
		if (!f.isList && f.type && f.type.toLowerCase().startsWith("select")) 
			value = $(id).find(":selected").val();
		// finally look for localized values display
		let forDisplay = (f.name.startsWith("text_")) 
				? _("" + f.forDisplay) : "" + f.forDisplay;
				// keep previously recorded change events on field
		this.#changed[f.name] = (this.#changed[f.name]) || (forDisplay != value);
		// replace "undefined" to circumvent execution errors
		if (!value) 
			value = "";
		return value;
    }
    
    /**
	 * read all values into the "inputs" of this form object as they were
	 * provided via the post or get method. This function will set all inputs of
	 * the form object, i. e. empty form inputs will delete a previously set
	 * value within a field. Strings are UTF-8 encoded. Not validation applies
	 * at this point.
	 */
    #readEntered (replace_insecure_chars = true) {
        this.#entered = {};
        for (let fkey in this.#formDefinition) {
        	let f = this.#formDefinition[fkey];
        	if (f.useValue && this.#isField(f) && (f.name.toLowerCase() != "submit")) {
        		let value = this.readField(fkey)
        		if (replace_insecure_chars !== false)
        			value = value.replace(/`/g, "\u{055A}").replace(/</g, "\u{227A}").replace(/>/g, "\u{227B}")
        					.replace(/;/g, "\u{037E}");
       			this.#entered[f.name] = value;
        	}
        }
    }
    
    /**
	 * Parse all entered values into the inputs field and report errors.
	 */
    #parseEntered() {
    	let formErrors = "";
        this.#parsed = {};
        for (let fkey in this.#entered) {
        	this.#parsed[fkey] = TfyhData.parse(this.#entered[fkey], this.#getParseAs(fkey));
        	if (TfyhData.validation_errors_count > 0)
        		formErrors += fkey + ": " + TfyhData.validation_result + " ";
        }
        return formErrors;
    }
    
    /**
	 * get the parsed value or the array of all parsed values
	 */
    getParsed(fieldname = false) {
    	if (fieldname)
    		return this.#parsed[fieldname];
    	else 
    		return this.#parsed;
    }
    
    /**
	 * get the changed flag or the array of all changed flags
	 */
    getChanged(fieldname = false) {
    	if (fieldname)
    		return this.#changed[fieldname];
    	else 
    		return this.#changed;
    }
    
    /**
	 * Set the respective changed flag to true. This is needed to collect the
	 * result list editing.
	 */
    setChanged(fieldname) {
    	this.#changed[fieldname] = true;
    }
    
    /**
	 * get the parsed values as String. Set includingUnchanged = true to get all
	 * values, not only the changed ones. Set the encoding to use, default is
	 * "csv" - which is the encoding at the API.
	 */
    getParsedReformatted(includingUnchanged, encoding = "csv") {
    	let keys = Object.keys(this.#parsed);
    	let reformatted = {};
    	for (let key of keys) {
    		if (this.#changed[key] || includingUnchanged) {
        		let value = this.#parsed[key];
        		let type = (this.#configItem && this.#configItem.has_child(key)) ? this.#configItem.get_child(key).get_type() : "string";
        		// do not guess the type, e.g. when using a date and a time
				// field separately for a datetime value, this will prevent the
				// assembly.
        		reformatted[key] = TfyhData.format(value, type, encoding);
    		}
    	}
   		return reformatted;
    }
    
    /**
	 * Use validated input values for the 'item'.
	 */
    useParsedFor(item) {
    	for (let iname in this.#parsed) {
    		if (item.has_child(iname))
    			item.get_child(iname).set_descriptor({ value_current : this.#parsed[iname] });
    		else if (TfyhData.is_descriptor_field_name(iname) || (TfyhData.item_keys.indexOf(name) >= 0)) {
    			let field = {};
    			field[iname] = this.#parsed[iname];
    			item.set_descriptor(field);
    		}
    	}
    }
    
    /**
	 * Check whether this field is a form field
	 */
    #isField (fieldDefinition) {
        return (fieldDefinition["type"].toLowerCase().localeCompare("submit") != 0) &&
               (fieldDefinition["name"].length > 0) &&
               (fieldDefinition["name"].toLowerCase().indexOf("_help_text") != 0) &&
               (fieldDefinition["name"].toLowerCase().indexOf("_no_input") != 0);
    }
    
    /**
	 * Check whether this field is a date type field
	 */
    #isDate (fieldDefinition) {
        return (fieldDefinition["type"].toLowerCase().localeCompare("date") == 0);
    }
    
    /**
	 * Check the validity of all inputs within the form. Uses the type
	 * declaration in the form to deduct the required data type and the
	 * "required" field to decide, whether a field must be filled or not. Will
	 * also return an error, if the value contains a '<' and the word 'script'
	 * to prevent from cross site scripting.
	 */
    #checkValidity (password_rule = 1) {
    	let formErrors = "";
    	TfyhValidate.clear_findings();
        for (let key in this.#parsed) {
            let value = this.#parsed[key];
            let definition = this.#formDefinition[key];
            // check empty inputs. They always comply to the format, if no
            // entry was required.
            if ((value == null) || value.length < 1) {
                // input is empty
                if (definition["required"] == "*") {
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
                	// input type the following checks are for legacy reasons
					// only
                	let type = definition["type"].toLowerCase(); 
                	let dataType = definition.dataType;
                    if (type.localeCompare("email") == 0) {
                    	let parts = value.split("@");
                    	let isValid = (value.length == 2);
                    	isValid = isValid && (parts[1].length > 5) && (parts[1].indexOf(".") > 0);
                        if (! isValid) {
                            formErrors += 'Bitte bei "' + definition["label"] +
                                           '" eine gültige E-Mail-Adresse eingeben<br>';
                            this.validities[key] = false;
                        }
                    } else if ((type.localeCompare("date") == 0) && (dataType !== 'string')) {
                    	// dates may not be parsed due to later date + time
						// assembly
                        if (isNaN(value)) {
                            formErrors += 'Bitte bei "' + definition["label"] +
                                         '" eine gültiges Datum eingeben (nicht "' + value +
                                         '")<br>';
                            this.validities[key] = false;
                        }
                    } else if ((type.localeCompare("password") == 0) && (password_rule > 0)) {
                    	let errors = TfyhToolbox.checkPassword(value);
                        if (errors.length > 0) {
                            formErrors += 'Das Passwort ist nicht ausreichend sicher in "' +
                                    definition["label"] + '" ' + errors + '<br>';
                            this.validities[key] = false;
                        }
                    }
                } 
                // validate lookup fields (legacy) as indicatied by the class
				// value
                if ((definition["class"].length > 9) && (definition["class"].indexOf("validate:") == 0)) {
                	let guids = cLists.names[definition["class"].substring(9) + "_names"];
                	let guid = (guids) ? guids[value] : false;
                    if (! guid) {
                        formErrors += 'Der Name "' + value + '" wurde in der Liste "' + definition["class"].substring(9) +
                        	'" nicht gefunden, muss aber dort enthalten sein.<br>';
                        this.validities[key] = false;
                    }
                } else if ((definition["class"].length > 5) && (definition["class"].indexOf("call:") == 0)) {
                	let call = definition["class"].substring(5);
                	let callModule = call.split(".")[0];
                	let callFunction = call.split(".")[1];
                	let approval = (window[callModule] && window[callModule][callFunction]) ? window[callModule][callFunction](this.#parsed) : true;
                    if (approval !== true) {
                        formErrors += approval + '<br>';
                        this.validities[key] = false;
                    }
                }
                // validate against record definition constraints
                // Note: there is no validation against descriptor field
				// constraints.
                if (this.#configItem && this.#configItem.has_child(key)) {
                	TfyhValidate.validate_parsed_value (this.#configItem.get_child(key), value);
                }
            }
        }
    	if (TfyhData.getErrors().length > 0)
            formErrors += _("yalySQ|Data validation failed. ...", TfyhData.getErrors()) + "<br>";
    	if (TfyhValidate.has_findings())
            formErrors += _("31KjuG|Values violate rules. Fi...", TfyhValidate.get_findings()) + "<br>";
    	this.formErrors = formErrors;
        return formErrors;
    }
		
}
