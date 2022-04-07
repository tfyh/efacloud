<?php

interface Tfyh_socket_listener
{

    /**
     * This function is called after each data base transaction, to all listeners for write
     * transaction only.
     *
     * @param String $tx_type
     *            the type of the transaction, e.g. "delete", "update", "insert".
     * @param String $tx_tablename
     *            The name of the affected table
     * @param array $tx_record
     *            the record used for the transaction.
     */
    public function on_socket_transaction (String $tx_type, String $tx_tablename, array $tx_record);
}

/**
 * class file for the Tfyh_socket class A utility class to connect to the data base. provides simple
 * get and put functions to read and write values.
 */
class Tfyh_socket
{

    /**
     * data base connection object
     */
    private $mysqli;

    /**
     * data base host. Do not use loclahost, but 127.0.0.1 instead.
     */
    private $db_host;

    /**
     * data base name
     */
    private $db_name;

    /**
     * data base user (account)
     */
    private $db_user;

    /**
     * data base user's password
     */
    private $db_pwd;

    /**
     * The common toolbox used.
     */
    private $toolbox;

    /**
     * an array of all socket listeners
     */
    private $listeners = [];

    /**
     * debug file name for sql-queries
     */
    public $sql_debug_file = "../log/debug_sql.log";

    /**
     * debug file name for sql-queries
     */
    private $debug_on;

    /**
     * $last_sql_executed reflects the last sql-command to executed in execute_and_log.
     */
    public $last_sql_executed = "";

    /**
     * Construct the socket. This initializes the data base connection. $cfg must contain the
     * appropriate values for: $cfg["db_host"], $cfg["db_accounts"], $cfg["db_name"] to get it
     * going.
     *
     * @param array $toolbox
     *            the basic utilities of the application.
     */
    function __construct (Tfyh_toolbox $toolbox)
    {
        $cfg = $toolbox->config->get_cfg();
        $this->db_host = $cfg["db_host"];
        $this->db_name = $cfg["db_name"];
        $this->db_user = $cfg["db_user"];
        $this->db_pwd = $cfg["db_up"];
        $this->mysqli = null;
        $this->toolbox = $toolbox;
        $this->debug_on = $toolbox->config->debug_level > 0;
    }

    /**
     * ******************** CONNECTION FUNCTONS AND RAW QUERY *****************************
     */
    
    /**
     * Connect to the data base as was configured. $cfg in constructor must contain the appropriate
     * values for: $cfg["db_host"], $cfg["db_user"], $cfg["db_up"], $cfg["db_name"] to get it going.
     * Returns error String on failure and true on success.
     */
    private function open ()
    {
        // do not connect, if connection is open.
        if (is_null($this->mysqli) || (! $this->mysqli->ping())) {
            // this will only connect, if $cfg contains the respective settings.
            $this->mysqli = new mysqli($this->db_host, $this->db_user, $this->db_pwd, $this->db_name);
        }
        if ($this->mysqli->connect_error) {
            return $this->mysqli->connect_error . "<br>db_user: " . $this->db_user . ".";
        } else {
            $this->mysqli->query("SET NAMES 'UTF8'");
            return true;
        }
    }

    /**
     * Test the connection. Will open the connection, if not yet done and try to query "SELECT 1" to
     * test the data base.
     *
     * @return true, if test statement succeeded; an error message, if not.
     */
    public function open_socket ()
    {
        // do not connect, if connection is open.
        if (is_null($this->mysqli) || (! $this->mysqli->ping()))
            // this will only connect with the correct settings in the settings_db file.
            $this->mysqli = new mysqli($this->db_host, $this->db_user, $this->db_pwd, $this->db_name);
        if ($this->mysqli->connect_error)
            return "Data base connection error: " . $this->mysqli->connect_error . ".";
        $this->mysqli->query("SET NAMES 'UTF8'");
        $ret = $this->mysqli->query("SELECT 1");
        // cf.
        // https://stackoverflow.com/questions/3668506/efficient-sql-test-query-or-validation-query-that-will-work-across-all-or-most
        return ($ret !== false) ? true : "Data base connection successful, but test statement 'SELECT 1' failed.";
    }

