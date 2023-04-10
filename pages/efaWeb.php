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

include_once "../classes/efa_config.php";
$efa_config = new Efa_config($toolbox);
// set logbook allowance
$logbook_allowance = ((strcasecmp($_SESSION["User"]["Rolle"], "admin") == 0) ||
         (strcasecmp($_SESSION["User"]["Rolle"], "board") == 0)) ? "all" : "workflow";
// set logbook
$logbook_to_use = $efa_config->current_logbook;
// set name format
$name_format = $efa_config->config["NameFormat"];

// ===== start page output
// start with boathouse header, which includes the set of javascript references needed.
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');
$personId = (isset($_SESSION["User"]["PersonId"])) ? $_SESSION["User"]["PersonId"] : "????????-????-????-????????????";
echo i("TdBhkc| ** Available boats ** T...", $logbook_to_use, $logbook_allowance, $personId);
// pass information to Javascript.
// User information
if (isset($_SESSION["User"])) {
    $currentUserAtServer = [];
    foreach ($_SESSION["User"] as $key => $value)
        if (strcasecmp($key, "ecrhis") != 0)
            $currentUserAtServer[$key] = $value;
    $currentUserAtServer["sessionID"] = session_id();
    $script = "\n\n<script>\nvar currentUserAtServer = " .
             json_encode(str_replace("\"", "\\\"", $currentUserAtServer)) . ";\n</script>\n\n";
    // echo $script; Obsolet??
}

echo $efa_config->pass_on_config();
echo "\n<script>var php_languageCode = '" . $toolbox->config->language_code . "';</script>\n";
echo file_get_contents('../config/snippets/page_03_footer_bths');
$script_completed = true;
