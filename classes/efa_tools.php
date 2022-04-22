<?php

/**
 * class file for the efaCloud toolbox for efacloud record management and data base layout changes when
 * upgrading.
 */
class Efa_tools
{

    /**
     * Tables with an efacloud record id field. Generated according to the selected data base layout.
     */
    public $ecrid_at;

    /**
     * Tables with an efacloud record history field. Generated according to the selected data base layout.
     */
    private $ecrhis_at;

    /**
     * The data base connection socket.
     */
    private $socket;

    /**
     * The generic toolbox.
     */
    private $toolbox;

    /**
     * The efa tables function set.
     */
    private $efa_tables;

    /**
     * Log path for specific efa_tools logging
     */
    private $efa_tools_log_default = "../log/efa_tools.log";

    private $efa_tools_log;

    /**
     * public Constructor.
     */
    public function __construct (Efa_tables $efa_tables, Tfyh_toolbox $toolbox)
    {
        $this->efa_tables = $efa_tables;
        $this->socket = $efa_tables->socket;
        $this->toolbox = $toolbox;
        $this->efa_tools_log = $this->efa_tools_log_default;
        
        include_once "../classes/efa_db_layout.php";
        $this->ecrid_at = [];
        $this->ecrhis_at = [];
        foreach (Efa_db_layout::db_layout($efa_tables->db_layout_version) as $tablename => $columns) {
            if (array_key_exists("ecrid", $columns))
                $this->ecrid_at[$tablename] = true;
            if (array_key_exists("ecrhis", $columns))
                $this->ecrhis_at[$tablename] = true;
        }
    }

    /**
     * Change the log path to a different one, for specific audit logging.
     * 
     * @param String $log_path
     *            the new log path. Set to "" to restore the default.
     */
    public function change_log_path (String $log_path)
    {
        if (strlen($log_path) == 0) {
            if (file_exists($this->efa_tools_log) && file_exists($this->efa_tools_log_default))
                file_put_contents($this->efa_tools_log_default, file_get_contents($this->efa_tools_log), 
                        FILE_APPEND);
            elseif (file_exists($this->efa_tools_log))
                rename($this->efa_tools_log, $this->efa_tools_log_default);
            $this->efa_tools_log = $this->efa_tools_log_default;
        } else {
            $this->efa_tools_log = $log_path;
            file_put_contents($log_path, "");
        }
    }

    /* --------------------------------------------------------------------------------------- */
    /* --------------- EFA CLOUD POST UPGRADE SUPPORT SCRIPTS -------------------------------- */
    /* --------------------------------------------------------------------------------------- */
    
    /**
     * Adjust the tfyh history settings to comply with the current data base layout. This will add a history
     * entry for each table which has a history and remove excess history settings. This function is called by
     * pages/upgrade.php from 2.3.0_15 to 2.3.0_19 instead of upgrade_efa_tables() which is the default
     * starting with 2.3.1_07.
     */
    // TODO make private, once V2.3.0 becomes obsolete.
    public function adjust_tfyh_history_settings (bool $callerIsThis = false)
    {
        // adapt the history section in the tfyh setting to enable history collection
        $tfyh_settings = explode("# --- border history section ---", 
                file_get_contents("../config/settings_tfyh"), 3);
        $tfyh_settings_modified = $tfyh_settings[0] . "# --- border history section ---\n";
        foreach ($this->ecrhis_at as $tablename => $ecrhis_used) {
            $colnames = $this->socket->get_column_names($tablename);
            if (in_array("ecrhis", $colnames)) {
                $tfyh_settings_modified .= "history." . $tablename . "=\"ecrhis\"\n";
                $tfyh_settings_modified .= "maxversions." . $tablename . "=20\n";
            }
        }
        $tfyh_settings_modified .= "# --- border history section ---" . $tfyh_settings[2];
        file_put_contents("../config/settings_tfyh", $tfyh_settings_modified);
        
        // TODO: obsolete. Remove when versions 2.3.0 become obsolete. For 2.3.1_07 ff. the call goes to
        // upgrade_efa_tables() instead during upgrade
        // procedure.
        /*
         * adjust the data base layout. This is called in this section, because the update trigger is already
         * existing in the upgrade procedure.
         */
        if (! $callerIsThis &&
                 ($this->efa_tables->db_layout_version < $this->efa_tables->db_layout_version_target))
            $this->update_database_layout($_SESSION["User"][$this->toolbox->users->user_id_field_name], 
                    $this->efa_tables->db_layout_version_target, false);
    }

