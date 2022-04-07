<?php
/**
 * The Impressum page.
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
	<h1>
		<br>
		<br>
		<br>Hilfe
	</h1>
	<p>Hilfe zu efaCloud ist verfügbar auf der</p>
	<ul>
		<li><a href='https://www.efacloud.org'>efaCloud Homepage</a></li>
	</ul>
	<p>und im eigens dafür eingericheten Forum auf der</p>
	<ul>
		<li><a href='efa.nmichael.de'>efa-Hompage</a></li>
	</ul>
</div>
<!-- END OF content -->

<?php
end_script();
