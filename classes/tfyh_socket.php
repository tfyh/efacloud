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
interface Tfyh_socket_trigger
{

    /**
     * This function is called before each data base write transaction, to all registered triggers for write
     * transaction only. It must to return a result as String, being empty for "ok" and containing all error
     * messages for user display.
     * 
     * @param int $user_id
     *            the user of the transaction. Will be added as owner field.
     * @param int $mode
     *            the mode of the transaction, being either of 1 for insert, 2 for update or 3 for delete.
     * @param String $tablename
     *            The name of the affected table
     * @param array $record
     *            the record used for the transaction.
     * @return mixed It must return either a) true, if the trigger did neither modify the record, nor fail b)
     *         a String with an error message on failure c) the modified $record, if adjustments were
     *         executed.
     */
    public function pre_write_transaction (int $user_id, int $mode, String $tablename, array $record);
}

interface Tfyh_socket_write_listener
{

    /**
     * This function is called after each data base write transaction, to all listeners. You may use it for
     * data replication purposes. No value must be returned.
     * 
     * @param int $mode
     *            the mode of the transaction, being either of 1 for insert, 2 for update or 3 for delete.
     * @param String $tablename
     *            The name of the affected table
     * @param array $record
     *            the record used for the transaction.
     */
    public function post_write_transaction (int $mode, String $tablename, array $record);
}

interface Tfyh_socket_read_listener
{

    /**
     * This function is called after each data read transaction, to all listeners. You may use it for data
     * access permissions filtering purposes. Read listeners post_write_transaction will not be called, if no
     * record, i.e. false or an error message is returned by the find call.
     * 
     * @param String $tablename
     *            The name of the affected table
     * @param array $records
     *            the records which were found by the find call.
     * @return mixed either a) true, if the listener did not modify any record. b) the set of records, if
     *         filters on records or fields applied and at least one record is left. c) false, if after
     *         application of filters no record is left.
     */
    public function post_read_transaction (String $tablename, array $records);
}

/**
 * class file for the Tfyh_socket class A utility class to connect to the data base. provides simple get and
 * put functions to read and write values.
 */
class Tfyh_socket
{

    /**
     * data base connection object
     */
    public $mysqli;

    /**
     * data base name
     */
    private $db_name;

    /**
     * The common toolbox used.
     */
    private $toolbox;

    /**
     * an array of all socket read listeners
     */
    private $read_listeners = [];

    /**
     * an array of all socket write listeners
     */
    private $write_listeners = [];

    /**
     * an array of all socket pre-modification checkers
     */
    private $triggers = [];

    /**
     * debug file name for sql-queries
     */
    public $sql_debug_file = "../log/debug_sql.log";

    /**
     * debug file name for sql-queries
     */
    private $sql_error_file = "../log/sys_sql_errors.log";

    /**
     * debug file name for sql-queries
     */
    private $debug_on;

    /**
     * $last_sql_executed reflects the last sql-command to executed in execute_and_log.
     */
    public $last_sql_executed = "";

    /**
     * The column names of the change log table, default. Set config.changelog_columns to use different names
     * (the values are fix).
     */
    private $change_log_columns = "`Author`, `Time`, `ChangedTable`, `ChangedID`, `Modification`";

    /**
     * Use the current microtime as float instead of the TIMESTAMP for the changelog.
     */
    private $change_log_timestamp_timef = false;

    /**
     * Construct the socket. This initializes the data base connection. $cfg must contain the appropriate
     * values for: $cfg["db_host"], $cfg["db_accounts"], $cfg["db_name"] to get it going.
     * 
     * @param array $toolbox
     *            the basic utilities of the application.
     */
    function __construct (Tfyh_toolbox $toolbox)
    {
        $cfg_db = $toolbox->config->get_cfg_db();
        $this->db_name = $cfg_db["db_name"];
        $this->mysqli = null;
        $this->toolbox = $toolbox;
        $this->debug_on = $toolbox->config->debug_level > 0;
        if (isset($toolbox->config->settings_tfyh["config"]["changelog_columns"]) &&
                 (strlen(isset($toolbox->config->settings_tfyh["config"]["changelog_columns"])) > 0))
            $this->change_log_columns = $toolbox->config->settings_tfyh["config"]["changelog_columns"];
        if (! $toolbox->config->mode_classic)
            $this->change_log_timestamp_timef = true;
    }

    /**
     * A wrapper to manage exceptions. No i18n for texts, this is low level code, English only.
     * 
     * @param String $sql_cmd            
     */
    private function mysqli_query (String $sql_cmd, String $caller_text = "tfyh_socket->mysqli_query")
    {
        $this->last_sql_executed = $sql_cmd;
        $log_opener = date("Y-m-d H:i:s") . ": [" . $caller_text . "] " . $sql_cmd . " => ";
        try {
            if ($this->debug_on)
                file_put_contents($this->sql_debug_file, $log_opener, FILE_APPEND);
            $result = $this->mysqli->query($sql_cmd);
            if ($result === false) {
                // nio i18n, because the error description will anyway be English
                $log_error = "[FAILED] " . $this->mysqli->error . json_encode($result) . "\n";
                file_put_contents($this->sql_error_file, $log_opener . $log_error, FILE_APPEND);
                if ($this->debug_on)
                    file_put_contents($this->sql_debug_file, $log_error, FILE_APPEND);
            } elseif ($this->debug_on) {
                file_put_contents($this->sql_debug_file, "[OK] " . json_encode($result) . "\n", FILE_APPEND);
            }
        } catch (Exception $e) {
            $result = false;
            $log_error = "[EXCEPTION] " . $this->mysqli->error . "\n";
            file_put_contents($this->sql_error_file, $log_opener . $log_error, FILE_APPEND);
            if ($this->debug_on)
                file_put_contents($this->sql_debug_file, $log_error, FILE_APPEND);
        }
        return $result;
    }

    /**
     * ******************** CONNECTION FUNCTONS, TRIGGERS AND RAW QUERY **************************
     */
    
    /**
     * Test the connection. Will open the connection, if not yet done and try to query "SELECT 1" to test the
     * data base.
     * 
     * @return true, if test statement succeeded; an error message, if not.
     */
    public function open_socket ()
    {
        // do nothing, if connection is open.
        if (! is_null($this->mysqli) && $this->mysqli->ping())
            return true;
        $cfg_db = $this->toolbox->config->get_cfg_db();
        // this will only connect with the correct settings in the settings_db file.
        try {
            $this->mysqli = new mysqli($cfg_db["db_host"], $cfg_db["db_user"], $cfg_db["db_up"], 
                    $this->db_name);
        } catch (exception $e) {
            return i("R51PFL|Data base connection fai...") . " " . $e->getMessage();
        }
        if ($this->mysqli->connect_error)
            return i("vCGSbJ|Data base connection err...") . " " . $this->mysqli->connect_error . ".";
        $this->mysqli_query("SET NAMES 'UTF8'", "open_socket");
        $ret = $this->mysqli_query("SELECT 1", "open_socket");
        // cf. https://stackoverflow.com/questions/3668506/efficient-sql-test-query-or-
        // validation-query-that-will-work-across-all-or-most
        return ($ret !== false) ? true : i("s2Mkwj|Data base connection suc...");
    }

    /**
     * Add a post-read-transaction listener to the socket. If a trigger of that name already exists, it is
     * replaced.
     * 
     * @param String $name
     *            the listener name to reference it for removal or replacement.
     * @param Tfyh_socket_read_listener $listener
     *            the post-modification listener to add
     */
    public function add_read_listener (String $name, Tfyh_socket_read_listener $listener)
    {
        $this->read_listeners[$name] = $listener;
    }

    /**
     * Add a post-write-transaction listener to the socket. If a trigger of that name already exists, it is
     * replaced.
     * 
     * @param String $name
     *            the trigger name to reference it for removal or replacement.
     * @param Tfyh_socket_write_listener $listener
     *            the post-modification listener to add
     */
    public function add_write_listener (String $name, Tfyh_socket_write_listener $listener)
    {
        $this->write_listeners[$name] = $listener;
    }

    /**
     * Remove a post-write/post-read-transaction listeners from the socket. If no listener of that name
     * exists, nothing happens. If no name is given, all listeners are removed.
     * 
     * @param String $name
     *            the listener name to remove. Set null to remove all read and write listeners at once.
     */
    public function remove_listeners (String $name = null)
    {
        if (is_null($name)) {
            $this->read_listeners = [];
            $this->write_listeners = [];
        } else {
            if (isset($this->read_listeners[$name]))
                unset($this->read_listeners[$name]);
            if (isset($this->write_listeners[$name]))
                unset($this->write_listeners[$name]);
        }
    }

    /**
     * Add a pre-write-transaction trigger to the socket.
     * 
     * @param Tfyh_socket_trigger $trigger
     *            pre-transaction trigger to add.
     */
    public function add_trigger (String $name, Tfyh_socket_trigger $trigger)
    {
        $this->triggers[$name] = $trigger;
    }

    /**
     * Remove a pre-write-transaction triggers from the socket. If no trigger of that name exists, nothing
     * happens. If no name is given, all triggers are removed.
     * 
     * @param String $name
     *            the trigger name to remove. Set null to remove all.
     */
    public function remove_triggers (String $name = null)
    {
        if (is_null($name))
            $this->triggers = [];
        else 
            if (isset($this->triggers[$name]))
                unset($this->triggers[$name]);
    }

    /**
     * simple wrapper on mysqli->close().
     */
    public function close ()
    {
        if (($this->mysqli != null) && ($this->mysqli->ping()))
            $this->mysqli->close();
        $this->mysqli = null;
    }

    /**
     * Get the number of affected rows for the last sql command executed. See mysqli::$affected_rows for
     * return value meaning.
     * 
     * @return int|mixed mysqli->affected_rows
     */
    public function affected_rows ()
    {
        return $this->mysqli->affected_rows;
    }

