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

    public $pdf_footer_text;

    public $pdf_document_author;

    public $pdf_margins;

    public $settings_tfyh;

    public $language_code;

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
     * configuraton regarding the tenant
     */
    private $cfg_app;

    /**
     * configuraton regarding the data base access
     */
    private $cfg_db;

    /**
     * configuraton default values. They will take effect, if no setting is provided. They shall be set in the
     * defaults section of the settings_tfyh file.
     */
    private $cfg_defaults;

    /**
     * Construct the Util class. This reads the configuration, initilizes the logger and the navigation menu,
     * asf.
     */
    public function __construct (Tfyh_toolbox $toolbox)
    {
        $this->toolbox = $toolbox;
        
        // read the framework settings
        $this->read_framework_config();
        // add generic defaults for the framework settings
        if (! isset($this->settings_tfyh["config"]["parameter_table_name"]))
            $this->settings_tfyh["config"]["parameter_table_name"] = "";
        // assign directly accessable framework settings
        $this->app_name = $this->settings_tfyh["config"]["app_name"];
        $this->changelog_name = $this->settings_tfyh["config"]["changelog_name"];
        
        // read data base access cobnfiguration
        $this->read_db_config();
        
        // generate the app_info array with information on version and copyright
        $this->app_info = [];
        $this->app_info["version_string"] = (file_exists("../public/version")) ? file_get_contents(
                "../public/version") : " ";
        $this->app_info["copyright"] = (file_exists("../public/copyright")) ? file_get_contents(
                "../public/copyright") : " ";
        $this->app_info["applogo"] = (file_exists("../public/applogo")) ? file_get_contents(
                "../public/applogo") : " ";
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
        
        // read the application settings. They may have changed, so this can also be triggered from outside
        $this->load_app_configuration();
        $this->app_url = $this->cfg_app["app_url"];
        $this->language_code = $this->cfg_app["language_code"];
    }

    /**
     * Set the data base configuration. MUST ONLY BE USED BY "../install/setup_db_connection.php".
     * 
     * @param array $cfg
     *            configuration to be copied.
     */
    public function set_cfg_db (array $cfg_db)
    {
        $this->cfg_db = $cfg_db;
    }

    /**
     * Load all application coniguration into the $this->cfg_app array.
     */
    public function load_app_configuration ()
    {
        // apply the generic application configuration defaults
        $this->cfg_app["backup"] = "off";
        $this->cfg_app["pdf_footer_text"] = "";
        $this->cfg_app["pdf_document_author"] = "$this->app_name";
        $this->cfg_app["pdf_margins"] = [15,15,15,10,10
        ];
        $this->cfg_app["debug_support"] = "";
        $this->cfg_app["app_url"] = "https://www.tfyh.org";
        $this->cfg_app["language_code"] = "de";
        
        // apply the application configuration defaults as available in the framework settings
        if (isset($this->settings_tfyh["default"]) && is_array($this->settings_tfyh["default"]))
            foreach ($this->settings_tfyh["default"] as $key => $default_value)
                $this->cfg_app[$key] = $default_value;
        // read the application settings from the file
        $this->read_app_config();
        
        // assign directly accessable application settings
        $this->pdf_footer_text = $this->cfg_app["pdf_footer_text"];
        $this->pdf_document_author = $this->cfg_app["pdf_document_author"];
        $this->pdf_margins = (is_array($this->cfg_app["pdf_margins"]) &&
                 (count($this->cfg_app["pdf_margins"]) == 5)) ? $this->cfg_app["pdf_margins"] : [15,15,15,
                        10,10
                ];
        $this->debug_level = ((strcasecmp($this->cfg_app["debug_support"], "on") == 0) ||
                 (intval($this->cfg_app["debug_support"]) > 0)) ? 1 : 0;
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
            $field_value = mb_substr($value_string, 1, mb_strlen($value_string) - 2);
        elseif (strcasecmp($value_string, "false") == 0)
            $field_value = false;
        elseif (strcasecmp($value_string, "true") == 0)
            $field_value = true;
        elseif ((mb_strlen($value_string) == 12) && (count(explode("-", $value_string)) == 3))
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
     * Abort the application if the settings_tfyh are erroneous.
     * 
     * @param String $nameToDisplay            
     */
    private function abort_on_missing_setting (String $nameToDisplay)
    {
        echo i("rgqM2t|Missing setting: %1. The...", $nameToDisplay);
        exit();
    }

    /**
     * Read the framework settings as defined in the settings_tfyh file. Sets the two level array of
     * $this->settings_tfyh.
     */
    private function read_framework_config ()
    {
        // read the framework settings
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
                    echo i("GUOWvb|Invalid field id in sett...") . " " . $field_id;
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
        // abort on missing mandatory values
        if (! isset($this->settings_tfyh["config"]["app_url"]))
            $this->abort_on_missing_setting("config.app_url");
        if (! isset($this->settings_tfyh["config"]["app_name"]))
            $this->abort_on_missing_setting("config.app_name");
        if (! isset($this->settings_tfyh["config"]["changelog_name"]))
            $this->abort_on_missing_setting("config.changelog_name");
    }

    /**
     * Read the data base access configuraton from the settings_db file. The settings file is a serialized
     * array description, base64 encoded. If the settings_db file has extra fields, it is replaced. (legacy
     * fix)
     * 
     * @return the configuration read. False, if no config was found.
     */
    private function read_db_config ()
    {
        // Read data base settings
        if (file_exists("../config/settings_db")) {
            // read data base connection configuration first
            $cfgStrBase64 = file_get_contents("../config/settings_db");
            if (! $cfgStrBase64)
                return false;
            $cfg_db = unserialize(base64_decode($cfgStrBase64));
        }
        $this->cfg_db["db_host"] = $cfg_db["db_host"];
        $this->cfg_db["db_name"] = $cfg_db["db_name"];
        $this->cfg_db["db_user"] = $cfg_db["db_user"];
        $this->cfg_db["db_up"] = Tfyh_toolbox::swap_lchars($cfg_db["db_up"]);
        // data base layout only used in efacloud (03.08.2022)
        $this->cfg_db["db_layout_version"] = (isset($cfg_db["db_layout_version"])) ? $cfg_db["db_layout_version"] : 0;
        // legacy fix: settings_db files sometimes also contain app settings
        // clear the app settings leftovers
        if (count($cfg_db) > 5) {
            $cfg_db = $this->cfg_db;
            $cfg_db["db_up"] = Tfyh_toolbox::swap_lchars($cfg_db["db_up"]);
            $cfgStr = serialize($cfg_db);
            $cfgStrBase64 = base64_encode($cfgStr);
            file_put_contents("../config/settings_db", $cfgStrBase64);
        }
    }

    /**
     * Read the configuraton regarding the tenant application configuration from the settings_app file. The
     * settings file is a serialized array description, base64 encoded.
     * 
     * @return the configuration read. False, if no config was found.
     */
    private function read_app_config ()
    {
        if (file_exists("../config/settings_app")) {
            // merge application configuration into it.
            $cfgStrBase64 = file_get_contents("../config/settings_app");
            if ($cfgStrBase64) {
                $cfg_app_raw = unserialize(base64_decode($cfgStrBase64));
                $cfg_app = [];
                foreach ($cfg_app_raw as $key => $value)
                    if (is_null($cfg_app_raw[$key]))
                        $cfg_app[$key] = "";
                    elseif (is_array($cfg_app_raw[$key]))
                        $cfg_app[$key] = $cfg_app_raw[$key];
                    else
                        $cfg_app[$key] = $this->parse_value($cfg_app_raw[$key]);
            }
        }
        // copy all values into $this->cfg_app, to keep the defaults where no configuration is set.
        foreach ($cfg_app as $key => $value) {
            // one exception: for numeric configurations keep default, if setting is not numeric.
            $default_numeric = (isset($this->cfg_app[$key]) && is_numeric($this->cfg_app[$key]));
            if (! $default_numeric || is_numeric($value))
                $this->cfg_app[$key] = $value;
        }
    }

    /**
     * Store the provided configuration array as tenant application configuration.
     * 
     * @param array $cfg_app
     *            the tenant application configuration
     */
    public function store_app_config (array $cfg_app)
    {
        $settings_path = "../config/settings_app";
        $cfgStr = serialize($cfg_app);
        $cfgStrBase64 = base64_encode($cfgStr);
        $info = "<p>" . i("c6AQAt|°%1° is written ... ", $settings_path);
        $byte_cnt = file_put_contents($settings_path, $cfgStrBase64);
        $info .= $byte_cnt . " " . i("n6ky2H|Byte.") . "</p>";
        return $info;
    }

    /**
     * simple getter of the tenant settings (cfg_app).
     * 
     * @return array the configuration regarding the tenant settings
     */
    public function get_cfg ()
    {
        return $this->cfg_app;
    }

    /**
     * simple getter of the db access settings. Shall only be called by Tfyh_socket::open()
     * 
     * @return array the configuration regarding the db access settings
     */
    public function get_cfg_db ()
    {
        return $this->cfg_db;
    }
}
