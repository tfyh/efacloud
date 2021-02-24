<?php

/**
 * class file for the specific handling of eFa tables, e. g. GUID generation, autoincrementation etc.
 * 
 * @package efacloud
 * @subpackage classes
 * @author mgSoft
 */
class Efa_tables
{

    /**
     * Column names of those columns that represent the explicit primary key of the specific table
     * 
     * @var array
     */
    public static $key_fields = 
    // efaCloud server table key fields
    ["efaCloudUsers" => ["ID"
    ],"efaCloudConfig" => ["ID"
    ],"efaCloudLog" => ["ID"
    ],
            // efa tables single field keys
            "efa2autoincrement" => ["Sequence"
            ],"efa2boatstatus" => ["BoatId"
            ],"efa2clubwork" => ["Id"
            ],"efa2crews" => ["Id"
            ],"efa2fahrtenabzeichen" => ["PersonId"
            ],"efa2logbook" => ["EntryId","Logbookname" // adaptation, only at the server side
            ],"efa2messages" => ["MessageId"
            ],"efa2sessiongroups" => ["Id"
            ],"efa2statistics" => ["Id"
            ],"efa2status" => ["Id"
            ],"efa2waters" => ["Id"
            ],
            // efa tables double field keys
            "efa2boatdamages" => ["BoatId","Damage"
            ],"efa2boatreservations" => ["BoatId","Reservation"
            ],
            // efa tables versionized tables
            "efa2boats" => ["Id","ValidFrom"
            ],"efa2destinations" => ["Id","ValidFrom"
            ],"efa2groups" => ["Id","ValidFrom"
            ],"efa2persons" => ["Id","ValidFrom"
            ],
            // efa config tables
            "efa2project" => ["Type","Name"
            ],"efa2admins" => ["Name"
            ],"efa2types" => ["Category","Type"
            ]
    ];

    /**
     * The tables for which a key fixing is allowed
     */
    private $fixid_allowed = "efa2logbook efa2messages efa2boatdamages efa2boatreservations";

    /**
     * The field which shall be autoincremented for tables for which a key fixing is allowed. MUST BE AN
     * INTEGER NUMBER.
     */
    public $fixid_auto_field = ["efa2logbook" => "EntryId","efa2messages" => "MessageId",
            "efa2boatdamages" => "Damage","efa2boatreservations" => "Reservation"
    ];

    /**
     * The projects meta-data. Will be read only when importing a backup and will not be written. Is needed to
     * identify the current logbook.
     */
    private $project_meta;

    /**
     * The projects current logbook, the only one which is imported into the data base.
     */
    private $current_logbook_name;

    /**
     * The data base connection socket.
     */
    public $socket;

    /**
     * The generic toolbox.
     */
    private $toolbox;

    /**
     * public Constructor.
     */
    public function __construct (Toolbox $toolbox, Socket $socket)
    {
        $this->socket = $socket;
        $this->toolbox = $toolbox;
    }

    /**
     * ===== BUILD STRUCTURE AT EFACLOUD ===============================================
     */
    /**
     * Create a new table. Builds the table including the columns provided. If a table of this name exists, it
     * will be dropped silently.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param String $table_name
     *            the name of the table to be created.
     * @param array $record
     *            The record contains column definitions, e.g. [ “Id” => “Varchar(256) NOT NULL”, “ValidFrom”
     *            => “int(20) NOT NULL” ]. The record should contain all key fields and may contain further
     *            fields.
     * @return mixed one or more error statements in case of failure or in case of success "".
     */
    public function api_createtable (array $client_verified, String $tablename, array $record)
    {
        $efaCloudUserID = $client_verified[$this->toolbox->users->user_id_field_name];
        $result = $this->socket->create_table($efaCloudUserID, $tablename, $record);
        if (strlen($result) == 0)
            return "300;ok"; // 300 => "Transaction successful."
        else
            return "502;" . $result; // 502 => "Transaction failed."
    }

