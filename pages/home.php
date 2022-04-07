<?php
/**
 * The start of the session after successfull login.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

$versions_string = file_get_contents(
        'https://efacloud.org/src/scanversions.php?own=' .
                 htmlspecialchars(file_get_contents("../public/version")));
$versions = explode("|", $versions_string);
rsort($versions);
$latest_version = $versions[0];
if (strpos($versions[0], "Versionswechsel") !== false)
    $latest_version = $versions[1];

$current_version = (file_exists("../public/version")) ? file_get_contents("../public/version") : "";

if (strcasecmp($latest_version, $current_version) != 0)
    $version_notification = "<b>&nbsp;Hinweis:</b> Es gibt eine neuere Programmversion: " . $latest_version .
             ". <a href='../pages/upgrade.php'>&nbsp;&nbsp;<b>==&gt; AKTUALISIEREN</a></b>.";
else
    $version_notification = "&nbsp;Ihr efaCloud Server ist auf dem neuesten Stand.";

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

$verified_user = $socket->find_record_matched($toolbox->users->user_table_name, 
        [$toolbox->users->user_id_field_name => $_SESSION["User"][$toolbox->users->user_id_field_name]
        ]);
$rolle = (strcasecmp($verified_user["Rolle"], $_SESSION["User"]["Rolle"]) !== 0) ? "<b>, angemeldet als " .
         $_SESSION["User"]["Rolle"] . "</b>" : "";
?>

<!-- START OF content -->
<div class="w3-container">
	<h3><?php echo $verified_user["Vorname"] . " " . $verified_user["Nachname"]?></h3>

<?php
echo "<table>";
echo "<tr><td><b>Mitglied Nr.</b></td><td>" . $verified_user[$toolbox->users->user_id_field_name] .
         "</td></tr>\n";
echo "<tr><td><b>" . $verified_user["Vorname"] . " " . $verified_user["Nachname"] . "</b></td><td>" .
         $verified_user["EMail"] . "</td></tr>\n";
echo "<tr><td><b>Rolle</b></td><td>" . $verified_user["Rolle"] . "</td></tr>\n";
echo "</table>";
if (strcasecmp($verified_user["Rolle"], "bths") == 0)
    echo '<h3><a href="../pages/bths.php">Zum Fahrtenbuch...</a></h3>';
echo "<p>" . $version_notification . "</p>";

?>
	<h4>Boote unterwegs</h4>
	<iframe src="../public/info.php?type=onthewater&mode=7"
		title="Boote auf dem Wasser" style="width: 100%; border: none"></iframe>
<?php
// see also: "../classes/sec_concept.php"
$clients = scandir("../log/lra");
$active_clients = "";
foreach ($clients as $client) {
    if (($client != ".") && ($client != "..")) {
        $client_record = $socket->find_record("efaCloudUsers", "efaCloudUserID", $client);
        if ($client_record !== false) {
            $active_clients .= "<p>" . $client_record["Vorname"] . " " . $client_record["Nachname"] . " (#" .
                     $client_record["efaCloudUserID"] . ", " . $client_record["Rolle"] .
                     "), letzte Aktivität: " . file_get_contents("../log/lra/" . $client) . "</p>";
            $is_boathouse = (strcasecmp($client_record["Rolle"], "bths") == 0);
            if (file_exists("../log/contentsize/" . $client) && $is_boathouse)
                $active_clients .= "<table><tr><td>" . str_replace("\n", "</td></tr><tr><td>", 
                        str_replace(";", "</td><td>", 
                                trim(file_get_contents("../log/contentsize/" . $client)))) . "</td></tr></table>";
        }
    }
}
?>
	<h4>Aktive Clients</h4>
	<p><?php echo $active_clients; ?>
  </p>
</div>
<?php
end_script();