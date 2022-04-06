<?php
/**
 * Page display file. Shows all mails available to public from ARB Verteler.
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

?>
<!-- START OF content -->
<div class="w3-container">
	<h2>Änderungen von Daten</h2>
	<h3>Hier ist die Liste der Änderungen von Daten in der Datenbank</h3>
	<p>Jede Datenänderung wird mitgeschrieben, hier werden die letzten 100
		Tage dargestellt. Verwendung gestattet nur zum geregelten Zweck.</p>
<?php
$socket->cleanse_change_log(100); // keep changes for max. 100 days.
echo $socket->get_change_log();
?>
	<!-- END OF Content -->
</div>

<?php
end_script();