    /**
     * Upgrade everything in the efa tables which shall be updated to comply with this versions functionality.
     * 
     * @param bool $forced
     *            set true to force an upgrade, even if the db_layout_version parameter is on target.
     */
    public function upgrade_efa_tables (bool $forced = false)
    {
        // adjust the data base layout.
        if ($forced || ($this->efa_tables->db_layout_version < $this->efa_tables->db_layout_version_target)) {
            $this->update_database_layout($_SESSION["User"][$this->toolbox->users->user_id_field_name], 
                    $this->efa_tables->db_layout_version_target, false);
            $this->adjust_tfyh_history_settings(true);
        }
    }

    /* --------------------------------------------------------------------------------------- */
    /* ----------------------- DATA BASE LAYOUT GENERATOR FUNCTIONS -------------------------- */
    /* --------------------------------------------------------------------------------------- */
    
    /**
     * Check the current session user whether it has admin rights.
     * 
     * @return boolean|array the efaCloudUser record. if the user has admin rights, or false, if not.
     * @param bool $log_violations
     *            log a violation, if the user has not sufficient priviledges, set false to skip logging.
     * @return boolean|array
     */
    private function is_admin_session_user (bool $log_violations)
    {
        if (! isset($_SESSION["User"]) || ! isset($_SESSION["User"]["efaCloudUserID"])) {
            $this->toolbox->logger->log(1, 0, "Data base manipulation cancelled. User not valid.");
            return false;
        }
        // cache the user record for re-insertion after table reset.
        $appUserID = $_SESSION["User"]["efaCloudUserID"];
        $admin_record = $this->socket->find_record("efaCloudUsers", "efaCloudUserID", $appUserID);
        if ($admin_record === false) {
            if ($log_violations)
                $this->toolbox->logger->log(1, $appUserID, 
                        "Data base manipulation prohibited for unkown user '" . $appUserID . "'.");
            return false;
        }
        if (! isset($admin_record["Rolle"]) || (strcasecmp($admin_record["Rolle"], "admin") != 0)) {
            if ($log_violations)
                $this->toolbox->logger->log(1, $appUserID, 
                        "Data base manipulation prohibited for User '" . $admin_record["efaCloudUserID"] .
                                 "'. Insufficient access priviledges.");
            return false;
        }
        return $admin_record;
    }

    /**
     * adapt 'db_layout' configuration parameter within db configuration file, cf. install.php
     * 
     * @param int $db_layout_version
     *            the version to be set.
     */
    private function set_db_layout_version (int $db_layout_version)
    {
        // adapt 'db_layout' configuration parameter within db configuration file, cf. install.php
        $cfg = $this->toolbox->config->get_cfg();
        $cfg["db_layout"] = $db_layout_version;
        $cfg["db_up"] = Tfyh_toolbox::swap_lchars($cfg["db_up"]);
        $cfgStr = serialize($cfg);
        $cfgStrBase64 = base64_encode($cfgStr);
        file_put_contents("../config/settings_db", $cfgStrBase64);
    }

    /**
     * Execute and log an $sql_cmd.
     * 
     * @param unknown $sql_cmd            
     */
    private function execute_and_log (String $appUserID, String $sql_cmd, String $log_message)
    {
        $success = $this->socket->query($sql_cmd);
        if ($success === false) {
            $fail_message = "Failed data base statement for User ' . $appUserID . ': " . $log_message .
                     "\n   Error: " . $this->socket->get_last_mysqli_error() . " in " . $sql_cmd;
            $this->toolbox->logger->log(2, $appUserID, $fail_message);
        } else {
            $success_message = "Executed data base statement for User ' . $appUserID . ': " . $log_message;
            $this->toolbox->logger->log(0, $appUserID, $success_message);
        }
    }

