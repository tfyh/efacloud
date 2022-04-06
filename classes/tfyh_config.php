<?php

/**
 * A utility class to hold all application configuration. There are three layers of config data. 1.
 * Application constants which are part of the code and configure the framework (code constants), 2. data base
 * stored parameters which are typical for the app's function and (values within a config table) 3.
 * administrative parameters whih are typical for the tenant using the application (the setgtings_app and
 * settings_db file).
 */
class Tfyh_config
{

    /**
     * Application specific configuration
     */
    public $app_name;

    public $app_url;

    public $changelog_name;

    public $parameter_table_name;

    public $pdf_footer_text;

    public $pdf_document_author;

    public $pdf_margins;

    public $settings_tfyh;

    /**
     * Debug level to add more information for support cases.
     */
    public $debug_level;

    /**
     * application information: version as "version_string", "release", "major", "minor" and "drop" and
     * copyright.
     */
    public $app_info;

    /**
     * The common toolbox.
     */
    private $toolbox;

    /**
     * configuraton regarding the tenant and data base access
     */
    private $cfg;

    /**
     * Construct the Util class. This reads the configuration, initilizes the logger and the navigation menu,
     * asf.
     */
    public function __construct (Tfyh_toolbox $toolbox)
    {
        $this->toolbox = $toolbox;
        
        // read the settings_tfyh file
        $settings_tfyh_contents = file_get_contents("../config/settings_tfyh");
        // allow for line extensions using the " \" line end
        $settings_tfyh_contents = str_replace(" \\\n", "", $settings_tfyh_contents);
        $settings_tfyh = explode("\n", $settings_tfyh_contents);
        // read all settings lines
        foreach ($settings_tfyh as $setting_tfyh) {
            if ((strlen($setting_tfyh) > 0) && (substr($setting_tfyh, 0, 1) != "#")) {
                // split name and value
                $field_id = explode("=", $setting_tfyh, 2)[0];
                if (count(explode(".", $field_id)) < 2) {
                    echo "invalid field id in settings_tfyh: " . $field_id;
                    exit();
                }
                $field_value = explode("=", $setting_tfyh, 2)[1];
                // assign it to the two level $this->settings_tfyh settings array
                if (! isset($this->settings_tfyh[explode(".", $field_id)[0]]))
                    $this->settings_tfyh[explode(".", $field_id)[0]] = [];
                $this->settings_tfyh[explode(".", $field_id)[0]][explode(".", $field_id)[1]] = $this->parse_value(
                        $field_value);
            }
        }
        if (!isset($this->settings_tfyh["config"]["app_name"])) $this->abort_on_missing_setting("config.app_name");
        if (!isset($this->settings_tfyh["config"]["changelog_name"])) $this->abort_on_missing_setting("config.changelog_name");
        $this->app_name = $this->settings_tfyh["config"]["app_name"];
        $this->changelog_name = $this->settings_tfyh["config"]["changelog_name"];
        $this->parameter_table_name = (isset($this->settings_tfyh["config"]["parameter_table_name"])) ? $this->settings_tfyh["config"]["parameter_table_name"] : "";
        
        // read the settings_app file
        $this->cfg = $this->read_cfg();
        if (!isset($this->cfg["backup"])) $this->cfg["backup"] = "off";
        if (!isset($this->cfg["app_url"])) $this->cfg["app_url"] = "";
        if (!isset($this->cfg["pdf_footer_text"])) $this->cfg["pdf_footer_text"] = "";
        if (!isset($this->cfg["pdf_document_author"])) $this->cfg["pdf_document_author"] = "$this->app_name";
        if (!isset($this->cfg["pdf_margins"])) $this->cfg["pdf_margins"] = [
                15,15,15,10,10
        ];
        if (!isset($this->cfg["debug_support"])) $this->cfg["debug_support"] = 0;
        $this->app_url = $this->cfg["app_url"];
        $this->pdf_footer_text = $this->cfg["pdf_footer_text"];
        $this->pdf_document_author = $this->cfg["pdf_document_author"];
        $this->pdf_margins = (is_array($this->cfg["pdf_margins"]) && (count($this->cfg["pdf_margins"]) == 5)) ? $this->cfg["pdf_margins"] : [
                        15,15,15,10,10
                ];
        $this->debug_level = (isset($this->cfg["debug_support"]) && (strlen($this->cfg["debug_support"]) > 0)) ? 1 : 0;
        
        // read version and copyright
        $this->app_info = [];
        $this->app_info["version_string"] = (file_exists("../public/version")) ? file_get_contents(
                "../public/version") : "";
        $this->app_info["copyright"] = (file_exists("../public/copyright")) ? file_get_contents(
                "../public/copyright") : "";
        if (strlen($this->app_info["version_string"]) > 0) {
            $parts = explode("_", $this->app_info["version_string"]);
            if (count($parts) > 1)
                $this->app_info["drop"] = intval($parts[1]);
            $dotted = explode(".", $parts[0]);
            $this->app_info["release"] = intval($dotted[0]);
            $this->app_info["major"] = (count($dotted) > 1) ? intval($dotted[1]) : 0;
            $this->app_info["minor"] = (count($dotted) > 2) ? intval($dotted[2]) : 0;
        } else {
            $this->app_info["release"] = 0;
            $this->app_info["major"] = 0;
            $this->app_info["drop"] = 0;
            $this->app_info["drop"] = 0;
        }
    }
    
