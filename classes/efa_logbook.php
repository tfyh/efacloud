<?php

/**
 *
 *       efaCloud
 *       --------
 *       https://www.efacloud.org
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

/**
 * class file for logbook reading, auditing and formatting capabilities.
 */
class Efa_logbook
{

    /**
     * The common toolbox used.
     */
    private $toolbox;

    /**
     * Tfyh_socket to data base.
     */
    private $socket;

    /**
     * efa uuid resolving utility class.
     */
    private $efa_uuids;

    /**
     * Efa_tables utility class.
     */
    private $efa_config;

    /**
     * an array with all logbook periods known from the respective client configuration.
     */
    private $logbook_periods;

    /**
     * The maximum number of recipients to get the personal logbook. This limit is needed to avoid memory
     * overflow.
     */
    private $max_no_recipients_personal_logbook = 1000;

    /**
     * the array of column names to be included, German version.
     */
    private $pers_logbook_cols_used_locale;

    /**
     * the array of column names to be included, German version.
     */
    private $pers_logbook_cols_used_en;

    /**
     * the array of column names to be included, German version.
     */
    private $table_tags;

    /**
     * Sessiontypes, only in German.
     * 
     * @var array
     */
    private $sessiontypes = ["LATEENTRY" => "Kilometernachtrag","MOTORBOAT" => "Motorboot",
            "NORMAL" => "normale Fahrt","REGATTA" => "Regatta","TRAININGCAMP" => "Trainingslager"
    ];

    /**
     * public Constructor. Runs the anonymization.
     */
    public function __construct (Tfyh_toolbox $toolbox, Tfyh_socket $socket)
    {
        $this->socket = $socket;
        $this->toolbox = $toolbox;
        include_once "../classes/efa_uuids.php";
        $this->efa_uuids = new Efa_uuids($toolbox, $socket);
        include_once "../classes/efa_config.php";
        $this->efa_config = new Efa_config($toolbox);
        $this->efa_config->load_efa_config();
        
        // special case: the column names of the personal logbook need e reverse translation
        // Create an associative array for this task. Translate the English names:
        $pers_logbook_cols_all_en = explode(",", 
                "EntryId,Date,Boat,Cox,Crew,StartTime,EndTime,Destination,Distance,Waters");
        // make sure the i18n resource definition matches the column name definitions above
        $pers_logbook_cols_all_local = explode(",", i("BqM9Lt|EntryId,Date,Boat,Cox,Cr..."));
        $pers_logbook_cols_translation = [];
        // provide a translation into English for all options
        for ($c = 0; $c < count($pers_logbook_cols_all_local); $c ++)
            $pers_logbook_cols_translation[$pers_logbook_cols_all_local[$c]] = $pers_logbook_cols_all_en[$c];
        
        // Than map the used local names to the corresponding English names.
        $cfg = $toolbox->config->get_cfg();
        $this->pers_logbook_cols_used_locale = (isset($cfg["pers_logbook_cols"]) &&
                 (strlen($cfg["pers_logbook_cols"]) > 0)) ? explode(",", $cfg["pers_logbook_cols"]) : $pers_logbook_cols_all_local;
        $this->pers_logbook_cols_used_en = [];
        for ($c = 0; $c < count($this->pers_logbook_cols_used_locale); $c ++) {
            $pers_logbook_col_used_locale = $this->pers_logbook_cols_used_locale[$c];
            if (! isset($pers_logbook_cols_translation[$pers_logbook_col_used_locale]))
                $this->pers_logbook_cols_used_locale[$c] = i("AvRL61|no such field: %1", 
                        $pers_logbook_col_used_locale);
            else
                $this->pers_logbook_cols_used_en[$pers_logbook_col_used_locale] = $pers_logbook_cols_translation[$pers_logbook_col_used_locale];
        }
        
        // now prepare the table layout.
        $this->table_tags = [];
        $this->table_tags["table"] = (isset($cfg["pers_logbook_table"]) &&
                 (strlen($cfg["pers_logbook_table"]) > 0)) ? "<table style=\"" . $cfg["pers_logbook_table"] .
                 "\">" : "<table>";
        $this->table_tags["tr"] = (isset($cfg["pers_logbook_tr"]) && (strlen($cfg["pers_logbook_tr"]) > 0)) ? "<tr style=\"" .
                 $cfg["pers_logbook_tr"] . "\">" : "<tr>";
        $this->table_tags["th"] = (isset($cfg["pers_logbook_th"]) && (strlen($cfg["pers_logbook_th"]) > 0)) ? "<th style=\"" .
                 $cfg["pers_logbook_th"] . "\">" : "<th>";
        $this->table_tags["td"] = (isset($cfg["pers_logbook_td"]) && (strlen($cfg["pers_logbook_td"]) > 0)) ? "<td style=\"" .
                 $cfg["pers_logbook_td"] . "\">" : "<td>";
    }

