<?php

/**
 * class file for the efaCloud table auditing
 */
include_once "../classes/efa_tables.php";
include_once "../classes/tfyh_list.php";

class Efa_archive
{

    /**
     * The prefix identifying an archived record.
     */
    public static $archive_id_prefix = "archiveID:";

    /**
     * The archive settings. ITS SEQUENCE MUST BE THE SAME AS FOR THE LISTS IN '../config/lists/efaArchive'.
     * This is also the complete list of tables for which records will be archived at all. The default for
     * corresponds to Efa_tables::$forever_days.
     */
    public static $archive_settings = [
            "efa2boatdamages" => ["ListParam" => "DamageAgeDays","deleteAtOrigin" => true,
                    "archiveID_at" => "Notes"
            ],
            "efa2boatreservations" => ["ListParam" => "ReservationAgeDays","deleteAtOrigin" => true,
                    "archiveID_at" => "Reason"
            ],
            "efa2clubwork" => ["ListParam" => "ClubworkAgeDays","deleteAtOrigin" => true,
                    "archiveID_at" => "Description"
            ],
            "efa2logbook" => ["ListParam" => "SessionAgeDays","deleteAtOrigin" => true,
                    "archiveID_at" => "Comments"
            ],
            "efa2messages" => ["ListParam" => "MessageAgeDays","deleteAtOrigin" => true,
                    "archiveID_at" => "Subject"
            ],
            "efa2persons" => ["ListParam" => "PersonsAgeDays","deleteAtOrigin" => false,
                    "archiveID_at" => "LastName"
            ]
    ];

    /**
     * The data base connection socket.
     */
    private $socket;

    /**
     * The generic toolbox.
     */
    private $toolbox;

    /**
     * The afCloudUserID of the archiving user.
     */
    private $app_user_id;

    /**
     * Maximm number of records to be archived for a table in one go, for performance reasons
     */
    private $max_count_archived = 250;

    /**
     * public Constructor.
     * 
     * @param Tfyh_toolbox $toolbox
     *            application toolbox
     * @param Tfyh_socket $socket
     *            the socket to connect to the database
     * @param int $appUserID
     *            the ID of the application user of the user who performs the statement. For change logging.
     */
    public function __construct (Tfyh_toolbox $toolbox, Tfyh_socket $socket, int $appUserID)
    {
        $this->toolbox = $toolbox;
        $this->socket = $socket;
        $this->app_user_id = $appUserID;
        $id = 1;
        $cfg = $toolbox->config->get_cfg();
        foreach (self::$archive_settings as $for_table => $archive_setting) {
            // Get the minimum age in days for archiving configuration.
            $configured = (isset($cfg[$archive_setting["ListParam"]])) ? intval(
                    $cfg[$archive_setting["ListParam"]]) : 0;
            // default is forever, i. e. no archiving at all.
            if ($configured <= 0)
                $configured = Efa_tables::$forever_days;
            // set the parameter to use to be minimum 180 days
            self::$archive_settings[$for_table]["MinAgeDays"] = max($configured, 180);
            $archive_list = new Tfyh_list("../config/lists/efaArchive", $id, "", $this->socket, $toolbox);
            if (strcmp($archive_list->get_table_name(), $for_table) != 0) {
                echo i(
                        "Pb2uDW|The order of the archive...", 
                        $for_table);
                exit();
            }
            $id ++;
        }
    }

    /**
     * Convert the efa-validity value (millis, forever = Long.MAX_VALUE) into a 32 bit integer (seconds,
     * forever = Imteger.MAX_VALUE)
     * 
     * @param String $validity
     *            the efa-validity value
     * @return number the resulting 32 bit integer
     */
    private function value32_validity (String $validity)
    {
        if (strlen($validity) > Efa_tables::$forever_len_gt)
            return Efa_tables::$forever32int; // 32 bit maximum number
        if (strlen($validity) > 3)
            return intval(substr($validity, 0, strlen($validity) - 3));
        return 0;
    }

