<?php
/**
 *
 *       efaCloud
 *       --------
 *       https://www.efacloud.org
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


// TODO introduced to avoid a fata error when updating from < 2.3.2_13 to 2.3.2_13ff. in April 2023. Remove
// some day
if (! function_exists("i"))
    include_once "../classes/init_i18n.php";

/**
 * class file for the efaCloud toolbox for efacloud record management and data base layout changes when
 * upgrading.
 */
class Efa_tools
{

    /**
     * The list of tables in which records which are marked as deleted shall be finally purge. Note: they must
     * be kept to inform all clients of their deletion. Once they are purged, a client will no more be
     * notified of this deletion.
     */
    private static $tables_to_purge_deleted = ["efa2autoincrement","efa2boatdamages",
            "efa2boatreservations","efa2boats","efa2boatstatus","efa2clubwork","efa2crews","efa2destinations",
            "efa2fahrtenabzeichen","efa2groups","efa2logbook","efa2messages","efa2persons","efa2sessiongroups",
            "efa2statistics","efa2status","efa2waters"
    ];

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
     * Log path for specific efa_tools data base audit logging
     */
    private $db_audit_log = "../log/sys_db_audit.log";

    /**
     * The version code for the data base layout. If $db_layout_version >= 2 this reflects the server's
     * capability to use efaCLoud record management. Do not mix up with the API version.
     */
    public $db_layout_version;

