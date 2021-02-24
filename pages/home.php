<?php
/**
 * The start of the session after successfull login.
 *
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

$verified_user = $socket->find_record_matched($toolbox->users->user_table_name, [ $toolbox->users->user_id_field_name => $_SESSION["User"][$toolbox->users->user_id_field_name] ]);
$rolle = (strcasecmp($verified_user["Rolle"], $_SESSION["User"]["Rolle"]) !== 0) ? "<b>, angemeldet als " .
         $_SESSION["User"]["Rolle"] . "</b>" : "";
?>

<!-- START OF content -->
<div class="w3-container">
	<h3><?php echo $verified_user["Vorname"] . " " . $verified_user["Nachname"]?></h3>
	<h4>Startseite<?php echo ' Nutzer #'. $verified_user[$toolbox->users->user_id_field_name] . $rolle;?></h4>
</div>
<div class="w3-container">

<?php
echo "<table>";
echo "<tr><td><b>Mitglied Nr.</b></td><td>" . $verified_user[$toolbox->users->user_id_field_name] . "</td></tr>\n";
echo "<tr><td><b>" . $verified_user["Vorname"] . " " .
         $verified_user["Nachname"] . "</b></td><td>" .
         $verified_user["EMail"] . "</td></tr>\n";
echo "<tr><td><b>Rolle</b></td><td>" . $verified_user["Rolle"] . "</td></tr>\n";
echo "</table>";
if (strcasecmp($verified_user["Rolle"], "bths") == 0)
    echo '<h3><a href="../pages/bths.php">Zum Fahrtenbuch...</a></h3>';
?>

</div><?php
end_script();