<?php
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
 * This class provides a form segment for a web file. <p>The definition must be a CSV-file, all entries
 * without line breaks, with the first line being always
 * "tags;required;name;value;label;type;class;size;maxlength" and the following lines the respective values.
 * The usage has a lot of options and parameters, please see the tyfyh-PHP framework description for
 * details.</p>
 */
class Tfyh_form
{

    /**
     * Definition of form. Will be read once upon construction from $file_path.
     */
    private $form_definition;

    /**
     * To array of all inputs used in this form.
     */
    private $entered;

    /**
     * The array of all labels within the form definition. Filled when reading the entered data.
     */
    private $labels;

    /**
     * To array of validities for each inputs used in this form.
     */
    private $validities;

    /**
     * The socket used for DB-Access.
     */
    private $socket;

    /**
     * The common toolbox used.
     */
    private $toolbox;

    /**
     * A token to identify the fill in sequence for a multisequence form.
     */
    public $fs_id;

    /**
     * Pass the select options to this String programmatically EITHER as array, e. g. [ "y=yes", "n=no",
     * "d=dunno" ] and use 'select $options' as form layout, OR as per field array, e. g. [ "field1" => [
     * "y=yes", "n=no", "d=dunno" ],"field2" => [ "1=one", "2=two", "3=more" ]] and use 'select
     * $named_options' as form layout.
     */
    public $select_options;

    /**
     * Pass the radio options to this String programmatically as array, e. g. [ "y=yes", "n=no", "d=dunno" ]
     * and use 'radio $options' as form layout.
     */
    public $radio_options;

    /**
     * The index of this form in a multistep form.
     */
    private $layout_file;

    /**
     * The index of this form in a multistep form.
     */
    private $index;

    /**
     * Build a form based on the definition provided in the csv file at $file_path.
     * 
     * @param String $file_path
     *            path to file with form definition, without index. For multistep forms layout names must be
     *            $filepath . "_" . $index, e.g. ../layouts/form_1, ../layouts/form_2, etc. Single step forms
     *            use the file path as provided.
     * @param Tfyh_socket $socket
     *            the socket to connect to the data base
     * @param Util $toolbox
     *            the application basic utilities
     * @param int $index
     *            set to integer value for multistep forms. Will be the value of the done-parameter. set to 1,
     *            for single step forms. In forms using this form class it is usually called "$todo".
     * @param int $fs_id
     *            A form sequence ID (five random characters) to identify the fill in sequence for a
     *            multisequence form. is created in the init class.
     */
    public function __construct (String $file_path, Tfyh_socket $socket, Tfyh_toolbox $toolbox, int $index, 
            String $fs_id)
    {
        $this->layout_file = $file_path;
        $this->socket = $socket;
        $this->toolbox = $toolbox;
        $this->index = $index;
        $this->fs_id = $fs_id;
        $this->init($index, $fs_id);
    }