    /**
     * Delete all existing tables and build the data base from scratch. Select efa2 and/or efaCloud tables
     * separately. Will return and do nothin in case of unsufficient user priviledges of the
     * $_SESSION["User"]. If the efaCloud tables are reset, the current user will be added as admin to ensure
     * that the data base stays manageable.
     * 
     * @param bool $init_efa2
     *            set true to initialize all efa2 tables (tablenames starting with "efa2")
     * @param bool $init_efaCloud
     *            set true to initialize all efaCloud tables (tablenames starting with "efaCloud")
     */
    public function init_efa_data_base (bool $init_efa2, bool $init_efaCloud)
    {
        $result = "";
        // check user priviledges
        if (strcasecmp("admin", $_SESSION["User"]["Rolle"]) != 0)
            return "Fehler: Nutzer hat nicht ausreichend Rechte, um die Datenbank zu initialisieren.";
        $appUserID = $_SESSION["User"]["efaCloudUserID"];
        $admin_record = $this->socket->find_record("efaCloudUsers", "efaCloudUserID", $appUserID);
        if ($admin_record === false)
            $admin_record = $_SESSION["User"];
        // now reset all tables
        $db_layout_version = $this->efa_tables->db_layout_version_target;
        include_once '../classes/efa_db_layout.php';
        $this->toolbox->logger->log(0, $appUserID, date("Y-m-d H:i:s") . ": Starting init_efa_data_base().");
        $tables_of_layout = Efa_db_layout::db_layout($db_layout_version);
        foreach ($tables_of_layout as $tablename => $unused_definition) {
            if (($init_efa2 && (strpos($tablename, "efa2") === 0)) ||
                     ($init_efaCloud && (strpos($tablename, "efaCloud") === 0))) {
                $sql_cmd = "DROP TABLE `" . $tablename . "`";
                $log_message = "Drop table `" . $tablename . "`.";
                $result .= $log_message . "<br>";
                $this->execute_and_log($appUserID, $sql_cmd, $log_message);
                $log_message = "Create table `" . $tablename .
                         "` with all columns according to the current layout version " .
                         $this->efa_tables->db_layout_version_target;
                $result .= $log_message . "<br>";
                $sql_cmds = Efa_db_layout::build_sql_add_table_commands($db_layout_version, $tablename);
                foreach ($sql_cmds as $sql_cmd)
                    $this->execute_and_log($appUserID, $sql_cmd, $log_message);
            }
        }
        if ($init_efaCloud) {
            $log_message = "Admin '" . $admin_record["efaCloudUserID"] . "' was inserted into 'efaCloudUsers'.";
            $success = $this->socket->insert_into($appUserID, "efaCloudUsers", $admin_record);
            if (! is_numeric($success))
                $log_message = "Admin insertion failed: " . $success . "<br>";
            else
                $log_message = $log_message . "<br>";
            $this->toolbox->logger->log(0, $appUserID, $log_message);
            $result .= $log_message;
        }
        $this->set_db_layout_version($this->efa_tables->db_layout_version_target);
        return $result;
    }