    /**
     * Copy a record as json encoded String except its history to the archive. This will not change the record
     * itself.
     * 
     * @param String $tablename
     *            the name of the table to be used.
     * @param array $record
     *            a named array with key = column name and value = values to be inserted. Values must be PHP
     *            native encoded Strings. Enclosed quotes "'" will be appropriately escaped for the SQL
     *            command.
     * @return the ID (integer) of the copy in the archive table, or an error String, if the insterion failed.
     */
    private function copy_to_archive (String $tablename, array $record)
    {
        // remove record history
        if (isset($record["ecrhis"]) || is_null($record["ecrhis"]))
            unset($record["ecrhis"]);
        $archive_entry = json_encode($record);
        // limit size to 64k
        $cut_len = 65535 - 4096;
        while (strlen($archive_entry) > 65535) { // strlen == byte length
            foreach ($record as $key => $value)
                if (strlen($value) > $cut_len)
                    $record[$key] = substr(strval($record[$key]), 0, $cut_len);
            $archive_entry = json_encode($record);
            $cut_len = $cut_len - 4096;
        }
        $archive_record = ["Time" => date("Y-m-d H:i:s"),"Table" => $tablename,"Record" => $archive_entry
        ];
        $archive_ID = $this->socket->insert_into($this->app_user_id, "efaCloudArchived", $archive_record);
        return $archive_ID;
    }

    /**
     * Move a record to the archive based on the provided list element. The record is retreived, than copied
     * to the archive table, then emptied and marked as deleted or updated, depending on whether a minimum
     * record stub shall be kept for referential integrity and finally written to the data base. Any error
     * will be logged.
     * 
     * @param array $named_row_to_move
     *            the named list row indicaion the record that shall be archived. Must contain the ecrid for
     *            reference.
     * @param String $table_name
     *            the table it belongs to
     */
    private function move_to_archive (array $named_row_to_move, String $table_name)
    {
        if (! isset($named_row_to_move["ecrid"]) || (strlen($named_row_to_move["ecrid"]) < 5)) {
            $this->toolbox->logger->log(2, $this->app_user_id, 
                    i("xfM8pp|Failed to archive Id: %1...", $named_row_to_move[0], 
                            $table_name));
            return false;
        }
        $ecrid = $named_row_to_move["ecrid"];
        
        // retrieve the full record
        $full_record_to_move = $this->socket->find_record($table_name, "ecrid", $ecrid);
        if ($full_record_to_move === false) {
            $this->toolbox->logger->log(2, $this->app_user_id, 
                    i("WsILpP|Failed to archive Id: %1...", 
                            $named_row_to_move[0], $table_name, $ecrid));
            return false;
        }
        
        // copy the record to the archive.
        $archive_id = $this->copy_to_archive($table_name, $full_record_to_move);
        if (! is_numeric($archive_id)) {
            $this->toolbox->logger->log(2, $this->app_user_id, 
                    i("Ll1PcH|Failed to copy Id: %1 fo...", $named_row_to_move[0], $table_name)) .
                     " " . $archive_id;
            return false;
        }
        
        // delete the record or create the stub
        if (self::$archive_settings["$table_name"]["deleteAtOrigin"]) {
            $this->socket->delete_record_matched($this->app_user_id, $table_name, 
                    ["ecrid" => $ecrid
                    ]);
        } else {
            $nominal_stub = $this->create_archive_stub($table_name, $archive_id, $full_record_to_move);
            $nominal_stub = Efa_tables::register_modification($nominal_stub, time(), 
                    $full_record_to_move["ChangeCount"], "insert");
            if (in_array("ecrhis", Efa_tables::$server_gen_fields[$table_name]))
                $nominal_stub["ecrhis"] = "REMOVE!";
            $update_result = $this->socket->update_record_matched($this->app_user_id, $table_name, 
                    ["ecrid" => $ecrid
                    ], $nominal_stub);
            if (strlen($update_result) > 0) {
                $this->toolbox->logger->log(2, $this->app_user_id, 
                        i("3tX6IN|Failed to empty or delet...", 
                                $named_row_to_move[0], $table_name)) . " " . $update_result;
                return false;
            }
        }
        return true;
    }

