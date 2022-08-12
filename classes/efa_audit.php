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
    private static $assert_unique = ["efa2autoincrement" => [],"efa2boatdamages" => ["Damage.BoatId"
    ],"efa2reservations" => ["Reservation.BoatId"
    ],"efa2boatstatus" => [],"efa2clubwork" => [],"efa2crews" => ["Name"
    ],"efa2fahrtenabzeichen" => [],"efa2logbook" => ["EntryId.Logbookname"
    ],"efa2messages" => [],"efa2sessiongroups" => ["Name.Logbook"
    ],"efa2statistics" => ["Name","Position"
    ],"efa2status" => ["Name"
    ],"efa2waters" => ["Name"
    ],"efa2boatdamages" => [],"efa2boatreservations" => [],"efa2boats" => [],"efa2destinations" => [],
            "efa2groups" => [],"efa2persons" => []
    ];

    /**
     * The list indices for assert unique referencing (../config/lists/efaAuditDuplicates)
     */
    private static $assert_unique_list_id = ["efa2boatdamages" => 1,"efa2boatreservations" => 2,
            "efa2boats" => 3,"efa2boatstatus" => 4,"efa2crews" => 5,"efa2destinations" => 6,"efa2groups" => 6,
            "efa2logbooks" => 8,"efa2persons" => 9,"efa2sessiongroups" => 10,"efa2statistics" => 11,
            "efa2status" => 12,"efa2waters" => 13
    ];

    /**
     * Column names of those columns that must not be empty.
     */
    public static $assert_not_empty = ["efa2autoincrement" => [],"efa2boatstatus" => [],
            "efa2clubwork" => ["PersonId","Date","Description","Hours"
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
            ],"efa2persons" => ["StatusId","Gender"
            ],"efa2project" => [],"efa2admins" => ["Name","Password"
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
     * The list indices for UUID referencing. Use $this->build_indices to initialize respective associative
     * arrays
     */
    private static $uuid_list_id = ["efa2boats" => 1,"efa2clubwork" => 2,"efa2crews" => 3,
            "efa2destinations" => 4,"efa2groups" => 5,"efa2persons" => 6,"efa2sessiongroups" => 7,
            "efa2statistics" => 8,"efa2status" => 9,"efa2waters" => 10
    ];

    /**
     * For a bulk operation collect first all names and their ids to speed up uniqueness and references checks
     */
    private $ids_for_names = array();

    /**
     * collect the latest validities per UUID for bulk updates.
     */
    private $invalidFroms_per_ids = array();

    /**
     * collect the ecrids latest valid record per UUID for bulk updates.
     */
    private $ecrids_per_ids = array();

    /**
     * collect the ecrids latest valid record per UUID for bulk updates.
     */
    private $table_names = array();

    /**
     * public Constructor.
     */
    public function __construct (Efa_tables $efa_tables, Tfyh_toolbox $toolbox)
    {
        $this->efa_tables = $efa_tables;
        $this->socket = $efa_tables->socket;
        $this->toolbox = $toolbox;
        include_once '../classes/tfyh_list.php';
    }

    /* --------------------------------------------------------------------------------------- */
    /* --------------- HELPER FUNCTIONS ------------------------------------------------------ */
    /* --------------------------------------------------------------------------------------- */
    
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

    /* --------------------------------------------------------------------------------------- */
    /* --------------- DATA BASE SCAN OF DOUBLETS ETC ---------------------------------------- */
    /* --------------------------------------------------------------------------------------- */
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
        $audit_result = "<li><b>Liste der UUIDs in der Datenbank:</b></li>\n<ul>";
        $all_ids_count = 0;
        for ($list_id = 1; $list_id <= 11; $list_id ++) {
            $lists["uuidnames"][$list_id] = new Tfyh_list("../config/lists/efaAuditUUIDnames", $list_id, "", 
                    $this->socket, $this->toolbox);
            $table_name = $lists["uuidnames"][$list_id]->get_table_name();
            $ids_count = 0;
            $col_invalidFrom = $lists["uuidnames"][$list_id]->get_field_index("InvalidFrom");
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
                $invalidFrom = $row[$col_invalidFrom];
                if (is_null($invalidFrom))
                    $invalidFrom = $this->efa_tables->forever64;
                $invalidFrom32 = $this->efa_tables->value_validity32($invalidFrom);
                if (! isset($uuid_invalids32[$uuid]) || ($invalidFrom32 > $uuid_invalids32[$uuid]))
                    $uuid_invalids32[$uuid] = $invalidFrom32;
            }
            if ($ids_count > 0)
                $audit_result .= "<li>$ids_count UUIDs in $table_name.</li>\n";
            $all_ids_count += $ids_count;
        }
        $audit_result .= "<li>$all_ids_count UUIDs in Summe.</li>\n";
        $audit_result .= "</ul>";
        
        // continue with compilation of all UUID references
        // ================================================
        $audit_result .= "<li><b>Liste der Referenzen auf UUIDs in der Datenbank:</b></li>\n<ul>";
        $all_id_refs_count = 0;
        for ($list_id = 1; $list_id <= 11; $list_id ++) {
            $lists["uuidref"][$list_id] = new Tfyh_list("../config/lists/efaAuditUUIDrefs", $list_id, "", 
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
                                                  // but not in list.
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
                $audit_result .= "<li>$id_refs_count Referenzen auf UUIDs in $table_name:</li>\n";
            $all_id_refs_count += $id_refs_count;
        }
        
        /*
         * for debugging: echo "<code>"; foreach ($uuid_refs as $uuid => $refs) { $name = $uuid_names[$uuid];
         * $count = count($refs); echo "uuid: $uuid: $name Anzahl: $count<br>"; $c = 0; foreach($refs as $ref)
         * { if ($c < 10) echo "&nbsp;&nbsp;&nbsp;" . $ref . "<br>"; $c++; } } echo "</code>"; exit();
         */
        $audit_result .= "<li>In Summe $all_id_refs_count Referenzen auf UUIDs in der Datenbank.</li>\n";
        $audit_result .= "</ul>";
        
        // continue with duplicate warnings and unique assertions
        // ======================================================
        $audit_result .= "<li><b>Dublettencheck:</b></li>\n<ul>";
        for ($list_id = 1; $list_id <= 13; $list_id ++) {
            $lists["duplicate"][$list_id] = new Tfyh_list("../config/lists/efaAuditDuplicates", $list_id, "", 
                    $this->socket, $this->toolbox);
            $table_name = $lists["duplicate"][$list_id]->get_table_name();
            $audit_result .= "<li>auditiere $table_name:</li>\n<ul>";
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
                                            $audit_result .= " = " . htmlspecialchars($uuid_names[$id]);
                                        if ($invalid32 > 0)
                                            $audit_result .= ", " .
                                                     $this->efa_tables->format_validity32($invalid32);
                                        if (isset($uuid_refs[$id]))
                                            $audit_result .= " (" . count($uuid_refs[$id]) . " mal)";
                                        $audit_result .= "; ";
                                    }
                                    $audit_result .= "</li>\n";
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
                                            $audit_result .= ", " .
                                                     $this->efa_tables->format_validity32($invalid32);
                                        if (isset($uuid_refs[$value]))
                                            $audit_result .= " (" . count($uuid_refs[$value]) . " mal)";
                                    }
                                    $audit_result .= "; ";
                                }
                                $audit_result .= "<br>";
                            }
                            $audit_result .= "</li>\n";
                        }
                    }
                }
            }
            $audit_result .= "</ul>";
        }
        $audit_result .= "</ul>";
        
        // continue with non-empty checks
        // ==============================
        $audit_result .= "<li><b>Fehlende Angaben:</b></li>\n<ul>";
        for ($list_id = 1; $list_id <= 11; $list_id ++) {
            $lists["nonempty"][$list_id] = new Tfyh_list("../config/lists/efaAuditNotEmpty", $list_id, "", 
                    $this->socket, $this->toolbox);
            $table_name = $lists["nonempty"][$list_id]->get_table_name();
            $audit_result .= "<li>auditiere $table_name:</li>\n<ul>";
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
                             $recordstr . "</li>\n";
                }
            }
            $audit_result .= "</ul>";
        }
        $audit_result .= "</ul>";
        
        // complete with list of unused UUIDs
        // ==================================
        $unused_count = 0;
        include_once "../classes/efa_archive.php";
        foreach ($uuid_names as $uuid => $name)
            if ((! isset($uuid_refs[$uuid]) || (count($uuid_refs[$uuid]) == 0)) &&
                     (strpos($uuid_names[$uuid], Efa_archive::$archive_id_prefix) === false))
                $unused_count ++;
        $audit_result .= "<li><b>Die folgenden $unused_count der in Summe $all_ids_count UUIDs werden nicht (mehr) verwendet:</b></li>\n<ul>";
        foreach ($uuid_names as $uuid => $name) {
            if (! isset($uuid_refs[$uuid]) || (count($uuid_refs[$uuid]) == 0)) {
                if (strpos($uuid_names[$uuid], Efa_archive::$archive_id_prefix) === false) {
                    $audit_result .= "<li>" . $uuid . " = " . htmlspecialchars($uuid_names[$uuid]);
                    $invalid32 = $uuid_invalids32[$uuid];
                    if ($invalid32 > 0)
                        $audit_result .= "; " . $this->efa_tables->format_validity32($invalid32);
                    $audit_result .= "</li>\n";
                }
            }
        }
        $audit_result .= "</ul>";
        
        return $audit_result;
    }

    /* --------------------------------------------------------------------------------------- */
    /* --------------- PREMODIFICATION CHECKS AND CORRECTIONS -------------------------------- */
    /* --------------------------------------------------------------------------------------- */
    /**
     * Clear all indices to trigger rebuild.
     */
    public function clear_indices (int $list_id)
    {
        $this->ids_for_names[$list_id] = [];
        $this->invalidFroms_per_ids[$list_id] = [];
        $this->ecrids_per_ids[$list_id] = [];
    }

    /**
     * Initialize the arrays for checking in bulk operations.
     * 
     * @param int $list_id
     *            the id of the list within the set "../config/lists/efaAuditUUIDnames" which shall be used to
     *            identify the most recent record version.
     * @param bool $force_refresh
     *            set true to force a refeesh, even if the index was already build.
     */
    private function build_indices (int $list_id, bool $force_refresh)
    {
        if (! $force_refresh && (count($this->ids_for_names[$list_id]) > 0))
            return;
        $this->clear_indices($list_id);
        $uuid_names = new Tfyh_list("../config/lists/efaAuditUUIDnames", $list_id, "", $this->socket, 
                $this->toolbox);
        $this->table_names[$list_id] = $uuid_names->get_table_name();
        $col_uuid = $uuid_names->get_field_index("Id");
        $col_ecrid = $uuid_names->get_field_index("ecrid");
        $col_invalidFrom = $uuid_names->get_field_index("InvalidFrom");
        foreach ($uuid_names->get_rows() as $row) {
            $uuid = $row[0];
            // build name index
            if ($list_id == Efa_audit::$uuid_list_id["efa2persons"]) // Special case persons' name
                $name = $row[1] . " " . $row[2];
            else
                $name = $row[1]; // includes names_clubwork
            $this->ids_for_names[$list_id][$name] = $uuid;
            // build invalidFrom index with most recent validity timestamp per uuid.
            $invalid32 = $this->efa_tables->value_validity32(strval($row[$col_invalidFrom]));
            if (! isset($this->invalidFroms_per_ids[$list_id][$uuid]) ||
                     ($this->invalidFroms_per_ids[$list_id][$uuid] < $invalid32)) {
                $this->invalidFroms_per_ids[$list_id][$uuid] = $invalid32;
                $this->ecrids_per_ids[$list_id][$uuid] = $row[$col_ecrid];
            }
        }
    }

    /**
     * Check whether needed fields are all set, and return an error, if not.
     * 
     * @param array $record_to_check
     *            the record which shall be checked, mapped and completed.
     * @param String $tablename
     *            the tables name to know which fields are system and bool fields.
     * @param int $mode
     *            Set to mode of operations: 1 = insert, 2 = update, 3 = delimit.
     * @return string in case of errors a String with the error message, else an empty String
     */
    private function check_unique_and_not_empty (array $record_to_check, String $tablename, int $mode)
    {
        // check for insertion of a new or a copy of a record after delimitation, that no needed fields are
        // empty.
        if (($mode == 1) || ($mode == 1)) {
            foreach (Efa_audit::$assert_not_empty[$tablename] as $not_empty_field) {
                if (! isset($record_to_check[$not_empty_field]) || is_null($record_to_check[$not_empty_field]) ||
                         (strlen($record_to_check[$not_empty_field]) == 0)) {
                    return "Das erforderliche Feld '$not_empty_field' darf bei neuen Datensätzen nicht leer sein.";
                }
            }
        }
        // Check uniqueness of all relevant fields or field combinations for all cases.
        // prepare list for cross check
        $assert_unique_list_id = Efa_audit::$assert_unique_list_id[$tablename];
        $assert_unique_list = new Tfyh_list("../config/lists/efaAuditDuplicates", $assert_unique_list_id, "", 
                $this->socket, $this->toolbox);
        // prepare lookup indices for list entries and previous record (for updates)
        $assert_unique_fields = Efa_audit::$assert_unique[$tablename];
        $col_ecrid = $assert_unique_list->get_field_index("ecrid");
        $col_id = $assert_unique_list->get_field_index("Id");
        $previous_record = ($mode == 2) ? $this->socket->find_record($tablename, "ecrid", 
                $record_to_check["ecrid"]) : false;
        // prepare references to check whether the duplicate is actually the very same.
        $reference_ecrid = $record_to_check["ecrid"];
        $reference_id = $record_to_check["Id"];
        $is_versionized = $this->efa_tables->is_versionized[$tablename];
        // screen through fields to be asserted as unique.
        foreach ($assert_unique_fields as $assert_unique_field) {
            // per field compile first the reference value, which shall be checked for duplicates
            $parts = explode(".", $assert_unique_field);
            $reference = "";
            $cols = [];
            foreach ($parts as $part) {
                $cols[] = $assert_unique_list->get_field_index($part);
                if (! isset($record_to_check[$part])) {
                    // if the value is not set in the new record (e.g. for updates) use the previous one
                    if ($previous_record !== false)
                        $reference .= $previous_record[$part] . ".";
                    else
                        return "Dem Datensatz fehlt das auf Eindeutigkeit zu prüfende Feld '" .
                                 $assert_unique_field . "'. ";
                } else
                    $reference .= $record_to_check[$part] . ".";
            }
            // search for this field in all records.
            foreach ($assert_unique_list->get_rows() as $row) {
                $compare = "";
                foreach ($cols as $col)
                    $compare .= $row[$col] . ".";
                if (strcasecmp($reference, $compare) == 0) {
                    // a match was found. Now verify, whether it is the record's self or a duplicate.
                    if ((strcmp($row[$col_ecrid], $reference_ecrid) != 0) && ! $is_versionized)
                        return "Das eindeutige Feld '" . $assert_unique_field .
                                 "' ist nicht eindeutig. Weiteres Vorkommen im Datensatz mit ecrid '" .
                                 $row[$col_ecrid] . "'";
                    if ((strcmp($row[$col_id], $reference_id) != 0) && $is_versionized)
                        return "Das eindeutige Feld '" . $assert_unique_field .
                                 "' ist nicht eindeutig. Weiteres Vorkommen im Objekt mit Id '" . $row[$col_id] .
                                 "'";
                }
            }
        }
        return "";
    }

    /**
     * Check whether system fields were set, and return an error, if not allowed for the $mode.
     * 
     * @param array $record_to_check
     *            the record which shall be checked, mapped and completed.
     * @param String $tablename
     *            the tables name to know which fields are system and bool fields.
     * @param int $mode
     *            Set to mode of operations: 1 = insert, 2 = update, 3 = delimit.
     * @return string in case of errors a String with the error message, else an empty String
     */
    private function check_must_not_set (array $record_to_check, String $tablename, int $mode)
    {
        // system fields must not be set except key fields for update
        foreach (Efa_tables::$server_gen_fields[$tablename] as $system_field) {
            if (isset($record_to_check[$system_field]) && (strlen($record_to_check[$system_field]) > 0)) {
                $is_ecrid = (strcmp($system_field, "ecrid") == 0);
                $is_uuid = (strcmp($system_field, "Id") == 0);
                $is_key = in_array($system_field, Efa_tables::$key_fields[$tablename]);
                // a system field is set.
                if ($mode == 1)
                    // for insertion this is never allowed.
                    return "Das vom System zu definierende Feld '$system_field' darf bei neuen Datensätzen nicht gesetzt werden.";
                if (($mode == 2) && ! $is_key && ! $is_ecrid)
                    // for update this must be a key field
                    return "Das vom System zu definierende Feld '$system_field' darf bei zu ändernden Datensätzen nicht gesetzt werden. " .
                             "Nur Schlüsselfelder sind zulässig.";
                if (($mode == 3) && ! $is_uuid)
                    // for delimit this is only allowed for the Id field.
                    return "Das vom System zu definierende Feld '$system_field' darf bei Abgrenzung nicht gesetzt werden. Zulässig ist nur 'Id'.";
            }
        }
        return "";
    }

    /**
     * Map boolean 'on' from forms to 'true' for efa.
     * 
     * @param array $record
     *            the record which shall be checked and mapped.
     * @param String $tablename
     *            the tables name to know which fields are system and bool fields.
     * @return string|array the mapped record or in case of errors a String with the error message
     */
    private function map_bool_fields (array $record, String $tablename)
    {
        // boolean fields check and mapping 'on' => 'true'
        foreach (Efa_tables::$boolean_fields[$tablename] as $boolean_field) {
            if (isset($record_to_map[$boolean_field]) && (strlen($record[$boolean_field]) > 0)) {
                if (strcasecmp($record[$boolean_field], "on") == 0)
                    $record[$boolean_field] = "true";
                if ((strlen($boolean_field) > 0) && (strcasecmp($boolean_field, "true") !== 0) &&
                         (strcasecmp($boolean_field, "false") !== 0))
                    return "Das Datenfeld '$boolean_field' darf nur 'true', 'false' oder nichts enthalten.";
            }
        }
        return $record;
    }

    /**
     * Map names to Ids and extra fields ValidFromDate, InvalidFromDate to timestamps and StatusName to
     * StatusId. Map boolean "on" values to "true".
     * 
     * @param array $record
     *            the record which shall be checked and mapped.
     * @param String $tablename
     *            the tables name to know which fields are system and bool fields.
     * @return string|array the mapped record or in case of errors a String with the error message
     */
    public function map_extra_fields (array $record, String $tablename)
    {
        // Map the extra date fields
        if ((isset($record["ValidFromDate"])) && (strlen($record["ValidFromDate"]) > 0)) {
            $record["ValidFrom"] = strtotime($this->toolbox->check_and_format_date($record["ValidFromDate"])) .
                     "000";
        }
        if ((isset($record["InvalidFromDate"])) && (strlen($record["InvalidFromDate"]) > 0)) {
            $record["InvalidFrom"] == strtotime(
                    $this->toolbox->check_and_format_date($record["InvalidFromDate"])) . "000";
        }
        // Map the extra status name field
        if (isset($record["StatusName"]) && (strlen($record["StatusName"]) > 0)) {
            $this->build_indices(9, false); // 9 = list_id for efaAuditUUIDnames/name_status
            if (! isset($this->ids_for_names[9][$record["StatusName"]]))
                return "Für den Status '" . $record["StatusName"] . "' wurde keine Id gefunden.";
            $statusId = $this->ids_for_names[9][$record["StatusName"]];
            $record["StatusId"] = $statusId;
        }
        // unset all fields, even if they were empty
        unset($record["ValidFromDate"]);
        unset($record["InvalidFromDate"]);
        unset($record["StatusName"]);
        return $record;
    }

    /**
     * Add all virtual fields which shall be system generated, like FirstLastName.
     * 
     * @param array $record_to_modify
     *            the record which shall be modified in the data base and therefore get all missing system
     *            fields.
     * @param String $tablename
     *            the table's name to know which fields are system fields.
     * @return array the completed record
     */
    private function add_virtual_fields (array $record_to_modify, String $tablename)
    {
        if (strcasecmp($tablename, "efa2persons") == 0) {
            $record_to_modify["FirstLastName"] = $record_to_modify["FirstName"] . " " .
                     $record_to_modify["LastName"];
        }
        // TODO other tables.
        return $record_to_modify;
    }

    /**
     * Add all fields which shall be system generated, if not existing. See Efa_tables::$server_gen_fields for
     * a list.
     * 
     * @param array $record_to_modify
     *            the record which shall be modified in the data base and therefore get all missing system
     *            fields.
     * @param String $tablename
     *            the table's name to know which fields are system fields.
     * @param int $mode
     *            Set to mode of operations: 1 = insert, 2 = update, 3 = delimit.
     * @return string|array the mapped record or in case of errors a String with the error message
     */
    private function add_system_fields (array $record_to_modify, String $tablename, int $mode)
    {
        // add the system fields, if not existing
        foreach (Efa_tables::$server_gen_fields[$tablename] as $sysfield) {
            // increase the changecount, whether it is set or not.
            if (strcasecmp($sysfield, "ChangeCount") == 0)
                $record_to_modify[$sysfield] = (isset($record_to_modify[$sysfield])) ? intval(
                        $record_to_modify[$sysfield]) + 1 : 1;
            // add all other fields, if not set.
            elseif (! isset($record_to_modify[$sysfield]) || (strlen($record_to_modify[$sysfield]) == 0)) {
                // standard fields
                if (strcasecmp($sysfield, "LastModified") == 0)
                    $record_to_modify[$sysfield] = time() . "000";
                elseif (strcasecmp($sysfield, "LastModification") == 0)
                    $record_to_modify[$sysfield] = ($mode == 1) ? "insert" : "update";
                // efacloud Record management fields
                elseif (strcasecmp($sysfield, "ecrid") == 0)
                    $record_to_modify[$sysfield] = $this->efa_tables->generate_ecrids(1)[0];
                elseif (strcasecmp($sysfield, "ecrown") == 0)
                    $record_to_modify[$sysfield] = $_SESSION["User"][$this->toolbox->users->user_id_field_name];
                // UUID field
                elseif (strcasecmp($sysfield, "Id") == 0)
                    $record_to_modify[$sysfield] = $this->toolbox->create_GUIDv4();
                // numeric autoincrement key fields
                elseif (($mode == 1) && (strcasecmp($sysfield, $this->efa_tables->fixid_auto_field) == 0)) {
                    $logbookname = (isset($record_to_modify["Logbooknme"])) ? $record_to_modify["Logbooknme"] : "";
                    $record_to_modify[$sysfield] = $this->efa_tables->autoincrement_key_field($tablename, 
                            $logbookname);
                } elseif (strcasecmp($sysfield, "AllCrewIds") == 0)
                    // AllCrewIds field in logbook
                    $record_to_modify[$sysfield] = $this->efa_tables->create_AllCrewIds_field(
                            $record_to_modify);
                // a persons full name
                elseif (strcasecmp($sysfield, "FirstLastName") == 0)
                    $record_to_modify[$sysfield] = $record_to_modify["FirstName"] . " " .
                             $record_to_modify["LastName"];
            }
        }
        return $record_to_modify;
    }

    /**
     * Validate a version of a versionized record and find any existing record matching.
     * 
     * @param int $list_id
     *            the id of the list within the set "../config/lists/efaAuditUUIDnames" which shall be used to
     *            identify the most recent record version.
     * @param array $version_record
     *            the record to insert as new, update as existing or insert as new version
     * @param int $mode
     *            Set 1 for insert, 2 for update, 3 for delimiting.
     * @param bool $force_refresh
     *            set true to force an index refresh, even if the index was already build.
     * @return String an error message for user display on all errors, else an empty String.
     */
    private function select_existing_record (int $list_id, array $version_record, int $mode, 
            bool $force_refresh)
    {
        // Check whether the object in question exists. Use the UUID, if given, or resolve the name else
        // =============================================================================================
        $this->build_indices($list_id, $force_refresh);
        $tablename = $this->table_names[$list_id];
        $use_uuid = ((isset($version_record["Id"])) && (strlen($version_record["Id"]) > 0));
        if ($use_uuid) {
            $existing_uuid = (isset($this->invalidFroms_per_ids[$list_id][$version_record["Id"]])) ? $version_record["Id"] : null;
        } else {
            $name = $this->efa_tables->get_name($tablename, $version_record);
            $existing_uuid = $this->ids_for_names[$list_id][$name];
        }
        // for update and delimit now settle the record to use.
        if (! is_null($existing_uuid) && (strlen($existing_uuid) > 5)) {
            $version_record["ecrid"] = $this->ecrids_per_ids[$list_id][$existing_uuid];
            // for updates add also the Id to avoid that it removed.
            $version_record["Id"] = $existing_uuid;
        }
        
        // Check whether the record existance fits to the intended operation
        // =================================================================
        $error_rpefix = ($use_uuid) ? "Ein Objekt mit der Id '" . $version_record["Id"] .
                 "' gibt es in $tablename" : "Ein Objekt mit dem Namen " . $name . " gibt es in $tablename";
        if ($mode == 1) {
            if (! is_null($existing_uuid))
                return $error_rpefix .
                         " bereits ($existing_uuid = $name). Es kann nicht erneut angelegt werden.";
        } elseif ($mode == 2) {
            if (is_null($existing_uuid) || (strlen($existing_uuid) < 5))
                return $error_rpefix . " noch nicht. Es kann nicht aktualisiert werden.";
            $invalidFrom = $this->invalidFroms_per_ids[$list_id][$existing_uuid];
            if (time() > $invalidFrom)
                return $error_rpefix .
                         ", es hat aber keinen aktuell güligen Datensatz mehr. Es kann nicht aktualisiert werden.";
        } elseif ($mode == 3) {
            if (is_null($existing_uuid))
                return $error_rpefix . " noch nicht. Es kann nicht abgegrenzt werden.";
        }
        return $version_record;
    }

    /**
     * Validate a version of a versionized record and replace names by Ids. Ensure existance, uniqueness,
     * look-up and syntactical validity. For update: Ensure uniqueness of first/last name, validity of status,
     * gender, and email, if provided. StatusName will then be mapped to StatusId, ValidFromDate to ValidFrom
     * and InvalidFromDate to InvalidFrom. StatusName, ValidFromDate and InvalidFromDate will be unset.
     * 
     * @param int $list_id
     *            the id of the list within the set "../config/lists/efaAuditUUIDnames" which shall be used to
     *            identify the most recent record version.
     * @param array $version_record
     *            the record to insert as new, update as existing or insert as new version
     * @param int $mode
     *            Set 1 for insert, 2 for update, 3 for delimiting.
     * @param bool $force_refresh
     *            set true to force an index refresh, even if the index was already build.
     * @param bool $execute
     *            set true to execute the modification in the data base, false to only check.
     * @return String an error message for user display on all errors, else an empty String.
     */
    public function modify_version (int $list_id, array $version_record, int $mode, bool $force_refresh, 
            bool $execute)
    {
        
        // Check whether the object in question exists. Use the UUID, if given, or resolve the name else
        // =============================================================================================
        $version_record = $this->select_existing_record($list_id, $version_record, $mode, $force_refresh);
        if (! is_array($version_record))
            return $version_record;
        $this->build_indices($list_id, false);
        $tablename = $this->table_names[$list_id];
        if ($mode == 3)
            unset($version_record["ecrid"]);
        
        // Map boolean and extra fields
        // ============================
        $version_record = $this->map_bool_fields($version_record, $tablename);
        if (! is_array($version_record))
            return $version_record;
        $version_record = $this->map_extra_fields($version_record, $tablename);
        if (! is_array($version_record))
            return $version_record;
        
        // Check version validity for versionized tables
        // =============================================
        if (isset($version_record["ValidFrom"]) && (strlen($version_record["ValidFrom"]) > 0)) {
            if ($mode == 2)
                return "Die Angabe eines Gültigkeitsstarts ist für Änderungen nicht zulässig.";
        } else {
            if ($mode == 3)
                return "Für die Abgrenzung ist die Angabe des Gültigkeitsstarts nach Abgrenzung erforderlich.";
        }
        if (($mode == 1) || ($mode == 3)) {
            if (isset($version_record["InvalidFrom"]) && (strlen($version_record["InvalidFrom"]) > 0) &&
                     (strcmp($version_record["InvalidFrom"], $this->efa_tables->forever64) != 0))
                return "Für neue Objekte darf die Gültigkeit nicht begrenzt werden. Dazu dient die Abgrenzung bei bestehenden Objekten.";
            // add the ValidFrom and InvalidFrom timestamps.
            if (! isset($version_record["ValidFrom"]) || (strlen($version_record["ValidFrom"]) == 0))
                $version_record["ValidFrom"] = time() . "000";
            $version_record["InvalidFrom"] = $this->efa_tables->forever64;
        }
        
        // check data uniqueness and completeness
        // ======================================
        $data_completeness = $this->check_unique_and_not_empty($version_record, $tablename, $mode);
        if (strlen($data_completeness) > 0)
            return $data_completeness;
        $must_not_be_set = $this->check_must_not_set($version_record, $tablename, $mode);
        if (strlen($must_not_be_set) > 0)
            return $must_not_be_set;
        
        // check data correctness
        // ======================
        if (isset($version_record["Gender"]) && (strcasecmp($version_record["Gender"], "MALE") != 0) &&
                 (strcasecmp($version_record["Gender"], "FEMALE") != 0))
            return "Das Geschlecht muss entweder 'MALE' oder 'FEMALE' sein.";
        if (isset($version_record["Email"]) && (strlen($version_record["Email"]) > 0)) {
            if (filter_var($version_record["Email"], FILTER_VALIDATE_EMAIL) === false)
                return "Die Angabe " . $version_record["Email"] . " stellt keine gültige E-Mail-Adresse dar.";
        }
        
        // all checks completed, add the system fields, including the UUID for insertion
        // =============================================================================
        $version_record = $this->add_virtual_fields($version_record, $tablename);
        $version_record = $this->add_system_fields($version_record, $tablename, $mode);
        if (! is_array($version_record))
            return $version_record;
        
        if ($execute) {
            $appUserID = $_SESSION["User"][$this->toolbox->users->user_id_field_name];
            if (($mode == 1) || ($mode == 3)) {
                $insert_result = $this->socket->insert_into($appUserID, $tablename, $version_record);
                if (! is_numeric($insert_result))
                    return "Datenbankfehler. Die Version des Objekts " . $version_record["Id"] .
                             " konnte nicht hinzugefügt werden: " . $insert_result;
            } elseif ($mode == 2) {
                $update_result = $this->socket->update_record_matched($appUserID, $tablename, 
                        ["ecrid" => $version_record["ecrid"]
                        ], $version_record);
                if (strlen($update_result) > 0)
                    return "Datenbankfehler. Die Version des Objekts " . $version_record["Id"] .
                             " konnte nicht aktualisiert werden: " . $update_result;
            }
        }
        return "";
    }

    /**
     * Set the InvalidFrom of the most recent version of a versionized object to the provided $validityLimit
     * (the record in the data base is changed). Returns a copy of this most recent version record, in which
     * the ValidFrom is set to $validityLimit and the InvalidFrom to "$efa_tables->forever64". This works for
     * the most recent version, regardless on whether it is still valid or not. It may therefore reopen a
     * previously invalidated most recent version.
     * 
     * @param int $list_id
     *            the id of the list within the set "../config/lists/efaAuditUUIDnames" which shall be used to
     *            identify the most recent record version.
     * @param array $new_version_record
     *            the new version of the versionized record to delimit. It must contain EITHER the
     *            FirstLastName (person) / the Name (other) of the object OR the UUID to locate the most
     *            recent record of the object. If both name and Id are provided, the Id is used and the name
     *            ignored.
     * @param String $validityLimit
     *            the limit to use. If this is not larger than the most recent version's ValidFrom, an error
     *            will be returned.
     * @param bool $force_refresh
     *            set true to force a refeesh, even if the index was already build.
     * @param bool $execute
     *            set true to execute the modification in the data base, false to only check.
     * @return the full record without system fields, updated to reflect the new version (array) or an error
     *         message for user display (String).
     */
    public function delimit_version (int $list_id, array $new_version_record, String $validityLimit, 
            bool $force_refresh, bool $execute)
    {
        $mode = 3;
        // Check whether the object in question exists. Use the UUID, if given, or resolve the name else
        // =============================================================================================
        $version_record = $this->select_existing_record($list_id, $new_version_record, $mode, $force_refresh);
        if (! is_array($version_record))
            return $version_record;
        $this->build_indices($list_id, false);
        $tablename = $this->table_names[$list_id];
        $existing_uuid = $version_record["Id"];
        $ecrid = $version_record["ecrid"];
        
        // Get the record
        $name = $this->efa_tables->get_name($tablename, $version_record);
        if (is_null($ecrid))
            return "Zum Objekt mit dem Namen " . $name .
                     " konnte die efacloud Record Id  in $tablename nicht gefunden werden (ecrid). Es kann daher nicht abgegrenzt werden.";
        $record = $this->socket->find_record("efa2persons", "ecrid", $ecrid);
        if ($record === false)
            return "Zum Objekt mit dem Namen " . $name .
                     " konnte der Datensatz mit der neuesten Version in $tablename nicht gefunden werden. Es kann daher nicht abgegrenzt werden.";
        // check the validity limit.
        $current_valid_from32 = (is_null($record["ValidFrom"])) ? 0 : $this->efa_tables->value_validity32(
                $record["ValidFrom"]);
        $new_invalid_from32 = $this->efa_tables->value_validity32($validityLimit);
        if ($new_invalid_from32 <= $current_valid_from32)
            return "Zum Objekt mit dem Namen " . $name .
                     "in $tablename beginnt die Gültigkeit der neuesten Version (" . $current_valid_from32 .
                     ") nach der geforderten Abgrenzung (" . $new_invalid_from32 .
                     "). Es kann daher nicht abgegrenzt werden.";
        
        // Delimit the record
        $record["InvalidFrom"] = $validityLimit;
        $record["ChangeCount"] = intval($record["ChangeCount"]) + 1;
        $record["LastModification"] = "update";
        $record["LastModified"] = time() . "000";
        $appUserID = $_SESSION["User"][$this->toolbox->users->user_id_field_name];
        if ($execute) {
            $delimit_success = $this->socket->update_record_matched($appUserID, $tablename, 
                    ["ecrid" => $ecrid
                    ], $record);
            if (strlen($delimit_success) > 0)
                return "Zum Objekt mit dem Namen " . $name .
                         "in $tablename konnte in der Datenbank nicht abgegrenzt werden: " . $delimit_success;
        }
        
        // adjust the validity
        $record["ValidFrom"] = $validityLimit;
        $record["InvalidFrom"] = $this->efa_tables->forever64;
        // copy the changed values
        foreach ($new_version_record as $key => $new_value)
            $record[$key] = $new_value;
        // remove all system generated fields, except the Id, because the record will be insterted as new.
        foreach (Efa_tables::$server_gen_fields[$tablename] as $sysfield)
            if (strcmp($sysfield, "Id") != 0)
                unset($record[$sysfield]);
        return $record;
    }
}