    /**
     * DO NOT USE - THIS WILL NOT DO ANY SECURITY CHECKS - FOR TFYH FRAMEWORK USE ONLY - If the caller class
     * name does not start with "Tfyh" or equals "Efa_tools" the function will return without doing anything.
     * 
     * @param String $sql_cmd            
     * @return mixed msqli-query result or false in case of data base connection failure.
     */
    public function query (String $sql_cmd, $caller)
    {
        if (! is_object($caller))
            return false;
        if ((strpos(substr(get_class($caller), 0, 4), "Tfyh") !== 0) &&
                 (strcmp(get_class($caller), "Efa_tools") != 0) &&
                 (strcmp(get_class($caller), "Bulk_transaction") != 0))
            return false;
        return $this->mysqli_query($sql_cmd, "custom query");
    }

    /**
     *
     * @return the last mysqli interface error available.
     */
    public function get_last_mysqli_error ()
    {
        if (! is_null($this->mysqli->error) && (strlen($this->mysqli->error) > 0))
            return $this->mysqli->error;
        else
            return i("9Qyhtr|no last mysqli error ava...");
    }

    /**
     * ********************** STANDARD QUERY EXECUTION AND CHANGE LOGGING **************************
     */
    
    /**
     * Delete all entries from chage log which are older than $days_to_keep * 24*3600 seconds. Tod limits the
     * log also to $records_to_keep records. Issues no warnings and no errors.
     * 
     * @param int $days_to_keep
     *            time limit for deletion.
     * @param int $records_to_keep
     *            size limit for deletion. (Default: 2000)
     */
    public function cleanse_change_log (int $days_to_keep, int $records_to_keep = 2000)
    {
        // delete those which are older than $days_to_keep
        $now = time() - ($days_to_keep * 24 * 3600);
        $eldest_change = date("Y-m-d H:i:s", $now);
        $sql_cmd = "DELETE FROM `" . $this->toolbox->config->changelog_name . "` WHERE `Time`<'" .
                 $eldest_change . "'";
        $this->mysqli_query($sql_cmd, "cleanse_change_log");
        // now delete those which are just an overflow, e. g. by data base loading
        $sql_cmd = "SELECT `Time` FROM `" . $this->toolbox->config->changelog_name .
                 "` WHERE 1 ORDER BY `Time` DESC LIMIT " . ($records_to_keep + 2);
        $res = $this->mysqli_query($sql_cmd);
        $rows = 0;
        if (($res !== false) && intval($res->num_rows) > 0) {
            $row = $res->fetch_row();
            $max_change = $eldest_change;
            while ($row) {
                $max_change = $row[0];
                $rows ++;
                $row = $res->fetch_row();
            }
        }
        if ($rows > $records_to_keep) {
            $sql_cmd = "DELETE FROM `" . $this->toolbox->config->changelog_name . "` WHERE `Time`<'" .
                     $max_change . "'";
            $this->mysqli_query($sql_cmd);
        }
    }

    /**
     * Get all entries from the change log, html formatted. This will not cleanse the log.
     * 
     * @param String $id
     *            the field name for the chage log sequence ID, default is "ID" (upper case)
     * @return string the change log for html display, last entries first.
     */
    public function get_change_log (String $id = "ID")
    {
        $sql_cmd = "SELECT " . $this->change_log_columns . " FROM `" . $this->toolbox->config->changelog_name .
                 "` WHERE 1 ORDER BY `ID` DESC LIMIT 200";
        $res = $this->mysqli_query($sql_cmd, "get_change_log");
        if ($res === false)
            return "<h3>" . i("84qqTb|Changes") . "</h3><br>" . i("edML3Q|Error executing database...");
        elseif (intval($res->num_rows) > 0)
            $row = $res->fetch_row();
        else
            return i("SMOZBB|No changes logged.");
        $ret = "";
        while ($row) {
            if ($this->change_log_timestamp_timef) {
                $timestamp_object = Tfyh_toolbox::datetimef(floatval($row[1]));
                $row[1] = Tfyh_data::format($timestamp_object, "datetime", 
                        $this->toolbox->config->language_code);
            }
            $ret .= "<p><b>" . i("WP2gSG|Author:") . "</b> " . $row[0] . "<br><b>" . i("R9sv8M|Time:") .
                     "</b> " . $row[1] . "<br><b>" . i("k55LAo|Table:") . "</b> " . $row[2] . "<br><b>" .
                     i("gjYPBf|Changed ID:") . "</b> " . $row[3] . "<br><b>" . i("B9EOmx|Description:") .
                     "</b> " . $row[4] . "<br></p>\n";
            $row = $res->fetch_row();
        }
        return "<h3>" . i("E6z7DI|Changes") . "</h3><br>\n" . $ret;
    }

    /**
     * Execute an SQL command an log the result.
     * 
     * @param String $appUserID
     *            the ID of the application user of the user who performs the statement. For change logging.
     * @param String $table_name
     *            the name of the table to be used.
     * @param String $sql_cmd
     *            Command to be executed. Values within command must be UTF-8 encoded.
     * @param String $changed_id
     *            ID of changed entry. In case of inert statement, set to "" to use autoincremented id of
     *            inserted
     * @param String $change_entry
     *            Change text to be logged. Values within change text must be UTF-8 encoded, single quotes
     *            must be escaped.
     * @param bool $return_insert_id
     *            set true to try to return the insert id upon success. Use only when called with INSERT INTO
     *            statement.
     * @param bool $caller_text
     *            indicate the calling function as String.
     * @return mixed an error statement in case of failure, the numeric ID of the inserted record in case of
     *         insert-to success, else "".
     */
    private function execute_and_log (String $appUserID, String $table_name, String $sql_cmd, 
            String $changed_id, String $change_entry, bool $return_insert_id, String $caller_text)
    {
        // debug helper
        if ($this->debug_on) {
            file_put_contents($this->sql_debug_file, 
                    date("Y-m-d H:i:s") . ":  [tfyh_socket->execute_and_log] " . $sql_cmd . " => ", 
                    FILE_APPEND);
        }
        // execute sql command. Connection must have been opened before.
        // no i18n here, error messages are anyway in English
        $ret = "";
        $res = $this->mysqli_query($sql_cmd, $caller_text);
        if ($res === false) {
            if ($this->debug_on)
                file_put_contents($this->sql_debug_file, "failed: " . $this->mysqli->error . "\n", 
                        FILE_APPEND);
            $ret .= i("4YsmYP|Data base statement °%1 ...", htmlspecialchars(mb_substr($sql_cmd, 0, 5000)), 
                    $this->mysqli->error);
        } else {
            if ($this->debug_on)
                file_put_contents($this->sql_debug_file, i("vsbqU2|successful.") . "\n", FILE_APPEND);
            if ($return_insert_id == true)
                $ret = $this->mysqli->insert_id;
            else
                $ret = "";
            if (strlen($changed_id) == 0)
                $changed_id = $this->mysqli->insert_id;
            // write change log entry
            $timestamp = ($this->change_log_timestamp_timef) ? "'" . strval(Tfyh_toolbox::timef()) . "'" : "CURRENT_TIMESTAMP";
            $sql_cmd = "INSERT INTO `" . $this->toolbox->config->changelog_name . "` (" .
                     $this->change_log_columns . ") VALUES ('" . $appUserID . "', " . $timestamp . ", '" .
                     $table_name . "', '" . $changed_id . "', '" . str_replace("'", "\'", str_replace("\\", "\\\\", $change_entry)) .
                     "');";
            $tmpr = $this->mysqli_query($sql_cmd, "[change-log entry]");
        }
        
        return $ret;
    }

    /**
     * **************** STANDARD DB WRITE QUERIES: INSERT; UPDATE; DELETE *********************
     */
    
