<?php
/**
 * The start of the session after successfull login.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

// ==== error handling
$user_id = (isset($_GET["id"])) ? intval($_GET["id"]) : 0; // identify user via ID
$user_nr = (isset($_GET["nr"])) ? intval($_GET["nr"]) : 0; // identify user via efaCloudUserID
if ($user_id == 0) {
    if ($user_nr > 0) {
        $user_to_show = $socket->find_record_matched($toolbox->users->user_table_name, 
                [$toolbox->users->user_id_field_name => $user_nr
                ], false);
        if (! $user_to_show) {
            $toolbox->display_error("Nicht zulässig.", 
                    "Die Seite '" . $user_requested_file .
                             "' muss mit der Angabe der id oder efaCloudUserID des zu ändernden " .
                             "Nutzers aufgerufen werden.", __FILE__);
        } else {
            $efaCloudUserID = $user_nr;
            $user_id = $user_to_show["ID"];
        }
    }
} else {
    $user_to_show = $socket->find_record_matched($toolbox->users->user_table_name, ["ID" => $user_id
    ], false);
    $efaCloudUserID = $user_to_show[$toolbox->users->user_id_field_name];
}

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');
?>

<!-- START OF content -->
<div class="w3-container">
	<h3>Profil von <?php echo $user_to_show[$toolbox->users->user_firstname_field_name] . " " . $user_to_show[$toolbox->users->user_lastname_field_name]?></h3>
</div>
<div class="w3-container">


<?php

// update user data
echo $toolbox->users->get_user_profile($efaCloudUserID, $socket, false);
echo "<p>" . $toolbox->users->get_action_links($user_id) . "</p>";

?>
</div>
<div class="w3-container">
	<h4>Informationen zum Datenschutz</h4>
	<ul>
		<li>Mit der Speicherung, Übermittlung und der Verarbeitung der
			personenbezogenen Daten für Zwecke der Firmvorbereitung, gemäß den
			Bestimmungen des Datenschutzgesetzes, hast sich der Nutzer im Rahmen
			der Anmeldung einverstanden erklärt.</li>
		<li>Wenn Du diese Seite siehst, bist Du berechtigt, diese Daten zu
			sehen und zu modifizieren, obwohl es nicht Ihre eigenen Daten sind.
			Verwende sie nur zum dem in Deiner Funktion zugestandenen Zweck.</li>
		<li>Weitergabe der Information ist ausdrücklich nicht gestattet.</li>
		<li>weitere Informationen: <a href="../public/datenschutz.php">Datenschutz</a></li>
	</ul>
</div>

<?php
end_script();
