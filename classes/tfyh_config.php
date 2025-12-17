<?php
/**  
 *
 *       the tools-for-your-hobby framework
 *       ----------------------------------
 *       https://www.tfyh.org
 *
 * Copyright  2023-2024  Martin Glade
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
 * A utility class to hold all application configuration. There are three layers of config data. 1.
 * Application constants which are part of the code and configure the framework (code constants), 2. data base
 * stored parameters which are typical for the app's function and (values within a config table) 3.
 * administrative parameters whih are typical for the tenant using the application (the setgtings_app and
 * settings_db file).
 */
if (file_exists("../classes/tfyh_data.php")) {
    include_once "../classes/tfyh_config_item.php";
    include_once "../classes/tfyh_data_item.php";
    include_once "../classes/tfyh_data.php";
}

// this also includes: tfyh_data.php and tfyh_data_item.php
class Tfyh_config
{

    /* ------------------------------------------------------------------- */
    /* ---------- LEGACY PART -------------------------------------------- */
    /* ------------------------------------------------------------------- */
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

    /**
     * Debug level to add more information for support cases.
     */
    public $debug_level;

    /**
     * configuraton regarding the tenant
     */
    private $cfg_app;

    /**
     * configuraton regarding the data base access
     */
    private $cfg_db;

    /**
     * configuration default values. They will take effect, if no setting is provided. They shall be set in
     * the defaults section of the settings_tfyh file.
     */
    private $cfg_defaults;

    /**
     * true, if the classic configuration load is used. False for json cfg load
     */
    public $mode_classic;

    /* ------------------------------------------------------------------- */
    /* ---------- COMMON AND TREE MODE PART ------------------------------ */
    /* ------------------------------------------------------------------- */
    
    /**
     * Mandatory set of configuration files for the tree mode (except encoded "dbSettings"). The associative
     * array holds [ filename => object ], wherein object is [ name, path ]. If one of these is missing, the
     * application will not start, but prompt a simple error message right away.
     */
    public static $mandatory_cfg_files = ["descriptor" => null,"dataTypes" => null,"framework" => null,
            "appObjects" => null,"appTables" => ".appObjects.Tables",
            "appSettings" => ".appObjects.Application"
    ];

    /**
     * application information: version as "version_string", "release", "major", "minor" and "drop" and
     * copyright.
     */
    public $app_info;

    public $language_code;

    /**
     * THe root item of the entire configuration
     */
    private $cfg_root_item;

    /**
     * The common toolbox.
     */
    private $toolbox;

    /* ----------------------------------------------------------------- */
    /* ------ BUILD AND LOAD ------------------------------------------- */
    /* ----------------------------------------------------------------- */
    
    /**
     * Construct the Util class. This reads the configuration, initilizes the logger and the navigation menu,
     * asf.
     */
    public function __construct (Tfyh_toolbox $toolbox)
    {
        $this->toolbox = $toolbox;
        // LEGACY
        $this->mode_classic = ! file_exists("../config/settings/dbSettings");
        if ($this->mode_classic) {
            $this->init_classic();
        }
        // TREE MODE
        else
            $this->init();
    }

    /**
     * Initialize the configuration loader
     */
    private function init ()
    {
        // initialze the data type framework
        Tfyh_data::init();
        // initialze the data management framework
        include_once "../classes/tfyh_data_item.php";
        include_once "../classes/tfyh_config_item.php";
        // load the configuration
        $this->load_cfg();
    }

    /**
     * Check wether all configuration files are available. This is usually only needed in the development
     * phase, where thigs can get mixed up. Kept in for analogy with the JavaScript code, where files could
     * really be missing.
     */
    private function load_cfg ()
    {
        // read the configuration files
        $files = scandir("../config/settings");
        foreach (self::$mandatory_cfg_files as $cfg_file => $object_if_missing)
            if (! in_array($cfg_file, $files)) {
                if (is_null($this->get_cfg_root_item())) {
                    echo "the very first mandatory configuration file $cfg_file is missing. Aborting.";
                    exit(); // really exit. No test case left over.
                } elseif (! $this->get_cfg_root_item()->has_child($cfg_file)) {
                    // create file from object template. Cf. $this->include_settings().
                    $initial = $this->cfg_root_item->get_child($cfg_file, $object_if_missing);
                    file_put_contents("../config/settings/" . $cfg_file, 
                            $initial->to_string(false, "csv", 99, false));
                } else {
                    echo "mandatory configuration file $cfg_file is missing. Aborting.";
                    exit(); // really exit. No test case left over.
                }
            }
        // read the configuration
        $this->init_items();
    }

