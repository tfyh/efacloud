<?php
/**
 * Page display file. Shows all mails available to public from ARB Verteler.
 *
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
if (isset($_GET["todo"]))
    $todo = $_GET["todo"];
else
    $todo = 0;

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// kein Todo. Auflisten der Optionen
if ($todo == 0) {
    ?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Sammeltransaktionen</h3>
	<p>Hier können Sammeltransaktionen ausgeführt werden.</p>
	<p>Wenn Du diese Seite siehst, bst du dazu berechtigt. Führe die
		Transaktionen mit Bedacht aus. Sie können in der Regel nicht
		rückgängig gemacht werden.</p>
	<p></p>
	<p>
		<b>Mögliche Transaktionen</b>
	</p>
	<p>
		<a href="?todo=1">Anonymisieren des Fahrtenbuches</a> zu
		Demonstartionszwecken. Achtung: alle Fahrenbuchdaten werden
		manipuliert.
	</p>
</div>
<?php
} // angefordert ist die Ausgabe der Anmeldungen nach Ruderkurs
elseif ($todo == 1) {
    include_once "../classes/anonymizer.php";
    $anonymizer = new Anonymizer($socket, $_SESSION["User"][$toolbox->users->user_id_field_name]);
    ?>
<div class="w3-container">
	<h3>Sammeltransaktionen</h3>
	<p>Das Fahrtenbuch wurde anonymisiert.</p>
</div>
<?php
} // angefordert ist das Erstellen der SEPA Lastschriftdatei
end_script();
