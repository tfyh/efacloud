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
                             "Nutzers aufgerufen werden.", $user_requested_file);
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
echo "<p><b>Für diesen Nutzer stehen die folgenden Aktionen zur Verfügung (bitte auf den Link klicken):</b><br>";
$_SESSION["search_result"] = [];
$_SESSION["search_result"][1] = $user_to_show;
$_SESSION["search_result"]["tablename"] = "efaCöloudUsers";

echo $toolbox->users->get_action_links($user_id) . "<a href='../pages/show_history.php?searchresultindex=1'> - Versionsverlauf</a></p>";
?>
</div>
<div class="w3-container">
	<h4>Informationen zum Datenschutz</h4>
	<ul>
		<li>Wenn Du diese Seite siehst, bist Du berechtigt, diese Daten zu
			sehen und ggf. zu modifizieren, obwohl es nicht Deine eigenen Daten
			sind. Verwendung gestattet nur zum geregelten Zweck.</li>
		<li>Weitergabe der Information ist ausdrücklich nicht gestattet.</li>
	</ul>
</div>

<?php
end_script();
