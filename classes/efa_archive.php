<?php

/**
 * class file for the efaCloud table auditing
 */
class Efa_archive
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
     * The efa tables function set.
     */
    private $efa_tables;

    /**
     * a copy of the configuration for convenience.
     */
    private $cfg;

    /**
     * The afCloudUserID of the archiving user.
     */
    private $app_user_id;

    /**
     * The prefix identifying an archived record.
     */
    public static $archive_id_prefix = "archiveID:";

    /**
     * The field, to which the archive Id shall be written. When archived, records may be still needed by the
     * database for referential integrity. If so the most recent record or a uuid is emptied and kept with the
     * reference to the archived record in its name field. The existence of the reference will ensure both
     * uniqueness of the name field and the possibility to prevent from repetitive archiving.
     */
    public $field_for_archive_id = ["efa2persons" => "LastName"
    ];

    /**
     * The list argument used to filter the age records, sequence must be the same as for the lists in
     * ../config/lists/efaArchive. This is implicitly also the complete list of tables for which records will
     * be archived at all.
     */
    private $age_parameters = ["DamageAgeDays","ReservationAgeDays","ClubworkAgeDays","TripAgeDays",
            "MessageAgeDays","PersonsAgeDays"
    
    ];

    /**
     * The list of tables in which records which are marked as deleted shall be finally purge. Note: they must
     * be kept to inform all clients of their deletion. Once they are purged, a client will no more be
     * notified of this deletion.
     */
    private $tables_to_purge_deleted = ["efa2autoincrement","efa2boatdamages","efa2boatreservations",
            "efa2boats","efa2boatstatus","efa2clubwork","efa2crews","efa2destinations","efa2fahrtenabzeichen",
            "efa2groups","efa2logbook","efa2messages","efa2persons","efa2sessiongroups","efa2statistics",
            "efa2status","efa2waters"
    ];

    /**
     * public Constructor.
     * 
     * @param int $appUserID
     *            the ID of the application user of the user who performs the statement. For change logging.
     */
    public function __construct (Efa_tables $efa_tables, Tfyh_toolbox $toolbox, int $appUserID)
    {
        $this->efa_tables = $efa_tables;
        $this->socket = $efa_tables->socket;
        $this->toolbox = $toolbox;
        $this->app_user_id = $appUserID;
        $this->cfg = $toolbox->config->get_cfg();
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
        if (strlen($validity) > $this->efa_tables->forever_len_gt)
            return $this->efa_tables->forever32; // 32 bit maximum number
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
        while (strlen($archive_entry) > 65535) {
            foreach ($record as $key => $value)
                if (strlen($value) > $cut_len)
                    $record[$key] = substr(strval($record[$key]), 0, $cut_len);
            $archive_entry = json_encode($record);
            $cut_len = $cut_len - 4096;
        }
        $archive_record = ["Time" => date("Y-m-d H:i:s"),"Table" => $tablename,"Record" => $archive_entry
        ];
        // === test code
        // file_put_contents("../log/tmp", date("Y-m-d H:i:s") . " - " . $tablename . ": Would copy to
        // archive: " . $archive_entry . "\n", FILE_APPEND);
        // $archive_ID = 999999;
        // === test code
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
     * @param bool $keep_min_for_reference
     *            set true to keep a minimum record stub for referential integrity.
     */
    private function move_to_archive (array $named_row_to_move, String $table_name, 
            bool $keep_min_for_reference)
    {
        if (! isset($named_row_to_move["ecrid"]) || (strlen($named_row_to_move["ecrid"]) < 5)) {
            $this->toolbox->logger->log(2, $this->app_user_id, 
                    "Failed to archive Id: " . $named_row_to_move[0] . " for " . $table_name .
                             ". No ecrid available.");
            return false;
        }
        $ecrid = $named_row_to_move["ecrid"];
        
        // retrieve the full record
        $full_record_to_move = $this->socket->find_record($table_name, "ecrid", $ecrid);
        if ($full_record_to_move === false) {
            $this->toolbox->logger->log(2, $this->app_user_id, 
                    "Failed to archive Id: " . $named_row_to_move[0] . " for " . $table_name .
                             ". No matching record found for ecrid " . $ecrid);
            return false;
        }
        
        // copy the record to the archive.
        $archive_id = $this->copy_to_archive($table_name, $full_record_to_move);
        if (! is_numeric($archive_id)) {
            $this->toolbox->logger->log(2, $this->app_user_id, 
                    "Failed to copy Id: " . $named_row_to_move[0] . " for " . $table_name .
                             " to archive. Reason: ") . $archive_id;
            return false;
        }
        
        // clear the current record
        $emptied_record = $this->efa_tables->clear_record_for_delete($table_name, $full_record_to_move);
        if (isset($this->field_for_archive_id[$table_name]))
            // Replace Name by reference to archive ID, ensures also uniqueness of thus pseudononymized name
            // field
            $emptied_record[$this->field_for_archive_id[$table_name]] = Efa_archive::$archive_id_prefix .
                     strval($archive_id);
        if ($keep_min_for_reference)
            $emptied_record["LastModification"] = "update";
        
        // === test code
        // file_put_contents("../log/tmp",
        // $table_name . ": Would update record with: " . json_encode($emptied_record) . "\n",
        // FILE_APPEND);
        // $update_result = "";
        // === test code
        $update_result = $this->socket->update_record_matched($this->app_user_id, $table_name, 
                ["ecrid" => $ecrid
                ], $emptied_record);
        if (strlen($update_result) > 0) {
            $this->toolbox->logger->log(2, $this->app_user_id, 
                    "Failed to empty or delete record after archiving: " . $named_row_to_move . " for " .
                             $table_name . ". Error: " . $update_result);
            return false;
        }
        return true;
    }

    /**
     * Move versionized objects to the archive. This moves all records of all due objects (currently just
     * 'person') to the archive. The object is due, if the most recent version has come to the maximum age.
     * After being copied to the archive, all records except the most recent one are of the object are
     * deleted. The most recent one is emptied and kept as such.
     * 
     * @param Tfyh_list $versionized_list
     *            The list of all records to be checked for archiving.
     * @param String $age_parameter
     *            the name of the configuration parameter holding the maximum age.
     */
    private function versionized_to_archive (Tfyh_list $versionized_list, String $age_parameter)
    {
        $last_uuid = "";
        $table_name = $versionized_list->get_table_name();
        
        $max_age = $this->cfg[$age_parameter] * 86400;
        $pos_field_for_uuid = $versionized_list->get_field_index("Id");
        $pos_field_for_invalidFrom = $versionized_list->get_field_index("InvalidFrom");
        if (($pos_field_for_uuid === false) || ($pos_field_for_invalidFrom === false)) {
            $this->toolbox->logger->log(2, $this->app_user_id, 
                    "Failed to start archiving for " . $table_name .
                             ". Missing Id or InvalidFrom in list definition of efaArchive/" .
                             $versionized_list->get_list_name());
            return;
        }
        
        $pos_field_for_archive_id = $versionized_list->get_field_index(
                $this->field_for_archive_id[$table_name]);
        $keep_min_for_reference = (isset($this->field_for_archive_id[$table_name]) &&
                 (strlen($this->field_for_archive_id[$table_name]) > 0));
        $versionized_rows = $versionized_list->get_rows();
        $count_archived = 0;
        $count_failed = 0;
        foreach ($versionized_rows as $versionized_row) {
            // the first record of an object is kept, thus needs special treatment.
            $first_record_of_id = strcmp($versionized_row[$pos_field_for_uuid], $last_uuid) != 0;
            $valid32 = (is_null($versionized_row[$pos_field_for_invalidFrom])) ? 0 : $this->efa_tables->value_validity32(
                    $versionized_row[$pos_field_for_invalidFrom]);
            // check whether this object was already archived.
            $is_archived = $first_record_of_id && ($pos_field_for_archive_id !== false) && (strpos(
                    $versionized_row[$pos_field_for_archive_id], Efa_archive::$archive_id_prefix) !== false);
            // check whether this object shall be archived.
            $this_id_to_archive = ($first_record_of_id && ! $is_archived) ? (time() - $valid32) > $max_age : $this_id_to_archive;
            if ($this_id_to_archive) {
                $named_row = $versionized_list->get_named_row($versionized_row);
                $success = $this->move_to_archive($named_row, $table_name, 
                        ($first_record_of_id && $keep_min_for_reference));
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
        foreach ($simple_rows as $simple_row) {
            $named_row = $simple_list->get_named_row($simple_row);
            // THERE IS CURRENTLY NO CHECK FOR REFERENTIAL INTEGRITY, NEED CAN ARISE IF TABLES ARE ADDED.
            $success = $this->move_to_archive($named_row, $table_name, false);
            if ($success)
                $count_archived ++;
            else
                $count_failed ++;
        }
        if (($count_archived + $count_failed) >= 0)
            return $table_name . ": " . $count_archived . "/" . strval($count_archived + $count_failed) . ", ";
        else
            return "";
    }

    /**
     * Move all due records to the archive
     */
    public function records_to_archive ()
    {
        $id = 1;
        $info = "";
        
        // define the list to use
        include_once '../classes/tfyh_list.php';
        foreach ($this->age_parameters as $age_parameter) {
            // Default is 100 years, i.e. never archive.
            $age_parameter_value = (isset($this->cfg[$age_parameter]) &&
                     (strlen($this->cfg[$age_parameter]) > 0)) ? intval($this->cfg[$age_parameter]) : 36500;
            $list_args = ["{" . $age_parameter . "}" => $age_parameter_value
            ]; // These arguments are not needed for the versionized list, but do no harm.
            $archive_target_list = new Tfyh_list("../config/lists/efaArchive", $id, "", $this->socket, 
                    $this->toolbox, $list_args);
            // The table name is needed to ditinguish the handling
            $table_name = $archive_target_list->get_table_name();
            if (in_array($table_name, $this->efa_tables->is_versionized)) {
                $info .= $this->versionized_to_archive($archive_target_list, $age_parameter);
            } else {
                $info .= $this->non_versionized_to_archive($archive_target_list);
            }
            $id ++;
        }
        if (strlen($info) == 0)
            $info = "no records to be archived.";
        else
            $info = substr($info, 0, strlen($info) - 2);
        return $info;
    }

    /**
     * Purge all deleted records of all tables, if too old.
     */
    public function purge_outdated_deleted ()
    {
        $cfg = $this->toolbox->config->get_cfg();
        // Default is 100 years = never.
        $purgeDeletedAgeDays = (isset($cfg["PurgeDeletedAgeDays"]) && (strlen($cfg["PurgeDeletedAgeDays"]) > 0)) ? intval(
                $cfg["PurgeDeletedAgeDays"]) : 36500;
        $info = "";
        if ($purgeDeletedAgeDays > 0)
            foreach ($this->tables_to_purge_deleted as $tablename) {
                $deleted_cnt = $this->socket->count_records($tablename, 
                        ["LastModification" => "delete"
                        ], "=");
                $sql_cmd = "DELETE FROM `" . $tablename .
                         "` WHERE (`LastModification` = 'delete') AND (`LastModified` < ((UNIX_TIMESTAMP() - " .
                         $purgeDeletedAgeDays . " * 86400) * 1000))";
                // === test code
                // file_put_contents("../log/tmp", $tablename . ": Would execute purge: " . $sql_cmd . "\n",
                // FILE_APPEND);
                // === test code
                $this->socket->query($sql_cmd);
                $affected_rows = $this->socket->affected_rows();
                if (($affected_rows > 0) || ($deleted_cnt > 0))
                    $info .= $tablename . ": " . $affected_rows . "/" . $deleted_cnt . ", ";
            }
        if (strlen($info) == 0)
            $info = "no deleted records were found";
        else
            $info = substr($info, 0, strlen($info) - 2);
        return $info;
    }
}