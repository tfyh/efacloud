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
 * A fully static utility class to provide data parsing, validation and
 * formatting. Each data object has both a String and an inMemory
 * representation. Parsing converts String to inMemory, validation checks the
 * value against its limits, and formatting converts inMemory to String using
 * the proper local formats. An empty String is parsed as an empty String for
 * String data types, a boolean false for boolean data types else as NULL. The
 * very last parsing, cleansing and validation result is cached in memory and
 * must be read immediately after parsing and cleansing, in particular, if null
 * is returned, because this may be either really null or a parsing, cleansing
 * or validation error indication. Formatting applies rules according to the
 * language chosen. Two special languages are "csv", which formats for data base
 * write actions and "csv" which is the default and formats for file write.
 * Quotation will nor be provided.
 */
class TfyhData {

    // for 32 bit - 2147483648
    static #int_min = - 9223372036854775808;
    static #int_max = 9223372036854775807;
    // for 32 bit= - 3.40E+38
    static #float_min = - 1.79E+308;
    static #float_max = 1.79E+308;
    // Date and time values
    static #date_min = new Date("1000-01-01");
    static #date_max = new Date("2999-12-31");
    static #time_min = - 359999; // -99:59:59
    static #time_max = 359999; // 99:59:59
    static #datetime_min = new Date("1000-01-01 00:00:00");
    static #datetime_max = new Date("2999-12-31 23:59:59");
    // string length limit which will fit into a MySQL data field
    static #string_size = 4096;
    // string lengthlimit which will fit into a MySQL (medium = DEFAULT)
	// Text blob
    static #text_size = 65536;
    
    static get forever_seconds() { return 9.223372e+15; }

