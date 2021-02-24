<?php
/**
 * Page display file. Shows all mails available to public from ARB Verteler.
 *
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
$client_id = (isset($_GET["clientID"])) ? intval($_GET["clientID"]) : 0; // identify client for
                                                                         // statistics to
                                                                         // show
if (! $client_id) {
    $toolbox->display_error("Nicht zulässig.", 
            "Die Seite '" . $user_requested_file . "' muss mit der Angabe der efaCloudUserID des zu berichtenden " .
                     "Clients aufgerufen werden.");
}
$client_record = $socket->find_record($toolbox->users->user_table_name, $toolbox->users->user_id_field_name, $client_id);

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

function echo_content_reverse(String $filepath) {
    $content = file_get_contents($filepath);
    if ($content === false) return;
    $lines = explode("\n", $content);
    for ($i = count($lines); $i >= 0; $i--)
        echo $lines[$i] . "<br>";
}

?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Logs für den Client <?php echo $client_record["Vorname"] . " " . $client_record["Nachname"]; ?> anzeigen.</h3>
	<p>Bitte klicke auf eine der Listen, um sie komplett anzuzeigen.</p>
	<span tabindex='1' class="formbutton apiActivity">client activity</span>&nbsp;
	<span tabindex='5' class="formbutton serverWarnings">server warnings</span>&nbsp;<span
		tabindex='6' class="formbutton serverErrors">server errors </span>
	<p>&nbsp;</p>
	<div class="hiddendiv" id="apiActivity">
		<h4>Client activity</h4>
		<p><?php
		echo_content_reverse("../uploads/" . $client_id . "/efacloud.log");
?></p>
	</div>
	<div class="hiddendiv" id="serverWarnings">
		<h4>server warnings</h4>
		<p><?php
if (file_exists("../log/api_warnings.log"))
    echo_content_reverse("../log/api_warnings.log");
else
    echo "Keine Daten verfügbar.";
?></p>
	</div>
	<div class="hiddendiv" id="serverErrors">
		<h4>server errors</h4>
		<p><?php
if (file_exists("../log/api_error.log"))
    echo_content_reverse("../log/api_error.log");
else
    echo "Keine Daten verfügbar.";
?></p>
	</div>
	<!-- END OF Content -->
</div>

<?php
end_script();
