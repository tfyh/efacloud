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
 * class file for the specific handling of efainformation which is to be passed to different clients.
 */
class Efa_info
{

    /**
     * sentence to declare no entries.
     */
    private $no_entries_text;

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
     */
    public function __construct (Tfyh_toolbox $toolbox, Tfyh_socket $socket)
    {
        $this->socket = $socket;
        $this->toolbox = $toolbox;
        $this->no_entries_text = i("5DTJs0|currently no entries.");
    }

    /**
     * get a list of all group of the groups to which identified crew members belong.
     * 
     * @param array $all_crew_ids
     *            the UUIDs of all crew members
     * @return number[] an associative array of the group names as key and the occurrences as value
     */
    private function get_crew_members_groups (array $all_crew_ids)
    {
        $all_crew_groups = [];
        foreach ($all_crew_ids as $crew_member_id) {
            $member_groups = $this->socket->find_records_sorted_matched("efa2groups", 
                    ["MemberIdList" => "%" . $crew_member_id . "%"
                    ], 10, "LIKE", "Name", true);
            if ($member_groups !== false) {
                foreach ($member_groups as $member_group) {
                    if (! isset($all_crew_groups[$member_group["Name"]]))
                        $all_crew_groups[$member_group["Name"]] = 0;
                    $all_crew_groups[$member_group["Name"]] ++;
                }
            }
        }
        if (count($all_crew_groups) == 0)
            return "-";
        ksort($all_crew_groups);
        $crew_groups = "";
        foreach ($all_crew_groups as $crew_group_name => $crew_group_cnt)
            $crew_groups .= $crew_group_name . "(" . $crew_group_cnt . ")" . ", ";
        if (strlen($crew_groups) > 0)
            $crew_groups = mb_substr($crew_groups, 0, mb_strlen($crew_groups) - 2);
        return $crew_groups;
    }

    /**
     * Remove unused columns of an array of rows. The first row contains the headline.
     * 
     * @param array $table
     *            the table wich shall be transformed
     * @return array the cleansed table
     */
    private function remove_unused_columns (array $table)
    {
        // add a "no entries text" to the table, if empty.
        if (count($table) <= 1)
            $table[] = [$this->no_entries_text
            ];
        
        // check columns used
        $is_used = [];
        foreach ($table[0] as $colname)
            $is_used[] = false;
        $r = 0;
        foreach ($table as $row) {
            for ($c = 0; $c < count($is_used); $c ++)
                if (($r > 0) && isset($row[$c]) && (strlen(strval($row[$c])) > 0))
                    $is_used[$c] = true;
            $r ++;
        }
        // remove unused columns
        for ($c = 0; $c < count($is_used); $c ++)
            if (! $is_used[$c])
                for ($r = 0; $r < count($table); $r ++)
                    unset($table[$r][$c]);
        return $table;
    }

    /**
     * Transform an array of rows into a html table. The first row contains the headline.
     * 
     * @param array $table
     *            the table wich shall be transformed
     * @param int $mode
     *            bit mask with 0x2 = headline on/off, 0x4 = use multiple columns. Use -1 for default
     * @return html-String representing the table
     */
    private function table_to_html (array $table, int $mode)
    {
        $headline_on = (($mode & 0x2) > 0);
        $single_column = (($mode & 0x4) <= 0);
        
        // create the layout
        $html = "<table>";
        if ($headline_on) {
            $html .= "<thead><tr>";
            if ($single_column) {
                $html .= "<th>";
                for ($c = 0; $c < count($table[0]); $c ++)
                    $html .= $table[0][$c] . ", ";
                $html = mb_substr($html, 0, mb_strlen($html) - 2);
                $html .= "</th>";
            } else
                for ($c = 0; $c < count($table[0]); $c ++)
                    $html .= "<th>" . $table[0][$c] . "</th>";
            $html .= "</tr></thead>";
        }
        $html .= "<tbody>";
        for ($r = 1; $r < count($table); $r ++) {
            $html .= "<tr>";
            if ($single_column) {
                $html .= "<td>";
                for ($c = 0; $c < count($table[$r]); $c ++)
                    $html .= $table[$r][$c] . "<br>";
                $html .= "</td>";
            } else
                for ($c = 0; $c < count($table[$r]); $c ++)
                    $html .= "<td>" . $table[$r][$c] . "</td>";
            $html .= "</tr>";
        }
        $html .= "</tbody></table>";
        return $html;
    }