    /**
     * Update all tables and columns to comply to the selected data base layout.
     * 
     * @param String $appUserID
     *            the ID of the application user of the user who performs the update. For change logging.
     * @param int $use_layout_version
     *            the data base layout version which shall be used.
     * @param bool $verify_only
     *            If this flag is true, nothing will be done, but just checked whether something would be
     *            done. Only exception: default value setting is not checked.
     * @return true, for $verify_only set and a deviation from the layout false
     */
    public function update_database_layout (String $appUserID, int $use_layout_version, bool $verify_only)
    {
        // check user priviledges
        $admin_record = $this->is_admin_session_user(false);
        file_put_contents($this->efa_tools_log, 
                date("Y-m-d H:i:s") . ": Starting update_database_layout(" . $use_layout_version . ") [" .
                         json_encode($verify_only) . ", " . $appUserID . "].\n", FILE_APPEND);
        if (($admin_record === false) && ! $verify_only) {
            file_put_contents($this->efa_tools_log, 
                    date("Y-m-d H:i:s") .
                             ": User is no admin and thus not allowed to change anything. Aborting.\n", 
                            FILE_APPEND);
            return false;
        }
        
        // no reverification within 10 minutes.
        if ($verify_only) {
            if (file_exists("../log/efa_tools_db_verified.time")) {
                $last_verification = intval(file_get_contents("../log/efa_tools_db_verified.time"));
                if ((time() - $last_verification) < 120) {
                    file_put_contents($this->efa_tools_log, 
                            date("Y-m-d H:i:s") . ": Last verification less than 2 mins ago, returning.\n", 
                            FILE_APPEND);
                    return true;
                }
            }
        }
        
        // now adjust all tables
        include_once "../classes/efa_db_layout.php";
        $table_names_existing = $this->socket->get_table_names();
        $lower_case_tablenames = true;
        foreach ($table_names_existing as $table_name_existing)
            if (strcmp(strtolower($table_name_existing), $table_name_existing) != 0)
                $case_sensitive_names = false;
        
        $tables_of_layout = Efa_db_layout::db_layout($use_layout_version);
        if (! $verify_only)
            $this->toolbox->logger->log(0, $appUserID, 
                    date("Y-m-d H:i:s") . ": Starting update_database_layout(" . $use_layout_version . ").");
        // add or adjust tables according to the expected layout
        foreach ($tables_of_layout as $tablename => $unused_definition) {
            if (in_array($tablename, $table_names_existing)) {
                if (! $this->update_table_layout($appUserID, $use_layout_version, $tablename, $verify_only))
                    return false;
            } else {
                if ($verify_only) {
                    file_put_contents($this->efa_tools_log, 
                            date("Y-m-d H:i:s") . ": Verification failed. Table $tablename missing.\n", 
                            FILE_APPEND);
                    return false;
                }
                $log_message = "Create table `" . $tablename .
                         "` with all columns according to the current layout.";
                $sql_cmds = Efa_db_layout::build_sql_add_table_commands($use_layout_version, $tablename);
                foreach ($sql_cmds as $sql_cmd)
                    $this->execute_and_log($appUserID, $sql_cmd, $log_message);
            }
        }
        // and drop the obsolete ones
        // Microsoft mySQL implementations on MS Azure use all lower case table names.
        // Copy definitions to lower case to avoid table drop later.
        foreach ($tables_of_layout as $table_name => $table_definition)
            if (strcmp(strtolower($table_name), $table_name) != 0)
                $tables_of_layout[strtolower($tablename)] = $tables_of_layout[$tablename];
        foreach ($table_names_existing as $tablename) {
            if (! array_key_exists($tablename, $tables_of_layout)) {
                if ($verify_only) {
                    return false;
                    file_put_contents($this->efa_tools_log, 
                            date("Y-m-d H:i:s") . ": Verification failed. Table $tablename extra.\n", 
                            FILE_APPEND);
                }
                $sql_cmd = "DROP TABLE `" . $tablename . "`";
                $log_message = "Drop table `" . $tablename . "`.";
                $this->execute_and_log($appUserID, $sql_cmd, $log_message);
            }
        }
        
        // notify and register activity.
        if (! $verify_only) {
            $this->set_db_layout_version($use_layout_version);
            $this->toolbox->logger->log(0, $appUserID, 
                    date("Y-m-d H:i:s") . ": database_layout upgraded to " . $use_layout_version);
            file_put_contents("../log/efa_tools_db_verified.time", strval(time()));
        } else {
            $db_layout_verified_times = 0;
            if (file_exists("../config/db_layout_verified_times"))
                $db_layout_verified_times = intval(file_get_contents("../config/db_layout_verified_times"));
            if ($db_layout_verified_times < 50) {
                $db_layout_verified_times ++;
                file_put_contents("../config/db_layout_verified_times", $db_layout_verified_times);
                $this->toolbox->logger->log(0, $appUserID, 
                        date("Y-m-d H:i:s") .
                                 ": Verified database_layout $db_layout_verified_times of 50 times successfully.");
            }
        }
        
        // if the $verify_only flag is set and this point reached, all is fine
        return true;
    }