    /**
     * Sent personal logbooks to all persons which have an Email field set (must contain a '@').
     * 
     * @param bool $only_me
     *            set true to send the logbook only to the session user (test purposes).
     * @return number
     */
    public function send_logbooks (bool $only_me = false)
    {
        // find all persons with an Email address provided. Get not more than
        // $max_no_recipients_personal_logbook records to avoid memory problems.
        include_once "../classes/tfyh_list.php";
        $logbook_recipients_list = new Tfyh_list("../config/lists/logbook_management", 6, "", $this->socket, 
                $this->toolbox);
        // collect Ids and put to associative array. Filter invalid ones.
        $logbook_recipients = [];
        $logbook_recipient_Ids = [];
        if ($only_me) {
            $my_person_record = $this->socket->find_record_matched("efa2persons", 
                    ["Id" => $this->toolbox->users->session_user["PersonId"],
                            "InvalidFrom" => Efa_tables::$forever64
                    ]);
            $logbook_recipient_Ids[] = $my_person_record["Id"];
            $logbook_recipients[$my_person_record["Id"]] = $my_person_record;
        } else
            foreach ($logbook_recipients_list->get_rows() as $logbook_recipient_row) {
                $logbook_recipient = $logbook_recipients_list->get_named_row($logbook_recipient_row);
                $logbook_recipient_Ids[] = $logbook_recipient["Id"];
                $logbook_recipients[$logbook_recipient["Id"]] = $logbook_recipient;
            }
        // create all the personal logbooks
        $personal_logbooks = $this->get_logbook_for($logbook_recipient_Ids);
        
        // send all logbooks.
        include_once '../classes/tfyh_mail_handler.php';
        $cfg = $this->toolbox->config->get_cfg();
        $mail_handler = new Tfyh_mail_handler($cfg);
        $mails_sent = 0;
        
        foreach ($personal_logbooks as $uuid => $personal_logbook) {
            if (strlen($uuid) > 30) {
                $mailfrom = $mail_handler->system_mail_sender;
                $mailto = $logbook_recipients[$uuid]["Email"];
                $mailsubject = "[" . $cfg["acronym"] . "] " . i("7UNg40|Personal logbook");
                $mailbody = "<html><body><p>" . i("oR4Ytj|All trips for %1 in logb...", 
                        $logbook_recipients[$uuid]["FirstLastName"]) . "</p>" . $personal_logbook .
                         $cfg["mail_subscript"] . $cfg["mail_footer"];
                if (! $only_me || (strcasecmp($mailto, $this->toolbox->users->session_user["@mail"]) == 0)) {
                    $success = $mail_handler->send_mail($mailfrom, $mailfrom, $mailto, "", "", $mailsubject, 
                            $mailbody);
                    if ($success)
                        $mails_sent ++;
                }
            }
        }
        
        // return the count of personal logbooks sent.
        return $mails_sent;
    }