    /**
     * Transform an array of rows into a csv table. The first row contains the headline.
     * 
     * @param array $table
     *            the table wich shall be transformed
     * @param int $mode
     *            bit mask with 0x2 = headline on/off, 0x4 = use multiple columns. Use -1 for default
     * @return html-String representing the table
     */
    private function table_to_csv (array $table, int $mode)
    {
        $headline_on = (($mode & 0x2) > 0);
        $single_column = (($mode & 0x4) <= 0);
        
        // create the layout
        $csv = "";
        if ($headline_on) {
            if ($single_column) {
                for ($c = 0; $c < count($table[0]); $c ++)
                    $csv .= $table[0][$c] . ", ";
                $csv = $this->toolbox->encode_entry_csv($csv);
                $csv = mb_substr($csv, 0, mb_strlen($csv) - 2) . "\n";
            } else {
                for ($c = 0; $c < count($table[0]); $c ++)
                    $csv .= $this->toolbox->encode_entry_csv($table[0][$c]) . ";";
                $csv = mb_substr($csv, 0, mb_strlen($csv) - 1) . "\n";
            }
        }
        for ($r = 1; $r < count($table); $r ++) {
            if ($single_column) {
                $rowtxt = "";
                for ($c = 0; $c < count($table[0]); $c ++)
                    $rowtxt .= $table[$r][$c] . ", ";
                $rowtxt = $this->toolbox->encode_entry_csv($rowtxt);
                $csv .= mb_substr($rowtxt, 0, mb_strlen($rowtxt) - 2) . "\n";
            } else {
                for ($c = 0; $c < count($table[0]); $c ++)
                    $csv .= $this->toolbox->encode_entry_csv($table[$r][$c]) . ";";
                $csv = mb_substr($csv, 0, mb_strlen($csv) - 1) . "\n";
            }
        }
        $csv = mb_substr($csv, 0, mb_strlen($csv) - 1);
        return $csv;
    }

    /**
     * Get the header for the open trips table.
     * 
     * @param bool $get_empty_row
     *            set true to get an empty row instead of the header
     * @return array the non associative table header as array for the open trips table.
     */
    private function get_trip_header (bool $get_empty_row)
    {
        $trip_header = [];
        $cfg = $this->toolbox->config->get_cfg();
        $locale_names = Efa_tables::locale_names($cfg["language_code"]);
        if ($cfg["public_tripdata_EntryId"])
            $trip_header[] = ($get_empty_row) ? "-" : $locale_names["EntryId"];
        if ($cfg["public_tripdata_BoatName"])
            $trip_header[] = ($get_empty_row) ? "-" : $locale_names["BoatName"];
        if ($cfg["public_tripdata_BoatAffix"])
            $trip_header[] = ($get_empty_row) ? "-" : $locale_names["NameAffix"];
        if ($cfg["public_tripdata_BoatType"])
            $trip_header[] = ($get_empty_row) ? "-" : i("s6i5Ps|boat type");
        if ($cfg["public_tripdata_CrewGroups"])
            $trip_header[] = ($get_empty_row) ? "-" : i("iTj3F5|groups in crew");
        if ($cfg["public_tripdata_StartTime"])
            $trip_header[] = ($get_empty_row) ? "-" : $locale_names["StartTime"];
        if ($cfg["public_tripdata_Destination"])
            $trip_header[] = ($get_empty_row) ? "-" : $locale_names["DestinationName"];
        if ($cfg["public_tripdata_Distance"])
            $trip_header[] = ($get_empty_row) ? "-" : $locale_names["Distance"];
        return $trip_header;
    }

    /**
     * Check, whether trip data settings are provided. introduced with 2.3.2_17, August 2023. Remove some day,
     * because all configs will have the default.
     * 
     * @return boolean true if a single trip_data setting is "on", else false.
     */
    private function is_empty_trip_data_settings ()
    {
        $cfg = $this->toolbox->config->get_cfg();
        $public_tripdata_settings = 0;
        foreach (array_keys($cfg) as $key)
            if ((strpos($key, "public_tripdata_") !== false) && (strcasecmp($cfg[$key], "on") == 0))
                $public_tripdata_settings ++;
        return ($public_tripdata_settings == 0);
    }

    /**
     * Set the trip data settings to default.
     */
    private function default_trip_data_settings ()
    {
        $cfg = $this->toolbox->config->get_cfg();
        $cfg["public_tripdata_EntryId"] = "on";
        $cfg["public_tripdata_BoatName"] = "on";
        $cfg["public_tripdata_StartTime"] = "on";
        $cfg["public_tripdata_Destination"] = "on";
        $this->toolbox->config->store_app_config($cfg);
        $this->toolbox->config->load_app_configuration();
    }