    /**
     * Initialize the form. Separate function to keep aligned with the javascript twin code, in which external
     * initialization of a form is used.
     */
    private function init ()
    {
        if ($this->index === 1) {
            $form_definition = $this->toolbox->read_csv_array($this->layout_file);
        } else {
            $form_definition = $this->toolbox->read_csv_array($this->layout_file . "_" . $this->index);
        }
        
        // in order to be able to reference the field definition by its name, create
        // a named array.
        $iht = 0;
        $ins = 0;
        $field_index = 0;
        $this->form_definition = [];
        foreach ($form_definition as $field_definition) {
            
            // check whether i18n replacement is needed
            // NB: "value" is the form definition default and may be replaced
            // by programmatic presetting or previous forms step to provide
            // a string for display..
            if ($this->toolbox->is_valid_i18n_reference($field_definition["value"]))
                $field_definition["value"] = i($field_definition["value"]);
            if ($this->toolbox->is_valid_i18n_reference($field_definition["label"]))
                $field_definition["label"] = i($field_definition["label"]);
            
            // when creating the named array, take care for special form definition options.
            $help_text = (strpos($field_definition["name"], "_help_text") === 0);
            $no_input = (strpos($field_definition["name"], "_no_input") === 0);
            $subscriptions = (strpos($field_definition["name"], "#Name") === 0);
            $workflows = (strpos($field_definition["name"], "@Name") === 0);
            $concessions = (strpos($field_definition["name"], "\$Name") === 0);
            // make sure all help text definitions have different names in named array. Their
            // name within the definition is always "_help_text". They will become "_help_text2",
            // "_help_text2" etc. Same with "_no_input".
            if ($help_text) {
                $iht ++;
                $this->form_definition[$field_definition["name"] . $iht] = $field_definition;
            } elseif ($no_input) {
                $ins ++;
                $this->form_definition[$field_definition["name"] . $ins] = $field_definition;
            } elseif ($subscriptions) {
                $this->expand_service("subscriptions", $field_definition, '#');
            } elseif ($workflows) {
                $this->expand_service("workflows", $field_definition, '@');
            } elseif ($concessions) {
                $this->expand_service("concessions", $field_definition, '$');
            } else {
                $this->form_definition[$field_definition["name"]] = $this->read_options($field_definition);
            }
            $field_index ++;
        }
        $this->validities = null;
        if ($this->toolbox->config->debug_level > 0) {
            $form_to_dump = (isset($_SESSION["forms"])) ? $_SESSION["forms"][$this->fs_id] : [
                    "form" => "empty"
            ];
            // do not show sensitive data within the log.
            foreach ($form_to_dump as $key => $value)
                if (strpos(strtolower($key), "passw") !== false)
                    $form_to_dump[$key] = substr(
                            "Lorem_ipsum_dolor_sit_amet_consectetur_adipisici_elit_sed_eiusmod_tempor_incidunt_", 
                            0, strlen($value));
            file_put_contents("../log/debug_app.log", 
                    date("Y-m-d H:i.s") . " - Form " . $this->layout_file . " (" . $this->fs_id . "|" .
                             $this->index . "): " . json_encode(str_replace("\"", "\\\"", $form_to_dump)) .
                             "\n", FILE_APPEND);
        }
    }

    /**
     * Expand a service (subscription, workflow, concession) definition in a field per service. Service names
     * are already unique and must be used unchanged.
     * 
     * @param String $service_name
     *            the name of the service
     * @param array $field_definition
     *            the field definition which shall be expanded per service
     * @param String $identifier
     *            the identifier String of the servives type: @ - wokflows, $ - concessions, # - subscriptions
     */
    private function expand_service (String $service_name, array $field_definition, String $identifier)
    {
        $services_set = $this->toolbox->read_csv_array("../config/access/" . $service_name);
        foreach ($services_set as $service) {
            $field_definition_service = $field_definition;
            // replace in all field definition values all workflow keys by their workflow
            // values
            foreach ($field_definition as $key => $value)
                if (strpos($value, $identifier) !== false)
                    foreach ($service as $skey => $svalue)
                        $field_definition_service[$key] = str_replace($identifier . $skey, $svalue, 
                                i($field_definition_service[$key]));
            $this->form_definition[$field_definition_service["name"]] = $field_definition_service;
        }
    }