    /**
     * Include a ../config/settings file in the configuration. Replace the contents, if already included
     * 
     * @param String $settings_file
     *            the name of the file to include, e.g. "appObjects"
     * @param String $object_if_missing
     *            if the file does not exist, a file is created based on the object template.
     * @param bool $from_defaults
     *            set true to get the settings from the "../config/settings/_defaults/" directory instead of
     *            "../config/settings/".
     * @return String an error message, if applicable. Else an empty string.
     */
    public function include_settings (String $settings_file, String $object_if_missing = null, 
            bool $from_defaults = false)
    {
        // read the configuration
        $fname = "../config/settings/" . (($from_defaults) ? "_defaults/" : "") . $settings_file;
        if (! file_exists($fname) && ! is_null($object_if_missing)) {
            // create file from object template. Cf. $this->load_cfg)()
            if (! $this->get_cfg_root_item()->has_child($settings_file)) {
                $initial = $this->cfg_root_item->get_child($settings_file, $object_if_missing);
                file_put_contents("../config/settings/" . $settings_file, 
                        $initial->to_string(false, "csv", 99, false));
            }
        }
        $branch_root = Tfyh_config_item::read_branch($fname, true);
        if (is_string($branch_root))
            // a configuration file is missing or corrupt, ignore it. This can ahppen with efs
            // import files.
            return $branch_root;
        if (is_null($branch_root))
            // a configuration file is missing or corrupt, ignore it. This can ahppen with efs
            // import files.
            return "Error reading $settings_file. Returned null object.";
        
        $this->cfg_root_item->attach_branch($settings_file, $branch_root);
        // object templates initalisation needs the full path, must there be done after attachment
        // to the
        // config root.
        $this->init_objects($branch_root); // create the obect templates flat array
        $this->expand_object_templates(); // expand nested object templates.
                                          // the included settings may have overwritten a user setting. Fix
                                          // this,
        return "";
    }

    /**
     * merge the session usrs preferences into the configuration
     */
    public function merge_session_user_preferences ()
    {
        if (isset($this->toolbox->users->session_user) &&
                 isset($this->toolbox->users->session_user["preferences"]) &&
                 method_exists($this->toolbox->users, "add_user_preferences")) {
            $this->toolbox->users->add_user_preferences($this->toolbox->users->session_user["preferences"]);
            // refresh the directly accessible variables also.
            $this->init_constants();
        }
    }

    /**
     * Copy all config item definitions of data type object into the Tfyh_data::$object_templates associative
     * array (path => template branch).
     */
    private function init_objects (Tfyh_config_item $item)
    {
        if (strcmp($item->get_type(), "object") == 0)
            Tfyh_data::$object_templates[$item->get_path()] = $item;
        foreach ($item->get_children() as $childname => $child) {
            $this->init_objects($child);
        }
    }

    /**
     * Object templates can be nested, e.g. the appSettings object with a appSettings object as child. Then
     * the appSettings object in the appSettings template has no children. Using this template will then
     * create incomplete object instances. This is cared for here.
     */
    private function expand_object_templates ()
    {
        if (is_array(Tfyh_data::$object_templates))
            foreach (Tfyh_data::$object_templates as $path => $template_root)
                $this->expand_object_template($template_root);
    }

    /**
     * The recursive section of expand_object_templates.
     */
    private function expand_object_template (Tfyh_config_item $item)
    {
        $children_names = array_keys($item->get_children());
        foreach ($children_names as $cname) {
            $child = $item->get_child($cname);
            $ctype = $child->get_type();
            if ((count($child->get_children()) == 0) && (substr($child->get_type(), 0, 1) == ".")) {
                // the child is an object, but does not have any children. Expand it.
                $cached_descriptor = $child->get_descriptor_clone(); // cahce its descriptor
                $item->remove_branch($cname); // remove it
                $expanded_child = $item->get_child($cname, $ctype); // add instead the object
                                                                    // template
                $expanded_child->set_descriptor($cached_descriptor); // restore the descriptor.
                $this->expand_object_template($expanded_child); // drill down.
            }
        }
    }

    /**
     * Initialize the configuration by reading the item definition files
     */
    private function init_items ()
    {
        // remove all traces from previous sessions
        Tfyh_config_item::clear_traces();
        // create the root node
        $this->cfg_root_item = Tfyh_config_item::get_new_root("tfyhRoot");
        // add mandatory branches
        file_put_contents("../log/settings_loaded.html", "<h3>Settings load report</h3>");
        foreach (self::$mandatory_cfg_files as $cfg_bootstrap_file => $object_if_missing) {
            $errors = $this->include_settings($cfg_bootstrap_file, $object_if_missing);
            if (strlen($errors) > 0) {
                echo $errors;
                exit(); // really exit, no test case left over.
            }
        }
        // read the data base configuration from ../config/settings/dbSettings
        $this->read_db_config();
        // initialize the tables configurations, including the common fields
        $this->init_tables();
        // add the version information from ../public/version, accessable via "$this->app_info"
        $this->init_version_information();
        // legacy support to access settings via "$this->settings_tfyh"
        $this->set_classic_fields();
        // assignments for direct parameter value access
        $this->init_constants();
    }