    /**
     * Get all records archived for the object which is archived in $archive_record.
     * 
     * @param array $archive_record
     *            the archive record as stored in the efaCloudArchived table, containing the record to decode
     *            in the $archive_record["Record"] field
     * @return array of archive records which belong to the archived object, sorted youngest first.
     *         Associative with $key = InvalidFrom in seconds. If this record is the only one, returns an
     *         array with just one element. If the record does not belong to a cersionized table, false is
     *         returned.
     */
    public function get_all_archived_versions (array $archive_record)
    {
        if (! in_array($archive_record["Table"], Efa_tables::$versionized_table_names))
            return false;
        $archived_record = $this->decode_archived_record($archive_record);
        if (! isset($archived_record["Id"]))
            return false;
        $id = $archived_record["Id"];
        $id_entry = '"Id":"' . $id . '"';
        $list_args = ["{IdEntry}" => $id_entry
        ];
        $object_list = new Tfyh_list("../config/lists/efaArchive", 9, "", $this->socket, $this->toolbox, 
                $list_args);
        $object_rows = $object_list->get_rows();
        $object_records = [];
        foreach ($object_rows as $object_row) {
            $object_archive_record = $object_list->get_named_row($object_row);
            $object_archived_record = $this->decode_archived_record($object_archive_record);
            $invalidFrom32 = Efa_tables::value_validity32($object_archived_record["InvalidFrom"]);
            $object_records[$invalidFrom32] = $object_archive_record;
        }
        ksort($object_records);
        return $object_records;
    }

    /**
     * Decode the json encoded archived record
     * 
     * @param array $archive_record
     *            the archive record as stored in the efaCloudArchived table, containing the record to decode
     *            in the $archive_record["Record"] field
     * @return array the "Record" decoded to an associate array, from the json String.
     */
    public function decode_archived_record (array $archive_record)
    {
        if (! isset($archive_record["Record"]))
            return false;
        // see
        // https://stackoverflow.com/questions/24312715/json-encode-returns-null-json-last-error-msg-gives-control-character-error-po
        $ctrl_replaced = preg_replace('/[[:cntrl:]]/', '', $archive_record["Record"]);
        return json_decode($ctrl_replaced, true);
    }

    /**
     * Create an archive reference record to be used in the origin table. Please remember to register the
     * modification afterwards in order to ensure propagation to clients.
     * 
     * @param String $tablename
     *            the name of the table the record belongs to
     * @param int $archive_id
     *            the ID of the archive record to link to
     * @param array $full_record
     *            the full record to create the stub from
     */
    private function create_archive_stub (String $tablename, int $archive_id, array $full_record)
    {
        $is_efa2persons = strcmp($tablename, "efa2persons") == 0;
        $is_efa2logbook = strcmp($tablename, "efa2logbook") == 0;
        // if so, continue by creating the nominal stub
        include_once "../classes/efa_record.php";
        $nominal_stub = Efa_record::clear_record_for_delete($tablename, $full_record);
        $nominal_stub["LastModification"] = (self::$archive_settings[$tablename]["deleteAtOrigin"]) ? "delete" : "update";
        // add the archive ID to provide a link to the archived record
        $archive_id_reference = self::$archive_id_prefix . $archive_id;
        $nominal_stub[self::$archive_settings[$tablename]["archiveID_at"]] = $archive_id_reference;
        // add the virtual fields like in the cronjob routine to avoid ping-pong between
        // cronjob virtual field generation in stubs and stub autocorrection.
        $nominal_stub_plus_vf = Efa_tables::add_virtual_fields($nominal_stub, $tablename, $this->toolbox, 
                $this->socket);
        if ($nominal_stub_plus_vf !== false)
            $nominal_stub = $nominal_stub_plus_vf;
        if (in_array("ecrhis", Efa_tables::$server_gen_fields[$tablename]))
            $nominal_stub["ecrhis"] = "";
        return $nominal_stub;
    }