    /**
     * Read the parameter list for a select field from the data base and extend numeric size definitions by
     * 'em' as unit.
     */
    private function read_options (array $field_definition)
    {
        // expand select options.
        if (strpos(trim(strtolower($field_definition["type"])), "select use:") === 0) {
            // use a select parameter as defined in the parameters table
            $lookup = explode(":", trim($field_definition["type"]));
            $parameter_name = (count($lookup) == 2) ? $lookup[1] : i("epwaIX|select use syntax error ...");
            $options_list = false;
            if (isset($this->toolbox->config->settings_tfyh["config"]["parameter_table_name"]))
                $options_list = $this->socket->find_record(
                        $this->toolbox->config->settings_tfyh["config"]["parameter_table_name"], "Name", 
                        $parameter_name);
            if ($options_list) {
                // compatibility to tfyh1 w/o i18n
                $values = explode(",", 
                        (isset($options_list["Value"])) ? $options_list["Value"] : $options_list["Wert"]);
                $select_string = "select ";
                foreach ($values as $value)
                    $select_string .= $value . "=" . $value . ";";
                $field_definition["type"] = mb_substr($select_string, 0, mb_strlen($select_string) - 1);
            } else {
                $field_definition["type"] = i("U6s9jf|select 0=?;1=no options ...");
            }
        } elseif (strpos(trim(strtolower($field_definition["type"])), "select list:") === 0) {
            // select from a list of lists, e. g. to select a mail distribution list.
            $lookup = explode(":", trim($field_definition["type"]));
            if (count($lookup) == 2) {
                include_once "../classes/tfyh_list.php";
                $list = new Tfyh_list("../config/lists/" . $lookup[1], 1, "", $this->socket, $this->toolbox);
                $list_definitions = $list->get_all_list_definitions();
                if ($list_definitions === false)
                    $this->toolbox->display_error("!#" . i("3VwQtV|Configuration error."), 
                            i("CBjPta|List configuration not f..."), __FILE__);
                // for list option listing check the entries against allowances.
                $select_string = "";
                foreach ($list_definitions as $list_definition) {
                    $list_name = $list_definition["name"];
                    if ($this->toolbox->is_valid_i18n_reference($list_name))
                        $list_name = i($list_name);
                    $list_id = intval($list_definition["id"]);
                    $test_list = new Tfyh_list("../config/lists/" . $lookup[1], $list_id, "", $this->socket, 
                            $this->toolbox);
                    if ($this->toolbox->users->is_allowed_item($test_list->get_permission()))
                        $select_string .= $list_id . "=" . $list_name . ";";
                }
                if (strlen($select_string) == 0)
                    $select_string = i("lIzc0X|noListForThisRole") . "=" . i("yGorGi|noListForThisRole");
                $select_string = "select " . $select_string;
                $field_definition["type"] = mb_substr($select_string, 0, mb_strlen($select_string) - 1);
            } // select from a list values using a list which has the value in column 1 and the
              // displayed String in column 2.
            elseif (count($lookup) == 3) {
                include_once "../classes/tfyh_list.php";
                $select_string = "";
                if (strpos($lookup[2], "+") == false) {
                    $list_id = intval($lookup[2]);
                } else {
                    $list_id = intval(mb_substr($lookup[2], 0, mb_strlen($lookup[2]) - 1));
                    $select_string .= "-1=" . i("WjRsQw|(empty)") . ";";
                }
                $list = new Tfyh_list("../config/lists/" . $lookup[1], $list_id, "", $this->socket, 
                        $this->toolbox);
                $listed_options = $list->get_rows();
                foreach ($listed_options as $listed_option) {
                    $select_string .= $listed_option[0] . "=" . $listed_option[1] . ";";
                }
                if (strlen($select_string) == 0)
                    $select_string = i("bNnasv|noValues") . "=" . i("8qQEHT|noValues");
                $select_string = "select " . $select_string;
                $field_definition["type"] = mb_substr($select_string, 0, mb_strlen($select_string) - 1);
            } else {
                $field_definition["type"] = i("P0YpJt|select config_error");
            }
        }
        // add unit "em" if size has no unit. For textarea this is in maxlength.
        if (strval(intval($field_definition["size"])) == $field_definition["size"]) {
            $field_definition["size"] = $field_definition["size"] . "em";
        }
        if (strpos(trim(strtolower($field_definition["type"])), "select use:") === 0) {
            if (strval(intval($field_definition["maxlength"])) == $field_definition["maxlength"]) {
                $field_definition["maxlength"] = $field_definition["maxlength"] . "em";
            }
        }
        return $field_definition;
    }

    /**
     * Simple getter.
     * 
     * @return int index of this form, as was defined upon construction.
     */
    public function get_index ()
    {
        return $this->index;
    }

    /**
     * Return a html code of the help text.
     * 
     * @return string
     */
    public function get_help_html ()
    {
        $form = "<h5><br />" . i("Fiwrt1|Please note") . "</h5><ul>";
        $l = 0;
        foreach ($this->form_definition as $f) {
            // the fo defintion contains both the form and some help text,
            // usually displayed within
            // a right border frame. Only the help text shall be returned in
            // this function
            if (strpos($f["name"], "_help_text") === 0) {
                $form .= $f["tags"] . $f["label"] . "\n";
                $l ++;
            }
        }
        $form .= "</ul>";
        return ($l > 0) ? $form : "";
    }