    /**
     * Remove user right fields from record, if the user is no user admin
     * 
     * @param int $appUserID
     *            the ID of the application user of the user who performs the statement. For change logging.
     * @param String $table_name
     *            the name of the table to be used.
     * @param array $record
     *            a named array with key = column name and value = values to be inserted. Values must be PHP
     *            native encoded Strings. Enclosed quotes "'" will be appropriately escaped for the SQL
     *            command.
     * @return array the same record, but user accss rights fields unset for a user who has not the user admin
     *         role.
     */
    private function protect_user_rights (int $appUserID, String $table_name, array $record)
    {
        if (strcasecmp($table_name, $this->toolbox->users->user_table_name) != 0)
            // this is no user data table: ok.
            return $record;
        $users_cnt = $this->count_records($this->toolbox->users->user_table_name);
        if ($users_cnt == 0)
            // the very first user must get the priviledge to be inserted anyway, with user admin
            // rights.
            return $record;
        
        $user = $this->find_record($table_name, $this->toolbox->users->user_id_field_name, $appUserID);
        
        if (strcasecmp($user["Rolle"], $this->toolbox->users->useradmin_role) == 0)
            // user has user administration priviledge: ok.
            return $record;
        
        // allow insertion of anonymous users
        if (($user === false) && (strcmp($this->toolbox->users->self_registered_role, "forbidden") != 0) &&
                 ! isset($record["ID"]) && (! isset($record["Rolle"]) ||
                 (strcasecmp($record["Rolle"], $this->toolbox->users->anonymous_role) == 0)) &&
                 (intval($record["Workflows"]) == 0) && (intval($record["Concessions"]) == 0)) {
            // if the $record["ID"] is not set, this is a registration. Allow it for the anonymous role,
            // except it is explicitly forbidden.
            $record["Rolle"] = $this->toolbox->users->anonymous_role;
            return $record;
        }
        
        if (isset($record["ID"]) && (intval($record["ID"]) != intval($user["ID"]))) {
            // role is no user admin, but workflows may allow to change other users data fields
            $is_allowed_workflow = false;
            foreach ($this->toolbox->users->useradmin_workflows as $allowed_workflow => $allowed_fields) {
                if (! $is_allowed_workflow && ((intval($user["Workflows"]) & intval($allowed_workflow)) > 0)) {
                    $is_allowed_workflow = true;
                    foreach ($record as $key => $value) {
                        if ((strcmp($key, "ID") != 0) && ! in_array($key, $allowed_fields))
                            $is_allowed_workflow = false;
                    }
                }
            }
            if (! $is_allowed_workflow)
                // this is a different users data and the user is no useradmin: forbidden
                return i("psSYI1|User tried to modify oth...");
        }
        
        // check change of role, workflows, concessions, userID or account name
        if ($user === false) {
            // refuse insertion of a user without a role definition or with a role definition other than the
            // 'self_registered_role'
            if (! isset($record["Rolle"]) ||
                     (strcasecmp($record["Rolle"], $this->toolbox->users->self_registered_role) != 0))
                return i("lDSTHw|Someone tried to create ...", $this->toolbox->users->self_registered_role);
        } else {
            if ((isset($user["Rolle"]) && isset($record["Rolle"]) &&
                     (strcasecmp($record["Rolle"], $user["Rolle"]) != 0)) || (! isset($user["Rolle"]) &&
                     (strcasecmp($record["Rolle"], $this->toolbox->users->self_registered_role) != 0)))
                return i("gI7hvr|User tried to modify own...");
            if (isset($record["Workflows"]) && (intval($record["Workflows"]) != intval($user["Workflows"])))
                return i("4RIp1F|User tried to modify own...");
            if (isset($record["Concessions"]) &&
                     (intval($record["Concessions"]) != intval($user["Concessions"])))
                return i("T5N6rh|User tried to modify own...");
            if (isset($record[$this->toolbox->users->user_id_field_name]) && (intval(
                    $record[$this->toolbox->users->user_id_field_name]) != intval(
                    $user[$this->toolbox->users->user_id_field_name])))
                return i("A2Zw3z|User tried to modify own...", 
                        $user[$this->toolbox->users->user_id_field_name], 
                        $record[$this->toolbox->users->user_id_field_name]);
            if (isset($record[$this->toolbox->users->user_account_field_name]) && (strcasecmp(
                    $record[$this->toolbox->users->user_account_field_name], 
                    $user[$this->toolbox->users->user_account_field_name]) != 0))
                return i("m6BPH3|User tried to modify own...", 
                        $user[$this->toolbox->users->user_account_field_name], 
                        $record[$this->toolbox->users->user_account_field_name]);
        }
        
        // All checks passed: ok.
        return $record;
    }

    /**
     *
     * @return mixed the name of the history field of the table, if it exists, else an empty String.
     */
    public function history_field_name (String $table_name)
    {
        if (isset($this->toolbox->config->settings_tfyh["history"][$table_name]))
            return $this->toolbox->config->settings_tfyh["history"][$table_name];
        else
            return false;
    }

    /**
     *
     * @return mixed the String containing the fields to exclude from the history ('.' separated list), if a
     *         history for that table exists, else an empty String.
     */
    private function history_exclude_fields (String $table_name)
    {
        if (isset($this->toolbox->config->settings_tfyh["historyExclude"][$table_name]))
            return $this->toolbox->config->settings_tfyh["historyExclude"][$table_name];
        else
            return "";
    }

    /**
     *
     * @return int the count of versions to be used at maximum for the history field, if a history for that
     *         table exists, else 0.
     */
    private function history_max_versions (String $table_name)
    {
        if (isset($this->toolbox->config->settings_tfyh["maxversions"][$table_name]))
            return intval($this->toolbox->config->settings_tfyh["maxversions"][$table_name]);
        else
            return 0;
    }

    /**
     * Insert a data record into the table with the given name. Checks the column names and removes the field
     * which do not fit. Does not check any key or value, but lets the data base decide on what can be
     * inserted and what not. If the table has a declared history field it adds the version to the history.
     * 
     * @param String $appUserID
     *            the ID of the application user of the user who performs the statement. For change logging.
     * @param String $table_name
     *            the name of the table to be used.
     * @param array $record
     *            a named array with key = column name and value = values to be inserted. Values must be PHP
     *            native encoded Strings. Enclosed quotes "'" will be appropriately escaped for the SQL
     *            command.
     * @return mixed ID of inserted record on success, else a String with warnings and error messages.
     */
    public function insert_into (String $appUserID, String $table_name, array $record)
    {
        // trigger pre-write-modification checks
        foreach ($this->triggers as $name => $trigger) {
            if ($this->debug_on)
                file_put_contents($this->sql_debug_file, 
                        date("Y-m-d H:i:s") . ":  [tfyh_socket->insert_into] triggering $name\n", FILE_APPEND);
            $trigger_result = $trigger->pre_write_transaction($appUserID, 1, $table_name, $record);
            if (is_array($trigger_result))
                $record = $trigger_result;
            elseif ($trigger_result !== true)
                return $trigger_result;
        }
        
        // protect user records from being changed by anyone except the user itself or user admin
        $record = $this->protect_user_rights($appUserID, $table_name, $record);
        if (! is_array($record))
            return $record;
        
        // initialize history data field, if applicable
        $history_field_name = $this->history_field_name($table_name);
        if (strlen($history_field_name) > 0)
            $record[$history_field_name] = $this->update_record_history($appUserID, $table_name, $record, 
                    null);
        
        // create the sql command and the change log entry
        $sql_cmd = "INSERT INTO `" . $table_name . "` (`";
        $change_entry = "inserted: "; // Technical term, no i18n
        foreach ($record as $key => $value) {
            $sql_cmd .= $key . "`, `";
            // no change logging for the record history. That would only create a lot of redundant
            // information
            if (! isset($this->toolbox->config->settings_tfyh["history"][$table_name]) || (strcasecmp($key, 
                    $this->toolbox->config->settings_tfyh["history"][$table_name]) != 0))
                // No quote escaping in the change log entry. This will be handled in
                // execute_and_log().
                $change_entry .= $key . '="' . $value . '", ';
        }
        // cut off last ", `";
        $sql_cmd = mb_substr($sql_cmd, 0, mb_strlen($sql_cmd) - 3);
        $change_entry = mb_substr($change_entry, 0, mb_strlen($change_entry) - 2);
        $sql_cmd .= ") VALUES ('";
        foreach ($record as $key => $value) {
            if (is_null($value))
                $sql_cmd = mb_substr($sql_cmd, 0, mb_strlen($sql_cmd) - 1) . "NULL, '";
            else
                $sql_cmd .= str_replace("'", "\'", str_replace("\\", "\\\\", $value)) . "', '";
        }
        // cut off last ", '";
        $sql_cmd = mb_substr($sql_cmd, 0, mb_strlen($sql_cmd) - 3);
        $sql_cmd .= ")";
        
        // execute sql command and log execution.
        $changed_id = (isset($record["uid"])) ? $record["uid"] : "";
        $res = $this->execute_and_log($appUserID, $table_name, $sql_cmd, $changed_id, $change_entry, true, 
                "insert_into");
        
        // trigger listeners
        if ((strlen($res) == 0) || is_numeric($res)) {
            $this->timestamp_write_access($appUserID);
            foreach ($this->write_listeners as $name => $listener) {
                $listener->post_write_transaction(1, $table_name, $record);
                if ($this->debug_on) {
                    file_put_contents($this->sql_debug_file,
                            date("Y-m-d H:i:s") .
                            ":  [tfyh_socket->insert_record_matched] informed listener $name\n",
                            FILE_APPEND);
                }
            }
        }
        return $res;
    }

    /**
     * log a write action time stamp, one per user and one for any, in ../log/lwa
     * 
     * @param String $appUserID
     *            the user executing the write access
     */
    private function timestamp_write_access (String $appUserID)
    {
        $timef = Tfyh_toolbox::timef();
        file_put_contents("../log/lwa/" . $appUserID, $timef);
        file_put_contents("../log/lwa/any", $timef);
    }

