<?php

// TODO introduced to avoid a fata error when updating from < 2.3.2_13 to 2.3.2_13ff. in April 2023. Remove
// some day
if (! function_exists("i"))
    include_once "../classes/init_i18n.php";

/**
 * class file for the efaCloud data verification and modification. This class adds to the Efa_tables class
 * whih deifnes tables type semantics and contains static checker functions.
 */
class Efa_audit
{

    /**
     * Defaults values for some of those columns that must not be empty.
     */
    private static $defaults_not_empty_values = [
            "efa2boatdamages" => ["Severity" => "LIMITEDUSEABLE"
            ],"efa2boatreservations" => ["Type" => "ONETIME"
            ]
    ];

    /**
     * Column names of those columns that must be checked not to contain a UUID of a record which shall be
     * deleted.
     */
    private static $assert_not_referenced_fields = [
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
     * Column names of those columns that are expected to be unique for the same UUID. If with a dot, both
     * parts ANDed must be unique.
     */
    private static $warn_duplicates_fields = ["efa2boats" => ["Name"
    ],"efa2destinations" => ["Name"
    ],"efa2groups" => ["Name"
    ],"efa2persons" => ["FirstName.LastName"
    ]
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
     * The data base connection socket.
     */
    private $socket;

    /**
     * The generic toolbox.
     */
    private $toolbox;

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
        include_once "../classes/efa_record.php";
        include_once "../classes/efa_tables.php";
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
     * @param bool $html
     *            set true to get a html formatted output, false for log text.
     * @return string the audit result.
     */
    public function data_integrity_audit (bool $html)
    {
        include_once "../classes/tfyh_list.php";
        $audit_user = $this->toolbox->users->session_user;
        $audit_user_id = $audit_user[$this->toolbox->users->user_id_field_name];
        $user_is_admin = (strcasecmp($this->toolbox->users->session_user["Rolle"], "admin") == 0);
        
        // update client configurations to ensure they are up to date before the audit starts.
        include_once "../classes/efa_config.php";
        $efa_config = new Efa_config($this->toolbox);
        $efa_config->parse_client_configs();
        
        $lists = [];
        $lists["corrupt"] = [];
        $lists["uuidnames"] = [];
        $lists["uuidref"] = [];
        $lists["duplicate"] = [];
        $lists["nonempty"] = [];
        $lists["virtual"] = [];
        
        $uuid_names = [];
        $uuid_invalids32 = [];
        $uuid_deleted = [];
        $uuid_refs = [];
        $table_keys = [];
        $start_time = time();
        $last_step = time();
        
        // start with collection of all UUID names and invalid froms
        // =========================================================
        $audit_result = "<li><b>" . i("Uht2dK|List of object IDs") .
                 "<sup class='eventitem' id='showhelptext_UUIDecrid'>&#9432;</sup>" . " " .
                 i("BtUecZ|in the database") . "</b></li>\n<ul>";
        $audit_log = date("Y-m-d H:i:s") . " +0 " . i("X2GfIo|Starting data base integ...") . "\n";
        $all_ids_count = 0;
        $list_definitions = new Tfyh_list("../config/lists/efaAuditUUIDnames", 0, "", $this->socket, 
                $this->toolbox);
        $list_definitions = $list_definitions->get_all_list_definitions();
        for ($list_index = 0; $list_index < count($list_definitions); $list_index ++) {
            $list_id = intval($list_definitions[$list_index]["id"]);
            $lists["uuidnames"][$list_id] = new Tfyh_list("../config/lists/efaAuditUUIDnames", $list_id, "", 
                    $this->socket, $this->toolbox);
            $table_name = $lists["uuidnames"][$list_id]->get_table_name();
            $ids_count = 0;
            $col_invalidFrom = $lists["uuidnames"][$list_id]->get_field_index("InvalidFrom");
            $col_deleted = $lists["uuidnames"][$list_id]->get_field_index("Deleted");
            $col_ecrid = $lists["uuidnames"][$list_id]->get_field_index("ecrid");
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
                    $invalidFrom = Efa_tables::$forever64;
                $invalidFrom32 = Efa_tables::value_validity32($invalidFrom);
                if (! isset($uuid_invalids32[$uuid]) || ($invalidFrom32 > $uuid_invalids32[$uuid]))
                    $uuid_invalids32[$uuid] = $invalidFrom32;
                // add to deleted index
                $deleted_val = $row[$col_deleted];
                $uuid_deleted[$uuid] = isset($deleted_val) && (strlen($deleted_val) > 0) &&
                         (strcasecmp($deleted_val, "true") == 0);
            }
            if ($ids_count > 0)
                $audit_result .= "<li>$ids_count " . i("FjaAjn|Object IDs in") . " $table_name.</li>\n";
            $all_ids_count += $ids_count;
        }
        $duration = time() - $last_step;
        $last_step = time();
        $audit_result .= "<li>$all_ids_count " . i("OV7xZi|Object IDs in total.") . "</li>\n";
        $audit_result .= "<li>" . i("tZIRPY|Duration: %1 seconds.", $duration) . "</li>";
        $audit_result .= "</ul>";
        $audit_log .= date("Y-m-d H:i:s") . " +" . $duration . " " . i("YQMCEW|Object IDs collected") . ": " .
                 $all_ids_count . "\n";
        
        // continue with compilation of all UUID references
        // ================================================
        $audit_result .= "<li><b>" . i("DyueMN|List of references to ob...") .
                 "<sup class='eventitem' id='showhelptext_UUIDecrid'>&#9432;</sup>" . " " .
                 i("TOfV45|in the database") . "</b></li>\n<ul>";
        $all_id_refs_count = 0;
        $list_definitions = new Tfyh_list("../config/lists/efaAuditUUIDrefs", 0, "", $this->socket, 
                $this->toolbox);
        $list_definitions = $list_definitions->get_all_list_definitions();
        for ($list_index = 0; $list_index < count($list_definitions); $list_index ++) {
            $list_id = intval($list_definitions[$list_index]["id"]);
            $lists["uuidref"][$list_id] = new Tfyh_list("../config/lists/efaAuditUUIDrefs", $list_id, "", 
                    $this->socket, $this->toolbox);
            $table_name = $lists["uuidref"][$list_id]->get_table_name();
            $id_refs_count = 0;
            $key_cols = [];
            $name_cols = [];
            $table_keys[$table_name] = "";
            if (is_array(Efa_tables::$efa_data_key_fields[$table_name]))
                foreach (Efa_tables::$efa_data_key_fields[$table_name] as $field_name) {
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
                        $row_name .= ' ' . substr(((isset($row[$c])) ? $row[$c] : ""), 0, 30);
                }
                // add all referenced UUIDs. col #0 is always the ecrid.
                for ($c = 1; $c < count($row); $c ++) {
                    if (! in_array($c, $key_cols) && ! in_array($c, $name_cols) && isset($row[$c]) &&
                             (strlen($row[$c]) > 0)) {
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
                $audit_result .= "<li>$id_refs_count " . i("vWuyPP|References to object IDs...") .
                         " $table_name</li>\n";
            $all_id_refs_count += $id_refs_count;
        }
        
        /* for debugging: echo "<code>"; foreach ($uuid_refs as $uuid => $refs) { $name = $uuid_names[$uuid];
         * $count = count($refs); echo "uuid: $uuid: $name Anzahl: $count<br>"; $c = 0; foreach($refs as $ref)
         * { if ($c < 10) echo "&nbsp;&nbsp;&nbsp;" . $ref . "<br>"; $c++; } } echo "</code>"; exit(); */
        $duration = time() - $last_step;
        $last_step = time();
        $audit_result .= "<li>" . i("XUdGql|In sum %1 references to ...", $all_id_refs_count) . "</li>\n";
        $audit_result .= "<li>" . i("pp5hEg|Duration: %1 seconds.", $duration) . "</li>";
        $audit_result .= "</ul>";
        $audit_log .= date("Y-m-d H:i:s") . " +" . $duration . " " . i("lW1weM|References collected") . "\n";
        
        // continue with duplicate ecrid check
        // ===================================
        $audit_result .= "<li><b>" . i("Duplicate ecrid check") . "</b></li>\n<ul>";
        $ecrids = [];
        $duplicate_found = false;
        $list_definitions = new Tfyh_list("../config/lists/allEcrids", 0, "", $this->socket, $this->toolbox);
        $list_definitions = $list_definitions->get_all_list_definitions();
        for ($list_index = 0; $list_index < count($list_definitions); $list_index ++) {
            $list_id = intval($list_definitions[$list_index]["id"]);
            $ecrid_list = new Tfyh_list("../config/lists/allEcrids", $list_id, "", $this->socket, 
                    $this->toolbox);
            $table_name = $ecrid_list->get_table_name();
            foreach ($ecrid_list->get_rows() as $row) {
                $ecrid = $row[0];
                if (isset($ecrids[$ecrid])) {
                    $duplicate_found = true;
                    $warning = "-- !!! WARNING !!! ---\nDUPLICATE ecrid '$ecrid' IN $table_name AND " .
                             $ecrids[$ecrid] . "!! MUST NOT OCCUR. PLEASE CORRECT.\n-- !!! WARNING !!! ---";
                    $audit_log .= $warning . "\n";
                    $audit_result .= "<li><b>$warning.</b></li>\n";
                } else
                    $ecrids[$ecrid] = $table_name;
            }
        }
        
        /* for debugging: echo "<code>"; foreach ($uuid_refs as $uuid => $refs) { $name = $uuid_names[$uuid];
         * $count = count($refs); echo "uuid: $uuid: $name Anzahl: $count<br>"; $c = 0; foreach($refs as $ref)
         * { if ($c < 10) echo "&nbsp;&nbsp;&nbsp;" . $ref . "<br>"; $c++; } } echo "</code>"; exit(); */
        $duration = time() - $last_step;
        $last_step = time();
        $audit_result .= "<li>" . i("pp5hEg|Duration: %1 seconds.", $duration) . "</li>";
        if (! $duplicate_found) {
            $audit_result .= "<li>" . count($ecrids) . " ecrids ok.</li>\n";
            $audit_log .= date("Y-m-d H:i:s") . " +" . $duration . " " . count($ecrids) . " ecrids ok.\n";
        } else {
            $audit_result .= "<li>ECRIDS NOT OK. PLEASE CORRECT.</li>\n";
            $audit_log .= date("Y-m-d H:i:s") . " +" . $duration . " ECRIDS NOT OK. PLEASE CORRECT.\n";
        }
        $audit_result .= "</ul>";
        
        // continue with check for corrupt data
        // ====================================
        $audit_result .= "<li><b>" . i("tSRTJf|List of corrupt records") .
                 "<sup class='eventitem' id='showhelptext_AuditKorrupteDatensaetze'>&#9432;</sup>" . "</b><br>" . i(
                        "ZqPC6r|Data records for which t...") . "</li>\n<ul>";
        $all_id_refs_count = 0;
        $list_definitions = new Tfyh_list("../config/lists/efaAuditCorruptData", 0, "", $this->socket, 
                $this->toolbox);
        $list_definitions = $list_definitions->get_all_list_definitions();
        $corrupt_records_cnt_all = 0;
        $corrupt_records_list_all = "";
        for ($list_index = 0; $list_index < count($list_definitions); $list_index ++) {
            $corrupt_records_cnt = 0;
            $corrupt_records_list = "";
            $list_id = intval($list_definitions[$list_index]["id"]);
            $lists["corrupt"][$list_id] = new Tfyh_list("../config/lists/efaAuditCorruptData", $list_id, "", 
                    $this->socket, $this->toolbox);
            $table_name = $lists["corrupt"][$list_id]->get_table_name();
            $ecrid_index = $lists["corrupt"][$list_id]->get_field_index("ecrid");
            $lastModification_index = $lists["corrupt"][$list_id]->get_field_index("LastModification");
            $lastModified_index = $lists["corrupt"][$list_id]->get_field_index("LastModified");
            $changeCount_index = $lists["corrupt"][$list_id]->get_field_index("ChangeCount");
            foreach ($lists["corrupt"][$list_id]->get_rows() as $row) {
                $cause = "";
                if (! isset($row[$ecrid_index]) || strlen($row[$ecrid_index]) == 0)
                    $cause .= i("mhjGNl|Ecrid specification is m...") . ", ";
                if (! isset($row[$lastModification_index]) || is_null($row[$lastModification_index]) ||
                         strlen($row[$lastModification_index]) == 0)
                    $cause .= i("mBOQpn|Information on the type ...") . ", ";
                if (! isset($row[$lastModified_index]) || is_null($row[$lastModified_index]) ||
                         strlen($row[$lastModified_index]) == 0)
                    $cause .= i("imsQg3|LastModified information...") . ", ";
                if (isset($row[$lastModified_index]) && strlen($row[$lastModified_index]) < 3)
                    $cause .= i("MGQrre|Last modification date i...") . ", ";
                if (! isset($row[$changeCount_index]) || is_null($row[$changeCount_index]) ||
                         strlen($row[$changeCount_index]) == 0)
                    $cause .= i("FE4Of4|Change counter missing") . ", ";
                if (isset($row[$changeCount_index]) && intval($row[$changeCount_index]) == 0)
                    $cause .= i("i0SFzf|Change counter invalid") . ", ";
                if (strlen($cause) == 0)
                    $cause = i("KARSRU|undetermined error, plea...");
                // get full record
                $full_record = (isset($row[$ecrid_index])) ? $this->socket->find_record($table_name, "ecrid", 
                        $row[$ecrid_index]) : $lists["corrupt"][$list_id]->get_named_row($row);
                $empty_notification = (Efa_record::is_content_empty($table_name, $full_record)) ? i(
                        "5zJxTU|(empty content)") . " " : "";
                $corrupt_records_list .= "<li>$cause " . i("taO63K|in record with the ID") . " '" .
                         $row[$ecrid_index] . "' $empty_notification<a class='eventitem' id='viewrecord_" .
                         $table_name . "_" . $row[$ecrid_index] . "'>" . i("XZwd9r|view") . "</a></li>\n";
                // collect key of referencing record
                $corrupt_records_cnt ++;
            }
            if ($corrupt_records_cnt > 0) {
                $corrupt_records_list_all .= "<li>" . i("OZYUda|audit") . " $table_name:</li><ul>\n" .
                         $corrupt_records_list . "</ul>";
                $corrupt_records_cnt_all += $corrupt_records_cnt;
            } else {
                $corrupt_records_list_all .= "<li>" . i("LYwuMx|audit") . " $table_name: " . i("lKuUN0|ok.") .
                         "</li>";
            }
        }
        $duration = time() - $last_step;
        $last_step = time();
        $dauer_li = "<li>" . i("WsmOAA|Duration: %1 seconds.", $duration) . "</li>";
        if ($corrupt_records_cnt_all > 0)
            $audit_result .= $corrupt_records_list_all . $dauer_li . "</ul>";
        else
            $audit_result .= "<li>" . i("7TSRnp|None.") . "</li>$dauer_li</ul>";
        $audit_log .= date("Y-m-d H:i:s") . " +" . $duration . " " . i("fTom5D|Corrupt records fixed.") . "\n";
        
        // continue with duplicate warnings and unique assertions
        // ======================================================
        $audit_result .= "<li><b>" . i("XRCLkB|Duplicate check") . "</b>" .
                 "<sup class='eventitem' id='showhelptext_AuditDubletten'>&#9432;</sup>" . "</li>\n<ul>";
        $list_definitions = new Tfyh_list("../config/lists/efaAuditDuplicates", 0, "", $this->socket, 
                $this->toolbox);
        $list_definitions = $list_definitions->get_all_list_definitions();
        for ($list_index = 0; $list_index < count($list_definitions); $list_index ++) {
            $list_id = intval($list_definitions[$list_index]["id"]);
            $lists["duplicate"][$list_id] = new Tfyh_list("../config/lists/efaAuditDuplicates", $list_id, "", 
                    $this->socket, $this->toolbox);
            $table_name = $lists["duplicate"][$list_id]->get_table_name();
            $audit_result .= "<li>" . i("QsmoVM|audit") . " $table_name:</li>\n<ul>";
            $id_col = $lists["duplicate"][$list_id]->get_field_index("Id");
            
            // prepare arrays
            $warn_duplicates_cols = [];
            $warn_duplicates_vals = [];
            if (isset(self::$warn_duplicates_fields[$table_name]) &&
                     is_array(self::$warn_duplicates_fields[$table_name]) &&
                     (count(self::$warn_duplicates_fields[$table_name]) > 0)) {
                foreach (self::$warn_duplicates_fields[$table_name] as $field_names) {
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
            if (is_array(Efa_record::$assert_unique_fields[$table_name]) &&
                     (count(Efa_record::$assert_unique_fields[$table_name]) > 0)) {
                foreach (Efa_record::$assert_unique_fields[$table_name] as $field_names) {
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
                $invalid_now = time();
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
                                    $audit_result .= "<li>" . i("1iSIZo|°%1° with value °%2° has...", 
                                            $fieldnames, $value);
                                    foreach ($ids as $id => $cnt) {
                                        $audit_result .= "<br>" . $id;
                                        $invalid32 = $uuid_invalids32[$id];
                                        if (isset($uuid_names[$id]))
                                            $audit_result .= " = " . htmlspecialchars($uuid_names[$id]);
                                        if ($invalid32 > 0)
                                            $audit_result .= ", " . Efa_tables::format_validity32($invalid32);
                                        if (isset($uuid_deleted[$id]) && $uuid_deleted[$id])
                                            $audit_result .= " " . i("yrvZC9|-DELETED-");
                                        if (isset($uuid_refs[$id]))
                                            $audit_result .= " (" . count($uuid_refs[$id]) . " " .
                                                     i("kbRZ71|times") . ")";
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
                            $audit_result .= i("stfYYi| ** °%1° with value °%2°...", $fieldnames, $value) .
                                     "<br>";
                            foreach ($occurrences as $occurrence) {
                                $audit_result .= "        ";
                                $named_row = $lists["duplicate"][$list_id]->get_named_row($occurrence);
                                foreach ($named_row as $key => $value) {
                                    $audit_result .= "$key = $value";
                                    if (! is_null($value) && $this->is_UUID($value)) {
                                        $invalid32 = $uuid_invalids32[$value];
                                        if ($invalid32 > 0)
                                            $audit_result .= ", " . Efa_tables::format_validity32($invalid32);
                                        if (isset($uuid_refs[$value]))
                                            $audit_result .= " (" . count($uuid_refs[$value]) . " mal)";
                                    }
                                    $audit_result .= "; ";
                                }
                                $audit_result .= " <a class='eventitem' id='viewrecord_" . $table_name . "_" .
                                         $named_row["ecrid"] . "'>" . i("ePOJhc|view") . "</a>";
                                $audit_result .= "<br>";
                            }
                            $audit_result .= "</li>\n";
                        }
                    }
                }
            }
            $audit_result .= "</ul>";
        }
        $duration = time() - $last_step;
        $last_step = time();
        $audit_result .= "<li>" . i("0zU8XJ|Duration: %1 seconds.", $duration) . "</li>";
        $audit_result .= "</ul>";
        $audit_log .= date("Y-m-d H:i:s") . " +" . $duration . " " . i("dyCw0A|Duplicates checked.") . "\n";
        
        // continue with non-empty checks
        // ==============================
        $audit_result .= "<li><b>" . i("pMdghG|Missing information") .
                 "<sup class='eventitem' id='showhelptext_AuditKorrupteDatensaetze'>&#9432;</sup>" .
                 "</b></li>\n<ul>";
        $list_definitions = new Tfyh_list("../config/lists/efaAuditNotEmpty", 0, "", $this->socket, 
                $this->toolbox);
        $list_definitions = $list_definitions->get_all_list_definitions();
        for ($list_index = 0; $list_index < count($list_definitions); $list_index ++) {
            $list_id = intval($list_definitions[$list_index]["id"]);
            $lists["nonempty"][$list_id] = new Tfyh_list("../config/lists/efaAuditNotEmpty", $list_id, "", 
                    $this->socket, $this->toolbox);
            $table_name = $lists["nonempty"][$list_id]->get_table_name();
            $audit_result .= "<li>" . i("hqvnxI|audit") . " $table_name:</li>\n<ul>";
            $assert_not_empty = array_merge(Efa_tables::$efa_data_key_fields[$table_name], 
                    Efa_record::$assert_not_empty_fields[$table_name]);
            $defaults = isset(self::$defaults_not_empty_values[$table_name]) ? self::$defaults_not_empty_values[$table_name] : false;
            foreach ($lists["nonempty"][$list_id]->get_rows() as $row) {
                $named_row = $lists["nonempty"][$list_id]->get_named_row($row);
                $missing = "";
                $defaulting = "";
                $default_values = "";
                $recordstr = "";
                $record_update = ["ecrid" => $named_row["ecrid"]
                ];
                foreach ($named_row as $key => $value) {
                    if (in_array($key, $assert_not_empty) && (strlen($value) == 0)) {
                        if (is_array($defaults) && array_key_exists($key, $defaults)) {
                            $defaulting .= $key . ", ";
                            $default_values .= $defaults[$key] . ", ";
                            $record_update[$key] = $defaults[$key];
                        } else
                            $missing .= $key . ", ";
                    }
                    $recordstr .= "$key = $value; ";
                }
                $recordstr .= " <a class='eventitem' id='viewrecord_" . $table_name . "_" . $row[$ecrid_index] .
                         "'>" . i("IqBXjy|view") . "</a>";
                if (strlen($missing) > 0)
                    $missing = substr($missing, 0, strlen($missing) - 2);
                if (strlen($defaulting) > 0)
                    $defaulting = substr($defaulting, 0, strlen($defaulting) - 2);
                if (strlen($default_values) > 0)
                    $default_values = substr($default_values, 0, strlen($default_values) - 2);
                if (strlen($defaulting) > 0) {
                    $audit_result .= "<li>" . i("n47bZV|The necessary informatio...", $defaulting, $recordstr) .
                             "</i></li>\n";
                }
                if (strlen($missing) > 0) {
                    $audit_result .= "<li>" . i("VFh57f|The necessary informatio...", $missing, $recordstr) .
                             "</li>\n";
                }
            }
            $audit_result .= "</ul>";
        }
        $duration = time() - $last_step;
        $last_step = time();
        $audit_result .= "<li>" . i("zxvnvd|Duration: %1 seconds.", $duration) . "</li>";
        $audit_result .= "</ul>";
        $audit_log .= date("Y-m-d H:i:s") . " +" . $duration . " " . i("II49o0|Missing fields checked.") . "\n";
        
        // continue with virtual fields check and correction
        // =================================================
        $audit_result .= "<li><b>" . i("IaiL4s|Virtual data fields") .
                 "<sup class='eventitem' id='showhelptext_AuditVirtuelleDatenfelder'>&#9432;</sup>" .
                 "</b></li>\n<ul>";
        $list_definitions = new Tfyh_list("../config/lists/efaAuditVirtualFieldsFull", 0, "", $this->socket, 
                $this->toolbox);
        $list_definitions = $list_definitions->get_all_list_definitions();
        include_once '../classes/efa_uuids.php';
        $efa_uuids = new Efa_uuids($this->toolbox, $this->socket);
        $limit_age = strval(time() - (30 * 86400)) . "000";
        $list_args = ["{LastModified}" => $limit_age
        ];
        $tables_log = "";
        for ($list_index = 0; $list_index < count($list_definitions); $list_index ++) {
            $list_id = intval($list_definitions[$list_index]["id"]);
            $lists["virtual"][$list_id] = new Tfyh_list("../config/lists/efaAuditVirtualFieldsFull", $list_id, 
                    "", $this->socket, $this->toolbox, $list_args);
            $table_name = $lists["virtual"][$list_id]->get_table_name();
            $audit_result .= "<li>" . i("KKGLqS|audit") . " $table_name:";
            $virtual_fields = Efa_tables::$virtual_fields[$table_name];
            $ecrid_field_index = $lists["virtual"][$list_id]->get_field_index("ecrid");
            $audit_result .= "<br>" . i("zkwDI7|lines") . ": " . count(
                    $lists["virtual"][$list_id]->get_rows());
            $checked = 0;
            $corrected = 0;
            $failed = 0;
            foreach ($lists["virtual"][$list_id]->get_rows() as $row) {
                $matching_key = ["ecrid" => $row[$ecrid_field_index]
                ];
                $full_record = $this->socket->find_record_matched($table_name, $matching_key);
                if ($full_record !== false) {
                    $record_with_correct_vf = Efa_tables::add_virtual_fields($full_record, $table_name, 
                            $this->toolbox, $this->socket, $efa_uuids);
                    if ($record_with_correct_vf !== false) {
                        $audit_result .= "<br>" . i("IfdaoB|Correct virtual fields f...", 
                                $row[$ecrid_field_index]);
                        $update_result = $this->socket->update_record_matched($audit_user_id, $table_name, 
                                $matching_key, $record_with_correct_vf);
                        if (strlen($update_result) == 0)
                            $corrected ++;
                        else
                            $failed ++;
                    }
                    $checked ++;
                }
            }
            if (($corrected > 0) || ($failed > 0))
                $audit_result .= " " . i("a5ador|checked: %1, corrected: ...", $checked, $corrected, $failed) .
                         "</li>\n";
            else
                $audit_result .= " ok.</li>\n";
            $tables_log .= " - " . $table_name . ":" . $checked;
        }
        $duration = time() - $last_step;
        $last_step = time();
        $audit_result .= "<li>" . i("xnRS5I|Duration: %1 seconds.", $duration) . "</li>";
        $audit_result .= "</ul>";
        $audit_log .= date("Y-m-d H:i:s") . " +" . $duration . " " . $tables_log . ": " .
                 i("R223pr|Virtual fields corrected...") . "\n";
        
        // complete with list of unused UUIDs
        // ==================================
        $unused_count = 0;
        include_once "../classes/efa_archive.php";
        foreach ($uuid_names as $uuid => $name)
            if ((! isset($uuid_refs[$uuid]) || (count($uuid_refs[$uuid]) == 0)) &&
                     (strpos($uuid_names[$uuid], Efa_archive::$archive_id_prefix) === false))
                $unused_count ++;
        $audit_result .= "<li><b>" . i("kXZ9rj|The following %1 of the ...", $unused_count, $all_ids_count) .
                 "</b></li>\n<ul>";
        foreach ($uuid_names as $uuid => $name) {
            if (! isset($uuid_refs[$uuid]) || (count($uuid_refs[$uuid]) == 0)) {
                if (strpos($uuid_names[$uuid], Efa_archive::$archive_id_prefix) === false) {
                    $audit_result .= "<li>" . $uuid . " = " . htmlspecialchars($uuid_names[$uuid]);
                    $invalid32 = $uuid_invalids32[$uuid];
                    if ($invalid32 > 0)
                        $audit_result .= "; " . Efa_tables::format_validity32($invalid32);
                    $audit_result .= "</li>\n";
                }
            }
        }
        
        // finally remove obsolete configuration directories
        // =================================================
        $audit_result .= "<li><b>" . i("Removing obsolete client configurations") .
        "</b></li>\n<ul>";
        $client_dirs = scandir("../uploads");
        $removed = 0;
        foreach ($client_dirs as $client_dir) {
            if (is_numeric($client_dir) && (intval($client_dir) > 0)) {
                $user = $this->socket->find_record($this->toolbox->users->user_table_name,
                        $this->toolbox->users->user_id_field_name, $client_dir);
                if ($user === false) {
                    // the user was removed. Delete its upload directory
                    $this->toolbox->rrmdir("../uploads/" . $client_dir);
                    $audit_result .= "<li>" . $client_dir . "</li>\n";
                }
            }
        }
        if ($removed == 0)
            $audit_result .= "<li>---</li>\n";
            
        $duration = time() - $last_step;
        $last_step = time();
        $audit_result .= "<li>" . i("RGeCEH|Duration: %1 seconds.", $duration) . "</li>";
        $audit_result .= "</ul>";
        $audit_log .= date("Y-m-d H:i:s") . " +" . $duration . " " . i("deuFgY|Completed.") . "\n";
        file_put_contents("../log/app_db_audit.html", $audit_result);
        
        return ($html) ? $audit_result : $audit_log;
    }

    /**
     * Check all records of efa2logbook and efa2clubwork whether they comply with the respective book's period
     * contstraints.
     */
    public function period_correctness_audit ()
    {
        global $dfmt_d, $dfmt_dt;
        $audit_user = $this->toolbox->users->session_user;
        $audit_user_id = $audit_user[$this->toolbox->users->user_id_field_name];
        $user_is_admin = (strcasecmp($this->toolbox->users->session_user["Rolle"], "admin") == 0);
        
        $audit_res = "<li><b>" . i("uMJF7t|Reference configuration") . "</b></li><ul>";
        $reference_client = $this->toolbox->config->get_cfg()["reference_client"];
        if (intval($reference_client) == 0)
            $reference_client = "<b>" . i("dB1fLF|not defined") . "</b>. " .
                     i("RYw5AW|Please set in menu °Set ...");
        $audit_res .= "<li>" . $reference_client . "</li>";
        $audit_res .= "</ul>";
        
        // parse configurations to ensure they are up to date before the audit starts.
        include_once "../classes/efa_config.php";
        $efa_config = new Efa_config($this->toolbox);
        $efa_config->parse_client_configs();
        // Show books summary
        $audit_res .= "<li><b>" . i("HIkuMt|Overview of logbooks and...") . "</b></li><ul>";
        $audit_res .= "<li>" . str_replace("\n", "</li><li>", trim($efa_config->summary_books)) . "</li>";
        $audit_res .= "</ul>";
        
        // compile misfit periods logbooks.
        $audit_res .= "<li><b>" . i("8QYlfo|Trips that do not match ...") . "</b></li><ul>";
        include_once "../classes/tfyh_list.php";
        $list = new Tfyh_list("../config/lists/efaAuditPeriods", 1, "", $this->socket, $this->toolbox);
        $rows = $list->get_rows();
        $index_logbookname = $list->get_field_index("Logbookname");
        $index_entryid = $list->get_field_index("EntryId");
        $index_date = $list->get_field_index("Date");
        $index_end_date = $list->get_field_index("EndDate");
        $index_ecrid = $list->get_field_index("ecrid");
        $index_lastmodification = $list->get_field_index("LastModification");
        
        foreach ($rows as $row) {
            $error_message = "";
            $logbook_name = $row[$index_logbookname];
            $entry_id = $row[$index_entryid];
            $date = $row[$index_date];
            $ecrid = $row[$index_ecrid];
            $is_delete = (strcasecmp($row[$index_lastmodification], "delete") == 0);
            if (! $is_delete) {
                $logbook_period = $efa_config->get_book_period($logbook_name, true);
                if ($logbook_period["book_matched"] === false)
                    $audit_res .= "<li>" . i("hxETBN|The logbook %1 (trip num...", $logbook_name, $entry_id) .
                             "</li>";
                else {
                    $logbook_start = $logbook_period["start_time"];
                    $logbook_end = $logbook_period["end_time"];
                    $entry_start = strtotime($date);
                    $entry_end = (isset($row[$index_end_date]) && (strlen($row[$index_end_date]) > 4)) ? strtotime(
                            $row[$index_end_date]) : $entry_start;
                    if (($entry_start < $logbook_start) || ($entry_start > $logbook_end) ||
                             ($entry_end < $logbook_start) || ($entry_end > $logbook_end)) {
                        $error_message = "<li>" . i("mY35PB|The trip %1 with start %...", $entry_id, 
                                date($dfmt_d, $entry_start), date($dfmt_d, $entry_end), $logbook_name, 
                                date($dfmt_d, $logbook_start), date($dfmt_d, $logbook_end));
                        $error_message .= " <a class='eventitem' id='viewrecord_" . $list->get_table_name() .
                                 "_" . $row[$index_ecrid] . "'>" . i("B6wzYu|view") . "</a>";
                        if ($user_is_admin)
                            $error_message .= " <a target='_blank' href='../pages/view_record.php?table=efa2logbook&ecrid=" .
                                     $row[$index_ecrid] . "'> - " . i("aIaf9l|Edit/delete in new tab") . "</a>";
                        $audit_res .= "</li>";
                    }
                    $audit_res .= $error_message;
                }
            }
        }
        $audit_res .= "</ul>";
        
        return $audit_res;
    }
}