    /**
     * Update the layout of a single table. The class "efa_db_layout.php" must be imported before callig this
     * function. This way multiple layout versions can be supported.
     * 
     * @param String $appUserID
     *            the ID of the application user of the user who performs the update. For change logging.
     * @param int $use_layout_version
     *            the data base layout version which shall be used.
     * @param bool $verify_only
     *            If this flag is true, nothing will be done, but just checked whether something would be
     *            done. Only exception: default value setting is not checked.
     * @return bool : true. For $verify_only set AND a deviation from the layout: false
     */
    private function update_table_layout (String $appUserID, int $use_layout_version, String $tablename, 
            bool $verify_only)
    {
        $column_names_existing = $this->socket->get_column_names($tablename);
        $column_type_descriptions_existing = $this->socket->get_column_types($tablename);
        $column_types_existing = [];
        $column_sizes_existing = [];
        foreach ($column_type_descriptions_existing as $cname => $column_type_description) {
            $has_size = strpos($column_type_description, "(") !== false;
            $column_types_existing[] = ($has_size) ? explode("(", $column_type_description)[0] : $column_type_description;
            $column_sizes_existing[] = ($has_size) ? intval(
                    str_replace(")", "", explode("(", $column_type_description)[1])) : 0;
        }
        
        $indexes_existing = $this->socket->get_indexes($tablename, false);
        $autoincrements_existing = $this->socket->get_autoincrements($tablename);
        $db_layout = Efa_db_layout::db_layout($use_layout_version);
        $columns_expected = $db_layout[$tablename];
        $indexes_expected = [];
        file_put_contents($this->efa_tools_log, 
                date("Y-m-d H:i:s") . ": Starting update_table_layout(" . $tablename . ").\n", FILE_APPEND);
        file_put_contents($this->efa_tools_log, 
                date("Y-m-d H:i:s") . ": Indexes existing(" . $tablename . json_encode($indexes_existing) .
                         ").\n", FILE_APPEND);
        
        // adjust or add columns according to the expected layout
        foreach ($columns_expected as $cname => $cdefinition) {
            
            // find the column within the set of exiting ones
            $c = array_search($cname, $column_names_existing, true);
            if ($c !== false) {
                // get parameters of column
                $ctype = trim($column_types_existing[$c]);
                $csize = ((strcasecmp($ctype, "text") == 0) || (strcasecmp($ctype, "mediumtext") == 0)) ? 0 : intval(
                        trim($column_sizes_existing[$c]));
                $definition = explode(";", $cdefinition);
                $dtype = trim($definition[0]);
                $dsize = intval(trim($definition[1]));
                $dunique = (strlen($definition[4]) > 0);
                $dautoincrement = (strlen($definition[5]) > 0);
                // column is existing. Adjust default first
                $default = Efa_db_layout::is_null_column_to_update($use_layout_version, $tablename, $cname);
                if (($default !== true) && ! $verify_only) {
                    $sql_cmd = Efa_db_layout::build_sql_column_command($use_layout_version, $tablename, 
                            $cname, Efa_db_layout::$sql_column_null_to_zero_adjustment);
                    $log_message = "Set Default for `" . $tablename . "`.`" . $cname . "` from NULL to " .
                             $default;
                    $this->execute_and_log($appUserID, $sql_cmd, $log_message);
                    file_put_contents($this->efa_tools_log, 
                            date("Y-m-d H:i:s") . ": Changed default for $cname in " . $tablename .
                                     " to current value.\n", FILE_APPEND);
                }
                // now change the column (change all, whatever mismatch was detected)
                if (! $verify_only) {
                    $activity_template = Efa_db_layout::$sql_change_column_command;
                    $sql_cmd = Efa_db_layout::build_sql_column_command($use_layout_version, $tablename, 
                            $cname, $activity_template);
                    $log_message = "Change column `" . $tablename . "`.`" . $cname . "` to current definition.";
                    $this->execute_and_log($appUserID, $sql_cmd, $log_message);
                    file_put_contents($this->efa_tools_log, 
                            date("Y-m-d H:i:s") . ": Changed $cname in " . $tablename .
                                     " to current definition.\n", FILE_APPEND);
                } else {
                    // or check type and size, if just the verification shall happen
                    $def_parts = explode(";", $cdefinition);
                    if (strcasecmp($ctype, $dtype) != 0) {
                        file_put_contents($this->efa_tools_log, 
                                date("Y-m-d H:i:s") .
                                         ": Verification failed. Column $tablename.$cname with type $ctype instead of $dtype:\n", 
                                        FILE_APPEND);
                        return false;
                    } else 
                        if ($csize != $dsize) {
                            file_put_contents($this->efa_tools_log, 
                                    date("Y-m-d H:i:s") .
                                             ": Verification failed. Column $tablename.$cname with size $csize instead of " .
                                             $dsize . ".\n", FILE_APPEND);
                            return false;
                        }
                }
            } else {
                // column is NOT existing. Adjust default first
                if ($verify_only) {
                    file_put_contents($this->efa_tools_log, 
                            date("Y-m-d H:i:s") .
                                     ": Verification failed. Column $tablename.$cname is missing.\n", 
                                    FILE_APPEND);
                    return false;
                }
                $activity_template = Efa_db_layout::$sql_add_column_command;
                $log_message = "Add column `" . $tablename . "`.`" . $cname . "` with current definition.";
                $sql_cmd = Efa_db_layout::build_sql_column_command($use_layout_version, $tablename, $cname, 
                        $activity_template);
                $this->execute_and_log($appUserID, $sql_cmd, $log_message);
                file_put_contents($this->efa_tools_log, 
                        date("Y-m-d H:i:s") . ": Adding missing $cname in " . $tablename . ".\n", FILE_APPEND);
            }
            
            // add a unique quality, if not yet existing
            if ($dunique) {
                $indexes_expected[] = $cname;
                if (! in_array($cname, $indexes_existing)) {
                    $sql_cmd = "ALTER TABLE `" . $tablename . "` ADD UNIQUE(`" . $cname . "`); ";
                    $log_message = "Add unique property to `" . $tablename . "`.`" . $cname . "`.";
                    if (! $verify_only) {
                        $this->execute_and_log($appUserID, $sql_cmd, $log_message);
                        file_put_contents($this->efa_tools_log, 
                                date("Y-m-d H:i:s") . ": Adding missing unique property for $cname in " .
                                         $tablename . ".\n", FILE_APPEND);
                    }
                }
            }
            
            // add an autoincrement quality, if needed
            // ALTER TABLE `efaCloudPartners` CHANGE `ID` `ID` INT NOT NULL AUTO_INCREMENT;
            if ($dautoincrement) {
                if (! in_array($cname, $autoincrements_existing)) {
                    $sql_cmd = "ALTER TABLE `" . $tablename . "` CHANGE `" . $cname . "` `" . $cname .
                             "` INT UNSIGNED NOT NULL AUTO_INCREMENT";
                    $log_message = "Add auto increment property to `" . $tablename . "`.`" . $cname . "`.";
                    if (! $verify_only) {
                        $this->execute_and_log($appUserID, $sql_cmd, $log_message);
                        file_put_contents($this->efa_tools_log, 
                                date("Y-m-d H:i:s") . ": Adding missing autoincrement property for $cname in " .
                                         $tablename . ".\n", FILE_APPEND);
                    }
                }
            }
        }
        
        file_put_contents($this->efa_tools_log, 
                date("Y-m-d H:i:s") . ": Indexes expected(" . $tablename . json_encode($indexes_expected) .
                         ").\n", FILE_APPEND);
        
        // delete columns which are obsolete in the expected layout
        foreach ($column_names_existing as $cname_existing) {
            if (! array_key_exists($cname_existing, $columns_expected)) {
                if ($verify_only) {
                    file_put_contents($this->efa_tools_log, 
                            date("Y-m-d H:i:s") .
                                     ": Verification failed. Column $tablename.$cname_existing extra.\n", 
                                    FILE_APPEND);
                    return false;
                }
                // e.g. ALTER TABLE `efaCloudPartners` DROP `UseEcrm`;
                $sql_cmd = "ALTER TABLE `" . $tablename . "` DROP `" . $cname_existing . "`;";
                $log_message = "Drop obsolete column `" . $tablename . "`.`" . $cname_existing . "`.";
                $this->execute_and_log($appUserID, $sql_cmd, $log_message);
            }
        }
        
        // delete indices which are obsolete in the expected layout
        foreach ($indexes_existing as $iname_existing) {
            if (! in_array($iname_existing, $indexes_expected)) {
                if ($verify_only) {
                    file_put_contents($this->efa_tools_log, 
                            date("Y-m-d H:i:s") .
                                     ": Verification failed. Index $tablename.$iname_existing extra.\n", 
                                    FILE_APPEND);
                    return false;
                }
                // ALTER TABLE `efa2autoincrement` DROP INDEX `Sequence_7`;
                $sql_cmd = "ALTER TABLE `" . $tablename . "` DROP INDEX `" . $iname_existing . "`;";
                $log_message = "Drop obsolete index `" . $tablename . "`.`" . $iname_existing . "`.";
                $this->execute_and_log($appUserID, $sql_cmd, $log_message);
            }
        }
        
        // if the $verify_only flag is set and this point reached, all is fine
        if ($verify_only) {
            file_put_contents($this->efa_tools_log, 
                    date("Y-m-d H:i:s") . ": Verification successful for '" . $tablename . "'.\n", FILE_APPEND);
        } else
            file_put_contents($this->efa_tools_log, 
                    date("Y-m-d H:i:s") . ": Completed update_table_layout(" . $tablename . ").\n", 
                    FILE_APPEND);
        return true;
    }

