<?php
/**
 * The public home page.
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

<!-- Image header -->
<div class="w3-container">
	<img src="../resources/efacloud-logo_512.png" alt="efacloud_logo"
		style="width: 50%">
</div>

<h2>
	<br>Efacloud - efa in der Wolke.
</h2>
<p>Efa hat eine lange Tradition. Auf dem Weg zur Digitalisierung wird
	das beliebteste Fahrtenbuch Deutschlands hier um die Möglichkeit
	ergänzt, die Daten in der Cloud abzulegen. Sicher, von überall
	zugänglich, konsistent.</p>
<p>Diese Oberfläche gibt einen Blick auf den efaCloud Server frei: auf
	die Daten und auf die Schnittstelle zum client. Sie erlaubt, den
	Zugriff zur efaCloud Serverdatenbank zu ersetzen. Aber sie ersetzt
	nicht efa. efa bleibt das Programm um die Daten zu verwalten, entweder
	im Bootshaus oder als efa-Basis zu Hause.</p>

<?php
end_script();