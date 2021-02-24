<?php

/**
 * class file for the Socket class A utility class to connect to the data base. provides simple get and put
 * functions to read and write values.
 */
class Socket
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
     * Construct the socket. This initializes the data base connection. $cfg must contain the appropriate
     * values for: $cfg["db_host"], $cfg["db_accounts"], $cfg["db_name"] to get it going.
     * 
     * @param array $toolbox
     *            the basic utilities of the application.
     */
    function __construct (Toolbox $toolbox)
    {
        $cfg = $toolbox->config->get_cfg();
        $this->db_host = $cfg["db_host"];
        $this->db_name = $cfg["db_name"];
        $this->db_user = $cfg["db_user"];
        $this->db_pwd = $cfg["db_up"];
        $this->mysqli = null;
        $this->toolbox = $toolbox;
    }

    /**
     * ******************** CONNECTION FUNCTONS AND RAW QUERY *****************************
     */
    
    /**
     * Connect to the data base as was configured. $cfg in constructor must contain the appropriate values
     * for: $cfg["db_host"], $cfg["db_user"], $cfg["db_up"], $cfg["db_name"] to get it going. Returns error
     * String on failure and true on success.
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
     * Test the connection. Will open the connection, if not yet done and try to query "SELECT 1" to test the
     * data base.
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
     * simple wrapper on mysqli->close().
     */
    public function close ()
    {
        if (($this->mysqli != null) && ($this->mysqli->ping()))
            $this->mysqli->close();
        $this->mysqli = null;
    }

    /**
     * Raw data base query. Will open the connection, if not yet done. The SQL-command is logged and the plain
     * result returned. Values will still be UTF-8 encoded, as the data base shall be.
     * 
     * @param String $sql_cmd            
     * @return mixed msqli-query result or false in case of data base connection failure.
     */
    public function query (String $sql_cmd)
    {
        if (! file_exists("../log/queries.txt"))
            file_put_contents("../log/queries.txt", $sql_cmd . "\n");
        else
            file_put_contents("../log/queries.txt", $sql_cmd . "\n", FILE_APPEND);
        
        $ret = $this->mysqli->query($sql_cmd);
        return $ret;
    }

    /**
     * ********************** STANDARD QUERY EXECUTION AND CHANGE LOGGING **************************
     */
    
    /**
     * Delete all entries from chage log which are older than $days_to_keep * 24*3600 seconds. And limits the
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
            $ret .= "<p><b>Author:</b> " . $row[0] . "<br><b>Time:</b> " . $row[1] . "<br><b>Table:</b> " .
                     $row[2] . "<br><b>changed ID:</b> " . $row[3] . "<br><b>Description:</b> " . $row[4] .
                     "<br></p>\n";
            $row = $res->fetch_row();
        }
        return "<h3>Changes</h3><br>\n" . $ret;
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
     *            Change text to be logged. Values within change text must be UTF-8 encoded.
     * @param bool $return_insert_id
     *            set true to try to return the insert id upon success. Use only when called with INSERT INTO
     *            statement.
     * @return mixed an error statement in case of failure, the numeric ID of the inserted record in case of
     *         insert-to success, else "".
     */
    private function execute_and_log (String $appUserID, String $table_name, String $sql_cmd, 
            String $changed_id, String $change_entry, bool $return_insert_id)
    {
        // execute sql command. Connection must have been opened before.
        $ret = "";
        $res = $this->mysqli->query($sql_cmd);
        if ($res === false) {
            $ret .= "database statement '" . htmlspecialchars(substr($sql_cmd, 0, 1000)) . " ... ' failed: '" .
                     $this->mysqli->error . "'.";
        } else {
            if ($return_insert_id == true)
                $ret = $this->mysqli->insert_id;
            else
                $ret = "";
            if (strlen($changed_id) == 0)
                $changed_id = $this->mysqli->insert_id;
            // write change log entry
            $sql_cmd = "INSERT INTO `" . $this->toolbox->config->changelog_name .
                     "` (`Author`, `Time`, `ChangedTable`, `ChangedID`, `Modification`) VALUES ('" . $appUserID .
                     "', CURRENT_TIMESTAMP, '" . $table_name . "', '" . $changed_id . "', '" .
                     str_replace("'", "\'", $change_entry) . "');";
            $tmpr = $this->mysqli->query($sql_cmd);
        }
        return $ret;
    }

    /**
     * **************** STANDARD DB WRITE QUERIES: INSERT; UPDATE; DELETE *********************
     */
    
    /**
     * Insert a data record into the table with the given name. Checks the column names and removes the field
     * which do not fit. Does not check any key or value, but lets the data base decide on what can be
     * inserted and what not.
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
        $change_entry = "inserted: ";
        $sql_cmd = "INSERT INTO `" . $table_name . "` (`";
        foreach ($record as $key => $value) {
            $sql_cmd .= $key . "`, `";
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
        // execute sql command and log execution.
        $res = $this->execute_and_log($appUserID, $table_name, $sql_cmd, "", $change_entry, true);
        return $res;
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
     *            the condition for $key and $value, e. g. "!=" for not equal. Set to "" to get every record.
     *            You can use a condition for each matching field, if so wisched, by listing them comma
     *            separated, e.g. >,= for two fields if which the first shall be greater, the second equal to
     *            the respective values. If more matching values are provided, than conditions, the last
     *            condition is taken for all extra matching fields.
     */
    private function clause_for_wherekeyis (String $table_name, array $matching, String $condition)
    {
        if (strlen($condition) == 0)
            return "WHERE 1";
        $wherekeyis = "WHERE ";
        $conditions = explode(",", $condition);
        $c = 0;
        foreach ($matching as $key => $value) {
            $wherekeyis .= "`" . $table_name . "`.`" . $key . "` " . $conditions[$c] . " '" . strval($value) .
                     "' AND ";
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
     *            the key to be matched. It may containe one or more fields. Values must be PHP native encoded
     *            Strings.
     */
    private function matched_record (array $matching)
    {
        $matched_record = "";
        foreach ($matching as $key => $value) {
            if ((count($matching) == 1) && ! is_nan($value))
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
     *            part of the record provided. Values must be UTF-8 encoded Strings.
     * @param array $record
     *            a named array with key = column name and value = values to be used for update. Must contain
     *            an "ID" field to identify the record to update. Values must be PHP native encoded. Enclosed
     *            quotes "'" will be appropriately escaped for the SQL command. record fields will be UTF-8
     *            decoded.
     * @return an error statement in case of failure, else "".
     */
    public function update_record_matched (String $appUserID, String $table_name, array $matching_keys, 
            array $record)
    {
        $prev_rec = $this->find_record_matched($table_name, $matching_keys);
        $change_entry = "updated: ";
        
        // create SQL command and change log entry.
        $sql_cmd = "UPDATE `" . $table_name . "` SET ";
        foreach ($record as $key => $value) {
            // check empty values. 1. If previous and current are empty, skip
            // the field.
            $skip_update = ((! $prev_rec[$key] && ($prev_rec[$key] !== 0)) && (! $value && ($value !== 0)));
            // skip matching keys, they are anyway equal
            foreach ($matching_keys as $matching_key)
                $skip_update = $skip_update || (strcasecmp($key, $matching_key) == 0);
            // check empty values. 2. If previous was not empty, and the record
            // was numeric or a
            // date, try NULL as value
            if (! $skip_update && (! $value && ($value !== 0)) && (is_numeric($prev_rec[$key]) ||
                     is_numeric(strtotime($prev_rec[$key]))))
                $sql_cmd .= "`" . $key . "` = NULL,";
            else 
                if (! $skip_update)
                    $sql_cmd .= "`" . $key . "` = '" . str_replace("'", "\'", $value) . "',";
            if (strcmp($value, $prev_rec[$key]) !== 0)
                $change_entry .= $key . ': "' . str_replace("'", "\'", 
                        $prev_rec[$key] . '"=>"' . str_replace("'", "\'", $value)) . '", ';
        }
        $sql_cmd = substr($sql_cmd, 0, strlen($sql_cmd) - 1);
        $change_entry = substr($change_entry, 0, strlen($change_entry) - 2);
        $sql_cmd .= " " . $this->clause_for_wherekeyis($table_name, $matching_keys, "=");
        
        // execute sql command and log execution.
        return $this->execute_and_log($appUserID, $table_name, $sql_cmd, 
                $this->matched_record($matching_keys), $change_entry, false);
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
     * Delete a record. The record is matched using the $match_key column and the $record[$match_key] value.
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
        // get previous recors to log change
        $prev_rec = $this->find_record_matched($table_name, $matching);
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
        
        // execute sql command and log execution.
        return $this->execute_and_log($appUserID, $table_name, $sql_cmd, $this->matched_record($matching), 
                $delete_entry, false);
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
     *            the condition for $key and $value, e. g. "!=" for not equal. Set to "" to get every record.
     *            You can use a condition for each matching field, if so wisched, by listing them comma
     *            separated, e.g. >,= for two fields if which the first shall be greater, the second equal to
     *            the respective values. If more matching values are provided, than conditions, the last
     *            condition is taken for all extra matching fields.
     * @param String $sort_key
     *            the name of the column to sort for. Set false to do no sorting.
     * @param bool $sort_ascending
     *            set to true to sort in ascending order, false to sort in descending order.
     * @return array of records, each being an array of key = column name and value = value. False in case of
     *         either connection error or no match. Values are UTF8 encoded.
     */
    public function find_records_sorted_matched (String $table_name, array $matching, int $max_rows, 
            String $condition, String $sort_key, bool $sort_ascending)
    {
        $sort_str = "";
        if ($sort_key) {
            $sort_way = ($sort_ascending) ? "ASC" : "DESC";
            $sort_cols = explode(",", $sort_key);
            $sort_str = " ORDER BY ";
            foreach ($sort_cols as $sort_col)
                $sort_str .= "`" . $table_name . "`.`" . $sort_col . "` " . $sort_way . ", ";
            $sort_str = substr($sort_str, 0, strlen($sort_str) - 2);
        }
        $col_names = $this->get_column_names($table_name);
        $col_indicators = "";
        foreach ($col_names as $col_name)
            $col_indicators .= "`" . $col_name . "`, ";
        $col_indicators = substr($col_indicators, 0, strlen($col_indicators) - 2);
        $sql_cmd = (strlen($condition) == 0) ? "SELECT " . $col_indicators . " FROM `" . $table_name .
                 "` WHERE 1 " . $sort_str : "SELECT " . $col_indicators . " FROM `" . $table_name . "` " . $this->clause_for_wherekeyis(
                        $table_name, $matching, $condition) . $sort_str;
        $res = $this->mysqli->query($sql_cmd);
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
        // now retrieve all column names
        $column_names = $this->get_column_names($table_name);
        $i = 0;
        foreach ($column_names as $column_name) {
            for ($r = 0; $r < $n_rows; $r ++)
                $sets[$r][$column_name] = $rows[$r][$i];
            $i ++;
        }
        return $sets;
    }

    /**
     * Count the number of records within a table
     * 
     * @param String $tablename            
     * @return boolean
     */
    public function count_records (String $tablename)
    {
        // now retrieve all column names
        $sql_cmd = "SELECT COUNT(*) FROM `" . $tablename . "`;";
        $res = $this->mysqli->query($sql_cmd);
        $count = 0;
        if (is_object($res) && intval($res->num_rows) > 0) {
            $row = $res->fetch_row();
            $count = intval($row[0]);
        }
        return $count;
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
            // the table name
            // always being at pos 0
            foreach ($row as $entry) {
                if ((strpos($entry, ";") !== false) || (strpos($entry, '"') !== false))
                    $entry = '"' . str_replace('"', '""', $entry) . '"';
                $csv .= $entry . ";";
            }
            $csv = substr($csv, 0, strlen($csv) - 1) . "\n";
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
     *            a String which will be added to the SQL statement containing the WHERE and ORDER BY clauses,
     *            e.g. "WHERE 1 ORDER BY `Funktionen`.`efaCloudUserID` DESC".
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
                $sql_cmd = "SELECT * FROM `" . $table_name . "` WHERE `" . $idname . "` = '" . $id . "'";
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
     * Get all column names as array with $array[n] = n. column's name.
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
        $res = $this->mysqli->query($sql_cmd);
        // put all values to the array, with numeric autoincrementing key.
        $ret = [];
        $column_names = $res->fetch_array();
        while ($column_names) {
            // the fetch_array function is an iterator, returning an array with
            // the column name
            // always being at pos 0
            $ret[] = $column_names[0];
            $column_names = $res->fetch_array();
        }
        return $ret;
    }

    /**
     * Get all column types as array with $array[n] = n. column's type.
     * 
     * @param String $table_name
     *            the name of the table to be used.
     * @return array of column names or false, if data base connection fails.
     */
    public function get_column_types (String $table_name)
    {
        // now retrieve all column names
        $sql_cmd = "SELECT `DATA_TYPE`, `CHARACTER_MAXIMUM_LENGTH` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA`='" .
                 $this->db_name . "' AND `TABLE_NAME`='" . $table_name . "'";
        $res = $this->mysqli->query($sql_cmd);
        // put all values to the array, with numeric autoincrementing key.
        $ret = [];
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
                    if (strpos(",Non_unique,Key_name,Column_name,Null", $index_response_column . ",") !== false) {
                        $index[$index_response_column] = $row[$c];
                    }
                    $c ++;
                }
                $index_description = "";
                if (strcasecmp($index["Key_name"], "PRIMARY") == 0)
                    $index_description .= "PRIMARY ";
                if (intval($index["Non_unique"]) == 0)
                    $index_description .= "UNIQUE ";
                if (strcasecmp($index["Null"], "YES") == 0)
                    $index_description .= "NULLABLE ";
                if ($include_description)
                    $indexes[$index["Column_name"]] = $index_description;
                else
                    $indexes[] = $index["Column_name"];
                $row = $res->fetch_row();
            }
        }
        return $indexes;
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
            // the table name
            // always being at pos 0
            $ret[] = $row[0];
            $row = $res->fetch_row();
        }
        $res->free();
        return $ret;
    }

    /**
     * *************************** WRITE STRUCTURE INFORMATION *****************************
     */
    
    /**
     * Create a new table. Builds the table including the columns provided. If a table of this name exists, it
     * will be dropped silently.
     * 
     * @param String $appUserID
     *            the ID of the application user of the user who performs the statement. For change logging.
     * @param String $table_name
     *            the name of the table to be created.
     * @param array $columns
     *            a named array with column => definition elements, e. g. "Id" => "varchar(64) NOT NULL". Must
     *            contain at least one column.
     * @return mixed one or more error statements in case of failure or in case of success "".
     */
    public function create_table (String $appUserID, String $table_name, array $columns)
    {
        $sql_cmd = "DROP TABLE `" . $table_name . "`";
        $this->execute_and_log($appUserID, $table_name, $sql_cmd, 0, "Dropped table " . $table_name . ".", 
                false, true);
        $sql_cmd = "CREATE TABLE `" . $table_name . "` ( ";
        $cn = 0;
        // e. g. CREATE TABLE `Table99` ( `Id` Varchar(256) NOT NULL ,
        // `ValidFrom` int(20) NOT NULL
        // )
        foreach ($columns as $column => $definition) {
            $sql_cmd .= "`" . $column . "` " . $definition . " , ";
            $cn ++;
        }
        if ($cn == 0)
            return "Error creating table " . $table_name . ". No columns provided.";
        $sql_cmd = substr($sql_cmd, 0, strlen($sql_cmd) - 2) . ")";
        return $this->execute_and_log($appUserID, $table_name, $sql_cmd, 0, 
                "Created table " . $table_name . " with " . $cn . " columns.", false);
    }

    /**
     * Add columns to a table.
     * 
     * @param String $appUserID
     *            the ID of the application user of the user who performs the statement. For change logging.
     * @param String $table_name
     *            the name of the table to be created.
     * @param array $column
     *            a named array with column => definition elements, e. g. "FirstName" => "varchar(256) NOT
     *            NULL DEFAULT 'John'", "LastName" => "varchar(256) NOT NULL DEFAULT 'Doe'", "MiddleInitial"
     *            => "varchar(256) NULL DEFAULT NULL". Must contain at least one column.
     * @return mixed one or more error statements in case of failure or in case of success "".
     */
    public function add_columns (String $appUserID, String $table_name, array $columns)
    {
        $cn = 0;
        $result = "";
        foreach ($columns as $column => $definition) {
            // e. g. ALTER TABLE `efaCloudUsers` ADD `FirstName` varchar(256)
            // NOT NULL DEFAULT
            // 'John'
            $sql_cmd = "ALTER TABLE `" . $table_name . "` ADD `" . $column . "` " . $definition;
            $result .= $this->execute_and_log($appUserID, $table_name, $sql_cmd, 0, 
                    "Added column `" . $column . "` to table " . $table_name . ".", false);
            $cn ++;
        }
        return $result;
    }

    /**
     * Set a column of a table to be unique, e. g. duplicates are refused.
     * 
     * @param String $appUserID
     *            the ID of the application user of the user who performs the statement. For change logging.
     * @param String $table_name
     *            the name of the table to be created.
     * @param String $column
     *            the column to be made unique.
     * @return mixed one or more error statements in case of failure or in case of success "".
     */
    public function set_unique (String $appUserID, String $table_name, String $column)
    {
        $sql_cmd = "ALTER TABLE `" . $table_name . "` ADD UNIQUE(`" . $column . "`)";
        $result = $this->execute_and_log($appUserID, $table_name, $sql_cmd, 0, 
                "Made column `" . $column . "` of table " . $table_name . " unique.", false);
        return $result;
    }

    /**
     * Set a column of a table to auto increment. Changes the column to become int(11) UNSIGNED
     * 
     * @param String $appUserID
     *            the ID of the application user of the user who performs the statement. For change logging.
     * @param String $table_name
     *            the name of the table to be created.
     * @param String $column
     *            the column to auto increment.
     * @return mixed one or more error statements in case of failure or in case of success "".
     */
    public function set_autoincrement (String $appUserID, String $table_name, String $column)
    {
        // e. g. ALTER TABLE `Logbook` CHANGE `EntryID` `EntryID` INT(11)
        // UNSIGNED NOT NULL
        // AUTO_INCREMENT
        $sql_cmd = "ALTER TABLE `" . $table_name . "` CHANGE `" . $column . "` `" . $column .
                 "` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT";
        $result = $this->execute_and_log($appUserID, $table_name, $sql_cmd, 0, 
                "Made column `" . $column . "` of table " . $table_name . " to auto increment.", false);
        return $result;
    }
}
