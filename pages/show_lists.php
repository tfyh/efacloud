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
include_once "../classes/tfyh_list.php";
$list = new Tfyh_list("../config/lists/" . $satz, $list_id, "", $socket, $toolbox);

// ===== check list definitions.
if ($list->get_all_list_definitions() === false)
    $toolbox->display_error("!#Konfigurationsfehler.", 
            "Listenkonfiguration nicht gefunden. Konfigurationsfehler der Anwendung. Bitte rede mit dem Admn.", 
            __FILE__);
if (($list->is_valid() === false) && ($list_id != 0))
    $toolbox->display_error("!#Konfigurationsfehler.", 
            "Gesuchte Liste nicht gefunden. Konfigurationsfehler der Anwendung. Bitte rede mit dem Admn.", 
            __FILE__);

// ===== identify used list and verify user permissions
$list_name = ($list_id == 0) ? "Aufstellung der Listen" : $list->get_list_name();
$permitted = $toolbox->users->is_allowed_item($list->get_set_permission());
if (! $permitted) {
    $toolbox->display_error("Liste für Nutzer nicht zulässig.", 
            "Die Liste '" . $list_name . "' darf für die Rolle '" . $_SESSION["User"]["Rolle"] .
                     "' oder die für den aktuellen Nutzer zulässigen Subskriptionen oder" . " Workflows (" .
                     $_SESSION["User"]["Workflows"] . ": " . $list->get_set_permission() .
                     ") nicht ausgegeben werden.", __FILE__);
}

// ====== zip-Download was requested. Create zip and return it.
$osorts_list = (isset($_GET["sort"])) ? $_GET["sort"] : "";
$ofilter = (isset($_GET["filter"])) ? $_GET["filter"] : "";
$ofvalue = (isset($_GET["fvalue"])) ? $_GET["fvalue"] : "";
$data_errors = "";
if (isset($_GET["zip"])) {
    if ($_GET["zip"] == 1) {
        $toolbox->logger->log(Tfyh_logger::$TYPE_DONE, intval($_SESSION["User"][$toolbox->users->user_id_field_name]), 
                $list_name . " als csv zum Download bereitgestellt.");
        $data_errors = $list->get_zip($osorts_list, $ofilter, $ofvalue, $_SESSION["User"]);
    }
}

// ===== start page output
// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

?>
<!-- START OF content -->
<div class="w3-container">
	<h2><?php echo $list_name; ?></h2>
	<h3>Hier können Daten aus der Datenbank als Listen abgerufen werden</h3>
	<p>Verwende die zur Verfügung gestellte Information nur zum dir
		zugestandenen Zweck. Eine Weitergabe von hier exportierten Listen ist
		nicht zulässig.</p>
	<p></p>
	<?php
if ($list_id == 0) {
    echo "<table width=70%><tr><th>ID </th><th>Berechtigung </th><th>Beschreibung </th></tr>\n";
    foreach ($list->get_all_list_definitions() as $l) {
        if ($toolbox->users->is_allowed_item($l["permission"])) {
            $permissionstr = (strpos($l["permission"], "#") === 0) ? "Subskriptionen, Maske " .
                     $l["permission"] : $l["permission"];
            $permissionstr = (strpos($l["permission"], "@") === 0) ? "Workflows, Maske " . $l["permission"] : $l["permission"];
            $list->parse_options($l["options"]);
            echo "<tr><td>" . $l["id"] . "</td><td>" . $permissionstr . "</td><td><a href='?id=" . $l["id"] .
                     "&satz=" . $satz . "&pivot=" . $list->pivot . "'>" . $l["name"] . "</a></td></tr>\n";
        }
    }
    echo "</table>\n";
} else {
    echo $data_errors;
    echo $list->get_html($osorts_list, $ofilter, $ofvalue, $_SESSION["User"]);
    if (($pivot !== false) && (count($pivot) == 4)) {
        echo "<br>Übersicht<br>";
        include_once "../classes/pivot_table.php";
        $ptable = new Pivot_table($list, $pivot[0], $pivot[1], $pivot[2], $pivot[3]);
        echo $ptable->get_html("%d");
    }
}

?>
	<!-- END OF Content -->
</div>

<?php
end_script();
