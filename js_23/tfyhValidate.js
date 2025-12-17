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
 * A fully static utility class to provide data validation rules.
 */
class TfyhValidate {

    static #errors = [];
    static #warnings = [];

    /**
	 * clear the TfyhValidate.#errors and TfyhData.#findings
	 */
    static clear_findings()
    {
    	TfyhValidate.#errors = [];
    	TfyhValidate.#warnings = [];
    }

    /**
	 * Add the findings from data validation to the TfyhValidate findings
	 */
    static #addDataValidationFindings ()
    {
    	TfyhValidate.#errors = TfyhValidate.#errors.concat(TfyhData.getErrors());
    	TfyhValidate.#warnings = TfyhValidate.#warnings.concat(TfyhData.getWarnings());
    }

    /**
	 * Add a finding from local validation to the Tfyh_Validate findings
	 */
    static #add_finding (is_error, finding)
    {
        if (is_error)
        	TfyhValidate.#errors.push(finding);
        else
        	TfyhValidate.#warnings.push(finding);
    }

    /**
	 * Quick check whether findings exist.
	 */
    static has_findings (include_warnings = false)
    {
        return ((include_warnings && (TfyhValidate.#warnings.length > 0)) || (TfyhValidate.#errors.length > 0));
    }

    /**
	 * Get a list of all findings, one finding per line, using \n als line
	 * break.
	 */
    static get_findings (include_warnings = false)
    {
        let findings = "";
        let errorText = _("SYTWdF|Error") + ": ";
        for (let error of TfyhValidate.#errors)
            findings += errorText + error + "\n";
        if (! include_warnings)
            return findings;
        let warningText = _("V5GHFW|Warning") + ": ";
        for (let warning of TfyhValidate.#warnings)
            findings += warningText + warning + "\n";
        return findings;
    }
    
    /**
	 * Validate a parsed value according to the data type and rules provided in
	 * the template item. Get the validated value back as native value.
	 */
    static validate_parsed_value (templateItem, parsedValue)
    {
        let dataType = templateItem.get_type();
        let typeAndLimitsChecked = TfyhData.check_limits(parsedValue, dataType, templateItem.get_min(), 
                templateItem.get_max());
        let valueRules = templateItem.get_descriptor_field("value_rules");
        if (valueRules && (valueRules.length > 0)) {
            let rules = valueRules.split(",");
            for (let rule of rules) {
                let ruleAppliedFinding = TfyhValidate.apply_rule(typeAndLimitsChecked, rule);
                if (ruleAppliedFinding.length > 0)
                	TfyhValidate.#add_finding(true, ruleAppliedFinding);
            }
        }
        return typeAndLimitsChecked;
    }

    /**
	 * Validate a String according to the data type and rules provided in the
	 * template item. Get the validated value back as native value.
	 */
    static parse_and_validate_value (template_item, value_as_string)
    {
        let dataType = template_item.get_type();
        let parsed = TfyhData.parse(value_as_string, dataType);
        TfyhValidate.add_data_validation_findings();
        let typeAndLimitsChecked = TfyhValidate.validate_parsed_value (templateItem, parsedValue)
        return typeAndLimitsChecked;
    }

    /**
	 * Validate a data record according to the data type and rules provided in
	 * the record item. Get the validated record back as native values. If the
	 * record_as_strings contains a field without template definition, it is
	 * returned unchanged.
	 */
    static parse_and_validate_record (record_as_strings, record_item)
    {
        let errors = "";
        let recordValidated = {};
        for (let field in record_as_strings) {
        	let value = record_as_strings[field];
            if (! record_item.has_child(field))
            	recordValidated[field] = value;
            else {
                fieldItem = record_item.get_child(field);
                validated = TfyhValidate.parse_and_validate_value(fieldItem, value);
                recordValidated[field] = validated;
            }
        }
        return recordValidated;
    }

    /**
	 * Format the full record according to the rules of its configuration
	 */
    static formatParsed (record_native, record_item, 
            language_code)
    {
        let recordFormatted = {};
        for (let field in record_native) {
        	let value = record_native[field];
            let dataType = (record_item.has_child(field)) ? record_item.get_child(field).get_type() : "string";
            recordFormatted[field] = TfyhData.format(value, dataType, language_code);
        }
        return recordFormatted;
    }

    /**
	 * This will apply a validation rule to the value. Return value ist true, if
	 * compliant or an error String, if not compliant.
	 */
    static apply_rule(value, rule) {
    	if (rule.localeCompare("identifier") == 0) {
    		return TfyhValidate.#validateIdentifier(value);
    	}
    	else if (rule.localeCompare("password") == 0) {
    		return TfyhValidate.#validatePassword(value);
    	}
    	return "";
    }

    /**
	 * An identifier is a String consisting of [_a-zA-Z] followed by
	 * [_a-zA-Z0-9] and of 1 .. 64 characters length
	 */
    static #validateIdentifier(identifier) {
        if (identifier.length < 1) 
            return _("P1cjVY|Empty identifier.");
        if (identifier.length > 64) 
            return _("DnzOuP|The maximum identifier l...");
    	const regex = "[_a-zA-Z][_a-zA-Z0-9]{0,63}";
    	const found = identifier.match(regex);
    	if (found[0].localeCompare(identifier) != 0)
    		return _("4iXMSi|Invalid identifier: " + found[0] + "?");
    	return "";
    }

    /**
	 * Check whether to_check represents a UUID
	 */
    static is_uuid (to_check)
    {
        if (to_check.length != 36)
            return false;
        let parts = to_check.split(/\-/g);
        if (parts.length != 5)
            return false;
        let sizes = [8,4,4,4,12];
        let hex = "01234567890abcdefABCDEF";
        for (let i = 0; i < 5; i ++) {
            if (parts[i].length != sizes[i])
                return false;
            for (let j = 0; j < parts[i].length; j++) {
            	let c = parts[i].charAt(j);
                if (hex.indexOf(c) < 0)
                	return false;
            }
        }
        return true;
    }

    /**
	 * Check, whether the pwd complies to password rules.
	 * 
	 * @param String
	 *            pwd password to be checked
	 * @return String list of errors found. Returns empty String, if no errors
	 *         were found.
	 */
	static #validatePassword (pwd)  {
        if (pwd.length < 8) 
            return _("GLHPzt|The minimum password len...");
        if (pwd.length > 32) 
            return _("xUlaEi|The maximum password len...");
        let numbers = (/\d/.test(pwd)) ? 1 : 0;
        let lowercase = (pwd.toUpperCase() == pwd) ? 0 : 1;
        let uppercase = (pwd.toLowerCase() == pwd) ? 0 : 1;
        // Four ASCII blocks: !"#%&'*+,-./ ___ :;<=>?@ ___ [\]^_` ___ {|}~
        let specialchars = (pwd.match(/[!-\/]+/g) || pwd.match(/[:-@]+/g) || pwd.match(/[\[-`]+/g) ||
        		pwd.match(/[{-~]+/g)) ? 1 : 0;
        if ((numbers + lowercase + uppercase + specialchars) < 3)
            return _("ObNF6F|The password must contai..." +
                     "digits, lower case letters, upper case letters, special characters. " +
                     "A special character may be one of !\"#$%&'*+,-./:;<=>?@[\]^_`{|}~");
        return "";
    }

}
