<?php
/**
 * Page display file. Shows all logs of the application.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

if (isset($_GET["category"]))
    $category = $_GET["category"];
else
    $category = "app";
if (isset($_GET["type"]))
    $type = $_GET["type"];
else
    $type = "info";

$selection = "";
$available_logs = $toolbox->config->settings_tfyh["logger"]["logs"];
$categories_to_show = ["api" => "Anbindung","app" => "Serveranwendung","debug" => "Fehlersuche",
        "sys" => "Systemmeldungen"
];
$types_to_show = ["info" => "Information","warnings" => "Warnungen","errors" => "Fehler",
        "bulk_txs" => "Sammeltransaktionen","api" => "Anbindung","app" => "Serveranwendung",
        "cronjobs" => "Regelaufgaben","db_audit" => "Datenbanküberprüfung"
];
$configured_logs = [];
foreach ($available_logs as $available_log) {
    $category_and_type = explode("_", $available_log, 2);
    if (! isset($configured_logs[$category_and_type[0]]))
        $configured_logs[$category_and_type[0]] = [];
    $configured_logs[$category_and_type[0]][] = str_replace(".log", "", $category_and_type[1]);
}

foreach ($categories_to_show as $category_to_show => $category_display) {
    $heading = "<h5>" . $category_display . "<h5><p>";
    $files_found = "";
    foreach ($types_to_show as $type_to_show => $type_display) {
        $filename = "../log/" . $category_to_show . "_" . $type_to_show . ".log";
        if (file_exists($filename))
            $files_found .= "<a href='?category=" . $category_to_show . "&type=" . $type_to_show .
                     "' class='formbutton'>" . $type_display . "</a>&nbsp;&nbsp;";
    }
    if (strlen($files_found) > 0)
        $selection .= $heading . $files_found . "</p>";
}

$log = "<h4>" . $categories_to_show[$category] . ", " . $types_to_show[$type] . "</h4>";
$filename = "../log/" . $category . "_" . $type . ".log";
$log = "<h4>" . $filename . "</h4><code>";
if (! file_exists($filename))
    $log .= "Datei nicht vorhanden.";
else
    $log .= str_replace("\n", "<br>", file_get_contents($filename));
$log .= "</code>";

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Server-Meldungen</h3>
	<p>Informationen, Warnungen und Fehler mit ID des auslösenden Nutzers.
		Alles was die Anwendung protokolliert hat. Die dargestellte
		Information kan persönliche Daten enthalten und darf nur im geregelten
		Zweck verwendet werden.</p>
	<h4>Vorhandene Logs</h4>
	<div class='w3-row' style='padding: 10px;'><?php echo $selection; ?></div>
	<h4>Ausgewählter Log</h4>
	<div class='w3-row' style='padding: 10px;'><?php echo $log; ?></div>
	<!-- END OF Content -->
</div>

<?php
end_script();
