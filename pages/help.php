<?php
/**
 * A page to reset the complete data base.
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

// ===== load help page
echo file_get_contents('https://www.efacloud.org/hilfe_content.php');
?>
<div class="w3-container">
	<p>
		Mehr Information gibt es auf der &gt;&gt;&gt; <a href='https://www.efacloud.org' target='_blank'>
			efaCloud-Webseite (öffnet einen neuen Tab).</a>
	</p>
</div>

<?php
end_script();