    /**
     * Initialize or update the history field of a data record. Check the existance of a history field within
     * the table structure before calling. Special function: The history field is emptied, if the history
     * field value in the new record is set to "REMOVE!" (without quotation, 7 characters String).
     * 
     * @param int $appUserID
     *            the user id for logging purposes
     * @param String $table_name
     *            the name of the table to which the record belongs. If this table has no history field, an
     *            empty String will be returned.
     * @param array $new_record
     *            the new data record. May be incomplete. If it contains the data field
     *            $record[$history_field_name] that field will be ignored.
     * @param array $current_record
     *            the current data record. Set to null for the insert into operation. If not null it must
     *            include the data field $current_record[$history_field_name]. If it does not, an empty String
     *            is returned.
     * @return the new JSON encoded String for the history field, or an empty String if 1. the records history
     *         field contains the String "REMOVE!" or 2. the table has no history field
     */
    private function update_record_history (int $appUserID, String $table_name, array $new_record, 
            array $current_record = null)
    {
        $history_field_name = $this->history_field_name($table_name);
        if (strlen($history_field_name) == 0)
            return "";
        if (isset($new_record[$history_field_name]) &&
                 (strcmp($new_record[$history_field_name], "REMOVE!") == 0))
            return "";
        $history_field_exclude = $this->history_exclude_fields($table_name);
        // There is a current record, but without a history entry
        if (! is_null($current_record) && (! isset($current_record[$history_field_name]) ||
                 (strlen($current_record[$history_field_name]) < 5))) {
            // start history, first entry at all. Because the history may not have been initialized
            // from the very beginning, this will also be used for updates.
            $history = "1;" . $appUserID . ";" . time() . ";";
            foreach ($current_record as $fieldname => $value) {
                $is_history_field = ($fieldname == $history_field_name);
                $is_exclude_field = (strpos($history_field_exclude, "." . $fieldname . ".") !== false);
                if (! $is_history_field && ! $is_exclude_field) {
                    $first_value = strval($value);
                    if (strlen($first_value) > 1024)
                        $first_value = mb_substr($new_record[$fieldname], 0, 1020) . "...";
                    $history .= $this->toolbox->encode_entry_csv($fieldname . ":" . $first_value) . ";";
                }
            }
            $current_record[$history_field_name] = $history;
        }
        
        // read the current history entry. Keep the version index. Create an empty array for insert
        if (is_null($current_record) || ! isset($current_record[$history_field_name]))
            $record_versions = [];
        else
            $record_versions = $this->get_versions($current_record[$history_field_name]);
        
        // remove empty lines
        $i = 0;
        while ($i < count($record_versions)) {
            if (strlen(trim($record_versions[$i])) == 0)
                array_splice($record_versions, $i, 1);
            else
                $i ++;
        }
        
        // remove versions which are beyond the $max_versions count
        $max_versions = $this->history_max_versions($table_name);
        if (count($record_versions) >= $max_versions)
            $record_versions = array_splice($record_versions, 1 - $max_versions);
        
        // legacy conversion of obsolete pre-2021 history field text encoding.
        $last_version_number = 0;
        for ($i = 0; $i < count($record_versions); $i ++) {
            $record_version = $record_versions[$i];
            if (strlen(trim($record_version)) > 0) {
                $version_number_str = (strpos($record_version, ";") !== false) ? substr($record_version, 0, 
                        strpos($record_version, ";")) : "";
                $version_number_int = (is_numeric($version_number_str)) ? intval($version_number_str) : 0;
                if ($version_number_int == 0) {
                    $last_version_number ++;
                    $record_versions[$i] = $last_version_number . ";0;0;" .
                             $this->toolbox->encode_entry_csv("@all:" . trim($record_version));
                } else 
                    if ($version_number_int > $last_version_number)
                        $last_version_number = $version_number_int;
            }
        }
        
        // find last version number
        $last_version = (count($record_versions) > 0) ? $record_versions[count($record_versions) - 1] : "0;";
        $last_version_number = intval(explode(";", $last_version, 2)[0]);
        $new_version_number = $last_version_number + 1;
        // create a new version record with the delta between current and new record.
        $new_version = $new_version_number . ";" . $appUserID . ";" . time() . ";";
        $any_changes = false;
        $isnull_current_record = is_null($current_record);
        foreach ($new_record as $fieldname => $value) {
            $is_history_field = ($fieldname == $history_field_name);
            $is_exclude_field = (strpos($history_field_exclude, "." . $fieldname . ".") !== false);
            if (! $is_history_field && ! $is_exclude_field) {
                $no_record_to_value = ($isnull_current_record && isset($value) && (strlen($value) > 0));
                $null_to_value = ($isnull_current_record || ! isset($current_record[$fieldname])) &&
                         isset($value) && (strlen($value) > 0);
                $value_to_null = isset($current_record[$fieldname]) &&
                         (strlen($current_record[$fieldname]) > 0) &&
                         (! isset($value) || (strlen($value) == 0));
                $is_changed = $no_record_to_value || $null_to_value || $value_to_null ||
                         (isset($current_record[$fieldname]) && ($value !== $current_record[$fieldname]));
                if ($is_changed) {
                    $any_changes = true;
                    $first_value = strval($value);
                    if (strlen($first_value) > 1024)
                        $first_value = mb_substr($new_record[$fieldname], 0, 1020) . "...";
                    $new_version .= $this->toolbox->encode_entry_csv($fieldname . ":" . $first_value) . ";";
                }
            }
        }
        $new_version = mb_substr($new_version, 0, mb_strlen($new_version) - 1);
        // add the new version, if there were changes, to the history array and return it.
        if ($any_changes)
            $record_versions[] = $new_version;
        
        // compile the history String
        $record_history = "";
        foreach ($record_versions as $record_version)
            if (strlen(trim($record_version)) > 0)
                $record_history .= $record_version . "\n";
        return $record_history;
    }

    /**
     * Little helper to create the "WHERE" - clause to match the $matching key.
     * 
     * @param String $table_name
     *            the name of the table to be used.
     * @param array $matching
     *            the key to be matched. It may containe one or more fields. Values must be UTF-8 encoded
     *            Strings.
     * @param String $condition
     *            the condition for $key and $value, e. g. "!=" for not equal. Set to "" to get every record
     *            or set "NULL" to get all records with the value being NULL. Set "IN" with the value being
     *            the appropraite formatted array String to get values matching the given array. You can use a
     *            condition for each matching field, if so wished, by listing them comma separated, e.g. >,=
     *            for two fields if which the first shall be greater, the second equal to the respective
     *            values. If more matching values are provided, than conditions, the last condition is taken
     *            for all extra matching fields.
     */
    private function clause_for_wherekeyis (String $table_name, array $matching, String $condition)
    {
        if (strlen($condition) == 0)
            return "WHERE 1";
        $wherekeyis = "WHERE ";
        $conditions = explode(",", $condition);
        $c = 0;
        foreach ($matching as $key => $value) {
            if (strcasecmp($conditions[$c], "NULL") == 0) {
                $wherekeyis .= "`" . $table_name . "`.`" . $key . "` IS NULL AND ";
            } else 
                if (strcasecmp($conditions[$c], "IN") == 0) {
                    $wherekeyis .= "`" . $table_name . "`.`" . $key . "` IN (" . $value . ") AND ";
                } else
                    $wherekeyis .= "`" . $table_name . "`.`" . $key . "` " . $conditions[$c] . " '" .
                             strval($value) . "' AND ";
            if ($c < count($conditions) - 1)
                $c ++;
        }
        if (strlen($wherekeyis) == strlen("WHERE "))
            return "WHERE 1";
        $wherekeyis = mb_substr($wherekeyis, 0, mb_strlen($wherekeyis) - 5);
        return $wherekeyis;
    }

    /**
     * Little helper to create the matched record for logging based on the $matching key.
     * 
     * @param array $matching
     *            the key to be matched. It may containe one or more fields. Values must be PHP native encoded
     *            Strings.
     */
    private function matched_record (array $matching)
    {
        $matched_record = "";
        foreach ($matching as $key => $value) {
            if (count($matching) == 1)
                return $value;
            else
                $matched_record .= $key . "=\'" . strval($value) . "\', ";
        }
        if (strlen($matched_record) == 0)
            return i("VpjI4B|[not defined]");
        $matched_record = mb_substr($matched_record, 0, mb_strlen($matched_record) - 2);
        return $matched_record;
    }

    /**
     * Convenience shortcut for update_record_matched with a primary key of name ID
     * 
     * @return an error statement in case of failure, else "".
     */
    public function update_record (String $appUserID, String $table_name, array $record)
    {
        return $this->update_record_matched($appUserID, $table_name, ["ID" => $record["ID"]
        ], $record);
    }

    /**
     * Update a record providing an array with $array[ column name ] = value. The record is matched using the
     * $match_key column and the $record[$match_key] value.
     * 
     * @param String $appUserID
     *            the ID of the application user of the user who performs the statement. For change logging.
     * @param String $table_name
     *            the name of the table to be used.
     * @param array $matching_keys
     *            the keys to be matched. It may containe one or more field names as indexed array. Values are
     *            part of the record provided.
     * @param array $record
     *            a named array with key = column name and value = values to be used for update. Enclosed
     *            quotes "'" will be appropriately escaped for the SQL command.
     * @return an error statement in case of failure, else "".
     */
    public function update_record_matched (String $appUserID, String $table_name, array $matching_keys, 
            array $record)
    {
        // trigger pre-write-modification checks
        foreach ($this->triggers as $name => $trigger) {
            if ($this->debug_on)
                file_put_contents($this->sql_debug_file, 
                        date("Y-m-d H:i:s") . ":  [tfyh_socket->update_record_matched] triggering $name\n", 
                        FILE_APPEND);
            $trigger_result = $trigger->pre_write_transaction($appUserID, 2, $table_name, $record);
            if (is_array($trigger_result))
                $record = $trigger_result;
            elseif ($trigger_result !== true)
                return $trigger_result;
        }
        
        // protect user records from being changed by anyone except the user itself or user admin
        $record = $this->protect_user_rights($appUserID, $table_name, $record);
        if (! is_array($record))
            return $record;
        
        // update history data field
        $prev_rec = $this->find_record_matched($table_name, $matching_keys);
        if ($prev_rec === false)
            return i("EvZXbc|Error updating record in...", $table_name) . " " . json_encode($matching_keys);
        $history_field_name = $this->history_field_name($table_name);
        if (strlen($history_field_name) > 0)
            $record[$history_field_name] = $this->update_record_history($appUserID, $table_name, $record, 
                    $prev_rec);
        
        $change_entry = "updated: "; // Technical term, no i18n
                                     // create SQL command and change log entry.
        $sql_cmd = "UPDATE `" . $table_name . "` SET ";
        foreach ($record as $key => $value) {
            // check empty values. 1a. If previous and current are empty, skip the field.
            $skip_update = (! isset($prev_rec[$key]) || (strlen($prev_rec[$key]) == 0)) && (! isset($value) ||
                     (strlen($value) == 0));
            // check mismatching fields. 1b. If the current record has an extra field, drop it.
            $skip_update = $skip_update || ! array_key_exists($key, $prev_rec);
            // skip matching keys, they are anyway equal
            foreach ($matching_keys as $matching_key)
                $skip_update = $skip_update || (strcasecmp($key, $matching_key) == 0);
            if (! $skip_update) {
                if ((is_null($value) || (strlen($value) == 0)) && (is_numeric($prev_rec[$key]) ||
                         is_numeric(strtotime($prev_rec[$key])))) {
                    // replace empty numeric values. 2. If previous was not empty, and the record
                    // was numeric
                    // or a date, this will create a number format error, instead use NULL as value
                    $sql_cmd .= "`" . $key . "` = NULL,";
                } else {
                    if (is_null($value))
                        $sql_cmd .= "`" . $key . "` = NULL,";
                    else
                        $sql_cmd .= "`" . $key . "` = '" . str_replace("'", "\'", str_replace("\\", "\\\\", $value)) . "',";
                }
            }
            // the change entry shall neither contain the keys, nor the record history to
            // prevent from too much redundant information
            if (! $skip_update && (strcmp($value, $prev_rec[$key]) !== 0) && (! isset(
                    $this->toolbox->config->settings_tfyh["history"][$table_name]) || (strcasecmp($key, 
                    $this->toolbox->config->settings_tfyh["history"][$table_name]) != 0)))
                // No quote escaping in the change log entry. This will be handled in
                // execute_and_log().
                $change_entry .= $key . ': "' . $prev_rec[$key] . '"=>"' . $value . '", ';
        }
        
        $sql_cmd = mb_substr($sql_cmd, 0, mb_strlen($sql_cmd) - 1);
        $change_entry = mb_substr($change_entry, 0, mb_strlen($change_entry) - 2);
        $sql_cmd .= " " . $this->clause_for_wherekeyis($table_name, $matching_keys, "=");
        
        // execute sql command and log execution.
        $result = $this->execute_and_log($appUserID, $table_name, $sql_cmd, 
                $this->matched_record($matching_keys), $change_entry, false, "update_record");

        // trigger listeners
        if (strlen($result) == 0) {
            $this->timestamp_write_access($appUserID);
            foreach ($this->write_listeners as $name => $listener) {
                $listener->post_write_transaction(2, $table_name, $record);
                if ($this->debug_on) {
                    file_put_contents($this->sql_debug_file,
                            date("Y-m-d H:i:s") .
                            ":  [tfyh_socket->update_record_matched] informed listener $name\n",
                            FILE_APPEND);
                }
            }
        }
        return $result;
    }