    /**
     * Look through all archive records for the table $tablename, check whether a referencing stub is needed
     * within the table itself and fix it, if needed. To fix a stub a nominally correct stub is build based on
     * the archived record. It is inserted, if it is missing. If a stub is there, the nominal and the
     * currently available stub are compared and the stub replaced, if it is different. In order to force
     * synchronisation the stub will always get the current time as LastModified timestamp.
     * 
     * @param String $tablename
     *            the table to check and fix the stubs for
     * @param int $min_age_days_for_check
     *            the minimum age in days of the stubs which shall be checked to avoid daily checking, in
     *            particularly of the larger records and tables.
     * @return number the count of fixed stubs
     */
    public function autocorrect_archive_stubs (String $tablename, int $min_age_days_for_check)
    {
        // prepare activity
        $start_row = 0;
        $chunk_size = 100;
        $checked = 0;
        $corrected = 0;
        $failed = 0;
        $skipped = 0;
        $dublets = 0;
        $ecrids_handled = [];
        $is_delete_stub = self::$archive_settings[$tablename]["deleteAtOrigin"];
        $last_modification = ($is_delete_stub) ? "delete" : "update";
        $min_age_secs = self::$archive_settings[$tablename]["MinAgeDays"] * 86400;
        $now = time();
        do {
            // Check all existing archived records
            $archive_records = $this->socket->find_records_sorted_matched("efaCloudArchived", 
                    ["Table" => $tablename
                    ], $chunk_size, "=", "ID", true, $start_row);
            if ($archive_records !== false)
                foreach ($archive_records as $archive_record) {
                    $checked ++;
                    $archive_id = $archive_record["ID"];
                    $archived_at = strtotime($archive_record["Time"]);
                    $archived_record = $this->decode_archived_record($archive_record);
                    // check whether this ecrid is a dublet in the archive
                    if (isset($ecrids_handled[$archived_record["ecrid"]])) {
                        if (Efa_tables::records_are_equal($ecrids_handled[$archived_record["ecrid"]], 
                                $archived_record, false)) {
                            $delete_result = $this->socket->delete_record($this->app_user_id, 
                                    "efaCloudArchived", $archive_id);
                            // echo "<br>ecrid dublet removal ID $archive_id: $delete_result<hr>";
                        } else {
                            // echo "<br>ecrid dublet with different content, thus kept.<hr>";
                        }
                        $dublets ++;
                        // check whether a stub is required and can be created.
                    } elseif (isset($archived_record["ecrid"]) && ! isset(
                            $ecrids_handled[$archived_record["ecrid"]]) &&
                             (! $is_delete_stub || ($now - $archived_at <= $min_age_secs))) {
                        $ecrids_handled[$archived_record["ecrid"]] = $archived_record;
                        // if so, continue by creating the nominal stub
                        $nominal_stub = $this->create_archive_stub($tablename, $archive_id, $archived_record);
                        // check the current stub
                        $current_stub = $this->socket->find_record($tablename, "ecrid", 
                                $archived_record["ecrid"]);
                        if ($current_stub === false) {
                            // current stub is missing, insert the nominal stub
                            $nominal_stub = Efa_tables::register_modification($nominal_stub, $now, 
                                    $archived_record["ChangeCount"], "insert");
                            $insert_result = $this->socket->insert_into($this->app_user_id, $tablename, 
                                    $nominal_stub);
                            if (is_numeric($insert_result))
                                $corrected ++;
                            else
                                $failed ++;
                        } elseif ((time() - intval(
                                Efa_tables::value_validity32($current_stub["LastModified"]))) >
                                 $min_age_days_for_check) {
                            // current stub is existing and old enough, check for correctness.
                            // add the Change Management fields, using the current stubs change count
                            $change_count_current = $current_stub["ChangeCount"];
                            $current_stub = Efa_tables::register_modification($current_stub, $now, 
                                    $change_count_current, $current_stub["LastModification"]);
                            // adapt the nominal_stub ChangeCount field for equality checks
                            $nominal_stub = Efa_tables::register_modification($nominal_stub, $now, 
                                    $change_count_current, $last_modification);
                            // compare the current with the nominal stub
                            if (! Efa_tables::records_are_equal($nominal_stub, $current_stub, false)) {
                                if (in_array("ecrhis", Efa_tables::$server_gen_fields[$tablename]))
                                    $nominal_stub["ecrhis"] = "REMOVE!";
                                $change_result = $this->socket->update_record_matched($this->app_user_id, 
                                        $tablename, 
                                        ["ecrid" => $nominal_stub["ecrid"]
                                        ], $nominal_stub);
                                if (strlen($change_result) == 0)
                                    $corrected ++;
                                else
                                    $failed ++;
                            }
                            // else echo "<br>no change.<hr>";
                        } else
                            // echo "<br>skipped.<hr>";
                            $skipped ++;
                    }
                }
            $start_row += $chunk_size;
        } while (($archive_records !== false) && (count($archive_records) > 0) &&
                 ($corrected < $this->max_count_archived));
        
        if ($corrected == 0)
            $result = i("XIGJqp|checked/total count: %1/...", strval($checked - $skipped), 
                    $checked);
        else
            $result = i(
                    "mfU3JA|checked: %1, corrected: ...", 
                    $checked, $corrected, $failed, $dublets);
        return $result;
    }

