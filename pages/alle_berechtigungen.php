<?php
/**
 * An overview on all accesses currently granted.
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
	<h3>Alle Berechtigungen</h3>
	<h4>Eine Übersicht über die momentan vergebenen Berechtigungen</h4>
	<p></p>

<?php

echo $toolbox->users->get_all_accesses($socket);

?>
</div>
<div class="w3-container">
	<h3>Informationen zum Datenschutz</h3>
	<ul class="listWithMarker">
		<li>Diese Information wird ausschließlich zum Kontrollzweck für
			Funktionsträger in der Berechtigungsverwaltung bereitgestellt. Sie
			darf nicht weitergeleitet werden.</li>
	</ul>
</div>
<?php
end_script();