    /**
     * Return a html code of this form based on its definition. Will not return the help text.
     * 
     * @param bool $is_file_upload
     *            set true, to enable file-upload. You shall then set a hidden <input type="hidden"
     *            name="MAX_FILE_SIZE" value="30000" /> or similar and use the file upload input such as
     *            name="userfile" type="file". The $_FILES['userfile'] then provides all you need to access
     *            the file.
     * @param String $get_parameter
     *            get parameter to add after the 'done' value, e.g. "id=2&type=add"
     * @return string the html code of the form for display
     */
    public function get_html (bool $is_file_upload = false, String $get_parameter = "")
    {
        if (count($this->form_definition) == 0)
            return "";
        // start the form.
        if (strlen($get_parameter) > 0)
            $get_parameter = "&" . $get_parameter;
        if ($is_file_upload)
            $form = '		<form enctype="multipart/form-data" action="?fseq=' . $this->fs_id . $this->index .
                     $get_parameter . '" method="post">' . "\n";
        else
            $form = '		<form action="?fseq=' . $this->fs_id . $this->index . $get_parameter .
                     '" method="post">' . "\n";
        // ---------------------------------------------------------
        // Buld form as a table of input fields. Tags define columns
        // ---------------------------------------------------------
        foreach ($this->form_definition as $f) {
            // start the input field with the label
            $mandatory_flag = (strlen($f["required"]) > 0) ? "*" : "";
            // horizontal radio buttons
            $inline_label = (strcasecmp("radio", $f["type"]) === 0) ||
                     (strcasecmp("checkbox", $f["type"]) === 0) || (strcasecmp("input", $f["type"]) === 0) ||
                     (strlen($f["label"]) === 0);
            
            // the form defintion contains both the form and some help text, usually displayed
            // within a right border frame. The help text shall not be returned in this function
            $help_text = isset($f["name"]) && (strpos($f["name"], "_help_text") === 0);
            $no_input = isset($f["name"]) && (strpos($f["name"], "_no_input") === 0);
            
            // provide border an label styling. Include case of invalid input.
            $style_str = "";
            $validity_label_style_open = "";
            $validity_label_style_close = "";
            if (! is_null($this->validities) && $this->validities[$f["name"]] === false) {
                $style_str = 'style="' . $style_str . ';border:1px solid #A22;border-radius: 0px;" ';
                $validity_label_style_open = "<span style=\"color:#A22;\">";
                $validity_label_style_close = "</span>";
            } elseif (strpos($f["type"], "textarea") !== false)
                $style_str .= ' "cols="' . $f["maxlength"] . '" rows="' .
                         ((isset($f["size"]) && (intval($f["size"]) > 0)) ? $f["size"] : 4) . '" ';
            elseif (strlen($f["size"]) > 0)
                $style_str = 'style="width:' . $f["size"] . ';" ';
            
            // show label for input
            if (! $help_text) {
                if ($inline_label) // radio and checkbox
                    $form .= $f["tags"];
                else // includes "_no_input"
                    $form .= $f["tags"] . $validity_label_style_open . $mandatory_flag . $f["label"] .
                             $validity_label_style_close . "<br>\n";
            }
            // now provide the previously entered or programatically provided value. Wrap with
            // htmlSpecialChars to prevent from XSS
            // https://stackoverflow.com/questions/1996122/how-to-prevent-xss-with-html-php
            // $_SESSION["forms"][$this->fs_id][$f["name"]] reflects the previously entered String
            $for_display = (isset($_SESSION["forms"][$this->fs_id][$f["name"]]) &&
                     is_string($_SESSION["forms"][$this->fs_id][$f["name"]])) ? htmlspecialchars(
                            $_SESSION["forms"][$this->fs_id][$f["name"]], ENT_QUOTES, 'UTF-8') : false;
            // if there is no previously entered field, but a default value set by the form, use
            // this
            // default.
            if (($for_display === false) && isset($f["value"])) {
                if (strpos($f["value"], "\$now") === 0)
                    // special case date of now
                    $for_display = date("Y-m-d");
                else
                    // all other cases
                    $for_display = $f["value"];
            }
            
            // compile all attribute definitions
            $type_str = (strlen($f["type"]) > 0) ? 'type="' . $f["type"] . '" ' : "";
            $name_str = (strlen($f["name"]) > 0) ? 'name="' . $f["name"] . '" ' : "";
            $id_str = 'id="cFormInput-' . $f["name"] . '" ';
            // do not use the name for the submit button as id
            if (strpos(strtolower($f["type"]), "submit") !== false)
                $id_str = 'id="cFormInput-submit" ';
            // set default first
            $class_str = 'class="forminput" ';
            if (strlen($f["class"]) > 0) {
                // special case: dedicated ID attribute within the class field
                if (strpos($f["class"], '#') === 0)
                    $id_str = 'id="' . substr($f["class"], 1) . '" ';
                else
                    $class_str = 'class="' . $f["class"] . '" ';
            }
            $disabled_flag = (strcmp($f["required"], "!") == 0) ? "disabled" : "";
            
            // do not use invalid values for preset
            if (! is_null($this->validities) && ($this->validities[$f["name"]] === false))
                $for_display = null;
            // special case: select field.
            if (strpos($f["type"], "select") !== false) {
                // ---------------------------
                // special case: select field.
                // ---------------------------
                $class_str = 'class="formselector" ';
                $form .= "<select " . $name_str . $style_str . $class_str . $id_str . $disabled_flag . ">\n";
                
                // split type definition into 'select' and options
                $options = substr($f["type"], strpos($f["type"], " ") + 1);
                if (strcasecmp($options, "\$options") == 0)
                    $options_array = $this->select_options;
                elseif (strcasecmp($options, "\$named_options") == 0)
                    $options_array = $this->select_options[$f["name"]];
                else
                    $options_array = explode(";", $options);
                
                // code all options as defined
                if (is_array($options_array))
                    foreach ($options_array as $option) {
                        $nvp = explode("=", $option);
                        $selected = (strcasecmp($nvp[0], $for_display) == 0) ? "selected " : "";
                        $form .= '<option ' . $selected . 'value="' . trim($nvp[0]) . '">' . trim($nvp[1]) .
                                 "</option>\n";
                    }
                $form .= "</select>\n";
            } elseif ((strpos($f["type"], "radio") !== false)) {
                // --------------------------------------------------------
                // special case: radio group (similar to select field case)
                // --------------------------------------------------------
                // split type definition into 'radio' and options
                $options = substr($f["type"], strpos($f["type"], " ") + 1);
                if (strcasecmp($options, "\$options") == 0)
                    $options_array = $this->radio_options;
                else
                    $options_array = explode(";", $options);
                // code all options as defined
                $o = 1;
                foreach ($options_array as $option) {
                    $nvp = explode("=", $option);
                    $checked = ((strcasecmp($nvp[0], $for_display) === 0)) ? "checked " : "";
                    $form .= '<label class="cb-container">' . $f["label"] . "\n";
                    // no style or class definitions allowed for radio selections
                    $form .= '<input type="radio" ' . $name_str . $style_str . 'value="' . $nvp[0] . '" ' .
                             $checked . str_replace('" ', '-' . $o ++ . '" ', $id_str) . $disabled_flag . '>' .
                             $nvp[1];
                    $form .= '<span class="cb-radio"></span></label>' . "\n";
                }
            } elseif (strpos($f["type"], "checkbox") !== false) {
                // -----------------------------
                // special case: checkbox input
                // -----------------------------
                // In case of a checkbox, set checked for value "on" and set the class to checked-on
                // or off to keep track of the state. This is needed due to the CSS styles using the
                // ::after
                // property which can not be queried.
                $checked = ((strlen($for_display) > 0) && (strcmp($for_display, "false") != 0)) ? 'checked class="checked-on" ' : 'class="checked-off" ';
                $form .= '<label class="cb-container">' . $f["label"] . "\n";
                // no class definitions allowed for checkboxes
                $form .= '<input ' . $type_str . $name_str . $style_str . $checked . $id_str . $disabled_flag .
                         '>';
                $form .= '<span class="cb-checkmark"></span></label>';
            } elseif (strpos($f["type"], "textarea") !== false) {
                // -----------------------------
                // special case: text area input
                // -----------------------------
                if ($for_display === false)
                    $for_display = "";
                $box_size = ' cols="' . $f["maxlength"] . '" rows="' . $f["size"] . '"';
                $form .= '<textarea ' . $name_str . $box_size . $class_str . $id_str . $disabled_flag . '>' .
                         $for_display . '</textarea><br>' . "\n";
            } elseif (! $help_text && ! $no_input && (strlen($f["name"]) > 0)) {
                // -----------------------------
                // default input type
                // -----------------------------
                $form .= "<input " . $type_str . $name_str . $style_str . $class_str;
                if (strlen($f["maxlength"]) > 0)
                    $form .= 'maxlength="' . $f["maxlength"] . '" ';
                // set value.
                if (strlen($for_display) > 0)
                    $form .= 'value="' . $for_display . '" ';
                $form .= $id_str . $disabled_flag . ">\n";
                // add the inline label.
                if ($inline_label)
                    $form .= "&nbsp;" . $validity_label_style_open . $mandatory_flag . $f["label"] .
                             $validity_label_style_close . "\n";
            }
        }
        // ----------------------------
        // Table for form is completed.
        // ----------------------------
        $form .= "	</form>\n";
        return $form;
    }