    /**
     * Find data records without ecrids and add the ecrids to them.
     * 
     * @param int $count
     *            maximum number of ecrids to be added.
     * @return int count of ecrids added.
     */
    public function add_ecrids (int $count)
    {
        // include layout definition
        include_once '../classes/efa_db_layout.php';
        $efa_db_layout = Efa_db_layout::db_layout($this->efa_tables->db_layout_version);
        
        // create and add $count efaCloud record Ids
        $ecrids = $this->efa_tables->generate_ecrids($count);
        $i = 0;
        
        foreach ($efa_db_layout as $tablename => $columns) {
            $column_names = $this->socket->get_column_names($tablename);
            // add only if an ecrid field is within the table in both the real one and the layout definition.
            if (($this->ecrid_at[$tablename] === true) && in_array("ecrid", $column_names)) {
                // add only using chunks of 100 to avoid memory overflow.
                $records_wo_ecrid = $this->socket->find_records_sorted_matched($tablename, 
                        ["ecrid" => ""
                        ], 100, "NULL", "", true);
                while (($records_wo_ecrid !== false) && (count($records_wo_ecrid) > 0)) {
                    file_put_contents($this->efa_tools_log, 
                            date("Y-m-d H:i:s") . ": Adding " . count($records_wo_ecrid) . "ecrids for '" .
                                     $tablename . "'.\n", FILE_APPEND);
                    foreach ($records_wo_ecrid as $record_wo_ecrid) {
                        $data_key = $this->efa_tables->get_data_key($tablename, $record_wo_ecrid);
                        // create SQL command and change log entry.
                        $sql_cmd = "UPDATE `" . $tablename . "` SET `ecrid` = '" . $ecrids[$i] . "' WHERE ";
                        $wherekeyis = "";
                        foreach ($data_key as $key => $value)
                            $wherekeyis .= "`" . $tablename . "`.`" . $key . "` = '" . strval($value) .
                                     "' AND ";
                        $sql_cmd .= substr($wherekeyis, 0, strlen($wherekeyis) - 5);
                        $result = $this->socket->query($sql_cmd);
                        $i ++;
                        if ($i >= $count)
                            return $i;
                    }
                    $records_wo_ecrid = $this->socket->find_records_sorted_matched($tablename, 
                            ["ecrid" => ""
                            ], 100, "NULL", "", true);
                }
            }
        }
        
        return $i;
    }

