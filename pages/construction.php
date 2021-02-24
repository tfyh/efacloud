<?php
/**
 * Page display file. A generic error message display page.
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

// page heading, identical for all workflow steps
?>
<!-- START OF content -->
<div class="w3-container">
	<h3><br><br><br><br>Baustelle</h3>
	<p>Die Seite ist noch nicht vorhanden, wir bauen noch.</p>
</div>

<?php
end_script();
