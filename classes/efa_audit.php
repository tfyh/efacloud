<?php

/**
 * class file for the efaCloud table auditing
 */
class Efa_audit
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
     * Column names of those columns that are expected to be unique for the same UUID. If with a dot, both
     * parts ANDed must be unique.
     */
    private static $warn_duplicates = ["efa2boats" => ["Name"
    ],"efa2destinations" => ["Name"
    ],"efa2groups" => ["Name"
    ],"efa2persons" => ["FirstName.LastName"
    ]
    ];

    /**
     * Column names of those columns that must be unique, additionally to the key fields. If two key fields
     * ANDed must be unique, they are separated by a dot.
     */
    private static $assert_unique = ["efaCloudUsers" => ["EMail","efaCloudUserID"
    ],"efaCloudLog" => [],"efaCloudCleansed" => [],"efaCloudArchived" => [],"efa2autoincrement" => [],
            "efa2boatstatus" => [],"efa2clubwork" => [],"efa2crews" => ["Name"
            ],"efa2fahrtenabzeichen" => [],"efa2logbook" => ["EntryId.Logbookname"
            ],"efa2messages" => [],"efa2sessiongroups" => ["Name.Logbook"
            ],"efa2statistics" => ["Name","Position"
            ],"efa2status" => ["Name"
            ],"efa2waters" => ["Name"
            ],"efa2boatdamages" => [],"efa2boatreservations" => [],"efa2boats" => [],"efa2destinations" => [],
            "efa2groups" => [],"efa2persons" => [],
            "efa2project" => ["ProjectName","BoathouseId","Type.Name"
            ],"efa2admins" => [],
            "efa2types" => ["Category.Value","Category.Type" // key: "Category","Type"
            ]
    ];

    /**
     * Column names of those columns that must not be empty.
     */
    private static $assert_not_empty = ["efaCloudUsers" => ["EMail","efaCloudUserID"
    ],"efaCloudLog" => [],"efaCloudCleansed" => [],"efaCloudArchived" => [],"efa2autoincrement" => [],
            "efa2boatstatus" => [],"efa2clubwork" => ["PersonId","Date","Description","Hours"
            ],"efa2crews" => ["Name"
            ],"efa2fahrtenabzeichen" => [],"efa2logbook" => [],"efa2messages" => [],
            "efa2sessiongroups" => ["Logbook","Name","StartDate","EndDate"
            ],"efa2statistics" => ["Name","Position"
            ],"efa2status" => ["Name"
            ],"efa2waters" => ["Name"
            ],"efa2boatdamages" => ["Severity"
            ],"efa2boatreservations" => ["Type"
            ],"efa2boats" => ["Name"
            ],"efa2destinations" => ["Name"
            ],"efa2groups" => ["Name"
            ],"efa2persons" => [],"efa2project" => [],"efa2admins" => ["Name","Password"
            ],"efa2types" => ["Category","Type","Value"
            ]
    ];

    /**
     * Column names of those columns that must be checked not to contain a UUID of a record which shall be
     * deleted.
     */
    private static $assert_not_referenced = [
            "efa2boats" => ["efa2boatdamages.BoatId","efa2boatreservations.BoatId",
                    "efa2boatstatus.BoatId","efa2logbook.BoatId"
            ],"efa2crews" => ["efa2boats.DefaultCrewId"
            ],
            "efa2destinations" => ["efa2boats.DefaultDestinationId","LogbookRecord.DestinationId"
            ],"efa2groups" => ["efa2boats.AllowedGroupIdList","efa2boats.RequiredGroupId"
            ],"efa2logbook" => ["efa2boatstatus.EntryNo"
            ],
            "efa2persons" => ["efa2fahrtenabzeichen.PersonId","efa2groups.MemberIdList",
                    "efa2crews.CoxId:Crew1Id:Crew2Id:Crew3Id:Crew4Id:Crew5Id:Crew6Id:Crew7Id:Crew8Id:Crew9Id:Crew10Id:Crew11Id:Crew12Id:Crew14Id:Crew11Id:Crew16Id:Crew11Id:Crew17Id:Crew18Id:Crew19Id:Crew20Id:Crew21Id:Crew22Id:Crew23Id:Crew24Id",
                    "efa2boatdamages.ReportedByPersonId:FixedByPersonId","efa2boatreservations.PersonId",
                    "efa2logbook.CoxId:Crew1Id:Crew2Id:Crew3Id:Crew4Id:Crew5Id:Crew6Id:Crew7Id:Crew8Id:Crew9Id:Crew10Id:Crew11Id:Crew12Id:Crew13Id:Crew14Id:Crew15Id:Crew16Id:Crew17Id:Crew18Id:Crew19Id:Crew20Id:Crew21Id:Crew22Id:Crew23Id:Crew24Id"
            ],"efa2sessiongroups" => ["efa2logbook.SessiongroupId"
            ],"efa2status" => ["efa2persons.StatusId"
            ],"efa2waters" => ["efa2destinations.WatersIdList"
            ]
    ];

    /**
     * public Constructor.
     */
    public function __construct (Efa_tables $efa_tables, Tfyh_toolbox $toolbox)
    {
        $this->efa_tables = $efa_tables;
        $this->socket = $efa_tables->socket;
        $this->toolbox = $toolbox;
    }

    /* --------------------------------------------------------------------------------------- */
    /* --------------- DATA BASE SCAN OF DOUBLETS ETC ---------------------------------------- */
    /* --------------------------------------------------------------------------------------- */
    /**
     * Format the 32-bit validity value into a human readable date.
     * 
     * @param int $validity32
     *            the 32-bit validity value: 0 = undefined, 2147483647 = today valid, other until date
     * @return string the 32-bit validity value as a human readable date
     */
    private function format_validity32 (int $validity32)
    {
        if ($validity32 == 2147483647) {
            return "gültig";
        } elseif ($validity32 > 1) {
            return "bis " . date("d.m.Y", $validity32);
        } else {
            return "nicht definiert";
        }
    }

    /**
     * Convert the efa-validity value into a 32 bit integer
     * 
     * @param String $validity
     *            the efa-validity value
     * @return number the resulting 32 bit integer
     */
    private function value32_validity (String $validity)
    {
        if (strlen($validity) > 13)
            return 2147483647; // 32 bit maximum number
        if (strlen($validity) > 3)
            return intval(substr($validity, 0, strlen($validity) - 3));
        return 0;
    }

    /**
     * returns true, if the format complies to the UUID formatting rules of blocks, lenghs and dash-positions.
     * 
     * @param String $to_check            
     */
    private function is_UUID (String $to_check)
    {
        if (strlen($to_check) != 36)
            return false;
        if (strcmp(substr($to_check, 8, 1), "-") != 0)
            return false;
        if (strcmp(substr($to_check, 13, 1), "-") != 0)
            return false;
        if (strcmp(substr($to_check, 18, 1), "-") != 0)
            return false;
        if (strcmp(substr($to_check, 23, 1), "-") != 0)
            return false;
        return true;
    }

    /**
     * This runs a full data integrity audit and returns the result as html String
     * 
     * @return string the audit result.
     */
    public function data_integrity_audit ()
    {
        include_once "../classes/tfyh_list.php";
        
        $lists = [];
        $lists["uuidnames"] = [];
        $lists["uuidref"] = [];
        $lists["duplicate"] = [];
        $lists["nonempty"] = [];
        
        $uuid_names = [];
        $uuid_invalids32 = [];
        $uuid_refs = [];
        $table_keys = [];
        
        // start with collection of all UUID names and invalid froms
        // =========================================================
        $audit_result = "<li><b>Liste der UUIDs in der Datenbank:</b></li><ul>";
        $all_ids_count = 0;
        for ($list_id = 1; $list_id <= 11; $list_id ++) {
            $lists["uuidnames"][$list_id] = new Tfyh_list("../config/lists/efaUUIDnames", $list_id, "", 
                    $this->socket, $this->toolbox);
            $table_name = $lists["uuidnames"][$list_id]->get_table_name();
            $ids_count = 0;
            foreach ($lists["uuidnames"][$list_id]->get_rows() as $row) {
                // add to name index
                $uuid = $row[0];
                $name = ($list_id == 6) ? $row[1] . " " . $row[2] . "." : $row[1] . ".";
                if (! isset($uuid_names[$uuid])) {
                    $uuid_names[$uuid] = $table_name . "." . $name;
                    $ids_count ++;
                } elseif (strpos($uuid_names[$uuid], "." . $name) === false)
                    $uuid_names[$uuid] .= $name;
                // add to validity index
                $invalidFrom = (($list_id == 1) || ($list_id == 4) || ($list_id == 5)) ? $row[2] : (($list_id ==
                         6) ? $row[3] : "");
                if (is_null($invalidFrom))
                    $invalidFrom = "";
                $invalidFrom32 = $this->value32_validity($invalidFrom);
                if (! isset($uuid_invalids32[$uuid]) || ($invalidFrom32 > $uuid_invalids32[$uuid]))
                    $uuid_invalids32[$uuid] = $invalidFrom32;
            }
            if ($ids_count > 0)
                $audit_result .= "<li>$ids_count UUIDs in $table_name.</li>";
            $all_ids_count += $ids_count;
        }
        $audit_result .= "<li>$all_ids_count UUIDs in Summe.</li>";
        $audit_result .= "</ul>";
        
        // continue with compilation of all UUID references
        // ================================================
        $audit_result .= "<li><b>Liste der Referenzen auf UUIDs in der Datenbank:</b></li><ul>";
        $all_id_refs_count = 0;
        for ($list_id = 1; $list_id <= 11; $list_id ++) {
            $lists["uuidref"][$list_id] = new Tfyh_list("../config/lists/efaUUIDrefs", $list_id, "", 
                    $this->socket, $this->toolbox);
            $table_name = $lists["uuidref"][$list_id]->get_table_name();
            $id_refs_count = 0;
            $key_cols = [];
            $name_cols = [];
            $table_keys[$table_name] = "";
            if (is_array(Efa_tables::$key_fields[$table_name]))
                foreach (Efa_tables::$key_fields[$table_name] as $field_name) {
                    $field_index = $lists["uuidref"][$list_id]->get_field_index($field_name);
                    if ($field_index !== false) { // for reservations and damages the BoatId is a key field,
                                                  // but
                                                  // not in list.
                        $table_keys[$table_name] .= "." . $field_name;
                        $key_cols[] = $lists["uuidref"][$list_id]->get_field_index($field_name);
                    }
                }
            if (is_array(Efa_tables::$name_fields[$table_name]))
                foreach (Efa_tables::$name_fields[$table_name] as $field_name) {
                    $name_cols[] = $lists["uuidref"][$list_id]->get_field_index($field_name);
                }
            
            foreach ($lists["uuidref"][$list_id]->get_rows() as $row) {
                // collect key of referencing record
                $row_key = $table_name;
                $row_name = "";
                for ($c = 0; $c < count($row); $c ++) {
                    if (in_array($c, $key_cols))
                        $row_key .= '.' . $row[$c];
                    if (in_array($c, $name_cols))
                        $row_name .= ' ' . substr($row[$c], 0, 30);
                }
                // add all referenced UUIDs. col #0 is always the ecrid.
                for ($c = 1; $c < count($row); $c ++) {
                    if (! in_array($c, $key_cols) && ! in_array($c, $name_cols) && (strlen($row[$c]) > 0)) {
                        if (strpos($row[$c], ";") !== false)
                            $uuids = explode(";", $row[$c]);
                        elseif (strpos($row[$c], ",") !== false)
                            $uuids = explode(",", $row[$c]);
                        else
                            $uuids = [$row[$c]
                            ];
                        foreach ($uuids as $uuid) {
                            if (! isset($uuid_refs[$uuid]))
                                $uuid_refs[$uuid] = [];
                            $uuid_refs[$uuid][] = $row_key . "|" . $row[0] . "|" . $row_name;
                            $id_refs_count ++;
                        }
                    }
                }
            }
            if ($id_refs_count > 0)
                $audit_result .= "<li>$id_refs_count Referenzen auf UUIDs in $table_name:</li>";
            $all_id_refs_count += $id_refs_count;
        }
        
        /*
         * for debugging: echo "<code>"; foreach ($uuid_refs as $uuid => $refs) { $name = $uuid_names[$uuid];
         * $count = count($refs); echo "uuid: $uuid: $name Anzahl: $count<br>"; $c = 0; foreach($refs as $ref)
         * { if ($c < 10) echo "&nbsp;&nbsp;&nbsp;" . $ref . "<br>"; $c++; } } echo "</code>"; exit();
         */
        $audit_result .= "<li>In Summe $all_id_refs_count Referenzen auf UUIDs in der Datenbank.</li>";
        $audit_result .= "</ul>";
        
        // continue with duplicate warnings and unique assertions
        // ======================================================
        $audit_result .= "<li><b>Dublettencheck:</b></li><ul>";
        for ($list_id = 1; $list_id <= 11; $list_id ++) {
            $lists["duplicate"][$list_id] = new Tfyh_list("../config/lists/efaDuplicates", $list_id, "", 
                    $this->socket, $this->toolbox);
            $table_name = $lists["duplicate"][$list_id]->get_table_name();
            $audit_result .= "<li>auditiere $table_name:</li><ul>";
            $id_col = $lists["duplicate"][$list_id]->get_field_index("Id");
            
            // prepare arrays
            $warn_duplicates_cols = [];
            $warn_duplicates_vals = [];
            if (is_array(self::$warn_duplicates[$table_name]) &&
                     (count(self::$warn_duplicates[$table_name]) > 0)) {
                foreach (self::$warn_duplicates[$table_name] as $field_names) {
                    $warn_duplicates_cols[$field_names] = [];
                    $warn_duplicates_vals[$field_names] = [];
                    foreach (explode(".", $field_names) as $field_name) {
                        $list_col = $lists["duplicate"][$list_id]->get_field_index($field_name);
                        $warn_duplicates_cols[$field_names][] = $list_col;
                    }
                }
            }
            
            $assert_unique_cols = [];
            $assert_unique_vals = [];
            if (is_array(self::$assert_unique[$table_name]) && (count(self::$assert_unique[$table_name]) > 0)) {
                foreach (self::$assert_unique[$table_name] as $field_names) {
                    $assert_unique_cols[$field_names] = [];
                    $assert_unique_vals[$field_names] = [];
                    foreach (explode(".", $field_names) as $field_name) {
                        $list_col = $lists["duplicate"][$list_id]->get_field_index($field_name);
                        $assert_unique_cols[$field_names][] = $list_col;
                    }
                }
            }
            
            // parse list
            foreach ($lists["duplicate"][$list_id]->get_rows() as $row) {
                // collect all rows per value for duplicates warnings
                foreach ($warn_duplicates_cols as $fieldnames => $list_cols) {
                    $warn_duplicates_val = "";
                    foreach ($list_cols as $list_col)
                        $warn_duplicates_val .= strval($row[$list_col]) . ".";
                    $warn_duplicates_val = substr($warn_duplicates_val, 0, strlen($warn_duplicates_val) - 1);
                    if (! isset($warn_duplicates_vals[$field_names][$warn_duplicates_val]))
                        $warn_duplicates_vals[$field_names][$warn_duplicates_val] = [$row
                        ];
                    else
                        $warn_duplicates_vals[$field_names][$warn_duplicates_val][] = $row;
                }
                // collect all rows per value for unique assertion
                foreach ($assert_unique_cols as $fieldnames => $list_cols) {
                    $assert_unique_val = "";
                    foreach ($list_cols as $list_col)
                        $assert_unique_val .= strval($row[$list_col]) . ".";
                    $assert_unique_val = substr($assert_unique_val, 0, strlen($assert_unique_val) - 1);
                    if (! isset($assert_unique_vals[$field_names][$assert_unique_val]))
                        $assert_unique_vals[$field_names][$assert_unique_val] = [$row
                        ];
                    else
                        $assert_unique_vals[$field_names][$assert_unique_val][] = $row;
                }
            }
            
            // issue warnings for duplicates
            if (count($warn_duplicates_cols) > 0) {
                foreach ($warn_duplicates_vals as $fieldnames => $value_list) {
                    foreach ($value_list as $value => $occurrences) {
                        if (count($occurrences) > 1) {
                            // more than one list rows have the same value
                            if ($id_col !== false) {
                                // There is a UUID field, check whether all these rows belong to the same UUID
                                $ids = [];
                                foreach ($occurrences as $occurrence) {
                                    if (! isset($ids[$occurrence[$id_col]]))
                                        $ids[$occurrence[$id_col]] = 1;
                                    else
                                        $ids[$occurrence[$id_col]] += 1;
                                }
                                if (count($ids) > 1) {
                                    $audit_result .= "<li>'$fieldnames' mit Wert '$value' hat mehr als eine zugehörige Id:";
                                    foreach ($ids as $id => $cnt) {
                                        $audit_result .= "<br>" . $id;
                                        $invalid32 = $uuid_invalids32[$id];
                                        if (isset($uuid_names[$id]))
                                            $audit_result .= " = " . $uuid_names[$id];
                                        if ($invalid32 > 0)
                                            $audit_result .= ", " . $this->format_validity32($invalid32);
                                        if (isset($uuid_refs[$id]))
                                            $audit_result .= " (" . count($uuid_refs[$id]) . " mal)";
                                        $audit_result .= "; ";
                                    }
                                    $audit_result .= "</li>";
                                }
                            } else {
                                // There is no list with duplicate warning and no UUID
                            }
                        }
                    }
                }
            }
            
            // issue errors for not uniques
            if (count($assert_unique_cols) > 0) {
                foreach ($assert_unique_vals as $fieldnames => $value_list) {
                    foreach ($value_list as $value => $occurrences) {
                        if (count($occurrences) > 1) {
                            $audit_result .= "<li>'$fieldnames' mit Wert '$value' muss eindeutig sein, kommt aber vor in Datensätzen:<br>";
                            foreach ($occurrences as $occurrence) {
                                $audit_result .= "        ";
                                $named_row = $lists["duplicate"][$list_id]->get_named_row($occurrence);
                                foreach ($named_row as $key => $value) {
                                    $audit_result .= "$key = $value";
                                    if ($this->is_UUID($value)) {
                                        $invalid32 = $uuid_invalids32[$value];
                                        if ($invalid32 > 0)
                                            $audit_result .= ", " . $this->format_validity32($invalid32);
                                        if (isset($uuid_refs[$value]))
                                            $audit_result .= " (" . count($uuid_refs[$value]) . " mal)";
                                    }
                                    $audit_result .= "; ";
                                }
                                $audit_result .= "<br>";
                            }
                            $audit_result .= "</li>";
                        }
                    }
                }
            }
            $audit_result .= "</ul>";
        }
        $audit_result .= "</ul>";
        
        // continue with non-empty checks
        // ==============================
        $audit_result .= "<li><b>Fehlende Angaben:</b></li><ul>";
        for ($list_id = 1; $list_id <= 11; $list_id ++) {
            $lists["nonempty"][$list_id] = new Tfyh_list("../config/lists/efaNotEmpty", $list_id, "", 
                    $this->socket, $this->toolbox);
            $table_name = $lists["nonempty"][$list_id]->get_table_name();
            $audit_result .= "<li>auditiere $table_name:</li><ul>";
            $asserts = self::$assert_not_empty[$table_name];
            foreach ($lists["nonempty"][$list_id]->get_rows() as $row) {
                $named_row = $lists["nonempty"][$list_id]->get_named_row($row);
                $missing = "";
                $recordstr = "";
                foreach ($named_row as $key => $value) {
                    if (in_array($key, $asserts) && (strlen($value) == 0))
                        $missing .= $key . ", ";
                    $recordstr .= "$key = $value; ";
                }
                if (strlen($missing) > 0) {
                    $audit_result .= "<li>die notwendigen Angaben " . $missing . " fehlen in Datensatz: " .
                             $recordstr . "</li>";
                }
            }
            $audit_result .= "</ul>";
        }
        $audit_result .= "</ul>";
        
        // complete with list of unused UUIDs
        // ==================================
        $unused_count = 0;
        foreach ($uuid_names as $uuid => $name)
            if (! isset($uuid_refs[$uuid]) || (count($uuid_refs[$uuid]) == 0))
                $unused_count ++;
        $audit_result .= "<li><b>Die folgenden $unused_count der in Summe $all_ids_count UUIDs werden nicht (mehr) verwendet:</b></li><ul>";
        foreach ($uuid_names as $uuid => $name) {
            if (! isset($uuid_refs[$uuid]) || (count($uuid_refs[$uuid]) == 0)) {
                $audit_result .= "<li>" . $uuid . " = " . $uuid_names[$uuid];
                $invalid32 = $uuid_invalids32[$uuid];
                if ($invalid32 > 0)
                    $audit_result .= "; " . $this->format_validity32($invalid32);
                $audit_result .= "</li>";
            }
        }
        $audit_result .= "</ul>";
        
        return $audit_result;
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
                        $data_key = $this->efa_tables->get_data_key($tablename, $tbc_record);
                        $success = $this->socket->update_record_matched($appUserID, $tablename, $data_key, 
                                $clean_record);
                        if (strlen($success) == 0) {
                            $notification = [];
                            // ID is automatically generated by MySQL data base
                            $notification["Author"] = $appUserID;
                            // Time is automatically generated by MySQL data base
                            $notification["Reason"] = "cleansed remaining values in deleted record.";
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
    public function remove_old_cleanse_records (int $max_age_days)
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

    /**
     * Check whether a records with this name is already existing, versionized tables only.
     * 
     * @param String $tablename
     *            the table of the record
     * @param array $record
     *            the record to check
     * @return String a warning message or an empty String if all is ok.
     */
    public function versionized_of_that_name_already_exists (String $tablename, array $record)
    {
        $result = "";
        $unique_field = Efa_audit::$warn_duplicates[$tablename];
        // check completeness of field values given
        if (strpos($unique_field, ".") !== false) {
            $unique_keys = explode(".", $unique_field);
            if (! isset($record[$unique_keys[0]]))
                $result .= "Datenfeld '" . $unique_keys[0] . "' fehlt im Datensatz für die Tabelle '" .
                         $tablename . "'.'";
            if (! isset($record[$unique_keys[1]]))
                $result .= "Datenfeld '" . $unique_keys[1] . "' fehlt im Datensatz für die Tabelle '" .
                         $tablename . "'.'";
            $matching = [$unique_keys[0] => $record[$unique_keys[0]],
                    $unique_keys[1] => $record[$unique_keys[1]]
            ];
        } else {
            if (! isset($record[$unique_field]))
                $result .= "Datenfeld '" . $unique_field . "' fehlt im Datensatz für die Tabelle '" .
                         $tablename . "'.'";
            $matching = [$unique_field => $record[$unique_field]
            ];
        }
        // check matching records whether they have the same UUID.
        $all_used = $this->socket->find_records_matched($tablename, $matching, 30);
        if ($all_used !== false) {
            foreach ($all_used as $used) {
                if (strcasecmp($record["Id"], $used["Id"]) != 0)
                    return $result . "Ein anderes Objekt mit dem gleichen Namen gibt es schon in der Tabelle '" .
                             $tablename . "'. Die Namen sollten eindeutig sein, um Fehler zu vermeiden.";
            }
        }
        return $result;
    }

    /**
     * Check whether a record complies to all rules before being inserted or updated.
     * 
     * @param String $tablename
     *            the table of the record
     * @param array $record
     *            the record to check
     * @param bool $is_delete
     *            set true to execute delete checks rather than insert/update checks
     * @return String an error message or an empty String if all is ok.
     */
    public function pre_modify_record_check (String $tablename, array $record, bool $is_delete)
    {
        if ($is_delete) {
            // All checks for delete
            // =====================
            // common reference check (some efa2 tables)
            if (! array_key_exists($tablename, Efa_audit::$assert_not_referenced))
                return "";
            $id_to_look_for = (strcasecmp($tablename, "efa2logbook") == 0) ? $record["EntryId"] : $record["Id"];
            $refs_to_check = Efa_audit::$assert_not_referenced[$tablename];
            $refs_found = "";
            foreach ($refs_to_check as $ref_to_check) {
                $table_to_check = explode(".", $ref_to_check)[0];
                $fields_to_check = explode(":", explode(".", $ref_to_check)[1]);
                foreach ($fields_to_check as $field_to_check) {
                    if (! isset($record[$field_to_check]))
                        return "Datenfeld '" . $field_to_check . "' in Tabelle '" . $tablename .
                                 "' muss auch beim Löschen des Datensatzes vorhanden sein.";
                    if (strpos($field_to_check, "IdList") !== false) {
                        $condition = "LIKE";
                        $match_value = "%" . $id_to_look_for . "%";
                    } else {
                        $condition = "=";
                        $match_value = $id_to_look_for;
                    }
                    $used = $this->socket->find_records_sorted_matched($tablename, 
                            [$field_to_check => $match_value
                            ], 1, $condition, "", true, 0);
                    if ($used !== false)
                        $refs_found .= $table_to_check . "." . $field_to_check . ", ";
                }
            }
            if (strlen($refs_found) > 0)
                return "Auf das Datenfeld '" . $id_to_look_for . "' sind noch folgende Referenzen vorhanden: " .
                         $refs_found . " die zuerst gelöscht werden müssen.";
            
            // === efa2status: prevent deletion of Status "USER".
            if (strcasecmp($tablename, "efa2status") == 0) {
                if (isset($record["Type"]) && (strcasecmp($record["Type"], "USER") == 0))
                    return "Vordefinierter Status vom Typ 'USER' kann nicht gelöscht werden.";
            }
            
            // all checks for delete were passed:
            return "";
        } else {
            // All checks for insert and update
            // ================================
            // not empty check for insert / update
            $key_fields = Efa_tables::$key_fields[$tablename];
            $not_empty = Efa_audit::$assert_not_empty[$tablename];
            $fields_to_check_not_empty = array_merge($key_fields, $not_empty);
            foreach ($fields_to_check_not_empty as $field_to_check) {
                if (! isset($record[$field_to_check]))
                    return "Datenfeld '" . $field_to_check . "' in Tabelle '" . $tablename .
                             "' muss angegeben sein.";
                if (is_null($record[$field_to_check]) || (strlen($record[$field_to_check]) == 0))
                    return "Datenfeld '" . $field_to_check . "' in Tabelle '" . $tablename .
                             "' darf nicht leer sein.";
            }
            // uniqueness check for insert / update
            $unique_fields = Efa_audit::$assert_unique[$tablename];
            foreach ($unique_fields as $unique_field) {
                if (strpos($unique_field, ".") !== false) {
                    $unique_keys = explode(".", $unique_field);
                    if (! isset($record[$unique_keys[0]]))
                        return "Eindeutiges Datenfeld '" . $unique_keys[0] .
                                 "' fehlt im Datensatz für die Tabelle '" . $tablename . "'.'";
                    if (! isset($record[$unique_keys[1]]))
                        return "Eindeutiges Datenfeld '" . $unique_keys[1] .
                                 "' fehlt im Datensatz für die Tabelle '" . $tablename . "'.'";
                    $matching = [$unique_keys[0] => $record[$unique_keys[0]],
                            $unique_keys[1] => $record[$unique_keys[1]]
                    ];
                    $used = $this->socket->find_record_matched($tablename, $matching);
                    if ($used !== false)
                        return "Einen Datensatz mit diesen Werten für '" . $unique_keys[0] . "' und '" .
                                 $unique_keys[1] . "' gibt es schon in der Tabelle '" . $tablename .
                                 "'. Die Felder müssen eindeutig sein.'";
                } else {
                    if (! isset($record[$unique_field]))
                        return "Eindeutiges Datenfeld '" . $unique_field .
                                 "' fehlt im Datensatz für die Tabelle '" . $tablename . "'.'";
                    $matching = [$unique_field => $record[$unique_field]
                    ];
                    $used = $this->socket->find_record_matched($tablename, $matching);
                    if ($used !== false)
                        return "Einen Datensatz mit dem Wert '" . $record[$unique_field] . "' für '" .
                                 $unique_field . "' gibt es schon in der Tabelle '" . $tablename .
                                 "'. Das Feld muss eindeutig sein.'";
                }
            }
            // Special checks for specific tables
            // === efa2boatreservations: check for overlapping times of this booking woth a previous one.
            if (strcasecmp($tablename, "efa2boatreservations") == 0) {
                $this_weekly = (strcmp($record["Type"], "WEEKLY") == 0);
                $this_day_of_week = $record["DayOfWeek"];
                $this_time_from = strtotime(
                        (($this_weekly) ? "1970-01-01" : $record["DateFrom"]) . " " . $record["TimeFrom"]);
                $this_time_to = strtotime(
                        (($this_weekly) ? "1970-01-01" : $record["DateTo"]) . " " . $record["TimeTo"]);
                $all_reservations_for_boat = $this->socket->find_records($tablename, "BoatId", 
                        $id_to_look_for, 200);
                foreach ($all_reservations_for_boat as $reservation) {
                    $weekly = (strcmp($reservation["Type"], "WEEKLY") == 0);
                    if (($this_weekly && $weekly) || (! $this_weekly && ! $weekly)) {
                        $day_of_week = $reservation["DayOfWeek"];
                        $time_from = strtotime(
                                (($weekly) ? "1970-01-01" : $reservation["DateFrom"]) . " " .
                                         $reservation["TimeFrom"]);
                        $time_to = strtotime(
                                (($weekly) ? "1970-01-01" : $reservation["DateTo"]) . " " .
                                         $reservation["TimeTo"]);
                        $time_overlap = (($this_time_from >= $time_from) && ($this_time_from < $time_to)) ||
                                 (($this_time_to > $time_from) && ($this_time_to <= $time_to));
                        if ($weekly)
                            $time_overlap = $time_overlap && (strcasecmp($this_day_of_week, $day_of_week) == 0);
                        if ($time_overlap)
                            return "Es gibt schon eine überlappende Buchung.";
                    }
                }
            }
            
            // === efa2destinations: Some Berlin specifics, not yet implemented
            if (strcasecmp($tablename, "efa2destinations") == 0) {
                // TODO the following efa Java code is only valid for Berlin and not yet implemented
                /*
                 * if (Daten.efaConfig.getValueUseFunctionalityRowingBerlin() &&
                 * getProject().getBoathouseAreaID() > 0) { DestinationRecord dr =
                 * ((DestinationRecord)record); if (dr.getStartIsBoathouse() && dr.getDestinationAreas() !=
                 * null && dr.getDestinationAreas().findZielbereich(getProject().getBoathouseAreaID()) >= 0) {
                 * throw new EfaModifyException(Logger.MSG_DATA_MODIFYEXCEPTION, "Eigener Zielbereich
                 * "+getProject().getBoathouseAreaID()+" bei Fahrten ab eigenem Bootshaus nicht erlaubt.",
                 * Thread.currentThread().getStackTrace()); } }
                 */
            }
            
            // === efa2logbook: Validate trip dates.
            if (strcasecmp($tablename, "efa2logbook") == 0) {
                // TODO configuration of logbooks
                $cfg = $this->toolbox->config->get_cfg();
                $logbookname = $record["Logbookname"];
                if (isset($cfg["Logbooks"]) && isset($cfg["Logbooks"][$logbookname])) {
                    $logbook_start_at = strtotime($cfg["Logbooks"][$logbookname]["StartDate"]);
                    $logbook_end_at = strtotime($cfg["Logbooks"][$logbookname]["EndDate"]);
                    $trip_start_at = strtotime($record["Date"] . " " . $record["StartTime"]);
                    if (($trip_start_at < $logbook_start_at) || ($trip_start_at > $logbook_end_at))
                        return "Der Beginn der Fahrt #" . $record["EntryId"] .
                                 " liegt außerhalb des zulässigen Bereichs für das Fahrtenbuch '" .
                                 $logbookname . "'.";
                    if (isset($record["EndDate"]) || isset($record["EndTime"])) {
                        $trip_end_at = strtotime(
                                (isset($record["EndDate"]) ? $record["EndDate"] : $record["StartDate"]) . " " .
                                         $record["EndTime"]);
                        if (($trip_end_at < $logbook_start_at) || ($trip_end_at > $logbook_end_at))
                            return "Das Ende der Fahrt #" . $record["EntryId"] .
                                     " liegt außerhalb des zulässigen Bereichs für das Fahrtenbuch '" .
                                     $logbookname . "'.";
                        if ($trip_end_at < $trip_start_at)
                            return "Das Ende der Fahrt #" . $record["EntryId"] . " liegt vor dem Beginn.";
                    }
                }
            }
            
            // === efa2logbook: Validate validity dates.
            if (strcasecmp($tablename, "efa2sessiongroup") == 0) {
                $res_efa2sessiongroup = "";
                $active_days = intval($record["ActiveDays"]);
                if (isset($record["StartDate"]) && isset($record["EndDate"]) && isset($record["ActiveDays"])) {
                    $days = (strtotime($record["StartDate"]) - strtotime($record["EndDate"])) / 86400;
                    if (($active_days < 1) || ($active_days > $days))
                        return "Das Feld 'ActiveDays' in SessionGroups hat einen ungültigen Wert.";
                    $referenced_trips = $this->socket->find_records("efa2logbook", "SessionGroupId", 
                            $id_to_look_for, 500);
                    $sg_start_date = strtotime($record["StartDate"]);
                    $sg_end_date = strtotime($record["EndDate"]);
                    foreach ($referenced_trips as $referenced_trip) {
                        $trip_start_date = strtotime($referenced_trip["StartDate"]);
                        $trip_end_date = isset($referenced_trip["EndDate"]) ? strtotime(
                                $referenced_trip["EndDate"]) : ($referenced_trip["StartDate"]);
                        if (($trip_start_date < $sg_start_date) || ($trip_end_date > $sg_end_date))
                            $res_efa2sessiongroup .= "Das Datum der Fahrt " . $referenced_trip["EntryId"] .
                                     " im Fahrtenbuch " . $referenced_trip["Logbookname"] .
                                     " liegt außerhalb des Zeitraums, " .
                                     "der für die ausgewählte Fahrtgruppe '" . $record["Name"] .
                                     "' angegeben wurde.";
                    }
                }
                if (strlen($res_efa2sessiongroup) > 0)
                    return $res_efa2sessiongroup;
            }
            
            // === efa2statistics: prevent changes in EfaWett.
            if (strcasecmp($tablename, "efa2statistics") == 0) {
                if (isset($record["OutputType"]) && (strcasecmp($record["OutputType"], "EfaWett") == 0) && (isset(
                        $record["PubliclyAvailable"]) && (strcasecmp($record["PubliclyAvailable"], "true") == 0)))
                    return "Das Erstellen von Meldedateien in öffentliche Statistiken ist nicht erlaubt.";
            }
            
            // all checks for insert / update were passed:
            return "";
        }
    }

    /**
     * Validate a person record: Uniqueness of first/last name, existance and validity of status and gender,
     * validity of status, gender, email, and ValidFromDate. Status will be mapped to StatusId, and
     * ValidFromDate will be mapped to ValidFrom.
     * 
     * @param bool $for_insert.
     *            Set true to validate for insert, false to validate for update.
     * @param array $efa2persons_record
     *            the efa2persons record to insert or update
     */
    public function is_validate_person (bool $for_insert, array $efa2persons_record)
    {
        // FirstName + LastName
        $FirstLastName = $efa2persons_record["FirstName"] . " " . $efa2persons_record["LastName"];
        $existing = $this->socket->find_record("efa2persons", "FirstLastName", $FirstLastName);
        if ($for_insert && ($existing !== false))
            return "Eine Person mit dem Namen " . $FirstLastName .
                     " gibt es bereits. Sie kann nicht erneut angelegt werden.";
        if (! $for_insert && ($existing === false))
            return "Eine Person mit dem Namen " . $FirstLastName .
                     " gibt es noch nicht. Sie kann nicht aktualsiert werden.";
        
        // ValidFrom
        $validFrom = strtotime($efa2persons_record["ValidFromDate"]);
        if (($validFrom === false) && (count(explode(".", $efa2persons_record["ValidFromDate"])) == 3)) {
            $parts = explode(".", $efa2persons_record["ValidFromDate"]);
            $validFrom = strtotime($parts[2] . "-" . $parts[1] . "-" . $parts[0]);
        }
        if ($validFrom === false)
            return "Die Gültigkeit " . $efa2persons_record["ValidFromDate"] .
                     " wurde nicht erkannt. Zulässige Formate: YYYY-MM-DD oder TT.MM.JJJJ.";
        if (! $for_insert && isset($efa2persons_record["ValidFromDate"]))
            return "Die Gültigkeit darf bei einer Aktualisierung nicht angegeben werden, " .
                     "da immer nür der letztgültige Wert aktualisiert werden kann.";
        
        // Status
        $StatusIds = $this->socket->find_records("efa2status", "Name", $efa2persons_record["Status"], 3);
        if ($StatusIds === false)
            return "Für den Status " . $efa2persons_record["Status"] . " wurde keine Id gefunden.";
        elseif (count($StatusIds) > 1)
            return "Für den Status " . $efa2persons_record["Status"] .
                     " gibt es mehr als eine Id, er ist nicht eindeutig.";
        
        // Gender
        include_once "../classes/efa_tfyh_config.php";
        $efa_config = new Efa_config($this->toolbox);
        $gender_values = $efa_config->types["GENDER"];
        $gender_valid = false;
        foreach ($gender_values as $position => $gender_value)
            if (strcasecmp($efa2persons_record["Gender"], explode(":", $gender_value)[0]) == 0)
                $gender_valid = true;
        
        // Email
        if (isset($efa2persons_record["Email"]) && (strlen($efa2persons_record["Email"]) > 0) &&
                 (filter_var($efa2persons_record["Email"], FILTER_VALIDATE_EMAIL) === false))
            return "Die Adresse " . $efa2persons_record["Email"] . " ist ungültig.";
        
        return true;
    }
}