    /**
     * Get the time in seconds which reflect the content's age for a non versionized record. For the logbook
     * this ist the sessions start date, for a person the InvalidFrom timestamp of the youngest version.
     * 
     * @param String $tablename
     *            the name of the table the record belongs to
     * @param array $record
     *            the record to determine the age for. The fields used are: efa2boatdamages.ReportDate,
     *            efa2boatreservations.DateFrom, efa2clubwork, efa2logbook, efa2messages.Date, all other:
     *            LastModified.
     * @return int as time in seconds of the "birth" of the record
     */
    public function time_of_non_versionized_record (String $tablename, array $record)
    {
        if (strcmp($tablename, "efa2boatdamages") == 0) {
            $creation_date = $this->toolbox->check_and_format_date($record["ReportDate"]);
        } elseif (strcmp($tablename, "efa2boatreservations") == 0) {
            $creation_date = $this->toolbox->check_and_format_date($record["DateFrom"]);
        } elseif ((strcmp($tablename, "efa2clubwork") == 0) || (strcmp($tablename, "efa2logbook") == 0) ||
                 (strcmp($tablename, "efa2messages") == 0)) {
            $creation_date = $this->toolbox->check_and_format_date($record["Date"]);
        }
        // Fallback: use LastModified timestamp.
        if ($creation_date === false)
            return $this->value32_validity($record["LastModified"]);
        // set 1970-01-01 as value for null Dates or invalid dates
        if (strlen($creation_date) == 0)
            $creation_date = "1970-01-01";
        $time_of_creation_date = strtotime($creation_date);
        if ($time_of_creation_date !== false)
            return $time_of_creation_date;
    }