    /**
     * public Constructor.
     * 
     * @param Tfyh_toolbox $toolbox
     *            application toolbox
     * @param Tfyh_socket $socket
     *            the socket to connect to the database
     */
    public function __construct (Tfyh_toolbox $toolbox, Tfyh_socket $socket)
    {
        $this->socket = $socket;
        $this->toolbox = $toolbox;
        include_once "../classes/efa_tables.php";
        
        $cfg_db = $toolbox->config->get_cfg_db();
        $this->db_layout_version = intval($cfg_db["db_layout_version"]);
        // minimum supported cofiguration version is db layout V3, the first with ecrid.
        // If the configuration is lower, the DB audit will not appropriately run.
        if ($this->db_layout_version < 4)
            $this->db_layout_version = 4;
        
        include_once "../classes/efa_db_layout.php";
        $this->ecrid_at = [];
        $this->ecrhis_at = [];
        foreach (Efa_db_layout::db_layout($this->db_layout_version) as $tablename => $columns) {
            if (array_key_exists("ecrid", $columns))
                $this->ecrid_at[$tablename] = true;
            if (array_key_exists("ecrhis", $columns))
                $this->ecrhis_at[$tablename] = true;
        }
        include_once "../classes/efa_archive.php"; // Used in add_FirstLastName and add_AllCrewIds
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
        /* adjust the data base layout. This is called in this section, because the update trigger is already
         * existing in the upgrade procedure. */
        if (! $callerIsThis &&
                 ($this->efa_tables->db_layout_version < Efa_db_layout::$db_layout_version_target))
            $this->update_database_layout($this->toolbox->users->session_user["@id"], 
                    Efa_db_layout::$db_layout_version_target, false);
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
        if ($forced || ($this->db_layout_version < Efa_db_layout::$db_layout_version_target)) {
            $upgrade_success = $this->update_database_layout($this->toolbox->users->session_user["@id"], 
                    Efa_db_layout::$db_layout_version_target, false);
            $this->adjust_tfyh_history_settings(true);
        }
        return $upgrade_success;
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
        if (! isset($this->toolbox->users->session_user) || ! isset(
                $this->toolbox->users->session_user["@id"])) {
            $this->toolbox->logger->log(1, 0, i("NUSssM|Data base manipulation c..."));
            return false;
        }
        // cache the user record for re-insertion after table reset.
        $appUserID = $this->toolbox->users->session_user["efaCloudUserID"];
        $admin_record = $this->socket->find_record("efaCloudUsers", "efaCloudUserID", $appUserID);
        if ($admin_record === false) {
            if ($log_violations)
                $this->toolbox->logger->log(1, $appUserID, 
                        i("5Ca1BA|Data base manipulation p...", $appUserID));
            return false;
        }
        if (! isset($admin_record["Rolle"]) || (strcasecmp($admin_record["Rolle"], "admin") != 0)) {
            if ($log_violations)
                $this->toolbox->logger->log(1, $appUserID, 
                        i("mMnBAj|Data base manipulation p...", $admin_record["efaCloudUserID"]));
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
        $cfg = $this->toolbox->config->get_cfg_db();
        $cfg["db_layout_version"] = $db_layout_version;
        $cfg["db_up"] = Tfyh_toolbox::swap_lchars($cfg["db_up"]);
        $cfgStr = serialize($cfg);
        $cfgStrBase64 = base64_encode($cfgStr);
        file_put_contents("../config/settings_db", $cfgStrBase64);
    }

    /**
     * Execute and log an $sql_cmd.
     * 
     * @param String $appUserID
     *            the user issueing the command
     * @param String $sql_cmd
     *            the command issued
     * @param String $log_message
     *            a message to include in the log
     * @return boolean the actiities success
     */
    private function execute_and_log (String $appUserID, String $sql_cmd, String $log_message)
    {
        $success = $this->socket->query($sql_cmd, $this);
        if ($success === false) {
            $fail_message = i("sHmiya|Failed data base stateme...", $appUserID, $log_message, 
                    $this->socket->get_last_mysqli_error(), $sql_cmd);
            file_put_contents($this->db_audit_log, date("Y-m-d H:i:s") . ": $fail_message.\n", FILE_APPEND);
            return false;
        } else {
            $success_message = i("sBBcUy|Executed data base state...", $appUserID, $log_message);
            file_put_contents($this->db_audit_log, date("Y-m-d H:i:s") . ": $success_message.\n", FILE_APPEND);
            return true;
        }
    }

    /**
     * Delete all existing tables and build the data base from scratch. Select efa2 and/or efaCloud tables
     * separately. Will return and do nothin in case of unsufficient user priviledges of the
     * $this->toolbox->users->session_user. If the efaCloud tables are reset, the current user will be added
     * as admin to ensure that the data base stays manageable.
     * 
     * @param bool $init_efa2
     *            set true to initialize all efa2 tables (tablenames starting with "efa2")
     * @param bool $init_efaCloud
     *            set true to initialize all efaCloud tables (tablenames starting with "efaCloud" except
     *            efaCloudUsers)
     * @param bool $init_efaCloud_users
     *            set true to initialize also the efaCloudUsers table
     */
    public function init_efa_data_base (bool $init_efa2, bool $init_efaCloud, bool $init_efaCloud_users)
    {
        $result = "";
        // check user priviledges
        if (strcasecmp("admin", $this->toolbox->users->session_user["Rolle"]) != 0)
            return i("vB1jX7|Error: User does not hav...");
        $appUserID = $this->toolbox->users->session_user["@id"];
        $admin_record = $this->socket->find_record("efaCloudUsers", "efaCloudUserID", $appUserID);
        if ($admin_record === false) {
            $session_user = $this->toolbox->users->session_user;
            $admin_record["Vorname"] = $session_user["Vorname"];
            $admin_record["Nachname"] = $session_user["Nachname"];
            $admin_record["EMail"] = $session_user["EMail"];
            $admin_record["efaCloudUserID"] = $session_user["efaCloudUserID"];
            $admin_record["efaAdminName"] = $session_user["efaAdminName"];
            $admin_record["Passwort_Hash"] = $session_user["Passwort_Hash"];
            $admin_record["Rolle"] = "admin";
        }
        // now reset all tables
        $db_layout_version = Efa_db_layout::$db_layout_version_target;
        include_once '../classes/efa_db_layout.php';
        $this->toolbox->logger->log(0, $appUserID, 
                date("Y-m-d H:i:s") . ": " . i("Yx56GO|Starting init_efa_data_b..."));
        
        $tables_of_layout = Efa_db_layout::db_layout($db_layout_version);
        foreach ($tables_of_layout as $tablename => $unused_definition) {
            $init_because_is_efa = ($init_efa2 && (strpos($tablename, "efa2") === 0));
            $init_because_is_efaCloud = ($init_efaCloud && (strpos($tablename, "efaCloud") === 0) &&
                     (strcasecmp($tablename, "efaCloudUsers") != 0));
            $init_because_is_efaCloudUsers = ($init_efaCloud_users &&
                     (strcasecmp($tablename, "efaCloudUsers") == 0));
            if ($init_because_is_efa || $init_because_is_efaCloud || $init_because_is_efaCloudUsers) {
                $log_message = i("2p7kEh|Dropping table °%1°... ", $tablename);
                $sql_cmd = "DROP TABLE `" . $tablename . "`;";
                $drop_success = $this->execute_and_log($appUserID, $sql_cmd, $log_message);
                $result .= $log_message . (($drop_success) ? i("vI1rIC|ok.") : i("VTLYoN|no such table.")) .
                         "<br>";
                // this will abort execution after first failure.
                $log_message = i("WRvThr|Creating empty new table...", $tablename);
                $sql_cmds = Efa_db_layout::build_sql_add_table_commands($db_layout_version, $tablename);
                $reset_success = true;
                foreach ($sql_cmds as $sql_cmd)
                    // this will abort execution after first failure.
                    $reset_success = $reset_success &&
                             $this->execute_and_log($appUserID, $sql_cmd, $log_message);
                $result .= $log_message .
                         (($reset_success) ? i("sQuH9Y|ok.") : i("uhcKky|aborted, see sys_db_audi...")) .
                         "<br>";
            }
        }
        // if also the efaCloud users table was dropped and rebuilt, insert now the cached user.
        if ($init_efaCloud_users) {
            $log_message = i("9vOwbr|Inserting admin °%1° rec...", $admin_record["efaCloudUserID"]) . ": ";
            $success = $this->socket->insert_into($appUserID, "efaCloudUsers", $admin_record);
            if (! is_numeric($success))
                $log_message = i("7pzDSf|Admin insertion failed. ...", $success) . "<br>";
            else
                $log_message = $log_message . i("id0bwJ|Successful.") . "<br>";
            $this->toolbox->logger->log(0, $appUserID, $log_message);
            $result .= $log_message;
        }
        $this->set_db_layout_version(Efa_db_layout::$db_layout_version_target);
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
     * @return boolean false is returned for 1. for $verify_only == true and a deviation from the layout or a
     *         previous verification happened less than 10 minutes ago, 2. for $verify_only == false and
     *         failure to correct the data base. Else true is returned.
     */
    public function update_database_layout (String $appUserID, int $use_layout_version, bool $verify_only)
    {
        // check user priviledges
        $admin_record = $this->is_admin_session_user(false);
        file_put_contents($this->db_audit_log, 
                date("Y-m-d H:i:s") . ": " . i("8hPspQ|Starting") . " update_database_layout(" .
                         $use_layout_version . ") [" . json_encode($verify_only) . ", " . $appUserID . "].\n");
        if (($admin_record === false) && ! $verify_only) {
            file_put_contents($this->db_audit_log, 
                    date("Y-m-d H:i:s") . ": " . i("a9d5U7|User is no admin and thu...") . "\n", FILE_APPEND);
            return false;
        }
        
        // now adjust all tables
        $correction_success = true;
        include_once "../classes/efa_db_layout.php";
        $table_names_existing = $this->socket->get_table_names();
        $lower_case_tablenames = true;
        foreach ($table_names_existing as $table_name_existing)
            if (strcmp(strtolower($table_name_existing), $table_name_existing) != 0)
                $case_sensitive_names = false;
        
        $tables_of_layout = Efa_db_layout::db_layout($use_layout_version);
        if (! $verify_only)
            $this->toolbox->logger->log(0, $appUserID, 
                    date("Y-m-d H:i:s") . ": " . i("mrY3PQ|Starting") . " update_database_layout(" .
                             $use_layout_version . ").");
        // add or adjust tables according to the expected layout
        foreach ($tables_of_layout as $tablename => $unused_definition) {
            if (in_array($tablename, $table_names_existing)) {
                if (! $this->update_table_layout($appUserID, $use_layout_version, $tablename, $verify_only))
                    return false;
            } else {
                if ($verify_only) {
                    file_put_contents($this->db_audit_log, 
                            date("Y-m-d H:i:s") . ": " . i("KprqM0|Verification failed. Tab...", $tablename) .
                                     "\n", FILE_APPEND);
                    return false;
                }
                $log_message = i("wpf4AI|Create table °%1° with a...", $tablename);
                $sql_cmds = Efa_db_layout::build_sql_add_table_commands($use_layout_version, $tablename);
                foreach ($sql_cmds as $sql_cmd)
                    // this will abort execution after first failure.
                    $correction_success = $correction_success &&
                             $this->execute_and_log($appUserID, $sql_cmd, $log_message);
                if ($correction_success === false)
                    return false;
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
                    file_put_contents($this->db_audit_log, 
                            date("Y-m-d H:i:s") . ": " . i("9g3u60|Verification failed. Tab...", $tablename) .
                                     "\n", FILE_APPEND);
                }
                $sql_cmd = "DROP TABLE `" . $tablename . "`";
                $log_message = i("TQrqJw|Drop table °%1°.", $tablename);
                $correction_success = $this->execute_and_log($appUserID, $sql_cmd, $log_message);
                if ($correction_success === false)
                    return false;
            }
        }
        
        // notify and register activity.
        if (! $verify_only) {
            $this->set_db_layout_version($use_layout_version);
            $this->toolbox->logger->log(0, $appUserID, 
                    date("Y-m-d H:i:s") . ": " . i("W6N63R|database_layout upgraded...", $use_layout_version));
        }
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
     * @return boolean false is returned for 1. for $verify_only == true and a deviation from the layout or a
     *         previous verification happened less than 10 minutes ago, 2. for $verify_only == false and
     *         failure to correct the data base. Else true is returned.
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
        file_put_contents($this->db_audit_log, 
                date("Y-m-d H:i:s") . ": " . i("YPmPF9|Starting") . " update_table_layout(" . $tablename .
                         ").\n", FILE_APPEND);
        file_put_contents($this->db_audit_log, 
                date("Y-m-d H:i:s") . ": " . i("aXHg3t|Indexes existing.") . " (" . $tablename .
                         json_encode($indexes_existing) . ").\n", FILE_APPEND);
        
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
                    $log_message = i("LjCmY9|Set Default for °%1°.°%2...", $tablename, $cname, $default);
                    $success = $this->execute_and_log($appUserID, $sql_cmd, $log_message);
                    if ($success === false)
                        return false;
                }
                // now change the column (change all, whatever mismatch was detected)
                if (! $verify_only) {
                    $activity_template = Efa_db_layout::$sql_change_column_command;
                    $sql_cmd = Efa_db_layout::build_sql_column_command($use_layout_version, $tablename, 
                            $cname, $activity_template);
                    $log_message = i("sNh3bq|Changed column °%1°.°%2°...", $tablename, $cname);
                    $success = $this->execute_and_log($appUserID, $sql_cmd, $log_message);
                    if ($success === false)
                        return false;
                } else {
                    // or check type and size, if just the verification shall happen
                    $def_parts = explode(";", $cdefinition);
                    if (strcasecmp($ctype, $dtype) != 0) {
                        file_put_contents($this->db_audit_log, 
                                date("Y-m-d H:i:s") . ": " . i("mwaK4O|Verification failed. Col...", 
                                        $tablename, $cname, $ctype, $dtype) . "\n", FILE_APPEND);
                        return false;
                    } else 
                        if ($csize != $dsize) {
                            file_put_contents($this->db_audit_log, 
                                    date("Y-m-d H:i:s") . ": " . i("3EWY5V|Verification failed. Col...", 
                                            $tablename, $cname, $csize, $dsize) . ".\n", FILE_APPEND);
                            return false;
                        }
                }
            } else {
                // column is NOT existing. Adjust default first
                if ($verify_only) {
                    file_put_contents($this->db_audit_log, 
                            date("Y-m-d H:i:s") . ": " .
                                     i("rxMN98|Verification failed. Col...", $tablename, $cname) . "\n", 
                                    FILE_APPEND);
                    return false;
                }
                $activity_template = Efa_db_layout::$sql_add_column_command;
                $log_message = i("xw01aj|Added column °%1°.°%2° w...", $tablename, $cname);
                $sql_cmd = Efa_db_layout::build_sql_column_command($use_layout_version, $tablename, $cname, 
                        $activity_template);
                $success = $this->execute_and_log($appUserID, $sql_cmd, $log_message);
                if ($success === false)
                    return false;
            }
            