    /**
     * Convenience shortcut for delete_record_matched with a primary key of name ID
     * 
     * @return String an error statement in case of failure, else "".
     */
    public function delete_record (String $appUserID, String $table_name, String $id)
    {
        return $this->delete_record_matched($appUserID, $table_name, ["ID" => $id
        ]);
    }

    /**
     * Delete a record. The record is matched using the $match_key column and the $record[$match_key] value.
     * This deletes the entire record from the data base, including its history. It may only be restored
     * manually using the change log.
     * 
     * @param String $appUserID
     *            the ID of the application user of the user who performs the statement. For change logging.
     * @param String $table_name
     *            the name of the table to be used.
     * @param array $matching
     *            the key to be matched. It may containe one or more fields. Values must be PHP native encoded
     *            Strings.
     * @return String an error statement in case of failure, else "".
     */
    public function delete_record_matched (String $appUserID, String $table_name, array $matching)
    {
        // trigger pre-write-modification checks
        foreach ($this->triggers as $name => $trigger) {
            if ($this->debug_on)
                file_put_contents($this->sql_debug_file, 
                        date("Y-m-d H:i:s") . ":  [tfyh_socket->delete_record_matched] triggering $name\n", 
                        FILE_APPEND);
            $trigger_result = $trigger->pre_write_transaction($appUserID, 3, $table_name, $matching);
            if (is_array($trigger_result))
                $record = $trigger_result;
            elseif ($trigger_result !== true)
                return $trigger_result;
        }
        
        // protect user records from being deleted by anyone except the user admin
        if (strcasecmp($table_name, $this->toolbox->users->user_table_name) == 0) {
            $session_user = $this->find_record($table_name, $this->toolbox->users->user_id_field_name, 
                    $this->toolbox->users->session_user["@id"]);
            if (($session_user === false) ||
                     (strcasecmp($session_user["Rolle"], $this->toolbox->users->useradmin_role) != 0))
                return i("6vuCGA|Only a user admin is all...");
            if ($this->count_records($this->toolbox->users->user_table_name) == 1)
                return i("rGyRdy|The very last user must ...");
        }
        
        // get previous record to log change
        $prev_rec = $this->find_record_matched($table_name, $matching);
        if ($prev_rec === false)
            return i("S9MHHT|Record to delete was not...");
        
        // create change log entry and SQL command.
        // No quote escaping in the change log entry. This will be handled in
        // execute_and_log().
        $change_entry = "deleted: "; // technical term, no i18n
        foreach ($prev_rec as $key => $value) {
            $change_entry .= $key . "='" . $prev_rec[$key] . "', ";
        }
        // deletions will not change the last modified time stamp, because they
        // delete the data anyway.
        $change_entry = mb_substr($change_entry, 0, mb_strlen($change_entry) - 2);
        // ID used is **ID**
        $sql_cmd .= "DELETE FROM `" . $table_name . "` " .
                 $this->clause_for_wherekeyis($table_name, $matching, "=");
        
        // execute sql command and log execution.
        $result = $this->execute_and_log($appUserID, $table_name, $sql_cmd, $this->matched_record($matching), 
                $change_entry, false, "delete_record");
        
        // trigger listeners
        if (strlen($result) == 0) {
            $this->timestamp_write_access($appUserID);
            foreach ($this->write_listeners as $name => $listener) {
                $listener->post_write_transaction(3, $table_name, $prev_rec);
                if ($this->debug_on)
                    file_put_contents($this->sql_debug_file, 
                            date("Y-m-d H:i:s") .
                                     ":  [tfyh_socket->delete_record_matched] informed listener $name\n", 
                                    FILE_APPEND);
            }
        }
        return $result;
    }

    /**
     * ************************ SELECT (FIND) RECORDS *****************************
     */
    
    /**
     * Convenience shortcut for find_record_matched with a primary key of name ID
     */
    public function get_record (String $table_name, String $id)
    {
        return $this->find_record_matched($table_name, ["ID" => $id
        ]);
    }

    /**
     * Convenience shortcut for find_record_matched with a single matching field
     */
    public function find_record (String $table_name, String $key, String $value)
    {
        return $this->find_record_matched($table_name, [$key => $value
        ]);
    }

    /**
     * Find the first record as associative array of key => value matching the $matching key. Returns false,
     * if the record key could not be matched or any other error occurred.
     * 
     * @param String $table_name
     *            the name of the table to be used.
     * @param array $matching
     *            the key to be matched (Values in PHP native encoding). It may contain one or more fields.
     *            Set to [] to get all.
     * @return array first record found as associative array of key => value. False in case of either
     *         connection error or no match. Values are UTF8 encoded.
     */
    public function find_record_matched (String $table_name, array $matching)
    {
        $sets = $this->find_records_matched($table_name, $matching, 1);
        if (($sets === false) || (count($sets) == 0))
            return false;
        return $sets[0];
    }

    /**
     * Convenience shortcut for find_records_matched with a single matching field
     */
    public function find_records (String $table_name, String $key, String $value, int $max_rows)
    {
        if ((strlen($key) == 0) && (strlen($value) == 0))
            $matching = [];
        else
            $matching = [$key => $value
            ];
        return $this->find_records_matched($table_name, $matching, $max_rows);
    }

    /**
     * Find all records as indexed array of records, each as associative array of key => value matching the
     * $matching key. Returns false, if the record key could not be matched or any other error occurred.
     * 
     * @param String $table_name
     *            the name of the table to be used.
     * @param array $matching
     *            the key to be matched. It may contain one or more fields. Values must be PHP native encoded
     *            Strings. Set to [] to get all.
     * @param int $max_rows
     *            the maximum number of rows to be returned.
     * @return array of records, each as associative array of key => value. False in case of either connection
     *         error or no match. Values are UTF8 encoded.
     */
    public function find_records_matched (String $table_name, array $matching, int $max_rows)
    {
        return $this->find_records_sorted_matched($table_name, $matching, $max_rows, 
                ((count($matching) == 0) ? "" : "="), "", true);
    }

    /**
     * Convenience shortcut for find_records_sorted_matched with a single matching field
     */
    public function find_records_sorted (String $table_name, String $key, String $value, int $max_rows, 
            String $condition, String $sort_key, bool $sort_ascending)
    {
        return $this->find_records_sorted_matched($table_name, [$key => $value
        ], $max_rows, $condition, $sort_key, $sort_ascending);
    }

