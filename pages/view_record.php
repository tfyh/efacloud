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
 * Generic record display file.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once "../classes/tfyh_list.php";
include_once "../classes/efa_tables.php";
include_once "../classes/efa_record.php";

// The 'view record' page has three different entry points: either after a search, then the search result has
// all relevant information, or from a list view of things to edit. Then the information is
// carried in the GET def-parameter. Using a list reduces the displayed fields to those used in the list.
// Finally the tablename and ecrid may be passed as GET-Parameter.
$def = (isset($_GET["def"])) ? $_GET["def"] : false;
$ecrid = (isset($_GET["ecrid"])) ? $_GET["ecrid"] : false;
$tablename = (isset($_GET["table"])) ? $_GET["table"] : false;
$missing_key_error = [i("mDzsS8|Key missing") => i("V8fLMV|Unfortunately, the recor...")
];
$valid_at = 0;
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
    $record_data_key = Efa_tables::get_record_key($tablename, $toDisplay);
    
    if ($record_data_key !== false)
        $tablerow = $socket->find_record_matched($tablename, $record_data_key);
    else // the record is not unique
        $toDisplay = $missing_key_error;
    
    // set a search result & index to which this record shall be copied, to be able to edit it.
    $search_result_index = 99; // This shall be more than the max-rows in "datensatz_finden.php"
    $_SESSION["efa2table"] = $list->get_table_name();
} elseif ($ecrid && $tablename) {
    // period matching only for ecrid-based record view. Because lists may do the lookup themselves.
    $tablerow = $socket->find_record_matched($tablename, ["ecrid" => $ecrid
    ]);
    $valid_at = (! array_key_exists($tablename, Efa_tables::$period_indication_fields)) ? 0 : strtotime(
            $tablerow[Efa_tables::$period_indication_fields[$tablename]]);
    if ($tablerow == false)
        $toDisplay = [$keyfield => $keyvalue,"???" => "not found."
        ];
    else
        $toDisplay = $tablerow;
    $search_result_index = 99; // This shall be more than the max-rows in "datensatz_finden.php"
    $_SESSION["efa2table"] = $tablename;
} else {
    $search_result_index = (isset($_SESSION["getps"][$fs_id]["searchresultindex"])) ? intval(
            $_SESSION["getps"][$fs_id]["searchresultindex"]) : 0;
    if ($search_result_index == 0)
        $toolbox->display_error(i("KjzmnU|Not allowed."), 
                i("b1CfJu|Page °%1° must be called...", $user_requested_file), $user_requested_file);
    $tablename = $_SESSION["efa2table"];
    $search_result = $_SESSION["search_result"][$search_result_index];
    $toDisplay = $search_result;
    // try to extract the data key. Only then the record is surely unique
    $record_data_key = Efa_tables::get_record_key($tablename, $toDisplay);
    if ($record_data_key !== false)
        $tablerow = $socket->find_record_matched($tablename, $record_data_key);
    else // the record is not unique
        $toDisplay = $missing_key_error;
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
if ((! isset($toDisplay["ecrhis"]) || (strlen($toDisplay["ecrhis"]) < 3)) && isset($tablerow["ecrhis"]))
    $toDisplay["ecrhis"] = $tablerow["ecrhis"];

// in order to be able to change the record or view the history, it has to be added to the "search_result"
// session variable. It gets the index 99 to be identifyable as specific.
$_SESSION["search_result"][$search_result_index] = $toDisplay;

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');
$tablename_de = (isset(Efa_tables::locale_names()[$tablename])) ? Efa_tables::locale_names()[$tablename] : $tablename;

// page heading, identical for all workflow steps
echo i("tSyQm8| ** Data record display...", $tablename_de);
$null_values = "";

if (! $ecrid && isset($toDisplay["ecrid"]) && (strlen($toDisplay["ecrid"]) > 0))
    $ecrid = $toDisplay["ecrid"];
include_once "../classes/efa_uuids.php";
$efa_uuids = new Efa_uuids($toolbox, $socket);
foreach ($toDisplay as $key => $value) {
    $key_de = (isset(Efa_tables::locale_names()[$key])) ? Efa_tables::locale_names()[$key] : $key;
    if (strlen($value) == 0)
        $null_values .= $key_de . ", ";
    elseif (Efa_uuids::isUUID($value)) {
        $resolved_UUID = $efa_uuids->resolve_UUID($value);
        echo "<tr><td>" . $key_de . "</td><td>" . $value . "</td><td>" .
                 $efa_uuids->resolve_UUID($value, $valid_at)[1] . "</td></tr>\n";
    } elseif (in_array($key, Efa_tables::$timestamp_field_names)) {
        $resolved_time = Efa_tables::get_readable_date_time($value);
        echo "<tr><td>" . $key_de . "</td><td>" . $value . "</td><td>" . $resolved_time . "</td></tr>\n";
    } elseif (in_array($key, Efa_tables::$date_fields[$tablename]))
        echo "<tr><td>" . $key_de . "</td><td>" . date($dfmt_d, strtotime($value)) . "</td><td></td></tr>\n";
    elseif ($ecrid && $tablename && (strcasecmp($key, "ecrhis") == 0))
        echo "<tr><td>" . i("UhbQ08|record history") . "</td><td>" .
                 "<a href='../pages/show_history.php?table=$tablename&ecrid=$ecrid'>" .
                 i("UcNTLA|show versions") . "</a></td><td></td></tr>\n";
    else
        echo "<tr><td>" . $key_de . "</td><td>" . $value . "</td><td></td></tr>\n";
}
if (strlen($null_values) > 0)
    echo "<tr><td>" . i("eiCoTk|empty data fields") . "</td><td>" .
             mb_substr($null_values, 0, mb_strlen($null_values) - 2) . "</td><td></td></tr>\n";

echo "</table>\n</div>\n<div class='w3-container'>\n<div class='w3-row'>\n";
if ($menu->is_allowed_menu_item("../forms/datensatz_aendern.php", $toolbox->users->session_user)) {
    $float_history = "right";
    echo i("gXEDSy|<div class=°w3-col l2°>...");
    $is_admin = (strcmp($toolbox->users->session_user["Rolle"], $toolbox->users->useradmin_role) == 0);
    $is_deleted = (isset($tablerow["LastModification"]) &&
             (strcasecmp($tablerow["LastModification"], "delete") == 0));
    if ($is_admin) {
        if ($is_deleted)
            echo "<br><span style='color:#800'>" . i("AicQjQ|This record has been del...") . "</span>";
        else {
            echo "<a href='../forms/datensatz_aendern.php?table=$tablename&ecrid=" . $tablerow["ecrid"] .
                     "' class=formbutton style='float: left;'>" . i("xBbzbd|Change record") . "</a> <br>";
            if (in_array($tablename, Efa_record::$allow_web_delete))
                echo "<br><br><a href='../pages/datensatz_loeschen.php?table=$tablename&ecrid=" .
                         $tablerow["ecrid"] . "' style='float: left;'>" . i("8Y01p6|Delete record") . "</a>";
        }
    }
    echo "</div>";
} else
    $float_history = "left";

if (isset($tablerow["ecrhis"]) && (strlen($tablerow["ecrhis"]) > 5)) {
    $link_parameters = "table=" . $tablename . "&ecrid=" . $tablerow["ecrid"];
}
end_script();