    /**
     * Add a write transaction listener to the socket.
     *
     * @param Tfyh_socket_listener $socket_listener            
     */
    public function add_listener (Tfyh_socket_listener $socket_listener)
    {
        $this->listeners[] = $socket_listener;
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
     * Raw data base query. Will open the connection, if not yet done. The SQL-command is logged and
     * the plain result returned. Values will still be UTF-8 encoded, as the data base shall be.
     *
     * @param String $sql_cmd            
     * @return mixed msqli-query result or false in case of data base connection failure.
     */
    public function query (String $sql_cmd)
    {
        if ($this->debug_on) {
            file_put_contents($this->sql_debug_file, 
                    date("Y-m-d H:i:s") . ": [tfyh_socket->query] " . $sql_cmd . "\n", FILE_APPEND);
        }
        $ret = $this->mysqli->query($sql_cmd);
        if ($this->debug_on && ($ret === false))
            file_put_contents($this->sql_debug_file, 
                    date("Y-m-d H:i:s") . ": [tfyh_socket->query error] " . $this->mysqli->error .
                             "\n", FILE_APPEND);
        return $ret;
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
            return "no last mysqli error available.";
    }

    /**
     * ********************** STANDARD QUERY EXECUTION AND CHANGE LOGGING **************************
     */
    
    /**
     * Delete all entries from chage log which are older than $days_to_keep * 24*3600 seconds. And
     * limits the log also to $records_to_keep records. Issues no warnings and no errors.
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
        $this->mysqli->query($sql_cmd);
        // now delete those which are just an overflow, e. g. by data base loading
        $sql_cmd = "SELECT `Time` FROM `" . $this->toolbox->config->changelog_name .
                 "` WHERE 1 ORDER BY `Time` DESC LIMIT " . ($records_to_keep + 2);
        $res = $this->mysqli->query($sql_cmd);
        if (intval($res->num_rows) > 0) {
            $row = $res->fetch_row();
            $max_change = $eldest_change;
            $rows = 0;
            while ($row) {
                $max_change = $row[0];
                $rows ++;
                $row = $res->fetch_row();
            }
        }
        if ($rows > $records_to_keep) {
            $sql_cmd = "DELETE FROM `" . $this->toolbox->config->changelog_name . "` WHERE `Time`<'" .
                     $max_change . "'";
            $this->mysqli->query($sql_cmd);
        }
    }

    /**
     * Get all entries from the change log, html formatted. This will not cleanse the log.
     */
    public function get_change_log ()
    {
        $sql_cmd = "SELECT `Author`, `Time`, `ChangedTable`, `ChangedID`, `Modification` FROM `" .
                 $this->toolbox->config->changelog_name . "` WHERE 1 ORDER BY `ID` DESC LIMIT 200";
        $res = $this->mysqli->query($sql_cmd);
        if (intval($res->num_rows) > 0)
            $row = $res->fetch_row();
        else
            return "No changes logged.";
        $ret = "";
        while ($row) {
            $ret .= "<p><b>Author:</b> " . $row[0] . "<br><b>Time:</b> " . $row[1] .
                     "<br><b>Table:</b> " . $row[2] . "<br><b>changed ID:</b> " . $row[3] .
                     "<br><b>Description:</b> " . $row[4] . "<br></p>\n";
            $row = $res->fetch_row();
        }
        return "<h3>Changes</h3><br>\n" . $ret;
    }

    /**
     * Execute an SQL command an log the result.
     *
     * @param String $appUserID
     *            the ID of the application user of the user who performs the statement. For change
     *            logging.
     * @param String $table_name
     *            the name of the table to be used.
     * @param String $sql_cmd
     *            Command to be executed. Values within command must be UTF-8 encoded.
     * @param String $changed_id
     *            ID of changed entry. In case of inert statement, set to "" to use autoincremented
     *            id of inserted
     * @param String $change_entry
     *            Change text to be logged. Values within change text must be UTF-8 encoded.
     * @param bool $return_insert_id
     *            set true to try to return the insert id upon success. Use only when called with
     *            INSERT INTO statement.
     * @return mixed an error statement in case of failure, the numeric ID of the inserted record in
     *         case of insert-to success, else "".
     */
    private function execute_and_log (String $appUserID, String $table_name, String $sql_cmd, 
            String $changed_id, String $change_entry, bool $return_insert_id)
    {
        // debug helper
        $this->last_sql_executed = $sql_cmd;
        if ($this->debug_on) {
            file_put_contents($this->sql_debug_file, 
                    date("Y-m-d H:i:s") . ":  [tfyh_socket->execute_and_log] " . $sql_cmd . " => ", 
                    FILE_APPEND);
        }
        // execute sql command. Connection must have been opened before.
        $ret = "";
        $res = $this->mysqli->query($sql_cmd);
        if ($res === false) {
            if ($this->debug_on)
                file_put_contents($this->sql_debug_file, "failed: " . $this->mysqli->error . "\n", 
                        FILE_APPEND);
            $ret .= "Database statement '" . htmlspecialchars(substr($sql_cmd, 0, 5000)) .
                     " ... failed. Error: '" . $this->mysqli->error . "'.";
        } else {
            if ($this->debug_on)
                file_put_contents($this->sql_debug_file, "successful.\n", FILE_APPEND);
            if ($return_insert_id == true)
                $ret = $this->mysqli->insert_id;
            else
                $ret = "";
            if (strlen($changed_id) == 0)
                $changed_id = $this->mysqli->insert_id;
            // write change log entry
            $sql_cmd = "INSERT INTO `" . $this->toolbox->config->changelog_name .
                     "` (`Author`, `Time`, `ChangedTable`, `ChangedID`, `Modification`) VALUES ('" .
                     $appUserID . "', CURRENT_TIMESTAMP, '" . $table_name . "', '" . $changed_id .
                     "', '" . str_replace("'", "\'", $change_entry) . "');";
            $tmpr = $this->mysqli->query($sql_cmd);
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
     *            the ID of the application user of the user who performs the statement. For change
     *            logging.
     * @param String $table_name
     *            the name of the table to be used.
     * @param array $record
     *            a named array with key = column name and value = values to be inserted. Values
     *            must be PHP native encoded Strings. Enclosed quotes "'" will be appropriately
     *            escaped for the SQL command.
     * @return array the same record, but user accss rights fields unset for a user who has not the
     *         user admin role.
     */
    private function protect_user_rights (int $appUserID, String $table_name, array $record)
    {
        if (strcasecmp($table_name, $this->toolbox->users->user_table_name) != 0)
            // this is no user data table: ok.
            return $record;
        $user = $this->find_record($table_name, $this->toolbox->users->user_id_field_name, 
                $appUserID);
        
        if (strcasecmp($user["Rolle"], $this->toolbox->users->useradmin_role) == 0)
            // user has user administration priviledge: ok.
            return $record;
        
        if (($user === false) && ! isset($record["ID"]) &&
                 (! isset($record["Rolle"]) ||
                 (strcasecmp($record["Rolle"], $this->toolbox->users->anonymous_role) == 0)) &&
                 (intval($record["Workflows"]) == 0) && (intval($record["Concessions"]) == 0))
            // if the $record["ID"] is not set, this is a registration. Allow it for no user rights.
            return $record;
        
        if (isset($record["ID"]) && intval($record["ID"]) != intval($user["ID"]))
            // this is a different users data and the user is no useradmin: forbidden
            return "User tried to modify other users data without useradmin role.";
        
        // check change of role, workflows, concessions, userID or account name
        if (isset($record["Rolle"]) && (strcasecmp($record["Rolle"], $user["Rolle"]) != 0))
            return "User tried to modify own access role without useradmin role.";
        if (isset($record["Workflows"]) &&
                 (intval($record["Workflows"]) != intval($user["Workflows"])))
            return "User tried to modify own Workflows role without useradmin role.";
        if (isset($record["Concessions"]) &&
                 (intval($record["Concessions"]) != intval($user["Concessions"])))
            return "User tried to modify own Concessions role without useradmin role.";
        if (isset($record[$this->toolbox->users->user_id_field_name]) && (intval(
                $record[$this->toolbox->users->user_id_field_name]) != intval(
                $user[$this->toolbox->users->user_id_field_name])))
            return "User tried to modify own user ID without useradmin role.";
        if (isset($record[$this->toolbox->users->user_account_field_name]) && (strcasecmp(
                $record[$this->toolbox->users->user_account_field_name], 
                $user[$this->toolbox->users->user_id_field_name]) != 0))
            return "User tried to modify own account name without useradmin role.";
        
        // All checks passed: ok.
        return $record;
    }

    /**
     * Insert a data record into the table with the given name. Checks the column names and removes
     * the field which do not fit. Does not check any key or value, but lets the data base decide on
     * what can be inserted and what not. If the table has a declared history field it adds the
     * version to the history.
     *
     * @param String $appUserID
     *            the ID of the application user of the user who performs the statement. For change
     *            logging.
     * @param String $table_name
     *            the name of the table to be used.
     * @param array $record
     *            a named array with key = column name and value = values to be inserted. Values
     *            must be PHP native encoded Strings. Enclosed quotes "'" will be appropriately
     *            escaped for the SQL command.
     * @return mixed ID of inserted record on success, else a String with warnings and error
     *         messages.
     */
    public function insert_into (String $appUserID, String $table_name, array $record)
    {
        $record = $this->protect_user_rights($appUserID, $table_name, $record);
        if (! is_array($record))
            return $record;
        
        // initialize history data field
        $historyField = (isset($this->toolbox->config->settings_tfyh["history"][$table_name])) ? $this->toolbox->config->settings_tfyh["history"][$table_name] : false;
        if ($historyField) {
            $excludeFields = (isset(
                    $this->toolbox->config->settings_tfyh["historyExclude"][$table_name])) ? $this->toolbox->config->settings_tfyh["historyExclude"][$table_name] : "";
            $record[$historyField] = $this->update_record_history(null, $record, $historyField, 
                    $excludeFields, $appUserID, 
                    $this->toolbox->config->settings_tfyh["maxversions"][$table_name]);
        }
        // create the sql command and the change log entry
        $sql_cmd = "INSERT INTO `" . $table_name . "` (`";
        $change_entry = "";
        foreach ($record as $key => $value) {
            $sql_cmd .= $key . "`, `";
            // no change logging for the record history. That would only create a lot of redundant
            // information
            if (! isset($this->toolbox->config->settings_tfyh["history"][$table_name]) || (strcasecmp(
                    $key, $this->toolbox->config->settings_tfyh["history"][$table_name]) != 0))
                $change_entry .= $key . '="' . str_replace("'", "\'", $value) . '", ';
        }
        // cut off last ", `";
        $sql_cmd = substr($sql_cmd, 0, strlen($sql_cmd) - 3);
        
        $change_entry = substr($change_entry, 0, strlen($change_entry) - 2);
        $sql_cmd .= ") VALUES ('";
        foreach ($record as $key => $value)
            $sql_cmd .= str_replace("'", "\'", $value) . "', '";
        // cut off last ", '";
        $sql_cmd = substr($sql_cmd, 0, strlen($sql_cmd) - 3);
        $sql_cmd .= ")";
        
        // set last write access timestamp, if used.
        if (file_exists("../log/lwa"))
            file_put_contents("../log/lwa/" . $appUserID, strval(time()));
        
        // execute sql command and log execution.
        $res = $this->execute_and_log($appUserID, $table_name, $sql_cmd, "", $change_entry, true);
        // trigger listeners
        if (count($this->listeners) > 0) {
            foreach ($this->listeners as $listener)
                $listener->on_socket_transaction("insert", $table_name, $record);
        }
        return $res;
    }

    /**
     * Initialize or update the history field of a data record.
     *
     * @param array $current_record
     *            the current data record. Set to null for the insert into operation. If not null it
     *            must include the data field $current_record[$history_field_name]. If it does not,
     *            an empty String is returned.
     * @param array $new_record
     *            the new data record. May be incomplete. If it contains the data field
     *            $record[$history_field_name] that field will be ignored.
     * @param String $history_field_name
     *            the name of the data field which contains the record history.
     * @param String $history_field_name
     *            the name of the data field which contains the lists of fields to exclude from the
     *            record history.
     * @return the new JSON encoded String for the history field
     */
    private function update_record_history (array $current_record = null, array $new_record, 
            String $history_field_name, String $history_field_exclude, int $appUserID, 
            int $max_versions)
    {
        // There is a current record, but without a history entry
        if (! is_null($current_record) && (! isset($current_record[$history_field_name]) ||
                 (strlen($current_record[$history_field_name]) < 5))) {
            // start history, first entry at all. Because the history may not have been initialized
            // from the
            // very beginning, this will also be used for updates.
            $history = "1;" . $appUserID . ";" . time() . ";";
            foreach ($current_record as $fieldname => $value) {
                $is_history_field = ($fieldname == $history_field_name);
                $is_exclude_field = (strpos($history_field_exclude, "." . $fieldname . ".") !== false);
                if (! $is_history_field && ! $is_exclude_field) {
                    $new_value = strval($value);
                    if (strlen($new_value) > 1024)
                        $new_value = substr($new_record[$fieldname], 0, 1020) . "...";
                    $history .= $this->toolbox->encode_entry_csv($fieldname . ":" . $new_value) . ";";
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
        if (count($record_versions) >= $max_versions)
            $record_versions = array_splice($record_versions, 1 - $max_versions);
        
        // add missing version numbers to support previously used history field text encoding.
        $last_version_number = 0;
        for ($i = 0; $i < count($record_versions); $i ++) {
            $record_version = $record_versions[$i];
            if (strlen(trim($record_version)) > 0) {
                $version_number_str = (strpos($record_version, ";") !== false) ? substr(
                        $record_version, 0, strpos($record_version, ";")) : "";
                $version_number_int = (is_numeric($version_number_str)) ? intval(
                        $version_number_str) : 0;
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
        foreach ($new_record as $fieldname => $value) {
            $is_history_field = ($fieldname == $history_field_name);
            $is_exclude_field = (strpos($history_field_exclude, "." . $fieldname . ".") !== false);
            if (! $is_history_field && ! $is_exclude_field) {
                $is_changed = isset($current_record[$fieldname]) &&
                         ($value !== $current_record[$fieldname]);
                if ($is_changed) {
                    $any_changes = true;
                    $new_value = strval($value);
                    if (strlen($new_value) > 1024)
                        $new_value = substr($new_record[$fieldname], 0, 1020) . "...";
                    $new_version .= $this->toolbox->encode_entry_csv($fieldname . ":" . $new_value) .
                             ";";
                }
            }
        }
        $new_version = substr($new_version, 0, strlen($new_version) - 1);
        
        // add the new version, if there were changes, to the history array and return it.
        if ($any_changes)
            $record_versions[] = $new_version;
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
     *            the key to be matched. It may containe one or more fields. Values must be UTF-8
     *            encoded Strings.
     * @param String $condition
     *            the condition for $key and $value, e. g. "!=" for not equal. Set to "" to get
     *            every record or set "NULL" to get all records with the value being NULL. Set "IN"
     *            with the value being the appropraite formatted array String to get values matching
     *            the given array. You can use a condition for each matching field, if so wished, by
     *            listing them comma separated, e.g. >,= for two fields if which the first shall be
     *            greater, the second equal to the respective values. If more matching values are
     *            provided, than conditions, the last condition is taken for all extra matching
     *            fields.
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
        $wherekeyis = substr($wherekeyis, 0, strlen($wherekeyis) - 5);
        return $wherekeyis;
    }

    /**
     * Little helper to create the matched record for logging based on the $matching key.
     *
     * @param array $matching
     *            the key to be matched. It may containe one or more fields. Values must be PHP
     *            native encoded Strings.
     */
    private function matched_record (array $matching)
    {
        $matched_record = "";
        foreach ($matching as $key => $value) {
            // singular numbers are not quoted (legacy reasons)
            if ((count($matching) == 1) && is_numeric($value))
                $matched_record = $value;
            else
                $matched_record .= $key . "=\'" . strval($value) . "\', ";
        }
        if (strlen($matched_record) == 0)
            return "[undefined]";
        if (strrpos($matched_record, ", ") !== false)
            $matched_record = substr($matched_record, 0, strlen($matched_record) - 2);
        return $matched_record;
    }

    /**
     * Convenience shortcut for update_record_matched with a primary key of name ID
     */
    public function update_record (String $appUserID, String $table_name, array $record)
    {
        return $this->update_record_matched($appUserID, $table_name, 
                ["ID" => $record["ID"]
                ], $record);
    }

    /**
     * Update a record providing an array with $array[ column name ] = value. The record is matched
     * using the $match_key column and the $record[$match_key] value.
     *
     * @param String $appUserID
     *            the ID of the application user of the user who performs the statement. For change
     *            logging.
     * @param String $table_name
     *            the name of the table to be used.
     * @param array $matching_keys
     *            the keys to be matched. It may containe one or more field names as indexed array.
     *            Values are part of the record provided. Values must be UTF-8 encoded Strings.
     * @param array $record
     *            a named array with key = column name and value = values to be used for update.
     *            Must contain an "ID" field to identify the record to update. Values must be PHP
     *            native encoded. Enclosed quotes "'" will be appropriately escaped for the SQL
     *            command. record fields will be UTF-8 decoded.
     * @return an error statement in case of failure, else "".
     */
    public function update_record_matched (String $appUserID, String $table_name, 
            array $matching_keys, array $record)
    {
        $record = $this->protect_user_rights($appUserID, $table_name, $record);
        if (! is_array($record))
            return $record;
        
        // update history data field
        $prev_rec = $this->find_record_matched($table_name, $matching_keys);
        if ($prev_rec === false)
            return "Error updating record in $table_name with key: " . json_encode($matching_keys);
        $historyField = (isset($this->toolbox->config->settings_tfyh["history"][$table_name])) ? $this->toolbox->config->settings_tfyh["history"][$table_name] : false;
        if ($historyField) {
            $excludeFields = (isset(
                    $this->toolbox->config->settings_tfyh["historyExclude"][$table_name])) ? $this->toolbox->config->settings_tfyh["historyExclude"][$table_name] : "";
            $record[$historyField] = $this->update_record_history($prev_rec, $record, $historyField, 
                    $excludeFields, $appUserID, 
                    $this->toolbox->config->settings_tfyh["maxversions"][$table_name]);
        }
        $change_entry = "updated: ";
        
        // create SQL command and change log entry.
        $sql_cmd = "UPDATE `" . $table_name . "` SET ";
        foreach ($record as $key => $value) {
            // check empty values. 1a. If previous and current are empty, skip the field.
            $skip_update = (! isset($prev_rec[$key]) || (strlen($prev_rec[$key]) == 0)) && (! isset(
                    $value) || (strlen($value) == 0));
            // check mismatching fields. 1b. If the current record has an extra field, drop it.
            $skip_update = $skip_update || ! array_key_exists($key, $prev_rec);
            // skip matching keys, they are anyway equal
            foreach ($matching_keys as $matching_key)
                $skip_update = $skip_update || (strcasecmp($key, $matching_key) == 0);
            // check empty values. 2. If previous was not empty, and the record was numeric or a
            // date, try NULL as value
            if (! $skip_update && (! $value && (strlen($value) == 0)) && (is_numeric(
                    $prev_rec[$key]) || is_numeric(strtotime($prev_rec[$key]))))
                $sql_cmd .= "`" . $key . "` = NULL,";
            else 
                if (! $skip_update)
                    $sql_cmd .= "`" . $key . "` = '" . str_replace("'", "\'", $value) . "',";
            // the change entry shall neither contain the keys, nor the record history to
            // prevent from too much redundant information
            if (! $skip_update && (strcmp($value, $prev_rec[$key]) !== 0) && (! isset(
                    $this->toolbox->config->settings_tfyh["history"][$table_name]) || (strcasecmp(
                    $key, $this->toolbox->config->settings_tfyh["history"][$table_name]) != 0)))
                $change_entry .= $key . ': "' . str_replace("'", "\'", 
                        $prev_rec[$key] . '"=>"' . str_replace("'", "\'", $value)) . '", ';
        }
        $sql_cmd = substr($sql_cmd, 0, strlen($sql_cmd) - 1);
        $change_entry = substr($change_entry, 0, strlen($change_entry) - 2);
        $sql_cmd .= " " . $this->clause_for_wherekeyis($table_name, $matching_keys, "=");
        
        // set last write access timestamp, if used.
        if (file_exists("../log/lwa"))
            file_put_contents("../log/lwa/" . $appUserID, strval(time()));
        
        // execute sql command and log execution.
        $result = $this->execute_and_log($appUserID, $table_name, $sql_cmd, 
                $this->matched_record($matching_keys), $change_entry, false);
        // trigger listeners
        if (count($this->listeners) > 0) {
            foreach ($this->listeners as $listener)
                $listener->on_socket_transaction("update", $table_name, $record);
        }
        return $result;
    }

    /**
     * Convenience shortcut for delete_record_matched with a primary key of name ID
     */
    public function delete_record (String $appUserID, String $table_name, String $id)
    {
        return $this->delete_record_matched($appUserID, $table_name, ["ID" => $id
        ]);
    }

    /**
     * Delete a record. The record is matched using the $match_key column and the
     * $record[$match_key] value. This deletes the entire record from the data base, including its
     * history. It may only be restored manually using the change log.
     *
     * @param String $appUserID
     *            the ID of the application user of the user who performs the statement. For change
     *            logging.
     * @param String $table_name
     *            the name of the table to be used.
     * @param array $matching
     *            the key to be matched. It may containe one or more fields. Values must be PHP
     *            native encoded Strings.
     * @return String an error statement in case of failure, else "".
     */
    public function delete_record_matched (String $appUserID, String $table_name, array $matching)
    {
        // get previous recors to log change
        $prev_rec = $this->find_record_matched($table_name, $matching);
        if ($prev_rec === false)
            return "Record to delete was not found.";
        $delete_entry = "deleted: ";
        
        // create SQL command and change log entry.
        foreach ($prev_rec as $key => $value) {
            $delete_entry .= $key . "='" . str_replace('"', '\"', $prev_rec[$key]) . "', ";
        }
        // deletions will not change the last modified time stamp, because they
        // delete the data anyway.
        $delete_entry = substr($delete_entry, 0, strlen($delete_entry) - 2);
        // ID used is **ID**
        $sql_cmd .= "DELETE FROM `" . $table_name . "` " .
                 $this->clause_for_wherekeyis($table_name, $matching, "=");
        
        // set last write access timestamp, if used.
        if (file_exists("../log/lwa"))
            file_put_contents("../log/lwa/" . $appUserID, strval(time()));
        
        // execute sql command and log execution.
        $result = $this->execute_and_log($appUserID, $table_name, $sql_cmd, 
                $this->matched_record($matching), $delete_entry, false);
        // trigger listeners
        if (count($this->listeners) > 0) {
            foreach ($this->listeners as $listener)
                $listener->on_socket_transaction("delete", $table_name, $prev_rec);
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
     * Find the first record as associative array of key => value matching the $matching key.
     * Returns false, if the record key could not be matched or any other error occurred.
     *
     * @param String $table_name
     *            the name of the table to be used.
     * @param array $matching
     *            the key to be matched (Values in PHP native encoding). It may contain one or more
     *            fields. Set to [] to get all.
     * @return array first record found as associative array of key => value. False in case of
     *         either connection error or no match. Values are UTF8 encoded.
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
     * Find all records as indexed array of records, each as associative array of key => value
     * matching the $matching key. Returns false, if the record key could not be matched or any
     * other error occurred.
     *
     * @param String $table_name
     *            the name of the table to be used.
     * @param array $matching
     *            the key to be matched. It may contain one or more fields. Values must be PHP
     *            native encoded Strings. Set to [] to get all.
     * @param int $max_rows
     *            the maximum number of rows to be returned.
     * @return array of records, each as associative array of key => value. False in case of either
     *         connection error or no match. Values are UTF8 encoded.
     */
    public function find_records_matched (String $table_name, array $matching, int $max_rows)
    {
        return $this->find_records_sorted_matched($table_name, $matching, $max_rows, 
                ((count($matching) == 0) ? "" : "="), "", true);
    }

    /**
     * Convenience shortcut for find_records_sorted_matched with a single matching field
     */
    public function find_records_sorted (String $table_name, String $key, String $value, 
            int $max_rows, String $condition, String $sort_key, bool $sort_ascending)
    {
        return $this->find_records_sorted_matched($table_name, [$key => $value
        ], $max_rows, $condition, $sort_key, $sort_ascending);
    }

    /**
     * Find all records as indexed array of records, each as associative array of key => value
     * matching the $matching key. Sort them in the requested order. Returns false, if the value is
     * not found or any other error occurred.
     *
     * @param String $table_name
     *            the name of the table to be used.
     * @param array $matching
     *            the key to be matched. It may contain one or more fields. Values must be PHP
     *            native encoded Strings. Set to [] to get all.
     * @param int $max_rows
     *            the maximum number of rows to be returned.
     * @param String $condition
     *            the condition for $key and $value, e. g. "!=" for not equal. Set to "" to get
     *            every record or set "NULL" to get all records with the value being NULL. Set "IN"
     *            with the value being the appropraite formatted array String to get values matching
     *            the given array. You can use a condition for each matching field, if so wished, by
     *            listing them comma separated, e.g. >,= for two fields if which the first shall be
     *            greater, the second equal to the respective values. If more matching values are
     *            provided, than conditions, the last condition is taken for all extra matching
     *            fields.
     * @param String $sort_key
     *            the name of the column to sort for. Set "" (previous versions: false, may also
     *            work) to do no sorting. Can be a list, comma separated. Precede by a '#' to sort
     *            as numbers, e.g. "#EntryId".
     * @param bool $sort_ascending
     *            set to true to sort in ascending order, false to sort in descending order.
     * @param bool $start_row
     *            (default = 0) set a value > 0 to start not with the first row. Use it for getting
     *            chunks rather than all.
     * @return array of records, each being an array of key = column name and value = value. False
     *         in case of either connection error or no match. Values are UTF8 encoded.
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
            $col_indicators = substr($col_indicators, 0, strlen($col_indicators) - 2);
        // compile command parts: rows to choose
        $where_string = (strlen($condition) == 0) ? 'WHERE 1 ' : $this->clause_for_wherekeyis(
                $table_name, $matching, $condition);
        // compile command parts: sorting of result
        $sort_str = "";
        if ($sort_key && strlen($sort_key) > 0) {
            $sort_way = ($sort_ascending) ? "ASC" : "DESC";
            $sort_cols = explode(",", $sort_key);
            $sort_str = " ORDER BY ";
            foreach ($sort_cols as $sort_col) {
                if (substr($sort_col, 0, 1) == '#')
                    $sort_str .= "CAST(`" . $table_name . "`.`" . substr($sort_col, 1) .
                             "` AS UNSIGNED) " . $sort_way . ", ";
                else
                    $sort_str .= "`" . $table_name . "`.`" . $sort_col . "` " . $sort_way . ", ";
            }
            $sort_str = substr($sort_str, 0, strlen($sort_str) - 2);
        }
        // compile command parts: limit or chunk of returned rows
        $limit_string = " LIMIT " . $start_row . "," . $max_rows;
        
        // compile command and execute
        $sql_cmd = "SELECT " . $col_indicators . " FROM `" . $table_name . "` " . $where_string .
                 $sort_str . $limit_string;
        if ($this->debug_on)
            file_put_contents($this->sql_debug_file, 
                    date("Y-m-d H:i:s") . ": [tfyh_socket->find_records_sorted_matched] " . $sql_cmd .
                             "\n", FILE_APPEND);
        
        $this->last_sql_executed = $sql_cmd;
        if ($this->debug_on) {
            file_put_contents($this->sql_debug_file, date("Y-m-d H:i:s") . ": " . $sql_cmd . " => ", 
                    FILE_APPEND);
        }
        // retrieve data from data base
        $res = $this->mysqli->query($sql_cmd);
        if ($this->debug_on) {
            if ($res === false)
                file_put_contents($this->sql_debug_file, "failed: " . $this->mysqli->error . "\n", 
                        FILE_APPEND);
            else
                file_put_contents($this->sql_debug_file, "successful.\n", FILE_APPEND);
        }
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
        // return result
        return $sets;
    }

    /**
     * Count the number of records within a table
     *
     * @param String $tablename
     *            the table to look into
     * @param array $matching
     *            the key to be matched. It may contain one or more fields. Values must be PHP
     *            native encoded Strings. Set to null or omit to get all.
     * @param String $condition
     *            the condition for $key and $value, e. g. "!=" for not equal. Set to "" to get
     *            every record. You can use a condition for each matching field, if so wisched, by
     *            listing them comma separated, e.g. >,= for two fields if which the first shall be
     *            greater, the second equal to the respective values. If more matching values are
     *            provided, than conditions, the last condition is taken for all extra matching
     *            fields. Ignored if $matching == null.
     * @return int the count of records in the table
     */
    public function count_records (String $tablename, array $matching = null, String $condition = "")
    {
        // now retrieve all column names
        $sql_cmd = ($matching == null) ? "SELECT COUNT(*) FROM `" . $tablename . "`;" : "SELECT COUNT(*) FROM `" .
                 $tablename . "` " . $this->clause_for_wherekeyis($tablename, $matching, $condition);
        $this->last_sql_executed = $sql_cmd;
        $res = $this->mysqli->query($sql_cmd);
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
        $res = $this->mysqli->query($sql_cmd);
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
     * ***************************** GET AND IMPORT FsULL TABLES ******************************
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
            return "no_columns_found_in_" . $table_name;
        
        $csv = "";
        foreach ($cols as $col)
            $csv .= $col . ";";
        $csv = substr($csv, 0, strlen($csv) - 1) . "\n";
        
        $col_indicators = "";
        foreach ($cols as $col_name)
            $col_indicators .= "`" . $col_name . "`, ";
        $col_indicators = substr($col_indicators, 0, strlen($col_indicators) - 2);
        $sql_cmd = "SELECT " . $col_indicators . " FROM `" . $table_name . "` WHERE 1";
        $res = $this->mysqli->query($sql_cmd);
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
                $csv = substr($csv, 0, strlen($csv) - 1);
            $csv .= "\n";
            $row = $res->fetch_row();
        }
        $res->free();
        return $csv;
    }

    /**
     * Get a table as PHP array.
     *
     * @param String $table_name
     *            the name of the table to be used.
     * @param String $filter_and_order
     *            a String which will be added to the SQL statement containing the WHERE and ORDER
     *            BY clauses, e.g. "WHERE 1 ORDER BY `Funktionen`.`efaCloudUserID` DESC".
     * @return the full table as array of rows, each row being an array of $entries as $key =>
     *         $value with $key being the respective column name.
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
        $col_indicators = substr($col_indicators, 0, strlen($col_indicators) - 2);
        $sql_cmd = "SELECT " . $col_indicators . " FROM " . $table_name . " " . $filter_and_order;
        $res = $this->mysqli->query($sql_cmd);
        $row = $res->fetch_row();
        $rows = [];
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
        return $rows;
    }

    /**
     * Import a csv file into a table or delete table records (provide single column csv with IDs
     * only). The csv-file must use the ';' separator and '"' text delimiters. It must contain a
     * headline with column names that are literally identical to the mySQL internal column names.
     * All data records must be of the same length as the header line, not more, not less. If a data
     * record does not comply, it will not be imported. The first column must be 'ID'. If this is
     * not the case, no data will be imported at all. For data records with an existing 'ID' all
     * provided record fields will be replaced, i. e. data will be deleted, if the respective field
     * is empty. For data records with an empty 'ID' the 'ID' will be auto generated. In this case,
     * and if the provided 'ID' is not yet existing, a new table record is inserted into the table.
     * All changes will be logged, as if they had been made manually.
     *
     * @param String $appUserID
     *            the ueser ID of the user who performs the statement. For change logging.
     * @param String $table_name
     *            name of table into which the data shal be loaded. Must be exactly a name of a
     *            database table.
     * @param String $csv_file_path
     *            path to file to be imported or to single column list of IDs which shall be
     *            deleted.
     * @param bool $verify_only
     *            set true to only see what would be done
     * @param String $idname
     *            Optional, default = "ID". Set it to the field name of the ID to use for
     *            comparison.
     * @return array the import result
     */
    public function import_table_from_csv (String $appUserID, String $table_name, 
            String $csv_file_path, bool $verify_only, String $idname = "ID")
    {
        // read table
        $table_read = $this->toolbox->read_csv_array($csv_file_path);
        if ($table_read === [])
            return "#Error: Import aborted because of a syntax error in the import file.";
        
        // check, if all column names exist and if column name "ID" exists
        $column_names = $this->get_column_names($table_name);
        $columns_not_matched = "";
        $column_ID_exists = false;
        foreach ($table_read[0] as $column => $entry) {
            if (strcmp($column, $idname) == 0)
                $column_ID_exists = true;
            $this_column_exists = false;
            foreach ($column_names as $column_name)
                if (strcmp($column, $column_name) == 0)
                    $this_column_exists = true;
            if (! $this_column_exists)
                $columns_not_matched .= $column . ", ";
        }
        if (! $column_ID_exists)
            return "#Error: Import aborted. Import file must have a column " . $idname . ".";
        if (strlen($columns_not_matched) > 0)
            return " // Error: Import aborted because the following columns of the import file are missing in
                     // the target table: " . $columns_not_matched;
        
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
                $sql_cmd = "SELECT * FROM `" . $table_name . "` WHERE `" . $idname . "` = '" . $id .
                         "'";
                $res = $this->mysqli->query($sql_cmd);
                $update_record = intval($res->num_rows) > 0;
                if ($update_record) {
                    // update record now
                    $result .= "Update " . $idname . " " . $id . " mit: ";
                    foreach ($record as $key => $entry) {
                        $result .= htmlspecialchars($entry) . ";";
                        // $this->update_record expects utf-8 encoded Strings
                        $record[$key] = $entry;
                    }
                    $result .= "<br />";
                    if (! $verify_only)
                        $result .= $this->update_record_matched($appUserID, $table_name, 
                                [$idname => $id
                                ], $record) . "<br />";
                } else {
                    $result .= "Skip missing " . $idname . " " . $id . "<br />";
                }
            } else {
                if ($delete_entries) {
                    // delete record now
                    $result .= "Deleting " . $idname . " " . $id . ". Record with current values: ";
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
                    $result .= "Inserting: ";
                    foreach ($record as $key => $entry) {
                        $result .= htmlspecialchars($entry) . ";";
                        // $this->insert_into expects utf-8 encoded Strings
                        $record[$key] = $entry;
                    }
                    $result .= "<br />";
                    if (! $verify_only) {
                        // remove the empty "ID" String to ensure it is
                        // autoincremented
                        unset($record[$idname]);
                        $insert_into_res = $this->insert_into($appUserID, $table_name, $record);
                        // in case of success $insert_into_res will be the id of
                        // the inserted
                        // record.
                        if (! is_numeric($insert_into_res))
                            $result .= $this->insert_into($appUserID, $table_name, $record);
                    }
                }
            }
        }
        
        // return result.
        $result = ($verify_only) ? "<b>The following changes are to be carried out with the import:</b><br />" .
                 $result : "<b>The following changes have been carried out with the import:</b><br />" .
                 $result;
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
     * @return data base server version
     */
    public function get_server_info ()
    {
        return "Client info = " . $this->mysqli->client_info . ", Server info = " .
                 $this->mysqli->server_info . ", Server version = " . $this->mysqli->server_version;
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
                 $this->db_name . "' AND `TABLE_NAME`='" . $table_name .
                 "' ORDER BY ORDINAL_POSITION";
        
        $result = $this->mysqli->query($sql_cmd);
        // put all values to the array, with numeric autoincrementing key.
        $ret = [];
        if (! is_array($result) && ! is_object($result))
            return $ret;
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
     * @return array numbered array of column types including size (if not 0) like varchar(192) or
     *         false, if data base connection fails.
     */
    public function get_column_types (String $table_name)
    {
        // now retrieve all column names
        $sql_cmd = "SELECT `DATA_TYPE`, `CHARACTER_MAXIMUM_LENGTH` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA`='" .
                 $this->db_name . "' AND `TABLE_NAME`='" . $table_name .
                 "' ORDER BY ORDINAL_POSITION";
        $res = $this->mysqli->query($sql_cmd);
        // put all values to the array, with numeric autoincrementing key.
        $ret = [];
        $column_types = $res->fetch_array();
        while ($column_types) {
            // the fetch_array function is an iterator
            $ret[] = (strlen($column_types[1]) > 0) ? $column_types[0] . " (" . $column_types[1] .
                     ")" : $column_types[0];
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
     *            set true to return the indexes as associative array, name => description, false to
     *            get a String array of the indexed column names.
     * @return array of indexes or false, if data base connection fails.
     */
    public function get_indexes (String $table_name, bool $include_description)
    {
        $index_response_columns = explode(",", 
                "Table,Non_unique,Key_name,Seq_in_index,Column_name,Collation,Cardinality," .
                         "Sub_part,Packed,Null,Index_type,Comment,Index_comment,Visible,Expression");
        $sql_cmd = "SHOW KEYS FROM " . $table_name;
        $indexes = [];
        $res = $this->query($sql_cmd);
        if (($res != false) && ($res->num_rows > 0)) {
            $row = $res->fetch_row();
            while ($row) {
                $c = 0;
                foreach ($index_response_columns as $index_response_column) {
                    if (strpos(",Non_unique,Key_name,Column_name,Null", 
                            $index_response_column . ",") !== false) {
                        $index[$index_response_column] = $row[$c];
                    }
                    $c ++;
                }
                $index_description = "@" . $index["Column_name"] . " ";
                if (isset($index["Non_unique"]) && (intval($index["Non_unique"]) == 0))
                    $index_description .= "UNIQUE ";
                if (isset($index["Null"]) && (strcasecmp($index["Null"], "YES") == 0))
                    $index_description .= "NULLABLE ";
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
     * Get all indexes as array with $array[column_name] = index description.
     *
     * @param String $table_name
     *            the name of the table to be used.
     */
    public function get_autoincrements (String $table_name)
    {
        // SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'efaCloudUsers' AND EXTRA
        // like
        // '%auto_increment%'
        $sql_cmd = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '" . $table_name .
                 "' AND EXTRA like '%auto_increment%'";
        $autoincrements = [];
        $res = $this->query($sql_cmd);
        if (($res != false) && ($res->num_rows > 0)) {
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
        $res = $this->mysqli->query($sql_cmd);
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
     * Get all versions of a history entry by splitting it into lines and combining those lines,
     * which end within a quoted entry.
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
     * Parse a history String and return the record history as html tables, each version being a
     * table.
     *
     * @param String $record_history
     *            The record history String.
     */
    public function get_history_html (String $record_history)
    {
        // read the current history. Keep the version index. Create an empty array for insert
        if (is_null($record_history) || (strlen($record_history) == 0))
            return "No version history available.";
        
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
                $author_name = ($author_record !== false) ? $parts[1] . " (" .
                         $author_record[$this->toolbox->users->user_firstname_field_name] . " " .
                         $author_record[$this->toolbox->users->user_lastname_field_name] . ")" : (($parts[1] ==
                         "0") ? "efaCloud Server" : "unbekannt");
                $version_html = "<p>" . date("d.m.Y H:i:s", $parts[2]) . " - <b>V" . $parts[0] .
                         "</b> - Autor " . $author_name . "</p>";
                $version_html .= "<table><tr><th>Feld</th><th>Wert</th></tr>\n";
                $fields = $this->toolbox->read_csv_line($parts[3])["row"];
                $record_version = [];
                foreach ($fields as $field) {
                    $key_n_value = explode(":", $field, 2);
                    $record_version[$key_n_value[0]] = $key_n_value[1];
                    if (strlen($key_n_value[1]) > 0) {
                        if (strcmp($key_n_value[1], $last_record_version[$key_n_value[0]]) != 0) {
                            $lmod_string = (strcasecmp("LastModified", $key_n_value[0]) == 0) ? " [" .
                                     date("d.m.Y H:i:s", intval(substr($key_n_value[1], 0, 10))) .
                                     "]" : "";
                            $version_html .= "<tr><td>" . $key_n_value[0] . "</td><td>" .
                                     str_replace("\n", "<br>", $key_n_value[1]) . $lmod_string .
                                     "</td></tr>\n";
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
 * *************************** WRITE STRUCTURE INFORMATION *****************************
 * *************************** has been removed due to security concerns ***************
 */
}
