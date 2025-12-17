<?php
/**
 *
 *       the tools-for-your-hobby framework
 *       ----------------------------------
 *       https://www.tfyh.org
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
 * Page display file. Shows all mails available to public from ARB Verteler.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

if (isset($_GET["satz"]))
    $satz = $_GET["satz"];
else
    $satz = "verwalten";
if (isset($_GET["id"]))
    $list_id = $_GET["id"];
else
    $list_id = 0;
if (isset($_GET["pivot"]))
    $pivot = explode(".", $_GET["pivot"]);
else
    $pivot = false;
if (isset($_GET["listparameter"]))
    $listparameter = ["{listparameter}" => $_GET["listparameter"]
    ];
elseif (isset($_GET["useconfig"])) {
    $listparameter = [
            "{" . $_GET["useconfig"] . "}" => $toolbox->config->get_cfg()[$_GET["useconfig"]]
    ];
} else
    $listparameter = [];
    
include_once "../classes/tfyh_list.php";
$list = new Tfyh_list("../config/lists/" . $satz, $list_id, "", $socket, $toolbox, $listparameter);

// ===== check list definitions.
if ($list->get_all_list_definitions() === false)
    $toolbox->display_error("!#" . i("YG4BuC|Configuration error."), 
            "List configuration not found. Configuration error of the application. Please talk to the administrator.", 
            $user_requested_file);
if (($list->is_valid() === false) && ($list_id != 0))
    $toolbox->display_error("!#" . i("qaczPb|Configuration error."), 
            "Searched list not found. Configuration error of the application. Please talk to the admin.", 
            $user_requested_file);
if (! $pivot && (count($list->pivot) == 4))
    $pivot = $list->pivot;

// ===== identify used list and verify user permissions
$list_name = ($list_id == 0) ? i("ey9S8D|Catalogue of lists") : $list->get_list_name();
$permitted = $toolbox->users->is_allowed_item($list->get_set_permission());
if (! $permitted) {
    $toolbox->display_error("List for user not permitted", 
            i("slCqRl|The list °%1° must not b...", $list_name, $toolbox->users->session_user["Rolle"], 
                    $toolbox->users->session_user["Workflows"], $list->get_set_permission()), 
            $user_requested_file);
}

// ====== zip-Download was requested. Create zip and return it.
$osorts_list = (isset($_GET["sort"])) ? $_GET["sort"] : "";
$ofilter = (isset($_GET["filter"])) ? $_GET["filter"] : "";
$ofvalue = (isset($_GET["fvalue"])) ? $_GET["fvalue"] : "";
$data_errors = "";
if (isset($_GET["zip"])) {
    if ($_GET["zip"] == 1) {
        $toolbox->logger->log(0, intval($toolbox->users->session_user["@id"]), 
                $list_name . " " . i("De1RPi|available for download a..."));
        $data_errors = $list->get_zip($osorts_list, $ofilter, $ofvalue, $toolbox->users->session_user);
    } elseif ($_GET["zip"] == 2) {
        $toolbox->logger->log(0, intval($toolbox->users->session_user["@id"]), 
                $list_name . " (pivot) " . i("De1RPi|available for download a..."));
        include_once "../classes/tfyh_pivot_table.php";
        $ptable = new Tfyh_pivot_table($list, $pivot[0], $pivot[1], $pivot[2], $pivot[3]);
        $csv = $ptable->get_csv("%d");
        $toolbox->return_string_as_zip($csv, $list->get_table_name() . ".csv");
    }
}
$helpicon = (file_exists("../helpdocs/Listen.html")) ? "<sup class='eventitem' id='showhelptext_Listen'>&#9432;</sup>" : "";

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

echo "<!-- START OF content -->\n<div class='w3-container'>\n";
echo "<h3>" . $list_name . $helpicon . "</h3><p>";

if ($list_id == 0)
    echo i("xo9xjE|The table shows all list...", $toolbox->users->session_user["Rolle"]);
echo "</p>";
if ($list_id == 0) {
    echo "<table width=70%><tr><th>" . i("LOWEsI|ID") . " </th><th>" . i("pYGgkq|Permission") .
             " </th><th>" . i("OJFiWA|Description") . " </th></tr>\n";
    foreach ($list->get_all_list_definitions() as $l) {
        if ($toolbox->users->is_allowed_item($l["permission"])) {
            $permissionstr = (strpos($l["permission"], "#") === 0) ? i(
                    "wTnmiM|Subskriptionen, Mask ") . $l["permission"] : $l["permission"];
            $permissionstr = (strpos($l["permission"], "@") === 0) ? i("IVseh0|Workflows, Mask ") .
                     $l["permission"] : $l["permission"];
            $list_params = $list->get_args($l);
            if (strlen($list_params) > 0)
                $list_params = " {" . $list_params . "}";
            echo "<tr><td>" . $l["id"] . "</td><td>" . $permissionstr . "</td><td><a href='?id=" .
                    $l["id"] . "&satz=" . $satz . "'>" . i($l["name"]) . $list_params . "</a></td></tr>\n";
        }
    }
    echo "</table>\n";
} else {
    echo $data_errors;
    echo $list->get_html($osorts_list, $ofilter, $ofvalue);
    if (($pivot !== false) && (count($pivot) == 4)) {
        echo "<h4>" . i("FalTMo|Overview") . "</h4>";
        $reference_this_page = $list->get_zip_link ($osorts_list, $ofilter, $ofvalue, true);
        echo "</p><p>" . i("OrvMhQ|get as csv-download file...") . " <a href='" .
                $reference_this_page . "'>" . $list->get_table_name() . ".pivot.zip</a>";
        include_once "../classes/tfyh_pivot_table.php";
        $ptable = new Tfyh_pivot_table($list, $pivot[0], $pivot[1], $pivot[2], $pivot[3]);
        echo $ptable->get_html("%d");
    }
}

echo "<!-- END OF Content -->\n</div>";
end_script();