    /**
     * read all values into the array of the superglobal $_SESSION for this form object as they were provided
     * via the post method. This function will use all entered data of the form object, i. e. empty form
     * inputs will delete a previously set values within a field. No validation applies at this point.
     * 
     * @param bool $replace_insecure_chars
     *            (optional) this function replaces "`" by the Armenian apostrophe "՚" and ";" by the Greek
     *            question mark ";". Characters look similar, but have different code points so that they will
     *            not be interpreted in their SQL-function such by any data base. Tod the same to prevent from
     *            cross side scripting, "<" is replaced by the math preceding character "≺". If you do not
     *            want to have these replacements, set $replace_insecure_chars to false.. Default is true.
     */
    public function read_entered (bool $replace_insecure_chars = true)
    {
        $this->labels = [];
        foreach ($this->form_definition as $f) {
            $this->labels[$f["name"]] = $f["label"];
            $value = (isset($_POST[$f["name"]])) ? $_POST[$f["name"]] : "";
            // trim value to avoid peceeding or trailing blanks
            $value = trim($value);
            // replacements to prevent from sql-injection and cross side
            // scripting.
            if ($replace_insecure_chars !== false)
                $value = str_replace("`", "\u{055A}", 
                        str_replace("<", "\u{227A}", str_replace(";", "\u{037E}", $value)));
            if ($this->isDate($f))
                $_SESSION["forms"][$this->fs_id][$f["name"]] = $this->toolbox->check_and_format_date($value);
            elseif ($this->isField($f))
                $_SESSION["forms"][$this->fs_id][$f["name"]] = $value;
        }
    }