    /**
     * Find all records as indexed array of records, each as associative array of key => value matching the
     * $matching key. Sort them in the requested order. Returns false, if the value is not found or any other
     * error occurred.
     * 
     * @param String $table_name
     *            the name of the table to be used.
     * @param array $matching
     *            the key to be matched. It may contain one or more fields. Values must be PHP native encoded
     *            Strings. Set to [] to get all.
     * @param int $max_rows
     *            the maximum number of rows to be returned.
     * @param String $condition
     *            the condition for $key and $value, e. g. "!=" for not equal. Set to "" to get every record
     *            or set "NULL" to get all records with the value being NULL. Set "IN" with the value being
     *            the appropraite formatted array String to get values matching the given array. You can use a
     *            condition for each matching field, if so wished, by listing them comma separated, e.g. >,=
     *            for two fields if which the first shall be greater, the second equal to the respective
     *            values. If more matching values are provided, than conditions, the last condition is taken
     *            for all extra matching fields.
     * @param String $sort_key
     *            the name of the column to sort for. Set "" (previous versions: false, may also work) to do
     *            no sorting. Can be a list, comma separated. Precede by a '#' to sort as numbers, e.g.
     *            "#EntryId".
     * @param bool $sort_ascending
     *            set to true to sort in ascending order, false to sort in descending order.
     * @param int $start_row
     *            (default = 0) set a value > 0 to start not with the first row. Use it for getting chunks
     *            rather than all.
     * @return array of records, each being an array of key = column name and value = value. False in case of
     *         either connection error or no match. Values are UTF8 encoded.
     */
    public function find_records_sorted_matched (String $table_name, array $matching, int $max_rows, 
            String $condition, String $sort_key, bool $sort_ascending, int $start_row = 0)
    {
        // compile command parts: columns to choose
        $col_names = $this->get_column_names($table_name);
        $col_indicators = "";
        foreach ($col_names as $col_name)
            $col_indicators .= "`" . $col_name . "`, ";
        if (strlen($col_indicators) == 0)
            $col_indicators = "*";
        else
            $col_indicators = mb_substr($col_indicators, 0, mb_strlen($col_indicators) - 2);
        // compile command parts: rows to choose
        $where_string = (strlen($condition) == 0) ? 'WHERE 1 ' : $this->clause_for_wherekeyis($table_name, 
                $matching, $condition);
        // compile command parts: sorting of result
        $sort_str = "";
        if ($sort_key && strlen($sort_key) > 0) {
            $sort_way = ($sort_ascending) ? "ASC" : "DESC";
            $sort_cols = explode(",", $sort_key);
            $sort_str = " ORDER BY ";
            foreach ($sort_cols as $sort_col) {
                if (substr($sort_col, 0, 1) == '#')
                    $sort_str .= "CAST(`" . $table_name . "`.`" . substr($sort_col, 1) . "` AS UNSIGNED) " .
                             $sort_way . ", ";
                else
                    $sort_str .= "`" . $table_name . "`.`" . $sort_col . "` " . $sort_way . ", ";
            }
            $sort_str = mb_substr($sort_str, 0, mb_strlen($sort_str) - 2);
        }
        // compile command parts: limit or chunk of returned rows
        $limit_string = " LIMIT " . $start_row . "," . $max_rows;
        
        // compile command and execute
        $sql_cmd = "SELECT " . $col_indicators . " FROM `" . $table_name . "` " . $where_string . $sort_str .
                 $limit_string;
        $res = $this->mysqli_query($sql_cmd, "find_records_sorted_matched");
        $rows = [];
        $n_rows = 0;
        if (isset($res) && ($res !== false) && (intval($res->num_rows) > 0)) {
            $row = $res->fetch_row();
            while (($row) && ($n_rows < $max_rows)) {
                $rows[] = $row;
                $n_rows ++;
                $row = $res->fetch_row();
            }
        } else
            return false;
        
        // add column names to build an associative array
        $column_names = $this->get_column_names($table_name);
        $i = 0;
        foreach ($column_names as $column_name) {
            for ($r = 0; $r < $n_rows; $r ++)
                $sets[$r][$column_name] = $rows[$r][$i];
            $i ++;
        }
        
        // trigger listeners
        foreach ($this->read_listeners as $name => $listener) {
            if (is_array($sets)) {
                $listener_result = $listener->post_read_transaction($table_name, $sets);
                if ($this->debug_on)
                    file_put_contents($this->sql_debug_file, 
                            date("Y-m-d H:i:s") .
                                     ":  [tfyh_socket->find_records_sorted_matched] notified read listener $name\n", 
                                    FILE_APPEND);
                if ($listener_result !== true)
                    $sets = $listener_result;
            }
        }
        
        // return result
        return $sets;
    }

    /**
     * Count the number of records within a table
     * 
     * @param String $tablename
     *            the table to look into
     * @param array $matching
     *            the key to be matched. It may contain one or more fields. Values must be PHP native encoded
     *            Strings. Set to null or omit to get all.
     * @param String $condition
     *            the condition for $key and $value, e. g. "!=" for not equal. Set to "" to get every record.
     *            You can use a condition for each matching field, if so wisched, by listing them comma
     *            separated, e.g. >,= for two fields if which the first shall be greater, the second equal to
     *            the respective values. If more matching values are provided, than conditions, the last
     *            condition is taken for all extra matching fields. Ignored if $matching == null.
     * @return int the count of records in the table
     */
    public function count_records (String $tablename, array $matching = null, String $condition = "")
    {
        // now retrieve all column names
        $sql_cmd = ($matching == null) ? "SELECT COUNT(*) FROM `" . $tablename . "`;" : "SELECT COUNT(*) FROM `" .
                 $tablename . "` " . $this->clause_for_wherekeyis($tablename, $matching, $condition);
        $this->last_sql_executed = $sql_cmd;
        $res = $this->mysqli_query($sql_cmd, "count_records");
        $count = 0;
        if (is_object($res) && intval($res->num_rows) > 0) {
            $row = $res->fetch_row();
            $count = intval($row[0]);
        }
        return $count;
    }

    /**
     * Count the frequencs of different values within a table column (like one column pivoting).
     * 
     * @param String $tablename
     *            the table to look into
     * @param String $column
     *            the colum in which values are to be counted.
     * @return array the different values as array keys and their count as array values.
     */
    public function count_values (String $tablename, String $columnname)
    {
        // now retrieve all column names
        $sql_cmd = "SELECT `" . $columnname . "`, COUNT(`" . $columnname . "`) FROM `" . $tablename .
                 "` GROUP BY `" . $columnname . "`;";
        $res = $this->mysqli_query($sql_cmd, "count_values");
        $ret = [];
        if (is_object($res) && intval($res->num_rows) > 0) {
            $row = $res->fetch_row();
            while ($row) {
                $value = $row[0];
                $count = intval($row[1]);
                $ret[$value] = $count;
                $row = $res->fetch_row();
            }
        }
        return $ret;
    }

    /**
     * ***************************** GET AND IMPORT FULL TABLES ******************************
     */
    
    /**
     * Get a table as csv String.
     * 
     * @param String $table_name
     *            the name of the table to be used.
     * @return the full table as csv String
     */
    public function get_table_as_csv (String $table_name)
    {
        $cols = $this->get_column_names($table_name);
        if (($cols === false) || (count($cols) == 0))
            return "no_columns_found_in_" . $table_name; // no i18n
        
        $csv = "";
        foreach ($cols as $col)
            $csv .= $col . ";";
        $csv = mb_substr($csv, 0, mb_strlen($csv) - 1) . "\n";
        
        $col_indicators = "";
        foreach ($cols as $col_name)
            $col_indicators .= "`" . $col_name . "`, ";
        $col_indicators = substr($col_indicators, 0, strlen($col_indicators) - 2);
        $sql_cmd = "SELECT " . $col_indicators . " FROM `" . $table_name . "` WHERE 1";
        $res = $this->mysqli_query($sql_cmd, "get_table_as_csv");
        if ($res !== false) {
            $row = $res->fetch_row();
            while ($row) {
                // the fetch_row function is an iterator, returning an array with
                // the table name always being at pos 0
                $entries = 0;
                foreach ($row as $entry) {
                    if ((strpos($entry, ";") !== false) || (strpos($entry, '"') !== false))
                        $entry = '"' . str_replace('"', '""', $entry) . '"';
                    $csv .= $entry . ";";
                    $entries ++;
                }
                if ($entries > 0)
                    $csv = mb_substr($csv, 0, mb_strlen($csv) - 1);
                $csv .= "\n";
                $row = $res->fetch_row();
            }
            $res->free();
        }
        return $csv;
    }

    /**
     * Get a table as PHP array.
     * 
     * @param String $table_name
     *            the name of the table to be used.
     * @param String $filter_and_order
     *            a String which will be added to the SQL statement containing the WHERE and ORDER BY clauses,
     *            e.g. "WHERE 1 ORDER BY `persons`.`user_id` DESC".
     * @return the full table as array of rows, each row being an array of $entries as $key => $value with
     *         $key being the respective column name.
     */
    public function get_table_as_array (String $table_name, String $filter_and_order)
    {
        $cols = $this->get_column_names($table_name);
        $header = [];
        $c = 0;
        foreach ($cols as $col) {
            $header[$c] = $col;
            $c ++;
        }
        
        $col_indicators = "";
        foreach ($cols as $col_name)
            $col_indicators .= "`" . $col_name . "`, ";
        $col_indicators = mb_substr($col_indicators, 0, mb_strlen($col_indicators) - 2);
        $sql_cmd = "SELECT " . $col_indicators . " FROM " . $table_name . " " . $filter_and_order;
        $res = $this->mysqli_query($sql_cmd, "get_table_as_array");
        $rows = [];
        if ($res !== false) {
            $row = $res->fetch_row();
            while ($row) {
                // the fetch_row function is an iterator, returning an array with
                // the table name always being at pos 0
                $entries = [];
                $c = 0;
                foreach ($row as $entry) {
                    $entries[$header[$c]] = $entry;
                    $c ++;
                }
                $rows[] = $entries;
                $row = $res->fetch_row();
            }
            $res->free();
        }
        return $rows;
    }