    /**
     * some post-load assignments for direct parameter value access of the application
     */
    private function init_constants ()
    {
        // add generic defaults for the framework settings
        if (! isset($this->settings_tfyh["config"]["parameter_table_name"]))
            $this->settings_tfyh["config"]["parameter_table_name"] = "";
        // assign directly accessable framework settings
        $this->app_name = $this->settings_tfyh["config"]["app_name"];
        $this->changelog_name = $this->settings_tfyh["config"]["changelog_name"];
        if ($this->mode_classic) {
            $this->app_url = $this->settings_tfyh["config"]["app_url"];
            $this->language_code = (isset($this->cfg_app["language_code"])) ? $this->cfg_app["language_code"] : "de";
        } else {
            $this->app_url = $this->get_value_by_path(".framework.config.app_url");
            // shall be set by the user.
            $language_code = $this->get_value_by_path(".appSettings.localisation.language");
            $this->language_code = is_null($language_code) ? "de" : $language_code;
            // the classic parameters need also loading, e.g. pdf settings
            $this->load_app_configuration();
        }
    }

    /**
     * Initialize the version information in memory for display, logging and debugging
     */
    private function init_version_information ()
    {
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
    }

    /**
     * Initialize the tables by inflating their common fields (_allRecords and _versionized sections). The
     * record templates will always be put to the end for consistent tree display.
     */
    private function init_tables ()
    {
        // read the layout
        $layout_definition = $this->get_by_path(".appTables");
        if (is_null($layout_definition)) {
            echo "The data base layout information is not initialized";
            exit(); // really exit. No test case left over.
        }
        // inflate common fields
        $common_names = [];
        foreach ($layout_definition->get_children() as $tablename => $tablenode) {
            foreach ($tablenode->get_children() as $nodename => $node) {
                if (strpos($nodename, "_") === 0) {
                    $columns_to_add = $this->get_by_path(".appTables." . $nodename);
                    $columns_to_add->copy_children($tablenode, 0);
                    $tablenode->remove_branch($nodename);
                    $common_names[$nodename] = (isset($common_names[$nodename])) ? ($common_names[$nodename]) +
                             1 : 1;
                }
            }
        }
        // remove common fields defintions
        foreach ($common_names as $nodename => $count)
            $layout_definition->remove_branch($nodename);
    }

    /* ----------------------------------------------------------------- */
    /* ------ GET CONFIGURATION INFORMATION ---------------------------- */
    /* ----------------------------------------------------------------- */
    
    /**
     * simple getter of the settings root.
     * 
     * @return Tfyh_config_item the configuration root node
     */
    public function get_cfg_root_item ()
    {
        return $this->cfg_root_item;
    }

    /**
     * Get the item by the path provided.
     * 
     * @param String $path
     *            the path to the configuration item
     * @return mixed if the path leads to a configuration item, the item is return, else null.
     */
    public function get_by_path (String $path)
    {
        $path_elements = explode(".", $path);
        $current = $this->cfg_root_item;
        $p = 1;
        while (($p < count($path_elements)) && $current->has_child($path_elements[$p])) {
            $current = $current->get_child($path_elements[$p ++]);
        }
        return ($p == count($path_elements)) ? $current : null;
    }

    /**
     * Resolves the configuration path to the value with the given name. If the path is not unique, the first
     * path will be used.
     * 
     * @param String $name
     *            the configuration parameter name
     * @return mixed null if the name is not matched, the value of the first matching path, if the opath was
     *         matched in its native type.
     */
    private function get_value_by_path (String $path)
    {
        $item = $this->get_by_path($path);
        return (is_null($item)) ? null : $item->get_value();
    }

    /* ----------------------------------------------------------------- */
    /* ------------------ DATA BASE ACCESS FUNCTIONS ------------------- */
    /* ----------------------------------------------------------------- */
    
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
        $fname_settings_db = ($this->mode_classic) ? "../config/settings_db" : "../config/settings/dbSettings";
        if (file_exists($fname_settings_db)) {
            // read data base connection configuration first
            $cfgStrBase64 = file_get_contents($fname_settings_db);
            if (! $cfgStrBase64)
                return false;
            $cfg_db = unserialize(base64_decode($cfgStrBase64));
        }
        