    /**
     * get the table information for a single trip for a boat on the water in the open trips table.
     * 
     * @param String $boat_id
     *            the Id of the boat, as is in the trip record.
     * @param array $trip
     *            the trip record
     * @return array the non associative table row as array for the open trips table.
     */
    private function get_trip_row (String $boat_id, array $trip)
    {
        global $dfmt_d, $dfmt_dt;
        $trip_row = [];
        $cfg = $this->toolbox->config->get_cfg();
        if ($cfg["public_tripdata_EntryId"])
            $trip_row[] = strval($trip["EntryId"]);
        $boat = $this->socket->find_record("efa2boats", "Id", $boat_id);
        if ($cfg["public_tripdata_BoatName"]) {
            $boatname = (($boat != false) && isset($boat["Name"])) ? $boat["Name"] : "Fremdboot";
            $trip_row[] = $boatname;
        }
        if ($cfg["public_tripdata_BoatAffix"]) {
            $boataffix = (($boat != false) && isset($boat["NameAffix"])) ? "(" . $boat["NameAffix"] . ")" : "";
            $trip_row[] = $boataffix;
        }
        if ($cfg["public_tripdata_BoatType"]) {
            $boattypes = (($boat != false) && isset($boat["TypeType"])) ? explode(";", $boat["TypeType"]) : [
                    "OTHER"
            ];
            $boatvarianttype = (isset($trip["BoatVariant"]) &&
                     (intval($trip["BoatVariant"]) <= count($boattypes))) ? $boattypes[intval(
                            $trip["BoatVariant"]) - 1] : "OTHER";
            include_once "../classes/efa_config.php";
            $efa_config = new Efa_config($this->toolbox);
            $cfgboattypes = $efa_config->types["BOAT"];
            $boattype = "???";
            foreach ($cfgboattypes as $cfgboattype)
                if (strcasecmp($cfgboattype["Type"], $boatvarianttype) == 0)
                    $boattype = $cfgboattype["Value"];
            $trip_row[] = $boattype;
        }
        if ($cfg["public_tripdata_CrewGroups"]) {
            $crew_groups = (isset($trip["AllCrewIds"]) && (strlen($trip["AllCrewIds"]) > 30)) ? $this->get_crew_members_groups(
                    explode(",", $trip["AllCrewIds"])) : "-";
            $trip_row[] = $crew_groups;
        }
        if ($cfg["public_tripdata_StartTime"]) {
            $start_time = (strcmp(date("Y-m-d"), $trip["Date"]) != 0) ? date($dfmt_d, 
                    strtotime($trip["Date"])) . " - " . $trip["StartTime"] : $trip["StartTime"];
            $trip_row[] = $start_time;
        }
        if ($cfg["public_tripdata_Destination"]) {
            $destination = (isset($trip["DestinationId"])) ? $trip["DestinationId"] : $trip["DestinationName"];
            if (isset($trip["DestinationId"])) {
                $destination_record = $this->socket->find_record("efa2destinations", "Id", 
                        $trip["DestinationId"]);
                $destination = (($destination_record != false) && isset($destination_record["Name"])) ? $destination_record["Name"] : "kein Ziel angegeben";
            } else
                $destination = $trip["DestinationName"];
            if (is_null($destination) || (strlen($destination) == 0))
                $destination = "-";
            $trip_row[] = $destination;
        }
        if ($cfg["public_tripdata_Distance"]) {
            $trip_row[] = (isset($trip["Distance"]) && (strlen($trip["Distance"]) > 0)) ? $trip["Distance"] : "-";
        }
        if ($cfg["public_tripdata_FreeUse1"]) {
            $trip_row[] = (isset($trip["FreeUse1"]) && (strlen($trip["FreeUse1"]) > 0)) ? $trip["FreeUse1"] : "-";
        }
        return $trip_row;
    }