    /* --------------------------------------------------------------------------------------- */
    /* ---------------------------- DATA BASES STATUS SUMMARY -------------------------------- */
    /* --------------------------------------------------------------------------------------- */
    
    /**
     * Create an html readable summary of the application status to send it per mail to admins.
     */
    public function create_app_status_summary (Tfyh_toolbox $toolbox, Tfyh_socket $socket)
    {
        // Check logbooks
        $total_record_count = 0;
        $html = "<h4>Fahrtenbücher</h4>\n";
        $html .= "<table><tr><th>Fahrtenbuch</th><th>Anzahl Fahrten</th></tr>\n";
        $logbooks = $socket->count_values("efa2logbook", "LogbookName");
        foreach ($logbooks as $logbook_name => $entry_count) {
            $html .= "<tr><td>" . $logbook_name . "</td><td>" . $entry_count . "</td></tr>\n";
            $total_record_count += $entry_count;
        }
        $html .= "<tr><td>Summe</td><td>" . $total_record_count . "</td></tr></table>\n";
        
        // check table sizes
        $html .= "<h4>Tabellen und Datensätze</h4>\n";
        $html .= "<table><tr><th>Tabelle</th><th>Anzahl Datensätze</th></tr>\n";
        $table_names = $socket->get_table_names();
        $total_record_count = 0;
        foreach ($table_names as $tn) {
            $record_count = $socket->count_records($tn);
            $html .= "<tr><td>" . $tn . "</td><td>" . $record_count . "</td></tr>\n";
            $total_record_count += $record_count;
        }
        $html .= "<tr><td>Summe</td><td>" . $total_record_count . "</td></tr></table>\n";
        
        // Check users and access rights
        $html .= $toolbox->users->get_all_accesses($socket, false);
        
        // Check accessses logged.
        $html .= "<h4>Zugriffe</h4>\n";
        include_once '../classes/tfyh_statistics.php';
        $tfyh_statistics = new Tfyh_statistics();
        file_put_contents("../log/efacloud_server_statistics.csv", 
                $tfyh_statistics->pivot_timestamps(86400, 14));
        $html .= "<table><tr><th>clientID</th><th>clientName</th><th>Anzahl Zugriffe</th></tr>\n";
        $timestamps_count_all = 0;
        foreach ($tfyh_statistics->timestamps_count as $clientID => $timestamps_count) {
            $person_record = $socket->find_record("efaCloudUsers", "efaCloudUserID", $clientID);
            $full_name = ($person_record === false) ? "unbekannte ID" : ($person_record["Vorname"] . " " .
                     $person_record["Nachname"]);
            $html .= "<tr><td>" . $clientID . "</td><td>" . $full_name . "</td><td>" . $timestamps_count .
                     "</td></tr>\n";
            $timestamps_count_all += $timestamps_count;
        }
        $html .= "<tr><td>Summe</td><td></td><td>" . $timestamps_count_all . "</td></tr></table>\n";
        
        // Check backup
        $html .= "<h4>Backups</h4>\n";
        $backup_dir = "../log/backup";
        $backup_files = scandir($backup_dir);
        $backup_files_size = 0;
        $backup_files_count = 0;
        $backup_files_youngest = 0;
        foreach ($backup_files as $backup_file) {
            if (strcasecmp(substr($backup_file, 0, 1), ".") != 0) {
                $backup_files_size += filesize($backup_dir . "/" . $backup_file);
                $lastmodified = filectime($backup_dir . "/" . $backup_file);
                if ($lastmodified > $backup_files_youngest)
                    $backup_files_youngest = $lastmodified;
                $backup_files_count ++;
            }
        }
        $html .= "<p>" . $backup_files_count . " Backup-Archive mit in Summe " .
                 (intval($backup_files_size / 1024 / 102) / 10) . " MByte. \n";
        $html .= "Jüngstes Backup von " . date("Y-m-d H:i:s", $backup_files_youngest) . ".</p>\n";
        
        return $html;
    }
    /* --------------------------------------------------------------------------------------- */
    /* --------------- EFA CLOUD CLEAR REMAINS OF DELETED RECORDS ---------------------------- */
    /* --------------------------------------------------------------------------------------- */
    