    /**
     * Move versionized objects to the archive. This moves all records of all due objects (currently just
     * person objects) to the archive. The object is due, if the most recent version has come to the maximum
     * age. After being copied to the archive, the records in the originating table are replaced by reference
     * stubs.
     * 
     * @param Tfyh_list $versionized_list
     *            The list of all records to be checked for archiving.
     */
    private function versionized_to_archive (Tfyh_list $versionized_list)
    {
        $last_uuid = "";
        $table_name = $versionized_list->get_table_name();
        
        $pos_field_for_uuid = $versionized_list->get_field_index("Id");
        $pos_field_for_invalidFrom = $versionized_list->get_field_index("InvalidFrom");
        // abort on inconsistency of programmed application configuration
        if (($pos_field_for_uuid === false) || ($pos_field_for_invalidFrom === false)) {
            echo i(
                    "deBLXi|Efa_archive::versionized...", 
                    $table_name);
            exit();
        }
        $pos_of_archive_id_in_list = $versionized_list->get_field_index(
                self::$archive_settings[$table_name]["archiveID_at"]);
        // abort on inconsistency of programmed application configuration
        if ($pos_of_archive_id_in_list === false) {
            echo i(
                    "4c2kZc|Efa_archive::versionized...", 
                    $table_name);
            exit();
        }
        
        $versionized_rows = $versionized_list->get_rows();
        $count_archived = 0;
        $count_failed = 0;
        $min_age_secs = self::$archive_settings[$table_name]["MinAgeDays"] * 86400;
        
        $this_id_to_archive = false; // default, the value will be set with the first not archived row.
        foreach ($versionized_rows as $versionized_row) {
            // the first record of an object is kept, thus needs special treatment.
            $first_record_of_id = strcmp($versionized_row[$pos_field_for_uuid], $last_uuid) != 0;
            $invalidFromSecs = (is_null($versionized_row[$pos_field_for_invalidFrom])) ? 0 : Efa_tables::value_validity32(
                    $versionized_row[$pos_field_for_invalidFrom]);
            // check whether this object was already archived.
            $is_archived = $first_record_of_id && (strpos($versionized_row[$pos_of_archive_id_in_list], 
                    Efa_archive::$archive_id_prefix) !== false);
            
            // check whether this object shall be archived.
            $this_id_to_archive = ($first_record_of_id && ! $is_archived) ? ((time() - $invalidFromSecs) >
                     $min_age_secs) : $this_id_to_archive;
            if ($this_id_to_archive && ($count_archived < $this->max_count_archived)) {
                $named_row = $versionized_list->get_named_row($versionized_row);
                $success = $this->move_to_archive($named_row, $table_name);
                if ($success)
                    $count_archived ++;
                else
                    $count_failed ++;
            }
            $last_uuid = $versionized_row[0];
        }
        if (($count_archived + $count_failed) >= 0)
            return $table_name . ": " . $count_archived . "/" . strval($count_archived + $count_failed) . ", ";
        else
            return "";
    }

    /**
     * Move non-versionized records to the archive. The record is due, if it has reached the maximum age.
     * After being copied to the archive, the record is emptied and the 'LastModification' field set to
     * 'delete'. THERE IS CURRENTLY NO CHECK FOR REFERENTIAL INTEGRITY, because the non-versionized tables
     * selected for archiving do not require such a check.
     * 
     * @param Tfyh_list $simple_list
     *            The list of the records to be archived.
     * @param String $parameter_name
     *            the name of the configuration parameter holding the maximum age.
     */
    private function non_versionized_to_archive (Tfyh_list $simple_list)
    {
        $table_name = $simple_list->get_table_name();
        // simple lists are already filtered for records to archive
        $simple_rows = $simple_list->get_rows();
        $count_archived = 0;
        $count_failed = 0;
        $min_age_secs = self::$archive_settings[$table_name]["MinAgeDays"] * 86400;
        foreach ($simple_rows as $simple_row) {
            $named_row = $simple_list->get_named_row($simple_row);
            // THERE IS CURRENTLY NO CHECK FOR REFERENTIAL INTEGRITY, NEED CAN ARISE IF TABLES ARE ADDED.
            $time_of_record = $this->time_of_non_versionized_record($table_name, $named_row);
            if (((time() - $time_of_record) > $min_age_secs) && ($count_archived < $this->max_count_archived)) {
                $success = $this->move_to_archive($named_row, $table_name);
                if ($success)
                    $count_archived ++;
                else
                    $count_failed ++;
            }
        }
        if (($count_archived + $count_failed) >= 0)
            return $table_name . ": " . $count_archived . "/" . strval($count_archived + $count_failed) . ", ";
        else
            return "";
    }