    /**
     * Check whether this field is a form field
     * 
     * @param array $fieldDefinition
     *            field definition
     * @return boolean true, if this is a form field, false if it is a submit field, a help text etc.
     */
    private function isField (array $fieldDefinition)
    {
        return (strcasecmp("submit", $fieldDefinition["type"]) !== 0) && (strlen($fieldDefinition["name"]) > 0) &&
                 (strpos($fieldDefinition["name"], "_help_text") !== 0) &&
                 (strpos($fieldDefinition["name"], "_no_input") !== 0);
    }

    /**
     * Check whether this field is a date type field
     * 
     * @param array $fieldDefinition
     *            field definition
     * @return boolean true, if this is a date type field, false if it is not.
     */
    private function isDate (array $fieldDefinition)
    {
        return (strcasecmp($fieldDefinition["type"], "date") === 0);
    }

    /**
     * preset all values of the form with those of the provided array with array($key, $value) being put to
     * the form object inputs array ($key, value) if in the form object inputs array such key exists.
     * 
     * @param $values array
     *            all values to be preset. Values must be UTF-8 encoded Strings. The method will read through
     *            the form definition and use values for each matching key which occurs in this array. If the
     *            array has keys, which are not keys of the form, theses keys are ignored.
     * @param $keep_hidden_defaults bool
     *            set true to keep the default values for hidden fields rather than to overwrite them
     */
    public function preset_values (array $values_record, bool $keep_hidden_defaults = false)
    {
        foreach ($this->form_definition as $f) {
            if (isset($values_record[$f["name"]]) && (! $keep_hidden_defaults ||
                     (isset($f["type"]) && (strcasecmp($f["type"], "hidden") != 0)))) {
                $_SESSION["forms"][$this->fs_id][$f["name"]] = strval($values_record[$f["name"]]);
            }
        }
    }

    /**
     * Preset a single value of the form object. If the key is not a field name of the form, this will have no
     * effect. Value must be a UTF-8 encoded String. To preset a subscription or a workflow form input, please
     * provide the $key '#Name' or '@Name' respectively and as $value the bitmask as String formatted integer
     * (radix 10).
     * 
     * @param $key String
     *            key of field to be preset
     * @param $value_str String
     *            value to be preset. Must be a UTF-8 encoded String. for inputs of type select it can be '~n'
     *            with n being the index of the value to be selected.
     */
    public function preset_value (String $key, String $value_str)
    {
        foreach ($this->form_definition as $f)
            // only set the value, if field name is existing.
            if (strcmp($f["name"], $key) === 0) {
                if ((strpos($f["type"], "select") !== false) && (substr($value_str, 0, 1) == '~')) {
                    // if the value starts with '~' it refers to the index of the option
                    $pos = intval(substr($value_str, 1)) - 1;
                    $options = substr($f["type"], strpos($f["type"], " ") + 1);
                    if (strcasecmp($options, "\$options") == 0)
                        $options_array = $this->select_options;
                    elseif (strcasecmp($options, "\$named_options") == 0)
                        $options_array = $this->select_options[$f["name"]];
                    else
                        $options_array = explode(";", $options);
                    $_SESSION["forms"][$this->fs_id][$key] = trim(explode("=", $options_array[$pos])[0]);
                } else {
                    $_SESSION["forms"][$this->fs_id][$key] = $value_str;
                }
            }
    }

