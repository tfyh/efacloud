<?php
/**
 * The maintenence page.
 *
 * @author mgSoft
 */

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo file_get_contents('../config/snippets/page_02_nav_to_body');
?>

<div class="w3-container">
	<h3><br><br><br><br><br>Wartungsarbeiten</h3>
	<p>
		Die Anwendung ist zur Zeit in Wartung bis voraussichtlich<br><b><?php echo $_GET["until"]; ?>.</b><br> 
		Wir bitten eventuell entstehende Unannehmlichkeiten zu entschuldigen.<br><br><br><br>&nbsp;
	</p>
</div>
<?php 
echo file_get_contents('../config/snippets/page_03_footer');