    static #parseAs2native = {
    	boolean : "boolean",
        date : "Date",
        datetime : "Date",
    	float : "number",
    	int : "number",
    	none : "string",
        string : "string",
        time : "number"
    };
    
    // Note: number and date have multiple mappings, the best precision option
	// is used
    static #native2parseAs = {
    	boolean : "boolean",
        date : "datetime",
    	number : "float",
        string : "string"
    };
    
    static #date_formats = {
    	sql : {
    		date : "Y-m-d",
    		datetime : "Y-m-d H:i:s"
    	},
    	csv : {
    		date : "Y-m-d",
    		datetime : "Y-m-d H:i:s"
    	},
    	de : {
    		date : "d.m.Y",
    		datetime : "d.m.Y H:i:s"
    	},
    	en : {
    		date : "Y-m-d",
    		datetime : "Y-m-d H:i:s"
    	},
    	fr : {
    		date : "d/m/Y",
    		datetime : "d/m/Y H:i:s"
    	},
    	it : {
    		date : "d/m/Y",
    		datetime : "d/m/Y H:i:s"
    	},
    	nl : {
    		date : "d.m.Y",
    		datetime : "d.m.Y H:i:s"
    	}
    };
    static #decimal_point_for = [ "sql","csv","en" ];
    static #write_null_as_NULL_for = [ "sql" ];
    static #write_boolean_as_true_false_for = [ "csv" ];
    static #write_boolean_as_1_0_for = [ "sql" ];

    static #errors = [];
    static #warnings = [];

    static #descriptorFields = {};
    static item_keys = ["uid","name","parent"];

    
    static typeTemplates = {};
    static objectTemplates = {};
    static #valueParseAs = {};

    /**
	 * clear the TfyhData.#errors and TfyhData.#findings
	 */
    static clear_findings()
    {
        TfyhData.#errors = [];
        TfyhData.#warnings = [];
    }

    /**
	 * Set the TfyhData.validation_error according to the reason of validation
	 * failure
	 */
    static #addFinding(reason_code, violating_value_str, violated_limit_str)
    {
        if (reason_code == 1) 
            TfyhData.#errors.push(_("Im6RzC|Format error in °%1°.", violating_value_str));
        else if (reason_code == 2)
        	TfyhData.#errors.push(_("4j2U0W|Numeric value required i...", violating_value_str));
        else if (reason_code == 3)
        	TfyhData.#warnings.push(_("IL4ihl|°%1° is too small. Repla...", violating_value_str, violated_limit_str));
        else if (reason_code == 4)
        	TfyhData.#warnings.push(_("O0EFCI|°%1° is too big. Replace...", violating_value_str, violated_limit_str));
        else if (reason_code == 5)
        	TfyhData.#errors.push(_("jN5Dvb|Unknown data type / vali...", violating_value_str));
        else if (reason_code == 6)
        	TfyhData.#errors.push(_("FHFWAq|The value°s native type ..."));
        else if (reason_code == 7)
        	TfyhData.#errors.push(_(
                    "R3IpuB|The value°s data type °%...", violating_value_str));
        else if (reason_code == 8)
        	TfyhData.#errors.push(_(
        			"fWxdb7|String °%1° too long. Cu...", violating_value_str, violated_limit_str));
    }

	static getErrors() {
		return TfyhData.#errors;
	}

	static getWarnings() {
		return TfyhData.#warnings;
	}
	
	static initParseAs(csv) {
		let rows = TfyhToolbox.csvToArray(csv);
		for (let i in rows)
			TfyhData.#valueParseAs[rows[i][0]] = rows[i][1];
		delete TfyhData.#valueParseAs.data_type;
	} 

    static init()
    {
        // no duplicate initialization
        if (Object.keys(TfyhData.#descriptorFields).length > 0)
            return;
        // parseAs fields must be initialzed first
        if (TfyhData.#valueParseAs.length == 0){
        	alert("parseAs fields must be initialzed first!");
        	_stopDirty_;
        }
        // initialize the descriptor_fields first.
    	let descriptor_fields_records = TfyhToolbox.csvToObject(window.localStorage.getItem("descriptor"));
        // descriptor field definitions are all Strings, no parsing required
        for (let descriptor_field_record of descriptor_fields_records) {
            let field_name = descriptor_field_record.name;
            TfyhData.#descriptorFields[field_name] = {};
            for (let key in descriptor_field_record) 
                TfyhData.#descriptorFields[field_name][key] = descriptor_field_record[key];
            // remove the structure definition fields which shall never be
			// parsed.
            delete TfyhData.#descriptorFields.uid;
            delete TfyhData.#descriptorFields.parent;
            delete TfyhData.#descriptorFields.name;
        }
        // remove root node of descriptor branch from descriptor_fields list
        delete TfyhData.#descriptorFields.descriptor;
        
        // initialize the "validatAs" field first for parsing
    	let typeTemplates_records = TfyhToolbox.csvToObject(window.localStorage.getItem("dataTypes"));
        for (let typeTemplates_record of typeTemplates_records) {
        	let data_type_name = typeTemplates_record.name;
            TfyhData.typeTemplates[data_type_name] = {};
            TfyhData.typeTemplates[data_type_name].value_type = typeTemplates_record.value_type;
            // TfyhData.#valueParseAs was initialized earlier, because this is
			// not a descriptor field.
        }
        // now parse all descriptors
        for (let typeTemplates_record of typeTemplates_records) {
        	let data_type_name = typeTemplates_record.name;
            TfyhData.typeTemplates[data_type_name] = TfyhData.parse_descriptor(typeTemplates_record);
        }
        // add a default for unknown types
        TfyhData.typeTemplates["_of_value"] = TfyhData.clone_descriptor(TfyhData.typeTemplates.unknown);
        TfyhData.typeTemplates[""] = TfyhData.clone_descriptor(TfyhData.typeTemplates.unknown);
        
        // remove root node of dataTypes branch from typeTemplates list
        delete TfyhData.typeTemplates.dataTypes;
        
        // now take the passed limits as validation parameters.
        TfyhData.#int_min = TfyhData.typeTemplates.int.value_min;
        TfyhData.#int_max = TfyhData.typeTemplates.int.value_max; 
        TfyhData.#float_min = TfyhData.typeTemplates.float.value_min;
        TfyhData.#float_max = TfyhData.typeTemplates.float.value_max;
        TfyhData.#date_min = TfyhData.typeTemplates.date.value_min;
        TfyhData.#date_max = TfyhData.typeTemplates.date.value_max;
        TfyhData.#time_min = TfyhData.typeTemplates.time.value_min;
        TfyhData.#time_max = TfyhData.typeTemplates.time.value_max;
        TfyhData.#datetime_min = TfyhData.typeTemplates.datetime.value_min;
        TfyhData.#datetime_max = TfyhData.typeTemplates.datetime.value_max;
        TfyhData.#string_size = TfyhData.typeTemplates.string.value_size;
        TfyhData.#text_size = TfyhData.typeTemplates.text.value_size;
    }

    /**
	 * Get the parsing rule for a data type
	 */
    static parseAs (dataType)
    {
        // valid data type
        if (TfyhData.#valueParseAs[dataType])
            return TfyhData.#valueParseAs[dataType];
        // object or any other. Use string as the most tolerant parsing rule.
        return "string";
    }
    
    /**
	 * check whether the name is a reserved name, i. e. one of the descriptor
	 * fields or one of "name", "uid", "parent"
	 */
    static is_reserved_name(name) {
    	return ((Object.keys(TfyhData.#descriptorFields).indexOf(name) >= 0) || 
    			(TfyhData.item_keys.indexOf(name) >= 0));
    }
    
    /**
	 * Check whether a value is "empty", i. e. either null, undefined, or a
	 * String of length 0.
	 */
    static is_empty (value)
    {
        // Note: is_string(null) = false.
        return (typeof value == 'string') ? (value.length > 0) 
        		: ((value != null) && (typeof value != 'undefined'));
    }
    
    /**
	 * Check whether the name is a name of a descriptor field.
	 */
    static is_descriptor_field_name(name) {
    	return (Object.keys(TfyhData.#descriptorFields).indexOf(name) >= 0);
    }

    /**
	 * Check whether the data type or object template exists.
	 */
    static #validType (dataType)
    {
        return (TfyhData.#valueParseAs[dataType] || TfyhData.objectTemplates[dataType]);
    }

/* ------------------------------------------------------------------------ */
/* ----- DESCRIPTOR HANDLING ---------------------------------------------- */
/* ------------------------------------------------------------------------ */
		    
    /**
	 * get a default descriptor clone for a given data type. Returns the "none"
	 * type descriptor, if the data type is not matched.
	 */
    static get_descriptor_template(dataType) {
        // get default descriptor
        if (TfyhData.typeTemplates[dataType])
            // known simple data type
            return TfyhData.clone_descriptor(TfyhData.typeTemplates[dataType]);
        else if (TfyhData.objectTemplates[dataType])
            // known object data type
            return TfyhData.objectTemplates[dataType].get_descriptor_clone();
        else
            // unknown data type
            return TfyhData.clone_descriptor(TfyhData.typeTemplates.unknown);
    }
    
    /**
	 * Get the data type for this descriptor field. If the data type is
	 * "_of_value", return $of_value. If the $descriptor_field_name is not
	 * matched, return "string" (in particular this can haappen for the reserved
	 * names uid, parent, name).
	 * 
	 * @return array the names of all descriptor fields
	 */
    static get_descriptor_field_names ()
    {
        let names = [];
        for (let name in TfyhData.#descriptorFields) 
            names.push(name);
        return names;
    }

    /**
	 * Get the data type for this descriptor field. If the data type is
	 * "_of_value", return $of_value. If the $descriptor_field_name is not
	 * matched, return "string".
	 */
    static get_descriptor_field_data_type (descriptor_field_name, of_value)
    {
        if (TfyhData.#descriptorFields[descriptor_field_name]) {
            let dt = TfyhData.#descriptorFields[descriptor_field_name].value_type;
            return (dt.localeCompare("_of_value") == 0) ? of_value : dt;
        }
        return "string";
    }

    /**
	 * Simple getter.
	 */
    static get_descriptor_field_local_name (descriptor_field_name)
    {
        if (TfyhData.#descriptorFields[descriptor_field_name]) 
        	return _(TfyhData.#descriptorFields[descriptor_field_name].text_local_name);
        return _("hdz6LT|unknown descriptor field");
    }

    /**
	 * Get a limit for the respective descriptor template.
	 * 
	 * @return int|float the respective limit
	 */
    static get_descriptor_template_limit (dataType, limitIndex)
    {
        if (limitIndex == 0) {
            if (TfyhData.typeTemplates[dataType].value_min)
                return TfyhData.typeTemplates[dataType].value_min;
        } else if (limitIndex == 1) {
            if (TfyhData.typeTemplates[dataType].value_max)
                return TfyhData.typeTemplates[dataType].value_max;
        } else if (limitIndex == 2) {
            if (TfyhData.typeTemplates[dataType].value_size)
                return TfyhData.typeTemplates[dataType].value_size;
        }
        // no value returned, i. e. no value existing. return null.
        return null;
    }

    /**
	 * Parse a descriptor with only String values as read from a csv definition
	 * and return it with all fields. Start with default descriptor for the
	 * given data type and overwrite those fields defined by the
	 * $descriptor_record.
	 */
    static parse_descriptor(descriptor_record) {
        let descriptor_data_type = (descriptor_record.value_type) ? descriptor_record.value_type : "string";
        if (! descriptor_data_type.startsWith(".") && 
        		! TfyhData.typeTemplates[descriptor_data_type])
            // this will only happen during initialiization
            TfyhData.typeTemplates[descriptor_data_type] = {};
        
        // initialize with default. Note that this was already parsed.
        let parsed = TfyhData.get_descriptor_template(descriptor_data_type);
        // parse all known descriptor fields and overwrite the default, if
		// matched
        for (let name in TfyhData.#descriptorFields) {
        	let field = TfyhData.#descriptorFields[name];
            let field_data_type = (field.value_type == "_of_value") ? descriptor_data_type : field.value_type;
            // Parse all fields except the empty ones and the item keys uid,
			// parent, and name
            if (descriptor_record[name] && (TfyhData.item_keys.indexOf(name) < 0)) {
            	let parsed_value = TfyhData.parse(descriptor_record[name], field_data_type);
                // the parse result will be false on failures, but in these
				// cases the value shall rather be null except for boolean
				// parsing.
                parsed[name] = ((field_data_type != "boolean") && (parsed_value === false)) 
                				? null : parsed_value;
            }
        }
    	// ensure that at least the parsing & formatting rules are defined
    	if (!parsed.value_type)
    		parsed.value_type = "string";
        // remove min/max = false defaults for boolean created by parsing
        let is_boolean = (parsed.value_type == "boolean");
        if (is_boolean) {
        	if (! typeof parsed.value_min == 'undefined')
                delete parsed.value_min;
        	if (! typeof parsed.value_max == 'undefined')
                delete parsed.value_max;
        }
        return parsed;
    }

    /**
	 * Clone a descriptor by deep array copy. Date/datetime values are copied by
	 * object cloning. Because they are objects a simple passing as parameter
	 * may not create a full copy.
	 */
    static clone_descriptor(descriptor)
    {
        let clone = {};
        for (let name in descriptor) {
        	let value = descriptor[name];
        	if (value == null) 
        		clone[name] = null;	
        	else if (Array.isArray(value))
        		clone[name] = value.slice();	
            else if (typeof value === "object")
            	// special case only for date, datetime, and null
                clone[name] = new Date(value.getTime());
            else
                clone[name] = value;
        }
        return clone;
    }

    /**
	 * Cleanse the descriptor by removing all elements which have a vaue that is
	 * identical to the default for the chosen data type. Upon construction
	 * these defaults are the starting point and therefore always included. But
	 * for writing a descriptor, defaults carry redundancy around.
	 */
    static clone_without_defaults(descriptor)
    {
        // get default descriptor
    	let datatype = descriptor.value_type;
    	let defaults = TfyhData.get_descriptor_template(datatype);
        if (defaults.value_type.localeCompare("unknown") == 0)
            // unknown data type
            return TfyhData.clone_descriptor(descriptor);
        // clone descriptor
    	let cleansedDescriptor = TfyhData.clone_descriptor(descriptor);
        // remove equal values
        for (let name in descriptor) {
            let field = descriptor[name];
            let name_lc = name.toLowerCase();
            // do not remove the data type, current value and text local name.
            let is_current = (name_lc == "value_current");
            let is_type = (name_lc == "value_type");
            let is_local_name = (name_lc == "text_local_name");
            let keep = (is_current || is_type || is_local_name);
            if (! keep && TfyhData.#isEqualValues(field, defaults[name]))
            	delete cleansedDescriptor[name];
        }
        return cleansedDescriptor;
    }
    
/* ------------------------------------------------------------------------ */
/* ----- DATA EQUALITY ---------------------------------------------------- */
/* ------------------------------------------------------------------------ */
		    
    /**
	 * Drilldown for differnce check in arrays. Keys must also be identical, but
	 * not in their sequence.
	 */
    static diff_arrays(a, b)
    {
    	let diff = "";
    	let keys_checked = [];
        for (let k in a) {
        	keys_checked.push(k);
            diff += TfyhData.#diffSingle(a[k], b[k]);
        }
        for (let k in b) {
            if ($.inArray(k, keys_checked) < 0)
                diff +=  _("6H3gWj|Extra field in B.");
        }
        return diff;
    }

    /**
	 * Create a difference statement for two values.
	 */
    static #diffSingle(a, b)
    {
    	let diff = "";
        // start with simple cases: null equality
        if (a == null)
            diff += (b == null) ? "" : _("kSCib2|A is null, but B is not ...") + " ";
        // start with simple cases: array type equality
        else if (Array.isArray(a) && ! Array.isArray(b))
            diff += _("Q3220i|A is an array, but B not...") + " ";
        else if (! Array.isArray(a) && Array.isArray(b))
            diff += _("Bczhcw|A is a single value, but...") + " ";
        
        // drilldown in case of two arrrays
        else if (Array.isArray(a))
            diff += TfyhData.diff_arrays(a, b);
        
        // single values
        // boolean
        else if (typeof a == "boolean")
            diff += (typeof b == "boolean") ? ((a == b) ? "" : _("ofZjXx|boolean A is not(boolean...")) : _(
                    "KH8xj4|A is boolean, B not.");
        // integer or time or float. Javascript does not distinguish int
		// and float
        else if (typeof a == "number")
 		    diff += (typeof b == "number") ? ((a == b) ? "" : _(
 		            		"1l4ZE2|number A != number B.")) : _("ncH3n3|A is a number, B not.");
        // date, time, datetime
        else if (typeof a == "object") {
            // only Date objects are allowed in the Tfyh data context as
			// value objects
            if (typeof b != "object")
                diff += _("JiCPzn|A is object, B not.");
            else if (a.constructor.name != "Date")
                diff += _("wMABQI|A is object, but not a D...");
            else if (b.constructor.name != "Date")
                diff += _("lEUpOn|A is Date, B not.");
            diff += (a.toISOString() == b.toISOString()) ? "" : _(
                    "Gh6rpp|datetime A != datetime B...");
        } else if (typeof a == "string") // String
            diff += (typeof b == "string") ? ((a == b) ? "" : _("gCk9cA|string A differs from st...")) : _(
                    "QYQwlG|A is a string, B not.");
        // no other values suported. They are always regarded as
		// unequal.
        else
            diff += _("CbG7UM|equality check failed du...") + a + "'.";
        
        // echo " result: " + diff + "<br>";
        return diff;
    }

    /**
	 * Drilldown for equality check in arrays. Keys must also be identical, but
	 * not in their sequence. a[k] == null is regarded as equal to both b[k] not
	 * set and b[k] = null. The same vice versa.
	 */
    static #isEqualArrays(a, b)
    {
    	let equal = true;
        var keys_collected = [];
        for (let k in a) {
            equal &= TfyhData.#isEqualValues(a[k], b[k]);
            keys_collected.push(k);
        }
        for (let k in b) {
        	if ($.inArray(k, keys_collected) < 0)
        		equal = false;
        }
        return equal;
    }

    /**
	 * Check whether two values of data are equal. Special cases: null == null,
	 * type safety (i. e. corresponds to the ==== operand), array drilldown and
	 * equality means equality on all levels, date/time/datetime equality as
	 * "Y-m-d H:i:s - equality", floating point numbers equal to
	 * PHP_FLOAT_EPSILON precision only.
	 */
    static #isEqualValues(a, b)
    {
    	let equal;
        // start with simple cases: null equality
        if (typeof a == 'undefined')
            equal = (typeof a == 'undefined');
        else if (a == null)
            equal = (b == null);
        // start with simple cases: array type equality
        else if (Array.isArray(a) && ! Array.isArray(b))
            equal = false;
        else if (! Array.isArray(a) && Array.isArray(b))
            equal = false;
        
        // drilldown in case of two arrrays
        else if (Array.isArray(a))
            equal = TfyhData.#isEqualArrays(a, b);
        
        // single values
        // boolean
        else if (typeof a == "boolean")
            equal = (typeof a == "boolean") && (a == b);
        // integer or time or floating point
        else if (typeof a == "number")
            equal = (typeof a == "number") && (a == b);
        // date, time, datetime
        // date, time, datetime
        else if (typeof a == "object")
            // only Date objects are allowed in the Tfyh data context as
			// value objects
        	equal = (typeof b != "object") && 
        		(a.constructor.name == "Date") && (b.constructor.name == "Date") && 
        		(a.toISOString() == b.toISOString());
        // String
        else if (typeof a == "string")
            equal = (typeof b == "string") && (a == b);
        // no other values suported. They are always regarded as
		// unequal.
        else
            equal = false;
        return equal;
    }

/* ---------------------------------------------------------------------- */
/* ----- DATA VALIDATION ------------------------------------------------ */
/* ----- The result is a native type value, null in case of errors. ----- */
/* ----- Errors can occur on parsing and limit or rules check. ---------- */
/* ----- Errors are documented in the public validation_error. ---------- */
/* ---------------------------------------------------------------------- */
		    
    /**
	 * Check whether the native type of value matches the data type
	 * expectations. The value must not be an array.
	 */
    static #isMatchingTypeSingle(type, value)
    {
        if ((value == null) || (typeof value == 'undefined'))
            return true;
        if (! TfyhData.#validType(type))
            return false;
        if (Array.isArray(value)) // arrays cannot be matched to a single
									// value
            return false;
        if (type.startsWith("."))
            return false; // objects can ot be matched to a single value
        let parseAs = TfyhData.parseAs(type);
        let native = (typeof value == "object") ? value.constructor.name : typeof value;
        return (TfyhData.#parseAs2native[parseAs] == native);
    }

    /**
	 * Check whether a value fits the native PHP type matching the type
	 * constraints and its min/max limits. Single values only, no arrays
	 * allowed. Values exceeding limits are adjusted to the exceeded limit.
	 */
    static #checkLimitsSingle(value, type, min, max)
    {
        if (! TfyhData.#validType(type)) {
        	TfyhData.#addFinding(7, type);
            return false;
        }
        if (! TfyhData.#isMatchingTypeSingle(type, value)) {
            TfyhData.#addFinding(6, TfyhData.format(value));
            return false;
        }
        // identify validation data type
        let parseAs = TfyhData.parseAs(type);
        let ulimit;
        let llimit;
        // at that point the data type is one of the programmatically
		// defined basic types
        if (parseAs == "int") {
            llimit = Math.max(min, TfyhData.#int_min);
            ulimit = Math.min(max, TfyhData.#int_max);
        } else if (parseAs == "float") {
            llimit = Math.max(min, TfyhData.#float_min);
            ulimit = Math.min(max, TfyhData.#float_max);
        } else if (parseAs == "boolean") {
            return value; // a boolean value never has limits
        } else if (parseAs == "date") {
        	// Math.min/max may not work with objects
            llimit = (TfyhData.#date_min.getTime() < min.getTime()) ? min : TfyhData.#date_min;
            ulimit = (TfyhData.#date_max.getTime() > max.getTime()) ? max : TfyhData.#date_max;
        } else if (parseAs == "datetime") {
            llimit = (TfyhData.#datetime_min.getTime() < min.getTime()) ? min : TfyhData.#datetime_min;
            ulimit = (TfyhData.#datetime_max.getTime() > max.getTime()) ? max : TfyhData.#datetime_max;
        } else if (parseAs == "time") {
            llimit = Math.max(min, TfyhData.#time_min);
            ulimit = Math.min(max, TfyhData.#time_max);
        } else if (parseAs == "string") {
            ulimit = Math.min(max, TfyhData.#string_size);
            if (value.length > ulimit) {
            	// shorten String, if too long
                TfyhData.#addFinding(8, value.substring(0, Math.min(value.length), 20) + "(" + value.length + ")", 
                		"" + ulimit);
                return (ulimit > 12) ? value.substring(0, ulimit - 4) + " ..." : mb_substr(value, 0, 
                        ulimit);
            } else
                return value;
        } else {
            // unknown type
            TfyhData.#addFinding(7, type);
            return false;
        }
    	// adjust value to not exceed the limits
        if (value < llimit) {
            TfyhData.#addFinding(3, TfyhData.format(value), TfyhData.format(llimit, type));
            return llimit;
        } else if (value > ulimit) {
            TfyhData.#addFinding(4, TfyhData.format(value), TfyhData.format(ulimit, type));
            return ulimit;
        } else
            return value;
    }

    /**
	 * Check whether a value fits the native PHP type matching the type
	 * constraints and its min/max limits. Values exceeding limits are adjusted
	 * to the exceeded limit.
	 */
    static check_limits(value, type, min, max)
    {
        // null is always valid
    	if ((typeof value == 'undefined') || (value == null))
            return true;
        // identify constraints which shall apply.
        if ((typeof min == 'undefined') || (min == null) || (min == ""))
            min = TfyhData.get_descriptor_template_limit(type, 0);
        if ((typeof max == 'undefined') || (max == null) || (max == ""))
        	if (TfyhData.parseAs(type).localeCompare("string") != 0)
        		max = TfyhData.get_descriptor_template_limit(type, 1);
        	else
                max = TfyhData.get_descriptor_template_limit(type, 2);
        // validate single
        if (!Array.isArray(value)) 
            return TfyhData.#checkLimitsSingle(value, type, min, max);
        // ... or array
        let checked = [];
        for (let element of value) 
        	checked .push(TfyhData.#checkLimitsSingle(element, type, min, max));
        return checked;
    }

    /**
	 * Guess the type of the value. The value is usually a String, but may as
	 * well be another native type. If value is an array, the type of the
	 * value[0] will be guessed. Null values return a "none" data type.
	 */
    static guess_type(value)
    {
        if ((value == null) || (typeof value == 'undefined'))
            return "string";
        if (Array.isArray(value))
            value = value[0];
        if (typeof value == "object") {
            if (value.constructor.name == "Date")
                return "datetime";
            else
                return "object";
        }
        
        // for a non-string value, use the TfyhData.#native2parseAs mapping
        let valueNativeType = typeof value;
        if (valueNativeType != "string")
            return TfyhData.#native2parseAs[valueNativeType];

        // if String, check null and for enclosing [] as array marker.
        if ((value == "NULL") || (value =="null"))
        	return "string";
        let trimmed = value.trim();
        if ((trimmed.substr(0, 1) == "[") && 
        		(trimmed.substr(trimmed.length - 1) == "]")) {
            let array = TfyhToolbox.splitCsvRow(trimmed.substring(1, trimmed.length - 1), ",");
            $value = $array[0];
        }
        
        // an empty String is always a String
        if (value.length == 0)
            return "string";
        // a long String is always text
        if (value.length > 4096)
            return "text";
        // check for numbers
        if (typeof value == "number") {
            if (value % 1 === 0) {
                if ((value > 100000000000) || (value < 3000000000000))
                    return "microtime";
                else if ((value > 2147483647) || (value < - 2147483648))
                    return "string";
                else
                    return "int";
            }
            return "float";
        }
        // check for boolean
        let value_lc = value.toLowerCase();
        if ((value_lc == "on") || (value_lc == "true") || (value_lc == "false"))
            return "boolean";
        // check for date and time separately
        if ((value.trim().length < 12) && (value.trim().indexOf(" ") < 0)) {
            // if a colon is contained, it may be a time format
            if (value.trim().indexOf(":") >= 0) {
                // Short String without space character and with colon
                let time = TfyhData.#cleanseTime(value);
                if (time === false)
                    return "string";
                return "time";
            }
            
            // if a dot or dash or slash is contained, it may be a date
			// format
            if ((value.indexOf("-") >= 0) || (value.indexOf(".") >= 0) || (value.indexOf("/") >= 0)) {
                // Short String without space character and with dot or
				// dash
            	let date = TfyhData.#cleanseDate(value);
                if (date === false)
                    return "string";
                return "date";
            }
        }
        // check for datetime
        if ((value.trim().length < 20) && (value.trim().split(/ /g).length == 2) &&
                 (value.indexOf(":") >= 0)) {
            // Short String with single space character and with colon
            let datetime = TfyhData.#cleanseDateTime(value);
            if (datetime === false)
                return "string";
            return "datetime";
        }
        // no specific format detected
        return "string";
    }

/* ------------------------------------------------------------------------ */
/* ----- DATA PARSING ----------------------------------------------------- */
/* ----- The result is a native type value or null, in case of errors. ---- */
/* ----- Errors are documented in the public validation_error. ------------ */
/* ------------------------------------------------------------------------ */
		    
    /**
	 * Convert a single or multiple value String to a PHP native single value or
	 * array. In case of errors the return value will be 0 or its equivalent
	 * (false, new Date(0)), either single or as array element.
	 */
    static parse(value_string, type)
    {
        // Guess type, if not given.
        if ((typeof type == 'undefined') || (type == null)) 
        	type = TfyhData.guess_type(value_string);
        // parse_as "none" returns always null.
        if (TfyhData.parseAs(type) == "none")
            return null;
        // initial null check
    	if ((value_string == null) || (typeof value_string == 'undefined'))
    		return (type == "boolean") ? false : null;
        if ((value_string == "NULL") || (value_string == "null"))
            return null;
        // trim string for correct array identification
        let trimmed = value_string.trim();
        // identify arrays by square brackets
        if ((trimmed.substr(0, 1) != "[") || (trimmed.substr(trimmed.length - 1) != "]"))
        	// no array, return the parsed singe value
        	return TfyhData.#parseSingle(trimmed, type);
        // parse an array
        trimmed = trimmed.substr(1, trimmed.length - 2);
        let array = TfyhToolbox.splitCsvRow(trimmed, ",");
        let parsed = [];
        // the type must be always the same, no mixed arrays.
        for (let element of array) 
        	parsed.push(TfyhData.#parseSingle(element, type));
        return parsed;
    }
    
    /**
	 * Convert a single value String to a Javascript native value. null or
	 * undefined will return false for booelan, else null. 'NULL' or 'null' will
	 * return null. In case of errors the return value will be 0 or its
	 * equivalent (false, new Date(0)), either single or as array element.
	 */
    static #parseSingle(value_string, type)
    {
        // initial null check
    	if ((value_string == null) || (typeof value_string == 'undefined'))
    		return (type == "boolean") ? false : null;
        if ((value_string == "NULL") || (value_string == "null"))
            return null;
        
        // parse boolean and String according to the rule
        let parseAs = TfyhData.parseAs(type);
        if (parseAs == "string")
            return value_string;
        if (parseAs == "boolean") 
            return TfyhData.#parseBoolean(value_string);

        // Neither String, nor boolean: an empty String represents NULL.
        if (value_string.length == 0)
            return null;
            
        // parse other according to the rule
        if (parseAs == "int") 
            return TfyhData.#parseNumber(value_string, false);
        if (parseAs == "float") 
            return TfyhData.#parseNumber(value_string, true);
        if (parseAs == "date") 
            return TfyhData.#parseDate(value_string);
        if (parseAs == "datetime") 
            return TfyhData.#parseDateTime(value_string);
        if (parseAs == "time") 
            return TfyhData.#parseTime(value_string);
        if (parseAs == "string") 
            return value_string;

        // unknown type
        TfyhData.#addFinding(5, type + ": " + parseAs);
        return false;
    }

    /**
	 * Convert a String to boolean. Returns false, if bool_string = "FALSE" or
	 * bool_string = "false", else this wil return true. Note that null or
	 * undefined are also converted to false, see #parseSingle().
	 */
    static #parseBoolean(bool_string)
    {
        return ((typeof bool_string != 'undefined') && (bool_string != null) && 
        		(bool_string.length > 0) && (bool_string != "false") && 
        		(bool_string != "FALSE") && (bool_string != "0"));
    }

    /**
	 * Convert a not-empty String to a number. If parsing returns NaN this will
	 * return 0.
	 */
    static #parseNumber(number_string, floating_point)
    {
        // check for ,/. replacement need
        if (number_string.indexOf(",") < 0) {
            if (number_string.lastIndexOf(".") > number_string.lastIndexOf(","))
                // UK style like 1,234.567 for 1234.567
                number_string = number_string.replace(/\,/g, "");
            else if (number_string.split(/\,/g).length > 1)
                // UK style like 1,234,567 for 1234567
                number_string = number_string.replace(/\,/g, "");
            else
                // DE style like 1234,567 or 1.234,567 for 1234.567. Note: 1,234
				// will be taken for DE style
                // 1.234, not for UK style 1234
                number_string = number_string.replace(/\./g, "").replace(/\,/g, ".");
        } else if (number_string.split(/\./g).length > 2)
            // DE style like 1.234.567 for 1234567
            number_string = number_string.replace(/\./g, "");

        if (isNaN(number_string)) {
            TfyhData.#addFinding(2, number_string);
            return 0;
        }
        return (floating_point) ? parseFloat(number_string) : parseInt(number_string);
    }

    /**
	 * Convert a String to a number of seconds. no limits to the number of hours
	 * apply. null or undefined will return null, 'NULL' or 'null' will return
	 * null. If time doesn't contain a ":" this will be 0.
	 */
    static #parseTime(time_string, no_hours = false)
    {
        let cleansed = TfyhData.#cleanseTime(time_string, no_hours);
        if (cleansed === false) {
            TfyhData.#addFinding(2, time_string);
            return 0;
        }
        let sign = (time_string.substring(0, 1) == "-") ? - 1 : 1;
        let hms = cleansed.split(/\:/g);
        return sign * (Math.abs(parseInt(hms[0])) * 3600 + parseInt(hms[1]) * 60 + parseInt(hms[2]));
    }

    /**
	 * Convert a String to a DateTimeImmutable (UTC 00:00:00 of the date given).
	 * If the year is two digits only, It will be assumed to be in the range of
	 * this year -89years .. +10years. null or undefined will return null,
	 * 'NULL' or 'null' will return null. In case of errors this wll be new
	 * Date(0).
	 */
    static #parseDate(date_string)
    {
        let datestr = TfyhData.#cleanseDate(date_string);
        if (datestr === false) {
            TfyhData.#addFinding(1, date_string);
            return new Date(0);
        }
        let ms = Date.parse(date_string);
        if (isNaN(ms)) {
            TfyhData.#addFinding(2, date_string);
            return new Date(0);
        }
        return new Date(ms);
    }

    /**
	 * Convert a datetime String to a DateTimeImmutable Object. If no date is
	 * given, the current date is inserted. If no time is given, the current
	 * time is inserted. In case of errors this wll be new Date(0).
	 */
    static #parseDateTime(datetime_string)
    {
        let datetime = TfyhData.#cleanseDateTime(datetime_string);
        if (datetime == null) {
            TfyhData.#addFinding(1, datetime_string);
            return new Date(0);
        }
        let ms = Date.parse(datetime);
        if (isNaN(ms)) {
            TfyhData.#addFinding(2, datetime_string);
            return new Date(0);
        }
        return new Date(ms);
    }

/* ------------------------------------------------------------------------ */
/* ----- STRING CLEANSING ------------------------------------------------- */
/* ----- Errors are documented in the public validation_error. ------------ */
/* ------------------------------------------------------------------------ */
		    
    /**
	 * Cleanse a date string to YYYY-MM-DD format.
	 */
    static #cleanseDate(date_string)
    {
        let parts;
        let s;
        if (date_string.indexOf(".") >= 0) {
            // de, nl (for simplicity)
            parts = date_string.split(/\./g);
            s = [2,1,0
            ];
        } else if (date_string.indexOf("/") >= 0) {
            // fr, it
            parts = date_string.split(/\//g);
            s = [2,1,0
            ];
        } else {
            // en, csv, sql
            parts = date_string.split(/\-/g);
            s = [0,1,2
            ];
        }

        let currentYear = (new Date().getFullYear());
        if (parts.length == 1) {
            // single integer is taken to be a year. Add first of Jánuary
            if ((parseInt(parts[0]) > 1000) && (parseInt(parts[0]) < 2999))
                return parts[0] + "-01-01";
            TfyhData.#addFinding(1, date_string);
            return false;
        }
        
        let y = (parts.length < 3) ? currentYear : parseInt(parts[s[0]]);
        if (parts.length == 2) {
            // duplicate integer is taken to be day + month
            let m = (s[0] > s[2]) ? parseInt(parts[0]) : parseInt(parts[1]);
            let d = (s[0] > s[2]) ? parseInt(parts[1]) : parseInt(parts[0]);
            if ((m >= 1) && (m <= 12) && (d >= 1) && (d >= 31)) {
            	let then = new Date("" + y + "-" + m + "-" + d);
                if (! isNaN(then.getTime()))
                	return then.toLocaleString('en-CA').substring(0, 10);            	
            }
            TfyhData.#addFinding(1, date_string);
            return false;
        }

        if (y < 100) {
            // extend two digits. Will only work fine until 2090.
        	let year_now = currentYear % 100;
        	let saeculum_now = currentYear - year_now;
            y = (y > (year_now + 10)) ? (saeculum_now - 100) + y : saeculum_now + y;
        }
        let then = new Date("" + y + "-" + parts[s[1]] + "-" + parts[s[2]]);
        if (isNaN(then.getTime()))
        	return false;
        return then.toLocaleString('en-CA').substring(0, 10);
    }

    /**
	 * Cleanse a time string to HH:MM:SS format. Milliseconds are dropped.
	 */
    static #cleanseTime(time_string, no_hours)
    {
    	// split off the "minus", if existing.
    	let sign = "";
    	if (time_string.startsWith("-")) {
    		time_string = time_string.substring(1).trim();
    		sign = "-";
    	}
    	// cleanse the remainder
        let hms = time_string.split(/\:/g);
        if ((hms.length < 2) || (hms.length > 3))  
            return false;
        let hms0 = TfyhToolbox.numToText(parseInt(hms[0]), 2); 
        let hms1 = TfyhToolbox.numToText(parseInt(hms[1]), 2); 
        if (hms.length == 2)
            return (no_hours) ? sign + "00:" + hms0 + ":" + hms1 : sign + hms0 + ":" + hms1 + ":00";
        let hms2 = TfyhToolbox.numToText(parseInt(hms[2]), 2);
        return sign + hms0 + ":" + hms1 + ":" + hms2;
    }

    /**
	 * Cleanse a datetime string to YYYY-MM-DD HH:MM:SS format. Milliseconds are
	 * dropped. If no date is given, the current date is inserted. If no time is
	 * given, the current time is inserted.
	 */
    static #cleanseDateTime(datetime_string)
    {
        if (datetime_string.toUpperCase() == "NULL")
            return "NULL";
        let dt = datetime_string.trim().split(/ /g);
        if (dt.length == 1) {
            // try both, date or time
        	let date = TfyhData.#cleanseDate(dt[0]);
        	let time = TfyhData.#cleanseTime(dt[0], false); // always with hours
            if (date !== false)
                return date + " 00:00:00";
            else if (time !== false) 
                return (new Date()).toLocaleString('en-CA').substring(0, 10) + " " + time;
            else {
                TfyhData.#addFinding(1, trim(datetime_string));
                return false;
            }
        } else {
        	let date = TfyhData.#cleanseDate(dt[0]);
        	let time = TfyhData.#cleanseTime(dt[1], false); // always with hours
            if ((date === false) || (time === false)) {
                TfyhData.#addFinding(1, trim(datetime_string));
                return false;
            }
            return date + " " + time;
        }
    }

/* ------------------------------------------------------------------------ */
/* ----- DATA FORMATTING -------------------------------------------------- */
/* ----- No errors are documented ----------------------------------------- */
/* ------------------------------------------------------------------------ */
		    
    /**
	 * Format a boolean value for storage in files and the data base.
	 */
    static #formatBoolean(bool, language_code = "csv")
    {
        if ($.inArray(language_code, TfyhData.#write_boolean_as_1_0_for) >= 0)
            return (bool) ? "1" : "0";
        if ($.inArray(language_code, TfyhData.#write_boolean_as_true_false_for) >= 0)
            return (bool) ? "true" : "false";
        return (bool) ? "on" : "";
    }

    /**
	 * Format an integer value for storage in files and the data base.
	 */
    static #formatInt(int, language_code = "csv")
    {
        return "" + int;
    }

    /**
	 * Format a floating point value for storage in files and the data base.
	 */
    static #formatFloat(float, language_code = "csv")
    {
        let number_string = "" + float;
        if ($.inArray(language_code, TfyhData.#decimal_point_for) >= 0)
            return number_string;
        number_string = number_string.replace(/\./g, ",");
        return number_string;
    }

    /**
	 * Format a date value for storage in files and the data base.
	 */
    static #formatDate(date, language_code = "csv")
    {
		let dateStr = date.toLocaleString('en-CA').substring(0,10);
		// do not use date.toISOString(), because this will always return UTC.
        let dateformat = TfyhData.#date_formats[language_code]["date"];
        if (dateformat == "Y-m-d")
        	return dateStr;
        let ymd = dateStr.split(/\-/g);
        if (dateformat == "d.m.Y")
        	return ymd[2] + "." + ymd[1] + "." + ymd[0];
        else
        	return ymd[2] + "/" + ymd[1] + "/" + ymd[0];
    }

    /**
	 * Convert a time int to HH:MM:SS format for values -360000 .. 360000 (-100 ..
	 * 100 hours). Convert to date and time for values > 360000. CAUTION: this
	 * is different from Tfyh_toolbox::#formatTime()!
	 */
    static #formatTime(time_int, language_code = "csv")
    {
        let sign = (time_int < 0) ? "-" : "";
        let sign_int = (time_int < 0) ? -1 : 1;
        time_int = Math.abs(time_int);
        if (time_int < 360000) {
            let s = time_int % 60;
            let s_str = (s < 10) ? "0" + s : "" + s;
            let m = ((time_int - s) / 60) % 60;
            let m_str = (m < 10) ? "0" + m : "" + m;
            let h = ((time_int - m * 60 - s) / 3600);
            let h_str = (h < 10) ? "0" + h : "" + h;
            return sign + h_str + ":" + m_str + ":" + s_str;
        } else {
            if (time_int > 2147483647)
            	time_int = 2147483647;
            let date = (new Date(1000 * sign_int * time_int));
            return (isNaN(date.getTime())) ? "00:00:00" : date.toLocaleString('en-CA').substr(11, 19);
        }
    }

    /**
	 * Format a datetime value for storage in files and the data base.
	 */
    static #formatDateTime(datetime, language_code = "csv")
    {
		let date = this.#formatDate(datetime, language_code);
		// do not use date.toISOString(), because this will always return UTC.
		let time = datetime.toTimeString().substring(0,8);
        return date + " " + time;
    }

    /**
	 * Format a value for storage in files and the data base.
	 */
    static #formatSingle(value, type, language_code = "csv")
    {
        if (value == null)
            return ($.inArray(language_code, TfyhData.#write_null_as_NULL_for) >= 0) ? "NULL" : "";
        // the following if / else if / else sequence is ordered in the
		// guessed sequence of occurrence frequency for performance
		// reasons
        let formatted;
        let formatValueAs = TfyhData.parseAs(type);
        if (formatValueAs == "none")
            formatted = "";  // no display of these values
        else if (formatValueAs == "string")
            // value should already be a string. Make sure it becomes one.
            formatted = "" + value;
        else if (formatValueAs == "int")
            formatted = TfyhData.#formatInt(value, language_code);
        else if (formatValueAs == "float")
            formatted = TfyhData.#formatFloat(value, language_code);
        else if (formatValueAs == "boolean")
            formatted = TfyhData.#formatBoolean(value, language_code);
        else if (formatValueAs == "date")
            formatted = TfyhData.#formatDate(value, language_code);
        else if (formatValueAs == "datetime")
            formatted = TfyhData.#formatDateTime(value, language_code);
        else if (formatValueAs == "time")
            formatted = TfyhData.#formatTime(value);
        else
            formatted = "" + value;
        return formatted;
    }

    /**
	 * Format a value for storage in files and the data base. Be aware that
	 * arrays will always be formatted into a list type String, i. e. a date
	 * array will not conform to the MySQL date parser expectation, only its
	 * members.
	 */
    static format(value, type, language_code = "csv")
    {
        if ((value == null) || (typeof value == 'undefined')) 
            return ($.inArray(language_code, TfyhData.#write_null_as_NULL_for) >= 0) ? "NULL" : "";
        if ((type == null) || (typeof type == 'undefined')) 
            type = TfyhData.guess_type(value);

        if (Array.isArray(value)) {
        	let out = "[";
            for (let fsingle of value) {
                fsingle = TfyhData.#formatSingle(fsingle, type);
                if ((fsingle.indexOf("\n") >= 0) || (fsingle.indexOf(",") >= 0) ||
                         (fsingle.indexOf("\"") >= 0))
                    fsingle = '"'  + fsingle.replace(/\"/g, '""') + '"';
                out += fsingle + ",";
            }
            if (out.length > 1)
                out = out.substring(0, out.length - 1);
            out += "]";
            return out;
        } else {
            return TfyhData.#formatSingle(value, type, language_code);
        }
    }

    /**
	 * Convert a String into an Identifier by replacing forbidden characters by
	 * an underscore and cutting the length to 64 characters maximum.
	 */
    to_identifier (str)
    {
        let identifier = "";
        let first = str.substring(0, 1);
        const firstAllowed = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_";
        const subsequentAllowed = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_0123456789";
        if (firstAllowed.indexOf(first) < 0)
            identifier += "_";
        for (let i = 0; (i < str.length) && (identifier.length < 64); i ++) {
            c = str.charAt(i);
            identifier += (subsequentAllowed.indexOf(c) < 0) ? ((c == " ") ? "_" : "") : c;
        }
        return identifier;
    }

}