            // add a unique quality, if not yet existing
            if ($dunique) {
                $indexes_expected[] = $cname;
                if (! in_array($cname, $indexes_existing)) {
                    $sql_cmd = "ALTER TABLE `" . $tablename . "` ADD UNIQUE(`" . $cname . "`); ";
                    $log_message = i("S1nQio|Added unique property to...", $tablename, $cname);
                    if (! $verify_only) {
                        $success = $this->execute_and_log($appUserID, $sql_cmd, $log_message);
                        if ($success === false)
                            return false;
                    } else {
                        file_put_contents($this->db_audit_log, 
                                date("Y-m-d H:i:s") . ": " . i("9mUNZn|Verification failed. Ind...", 
                                        $tablename, $cname) . "\n", FILE_APPEND);
                        return false;
                    }
                }
            }
            
            // add an autoincrement quality, if needed
            // ALTER TABLE `efaCloudPartners` CHANGE `ID` `ID` INT NOT NULL AUTO_INCREMENT;
            if ($dautoincrement) {
                if (! in_array($cname, $autoincrements_existing)) {
                    $sql_cmd = "ALTER TABLE `" . $tablename . "` CHANGE `" . $cname . "` `" . $cname .
                             "` INT UNSIGNED NOT NULL AUTO_INCREMENT";
                    $log_message = i("H5p5xA|Added auto increment pro...", $tablename, $cname);
                    if (! $verify_only) {
                        $success = $this->execute_and_log($appUserID, $sql_cmd, $log_message);
                        if ($success === false)
                            return false;
                    }
                }
            }
        }
        
        file_put_contents($this->db_audit_log, 
                date("Y-m-d H:i:s") . ": " . i("5OhRYr|Indexes expected.") . " (" . $tablename .
                         json_encode($indexes_expected) . ").\n", FILE_APPEND);
        
        // delete columns which are obsolete in the expected layout
        foreach ($column_names_existing as $cname_existing) {
            if (! array_key_exists($cname_existing, $columns_expected)) {
                if ($verify_only) {
                    file_put_contents($this->db_audit_log, 
                            date("Y-m-d H:i:s") . ": " . i("ulIoAv|Verification failed. Col...", $tablename, 
                                    $cname_existing) . "\n", FILE_APPEND);
                    return false;
                }
                // e.g. ALTER TABLE `efaCloudPartners` DROP `UseEcrm`;
                $sql_cmd = "ALTER TABLE `" . $tablename . "` DROP `" . $cname_existing . "`;";
                $log_message = i("TuOggl|Dropped obsolete column ...", $tablename, $cname_existing);
                $success = $this->execute_and_log($appUserID, $sql_cmd, $log_message);
                if ($success === false)
                    return false;
            }
        }
        
        // delete indices which are obsolete in the expected layout
        foreach ($indexes_existing as $iname_existing) {
            if (! in_array($iname_existing, $indexes_expected)) {
                if ($verify_only) {
                    file_put_contents($this->db_audit_log, 
                            date("Y-m-d H:i:s") . ": " .
                                     i("nPmhjO|Verification failed. Ind...", $tablename, $iname_existing) .
                                     "\n", FILE_APPEND);
                    return false;
                }
                // ALTER TABLE `efa2autoincrement` DROP INDEX `Sequence_7`;
                $sql_cmd = "ALTER TABLE `" . $tablename . "` DROP INDEX `" . $iname_existing . "`;";
                $log_message = i("zQY162|Dropped obsolete index  ...", $tablename, $iname_existing);
                $success = $this->execute_and_log($appUserID, $sql_cmd, $log_message);
                if ($success === false)
                    return false;
            }
        }
        
        // if the $verify_only flag is set and this point reached, all is fine
        if ($verify_only) {
            file_put_contents($this->db_audit_log, 
                    date("Y-m-d H:i:s") . ": " . i("gLdeJY|Verification successful ...", $tablename) . "\n", 
                    FILE_APPEND);
        } else
            file_put_contents($this->db_audit_log, 
                    date("Y-m-d H:i:s") . ": " . i("6QCftb|Completed update_table_l...", $tablename) . "\n", 
                    FILE_APPEND);
        return true;
    }

    /* --------------------------------------------------------------------------------------- */
    /* ---------------------------- DATA BASES STATUS SUMMARY -------------------------------- */
    /* --------------------------------------------------------------------------------------- */
    
    /**
     * Create an html readable summary of the application status to send it per mail to admins.
     */
    public function create_app_status_summary (Tfyh_toolbox $toolbox, Tfyh_socket $socket)
    {
        $html = "";
        
        // Check logbooks
        $total_record_count = 0;
        $html .= "<h4>" . i("4aLEcN|Logbooks") . "</h4>\n";
        $html .= "<table><tr><th>" . i("vb5sne|Logbook") . "</th><th>" . i("8UxLNR|Number of trips") .
                 "</th></tr>\n";
        $logbooks = $socket->count_values("efa2logbook", "Logbookname");
        foreach ($logbooks as $logbook_name => $entry_count) {
            $html .= "<tr><td>" . $logbook_name . "</td><td>" . $entry_count . "</td></tr>\n";
            $total_record_count += $entry_count;
        }
        $html .= "<tr><td>" . i("KhEE7l|Total") . "</td><td>" . $total_record_count . "</td></tr></table>\n";
        
        // Check clubwork books
        $total_record_count = 0;
        $html .= "<h4>" . i("rXpZ9D|Club workbooks") . "</h4>\n";
        $html .= "<table><tr><th>" . i("r8hjy3|Club workbook") . "</th><th>" . i("W14LOF|Number of club work") .
                 "</th></tr>\n";
        $clubworkbooks = $socket->count_values("efa2clubwork", "ClubworkbookName");
        foreach ($clubworkbooks as $clubworkbook_name => $entry_count) {
            $html .= "<tr><td>" . $clubworkbook_name . "</td><td>" . $entry_count . "</td></tr>\n";
            $total_record_count += $entry_count;
        }
        $html .= "<tr><td>" . i("EsUbNY|Total") . "</td><td>" . $total_record_count . "</td></tr></table>\n";
        
        // check table sizes
        $html .= "<h4>" . i("bxlpav|Tables and records") . "</h4>\n";
        $html .= "<table><tr><th>" . i("TjrkjA|Table") . "</th><th>" . i("NijhLI|Number of records") .
                 "</th></tr>\n";
        $table_names = $socket->get_table_names();
        $total_record_count = 0;
        foreach ($table_names as $tn) {
            $record_count = $socket->count_records($tn);
            $html .= "<tr><td>" . $tn . "</td><td>" . $record_count . "</td></tr>\n";
            $total_record_count += $record_count;
        }
        $html .= "<tr><td>" . i("F6snKT|Total") . "</td><td>" . $total_record_count . "</td></tr></table>\n";
        
        // Check users and access rights
        $html .= $this->toolbox->users->get_all_accesses($socket, false);
        
        // Check accessses logged.
        $days_to_log = 14;
        $html .= "<h4>" . i("W9GB51|accesses last %1 days", $days_to_log) . "</h4>\n";
        include_once '../classes/tfyh_statistics.php';
        $tfyh_statistics = new Tfyh_statistics();
        file_put_contents("../log/efacloud_server_statistics.csv", 
                $tfyh_statistics->pivot_timestamps(86400, $days_to_log));
        $html .= "<table><tr><th>" . i("3fXeDv|clientID") . "</th><th>" . i("UnpGlv|clientName") . "</th><th>" .
                 i("hYuVK4|Number of accesses") . "</th></tr>\n";
        $timestamps_count_all = 0;
        foreach ($tfyh_statistics->timestamps_count as $clientID => $timestamps_count) {
            $person_record = $socket->find_record("efaCloudUsers", "efaCloudUserID", $clientID);
            $full_name = ($person_record === false) ? i("IzCLZv|unknown ID") : ($person_record["Vorname"] . " " .
                     $person_record["Nachname"]);
            $html .= "<tr><td>" . $clientID . "</td><td>" . $full_name . "</td><td>" . $timestamps_count .
                     "</td></tr>\n";
            $timestamps_count_all += $timestamps_count;
        }
        $html .= "<tr><td>" . i("WLvJO7|Total") . "</td><td></td><td>" . $timestamps_count_all .
                 "</td></tr></table>\n";
        
        // Check backup
        $html .= "<h4>" . i("V4T3Wv|Backups") . "</h4>\n";
        $backup_dir = "../log/backup";
        $backup_files = scandir($backup_dir);
        $backup_files_size = 0;
        $backup_files_count = 0;
        $backup_files_youngest = 0;
        if ($backup_files !== false)
            foreach ($backup_files as $backup_file) {
                if (strcasecmp(substr($backup_file, 0, 1), ".") != 0) {
                    $backup_files_size += filesize($backup_dir . "/" . $backup_file);
                    $lastmodified = filectime($backup_dir . "/" . $backup_file);
                    if ($lastmodified > $backup_files_youngest)
                        $backup_files_youngest = $lastmodified;
                    $backup_files_count ++;
                }
            }
        $html .= "<p>" . i("3Fniwt|%1 Backup-Archive mit in...", $backup_files_count, 
                (intval($backup_files_size / 1024 / 102) / 10)) . "\n";
        $html .= i("Nza13s|Most recent backup of %1...", date("Y-m-d H:i:s", $backup_files_youngest)) .
                 "</p>\n";
        
        return $html;
    }

    /* --------------------------------------------------------------------------------------- */
    /* --------------- EFA CLOUD CLEAR REMAINS OF DELETED RECORDS ---------------------------- */
    /* --------------------------------------------------------------------------------------- */
    
    /**
     * Purge all trashed records, if too old.
     */
    public function purge_trashed ()
    {
        $cfg = $this->toolbox->config->get_cfg();
        // Default is maximum count of days, ~68 years.
        $purgeDeletedAgeDays = (isset($cfg["PurgeDeletedAgeDays"]) && (strlen($cfg["PurgeDeletedAgeDays"]) > 0)) ? intval(
                $cfg["PurgeDeletedAgeDays"]) : self::$forever_days;
        $tablename = "efaCloudTrash";
        if ($purgeDeletedAgeDays > 0) {
            $sql_cmd = "DELETE FROM `$tablename` WHERE (DATEDIFF(CURRENT_DATE,`TrashedAt`) > " .
                     $purgeDeletedAgeDays . ")";
            // === test code
            // file_put_contents("../log/tmp", $tablename . ": Would execute purge: " . $sql_cmd . "\n",
            // FILE_APPEND);
            // === test code
            $this->socket->query($sql_cmd, $this);
            $affected_rows = $this->socket->affected_rows();
            $trashed_cnt = $this->socket->count_records($tablename);
        }
        if (($affected_rows > 0) || ($trashed_cnt > 0))
            $info = i("w7VUs5|purged, left records:") . " " . $affected_rows . ", " . $trashed_cnt;
        else
            $info = i("0471BS|no trashed records to pu...");
        return $info;
    }

    /**
     * Purge all deleted records of all tables, if too old.
     */
    public function purge_outdated_deleted ()
    {
        $cfg = $this->toolbox->config->get_cfg();
        // Default is maximum count of days, ~68 years.
        $purgeDeletedAgeDays = (isset($cfg["PurgeDeletedAgeDays"]) && (strlen($cfg["PurgeDeletedAgeDays"]) > 0)) ? intval(
                $cfg["PurgeDeletedAgeDays"]) : self::$forever_days;
        $info = "";
        if ($purgeDeletedAgeDays > 0)
            foreach (self::$tables_to_purge_deleted as $tablename) {
                $deleted_cnt = $this->socket->count_records($tablename, 
                        ["LastModification" => "delete"
                        ], "=");
                $sql_cmd = "DELETE FROM `" . $tablename .
                         "` WHERE (`LastModification` = 'delete') AND (`LastModified` < ((UNIX_TIMESTAMP() - " .
                         $purgeDeletedAgeDays . " * 86400)) * 1000)";
                // === test code
                // file_put_contents("../log/tmp", $tablename . ": Would execute purge: " . $sql_cmd . "\n",
                // FILE_APPEND);
                // === test code
                $this->socket->query($sql_cmd, $this);
                $affected_rows = $this->socket->affected_rows();
                if (($affected_rows > 0) || ($deleted_cnt > 0))
                    $info .= $tablename . ": " . $affected_rows . "/" . $deleted_cnt . ", ";
            }
        if (strlen($info) == 0)
            $info = i("B826Ym|no deleted records were ...");
        else
            $info = mb_substr($info, 0, mb_strlen($info) - 2);
        return $info;
    }

    /**
     * Purge all corrupt data. No deletion, no deletion notification. Purges only those, which are empty.
     * Compare Efa_audit::data_integrity_audit().
     */
    public function purge_corrupt ()
    {
        include_once '../classes/efa_record.php';
        $efa_record = new Efa_record($this->toolbox, $this->socket);
        $purge_count = 0;
        $list_definitions = new Tfyh_list("../config/lists/efaAuditCorruptData", 0, "", $this->socket, 
                $this->toolbox);
        $list_definitions = $list_definitions->get_all_list_definitions();
        $purged_records_cnt_all = 0;
        $checked_records_cnt_all = 0;
        for ($list_index = 0; $list_index < count($list_definitions); $list_index ++) {
            $purged_records_cnt = 0;
            $checked_records_cnt = 0;
            $list_id = intval($list_definitions[$list_index]["id"]);
            $lists["corrupt"][$list_id] = new Tfyh_list("../config/lists/efaAuditCorruptData", $list_id, "", 
                    $this->socket, $this->toolbox);
            $table_name = $lists["corrupt"][$list_id]->get_table_name();
            $ecrid_index = $lists["corrupt"][$list_id]->get_field_index("ecrid");
            $lastModification_index = $lists["corrupt"][$list_id]->get_field_index("LastModification");
            $lastModified_index = $lists["corrupt"][$list_id]->get_field_index("LastModified");
            $changeCount_index = $lists["corrupt"][$list_id]->get_field_index("ChangeCount");
            $to_be_purged = false;
            foreach ($lists["corrupt"][$list_id]->get_rows() as $row) {
                $checked_records_cnt ++;
                // missing last modification
                $to_be_purged = $to_be_purged || ! isset($row[$lastModification_index]) ||
                         is_null($row[$lastModification_index]) || (strlen($row[$lastModification_index]) == 0);
                // missing or invalid last modified
                $to_be_purged = $to_be_purged || ! isset($row[$lastModified_index]) ||
                         is_null($row[$lastModified_index]) || (strlen($row[$lastModified_index]) < 2);
                // missing or invalid change count
                $to_be_purged = $to_be_purged || ! isset($row[$changeCount_index]) ||
                         is_null($row[$changeCount_index]) || (strlen($row[$changeCount_index]) == 0);
                // do not purge records without ecrid. Deletion may hit the wrong one.
                $to_be_purged = $to_be_purged && isset($row[$ecrid_index]) && (strlen($row[$ecrid_index]) > 0);
                if ($to_be_purged) {
                    // get full record
                    $full_record = $this->socket->find_record($table_name, "ecrid", $row[$ecrid_index]);
                    // do not purge records without conten automatically.
                    if (Efa_record::is_content_empty($table_name, $full_record)) {
                        $delete_result = $this->socket->delete_record_matched(
                                $this->toolbox->users->session_user["@id"], $table_name, 
                                ["ecrid" => $row[$ecrid_index]
                                ]);
                        if (strlen($delete_result) == 0) {
                            $purged_records_cnt ++;
                        }
                    }
                }
            }
            $purged_records_cnt_all += $purged_records_cnt;
            $checked_records_cnt_all += $checked_records_cnt;
        }
        return "$purged_records_cnt_all/$checked_records_cnt_all";
    }

    /**
     * Add a last modification field for records missing this entry. Check the last entry in the history to
     * identify, what was the value prior to deletion of this entry. And, if there is no history, check
     * whether the record is more or less empty, than use delete. Iterate through all efa tables, but do not
     * change more than 50 per records table.
     */
    public function add_last_modifications ()
    {
        $added_lms = 0;
        include_once "../classes/efa_record.php";
        $efa_record = new Efa_record($this->toolbox, $this->socket);
        foreach (Efa_tables::$server_gen_fields as $tablename => $server_gen_fields) {
            if (in_array("LastModification", $server_gen_fields)) {
                $missing_last_modified = $this->socket->find_records_matched($tablename, 
                        ["LastModification" => ""
                        ], 50);
                // LastModification may also be null
                if ($missing_last_modified === false)
                    $missing_last_modified = $this->socket->find_records_sorted_matched($tablename, 
                            ["LastModification" => ""
                            ], 50, "NULL", "", true);
                if ($missing_last_modified !== false) {
                    foreach ($missing_last_modified as $record) {
                        $ecrid = $record["ecrid"];
                        $last_modification = "";
                        if (isset($record["ecrhis"]) && (strlen($record["ecrhis"]) > 0)) {
                            $history_versions = $this->socket->get_history_array($record["ecrhis"]);
                            foreach ($history_versions as $history_version)
                                if (isset($history_version["record_version"]) &&
                                         isset($history_version["record_version"]["LastModification"]) &&
                                         (strlen($history_version["record_version"]["LastModification"]) > 0)) {
                                    $last_modification = $history_version["record_version"]["LastModification"];
                                }
                        } elseif (Efa_record::is_content_empty($tablename, $record)) {
                            $last_modification = "delete";
                        }
                        $is_delete = (strcasecmp($last_modification, "delete") == 0);
                        if ((strlen($last_modification) > 0) && isset($record["ecrid"])) {
                            $record = Efa_tables::register_modification($record, time(), 
                                    $record["ChangeCount"], $last_modification);
                            if (isset($record["ecrhis"]) && $is_delete)
                                $record["ecrhis"] = "REMOVE!";
                            $update_result = $this->socket->update_record_matched(
                                    $this->toolbox->users->session_user["@id"], $tablename, 
                                    ["ecrid" => $record["ecrid"]
                                    ], $record);
                            $added_lms ++;
                        }
                    }
                }
            }
        }
        return $added_lms;
    }
}