    /**
     * Abort the application if the settings_tfyh are erroneous.
     * @param String $nameToDisplay
     */
    private function abort_on_missing_setting(String $nameToDisplay) {
        echo "Missing setting: " . $nameToDisplay . ". The application can not start.";
        exit();
    }

    /**
     * Set the configuration. Must only be used by "../install/setup_db_connection.php".
     * 
     * @param array $cfg
     *            configuration to be copied.
     */
    public function set_cfg (array $cfg)
    {
        $this->cfg = $cfg;
    }

    /**
     * Parse a value and return the correct value and type
     * 
     * @param String $value_string
     *            return mixed the value in its appropriate type
     */
    private function parse_value (String $value_string)
    {
        // detect value type and decode value
        if ((substr($value_string, 0, 1) == "[") && ! is_null(json_decode($value_string)))
            $field_value = json_decode($value_string);
        elseif (substr($value_string, 0, 1) == "\"") // String literal. In config files, but not in
                                                     // forms
            $field_value = substr($value_string, 1, strlen($value_string) - 2);
        elseif (strcasecmp($value_string, "false") == 0)
            $field_value = false;
        elseif (strcasecmp($value_string, "true") == 0)
            $field_value = true;
        elseif ((strlen($value_string) == 12) && (count(explode("-", $value_string)) == 3))
            $field_value = strtotime($value_string);
        elseif (is_numeric($value_string)) {
            if (strpos($value_string, ".") !== false)
                $field_value = floatval($value_string);
            else
                $field_value = intval($value_string);
        } else
            $field_value = $value_string;
        return $field_value;
    }

    /**
     * Read the configuraton regarding the tenant and data base access from the settings_app and settings_db
     * files. The settings files are a serialized array description, base64 encoded, carrying all information
     * of the $cfg array. Use the "forms/def_settings.php" to set all values.
     * 
     * @return the configuration read. False, if no config was found.$this
     */
    private function read_cfg ()
    {
        $settings_file_path = "../config/settings";
        // read config. First try single file configuration
        if (file_exists($settings_file_path)) {
            $cfgStrBase64 = file_get_contents($settings_file_path);
            if (! $cfgStrBase64)
                return false;
            $cfg = unserialize(base64_decode($cfgStrBase64));
            if ($cfg["db_up"])
                $cfg["db_up"] = Tfyh_toolbox::swap_lchars($cfg["db_up"]);
            return $cfg;
        } else { // Configuration split into data base connection and
                 // application parameters
            if (file_exists($settings_file_path . "_db")) {
                // read data base connection configuration first
                $cfgStrBase64 = file_get_contents($settings_file_path . "_db");
                if (! $cfgStrBase64)
                    return false;
                $cfg = unserialize(base64_decode($cfgStrBase64));
                if ($cfg["db_up"])
                    $cfg["db_up"] = Tfyh_toolbox::swap_lchars($cfg["db_up"]);
            }
            if (file_exists($settings_file_path . "_app")) {
                // merge application configuration into it.
                $cfgStrBase64 = file_get_contents($settings_file_path . "_app");
                if ($cfgStrBase64) {
                    $cfg_app = unserialize(base64_decode($cfgStrBase64));
                    foreach ($cfg_app as $key => $value)
                        if (is_null($cfg_app[$key]))
                            $cfg[$key] = "";
                        elseif (is_array($cfg_app[$key]))
                            $cfg[$key] = $cfg_app[$key];
                        else
                            $cfg[$key] = $this->parse_value($cfg_app[$key]);
                }
            }
            return $cfg;
        }
        return [];
    }

    /**
     * simple getter.
     * 
     * @return array the configuration regarding the tenant and data base access settings
     */
    public function get_cfg ()
    {
        return $this->cfg;
    }
}