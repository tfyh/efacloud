<?php
/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2021 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo file_get_contents('../config/snippets/page_02_nav_to_body');
?>

<div class="w3-container">
	<h3>
		<br>
		<br>
		<br>
		<br>
		<br>Wartungsarbeiten
	</h3>
	<p>
		Die Anwendung ist zur Zeit in Wartung bis voraussichtlich<br>
		<b><?php echo $_GET["until"]; ?>.</b><br> Wir bitten eventuell
		entstehende Unannehmlichkeiten zu entschuldigen.<br>
		<br>
		<br>
		<br>&nbsp;
	</p>
</div>
<?php
echo file_get_contents('../config/snippets/page_03_footer');