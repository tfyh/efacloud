<?php
/**
 * Page display file. Shows all mails available to public from ARB Verteler.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

// identify client for statistics to show
$server_log = (isset($_GET["serverLog"])) ? intval($_GET["serverLog"]) : - 1;
$client_id = (isset($_GET["clientID"])) ? intval($_GET["clientID"]) : - 1;
if ($client_id >= 0)
    $client_record = $socket->find_record($toolbox->users->user_table_name, 
            $toolbox->users->user_id_field_name, $client_id);
else
    $client_record = false;

if ($server_log == 99)
    $toolbox->return_file_to_user($toolbox->logger->zip_logs(), "application/zip");

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

?>
<!-- START OF content -->
<div class="w3-container">
	<?php
if ($client_record !== false) {
    $filename = "../uploads/" . $client_id . "/efacloud.log";
    echo "<h3>Client #" . $client_id . " (" . $client_record["Vorname"] . " " . $client_record["Nachname"] .
             ")</h3><p>";
    echo "<h5>Ausgabe der zuletzt hochgeladenen Log-Datei 'efacloud.log'</h5>";
    echo "<p>Hochgeladen: " . date("Y-m-d H:i:s", filectime($filename)) . "</p>";
    echo "<code>" . str_replace("\n", "<br>", file_get_contents($filename)) . "</code>";
} else {
    echo "<h4>Diese Seite wurde ohne Angabe der Client-ID aufgerufen.</h4><p>";
}
echo "</p>";

?>
</div>
<!-- END OF Content -->

<?php
end_script();
