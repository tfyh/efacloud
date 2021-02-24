<?php

/**
 * A utility class to hold all application configuration. There are three layers of config data. 1.
 * Application constants which are part of the code and configure the framework (code constants), 2. data base
 * stored parameters which are typical for the app's function and (values within a config table) 3.
 * administrative parameters whih are typical for the tenant using the application (the setgtings_app and
 * settings_db file).
 */
class Config
{

    /**
     * Application specific configuration
     */
    public $app_name;

    public $changelog_name;

    public $parameter_table_name;

    public $pdf_footer_text;

    public $pdf_document_author;

    public $pdf_margins;

    public $settings_tfyh;

    /**
     * The common toolbox.
     */
    private $toolbox;

    /**
     * application configuration
     */
    private $cfg;

    /**
     * path for logging and monitoring
     */
    private $settings_file_path = "../config/settings";

    /**
     * Construct the Util class. This reads the configuration, initilizes the logger and the navigation menu,
     * asf.
     */
    public function __construct (Toolbox $toolbox)
    {
        $this->toolbox = $toolbox;
        $settings_tfyh = explode("\n", file_get_contents("../config/settings_tfyh"));
        foreach ($settings_tfyh as $setting_tfyh) {
            if ((strlen($setting_tfyh) > 0) && (substr($setting_tfyh, 0, 1) != "#")) {
                $field_id = explode("=", $setting_tfyh, 2)[0];
                $field_value = explode("=", $setting_tfyh, 2)[1];
                if (substr($field_value, 0, 1) == "[")
                    $field_value = json_decode($field_value);
                elseif (substr($field_value, 0, 1) == "\"")
                    $field_value = substr($field_value, 1, strlen($field_value) - 2);
                elseif (strcasecmp($field_value, "false") == 0)
                    $field_value = false;
                elseif (strcasecmp($field_value, "true") == 0)
                    $field_value = true;
                elseif ((strlen($field_value) == 12) && (count(explode("-", $field_value)) == 3))
                    $field_value = strtotime($field_value);
                elseif (strpos($field_value, ".") != false)
                    $field_value = floatval($field_value);
                else
                    $field_value = intval($field_value);
                if (! isset($this->settings_tfyh[explode(".", $field_id)[0]]))
                    $this->settings_tfyh[explode(".", $field_id)[0]] = [];
                $this->settings_tfyh[explode(".", $field_id)[0]][explode(".", $field_id)[1]] = $field_value;
            }
        }
        $this->cfg = $this->read_cfg($this->settings_file_path);
        $this->app_name = $this->settings_tfyh["config"]["app_name"];
        $this->changelog_name = $this->settings_tfyh["config"]["changelog_name"];
        $this->parameter_table_name = $this->settings_tfyh["config"]["parameter_table_name"];
        $this->pdf_footer_text = $this->settings_tfyh["config"]["pdf_footer_text"];
        $this->pdf_document_author = $this->settings_tfyh["config"]["pdf_document_author"];
        $this->pdf_margins = (isset($this->settings_tfyh["config"]["pdf_margins"])) ? 
                $this->settings_tfyh["config"]["pdf_margins"] : [15,15,15,10,10
        ];
    }

    /**
     * Set the configuration. WIll only e needed by the setter form "../forms/def_settings.php".
     * 
     * @param array $cfg
     *            configuration to be copied.
     */
    public function set_cfg (array $cfg)
    {
        $this->cfg = $cfg;
    }

    /**
     * Read the configuraton from the settings file.
     * 
     * @param String $settings_file_path
     *            path of file with settings of the application. The settings file is a serialized array
     *            description, base64 encoded, carrying all information of the $cfg array. Use the
     *            "forms/def_settings.php" to set all values.
     * @return the configuration read. False, if no config was found.$this
     */
    private function read_cfg (String $settings_file_path)
    {
        // read config. First try single file configuration
        if (file_exists($settings_file_path)) {
            $cfgStrBase64 = file_get_contents($settings_file_path);
            if (! $cfgStrBase64)
                return false;
            $cfg = unserialize(base64_decode($cfgStrBase64));
            if ($cfg["db_up"])
                $cfg["db_up"] = Toolbox::swap_lchars($cfg["db_up"]);
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
                    $cfg["db_up"] = Toolbox::swap_lchars($cfg["db_up"]);
            }
            if (file_exists($settings_file_path . "_app")) {
                // merge application configuration into it.
                $cfgStrBase64 = file_get_contents($settings_file_path . "_app");
                if ($cfgStrBase64) {
                    $cfg_app = unserialize(base64_decode($cfgStrBase64));
                    foreach ($cfg_app as $key => $value)
                        $cfg[$key] = $cfg_app[$key];
                }
            }
            return $cfg;
        }
        return [];
    }

    /**
     * simple getter.
     * 
     * @return array the configuration
     */
    public function get_cfg ()
    {
        return $this->cfg;
    }
}