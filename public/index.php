<?php
/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2021 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
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
	<p style='text-align: right; margin-bottom: -60px;margin-right:10px;'>
		<img src="../resources/efaCloud-Wolke_blau.png" alt="cloud"
			style="width: 20%">
	</p>

	<h2>
		<br>Efacloud - efa in der Wolke.
	</h2>
	<p>Efa hat eine lange Tradition. Auf dem Weg zur Digitalisierung wird
		das beliebteste Fahrtenbuch Deutschlands hier um die Möglichkeit
		ergänzt, die Daten in der Cloud abzulegen. Sicher, von überall
		zugänglich, konsistent.</p>
	<p>Diese Oberfläche ist der Einstieg zu efaWeb und efaCloud:</p>
	<ul>
		<li>efaCloud: der Serverzugriff für Verwaltung und Überwachung der
			Fahrtenbuchdaten aller angeschlossenen efa-PCs.</li>
		<li>efaWeb: ein web-basiertes Fahrtenbuchporogramm, für einfache
			Verwaltungsaufgaben, im Aufbau.</li>
	</ul>
	<p>Über Feedback an support(at)efacloud.org freue ich mich immer!</p>
</div>

<?php
end_script();