<?php
/**
 * Page display file. Shows all mails available to public from ARB Verteler.
 *
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
if (isset($_GET["type"]))
    $type = $_GET["type"];
else
    $type = Tfyh_logger::$TYPE_FAIL;

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Aktivitäten der Nutzer</h3>
	<p>Hier können die Nutzerakrivitäten ohne die API-Akivitäten eingesehen werden.</p>
</div>

<div class="w3-container">
	<p>Auflistung der nutzergetriebenen Aktivitäten. Zeitstempel,
		ID des betroffenen Nutzers, Beschreibung.</p>
	<p>Die dargestellte Information darf nur zum in dner Funktion zugestandenen
		Zweck verwendet werden.</p>
	<?php
// show activites summary last two weeks.
echo $toolbox->logger->get_activities_html(14);
?>
		<p>Aktivitätenliste</p>
	<ul>
		<li>
	<?php
// keep log entries for max. 100 days.
echo str_replace("\n", "</li><li>\n", $toolbox->logger->list_and_cleanse_entries($type, 100, true));
	?>
	</li>
	</ul>
	<!-- END OF Content -->
</div>

<?php
end_script();