        if (! isset($cfg_db["db_host"]) || ! isset($cfg_db["db_name"]) || ! isset($cfg_db["db_user"]) ||
                 ! isset($cfg_db["db_up"]))
            return false;
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
            file_put_contents($fname_settings_db, $cfgStrBase64);
        }
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
     * simple getter of the db access settings. Shall only be called by Tfyh_socket::open()
     * 
     * @return array the configuration regarding the db access settings
     */
    public function get_cfg_db ()
    {
        return $this->cfg_db;
    }

    /* ----------------------------------------------------------------- */
    /* ------------------ CLASSIC MODE FUNCTIONS ----------------------- */
    /* ----------------------------------------------------------------- */
    /**
     * for backwards compatibility classic configuration fields as set by settings_tfyh must be initialized as
     * well.
     */
    private function set_classic_fields ()
    {
        $fields = [
                "users" => ["action_links","user_table_name","user_id_field_name",
                        "user_archive_table_name","user_mail_field_name","user_account_field_name",
                        "user_firstname_field_name","user_lastname_field_name","use_workflows",
                        "use_concessions","use_subscriptions","useradmin_workflows","useradmin_role",
                        "self_registered_role","anonymous_role","ownerid_fields"
                ],
                "config" => ["app_name","app_url","db_layout_version_target","changelog_name",
                        "changelog_columns","parameter_table_name","forbidden_dirs","public_dirs"
                ],"upgrade" => ["src_path","version_path","remove_files"
                ],
                "init" => ["max_inits_per_hour","max_errors_per_hour","max_concurrent_sessions",
                        "max_session_duration","max_session_keepalive"
                ],"logger" => ["obsolete","maxsize","logs"
                ],"history" => [],"maxversions" => []
        ];
        // module fields
        $this->settings_tfyh = [];
        foreach ($fields as $module => $fnames) {
            foreach ($fnames as $fname) {
                $path = ".framework." . $module . "." . $fname;
                $item = $this->get_by_path($path);
                if (! is_null($this->get_by_path($path)))
                    $this->settings_tfyh[$module][$fname] = $this->get_value_by_path($path);
            }
        }
        // version history settings. Although they are in the socket section, they
        // have to be put to $this->settings_tfyh rather than to $this->settings_tfyh["socket"]
        // for legacy compatibility
        foreach (["history","maxversions","historyExclude"
        ] as $parameter) {
            $tables_list = $this->get_by_path(".framework.socket.$parameter");
            foreach ($tables_list->get_children() as $tablename => $item)
                $this->settings_tfyh[$parameter][$tablename] = $item->get_value();
        }
        $debug_cfg = $this->get_by_path(".appSettings.operations.debug_on");
        $this->debug_level = (! is_null($debug_cfg) && $debug_cfg->get_value()) ? 1 : 0;
    }

    /**
     * Load the settings the classic tfyh way using settings_db, settings_tfyh and settings_app.
     */
    private function init_classic ()
    {
        // read the framework settings
        $this->read_framework_config();
        // read data base access configuration
        $this->read_db_config();
        // read the version information
        $this->init_version_information();
        // read the application settings. They may have changed, so this can also be triggered from
        // outside
        $this->load_app_configuration();
        // post-load assignments for direct parameter value access of the application
        $this->init_constants();
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
        $this->cfg_app["app_url"] = "https: // www.tfyh.org";
        $this->cfg_app["language_code"] = "de";
        
        if ($this->mode_classic) {
            // apply the application configuration defaults as available in the framework settings
            if (isset($this->settings_tfyh["default"]) && is_array($this->settings_tfyh["default"]))
                foreach ($this->settings_tfyh["default"] as $key => $default_value)
                    $this->cfg_app[$key] = $default_value;
            // read the application settings from the file
            $this->read_app_config();
            
            // assign directly accessable application settings
            $this->pdf_document_author = $this->cfg_app["pdf_document_author"];
            $this->debug_level = ((strcasecmp($this->cfg_app["debug_support"], "on") == 0) ||
                     (intval($this->cfg_app["debug_support"]) > 0)) ? 1 : 0;
        } else {
            if (! is_null($this->get_by_path(".appSettings.communication.pdf.margins")))
                $this->cfg_app["pdf_margins"] = $this->get_by_path(".appSettings.communication.pdf.margins")->get_value();
            if (! is_null($this->get_by_path(".appSettings.communication.pdf.footer_text")))
                $this->cfg_app["pdf_footer_text"] = $this->get_by_path(
                        ".appSettings.communication.pdf.footer_text")->get_value();
        }
        // assign directly accessable application settings
        $this->pdf_footer_text = $this->cfg_app["pdf_footer_text"];
        $this->pdf_margins = (is_array($this->cfg_app["pdf_margins"]) &&
                 (count($this->cfg_app["pdf_margins"]) == 5)) ? $this->cfg_app["pdf_margins"] : [15,15,15,
                        10,10
                ];
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
        exit(); // really exit. No test case left over.
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
                    exit(); // really exit. No test case left over.
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
}