    /**
     *
     * @return the start of the sports year which is the current sports year based on the sports_year_start
     *         configuration parameter as time (seconds since 1970).
     */
    private function get_sports_year_start (int $so_many_years_ago = 0)
    {
        $cfg = $this->toolbox->config->get_cfg();
        $sports_year_start_month = (intval($cfg["sports_year_start"]) < 10) ? "0" . $cfg["sports_year_start"] : $cfg["sports_year_start"];
        $sports_year_start_this_year = strtotime(date("Y") . "-" . $sports_year_start_month . "-01");
        if ($sports_year_start_this_year > time())
            // the current sports year started last calendar year
            $so_many_years_ago ++;
        $sports_year_start_years_ago = strtotime(
                (intval(date("Y")) - $so_many_years_ago) . "-" . $sports_year_start_month . "-01");
        return $sports_year_start_years_ago;
    }

    /**
     *
     * @return the end of the sport year which is the current sport year based on the sports_year_start
     *         configuration parameter as time (seconds since 1970).
     */
    private function get_sports_year_end (int $so_many_years_ago = 0)
    {
        $sports_year_start = $this->get_sports_year_start($so_many_years_ago - 1);
        return $sports_year_start - 86400;
    }

    /**
     * Get the current years logbook as csv "|" separated and unquoted (like in efa 1).
     * 
     * @param bool $add_membership_numbers
     *            set true to add the membership numbers of each person at the end as _Number1_Number2_...
     *            list.
     * @param bool $add_ids
     *            set true to add the membership numbers of each person at the end as _Id1_Id2_... list.
     * @param bool $previous_year
     *            set true to get the previous years logbook rather than the current year.
     * @return string
     */
    public function get_logbook (bool $add_membership_numbers, bool $add_ids, bool $previous_year)
    {
        // get all trips of the current sports year.
        include_once '../classes/tfyh_list.php';
        $sports_year_filter = [
                "{sports_year_start}" => date("Y-m-d", $this->get_sports_year_start(($previous_year) ? 1 : 0)),
                "{sports_year_end}" => date("Y-m-d", $this->get_sports_year_end(($previous_year) ? 1 : 0))
        ];
        $trips_list = new Tfyh_list("../config/lists/efaExportTables", 17, "", $this->socket, $this->toolbox, 
                $sports_year_filter);
        $all_trips_raw = $trips_list->get_rows();
        $sequence = ["Logbookname","EntryId","Date","EndDate","Boat","Cox","Crew","StartTime","EndTime",
                "Waters","Destination","DestinationVariantName","Distance","SessionType","Comments"
        ];
        $waters_predefined = $this->waters_predefined();
        
        $csv = "";
        foreach ($sequence as $field)
            $csv .= $field . "|";
        if ($add_membership_numbers)
            $csv .= "CoxAndCrewMembershipNos" . "|";
        if ($add_ids)
            $csv .= "CoxAndCrewIds" . "|";
        $csv = mb_substr($csv, 0, mb_strlen($csv) - 1) . "\n";
        foreach ($all_trips_raw as $trip_raw) {
            $trip = $trips_list->get_named_row($trip_raw);
            if (isset($trip["DestinationId"]) && ! isset($trip["WatersIdList"]) && ! isset(
                    $trip["WatersNameList"]))
                $trip["WatersIdList"] = $waters_predefined[$trip["DestinationId"]];
            $trip_resolved = $this->resolve_trip($trip);
            foreach ($sequence as $field)
                $csv .= $trip_resolved[$field] . "|";
            if ($add_membership_numbers)
                $csv .= $trip_resolved["CoxAndCrewMembershipNos"] . "|";
            if ($add_ids)
                $csv .= $trip_resolved["CoxAndCrewIds"] . "|";
            $csv = mb_substr($csv, 0, mb_strlen($csv) - 1) . "\n";
        }
        return $csv;
    }

