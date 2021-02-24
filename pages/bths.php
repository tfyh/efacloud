<?php
/**
 * The boathouse client start page.
 *
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

// ===== start page output
// start with boathouse header, which includes the set of javascript references needed.
echo file_get_contents('../config/snippets/page_01_start_bths');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');
?>
<div class="w3-container">&nbsp;</div>
<!-- The Modal -->
<div id="bModal" class="modal" style="z-index:4">
	<!-- Modal content -->
	<div id="bModal_content" class="modal-content">
		<span class="close">&times;</span>
		<p>Some text in the Modal.</p>
	</div>
</div>
<!-- Projects grid (4 columns, 1 row; images must have the same size)-->
<div class="w3-auto">
	<div class="w3-col l2">
		<div class="w3-container" id="bths-toppanel-left">
		<h4>Verfügbare Boote</h4>
		</div>
		<div class="w3-container" id="bths-mainpanel-left">
		</div>
	</div>
	<div class="w3-col l2">
		<div class="w3-container" id="bths-toppanel-right">
		<h4>Nicht verfügbare Boote</h4>
		</div>
		<div class="w3-container" id="bths-mainpanel-right">
		</div>
	</div>
</div>

<?php
echo file_get_contents('../config/snippets/page_03_footer_bths');