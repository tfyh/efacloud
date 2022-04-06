<?php
/**
 * The boathouse client start page.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
// replace the default menu by the javascript menu version.
// The authentication and access rights provisioning was already done
// within init.
$menu = new Tfyh_menu("../config/access/wmenu", $toolbox);
// set a cookie to tell efaWeb the session and user.
setcookie("tfyhUserID", $_SESSION["User"][$toolbox->users->user_id_field_name], 0);
setcookie("tfyhSessionID", session_id(), 0);

$cfg = $toolbox->config->get_cfg();
$current_logbook = str_replace("JJJJ", date("Y"), $cfg["current_logbook"]);

// ===== start page output
// start with boathouse header, which includes the set of javascript references needed.
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');
?>
<!-- The Modal -->
<div id="bModal" class="modal" style="z-index: 4">
	<!-- Modal content -->
	<div id="bModal_content" class="modal-content">
		<span class="close">&times;</span>
		<p>Some text in the Modal.</p>
	</div>
</div>
<span style='display:none;' class='current-logbook' id='<?php echo $current_logbook; ?>'></span>
<!-- Projects grid (4 columns, 1 row; images must have the same size)-->
<!-- Display grid (2 columns)-->
<div class='w3-row'>
	<div class="w3-container" id="bths-headerpanel">
		<div class="w3-col l1">
		</div>
	</div>
</div>
<div class='w3-row'>
	<div class="w3-col l2">
		<div class="w3-container" id="bths-toppanel-left">
			<h4>Verfügbare Boote</h4>
		</div>
		<div class="w3-container" id="bths-mainpanel-left">Die Übersicht über
			die verfügbaren Boote wird aufgebaut, sofern die Berechtigung dafür
			existiert.</div>
	</div>
	<div class="w3-col l2">
		<div class="w3-container" id="bths-toppanel-right">
			<h4>Nicht verfügbare Boote</h4>
		</div>
		<div class="w3-container" id="bths-mainpanel-right">Die Übersicht über
			die nicht verfügbaren Boote wird aufgebaut, sofern die Berechtigung
			dafür existiert.</div>
	</div>
</div>
<div class='w3-row'>
	<div class="w3-col l1">
		<div class="w3-container" id="bths-listpanel-header"></div>
	</div>
	<div class="w3-col l1">
		<div class="w3-container" id="bths-listpanel-list"></div>
	</div>
</div>

<?php
if (isset($_SESSION["User"])) {
    $script = "\n\n<script>\nvar currentUserAtServer = {\n";
    foreach ($_SESSION["User"] as $key => $value) {
        if (strcasecmp($key, "ecrhis") != 0)
            $script .= '    ' . $key . ' : "' . $value . '"' . ",\n";
    }
    $script .= '    sessionID : "' . session_id() . '"' . "\n";
    $script .= "};\n</script>\n\n";
    echo $script;
}
echo file_get_contents('../config/snippets/page_03_footer_bths');