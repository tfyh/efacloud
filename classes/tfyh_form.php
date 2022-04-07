<?php

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
     * An array of all inputs used in this form.
     */
    private $entered;

    /**
     * The array of all labels within the form definition. Filled when reading the entered data.
     */
    private $labels;

    /**
     * An array of validities for each inputs used in this form.
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
     * Pass the select options to this String programmatically as array, e. g. [ "y=yes", "n=no", "d=dunno" ]
     * use select $options as form layout.
     */
    public $select_options;

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
        $this->socket = $socket;
        $this->toolbox = $toolbox;
        $this->fs_id = $fs_id;
        $this->layout_file = $file_path;
        if ($index === 1) {
            $form_definition = $toolbox->read_csv_array($file_path);
        } else {
            $form_definition = $toolbox->read_csv_array($file_path . "_" . $index);
        }
        
        // in order to be able to reference the field definition by its name, create
        // a named array.
        $iht = 0;
        $ins = 0;
        $field_index = 0;
        $this->form_definition = [];
        foreach ($form_definition as $field_definition) {
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
        $this->index = $index;
        if ($toolbox->config->debug_level > 0) {
            $form_to_dump = $_SESSION["forms"][$this->fs_id];
            // do not show sensitive data within the log.
            foreach ($form_to_dump as $key => $value)
                if (strpos(strtolower($key), "passw") !== false)
                    $form_to_dump[$key] = substr(
                            "Lorem_ipsum_dolor_sit_amet_consectetur_adipisici_elit_sed_eiusmod_tempor_incidunt_", 
                            0, strlen($value));
            file_put_contents("../log/debug_app.log", 
                    date("Y-m-d H:i.s") . " - Form " . $file_path . " (" . $this->fs_id . "|" . $index . "): " .
                             json_encode($form_to_dump) . "\n", FILE_APPEND);
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
                                $field_definition_service[$key]);
            $this->form_definition[$field_definition_service["name"]] = $field_definition_service;
        }
    }

    /**
     * Read the parameter list for a select field from the data base and extend numeric size definitions by
     * 'em' as unit.
     */
    private function read_options (array $field_definition)
    {
        if (strpos(trim(strtolower($field_definition["type"])), "select use:") === 0) {
            // use a select parameter as defined in the parameters table
            $lookup = explode(":", trim($field_definition["type"]));
            $parameter_name = (count($lookup) == 2) ? $lookup[1] : "select use syntax error in form definition.";
            $options_list = $this->socket->find_record($this->toolbox->config->parameter_table_name, "Name", 
                    $parameter_name);
            if ($options_list) {
                $values = explode(",", $options_list["Wert"]);
                $select_string = "select ";
                foreach ($values as $value)
                    $select_string .= $value . "=" . $value . ";";
                $field_definition["type"] = substr($select_string, 0, strlen($select_string) - 1);
            } else {
                $field_definition["type"] = "select config_error=config_error";
            }
        } elseif (strpos(trim(strtolower($field_definition["type"])), "select list:") === 0) {
            // select from a list of lists, e. g. to select a mail distribution list.
            $lookup = explode(":", trim($field_definition["type"]));
            if (count($lookup) == 2) {
                include_once "../classes/tfyh_list.php";
                $list = new Tfyh_list("../config/lists/" . $lookup[1], 1, "", $this->socket, $this->toolbox);
                $list_definitions = $list->get_all_list_definitions();
                if ($list_definitions === false)
                    $this->toolbox->display_error("!#Konfigurationsfehler.", 
                            "Listenkonfiguration nicht gefunden. Konfigurationsfehler der Anwendung. Bitte rede mit dem Admn.", 
                            __FILE__);
                // for list option listing check the entries against allowances.
                $select_string = "";
                foreach ($list_definitions as $list_definition) {
                    $list_name = $list_definition["name"];
                    $test_list = new Tfyh_list("../config/lists/" . $lookup[1], 0, $list_name, $this->socket, 
                            $this->toolbox);
                    if ($this->toolbox->users->is_allowed_item($test_list->get_permission()))
                        $select_string .= $list_name . "=" . $list_name . ";";
                }
                if (strlen($select_string) == 0)
                    $select_string = "keineListeFürDieseRolle=keineListeFürDieseRolle";
                $select_string = "select " . $select_string;
                $field_definition["type"] = substr($select_string, 0, strlen($select_string) - 1);
            } // select from a list values using a list which has the value in column 1 and the
              // displayed String in column 2.
            elseif (count($lookup) == 3) {
                include_once "../classes/tfyh_list.php";
                $select_string = "";
                if (strpos($lookup[2], "+") == false) {
                    $list_id = intval($lookup[2]);
                } else {
                    $list_id = intval(substr($lookup[2], 0, strlen($lookup[2]) - 1));
                    $select_string .= "-1=(leer);";
                }
                $list = new Tfyh_list("../config/lists/" . $lookup[1], $list_id, "", $this->socket, 
                        $this->toolbox);
                $listed_options = $list->get_rows();
                foreach ($listed_options as $listed_option) {
                    $select_string .= $listed_option[0] . "=" . $listed_option[1] . ";";
                }
                if (strlen($select_string) == 0)
                    $select_string = "keineWerte=keineWerte";
                $select_string = "select " . $select_string;
                $field_definition["type"] = substr($select_string, 0, strlen($select_string) - 1);
            } else {
                $field_definition["type"] = "select config_error=config_error";
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
        $form = "";
        foreach ($this->form_definition as $f) {
            // the fo defintion contains both the form and some help text,
            // usually displayed within
            // a right border frame. Only the help text shall be returned in
            // this function
            if (strpos($f["name"], "_help_text") === 0)
                $form .= $f["tags"] . $f["label"] . "\n";
        }
        return $form;
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
        // ----------------------------------------------------------
        // Buld form as a table of input fields. Tags defibne columns
        // ----------------------------------------------------------
        foreach ($this->form_definition as $f) {
            // start the input field with the label
            $mandatory_flag = (strlen($f["required"]) > 0) ? "*" : "";
            // horizontal radio buttons
            $is_radioh = (strpos($f["type"], "radioh") === 0);
            $inline_label = (strcasecmp("radio", $f["type"]) === 0) || $is_radioh ||
                     (strcasecmp("checkbox", $f["type"]) === 0) || (strcasecmp("input", $f["type"]) === 0) ||
                     (strlen($f["label"]) === 0);
            // the form defintion contains both the form and some help text, usually displayed
            // within a right border frame. The help text shall not be returned in this function
            $help_text = isset($f["name"]) && (strpos($f["name"], "_help_text") === 0);
            $no_input = isset($f["name"]) && (strpos($f["name"], "_no_input") === 0);
            // provide border an label styling in case of invalid input.
            if (! is_null($this->validities) && $this->validities[$f["name"]] === false) {
                $validity_border_style = "style=\"border:1px solid #A22;border-radius: 0px;\" ";
                $validity_label_style_open = "<span style=\"color:#A22;\">";
                $validity_label_style_close = "</span>";
            } else {
                $validity_border_style = "";
                $validity_label_style_open = "";
                $validity_label_style_close = "";
            }
            // show label for input
            if (! $help_text) {
                if ($inline_label) // radio and checkbox
                    $form .= $f["tags"];
                else // includes "_no_input"
                    $form .= $f["tags"] . $validity_label_style_open . $mandatory_flag . $f["label"] .
                             $validity_label_style_close . "<br>\n";
            }
            // now provide the field. Wrap with htmlSpecialChars to prevent from XSS
            // https://stackoverflow.com/questions/1996122/how-to-prevent-xss-with-html-php
            $preset = (isset($_SESSION["forms"][$this->fs_id][$f["name"]]) &&
                     is_string($_SESSION["forms"][$this->fs_id][$f["name"]])) ? htmlspecialchars(
                            $_SESSION["forms"][$this->fs_id][$f["name"]], ENT_QUOTES, 'UTF-8') : false;
            if (! $preset && $f["value"]) {
                if (strpos($f["value"], "\$now") === 0)
                    $preset = date("Y-m-d");
                else
                    $preset = $f["value"];
            }
            // do not use invalid values for preset
            if (! is_null($this->validities) && ($this->validities[$f["name"]] === false))
                $preset = null;
            // special case: select field.
            if (strpos($f["type"], "select") !== false) {
                // ---------------------------
                // special case: select field.
                // ---------------------------
                $form .= "<select " . $validity_border_style;
                if (strlen($f["class"]) > 0)
                    $form .= 'class="' . $f["class"] . '" ';
                else
                    $form .= 'class="formselector" ';
                if (strlen($f["name"]) > 0)
                    $form .= 'name="' . $f["name"] . '" ';
                if (strlen($f["size"]) > 0)
                    $form .= 'style="width: ' . $f["size"] . '">' . "\n";
                else
                    $form .= 'style="width:90%">' . "\n";
                // split type definition into 'select' and options
                $options = substr($f["type"], strpos($f["type"], " ") + 1);
                if (strcasecmp($options, "\$options") == 0)
                    $options_array = $this->select_options;
                else
                    $options_array = explode(";", $options);
                
                // code all options as defined
                if (is_array($options_array))
                    foreach ($options_array as $option) {
                        $nvp = explode("=", $option);
                        if (strcasecmp($nvp[0], $preset) !== 0)
                            $form .= '<option value="' . trim($nvp[0]) . '">' . trim($nvp[1]) . "</option>\n";
                        else
                            $form .= '<option selected value="' . trim($nvp[0]) . '">' . trim($nvp[1]) .
                                     "</option>\n";
                    }
                $form .= "</select>\n";
            } elseif ($is_radioh || (strpos($f["type"], "radio") !== false)) {
                // --------------------------------------------------------
                // special case: radio group (similar to select field case)
                // --------------------------------------------------------
                // split type definition into 'radio' and options
                $options = substr($f["type"], strpos($f["type"], " ") + 1);
                $options_array = explode(";", $options);
                // code all options as defined
                foreach ($options_array as $option) {
                    $nvp = explode("=", $option);
                    if ($is_radioh)
                        $form .= '<div class="w3-col l6">';
                    $form .= '<label class="cb-container">' . $f["label"] . "\n";
                    $form .= '<input type="radio" name="' . $f["name"] . '" value="' . $nvp[0] .
                             $validity_border_style;
                    if (strcasecmp($nvp[0], $preset) === 0)
                        $form .= '" checked>' . $nvp[1];
                    else
                        $form .= '">' . $nvp[1];
                    if (! $is_radioh)
                        $form .= "<br>\n";
                    $form .= '<span class="cb-radio"></span></label>';
                    if ($is_radioh)
                        $form .= '</div>';
                }
            } elseif (strpos($f["type"], "checkbox") !== false) {
                // -----------------------------
                // special case: checkbox input
                // -----------------------------
                $form .= '<label class="cb-container">' . $f["label"] . "\n";
                $form .= '<input type="checkbox" name="' . $f["name"] . '"';
                // In case of a checkbox, set checked for value "on".
                if (strlen($f["value"]) > 0)
                    $form .= (strcasecmp($f["value"], "on") == 0) ? " checked" : "";
                elseif (strlen($preset) > 0)
                    $form .= (strcasecmp($preset, "on") == 0) ? " checked" : "";
                $form .= '><span class="cb-checkmark"></span></label>';
            } elseif (strpos($f["type"], "textarea") !== false) {
                // -----------------------------
                // special case: text area input
                // -----------------------------
                $class_str = "";
                if (strlen($f["class"]) > 0)
                    $class_str .= 'class="' . $f["class"] . '" ';
                else
                    $class_str .= 'class="forminput" ';
                $form .= '<textarea name="' . $f["name"] . '" cols="' . $f["maxlength"] . '" rows="' .
                         $f["size"] . '" ' . $class_str . '>' . $preset . '</textarea><br>' . "\n";
            } elseif (! $help_text && ! $no_input && (strlen($f["name"]) > 0)) {
                // default input type
                $form .= "<input " . $validity_border_style;
                if (strlen($f["type"]) > 0)
                    $form .= 'type="' . $f["type"] . '" ';
                if (strlen($f["class"]) > 0)
                    $form .= 'class="' . $f["class"] . '" ';
                else
                    $form .= 'class="forminput" ';
                if (strlen($f["size"]) > 0)
                    $form .= 'style="width: ' . $f["size"] . '" ';
                if (strlen($f["maxlength"]) > 0)
                    $form .= 'maxlength="' . $f["maxlength"] . '" ';
                if (strlen($f["name"]) > 0)
                    $form .= 'name="' . $f["name"] . '" ';
                // set value.
                if (strlen($preset) > 0)
                    $form .= 'value="' . $preset . '" ';
                elseif (strlen($f["value"]) > 0)
                    $form .= 'value="' . $f["value"] . '" ';
                if ($inline_label)
                    $form .= ">&nbsp;" . $validity_label_style_open . $mandatory_flag . $f["label"] .
                             $validity_label_style_close . "\n";
                else
                    $form .= ">\n";
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
     *            not be interpreted in their SQL-function such by any data base. And the same to prevent from
     *            cross side scripting, "<" is replaced by the math preceding character "≺". If you do not
     *            want to have these replacements, set $replace_insecure_chars to false.. Default is true.
     */
    public function read_entered (bool $replace_insecure_chars = true)
    {
        $this->labels = [];
        foreach ($this->form_definition as $f) {
            $this->labels[$f["name"]] = $f["label"];
            $value = (isset($_POST[$f["name"]])) ? $_POST[$f["name"]] : "";
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
    public function preset_values (array $values, bool $keep_hidden_defaults = false)
    {
        foreach ($this->form_definition as $f) {
            if (isset($values[$f["name"]]) && (! $keep_hidden_defaults ||
                     (isset($f["type"]) && (strcasecmp($f["type"], "hidden") != 0)))) {
                $_SESSION["forms"][$this->fs_id][$f["name"]] = strval($values[$f["name"]]);
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
     * @param $value String
     *            value to be preset. Must be a UTF-8 encoded String. for inputs of type select it can b '~n'
     *            with n being the index of the value to be selected.
     */
    public function preset_value (String $key, String $value)
    {
        foreach ($this->form_definition as $f)
            // only set the value, if field name is existing.
            if (strcmp($f["name"], $key) === 0) {
                if ((strpos($f["type"], "select") !== false) && (substr($value, 0, 1) == '~')) {
                    $pos = intval(substr($value, 1)) - 1;
                    $options = substr($f["type"], strpos($f["type"], " ") + 1);
                    if (strcasecmp($options, "\$options") == 0)
                        $options_array = $this->select_options;
                    else
                        $options_array = explode(";", $options);
                    $_SESSION["forms"][$this->fs_id][$key] = trim(explode("=", $options_array[$pos])[0]);
                } else {
                    $_SESSION["forms"][$this->fs_id][$key] = $value;
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
                $this->toolbox->logger->log(1, $_SESSION["User"][$this->toolbox->users->user_id_field_name], 
                        "Form data key '" . $key . "' does not correspond to a field key of form " .
                                 $this->layout_file . ", step " . $this->index);
            else {
                // check empty inputs. They always comply to the format, if no
                // entry was required.
                if (strlen($value) < 1) {
                    // input is empty
                    if (strlen($definition["required"]) > 0) {
                        // input is required
                        $form_errors .= 'Bitte bei "' . $definition["label"];
                        if (strcmp($definition["type"], "checkbox") === 0)
                            $form_errors .= '" den Haken setzen.<br>';
                        else
                            $form_errors .= '" einen Wert eingeben.<br>';
                        $this->validities[$key] = false;
                    }
                } else {
                    // now check provided value on format compliance, if the
                    // type parameter is set
                    if (isset($definition["type"])) {
                        $type = $definition["type"];
                        if (strcmp($type, "email") === 0) {
                            if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                $form_errors .= 'Bitte bei "' . $definition["label"] .
                                         '" eine gültige E-Mail-Adresse eingeben<br>';
                                $this->validities[$key] = false;
                            }
                        } elseif (strcmp($type, "date") === 0) {
                            if ($this->toolbox->check_and_format_date($value) === false) {
                                $form_errors .= 'Bitte bei "' . $definition["label"] .
                                         '" eine gültiges Datum eingeben (nicht "' . $value . '")<br>';
                                $this->validities[$key] = false;
                            }
                        } elseif ((strcmp($type, "password") === 0) && ($password_rule > 0)) {
                            $errors = $this->toolbox->check_password($value);
                            if (strlen($errors) > 0) {
                                $form_errors .= 'Das Passwort ist nicht ausreichend sicher in "' .
                                         $definition["label"] . '" ' . $errors . '<br>';
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