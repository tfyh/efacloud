<?php
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
if (isset($_GET["logbookname"]))
    $logbookname = $_GET["logbookname"];
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
        if ($trip_startdate > 0) {  // do not use deleted records
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
    $logbooks_table_html = "<table><tr><th>Name</th><th>Fahrten</th><th>früheste Fahrt</th><th>späteste Fahrt</th>" .
             "<th>Anzahl Fahrten in</th><th>mögliche Aktion</th></tr>";
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
                $actions .= $year . "er Fahrten als einzige <a href='?action=2&logbookname=" . $logbookname .
                         "&selected_year=" . $year . "'>darin behalten</a>.<br>";
            else
                $actions .= $year . "er Fahrten <a href='?action=1&logbookname=" . $logbookname .
                         "&selected_year=" . $year . "'>aus diesem Fahrtenbuch löschen</a>.<br>";
        }
        $logbooks_table_html .= "<tr><td>" . $logbookname . "</td><td>" . $logbook["from"] . " - " .
                 $logbook["to"] . "</td><td>" . date("d.m.Y", $logbook["start"]) . "</td><td>" .
                 date("d.m.Y", $logbook["end"]) . "</td><td>" . $count . "</td><td>" . $actions . "</td></tr>";
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
                 "&selected_year=" . $selected_year .
                 "&mode=1'><table><tr><th>Fahrtenbuch</th><th>Fahrt</th><th>löschen?</th><th>Datum</th><th>Boot</th>" .
                 "<th>Ziel</th><th>letze Änderung</th><th>Mannschaft</th></tr>";
        foreach ($trip_records as $trip_record) {
            $trip_record["Boot"] = (strlen($trip_record["BoatId"]) > 10) ? $boat_names[$trip_record["BoatId"]] : $trip_record["BoatName"];
            $trip_record["Ziel"] = (strlen($trip_record["DestinationId"]) > 10) ? $destination_names[$trip_record["DestinationId"]] : $trip_record["DestinationId"];
            $trips_table .= "<tr><td>" . $trip_record["Logbookname"] . "</td><td>" . $trip_record["EntryId"] .
                     "</td><td style='text-align: center;'><input type='checkbox' name='" .
                     $trip_record["ecrid"] . "'></td><td>" . $trip_record["Date"] . "</td><td>" .
                     $trip_record["Boot"] . "</td><td>" . $trip_record["Ziel"] . "</td><td>" . date("d.m.Y", 
                            intval(
                                    substr($trip_record["LastModified"], 0, 
                                            strlen($trip_record["LastModified"]) - 3))) . "</td><td>" .
                     $trip_record["AllCrewNames"] . "</td></tr>";
        }
        $trips_table .= "</table><br><input type='submit' value='Ausgewählte Fahrten löschen' class='formbutton'/></form>";
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
                        $_SESSION["User"][$toolbox->users->user_id_field_name], "efa2logbook", $matching);
                if (strlen($trip_deletion_result) == 0)
                    $deletion_result .= "<b>" . $record_to_delete["Logbookname"] . ": " .
                             $record_to_delete["EntryId"] . "</b><br>" . json_encode($record_to_delete) .
                             "<hr>";
                else
                    $deletion_result .= "<b>" . $record_to_delete["Logbookname"] . ": " .
                             $record_to_delete["EntryId"] .
                             "</b><br>konnte nicht gelöscht werden. Fehlermeldung: " . $trip_deletion_result .
                             "<hr>";
            }
        if ($cnt_deleted == 0)
            $deletion_result = "Es wurden keine Fahrten ausgewählt und daher auch keine Fahrten gelöscht";
    }
}

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Übersicht über die Fahrtenbücher</h3>
	<p>Hier können die Fahrtenbücher strukturell eingesehen und ggf.
		korrigiert werden.</p>
</div>

<div class="w3-container">
	<?php
// show logbook tables.
if ($action == 0) {
    echo $logbooks_table_html;
    ?>
	<p>Für die Aktion wird im ersten Schritt nur angezeigt, was zu tun
		wäre. Auf Basis dieser Anzeige kann dann entschieden werden, ob die
		Aktion durchgeführt werden soll, oder nicht.</p>
<?php
} else {
    if ($mode == 0) {
        echo $trips_table;
        ?>
	<p>Mit der Bestätigung werden die ausgewählten Fahrten nun endgültig
		gelöscht.</p>
<?php
    } else {
        ?>
	<h4>Die folgenden Fahrten wurden endgültig gelöscht:</h4>
<?php
        echo $deletion_result;
    }
    ?>
	<p>
		<a href='../pages/show_logbooks.php'>Weitere Fahrten korrigieren.</a>
	</p>
<?php
}
?>
	<!-- END OF Content -->
</div>

<?php
end_script();
