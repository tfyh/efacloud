<?php
/**
 * Generic record display file.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once "../classes/tfyh_list.php";
include_once "../classes/efa_tables.php";
$efa_tables = new Efa_tables($toolbox, $socket);
include_once "../classes/efa_dataedit.php";
$efa_dataedit = new Efa_dataedit($toolbox, $socket);

// The 'view record' page has two different entry points: either after a search, then the search result has
// all relevant information, or from a list view of things to edit. Then the information is
// carried in the GET def-parameter. Using a list reduces the displayed fields to those used in the list.
$def = (isset($_GET["def"])) ? $_GET["def"] : false;
if ($def != false) {
    $defparts = explode(".", $def);
    $listset = (isset($defparts[0])) ? $defparts[0] : "unknownSet";
    $listid = (isset($defparts[1])) ? intval($defparts[1]) : "unknownList";
    $keyfield = (isset($defparts[2])) ? $defparts[2] : "unknownKeyField";
    $keyvalue = (isset($defparts[3])) ? $defparts[3] : "unknownKeyValue";
    
    $list = new Tfyh_list("../config/lists/" . $listset, $listid, "", $socket, $toolbox);
    $rows = $list->get_rows();
    $toDisplay = false;
    foreach ($rows as $row) {
        $named_row = $list->get_named_row($row);
        if (strcasecmp(trim($named_row[$keyfield]), trim($keyvalue)) == 0)
            $toDisplay = $named_row;
    }
    if ($toDisplay == false)
        $toDisplay = [$keyfield => $keyvalue,"???" => "not found."
        ];
    // get also table row to restore UUIDs and history
    $tablename = $list->get_table_name();
    // try to extract the data key. Only then the record is surely unique
    $record_data_key = $efa_tables->get_data_key($tablename, $toDisplay);
    if ($record_data_key !== false)
        $tablerow = $socket->find_record_matched($tablename, $record_data_key);
    else // the record is not unique
        $toDisplay = [
                "Schlüssel fehlt" => "Der Datenbsatz kann leider nicht angezeigt werden, weil mindestens ein Schlüsselfeld fehlt."
        ];
    
    // set a search result & index to which this record shall be copied, to be able to edit it.
    $search_result_index = 99; // This shall be more than the max-rows in "datensatz_finden.php"
    $_SESSION["efa2table"] = $list->get_table_name();
} else {
    $search_result_index = (isset($_SESSION["getps"][$fs_id]["searchresultindex"])) ? intval(
            $_SESSION["getps"][$fs_id]["searchresultindex"]) : 0;
    if ($search_result_index == 0)
        $toolbox->display_error("Nicht zulässig.", 
                "Die Seite '" . $user_requested_file .
                         "' muss als Folgeseite von Datensatz finden aufgerufen werden.", $user_requested_file);
    $tablename = $_SESSION["efa2table"];
    $search_result = $_SESSION["search_result"][$search_result_index];
    $toDisplay = $search_result;
    // try to extract the data key. Only then the record is surely unique
    $record_data_key = $efa_tables->get_data_key($tablename, $toDisplay);
    if ($record_data_key !== false)
        $tablerow = $socket->find_record_matched($tablename, $record_data_key);
    else // the record is not unique
        $toDisplay = [
                "Schlüssel fehlt" => "Der Datenbsatz kann leider nicht angezeigt werden, weil mindestens ein Schlüsselfeld fehlt."
        ];
}

// remove lookup references
$toDisplay_cleansed = [];
foreach ($toDisplay as $key => $value) {
    if (strpos($key, ">") != false) {
        $column = explode(">", $key)[0];
        if ($tablerow != false)
            $toDisplay_cleansed[$column] = $tablerow[$column];
        else
            $toDisplay_cleansed[$column] = $value;
    } else
        $toDisplay_cleansed[$key] = $value;
    $toDisplay = $toDisplay_cleansed;
}

// special case: add record history, if existing
if (! isset($toDisplay["ecrhis"]) && isset($tablerow["ecrhis"]))
    $toDisplay["ecrhis"] = $tablerow["ecrhis"];

// in order to be able to change the record or view the history, it has to be added to the "search_result"
// session variable. It gets the index 99 to be identifyable as specific.
$_SESSION["search_result"][$search_result_index] = $toDisplay;

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Datensatzanzeige für die Tabelle <?php echo $tablename; ?>:</h3>
</div>
<div class="w3-container">
	<table>
		<tr>
			<th>Datenfeld</th>
			<th>Wert</th>
			<th>Bedeutung (bei UUID, Zeitstempel)</th>
		</tr>
	<?php
foreach ($toDisplay as $key => $value) {
    if (strcasecmp($key, "ecrhis") !== 0) {
        if ($efa_dataedit->isUUID($value)) {
            $resolved_UUID = $efa_dataedit->resolve_UUID($value);
            echo "<tr><td>" . $key . "</td><td>" . $value . "</td><td>" .
                     $efa_dataedit->resolve_UUID($value)[1] . "</td></tr>\n";
        } elseif (in_array($key, $efa_tables->timestampFields)) {
            $resolved_time = $efa_tables->get_readable_date_time($value);
            echo "<tr><td>" . $key . "</td><td>" . $value . "</td><td>" .
                    $resolved_time . "</td></tr>\n";
        } else
            echo "<tr><td>" . $key . "</td><td>" . $value . "</td><td></td></tr>\n";
    }
}
?>
	</table>
</div>
<div class="w3-container">
	<div class='w3-row'>
<?php
if ($menu->is_allowed_menu_item("../forms/datensatz_aendern.php", $_SESSION["User"])) {
    $float_history = "right";
    ?>
<div class='w3-col l2'>
			<a
				href="../forms/datensatz_aendern.php?searchresultindex=<?php echo $search_result_index; ?>"
				class=formbutton style='float: left;'>Datensatz ändern</a> <br>&nbsp;
		</div>
<?php
} else
    $float_history = "left";

if (isset($tablerow["ecrhis"]) && (strlen($tablerow["ecrhis"]) > 5)) {
    ?>
<div class='w3-col l2'>
			<a
				href='../pages/show_history.php?searchresultindex=<?php
    echo $search_result_index;
    ?>'
				class=formbutton style='float: <?php echo $float_history; ?>;'>Versionsverlauf</a>
		</div>
	</div>
</div>
<?php
}
end_script();