    /**
     * Import a csv file into a table or delete table records (provide single column csv with IDs only). The
     * csv-file must use the ';' separator and '"' text delimiters. It must contain a headline with column
     * names that are literally identical to the mySQL internal column names. All data records must be of the
     * same length as the header line, not more, not less. If a data record does not comply, it will not be
     * imported. The first column must be 'ID'. If this is not the case, no data will be imported at all. For
     * data records with an existing 'ID' all provided record fields will be replaced, i. e. data will be
     * deleted, if the respective field is empty. For data records with an empty 'ID' the 'ID' will be auto
     * generated. In this case, and if the provided 'ID' is not yet existing, a new table record is inserted
     * into the table. All changes will be logged, as if they had been made manually.
     * 
     * @param String $appUserID
     *            the ueser ID of the user who performs the statement. For change logging.
     * @param String $table_name
     *            name of table into which the data shal be loaded. Must be exactly a name of a database
     *            table.
     * @param String $csv_file_path
     *            path to file to be imported or to single column list of IDs which shall be deleted.
     * @param bool $verify_only
     *            set true to only see what would be done
     * @param String $idname
     *            Optional, default = "ID". Set it to the field name of the ID to use for comparison.
     * @return array the import result
     */
    public function import_table_from_csv (String $appUserID, String $table_name, String $csv_file_path, 
            bool $verify_only, String $idname = "ID")
    {
        // read table
        $table_read = $this->toolbox->read_csv_array($csv_file_path);
        if ($table_read === [])
            return i("g1Qw1Z|#Error: Import aborted b...");
        
        // check, if all column names exist and if column name "ID" exists
        $column_names = $this->get_column_names($table_name);
        $columns_not_matched = "";
        $column_ID_exists = in_array($idname, $column_names);
        $column_LastModified_exists = in_array("LastModified", $column_names);
        $column_modified_exists = in_array("modified", $column_names);
        $column_created_by_exists = in_array("created_by", $column_names);
        $column_created_on_exists = in_array("created_on", $column_names);
        foreach ($table_read[0] as $column => $entry)
            if (! in_array($column, $column_names))
                $columns_not_matched .= $column . ", ";
        if (! $column_ID_exists)
            return i("UB62oq|#Error: Import aborted. ...", $idname);
        if (strlen($columns_not_matched) > 0)
            return i("tAzk9C|#Error: Import aborted b...") . " " . $columns_not_matched;
        
        // special case: if the able contains but the ID, then the records for
        // the given IDs shall be deleted
        $delete_entries = (count($table_read[0]) == 1);
        
        // execute import
        $result = "";
        foreach ($table_read as $record) {
            // check whether ID was set
            $update_record = strlen($record[$idname]) > 0;
            $id = $record[$idname];
            if ($update_record && ! $delete_entries) {
                // check whether ID exists
                $sql_cmd = "SELECT * FROM `" . $table_name . "` WHERE `" . $idname . "` = '" . $id . "'";
                $res = $this->mysqli_query($sql_cmd, "import_table_from_csv");
                $update_record = ($res !== false) && (intval($res->num_rows) > 0);
                if ($update_record) {
                    // update record now
                    $result .= i("UU1TV0|Update %1 with: %2", $idname, $id) . "<br>";
                    foreach ($record as $key => $entry) {
                        $result .= htmlspecialchars($entry) . ";";
                        // $this->update_record expects utf-8 encoded Strings
                        $record[$key] = $entry;
                    }
                    // Add timestamp for Last modification (efacloud legacy)
                    if ($column_LastModified_exists)
                        $record["LastModified"] = time() . "000";
                    // Add timestamp for Last modification (dilbo & following)
                    if ($column_modified_exists)
                        $record["modified"] = Tfyh_toolbox::timef();
                    $result .= "<br />";
                    if (! $verify_only)
                        $result .= $this->update_record_matched($appUserID, $table_name, 
                                [$idname => $id
                                ], $record) . "<br />";
                } else {
                    $result .= i("bciyl7|Skip missing %1 %2", $idname, $id) . "<br />";
                }
            } else {
                if ($delete_entries) {
                    // delete record now
                    $result .= i("IH9RiO|Deleting %1 %2 Record wi...", $idname, $id) . " ";
                    $current_record = $this->find_record_matched($table_name, 
                            [$idname => $id
                            ]);
                    foreach ($current_record as $entry)
                        $result .= htmlspecialchars($entry) . ";";
                    $result .= "<br />";
                    if (! $verify_only)
                        $result .= $this->delete_record_matched($appUserID, $table_name, 
                                [$idname => $id
                                ]);
                } else {
                    // insert record now
                    $result .= i("CWEDJn|Inserting:") . " ";
                    foreach ($record as $key => $entry) {
                        $result .= htmlspecialchars($entry) . ";";
                        // $this->insert_into expects utf-8 encoded Strings
                        $record[$key] = $entry;
                    }
                    $result .= "<br />";
                    if (! $verify_only) {
                        // create a missing uid
                        if ((strcmp($idname, "uid") == 0) &&
                                 (! isset($record["uid"]) || (strlen($record["uid"]) < 4)))
                            $record[$idname] = Tfyh_toolbox::create_uid(6);
                        // remove the empty "ID" String to ensure it is
                        // autoincremented
                        else 
                            if ((strcmp($idname, "ID") == 0) &&
                                     (! isset($record["ID"]) && (strlen($record["ID"]) > 0)))
                                unset($record[$idname]);
                        // Add timestamp for Last modification (efacloud legacy)
                        if ($column_LastModified_exists)
                            $record["LastModified"] = time() . "000";
                        // Add timestamp for creation (dilbo & following)
                        if ($column_created_on_exists)
                            $record["created_on"] = Tfyh_toolbox::timef();
                        if ($column_modified_exists)
                            $record["modified"] = Tfyh_toolbox::timef();
                        if ($column_created_by_exists)
                            $record["created_by"] = $appUserID;
                        if ($column_modified_exists)
                            $record["modified"] = Tfyh_toolbox::timef();
                        $insert_into_res = $this->insert_into($appUserID, $table_name, $record);
                        // in case of success $insert_into_res will be the id of the inserted
                        // record.
                        if (! is_numeric($insert_into_res))
                            $result .= $insert_into_res;
                    }
                }
            }
        }
        
        // return result.
        $result = ($verify_only) ? "<b>" . i("8h4mmf|The following changes ar...") . "</b><br />" . $result : "<b>" .
                 i("8gVWmU|The following changes ha...") . "</b><br />" . $result;
        return $result;
    }

    /**
     * *************************** GET STRUCTURE INFORMATION *****************************
     */
    
    /**
     * Simple getter
     * 
     * @return data base name
     */
    public function get_db_name ()
    {
        return $this->db_name;
    }

    /**
     * Simple getter
     * 
     * @return data base server version, no i18n
     */
    public function get_server_info ()
    {
        return "Client info = " . $this->mysqli->client_info . ", Server info = " . $this->mysqli->server_info .
                 ", Server version = " . $this->mysqli->server_version;
    }

    /**
     * Get all nullable properties.
     * 
     * @param String $table_name
     *            the name of the table to be used.
     * @return array of true/false or false, if data base connection fails.
     */
    public function get_column_nullables (String $table_name)
    {
        // Retrieve all column names
        $sql_cmd = "SELECT `IS_NULLABLE` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA`='" .
                 $this->db_name . "' AND `TABLE_NAME`='" . $table_name . "' ORDER BY ORDINAL_POSITION";
        
        $result = $this->mysqli_query($sql_cmd, "get_column_names");
        $ret = [];
        if (! is_array($result) && ! is_object($result))
            return $ret;
        // put all values to the array, with numeric autoincrementing key.
        $nullables = $result->fetch_array();
        while ($nullables) {
            // the fetch_array function is an iterator, returning an array with
            // the nullable property always being at pos 0
            $ret[] = (strcasecmp($nullables[0], "YES") == 0);
            $nullables = $result->fetch_array();
        }
        return $ret;
    }

    /**
     * Get all column names by ordinal position as array with $array[n] = n. column's name.
     * 
     * @param String $table_name
     *            the name of the table to be used.
     * @return array of column names or false, if data base connection fails.
     */
    public function get_column_names (String $table_name)
    {
        // Retrieve all column names
        $sql_cmd = "SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA`='" .
                 $this->db_name . "' AND `TABLE_NAME`='" . $table_name . "' ORDER BY ORDINAL_POSITION";
        
        $result = $this->mysqli_query($sql_cmd, "get_column_names");
        $ret = [];
        if (! is_array($result) && ! is_object($result))
            return $ret;
        // put all values to the array, with numeric autoincrementing key.
        $column_names = $result->fetch_array();
        while ($column_names) {
            // the fetch_array function is an iterator, returning an array with
            // the column name
            // always being at pos 0
            $ret[] = $column_names[0];
            $column_names = $result->fetch_array();
        }
        return $ret;
    }

    /**
     * Get all column types by ordinal position as array with $array[n] = n. column's type.
     * 
     * @param String $table_name
     *            the name of the table to be used.
     * @return array numbered array of column types including size (if not 0) like varchar(192) or false, if
     *         data base connection fails.
     */
    public function get_column_types (String $table_name)
    {
        // now retrieve all column names
        $sql_cmd = "SELECT `DATA_TYPE`, `CHARACTER_MAXIMUM_LENGTH` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA`='" .
                 $this->db_name . "' AND `TABLE_NAME`='" . $table_name . "' ORDER BY ORDINAL_POSITION";
        $res = $this->mysqli_query($sql_cmd, "get_column_types");
        $ret = [];
        if (! is_array($res) && ! is_object($res))
            return $ret;
        // put all values to the array, with numeric autoincrementing key.
        $column_types = $res->fetch_array();
        while ($column_types) {
            // the fetch_array function is an iterator
            $ret[] = (strlen($column_types[1]) > 0) ? $column_types[0] . " (" . $column_types[1] . ")" : $column_types[0];
            $column_types = $res->fetch_array();
        }
        return $ret;
    }

    /**
     * Get all indexes as array with $array[column_name] = index description.
     * 
     * @param String $table_name
     *            the name of the table to be used.
     * @param bool $include_description
     *            set true to return the indexes as associative array, name => description, false to get a
     *            String array of the indexed column names.
     * @return array of indexes or false, if data base connection fails.
     */
    public function get_indexes (String $table_name, bool $include_description)
    {
        $index_response_columns = ["Table","Non_unique","Key_name","Seq_in_index","Column_name",
                "Collation","Cardinality","Sub_part","Packed","Null","Index_type","Comment","Index_comment",
                "Visible","Expression"
        ];
        
        // Unique and nullable property
        $index_relevant_columns = ["Non_unique","Key_name","Column_name","Null"
        ];
        $sql_cmd = "SHOW KEYS FROM `" . $table_name . "`";
        $indexes = [];
        $res = $this->mysqli_query($sql_cmd, "get_indexes");
        if (! is_array($res) && ! is_object($res))
            return $indexes;
        if ($res->num_rows > 0) {
            $row = $res->fetch_row();
            while ($row) {
                $c = 0;
                foreach ($index_response_columns as $index_response_column) {
                    if (in_array($index_response_column, $index_relevant_columns))
                        $index[$index_response_column] = $row[$c];
                    $c ++;
                }
                $index_description = " ";
                if (isset($index["Non_unique"]) && (intval($index["Non_unique"]) === 0))
                    $index_description .= " : UNIQUE ";
                if (isset($index["Key_name"]) && $include_description)
                    $indexes[$index["Key_name"]] = $index_description;
                else
                    $indexes[] = $index["Key_name"];
                $row = $res->fetch_row();
            }
        }
        
        return $indexes;
    }