    /**
     * Return an html or csv representation of all boats on the water
     * 
     * @param int $mode
     *            bit mask with 0x1 = html (1)/csv (0), 0x2 = headline on/off, 0x4 = count of columns 4/1. Use
     *            -1 for default
     * @return string the html or csv representation of the table with all boats on the water
     */
    public function get_on_the_water (int $mode)
    {
        global $dfmt_d, $dfmt_dt;
        
        if ($this->is_empty_trip_data_settings())
            $this->default_trip_data_settings();
        
        include_once "../classes/efa_tables.php";
        $boats_on_the_water = $this->socket->find_records("efa2boatstatus", "CurrentStatus", "ONTHEWATER", 
                100);
        $table = [];
        $table[] = $this->get_trip_header(false);
        if ($boats_on_the_water !== false) {
            // gather all data into an array
            foreach ($boats_on_the_water as $boat_on_the_water) {
                $matching = ["EntryId" => $boat_on_the_water["EntryNo"],"Open" => "true"
                ];
                $trips = $this->socket->find_records_sorted_matched("efa2logbook", $matching, 10, "=", "Date", 
                        false);
                // If by an error $trips contain entries from different logbooks, filter for the current one.
                if ($trips !== false)
                    foreach ($trips as $trip) {
                        // filter those of the current logbook by year
                        if (strcmp(date("Y"), substr($trip["Date"], 0, 4)) == 0) {
                            $table[] = $this->get_trip_row($boat_on_the_water["BoatId"], $trip);
                        }
                    }
            }
        }
        if (count($table) == 1)
            $table[] = $this->get_trip_header(true);
        if ($mode == - 1)
            $mode = 3;
        if (($mode & 0x1) > 0)
            return $this->table_to_html($table, $mode);
        else
            return $this->table_to_csv($table, $mode);
    }

    /**
     * Return an html or csv representation of all not available
     * 
     * @param int $mode
     *            bit mask with 0x1 = html (1)/csv (0), 0x2 = headline on/off, 0x4 = count of columns 2/1. Use
     *            -1 for default
     * @return string the html or csv representation of the table with all boats not available
     */
    public function get_not_available (int $mode)
    {
        $boats_currently_not_available = $this->socket->find_records("efa2boatstatus", "CurrentStatus", 
                "NOTAVAILABLE", 100);
        $boats_never_available = $this->socket->find_records("efa2boatstatus", "BaseStatus", "NOTAVAILABLE", 
                100);
        // array merge with false instead of empty array for no match.
        $boats_not_available = (($boats_never_available === false) && ($boats_never_available === false)) ? [] : (($boats_never_available ===
                 false) ? $boats_currently_not_available : array_merge($boats_currently_not_available, 
                        $boats_never_available));
        $boats_shown = [];
        $table = [];
        $table[] = explode(",", i("3lHvNg|Boat,CurrentStatus,BaseS..."));
        foreach ($boats_not_available as $boat_not_available) {
            $boat = $this->socket->find_record("efa2boats", "Id", $boat_not_available["BoatId"]);
            $boat_valid = (strlen($boat["InvalidFrom"]) > 15);
            $boatname = (($boat != false) && isset($boat["Name"])) ? $boat["Name"] : "Fremdboot";
            if ($boat_valid && ! isset($boats_shown[$boatname])) {
                $table[] = [$boatname,$boat_not_available["CurrentStatus"],
                        $boat_not_available["BaseStatus"]
                ];
                $boats_shown[$boatname] = true;
            }
        }
        $table = $this->remove_unused_columns($table);
        
        if ($mode == - 1)
            $mode = 3;
        $table = $this->remove_unused_columns($table);
        if (($mode & 0x1) > 0)
            return $this->table_to_html($table, $mode);
        else
            return $this->table_to_csv($table, $mode);
    }

    /**
     * Return an html or csv representation of all boats reserved in the next 14 days
     * 
     * @param int $mode
     *            bit mask with 0x1 = html (1)/csv (0), 0x2 = headline on/off, 0x4 = count of columns 2/1. Use
     *            -1 for default
     * @return string the html or csv representation of the table with all boats not available
     */
    public function get_reserved (int $mode)
    {
        $boat_reservations = $this->socket->find_records("efa2boatreservations", "", "", 1000);
        $table = [];
        $table[] = explode(",", i("zkm1hq|Boat,DateFrom,DateTo"));
        
        $now = time();
        $relevant_period_start = $now - 2 * 24 * 3600;
        $relevant_period_end = $now + 30 * 24 * 3600;
        foreach ($boat_reservations as $boat_reservation) {
            $from = strtotime($boat_reservation["DateFrom"]);
            $until = strtotime($boat_reservation["DateTo"]);
            $to_be_listed = (($relevant_period_start < $from) && ($from < $relevant_period_end)) ||
                     (($relevant_period_start < $until) && ($until < $relevant_period_end));
            if ($to_be_listed) {
                $boat = $this->socket->find_record("efa2boats", "Id", $boat_reservation["BoatId"]);
                $boatname = (($boat != false) && isset($boat["Name"])) ? $boat["Name"] : "Fremdboot";
                $table[] = [$boatname,$boat_reservation["DateFrom"] . " " . $boat_reservation["TimeFrom"],
                        $boat_reservation["DateTo"] . " " . $boat_reservation["TimeTo"]
                ];
            }
        }
        $table = $this->remove_unused_columns($table);
        
        if ($mode == - 1)
            $mode = 3;
        if (($mode & 0x1) > 0)
            return $this->table_to_html($table, $mode);
        else
            return $this->table_to_csv($table, $mode);
    }

