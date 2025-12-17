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
 * Page display file. Shows all recent activities.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

// $action = 1: delete trips of selected year
// $action = 2: delete trips of other years
if (isset($_GET["action"]))
    $action = $_GET["action"];
else
    $action = 0;
// logbook name
if (isset($_GET["Logbookname"]))
    $logbookname = $_GET["Logbookname"];
else {
    $logbookname = "";
    $action = 0;
}
// respective year
if (isset($_GET["selected_year"]))
    $selected_year = $_GET["selected_year"];
else {
    $selected_year = 0;
    $action = 0;
}
// mode: 0 = test, 1 = execute
if (isset($_GET["mode"]))
    $mode = $_GET["mode"];
else
    $mode = 0;

include_once "../classes/tfyh_list.php";

// action = 0, just show the options
if ($action == 0) {
    $logbook_list = new Tfyh_list("../config/lists/logbook_management", 1, "Fahrtenbücher", $socket, $toolbox);
    // select all trips and pivt them into the management table.
    $trips = $logbook_list->get_rows();
    $logbooks = [];
    foreach ($trips as $trip) {
        $trip_logbookname = $trip[0];
        $trip_entryid = intval($trip[1]);
        $trip_startdate = strtotime($trip[2]);
        if ($trip_startdate > 0) { // do not use deleted records
            if (! isset($logbooks[$trip_logbookname])) {
                $logbooks[$trip_logbookname] = [];
                $logbooks[$trip_logbookname]["start"] = $trip_startdate;
                $logbooks[$trip_logbookname]["end"] = $trip_startdate;
                $logbooks[$trip_logbookname]["from"] = $trip_entryid;
                $logbooks[$trip_logbookname]["to"] = $trip_entryid;
                $logbooks[$trip_logbookname]["count"] = [];
                $logbooks[$trip_logbookname]["count"][date("Y", $trip_startdate)] = 1;
            } else {
                if ($trip_startdate < $logbooks[$trip_logbookname]["start"])
                    $logbooks[$trip_logbookname]["start"] = $trip_startdate;
                if ($trip_startdate > $logbooks[$trip_logbookname]["end"])
                    $logbooks[$trip_logbookname]["end"] = $trip_startdate;
                if ($trip_entryid < $logbooks[$trip_logbookname]["from"])
                    $logbooks[$trip_logbookname]["from"] = $trip_entryid;
                if ($trip_entryid > $logbooks[$trip_logbookname]["to"])
                    $logbooks[$trip_logbookname]["to"] = $trip_entryid;
                if (isset($logbooks[$trip_logbookname]["count"][date("Y", $trip_startdate)]))
                    $logbooks[$trip_logbookname]["count"][date("Y", $trip_startdate)] ++;
                else
                    $logbooks[$trip_logbookname]["count"][date("Y", $trip_startdate)] = 1;
            }
        }
    }
    // Generate the html code to display management table
    $logbooks_table_html = "<table><tr><th>" . i(
            "PfLbaB| ** Name ** Trips ** Ear..." . "<th>Anzahl Fahrten in</th><th>mögliche Aktion") .
             "</th></tr>";
    foreach ($logbooks as $logbookname => $logbook) {
        $count = "";
        $max_year = "";
        $max_trips = 0;
        foreach ($logbook["count"] as $year => $trips_count) {
            $count .= $year . ": " . $trips_count . "<br>";
            if ($trips_count > $max_trips) {
                $max_trips = $trips_count;
                $max_year = intval($year);
            }
        }
        $actions = "";
        foreach ($logbook["count"] as $year => $trips_count) {
            if (intval($year) == $max_year)
                $actions .= i("dfJUi2|Trips from %1 as only", $year) . " <a href='?action=2&logbookname=" .
                         $logbookname . "&selected_year=" . $year . "'>" . i("Cboyyi|keep in it") . "</a>.<br>";
            else
                $actions .= i("QufYal|Trips from %1", $year) . "<a href='?action=1&logbookname=" . $logbookname .
                         "&selected_year=" . $year . "'>" . i("3nkQKY|delete from this logbook") . "</a>.<br>";
        }
        $logbooks_table_html .= "<tr><td>" . $logbookname . "</td><td>" . $logbook["from"] . " - " .
                 $logbook["to"] . "</td><td>" . date($dfmt_d, $logbook["start"]) . "</td><td>" .
                 date($dfmt_d, $logbook["end"]) . "</td><td>" . $count . "</td><td>" . $actions . "</td></tr>";
    }
    $logbooks_table_html .= "</table>";
    
    //
} else {
    // collect all boats for mapping of Id to name
    $boats_list = new Tfyh_list("../config/lists/logbook_management", 4, "Boote", $socket, $toolbox);
    $boats = $boats_list->get_rows();
    $boat_names = [];
    foreach ($boats as $boat)
        $boat_names[$boat[0]] = $boat[1];
    // collect all destinations for mapping of Id to name
    $destinations_list = new Tfyh_list("../config/lists/logbook_management", 5, "Ziele", $socket, $toolbox);
    $destinations = $destinations_list->get_rows();
    $destination_names = [];
    foreach ($destinations as $destination)
        $destination_names[$destination[0]] = $destination[1];
    // select trips to delete are for the year selected or delete trips.
    $trip_list_args = ["{selected_year}" => $selected_year,"{logbookname}" => $logbookname
    ];
    // $action = 1: delete trips of selected year
    // $action = 2: delete trips of other years
    $trip_list = new Tfyh_list("../config/lists/logbook_management", $action + 1, "", $socket, $toolbox, 
            $trip_list_args);
    $trips = $trip_list->get_rows();
    $trip_records = [];
    foreach ($trips as $trip)
        $trip_records[] = $trip_list->get_named_row($trip);
    if ($mode == 0) {
        $trips_table = "<form method='POST' action='?action=" . $action . "&logbookname=" . $logbookname .
                 "&selected_year=" . $selected_year . "&mode=1'><table><tr><th>" . i("cGZgv4|Logbook") .
                 "</th><th>" . i("8zAeYI|Trip") . "</th><th>" . i("sSO9vY|delete?") . "</th><th>" .
                 i("2AQdk7|Date") . "</th><th>" . i("3BgOTw|boat") . "</th>" . "<th>" . i(
                        "hxPAgo|Destination") . "</th><th>" . i("p3eN5N|last change") . "</th><th>" .
                 i("ClrV6N|Team") . "</th></tr>";
        foreach ($trip_records as $trip_record) {
            $trip_record["Boot"] = (strlen($trip_record["BoatId"]) > 10) ? $boat_names[$trip_record["BoatId"]] : $trip_record["BoatName"];
            $trip_record["Ziel"] = (strlen($trip_record["DestinationId"]) > 10) ? $destination_names[$trip_record["DestinationId"]] : $trip_record["DestinationId"];
            $trips_table .= "<tr><td>" . $trip_record["Logbookname"] . "</td><td>" . $trip_record["EntryId"] .
                     "</td><td style='text-align: center;'><input type='checkbox' name='" .
                     $trip_record["ecrid"] . "'></td><td>" . $trip_record["Date"] . "</td><td>" .
                     $trip_record["Boot"] . "</td><td>" . $trip_record["Ziel"] . "</td><td>" . date($dfmt_d, 
                            intval(
                                    mb_substr($trip_record["LastModified"], 0, 
                                            mb_strlen($trip_record["LastModified"]) - 3))) . "</td><td>" .
                     $trip_record["AllCrewNames"] . "</td></tr>";
        }
        $trips_table .= "</table><br><input type='submit' value='" . i("vdj8vx|Delete selected trips") .
                 "' class='formbutton'/></form>";
    } else {
        $deletion_result = "";
        $cnt_deleted = 0;
        foreach ($_POST as $ecrid => $value)
            if (strcasecmp($value, "on") == 0) {
                $cnt_deleted ++;
                $matching = ["ecrid" => $ecrid
                ];
                $record_to_delete = $socket->find_record_matched("efa2logbook", $matching);
                $trip_deletion_result = $socket->delete_record_matched(
                        $toolbox->users->session_user["@id"], "efa2logbook", $matching);
                if (strlen($trip_deletion_result) == 0)
                    $deletion_result .= "<b>" . $record_to_delete["Logbookname"] . ": " .
                             $record_to_delete["EntryId"] . "</b><br>" .
                             json_encode(str_replace("\"", "\\\"", $record_to_delete)) . "<hr>";
                else
                    $deletion_result .= "<b>" . $record_to_delete["Logbookname"] . ": " .
                             $record_to_delete["EntryId"] . "</b><br>" .
                             i("mazy61|Could not be deleted. Er...") . " " . $trip_deletion_result . "<hr>";
            }
        if ($cnt_deleted == 0)
            $deletion_result = i("1gFUrv|No trips have been selec...");
    }
}

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

echo i("OEps5V| ** Overview of the logb...");
// show logbook tables.
if ($action == 0) {
    echo $logbooks_table_html;
    echo i("ffsj9S| ** For the action, only...");
} else {
    if ($mode == 0) {
        echo $trips_table;
        echo i("wir4uQ| ** With the confirmatio...");
    } else {
        echo i("sIOQ62| ** The following journe...");
        echo $deletion_result;
    }
    echo i("gBlz7m| ** Correct further trip...");
}
echo i("K3jC4w|<!-- END OF Content -->...");
end_script();