    /**
     * Restore a single record from the archive. In case of success the $archive_record will be removed from
     * the efaCloudArchived table and an empty String is returned. In case of failure an error message ist
     * returned.
     * 
     * @param array $archive_record
     *            the archive record to be restored as associative array.
     */
    private function restore_one_from_archive (array $archive_record)
    {
        $result_message = "";
        $archive_id = $archive_record["ID"];
        $tablename = $archive_record["Table"];
        $archived_at = strtotime($archive_record["Time"]);
        $archived_record = $this->decode_archived_record($archive_record);
        if (! is_null($archived_record) && is_array($archived_record)) {
            $stub = $this->socket->find_record_matched($tablename, 
                    ["ecrid" => $archived_record["ecrid"]
                    ]);
            if ($stub !== false) {
                // if a stub is existing, use its ChangeCount to trigger synchronisation ...
                $restore_record = Efa_tables::register_modification($archived_record, time(), 
                        $stub["ChangeCount"], "update");
                // ... and update the stub.
                $update_result = $this->socket->update_record_matched($this->app_user_id, $tablename, 
                        ["ecrid" => $archived_record["ecrid"]
                        ], $restore_record);
                if (strlen($update_result) == 0) {
                    // delete the archived record after restore
                    $this->socket->delete_record_matched($this->app_user_id, "efaCloudArchived", 
                            ["ID" => $archive_id
                            ]);
                } else {
                    $result_message .= i(
                            "VG4gUV|The archive record #%1 c...", 
                            $archive_id) . " " . $update_result . "<br>";
                }
            } else {
                // if no stub is there, the archived records ChangeCount ...
                $restore_record = Efa_tables::register_modification($archived_record, time(), 
                        $archived_record["ChangeCount"], "update");
                // and insert the record instead of updating.
                $insert_result = $this->socket->insert_into($this->app_user_id, $tablename, $restore_record);
                if (is_numeric($insert_result)) {
                    // if the deletion of the archive record fails, this is ignored. The ecrid uniqueness will
                    // prevent the record from duplication when trying to again restore the record.
                    $this->socket->delete_record_matched($this->app_user_id, "efaCloudArchived", 
                            ["ID" => $archive_id
                            ]);
                } else {
                    $result_message .= i(
                            "o1UBxC|The archive record #%1 c...", 
                            $archive_id) . " " . $insert_result . "<br>";
                }
            }
        } else {
            $result_message .= i(
                    "CZ56mL|Archive record #%1 could...", 
                    $archive_id) . "<br>";
        }
        return $result_message;
    }

    /**
     * Restore all records of a table which were archived less than $archived_less_than_days_ago.
     */
    public function restore_form_archive (String $tablename, int $archived_less_than_days_ago)
    {
        try {
            $restore_list_args = ["{ArchivedLessThanDaysAgo}" => $archived_less_than_days_ago,
                    "{Table}" => $tablename
            ];
            $restore_list = new Tfyh_list("../config/lists/efaArchive", count(self::$archive_settings) + 1, "", 
                    $this->socket, $this->toolbox, $restore_list_args);
            $restore_rows = $restore_list->get_rows();
            $successes = 0;
            $failed = 0;
            $failure_log = "";
            foreach ($restore_rows as $restore_row) {
                $archive_record = $restore_list->get_named_row($restore_row);
            }
            return i(
                    "0LiLBm|Restore completed for re...", 
                    $tablename, $archived_less_than_days_ago, $successes, $failed) . " " . $failure_log;
        } catch (Exception $e) {
            return i(
                    "bZbb82|The restore for records ...", 
                    $tablename, $archived_less_than_days_ago) . $e->getMessage();
        }
    }

    /**
     * Move all due records to the archive
     */
    public function records_to_archive ()
    {
        $id = 1;
        $info = "";
        
        // define the list to use
        foreach (self::$archive_settings as $for_table => $archive_setting) {
            // The archiving trigger can never be less than 180 days.
            $list_args = ["{" . $archive_setting["ListParam"] . "}" => $archive_setting["MinAgeDays"]
            ]; // These arguments are not needed for the versionized list, but do no harm.
            $archive_target_list = new Tfyh_list("../config/lists/efaArchive", $id, "", $this->socket, 
                    $this->toolbox, $list_args);
            // The table name is needed to ditinguish the handling
            $table_name = $archive_target_list->get_table_name();
            if (in_array($table_name, Efa_tables::$versionized_table_names)) {
                $info .= $this->versionized_to_archive($archive_target_list, $archive_setting);
            } else {
                $info .= $this->non_versionized_to_archive($archive_target_list);
            }
            $id ++;
        }
        if (strlen($info) == 0)
            $info = i("bKEeu8|no records to be archive...");
        else
            $info = mb_substr($info, 0, mb_strlen($info) - 2);
        return $info;
    }
}