    /**
     * Get all not null columns as array with $array[column_name] = description.
     * 
     * @param String $table_name
     *            the name of the table to be used.
     */
    public function get_not_null (String $table_name)
    {
        
        // NOT NULL property
        $sql_cmd = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '" . $table_name .
                 "' AND IS_NULLABLE = 'NO'";
        $not_nulls = [];
        $res = $this->mysqli_query($sql_cmd, "get_not_null");
        if (! is_array($res) && ! is_object($res))
            return $not_nulls;
        if ($res->num_rows > 0) {
            $row = $res->fetch_row();
            while ($row) {
                $not_nulls[$row[3]] = $row[15] . ", " . $row[16] . ", " . $row[18];
                $row = $res->fetch_row();
            }
        }
        return $not_nulls;
    }

    /**
     * Get all autoincrements as array with $array[column_name] = description.
     * 
     * @param String $table_name
     *            the name of the table to be used.
     */
    public function get_autoincrements (String $table_name)
    {
        $sql_cmd = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '" . $table_name .
                 "' AND EXTRA LIKE '%auto_increment%'";
        $autoincrements = [];
        $res = $this->mysqli_query($sql_cmd, "get_autoincrements");
        if (! is_array($res) && ! is_object($res))
            return $autoincrements;
        if ($res->num_rows > 0) {
            $row = $res->fetch_row();
            while ($row) {
                $autoincrements[$row[3]] = $row[15] . ", " . $row[16] . ", " . $row[18];
                $row = $res->fetch_row();
            }
        }
        return $autoincrements;
    }

    /**
     * Get all available table names.
     * 
     * @return array of table names or false, if data base connection fails.
     */
    public function get_table_names ()
    {
        $sql_cmd = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' " .
                 "AND TABLE_SCHEMA='" . $this->db_name . "' ";
        $res = $this->mysqli_query($sql_cmd, "get_table_names");
        // put all values to the array, the column name being the key.
        $ret = [];
        $row = $res->fetch_row();
        while ($row) {
            // the fetch_row function is an iterator, returning an array with
            // the table name always being at pos 0
            $ret[] = $row[0];
            $row = $res->fetch_row();
        }
        $res->free();
        return $ret;
    }

    /**
     * Get the size in kB of all tables of the data base.
     * 
     * @return array the tables sizes as associative array, sorted by largest first.
     */
    public function get_table_sizes_kB ()
    {
        $sql_cmd = "SELECT TABLE_NAME AS `Table`, ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024) AS `Size (kB)` " .
                 "FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . $this->db_name .
                 "' ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;";
        $res = $this->mysqli_query($sql_cmd, "get_table_sizes");
        // put all values to the array, the column name being the key.
        $ret = [];
        $row = $res->fetch_row();
        while ($row) {
            // the fetch_row function is an iterator, returning an array with
            // the table name always being at pos 0
            $ret[$row[0]] = $row[1];
            $row = $res->fetch_row();
        }
        $res->free();
        foreach ($ret as $tablename => $tablesize) {
            $column_names = $this->get_column_names($tablename);
            $column_types = $this->get_column_types($tablename);
            $total_blob_size_kB = 0;
            for ($c = 0; $c < count($column_names); $c ++) {
                if (strpos(strtolower($column_types[$c]), "text") !== false) {
                    $sql_cmd = "SELECT SUM(OCTET_LENGTH(`" . $column_names[$c] .
                             "`)) AS TOTAL_SIZE FROM `$tablename`";
                    $res = $this->mysqli_query($sql_cmd, "get_blob_colum_size");
                    $row = $res->fetch_row();
                    $column_size = intval($row[0] / 1024);
                    $total_blob_size_kB += $column_size;
                }
            }
            $ret[$tablename] += $total_blob_size_kB;
        }
        return $ret;
    }

    /**
     * Get the last access error.
     * 
     * @return String the error.
     */
    public function get_error ()
    {
        return $this->mysqli->error;
    }

    /**
     * Get all versions of a history entry by splitting it into lines and combining those lines, which end
     * within a quoted entry.
     * 
     * @param String $record_history
     *            The record history entry
     * @return array[] all the versions decoded.
     */
    private function get_versions (String $record_history)
    {
        $record_history_lines = explode("\n", $record_history);
        $i = 0;
        $record_versions = [];
        while ($i < count($record_history_lines)) {
            $record_version = $record_history_lines[$i];
            $i ++;
            $cnt_quotes = substr_count($record_version, "\"");
            while (($cnt_quotes % 2 != 0) && ($i < count($record_history_lines))) {
                $record_version .= $record_history_lines[$i];
                $i ++;
                $cnt_quotes = substr_count($record_version, "\"");
            }
            if (strlen(trim($record_version)) > 0)
                $record_versions[] = $record_version;
        }
        return $record_versions;
    }

    /**
     * Parse a history String and return the record history as html tables, each version being a table.
     * 
     * @param String $record_history
     *            The record history String.
     * @param String $restore_link
     *            A link to be included as a restore button per version. Omit or Set to "" if not used.
     *            Example "../pages/show_history.php?table=users&id=123". The respective version will be added
     *            as "&restore_version=[number]".
     */
    public function get_history_html (String $record_history, String $restore_link = "")
    {
        global $dfmt_d, $dfmt_dt;
        // read the current history. Keep the version index. Create an empty array for insert
        if (is_null($record_history) || (strlen($record_history) == 0))
            return i("Xn806o|No version history avail...");
        
        $record_versions = $this->get_versions($record_history);
        $html = "";
        $last_record_version = [];
        foreach ($record_versions as $record_version) {
            // now interpret the version.
            if (strlen($record_version) > 5) {
                $parts = explode(";", $record_version, 4);
                $author_record = $this->find_record_matched($this->toolbox->users->user_table_name, 
                        [$this->toolbox->users->user_id_field_name => $parts[1]
                        ]);
                $version_int = intval($parts[0]);
                $author_name = ($author_record !== false) ? $parts[1] . " (" .
                         $author_record[$this->toolbox->users->user_firstname_field_name] . " " .
                         $author_record[$this->toolbox->users->user_lastname_field_name] . ")" : (($parts[1] ==
                         "0") ? i("KWQvhQ|Application") : i("VCdF1p|unknown"));
                $version_html = "<p>" . date($dfmt_dt, $parts[2]) . " - <b>V" . $version_int . "</b> - " .
                         i("nOchmJ|Author") . " " . $author_name;
                if ((strlen($restore_link) > 0) && ($version_int != count($record_versions)))
                    $version_html .= " - <a href='" . $restore_link . "&restore_version=" . $version_int . "'>" .
                             i("wiLfGW|Restore version V%1.", $parts[0]) . "</a>";
                $last_version = false;
                $version_html .= "</p>";
                $version_html .= "<table><tr><th>" . i("avyj0D|Field") . "</th><th>" . i("W1p3g6|Value") .
                         "</th></tr>\n";
                $fields = (isset($parts[3])) ? $this->toolbox->read_csv_line($parts[3])["row"] : [];
                $record_version = [];
                foreach ($fields as $field) {
                    $key_n_value = explode(":", $field, 2);
                    $record_version[$key_n_value[0]] = $key_n_value[1];
                    
                    $last_value = (isset($last_record_version[$key_n_value[0]])) ? $last_record_version[$key_n_value[0]] : false;
                    if ((strlen($key_n_value[1]) > 0) || ($last_value !== false)) {
                        if (($last_value == false) || (strcmp($key_n_value[1], $last_value) != 0)) {
                            $lmod_string = (strcasecmp("LastModified", $key_n_value[0]) == 0) ? " [" .
                                     date($dfmt_dt, intval(substr($key_n_value[1], 0, 10))) . "]" : "";
                            $version_html .= "<tr><td>" . $key_n_value[0] . "</td><td>" .
                                     str_replace("\n", "<br>", $key_n_value[1]) . $lmod_string . "</td></tr>\n";
                        }
                    }
                }
                $version_html .= "</table>\n";
                $last_record_version = $record_version;
                $html = $version_html . $html;
            }
        }
        return $html;
    }

    /**
     * Parse a history String and return the record history as array with per version an associative array
     * "author", "Version", "time", "record_version".
     * 
     * @param String $record_history
     *            The record history String.
     * @return array the array of all record versions
     */
    public function get_history_array (String $record_history)
    {
        // read the current history. Keep the version index. Create an empty array for insert
        if (is_null($record_history) || (strlen($record_history) == 0))
            return i("JTclLO|No version history avail...");
        
        $record_versions = $this->get_versions($record_history);
        $record_versions_array = [];
        foreach ($record_versions as $record_version) {
            // now interpret the version.
            if (strlen($record_version) > 5) {
                $parts = explode(";", $record_version, 4);
                $fields = $this->toolbox->read_csv_line($parts[3])["row"];
                $record_version = [];
                foreach ($fields as $field) {
                    $key_n_value = explode(":", $field, 2);
                    $record_version[$key_n_value[0]] = $key_n_value[1];
                }
                $version_array = ["version" => intval($parts[0]),"author" => $parts[1],
                        "time" => intval($parts[2]),"record_version" => $record_version
                ];
                $record_versions_array[] = $version_array;
            }
        }
        return $record_versions_array;
    }

/**
 * *************************** WRITE STRUCTURE INFORMATION *****************************
 * *************************** has been removed due to security concerns ***************
 */
}
