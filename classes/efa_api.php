<?php
include_once "../classes/efa_tables.php";
include_once "../classes/tx_handler.php";

/**
 * class file for the specific handling of eFa tables, e. g. GUID generation, autoincrementation etc.
 */
class Efa_api
{

    /**
     * The data base connection socket.
     */
    private $socket;

    /**
     * The generic toolbox.
     */
    private $toolbox;

    /**
     * Debug level to add mor information for support cases.
     */
    private $debug_on;

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
        $this->toolbox = $toolbox;
        $this->socket = $socket;
        $this->debug_on = $toolbox->config->debug_level > 0;
    }

    /* --------------------------------------------------------------------------------------- */
    /* ------------------ WRITE DATA TO EFACLOUD - PUBLIC FUNCTIONS -------------------------- */
    /* --------------------------------------------------------------------------------------- */
    
    /**
     * Modify a record of a table using the API syntax and return the result as
     * "<result_code>;<result_message>". Set the LastModified and LastModification values, if not yet set.
     * Generate an efaCloud record Id, if efaCloud record management is enabled at the server side and no
     * efaCloud record Id is provided in the record to insert.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param String $tablename
     *            the table into which the record shall be imported.
     * @param array $record
     *            record which shall be inserted.
     * @param int $mode
     *            Set 1 for insert, 2 for update, 3 for delete
     * @param int $api_version
     *            API-version of the client request. For API-version >= 3 the insert is checked before
     *            execution and possibly rejected with an error message.
     * @return the api result-code and result
     */
    public function api_modify (array $client_verified, String $tablename, array $record, int $mode, 
            int $api_version = 1)
    {
        // TODO: YYYYx logbook was introduced Jan 2023 and deprecated Feb 2023. Remove in 2024.
        if ($this->summary_logbook_filter($tablename, $record) !== false)
            return "304;" . i("ShX0Ie|records of a virtual sum...");
        // TODO: YYYYx logbook was introduced Jan 2023 and deprecated Feb 2023. Remove in 2024.
        $mode_str = ($mode == 1) ? "insert" : (($mode == 2) ? "update" : "delete");
        if ($this->debug_on)
            file_put_contents(Tx_handler::$api_debug_log_path, 
                    date("Y-m-d H:i:s") . ": starting api_modify ($mode_str) for client " .
                             $client_verified[$this->toolbox->users->user_id_field_name] . " at table " .
                             $tablename . ".\n", FILE_APPEND);
        $efaCloudUserID = $client_verified[$this->toolbox->users->user_id_field_name];
        $key_was_modified = false;
        include_once "../classes/efa_record.php";
        $efa_record = new Efa_record($this->toolbox, $this->socket);
        
        // Pre-modification check
        // ----------------------
        $key_was_modified = false;
        if ($api_version < 3) {
            // only existance and overwriting checks at API V1 and V2
            $pre_modification_check = $efa_record->validate_record_APIv1v2($tablename, $record, $mode, 
                    $efaCloudUserID, true);
            // $force_refresh = true, for multiple API transaction in one container, values may change in
            // between.
            if (! is_array($pre_modification_check))
                return "304;" . $pre_modification_check;
            $record = $pre_modification_check[0];
            $key_was_modified = $pre_modification_check[1];
            // From 2.3.2_09 onwards a message record with an existing key will overwrite the existing
            // message instead of being added with a key to fix.
            if (($mode == 1) && $key_was_modified && (strcasecmp($tablename, "efa2messages") == 0))
                $mode = 2;
            // if there is still a delete stub with that key update rather than insert.
            if (($mode == 1) && $pre_modification_check[2])
                $mode = 2;
        } else {
            // API version 3 or higher: add missing fields and do the semantic checks at the server side.
            $pre_modification_check = $efa_record->validate_record_APIv3($tablename, $record, $mode, 
                    $efaCloudUserID, true);
            if (! is_array($pre_modification_check))
                return "304;" . $pre_modification_check;
            $record = $pre_modification_check;
        }
        
        // modify record
        $result = $efa_record->modify_record($tablename, $record, $mode, $efaCloudUserID, $api_version < 3);
        if (($mode == 3) && is_numeric($result))
            $result = "";
        
        // for mode = insert adjust autoincrement for API V3+
        if (($api_version >= 3) && (strlen($result) == 0) && ($mode == 1))
            $efa_record->update_efa2autoincrement($tablename, $record, $efaCloudUserID);
        
        // Return response
        if (strlen($result) == 0) {
            // return response, depending on whether a key was modified, or not.
            if ($key_was_modified) {
                $fixing_request_csv = $this->get_next_key_to_fix($client_verified, $tablename);
                if ($this->debug_on)
                    file_put_contents(Tx_handler::$api_debug_log_path, 
                            date("Y-m-d H:i:s") . ": api_$mode_str: Key fixed to " . $result . " \n", 
                            FILE_APPEND);
                return "303;" . $fixing_request_csv; // 303 => "Transaction completed and data key
                                                         // mismatch detected."
            } else {
                if ($this->debug_on)
                    file_put_contents(Tx_handler::$api_debug_log_path, 
                            date("Y-m-d H:i:s") . ": api_$mode_str: " . i("vOXx8v|completed") . ". Ecrid '" .
                                     $record["ecrid"] . "' \n", FILE_APPEND);
                // return the ecrid and data keys, if existing. Clients without efaCloud record management
                // will be without effect ignore this information.
                $keys = "ecrid=" . $record["ecrid"];
                foreach (Efa_tables::$efa_data_key_fields[$tablename] as $key)
                    $keys .= ";" . $key . "=" . $record[$key];
                return "300;" . $keys; // 300 => "Transaction completed."
            }
        } else {
            if ($this->debug_on)
                file_put_contents(Tx_handler::$api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": api_$mode_str: " . i("fposcc|Failed. Reason:") . " '" . $result .
                                 "' \n", FILE_APPEND);
            return "502;" . $result; // 502 => "Transaction failed."
        }
    }

    /**
     * Remove a ClientSideKey entry after the mismatch was fixed at the client side. Only if the client has no
     * efaCloud record management enabled.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param array $tablename
     *            the table out of which the record shall be fixed.
     * @param array $fixed_record_reference
     *            reference to fixed record in which the ClientSideKey field shall be removed. Usually just
     *            the records server side data key. MUST NOT CONTAIN AN ECRID FIELD.
     */
    public function api_keyfixing (array $client_verified, String $tablename, array $fixed_record_reference)
    {
        if ($this->debug_on)
            file_put_contents(Tx_handler::$api_debug_log_path, 
                    date("Y-m-d H:i:s") . ": " . i("rmslRn|starting") . " api_keyfixing\n", FILE_APPEND);
        
        if (! array_key_exists($tablename, Efa_tables::$efa_autoincrement_fields)) {
            // 304 => "Transaction forbidden.", if the key must not be fixed.
            if ($this->debug_on)
                file_put_contents(Tx_handler::$api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": api_keyfixing " .
                                 i("ytlK51|aborted. Not allowed for...", $tablename) . "\n", FILE_APPEND);
            return "304;" . $tablename . ": " . i("SI2aD0|no key fixing for this t...");
        }
        if (array_key_exists("ecrid", $fixed_record_reference) &&
                 (strlen($fixed_record_reference["ecrid"]) > 5)) {
            if ($this->debug_on)
                file_put_contents(Tx_handler::$api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": api_keyfixing " . i("NHMrlY|aborted. Ecrid available...") . "\n", 
                        FILE_APPEND);
            return "304;" . i("SXW6e1|Keyfixing is not allowed...");
        }
        
        // identify, whether the keyfixing record is empty. Ignore the Logbookname field, because the
        // keyfixing record for the logbook always contains the Logbookname, even if there is no key to fix.
        $is_empty_record = (count($fixed_record_reference) == 0) || ((count($fixed_record_reference) == 1) &&
                 (strcasecmp($tablename, "efa2logbook") == 0) &&
                 (isset($fixed_record_reference["Logbookname"])));
        
        // keyfixing may be called with an empty keyfixing record to get the next mismatching
        // record's key. If, however, a key of a fixed record is provided, fix it
        if (! $is_empty_record) {
            $server_key_of_fixed_record = Efa_tables::get_record_key($tablename, $fixed_record_reference);
            if ($server_key_of_fixed_record === false) {
                // 304 => "Transaction failed." if the table must not be fixed.
                return "304;" . $tablename . ": " .
                         i("ny15Xd|incomplete key for fixin...", 
                                json_encode($fixed_record_reference), 
                                json_encode(Efa_tables::$efa_data_key_fields[$tablename]));
            }
            // get record to fix
            $record_to_remove_clientsidekey = $this->socket->find_record_matched($tablename, 
                    $server_key_of_fixed_record);
            // fix it, if found. Ignore, if not.
            if ($record_to_remove_clientsidekey) {
                // replace clientSideKey entry be a "previous key" remark
                $previous = explode(":", $record_to_remove_clientsidekey["ClientSideKey"]);
                $update_fields_for_fixed_record["ClientSideKey"] = "corrected from " . $previous[1] .
                         " at client " . $previous[0];
                // the following update will not change the ChangeCount/LastModified/Lastmodification-values
                // because it shall not trigger an update, except by explicit key fixing.
                $res = $this->socket->update_record_matched(
                        $client_verified[$this->toolbox->users->user_id_field_name], $tablename, 
                        $server_key_of_fixed_record, $update_fields_for_fixed_record);
                if ($this->debug_on)
                    file_put_contents(Tx_handler::$api_debug_log_path, 
                            date("Y-m-d H:i:s") . ": api_keyfixing " . i("cXYJJu|processed. Result:") . " " . $res .
                                     ".\n", FILE_APPEND);
            }
        }
        
        // check for more key which need fixing
        $return_message = $this->get_next_key_to_fix($client_verified, $tablename);
        if (! $return_message) {
            if ($this->debug_on)
                file_put_contents(Tx_handler::$api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": api_keyfixing " . i("3Dhpv1|completed. No more keys ...") . "\n", 
                        FILE_APPEND);
            return "300;";
        } else {
            if ($this->debug_on)
                file_put_contents(Tx_handler::$api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": api_keyfixing " . i("Sr2ozD|completed. More keys to ...") . " " .
                                 $return_message . ".\n", FILE_APPEND);
            return "303;" . $return_message;
        }
    }

    /* --------------------------------------------------------------------------------------- */
    /* ----------------------- READ DATA FROM EFACLOUD --------------------------------------- */
    /* --------------------------------------------------------------------------------------- */
    
    /**
     * Return a list of a table using the API syntax and return the result as "<result-code><rms><result>"
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param int $api_version
     *            The API version of the client request. For efaCloud record management at least 2
     * @param String $table_name
     *            the name of the table to be read. Ihe table name is "@All", the table names and their record
     *            count is returned.
     * @param array $filter
     *            the filter condition. An array containing field names and values and a ["?"] field with the
     *            filter condition. If the filter condition is "@All", and the table name is "@All", all
     *            records of all tables will be returned.
     * @param bool $keys_only
     *            set true to return the data keys and modification of records matching rather than the full
     *            records themselves.
     */
    public function api_select (array $client_verified, int $api_version, String $table_name, array $filter, 
            bool $keys_only)
    {
        if ($this->debug_on)
            file_put_contents(Tx_handler::$api_debug_log_path, 
                    date("Y-m-d H:i:s") . ": " . i(
                            "BbNX8o|starting api_select for ...", 
                            $client_verified[$this->toolbox->users->user_id_field_name], $table_name, 
                            $api_version) . "\n", FILE_APPEND);
        $condition = "=";
        if (isset($filter["?"])) {
            $condition = $filter["?"];
            unset($filter["?"]);
        }
        // find the select mode and count records, if needed
        $get_record_counts_of_db = (strcasecmp($table_name, "@All") == 0);
        $get_all_records_of_db = $get_record_counts_of_db && (strcasecmp($condition, "@All") == 0);
        $ret = "";
        if ($get_record_counts_of_db) {
            $tnames = $this->socket->get_table_names(true);
            foreach ($tnames as $tname)
                if (Efa_tables::is_efa_table($tname)) {
                    $record_count = $this->socket->count_records($tname, $filter, $condition);
                    $ret .= $tname . "=" . $record_count . ";";
                }
            // return the count, if not a full DB dump is requested.
            if (! $get_all_records_of_db)
                return "300;" . $ret;
        } else {
            $tnames = [$table_name
            ];
        }
        
        $csv_rows_cnt = 0;
        // iterate over all tables. This is only a loop for a full dump, else there is just one table to go.
        foreach ($tnames as $tname) {
            
            if ($this->debug_on)
                file_put_contents(Tx_handler::$api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": " . i("AxOnvy|continued api_select for...") . " $table_name.\n", 
                        FILE_APPEND);
            // add the condition to match the logbook, if the logbookname is part of the filter,
            $isLogbooktable = (strcasecmp($tname, "efa2logbook") == 0);
            // TODO: Remove the following comment for summary logbook from 2.3.2_13 onwards
            // $isSummaryLogbook = false;
            if ($isLogbooktable) {
                // TODO: Remove the following two lines of comment for summary logbook in 2024
                // $summary_logbook_filter = $this->summary_logbook_filter($tname, $filter);
                // was $condition .= ($summary_logbook_filter !== false) ? ",LIKE" : ",=";
                // add the condition, efa will only provide one, which ist the '>' for the LastModified
                $condition .= ",=";
                // TODO: Remove he following block for summary logbook in 2024
                // the summary logbook is YYYYx, YYYY being the four digit year. It includes all logbooks with
                // a name containing the four digit year
                // if ($summary_logbook_filter !== false) {
                // $filter["Logbookname"] = $summary_logbook_filter;
                // $logbooksequence = $this->list_logbooks_for_summary($summary_logbook_filter);
                // $isSummaryLogbook = true;
                // }
            }
            // add the condition to match the clubwork book, if the clubworkbookname is part of the filter,
            $isClubworkbooktable = (strcasecmp($tname, "efa2clubwork") == 0);
            // TODO hack for wrong capital N in efa request filter. My be removed some day, inserted 2.12.2022
            if (! isset($filter["Clubworkbookname"]) && isset($filter["ClubworkbookName"]))
                $filter["Clubworkbookname"] = $filter["ClubworkbookName"];
            if ($isClubworkbooktable && isset($filter["Clubworkbookname"]))
                // see Lgobook-handling above
                $condition .= ",=";
            
            // decide, whether to include efaCloud record management information, based on the requests
            // protocol version.
            $include_ecrm_fields = ($api_version >= 3);
            
            $csvtable = ($get_all_records_of_db) ? "###T###" . $tname . "###=###" : "";
            $header = ""; // header row to be created just at top
            $key_fields = Efa_tables::$efa_data_key_fields[$tname];
            $key_field_list = ",";
            foreach ($key_fields as $key_field)
                // use all keys for tables other than efa2logbook.
                // For the latter use all keys except the "Logbookname".
                // Note that Clubwork uses UUIDs as key which are unique for all years.
                $key_field_list .= (! $isLogbooktable || (strcasecmp($key_field, "Logbookname") != 0)) ? $key_field .
                         "," : "";
            $key_field_list .= "LastModified,LastModification,";
            if ($include_ecrm_fields)
                $key_field_list .= "ecrid,";
            
            // Exclude fields which will never be handled by the client.
            // 1. the server side key cache for keyfixing and AllCrewIds field, 2. the logbook and
            // clubworkbook selector, 3. the record history and the records copy recipients
            $fields_to_exclude_from_full = ",ClientSideEntryId,AllCrewIds," . "Logbookname,Clubworkbookname," .
                     "ecrhis,";
            // 4. if the client does not support the efacloud record management
            // exclude record id, owner and additional copy to fields
            if (! $include_ecrm_fields)
                $fields_to_exclude_from_full .= "ecrid,ecrown,ecract,";
            // Note: it doesn't hurt to exclude fields which are not existing anyways.
            
            $isFirstRow = true; // filter to identify, whether a header shall be created
            $start_row = 0;
            // iterate through all rows in chunks, use defalt ordering. Note taht this may not create a
            // consistent snapshot, if the table is changed in the meanwhile. But for the purpose of
            // repetitive synchronization tis is no risk.
            $records = $this->socket->find_records_sorted_matched($tname, $filter, 
                    Efa_tables::$select_chunk_size, $condition, "", true, $start_row);
            
            while (($records !== false) && (count($records) > 0)) {
                
                if ($this->debug_on)
                    file_put_contents(Tx_handler::$api_debug_log_path, 
                            date("Y-m-d H:i:s") . ": " . i("YonxH0|processing records from ...", $start_row) .
                                     "\n", FILE_APPEND);
                // add the condition to match the logbook, if the logbookname is part of the filter,
                foreach ($records as $record) {
                    // drop empty rows
                    if (count($record) > 0) {
                        $csvrow = "";
                        // set last 9 characters of "LastModified" to "000000230" for records without ecrid to
                        // trigger an update for ecrid generation.
                        if (! array_key_exists("ecrid", $record) || (strlen($record["ecrid"]) <= 5))
                            $record["LastModified"] = substr($record["LastModified"], 0, 
                                    strlen($record["LastModified"]) - 9) . "000000230";
                        // TODO: Remove summary logbook if block from 2.3.2_13 onwards.
                        /* // Modify the entryId for logbook records of a virtual summary logbook. if
                         * ($isSummaryLogbook) { if (isset($record["EntryId"]) &&
                         * isset($logbooksequence[$record["Logbookname"]])) { $record["EntryId"] =
                         * strval($record["EntryId"]) . strval($logbooksequence[$record["Logbookname"]]); } }
                         * */
                        foreach ($record as $field_name => $value) {
                            $field_name_checker = "," . $field_name . ",";
                            // use the column, if it is a key field, and a key is requested, or if it is not
                            // to be excluded and a full record is requested.
                            $use_column = ($keys_only) ? (strpos($key_field_list, $field_name_checker) !==
                                     false) : (strpos($fields_to_exclude_from_full, $field_name_checker) ===
                                     false);
                            if ($use_column) {
                                if ($isFirstRow)
                                    $header .= $field_name . ";";
                                // the value needs proper csv-quotation, field name not.
                                if ((strpos($value, ";") !== false) || (strpos($value, "\n") !== false) ||
                                         (strpos($value, "\"") !== false))
                                    $csvrow .= '"' . str_replace('"', '""', $value) . '";';
                                else
                                    $csvrow .= $value . ';';
                            }
                        }
                        
                        // before writing the first row, put the header w/o the dangling ';'
                        if ($isFirstRow) {
                            $csvtable = substr($header, 0, strlen($header) - 1) . "\n";
                            $isFirstRow = false;
                        }
                        // put the row w/o the dangling ';'
                        if (strlen($csvrow) > 0)
                            $csvrow = mb_substr($csvrow, 0, mb_strlen($csvrow) - 1);
                        $csvtable .= $csvrow . "\n";
                        $csv_rows_cnt ++;
                    }
                }
                $start_row += Efa_tables::$select_chunk_size;
                // the following statment must be the very same as above before the loop.
                $records = $this->socket->find_records_sorted_matched($tname, $filter, 
                        Efa_tables::$select_chunk_size, $condition, "", true, $start_row);
            }
        }
        
        // cut off the last \n and return the result.
        if (strlen($csvtable) > 0)
            $csvtable = mb_substr($csvtable, 0, mb_strlen($csvtable) - 1);
        if ($this->debug_on)
            file_put_contents(Tx_handler::$api_debug_log_path, 
                    date("Y-m-d H:i:s") . ": api_select " .
                             i("Mfgl2o|for table %1 completed. ...", $table_name, $csv_rows_cnt) .
                             "\n", FILE_APPEND);
        return "300;" . $csvtable;
    }

    /**
     * Return a list of a table using the API syntax and return the result."
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param String $listname
     *            the name of the list to be used.
     * @param String $setname
     *            the name of the lis set to use.
     * @param String $logbookname
     *            the the name of the logbook to use, if applicable.
     * @param int $last_modified_min
     *            the minimum LastModified vaöue to apply (seconds).
     */
    public function api_list (array $client_verified, String $listname, array $record, int $last_modified_min)
    {
        $logbookname = (isset($record["logbookname"])) ? $record["logbookname"] : date("Y");
        if (! isset($record["setname"]))
            // this is not "304", because the record contains a configuration information, not efa data.
            return "502;" . i("dAwKF1|Missing name of set in r...");
        if (! file_exists("../config/lists/" . $record["setname"]))
            // this is not "304", because the record contains a configuration information, not efa data.
            return "502;" . i("X6z2gz|Invalid name of set in r...");
        $list_args = [
                "{LastModified}" => (($last_modified_min == 0) ? "0" : strval($last_modified_min) . "000"),
                "{Logbookname}" => $logbookname
        ]; // The logbook name can always be added, if not used, that does hurt.
        foreach ($record as $key => $value)
            if (strpos($key, "listarg") !== false)
                $list_args[explode("=", $value, 2)[0]] = explode("=", $value, 2)[1];
        
        include_once "../classes/tfyh_list.php";
        $list = new Tfyh_list("../config/lists/" . $record["setname"], 0, $listname, $this->socket, 
                $this->toolbox, $list_args);
        $list->entry_size_limit = 10000;
        $is_versionized_table = ($list->get_field_index("InvalidFrom") !== false);
        $csv = $list->get_csv($client_verified, ($is_versionized_table) ? "Id" : null);
        return "300;" . $csv;
    }

    /* --------------------------------------------------------------------------------------- */
    /* ----------------------- API SUPPORT FUNCTIONS ----------------------------------------- */
    /* --------------------------------------------------------------------------------------- */
    
    /**
     * Trigger a backup of all tables creating a zip archive of text files. There is a two stage backup
     * process with 10 backups at each stage. So this gives you 10 days daily backup and 10 backups with a 10
     * day period between each, i. e. a 100 day backup regime. This function will also trigger a move of the
     * API log file to an indexed version, when a secondary backup is triggered. By this also the api log is
     * regularly moved and overwritten.
     * 
     * @return string the transaction result
     */
    public function api_backup ()
    {
        include_once "../classes/tfyh_backup_handler.php";
        $backup_handler = new Tfyh_backup_handler("../log/", $this->toolbox, $this->socket);
        $backup_index = $backup_handler->backup();
        return "300;" . $backup_index;
    }

    /**
     * Trigger the cron_jobs as defined in the Cron_jobs class.
     * 
     * @param int $user_id
     *            the efaCloudUserID of the user which triggered the job execution.
     * @return string the transaction result
     */
    public function api_cronjobs (int $user_id)
    {
        include_once ("../classes/cron_jobs.php");
        Cron_jobs::run_daily_jobs($this->toolbox, $this->socket, $user_id);
        return "300;" . ("jobs comopleted");
    }

    /**
     * Return some configuration settings as add-on to nop
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param array $record
     *            the record provided within the transaction
     * @param $max_api_version_server the
     *            maximum API version of the server. It is conveied with the NOP response to allow the client
     *            to max out the version to use.
     */
    public function api_nop (array $client_verified, array $record, int $max_api_version_server)
    {
        // wait some time as this is also a nop function.
        $wait_for_secs = intval(trim($record["sleep"]));
        $wait_for_secs = ($wait_for_secs > 100) ? 100 : $wait_for_secs;
        if ($wait_for_secs > 0)
            sleep($wait_for_secs);
        $tx_response = "300";
        // add maximum API protocol version and session ID
        $tx_response .= ";max_api_version_server=$max_api_version_server";
        if (isset($_SESSION["API_sessionid"]))
            $tx_response .= ";API_sessionid=" . $_SESSION["API_sessionid"];
        // add the synchronisation period settings
        $cfg = $this->toolbox->config->get_cfg();
        $tx_response .= ";synch_check_period=" . intval($cfg["synch_check_period"]);
        $tx_response .= ";synch_period=" . intval($cfg["synch_period"]);
        $tx_response .= ";user_concessions=" . intval($_SESSION["User"]["Concessions"]);
        // TODO the MemberIdList size adjustment is needed for efa 2.3.2_02 and lower.
        // add the efa2groups member id list field size
        $group_memberidlist_size = $this->column_size("efa2groups", "MemberIdList");
        if ($group_memberidlist_size != 1024)
            $tx_response .= ";group_memberidlist_size=" . $group_memberidlist_size;
        // add the server welcome message
        $username = $client_verified[$this->toolbox->users->user_firstname_field_name] . " " .
                 $client_verified[$this->toolbox->users->user_lastname_field_name] . " (Rolle: " .
                 $client_verified["Rolle"] . ")";
        $version = (file_exists("../public/version")) ? file_get_contents("../public/version") : "";
        // add the table layout
        include_once "../classes/efa_tools.php";
        $efa_tools = new Efa_tools($this->toolbox, $this->socket);
        include_once "../classes/efa_db_layout.php";
        $db_layout = Efa_db_layout::get_layout($efa_tools->db_layout_version);
        $tx_response .= ";server_welcome_message=efaCloud Server Version '" . $version . "'//" .
                 i("BN4UxH|Connected as") . " '" . $username . "';db_layout=" . $db_layout;
        return $tx_response;
    }

    /**
     * Return the verification result for an efaCloudUser
     * 
     * @param array $record
     *            the record provided within the transaction
     * @param int $api_version
     *            The API version of the client request. For efaCloud record management at least 2
     */
    public function api_verify (array $record, int $api_version)
    {
        if ($api_version < 2)
            return "501;API version must be 2 or higher.";
        // get the user which shall be verified
        if (isset($record["efaAdminName"])) {
            $login_field = "efaAdminName";
            $login_value = $record["efaAdminName"];
        } elseif (isset($record[$this->toolbox->users->user_id_field_name])) {
            $login_field = $this->toolbox->users->user_id_field_name;
            $login_value = $record[$this->toolbox->users->user_id_field_name];
        } else
            return "402;" . i(
                    "vDf51g|Neither efaAdminName nor...", 
                    $this->toolbox->users->user_id_field_name);
        if (! isset($record["password"]) || (strlen($record["password"]) < 8))
            return "403;" .
                     i(
                            "7n26tL|No password provided or ...");
        
        // get the user to verify the credentials for
        $user_to_verify = $this->socket->find_record($this->toolbox->users->user_table_name, $login_field, 
                $login_value);
        
        // and the user's password hash
        $auth_provider_class_file = "../authentication/auth_provider.php";
        if ((strlen($user_to_verify["Passwort_Hash"]) < 10) && file_exists($auth_provider_class_file)) {
            include_once $auth_provider_class_file;
            $auth_provider = new Auth_provider();
            $user_to_verify["Passwort_Hash"] = $auth_provider->get_pwhash(
                    $user_to_verify[$this->toolbox->users->user_id_field_name]);
        }
        
        if (strlen($user_to_verify["Passwort_Hash"]) < 10)
            return "403:" . i("PI741T|user has no password has...");
        
        $verified = password_verify($record["password"], $user_to_verify["Passwort_Hash"]);
        $user_keys_csv = "";
        $user_values_csv = "";
        foreach ($user_to_verify as $key => $value) {
            if ((strcasecmp($key, "Passwort_Hash") != 0) && (strcasecmp($key, "ecrhis") != 0)) {
                $user_keys_csv .= $key . ";";
                $user_values_csv .= $this->toolbox->encode_entry_csv($value) . ";";
            }
        }
        $user_keys_csv = mb_substr($user_keys_csv, 0, mb_strlen($user_keys_csv) - 1);
        $user_values_csv = mb_substr($user_values_csv, 0, mb_strlen($user_values_csv) - 1);
        $user_record_csv = $user_keys_csv . "\n" . $user_values_csv;
        return ($verified) ? "300;" . $user_record_csv : "403:" .
                 i("auPGvi|credentials in VERIFY tr...");
    }

    /* --------------------------------------------------------------------------------------- */
    /* ------------------ PRIVATE SUPPORT FUNCTIONS ------------------------------------------ */
    /* --------------------------------------------------------------------------------------- */
    
    // TODO: remove summary logbook function from 2.3.2_13 onwards
    /**
     * Return all logbook names matching the filter of the efa2logbook table as array
     * 
     * @param String $filter_like
     *            the filter to look for, e.g. %2022%
     * @return array all names found, an empty array on no match. private function list_logbooks_for_summary
     *         (String $filter_like) { $res = $this->socket->query( "SELECT DISTINCT `Logbookname` FROM
     *         `efa2logbook` WHERE `Logbookname` LIKE '" . $filter_like . "'"); $logbooksequence = []; $l = 1;
     *         if (isset($res->num_rows) && (intval($res->num_rows) > 0)) { $row = $res->fetch_row(); while
     *         ($row) { $logbooksequence[$row[0]] = $l; $l ++; $row = $res->fetch_row(); } } return
     *         $logbooksequence; }
     */
    
    /**
     *
     * @param String $tablename
     *            the name of the table used
     * @param String $logbook_name
     *            the name og the logbook to check
     * @return String|bool the filter for the select statement, if yes. Else false.
     */
    // TODO: YYYYx summary logbook was introduced Jan 2023 and deprecated Feb 2023. Remove in 2024.
    private function summary_logbook_filter (String $tablename, array $record_or_filter)
    {
        if (strcasecmp($tablename, "efa2logbook") != 0)
            return false;
        if (! isset($record_or_filter["Logbookname"]))
            return false;
        if (strlen($record_or_filter["Logbookname"]) != 5)
            return false;
        if (strcasecmp(substr($record_or_filter["Logbookname"], 4, 1), "x") != 0)
            return false;
        return "%" . substr($record_or_filter["Logbookname"], 0, 4) . "%";
    }

    /**
     * get the size of a specific column to adjust the client max length property. Returns 0 on unsized
     * columns like INT, BIGINT, DATE or similar. Used for efa2groups memberIdList.
     * 
     * @param String $table_name
     *            table to search in
     * @param String $column_name
     *            column to search for
     * @return int the size of the column, if provided, else 0.
     */
    private function column_size (String $table_name, String $column_name)
    {
        $column_names = $this->socket->get_column_names($table_name);
        $column_definitions = $this->socket->get_column_types($table_name);
        for ($i = 0; $i < count($column_names); $i ++)
            if (strcmp($column_names[$i], $column_name) == 0)
                if (strpos($column_definitions[$i], "(") === false)
                    return 0;
                else {
                    $start = strpos($column_definitions[$i], "(") + 1;
                    $end = strpos($column_definitions[$i], ")");
                    $size = substr($column_definitions[$i], $start, $end - $start);
                    return intval($size);
                }
        return 0;
    }

    /**
     * Return the key as defined in Efa_tables::$efa_data_key_fields[]
     * 
     * @param String $tablename
     *            name of table to find the record
     * @param String $clientSideKey
     *            the client side key formatted as done by getClientSideKey
     * @return mixed the key as associative array, if for all key fields a value is provided, else false.
     */
    private function get_clientSideKey_array (String $tablename, String $clientSideKey)
    {
        $clientSideKeyArray = [];
        $keys = Efa_tables::$efa_data_key_fields[$tablename];
        $clientSideKeyParts = explode("|", $clientSideKey);
        $field_index = 0;
        if (strcasecmp($tablename, "efa2logbook") == 0)
            foreach ($keys as $key) {
                if (isset($clientSideKeyParts[$field_index]))
                    $clientSideKeyArray[$key] = $clientSideKeyParts[$field_index];
                else
                    $clientSideKeyArray[$key] = "";
                $field_index ++;
            }
        return $clientSideKeyArray;
    }

    /**
     * Get the server record which is matching the provided client record. It may not have the same data key,
     * if a key mismatch was not yet fixed and the record has no ecrid, as usual with an efa client record.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param String $tablename
     *            the table into which the record shall be imported.
     * @param array $client_record
     *            client record which shall be matched.
     * @param String $tablename            
     * @return the matching server record. False, if no match was found.
     */
    private function get_corresponding_server_record (array $client_verified, String $tablename, 
            array $client_record)
    {
        $client_record_key = Efa_tables::get_record_key($tablename, $client_record);
        if (! $client_record_key)
            return false;
        $efaCloudUserID = $client_verified[$this->toolbox->users->user_id_field_name];
        // if either no key fixing is allowed, or if an efaCloud record Id is provided by the client, find the
        // record using the provided key and return it.
        if (! array_key_exists($tablename, Efa_tables::$efa_autoincrement_fields) ||
                 (array_key_exists("ecrid", $client_record) && (strlen($client_record["ecrid"]) > 5)))
            return $this->socket->find_record_matched($tablename, $client_record_key);
        
        // if keyfixing is allowed and no efaCloud record Id is provided, get all records which need fixing
        // from this client. Note that not more than a couple of keys shall be with a key to fix.
        $records_to_fix = $this->socket->find_records_sorted_matched($tablename, 
                ["ClientSideKey" => "%" . $efaCloudUserID . ":%"
                ], 100, "LIKE", false, true, false);
        // none found which needs fixing, so find the record using the provided key and return it.
        if ($records_to_fix === false)
            return $this->socket->find_record_matched($tablename, $client_record_key);
        
        // some found which need fixing. See whether one of those has the client record's key cached as
        // ClientSideKey
        include_once "../classes/efa_record.php";
        $client_record_key_for_caching = $efaCloudUserID . ":" .
                 Efa_record::compile_clientSideKey($tablename, $client_record);
        // if so, return this record which still needs fixing
        foreach ($records_to_fix as $record_to_fix)
            if (strcmp($record_to_fix["ClientSideKey"], $client_record_key_for_caching) == 0)
                return $record_to_fix;
        // if not, find the record using the provided key and return it.
        return $this->socket->find_record_matched($tablename, $client_record_key);
    }

    /**
     * Get the next mismatching key in this table for that client. For both insert and keyfixing requests the
     * server has to return an appropriate data key which shall be fixed. It is mandatory that this new data
     * key is not in use at the client side, because if so, the insertion of the fixed record at the client
     * side will fail. Now the set of client side keys is the set of keys of all records without key mismatch
     * plus the set of mismatching client side keys. To find a free client side key you need to iterate
     * through the server side keys until you’ve found one, which is not in set of client side keys. Because
     * all synchronous keys are part of both sets, they can be left out. Said that, a free client side key can
     * be detected at the server by iterating through the set of all server side keys of mismatching records
     * until one is detected which is not in the set of mismatching client side keys.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param array $tablename
     *            the table out of which the record shall be updated.
     * @param String $tablename            
     * @return the csv table with the new rtecord and the current key as it shall be returned to the client.
     *         False, if no further key mismatch exists for this table
     */
    private function get_next_key_to_fix (array $client_verified, String $tablename)
    {
        
        // 2.3.2_09 and following: key fixing of message records will no more take place.
        // the client may, however, still ask for keys to be fixed. Return always none.
        if (strcasecmp($tablename, "efa2messages") == 0)
            return false;
        
        $api_log_path = Tx_handler::$api_log_path;
        // get all records which need fixing from this client
        // Note that the mismatching client side key of those always contains at least one ":"
        $client_key_filter = "%" . $client_verified[$this->toolbox->users->user_id_field_name] . ":%";
        // Note that not more than a couple of keys shall be with a key to fix.
        $mismatching_server_side_records = $this->socket->find_records_sorted_matched($tablename, 
                ["ClientSideKey" => $client_key_filter
                ], 100, "LIKE", false, true, false);
        if (! $mismatching_server_side_records)
            return false;
        
        file_put_contents($api_log_path, 
                "[" . date("Y-m-d H:i:s") . "] - " . i("JkJqGq|Transaction: %1 mismatch...", 
                        count($mismatching_server_side_records)) . "\n", FILE_APPEND);
        
        // collect mismatching keys
        $mismatching_client_side_keys = [];
        $mismatching_server_side_records_plus = []; // add the server side key for later use
        include_once "../classes/efa_record.php";
        foreach ($mismatching_server_side_records as $mismatching_server_side_record) {
            // compile the data key of the record to fix
            $mismatching_server_side_key = Efa_record::compile_clientSideKey($tablename, 
                    $mismatching_server_side_record);
            file_put_contents($api_log_path, 
                    "[" . date("Y-m-d H:i:s") . "] - " . i("nrTQdY|Transaction: mismatching...", 
                            json_encode($mismatching_server_side_key)) . "\n", FILE_APPEND);
            $mismatching_client_side_key_pair = explode(":", $mismatching_server_side_record["ClientSideKey"]);
            file_put_contents($api_log_path, 
                    "[" . date("Y-m-d H:i:s") . "] - " . i(
                            "irFk1J|Transaction: mismatching...", 
                            json_encode($mismatching_client_side_key_pair)) . "\n", FILE_APPEND);
            $mismatching_client_side_keys[] = $mismatching_client_side_key_pair[1];
            // add the comparable server side key to the record for later usage
            $mismatching_server_side_record["ServerSideKey"] = Efa_record::compile_clientSideKey($tablename, 
                    $mismatching_server_side_record);
            $mismatching_server_side_records_plus[] = $mismatching_server_side_record;
        }
        
        // find a mismatching record with server side key which is not yet used on the client side.
        $free_at_client = false;
        foreach ($mismatching_server_side_records_plus as $mismatching_server_side_record) {
            $used_at_client = false;
            if (! $free_at_client)
                foreach ($mismatching_client_side_keys as $mismatching_client_side_key) {
                    if (strcmp($mismatching_client_side_key, $mismatching_server_side_record["ServerSideKey"]) ==
                             0)
                        $used_at_client = true;
                }
            if (! $used_at_client)
                $free_at_client = $mismatching_server_side_record;
        }
        
        // No such record was identified. That actually shall never happen. If so, abort fixing.
        if ($free_at_client === false) {
            file_put_contents($api_log_path, 
                    "[" . date("Y-m-d H:i:s") . "] - " .
                             i(
                                    "ebzhv5|Transaction: could not f...") .
                             "\n", FILE_APPEND);
            return "";
        }
        
        file_put_contents($api_log_path, 
                "[" . date("Y-m-d H:i:s") . "] - " . i(
                        "dTHKvl|Transaction: Key value °...", 
                        $free_at_client["ServerSideKey"]) . "\n", FILE_APPEND);
        
        // provide the record to delete at the client side and the record to insert instead.
        $record_to_delete_key = $free_at_client["ClientSideKey"];
        unset($free_at_client["ServerSideKey"]);
        
        // write csv header
        $ret = "";
        $exclude_server_side_only_fields = "ClientSideKey LastModification";
        foreach ($free_at_client as $key => $value)
            if (strpos($exclude_server_side_only_fields, $key) === false)
                $ret .= $key . ";";
        $ret = mb_substr($ret, 0, mb_strlen($ret) - 1) . "\n";
        
        // write values of the record to be inserted
        foreach ($free_at_client as $key => $value)
            if (strpos($exclude_server_side_only_fields, $key) === false)
                $ret .= $this->toolbox->encode_entry_csv($value) . ";";
        $ret = mb_substr($ret, 0, mb_strlen($ret) - 1) . "\n";
        
        // replace the key of the new record by the current key.
        $record_to_delete_key_pair = explode(":", $record_to_delete_key);
        $record_to_delete_key_array = $this->get_clientSideKey_array($tablename, 
                $record_to_delete_key_pair[1]);
        foreach ($record_to_delete_key_array as $key => $current_value)
            $free_at_client[$key] = $current_value;
        
        // Write the new record with the current key for deletion. Although only key values are used
        // for deletion, the full set of values is written to stick to the csv table layout
        foreach ($free_at_client as $key => $value)
            if (strpos($exclude_server_side_only_fields, $key) === false)
                $ret .= $this->toolbox->encode_entry_csv($value) . ";";
        $ret = mb_substr($ret, 0, mb_strlen($ret) - 1);
        
        // Return new record and current key csv string.
        return $ret;
    }
}