    /**
     * simple getter of user entered data as $key => $value.
     */
    public function get_entered ()
    {
        return $_SESSION["forms"][$this->fs_id];
    }

    /**
     * simple getter of labels (user visible field descriptions) as $key => $label. Cf. get_entered()
     */
    public function get_labels ()
    {
        return $this->labels;
    }

    /**
     * get the definition of a field with the given name and its current value.
     */
    public function get_field (String $name)
    {
        if (! isset($this->form_definition[$name]))
            return null;
        $field = [];
        foreach ($this->form_definition[$name] as $key => $value)
            $field[$key] = $value;
        return $field;
    }

    /**
     * Set an input fields validity. If set to false, the input will be marked as invalid when the form will
     * be redisplayed.
     * 
     * @param String $key
     *            the key of the input
     * @param bool $is_valid
     *            the validity to be set.
     */
    public function set_input_validity (String $key, bool $is_valid)
    {
        $this->validities[$key] = $is_valid;
    }

    /**
     * Check the validity of all inputs within the form. Uses the type declaration in the form to deduct the
     * required data type and the "required" field to decide, whether a field must be filled or not. Will also
     * return an error, if the value contains a '<' and the word 'script' to prevent from cross site
     * scripting.
     * 
     * @param int $password_rule
     *            leave out or set to 1 for default rule, 0 for no check
     * @return String list of compliance errors found in the form values. Returns empty String, if no errors
     *         were found.
     */
    public function check_validity (int $password_rule = 1)
    {
        $form_errors = "";
        if (! isset($_SESSION["forms"][$this->fs_id]) || ! is_array($_SESSION["forms"][$this->fs_id]))
            return;
        foreach ($_SESSION["forms"][$this->fs_id] as $key => $value) {
            $definition = isset($this->form_definition[$key]) ? $this->form_definition[$key] : false;
            if ($definition === false)
                $this->toolbox->logger->log(1, $this->toolbox->users->session_user["@id"], 
                        i("obg4CF|Form data key °%1° does ...", $key, $this->layout_file, 
                                strval($this->index)));
            else {
                // check empty inputs. They always comply to the format, if no
                // entry was required.
                if (strlen($value) < 1) {
                    // input is empty
                    if (strlen($definition["required"]) > 0) {
                        // input is required
                        $form_errors .= i("AdwVBx|Please at °") . $definition["label"];
                        if (strcmp($definition["type"], "checkbox") === 0)
                            $form_errors .= '" ' . i('iPXyEn|set the tick.') . '<br>';
                        else
                            $form_errors .= '" ' . i('H6xh5s|enter a value.') . '<br>';
                        $this->validities[$key] = false;
                    }
                } else {
                    // now check provided value on format compliance, if the
                    // type parameter is set
                    if (isset($definition["type"])) {
                        $type = $definition["type"];
                        if (strcmp($type, "email") === 0) {
                            if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                $form_errors .= i('6ytHoP|Please enter a valid ema...', $definition["label"]) .
                                         '<br>';
                                $this->validities[$key] = false;
                            }
                        } elseif (strcmp($type, "date") === 0) {
                            if ($this->toolbox->check_and_format_date($value) === false) {
                                $form_errors .= i('arUUZe|Please enter a valid dat...', $definition["label"], 
                                        $value) . '<br>';
                                $this->validities[$key] = false;
                            }
                        } elseif ((strcmp($type, "password") === 0) && ($password_rule > 0)) {
                            $errors = $this->toolbox->check_password($value);
                            if (strlen($errors) > 0) {
                                $form_errors .= i('W37MTN|The password is not secu...', $definition["label"], 
                                        $errors) . '<br>';
                                $this->validities[$key] = false;
                            }
                        }
                    }
                }
            }
        }
        return $form_errors;
    }
}