    /**
     * Resolve all data within a trip record to clear names and create collective lists of Ids for cox and
     * crew as well as membership numbers for cox and crew.
     * 
     * @param array $trip            
     * @return array resolved trip record
     */
    private function resolve_trip (array $trip)
    {
        $resolved = [];
        $all_cox_and_crew_ids = "_";
        $all_cox_and_crew_membership_numbers = "_";
        $resolved["Boat"] = (isset($trip["BoatId"]) && (strlen($trip["BoatId"]) > 0)) ? $this->efa_uuids->resolve_UUID(
                $trip["BoatId"])[1] : $trip["BoatName"];
        if (isset($trip["CoxId"]) && (strlen($trip["CoxId"]) > 0)) {
            $resolved["Cox"] = $this->efa_uuids->resolve_UUID($trip["CoxId"])[1];
            $all_cox_and_crew_ids .= $trip["CoxId"] . "_";
            $all_cox_and_crew_membership_numbers .= $this->efa_uuids->membership_numbers[$trip["CoxId"]] . "_";
        } elseif (isset($trip["CoxName"]) && (strlen($trip["CoxName"]) > 0)) {
            $resolved["Cox"] = $trip["CoxName"];
            $all_cox_and_crew_ids .= "???_";
            $all_cox_and_crew_membership_numbers .= "???_";
        } else
            $resolved["Cox"] = "";
        $resolved["Crew"] = "";
        for ($i = 1; $i <= 24; $i ++) {
            $crewname = "";
            $crew_id_field = "Crew" . $i . "Id";
            $crew_name_field = "Crew" . $i . "Name";
            if (isset($trip[$crew_id_field]) && (strlen($trip[$crew_id_field]) > 0)) {
                $crewname = $this->efa_uuids->resolve_UUID($trip[$crew_id_field])[1];
                $all_cox_and_crew_ids .= $trip[$crew_id_field] . "_";
                $all_cox_and_crew_membership_numbers .= $this->efa_uuids->membership_numbers[$trip[$crew_id_field]] .
                         "_";
            } elseif (isset($trip[$crew_name_field]) && (strlen($trip[$crew_name_field]) > 0)) {
                $crewname .= $trip[$crew_name_field];
                $all_cox_and_crew_ids .= "???_";
                $all_cox_and_crew_membership_numbers .= "???_";
            }
            if (strlen($crewname) > 2)
                $resolved["Crew"] .= $crewname . "; ";
        }
        if (strlen($resolved["Crew"]) >= 2)
            $resolved["Crew"] = mb_substr($resolved["Crew"], 0, mb_strlen($resolved["Crew"]) - 2);
        
        $resolved["Destination"] = (isset($trip["DestinationId"]) && (strlen($trip["DestinationId"]) > 0)) ? $this->efa_uuids->resolve_UUID(
                $trip["DestinationId"])[1] : $trip["DestinationName"];
        $resolved["Waters"] = (isset($trip["WatersIdList"]) && (strlen($trip["WatersIdList"]) > 0)) ? $this->efa_uuids->resolve_UUID(
                explode(";", $trip["WatersIdList"])[0])[1] : $trip["WatersNameList"];
        if (count(explode(";", $trip["WatersIdList"])) > 1)
            $resolved["Waters"] .= ", ...";
        $resolved["Distance"] = $trip["Distance"];
        $resolved["EntryId"] = $trip["EntryId"];
        $resolved["Date"] = $trip["Date"];
        $resolved["StartTime"] = $trip["StartTime"];
        $resolved["EndTime"] = $trip["EndTime"];
        $resolved["Logbookname"] = $trip["Logbookname"];
        $resolved["SessionType"] = $this->sessiontypes[$trip["SessionType"]];
        $resolved["CoxAndCrewIds"] = $all_cox_and_crew_ids;
        $resolved["CoxAndCrewMembershipNos"] = $all_cox_and_crew_membership_numbers;
        return $resolved;
    }

    /**
     * Get the watersIdList per Destination o resolve the waters in case they are part of the destination
     * definition.
     * 
     * @return array $waters_predefined[destinationId] = WatersIdList
     */
    private function waters_predefined ()
    {
        $destinations_list = new Tfyh_list("../config/lists/efaExportTables", 7, "", $this->socket, 
                $this->toolbox);
        $all_destinations = $destinations_list->get_rows();
        $destination_uuid_index = $destinations_list->get_field_index("Id");
        $destination_waterslist_index = $destinations_list->get_field_index("WatersIdList");
        $waters_predefined = [];
        foreach ($all_destinations as $destination)
            $waters_predefined[$destination[$destination_uuid_index]] = $destination[$destination_waterslist_index];
        return $waters_predefined;
    }