    /**
     * Return an html or csv representation of all boats with damages in "NOTUSEABLE" status
     * 
     * @param int $mode
     *            bit mask with 0x1 = html (1)/csv (0), 0x2 = headline on/off, 0x4 = count of columns 2/1. Use
     *            -1 for default
     * @return string the html or csv representation of the table with all boats not usable
     */
    public function get_not_usable (int $mode)
    {
        
        // get all damages which lead to a not usable boat
        include_once "../classes/tfyh_list.php";
        $damage_list = new Tfyh_list("../config/lists/efaWeb", 2, "efaWeb_boatdamages", $this->socket, 
                $this->toolbox);
        $damage_rows = $damage_list->get_rows();
        $boats_not_usable = [];
        foreach ($damage_rows as $damage_row) {
            $damage_record = $damage_list->get_named_row($damage_row);
            // filter those which are NOTUSEABLE
            if (strcasecmp($damage_record["Severity"], "NOTUSEABLE") == 0) {
                $boat = $this->socket->find_record("efa2boats", "Id", $damage_record["BoatId"]);
                if (! isset($boats_not_usable[$boat["Name"]]))
                    $boats_not_usable[$boat["Name"]] = [];
                $boats_not_usable[$boat["Name"]][] = $damage_record;
            }
        }
        // list all boats with all damages.
        $table = [];
        $table[] = explode(",", i("wOD59L|Boat,Description"));
        foreach ($boats_not_usable as $boatname => $damages) {
            $damages_desc = "";
            foreach ($damages as $damage) {
                if (($mode & 0x1) > 0)
                    $damages_desc .= $damage["Description"] . "<br>";
                else
                    $damages_desc .= $damage["Description"] . " // ";
            }
            $table[] = [$boatname,$damages_desc
            ];
        }
        $table = $this->remove_unused_columns($table);
        
        if (($mode & 0x1) > 0)
            return $this->table_to_html($table, $mode);
        else
            return $this->table_to_csv($table, $mode);
    }

    /**
     * Check whether the info is allowed for the user. Returns true, if so. Else false
     * 
     * @param
     *            String type the type of information requested
     * @param array $client_verified
     *            the verified client which requests the execution
     * @return bool the check result
     */
    public function is_allowed_info (array $client_verified, String $type)
    {
        $cfg = $this->toolbox->config->get_cfg();
        // be aware that hte confg parameter name has a prefix "pblic_" for better configuratuion readability
        $publicly_allowed = isset($cfg[$type]) && (strlen("public_" . $cfg[$type]) > 1);
        if ($publicly_allowed)
            return true;
        $client_not_anonymous = isset($client_verified["Rolle"]) &&
                 (strcasecmp($client_verified["Rolle"], $this->toolbox->users->anonymous_role) != 0);
        return $client_not_anonymous;
    }

    /**
     * Get the information of the requested type as state in the transaction record.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param array $client_record
     *            client record which carries the request for information.
     * @return the requested info or an appropriate error code.
     */
    public function api_info (array $client_verified, array $client_record)
    {
        $tx_response = "502;" . i("QzwDXO|Info type not recognized...") . " " . json_encode($client_record);
        if (isset($client_record["type"]) && $this->is_allowed_info($client_verified, $client_record["type"])) {
            if (strcasecmp("onthewater", $client_record["type"]) == 0) {
                $tx_response = "300;" . $this->get_on_the_water(intval($client_record["mode"]));
            } elseif (strcasecmp("notavailable", $client_record["type"]) == 0) {
                $tx_response = "300;" . $this->get_not_available(intval($client_record["mode"]));
            } elseif (strcasecmp("notusable", $client_record["type"]) == 0) {
                $tx_response = "300;" . $this->get_not_usable(intval($client_record["mode"]));
            } elseif (strcasecmp("reserved", $client_record["type"]) == 0) {
                $tx_response = "300;" . $this->get_reserved(intval($client_record["mode"]));
            }
        } elseif (isset($client_record["type"]))
            $tx_response = "300;" . i("hNikUk|Access to this informati...");
        
        return $tx_response;
    }
}
    
