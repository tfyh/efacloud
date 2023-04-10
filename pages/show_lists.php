<?php
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
    $listparameter = ["{" . $_GET["useconfig"] . "}" => $toolbox->config->get_cfg()[$_GET["useconfig"]]
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

// ===== identify used list and verify user permissions
$list_name = ($list_id == 0) ? i("ey9S8D|Catalogue of lists") : $list->get_list_name();
$permitted = $toolbox->users->is_allowed_item($list->get_set_permission());
if (! $permitted) {
    $toolbox->display_error("List for user not permitted", 
            i("slCqRl|The list °%1° must not b...", $list_name, $_SESSION["User"]["Rolle"], 
                    $_SESSION["User"]["Workflows"], $list->get_set_permission()), $user_requested_file);
}

// ====== zip-Download was requested. Create zip and return it.
$osorts_list = (isset($_GET["sort"])) ? $_GET["sort"] : "";
$ofilter = (isset($_GET["filter"])) ? $_GET["filter"] : "";
$ofvalue = (isset($_GET["fvalue"])) ? $_GET["fvalue"] : "";
$data_errors = "";
if (isset($_GET["zip"])) {
    if ($_GET["zip"] == 1) {
        $toolbox->logger->log(0, intval($_SESSION["User"][$toolbox->users->user_id_field_name]), 
                $list_name . " " . i("De1RPi|available for download a..."));
        $data_errors = $list->get_zip($osorts_list, $ofilter, $ofvalue, $_SESSION["User"]);
    }
}
$helpicon = (file_exists("../helpdocs/Listen.html")) ? "<sup class='eventitem' id='showhelptext_Listen'>&#9432;</sup>" : "";

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

echo i("MCB3HR|<!-- START OF content -...");
echo $list_name . $helpicon . "</h2><p>";

if ($list_id == 0)
    echo i("xo9xjE|The table shows all list...", $_SESSION["User"]["Rolle"]);
echo "</p>";
if ($list_id == 0) {
    echo "<table width=70%><tr><th>" . i("LOWEsI|ID") . " </th><th>" . i("pYGgkq|Permission") . " </th><th>" .
             i("OJFiWA|Description") . " </th></tr>\n";
    foreach ($list->get_all_list_definitions() as $l) {
        if ($toolbox->users->is_allowed_item($l["permission"])) {
            $permissionstr = (strpos($l["permission"], "#") === 0) ? i("wTnmiM|Subskriptionen, Mask ") .
                     $l["permission"] : $l["permission"];
            $permissionstr = (strpos($l["permission"], "@") === 0) ? i("IVseh0|Workflows, Mask ") . $l["permission"] : $l["permission"];
            $list->parse_options($l["options"]);
            echo "<tr><td>" . $l["id"] . "</td><td>" . $permissionstr . "</td><td><a href='?id=" . $l["id"] .
                     "&satz=" . $satz . "'>" . i($l["name"]) . "</a></td></tr>\n";
        }
    }
    echo "</table>\n";
} else {
    echo $data_errors;
    echo $list->get_html($osorts_list, $ofilter, $ofvalue);
    if (($pivot !== false) && (count($pivot) == 4)) {
        echo "<br>" . i("FalTMo|Overview") . "<br>";
        include_once "../classes/tfyh_pivot_table.php";
        $ptable = new Tfyh_pivot_table($list, $pivot[0], $pivot[1], $pivot[2], $pivot[3]);
        echo $ptable->get_html("%d");
    }
}

echo i("PZe0Fq|<!-- END OF Content -->...");
end_script();