    /**
     * Add columns to a table.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param String $table_name
     *            the name of the table to be created.
     * @param array $column
     *            a named array with column => definition elements, e. g. "FirstName" => "varchar(256) NOT
     *            NULL DEFAULT 'John'", "LastName" => "varchar(256) NOT NULL DEFAULT 'Doe'", "MiddleInitial"
     *            => "varchar(256) NULL DEFAULT NULL". SHOULD contain at least one column.
     * @return mixed one or more error statements in case of failure or in case of success "".
     */
    public function api_addcolumns (array $client_verified, String $table_name, array $columns)
    {
        $efaCloudUserID = $client_verified[$this->toolbox->users->user_id_field_name];
        $result = $this->socket->add_columns($efaCloudUserID, $table_name, $columns);
        if (strlen($result) == 0)
            return "300;ok"; // 300 => "Transaction successful."
        else
            return "502;" . $result; // 502 => "Transaction failed."
    }

    /**
     * Set a column of a table to auto increment. Changes the column to become int(11) UNSIGNED
     * 
     * @param String $efaCloudUserID
     *            the Mitgliednummer of the user who performs the statement. For change logging.
     * @param String $table_name
     *            the name of the table to be created.
     * @param String $column
     *            the column to auto increment.
     * @return mixed one or more error statements in case of failure or in case of success "".
     */
    public function api_autoincrement (array $client_verified, String $table_name, String $column)
    {
        $efaCloudUserID = $client_verified[$this->toolbox->users->user_id_field_name];
        $result = $this->socket->set_autoincrement($efaCloudUserID, $table_name, $column);
        if (strlen($result) == 0)
            return "300;ok"; // 300 => "Transaction successful."
        else
            return "502;" . $result; // 502 => "Transaction failed."
    }

    /**
     * Set a column of a table to be unique, e. g. duplicates are refused.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param String $table_name
     *            the name of the table to be created.
     * @param String $column
     *            the column to be made unique.
     * @return mixed one or more error statements in case of failure or in case of success "".
     */
    public function api_unique (array $client_verified, String $table_name, String $column)
    {
        $efaCloudUserID = $client_verified[$this->toolbox->users->user_id_field_name];
        $result = $this->socket->set_unique($efaCloudUserID, $table_name, $column);
        if (strlen($result) == 0)
            return "300;ok"; // 300 => "Transaction successful."
        else
            return "502;" . $result; // 502 => "Transaction failed."
    }

    /**
     * ===== WRITE DATA TO EFACLOUD - PRIVATE HELPER FUNCTIONS ========================
     */
    /**
     * Checks whether the table has a LastModification field
     * 
     * @param String $tablename
     *            name of table to check
     * @return boolean true, if such field exists in the table.
     */
    private function has_LastModification_field (String $tablename)
    {
        return (strpos($tablename, "efa2") === 0);
    }

    /**
     * Return the server side key as defined in self::$key_fields[]. This is the same as in the client except
     * for the logbook. Within the client each year has a separate logbook with EntryIds starting from 1 at
     * the first of January. The server uses just one table with an additional data field Logbookname. The
     * server side key therefore is the EntryId plus the Logbookname.
     * 
     * @param String $tablename
     *            name of table to find the record
     * @param array $record
     *            record to find
     * @return mixed the key as associative array, if for all key fields a value is provided, else false.
     */
    private function getServerKey (String $tablename, array $record)
    {
        $matching = [];
        $keys = self::$key_fields[$tablename];
        foreach ($keys as $key) {
            if (isset($record[$key]))
                $matching[$key] = $record[$key];
            else
                return false;
        }
        return $matching;
    }

    /**
     * Return the client side key as defined in self::$key_fields[]. This is the same as in the client except
     * for the logbook. Within the client each year has a separate logbook with EntryIds starting from 1 at
     * the first of January. The server uses just one table with an additional data field Logbookname. The
     * client side key is just the EntryId without the Logbookname.
     * 
     * @param String $tablename
     *            name of table to find the record
     * @param array $record
     *            record to find
     * @return mixed the key as value for the ClientSideKey comparison, if for all key fields a value is
     *         provided, else false.
     */
    private function getClientSideKey (String $tablename, array $record)
    {
        $matching = $this->getServerKey($tablename, $record);
        if (! $matching || (count($matching) == 0))
            return false;
        if (strcasecmp($tablename, "efa2logbook") == 0)
            return $record["EntryId"];
        $values = "";
        foreach ($matching as $key => $value)
            $values .= $value . "|";
        return substr($values, 0, strlen($values) - 1);
    }

