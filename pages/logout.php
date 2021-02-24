<?php
/**
 * Page display file. Logout page.
 *
 * @author mgSoft
 */
// remove all remnants of the session.
session_start(); // you need to start the session to be able to destroy it.
                 // delete the extra session file which was stored for load throttling (session
                 // counter)
$session_file = "../log/sessions/" . session_id();
if (file_exists($session_file))
    unlink($session_file);
// now destroy the session
session_destroy();
$_SESSION = array();

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
// ===== page shall be available for anonymous users.
// This will also invalidate the $_SESSION["User"]
include_once "../classes/init.php";

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

?>

<!-- START OF content -->
<div class="w3-container">
	<h3>
		<br>
		<br>
		<br>
		<br>Abgemeldet
	</h3>
	<p>
		Die Abmeldung war erfolgreich.<br>
		<br>
		<br>&nbsp;
	</p>
</div>

<?php
end_script();