    /**
     * Records sometimes are not completley deleted. Check those and remove remaining data. Only affects
     * efa-tables, not efaCloud tables.
     *
     * @param int $appUserID
     *            the ID of the verified client which requests the cleansing
     */
    public function cleanse_deleted (int $appUserID)
    {
        foreach ($this->efa_tables->efa2tablenames as $tablename) {
            $to_be_cleansed = $this->socket->find_records_matched($tablename,
                    ["LastModification" => "delete"
                    ], 1000);
            if ($to_be_cleansed !== false) {
                foreach ($to_be_cleansed as $tbc_record) {
                    $clean_record = $this->efa_tables->clear_record_for_delete($tablename, $tbc_record);
                    if ($clean_record !== false) {
                        $success = $this->socket->update_record_matched($appUserID, $tablename,
                                ["ecrid" => $tbc_record["ecrid"]
                                ], $clean_record);
                        if (strlen($success) == 0) {
                            $fields_changed = "";
                            foreach ($tbc_record as $key => $value)
                                if ($clean_record[$key] !== $value)
                                    $fields_changed .= $key . ": " . $clean_record[$key] . "; ";
                                    $notification = [];
                                    // ID is automatically generated by MySQL data base
                                    $notification["Author"] = $appUserID;
                                    // Time is automatically generated by MySQL data base
                                    $notification["Reason"] = "cleansed remaining values in deleted record: " .
                                            $fields_changed;
                                            $notification["ChangedTable"] = $tablename;
                                            $notification["ChangedRecord"] = json_encode($tbc_record);
                                            $success = $this->socket->insert_into($appUserID, "efaCloudCleansed",
                                                    $notification);
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Remove the cleansing log records older than $max_age_days. Usually 30 days wast papter basket time.
     *
     * @param int $max_age_days
     */
    public function remove_old_cleansed_records (int $max_age_days)
    {
        $sql_cmd = "DELETE FROM `efaCloudCleansed` WHERE DATEDIFF(NOW(), `Time`) > " . $max_age_days;
        $this->socket->query($sql_cmd);
    }
    
    /**
     * Records sometimes are not completley deleted. Check those and remove remaining data. Per table modify
     * not more than 10 records for speed reasons.
     *
     * @param int $appUserID
     *            the ID of the verified client which requests the cleansing
     */
    public function add_AllCrewIds (int $appUserID)
    {
        $tablename = "efa2logbook";
        $matching = ["AllCrewIds" => ""
        ];
        $to_be_modified = $this->socket->find_records_sorted_matched($tablename, $matching, 50, "NULL",
                "LastModified", true);
        if ($to_be_modified === false)
            return;
            foreach ($to_be_modified as $tbm_record) {
                $allCrewIds = $this->efa_tables->create_AllCrewIds_field($tbm_record);
                $tbm_record["AllCrewIds"] = $allCrewIds;
                // success is explicitly ignored
                $data_key = $this->efa_tables->get_data_key($tablename, $tbm_record);
                $success = $this->socket->update_record_matched($appUserID, $tablename, $data_key, $tbm_record);
            }
    }
    
}