    /**
     * Return the key as defined in self::$key_fields[]
     * 
     * @param String $tablename
     *            name of table to find the record
     * @param String $clientSideKey
     *            the client side key formatted as done by getClientSideKey
     * @return mixed the key as associative array, if for all key fields a value is provided, else false.
     */
    private function getClientSideKeyArray (String $tablename, String $clientSideKey)
    {
        $clientSideKeyArray = [];
        $keys = self::$key_fields[$tablename];
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
     * Autoincrement the next free key value for tables which may use key fixing.
     * 
     * @param String $tablename
     *            the table for which the maximum numeric part of the key shall be found
     * @param String $logbookname
     *            the logbookname in case the table is the logbook to look only into the correct split part.
     * @return int next key value to use
     */
    private function autoincrement_key_field (String $tablename, String $logbookname)
    {
        $field_to_autoincrement = $this->fixid_auto_field[$tablename];
        if (strcasecmp($tablename, "efa2logbook") == 0) {
            $all_records = $this->socket->find_records_matched($tablename, 
                    ["Logbookname" => $logbookname
                    ], 10000);
        } else
            $all_records = $this->socket->find_records_matched($tablename, [], 10000);
        $max_value = 0;
        foreach ($all_records as $record)
            if (intval($record[$field_to_autoincrement]) > $max_value)
                $max_value = intval($record[$field_to_autoincrement]);
        $max_value ++;
        return $max_value;
    }

    /**
     * Get the server record which is matching this client record. It may not have the same key, if a key
     * mismatch was not yet fixed.
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
        // The client record must contain the logbook name in case of an efa2logbook record
        $server_record_key = $this->getServerKey($tablename, $client_record);
        if (! $server_record_key)
            return false;
        $efaCloudUserID = $client_verified[$this->toolbox->users->user_id_field_name];
        // find the record using directly the provided key, if no key fixing was allowed
        if (strpos($this->fixid_allowed, $tablename) === false)
            return $this->socket->find_record_matched($tablename, $server_record_key);
        
        // if keyfixing is allowed, get all records which need fixing from this client
        $records_to_fix = $this->socket->find_records_sorted_matched($tablename, 
                ["ClientSideKey" => "%" . $efaCloudUserID . ":%"
                ], 1000, "LIKE", false, true, false);
        // none found, so find the record using directly the provided key
        if (! $records_to_fix)
            return $this->socket->find_record_matched($tablename, $server_record_key);
        
        // some found. See whether one of those has the client record's key cached as ClientSideKey
        $client_record_key_for_caching = $efaCloudUserID . ":" .
                 $this->getClientSideKey($tablename, $client_record);
        // if so, return it
        foreach ($records_to_fix as $record_to_fix)
            if (strcmp($record_to_fix["ClientSideKey"], $client_record_key_for_caching) == 0)
                return $record_to_fix;
        // if not, find the record using directly the provided key
        return $this->socket->find_record_matched($tablename, $server_record_key);
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
     * @param String $api_log_path
     *            path to write the keyfixing activities to
     * @return the csv table with the new rtecord and the current key as it shall be returned to the client.
     *         False, if no further key mismatch exists for this table
     */
    private function get_next_key_to_fix (array $client_verified, String $tablename, String $api_log_path)
    {
        // get all records which need fixing from this client
        // Note that the mismatching client side key of those always contains at least one ":"
        $mismatching_server_side_records = $this->socket->find_records_sorted_matched($tablename, 
                [
                        "ClientSideKey" => "%" . $client_verified[$this->toolbox->users->user_id_field_name] .
                                 ":%"
                ], 1000, "LIKE", false, true, false);
        if (! $mismatching_server_side_records)
            return false;
        
        file_put_contents($api_log_path, 
                "[" . date("Y-m-d H:i:s") . "] - Transaction: " . count($mismatching_server_side_records) .
                         " mismatching_server_side_records.\n", FILE_APPEND);
        
        // collect mismatching keys
        $mismatching_client_side_keys = [];
        $mismatching_server_side_records_plus = []; // add the server side key for later use
        foreach ($mismatching_server_side_records as $mismatching_server_side_record) {
            // compile the data key of the record to fix
            $mismatching_server_side_key = $this->getClientSideKey($tablename, 
                    $mismatching_server_side_record);
            
            file_put_contents($api_log_path, 
                    "[" . date("Y-m-d H:i:s") . "] - Transaction: mismatching_server_side_key = " .
                             json_encode($mismatching_server_side_key) . "\n", FILE_APPEND);
            
            $mismatching_client_side_key_pair = explode(":", $mismatching_server_side_record["ClientSideKey"]);
            
            file_put_contents($api_log_path, 
                    "[" . date("Y-m-d H:i:s") . "] - Transaction: mismatching_client_side_key_pair = " .
                             json_encode($mismatching_client_side_key_pair) . "\n", FILE_APPEND);
            
            $mismatching_client_side_keys[] = $mismatching_client_side_key_pair[1];
            // add the comparable server side key to the record for later usage
            $mismatching_server_side_record["ServerSideKey"] = $this->getClientSideKey($tablename, 
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
                    "[" . date("Y-m-d H:i:s") .
                             "] - Transaction: could not find free key to use at client side for key correction. key correction aborted.\n", 
                            FILE_APPEND);
            return "";
        }
        
        file_put_contents($api_log_path, 
                "[" . date("Y-m-d H:i:s") . "] - Transaction: Key value '" . $free_at_client["ServerSideKey"] .
                         "' is detected to be free_at_client.\n", FILE_APPEND);
        
        // provide the record to delete at the client side and the record to insert instead.
        $record_to_delete_key = $free_at_client["ClientSideKey"];
        unset($free_at_client["ServerSideKey"]);
        
        // write csv header
        $ret = "";
        $exclude_server_side_only_fields = "ClientSideKey LastModification";
        foreach ($free_at_client as $key => $value)
            if (strpos($exclude_server_side_only_fields, $key) === false)
                $ret .= $key . ";";
        $ret = substr($ret, 0, strlen($ret) - 1) . "\n";
        
        // write values of the record to be inserted
        foreach ($free_at_client as $key => $value)
            if (strpos($exclude_server_side_only_fields, $key) === false)
                $ret .= $this->toolbox->encode_entry_csv($value) . ";";
        $ret = substr($ret, 0, strlen($ret) - 1) . "\n";
        
        // replace the key of the new record by the current key.
        $record_to_delete_key_pair = explode(":", $record_to_delete_key);
        $record_to_delete_key_array = $this->getClientSideKeyArray($tablename, $record_to_delete_key_pair[1]);
        foreach ($record_to_delete_key_array as $key => $current_value)
            $free_at_client[$key] = $current_value;
        
        // Write the new record with the current key for deletion. Although only key values are used
        // for deletion, the full set of values is written to stick to the csv table layout
        foreach ($free_at_client as $key => $value)
            if (strpos($exclude_server_side_only_fields, $key) === false)
                $ret .= $this->toolbox->encode_entry_csv($value) . ";";
        $ret = substr($ret, 0, strlen($ret) - 1);
        
        // Return new record and current key csv string.
        return $ret;
    }

    /**
     * ===== WRITE DATA TO EFACLOUD - PUBLIC FUNCTIONS ===============================
     */
    /**
     * Insert a record into a table using the API syntax and return the result as
     * "<result_code>;<result_message>". Set the LastModified and LastModification values, if not yet set.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param String $tablename
     *            the table into which the record shall be imported.
     * @param array $record
     *            record which shall be inserted.
     * @param String $api_log_path
     *            path to write the keyfixing activities to
     * @return the api result-code and result
     */
    public function api_insert (array $client_verified, String $tablename, array $record, String $api_log_path)
    {
        // Check provided key and existing record. Error: 502 => "Transaction failed."
        $key = $this->getServerKey($tablename, $record);
        if ($key === false)
            return "502;Cannot insert record, key is incomplete or missing.";
        
        // It is checked, whetheter the key is used. If so, an error is returned, except for the
        // tables in which keys can be fixed.
        $record_matched = $this->socket->find_record_matched($tablename, $key);
        $key_was_modified = false;
        if ($record_matched !== false) {
            if (strpos($this->fixid_allowed, $tablename) === false)
                return "502;Cannot insert record, provided key is already in use.";
            else {
                // copy the client side key to the ClientSideKey field
                $record["ClientSideKey"] = $client_verified[$this->toolbox->users->user_id_field_name] . ":" .
                         $this->getClientSideKey($tablename, $record);
                // autoincrement the numeric part of the key. Note that for the logbook tables that will
                // need the logbook name, because all logbooks are in one single table at the server
                // side.
                $record[$this->fixid_auto_field[$tablename]] = $this->autoincrement_key_field($tablename, 
                        (isset($record["Logbookname"]) ? $record["Logbookname"] : ""));
                $key_was_modified = true;
            }
        }
        
        // insert data record and return result
        $efaCloudUserID = $client_verified[$this->toolbox->users->user_id_field_name];
        if (! isset($record["LastModified"]))
            ! $record["LastModified"] = time() . "000";
        if (! isset($record["LastModification"]) && $this->has_LastModification_field($tablename))
            $record["LastModification"] = "insert";
        $result = $this->socket->insert_into($efaCloudUserID, $tablename, $record);
        if (is_numeric($result) || (strlen($result) == 0)) {
            if ($key_was_modified) {
                $fixing_request_csv = $this->get_next_key_to_fix($client_verified, $tablename, $api_log_path);
                return "303;" . $fixing_request_csv; // 303 => "Transaction completed and data key
                                                         // mismatch detected."
            } else
                return "300;ok."; // 300 => "Transaction completed."
        } else
            return "502;" . $result; // 502 => "Transaction failed."
    }

    /**
     * Update a record within a table using the API syntax and return the result as
     * "<result_code>;<result_message>". Set the LastModified and LastModification values, if not yet set.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param array $tablename
     *            the table out of which the record shall be updated.
     * @param array $record
     *            record which shall be used for updating.
     */
    public function api_update (array $client_verified, String $tablename, array $record)
    {
        // Check provided key and existing record.
        $key = $this->getServerKey($tablename, $record);
        if ($key === false)
            return "502;Cannot update record, key is incomplete or missing.";
        $record_matched = $this->get_corresponding_server_record($client_verified, $tablename, $record);
        if ($record_matched === false)
            return "502;Cannot update record, no record matching the given key was found.";
        // update and return result
        $efaCloudUserID = $client_verified[$this->toolbox->users->user_id_field_name];
        if (! isset($record["LastModified"]))
            $record["LastModified"] = time() . "000";
        if (! isset($record["LastModification"]) && $this->has_LastModification_field($tablename))
            $record["LastModification"] = "update";
        $result = $this->socket->update_record_matched($efaCloudUserID, $tablename, $key, $record, true);
        if (strlen($result) == 0)
            return "300;ok."; // 300 => "Transaction successful."
        else
            return "502;" . $result; // 502 => "Transaction failed."
    }

    /**
     * Delete a record within a table using the API syntax and return the result as
     * "<result_code>;<result_message>". For the 16 common efa tables data records are not deleted, but rather
     * emptied except the data key and the LastModified and Last Modification fields. That is the only way to
     * inform an offline client afterwards about the record deletion.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param array $tablename
     *            the table out of which the record shall be deleted.
     * @param array $record_or_key
     *            record, or at least the key of the record which shall be deleted.
     */
    public function api_delete (array $client_verified, String $tablename, array $record_or_key)
    {
        // Check provided key and existing record. Error: 502 => "Transaction failed."
        $cleansed_key = $this->getServerKey($tablename, $record_or_key);
        if ($cleansed_key === false)
            return "502;Cannot delete record, key is incomplete or missing.";
        $record_matched = $this->get_corresponding_server_record($client_verified, $tablename, $record_or_key);
        if ($record_matched === false)
            return "502;Cannot delete record, no record matching the given key was found.";
        
        // if is efa2 table empty the reord and update it rather than delete it
        $efaCloudUserID = $client_verified[$this->toolbox->users->user_id_field_name];
        if ($this->has_LastModification_field($tablename)) {
            $record_emptied = [];
            foreach ($record_matched as $key => $value)
                $record_emptied[$key] = "";
            foreach ($cleansed_key as $key => $value)
                $record_emptied[$key] = $value;
            $record_emptied["LastModified"] = time() . "000";
            $record_emptied["LastModification"] = "delete";
            $result = $this->socket->update_record_matched($efaCloudUserID, $tablename, $cleansed_key, 
                    $record_emptied, true);
        } else // delete the record
            $result = $this->socket->delete_record_matched($efaCloudUserID, $tablename, $cleansed_key);
        
        // return the result
        if (strlen($result) == 0)
            return "300;ok"; // 300 => "Transaction successful."
        else
            return "502;" . $result; // 502 => "Transaction failed."
    }

    /**
     * Remove a ClientSideKey entry after the mismatch was fixed at the client side
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param array $tablename
     *            the table out of which the record shall be fixed.
     * @param array $fixed_record_reference
     *            reference to fixed record in which the ClientSideKey field shall be removed. Usually just
     *            the records server side data key.
     * @param String $api_log_path
     *            path to write the keyfixing activities to
     */
    public function api_keyfixing (array $client_verified, String $tablename, array $fixed_record_reference, 
            String $api_log_path)
    {
        if (strpos($this->fixid_allowed, $tablename) === false)
            // 502 => "Transaction failed." if the table must not be fixed.
            return "502;" . $tablename . ": no key fixing for this table.";
        
        // the keyfixing record for the logbook contains the Logbookname, even if there is no key to fix
        $is_empty_record = (count($fixed_record_reference) == 0) || ((count($fixed_record_reference) == 1) &&
                 (strcasecmp($tablename, "efa2logbook") == 0) && (isset(
                        $fixed_record_reference["Logbookname"])));
        
        // keyfixing may be called with an empty $key_of_fixed_record to get the next mismatching
        // record's key. If, however, a $key_of_fixed_record is provided, fix it
        if (! $is_empty_record) {
            $server_key_of_fixed_record = $this->getServerKey($tablename, $fixed_record_reference);
            if ($server_key_of_fixed_record === false)
                // 502 => "Transaction failed." if the table must not be fixed.
                return "502;" . $tablename . ": incomplete key for fixing in this table. Record: " .
                         json_encode($fixed_record_reference) . ", expected key fields: " .
                         json_encode(self::$key_fields[$tablename]);
            // get record to fix
            $record_to_remove_clientsidekey = $this->socket->find_record_matched($tablename, 
                    $server_key_of_fixed_record);
            // fix it, if found. Ignore, if not.
            if ($record_to_remove_clientsidekey) {
                // replace clientSideKey entry be a "previous key" remark
                $previous = explode(":", $record_to_remove_clientsidekey["ClientSideKey"]);
                $update_fields_for_fixed_record["ClientSideKey"] = "corrected from " . $previous[1] .
                         " at client " . $previous[0];
                $res = $this->socket->update_record_matched(
                        $client_verified[$this->toolbox->users->user_id_field_name], $tablename, 
                        $server_key_of_fixed_record, $update_fields_for_fixed_record);
            }
        }
        
        // check for more key which need fixing
        $return_message = $this->get_next_key_to_fix($client_verified, $tablename, $api_log_path);
        if (! $return_message)
            return "300;";
        else
            return "303;" . $return_message;
    }

    /**
     * ===== READ DATA FROM EFACLOUD =================================================
     */
    /**
     * Return a list of a table using the API syntax and return the result as "<result-code><rms><result>"
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param String $table_name
     *            the name of the table to be created.
     * @param array $filter
     *            the column to be made unique.
     * @param bool $keys_only
     *            set true to return the data keys and modification of records matching rather than the full
     *            records themselves.
     */
    public function api_select (array $client_verified, String $table_name, array $filter, bool $keys_only)
    {
        $condition = "=";
        $logbookname = "";
        if ($filter["?"]) {
            $condition = $filter["?"];
            unset($filter["?"]);
        }
        if (strcasecmp($table_name, "@All") == 0) {
            $tnames = $this->socket->get_table_names(true);
            $ret = "300;";
            foreach ($tnames as $tname) {
                $records = $this->socket->find_records_sorted_matched($tname, $filter, 10000, $condition, 
                        false, true, true);
                if ($records !== false)
                    $ret .= $tname . "=" . count($records) . ";";
                else
                    $ret .= $tname . "=0;";
            }
            return $ret; // substr($ret, 0, strlen($ret) - 1);
        }
        // add the condition to match the logbook, if the loogbookname is part of the filter,
        $isLogbooktable = (strcasecmp($table_name, "efa2logbook") == 0);
        if ($isLogbooktable && isset($filter["Logbookname"]))
            $condition .= ",=";
        // get the records
        $records = $this->socket->find_records_sorted_matched($table_name, $filter, 10000, $condition, false, 
                true, false);
        if (! $records)
            return "300;";
        
        // build table
        $csvtable = "";
        $header = ""; // header row to be created just at top
        $key_fields = self::$key_fields[$table_name];
        $key_field_list = ",";
        foreach ($key_fields as $key_field)
            $key_field_list .= (!$isLogbooktable || (strcasecmp($key_field, "Logbookname") != 0)) ? $key_field . "," : "";
        
        $key_field_list .= ",LastModified,LastModification,";
        // Note: LastModification will be needed at the server side for synchronization purposes
        $fields_to_exclude_from_full = ",ClientSideEntryId,Logbookname,";
        $isFirstRow = true; // filter to identify, whether a header shall be created
                            // iterate through all rows
        foreach ($records as $record) {
            // drop empty rows
            if (count($record) > 0) {
                $csvrow = "";
                foreach ($record as $key => $value) {
                    $key_checker = "," . $key . ",";
                    $use_column = ($keys_only) ? (strpos($key_field_list, $key_checker) !== false) : (strpos(
                            $fields_to_exclude_from_full, $key_checker) === false);
                    if ($use_column) {
                        if ($isFirstRow)
                            $header .= $key . ";";
                            if ((strpos($value, ";") !== false) || (strpos($value, "\n") !== false) || (strpos($value, "\"") !== false))
                            $csvrow .= '"' . str_replace('"', '""', $value) . '";';
                        else
                            $csvrow .= $value . ';';
                    }
                }
                
                // before writing the first row, put the header w/o the dangling ';'
                if ($isFirstRow)
                    $csvtable = substr($header, 0, strlen($header) - 1) . "\n";
                $isFirstRow = false;
                // put the row w/o the dangling ';'
                if (strlen($csvrow) > 0)
                    $csvrow = substr($csvrow, 0, strlen($csvrow) - 1);
                $csvtable .= $csvrow . "\n";
            }
        }
        // cut off the last \n and return the result.
        if (strlen($csvtable) > 0)
            $csvtable = substr($csvtable, 0, strlen($csvtable) - 1);
        return "300;" . $csvtable;
    }

    /**
     * Return a list of a table using the API syntax and return the result as "<result-code><rms><result>"
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param String $listname
     *            the column to be made unique.
     */
    public function api_list (array $client_verified, String $listname)
    {
        include_once "../classes/tfyh_list.php";
        $list = new Tfyh_list("../config/lists/api", 0, $listname, $this->socket, $this->toolbox);
        return "300;" . $list->get_csv($client_verified);
    }

    /**
     * ===== SUPPORT FUNCTIONS =======================================================
     */
    /**
     * Trigger a backup of all tables creating a zip archive of text files. There is a two stage backup
     * process with 10 backups at each stage. So this gives you 10 days daily backup and 10 backups with a 10
     * day period between each, i. e. a 100 day backup regime. This function will also trigger a move of the
     * API log file to an indexed version, when a secondary backup is triggered. By this also the api log is
     * regularly moved and overwritten.
     * 
     * @param String $api_log_path
     *            the log path for the api transactions
     * @return string the transaction result
     */
    public function api_backup (String $api_log_path)
    {
        include_once "../classes/backup_handler.php";
        $backup_handler = new Backup_handler("../log/", $this->toolbox, $this->socket);
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
        return "300;jobs comopleted";
    }
}