    /**
     * Get all logbook entries for a set of persons based on his/her UUID
     * 
     * @param array $personUUIDs
     *            UUIDs of persons to get the trips for.
     * @return array for each person an html table with the trips found.
     */
    private function get_logbook_for (array $personUUIDs)
    {
        // get all trips of the current sports year. (Code duplicate to get_logbook to avoid array copying.
        include_once '../classes/tfyh_list.php';
        $sports_year_filter = ["{sports_year_start}" => date("Y-m-d", $this->get_sports_year_start()),
                "{sports_year_end}" => date("Y-m-d", $this->get_sports_year_end())
        ];
        $trips_list = new Tfyh_list("../config/lists/efaExportTables", 17, "", $this->socket, $this->toolbox, 
                $sports_year_filter);
        $all_trips_raw = $trips_list->get_rows();
        $all_trips = [];
        foreach ($all_trips_raw as $trip_raw)
            $all_trips[] = $trips_list->get_named_row($trip_raw);
        $waters_predefined = $this->waters_predefined();
        
        $all_logbook_tables = [];
        foreach ($personUUIDs as $personUUID) {
            $person_trips = [];
            foreach ($all_trips as $trip) {
                if (isset($trip["DestinationId"]) && ! isset($trip["WatersIdList"]) && ! isset(
                        $trip["WatersNameList"]))
                    $trip["WatersIdList"] = $waters_predefined[$trip["DestinationId"]];
                if (strcmp($trip["CoxId"], $personUUID) == 0) {
                    $person_trips[] = $trip;
                } else {
                    for ($i = 1; $i <= 24; $i ++) {
                        if (isset($trip["Crew" . $i . "Id"]) &&
                                 (strcmp($trip["Crew" . $i . "Id"], $personUUID) == 0))
                            $person_trips[] = $trip;
                    }
                }
            }
            
            // collect trips: Headline
            $person_trips_html = $this->table_tags["table"] . $this->table_tags["tr"] . "\n";
            foreach ($this->pers_logbook_cols_used_locale as $pers_logbook_col_used_locale)
                $person_trips_html .= $this->table_tags["th"] . $pers_logbook_col_used_locale . "</th>";
            $person_trips_html .= "\n</tr>\n";
            $total_distance = 0;
            $trip_count = 0;
            
            // collect trips: Rows
            foreach ($person_trips as $person_trip) {
                // collect data
                $row = $this->resolve_trip($person_trip);
                
                // compile table row.
                $person_trips_html .= $this->table_tags["tr"];
                foreach ($this->pers_logbook_cols_used_en as $pers_logbook_col_used_en)
                    $person_trips_html .= $this->table_tags["td"] . $row[$pers_logbook_col_used_en] . "</td>";
                $person_trips_html .= "\n</tr>\n";
                $trip_count ++;
                $total_distance += floatval($person_trip["Distance"]);
            }
            // add the sum only, if there is more than just one trip
            if ($trip_count > 1) {
                $sum = [];
                $sum["EntryId"] = i("Z4dgHJ|Total");
                $sum["Boat"] = $trip_count . " " . i("rckzWH|Trips");
                $sum["Distance"] = $total_distance . " km";
                $person_trips_html .= $this->table_tags["tr"];
                foreach ($this->pers_logbook_cols_used_en as $pers_logbook_col_used_en) {
                    $sumtext = (isset($sum[$pers_logbook_col_used_en])) ? $sum[$pers_logbook_col_used_en] : "";
                    $person_trips_html .= $this->table_tags["td"] . $sumtext . "</td>";
                }
                $person_trips_html .= "</tr>\n";
            }
            $person_trips_html .= "</table>\n";
            
            // only send personal logbooks which have at least a single trip listed.
            if ($trip_count > 0)
                $all_logbook_tables[$personUUID] = $person_trips_html;
        }
        return $all_logbook_tables;
    }
}
    
