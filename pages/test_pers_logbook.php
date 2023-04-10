<?php
/**
 * The page to sen a login-token to any user.
 * 
 * @author mgSoft
 */
// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

$tmp_attachement_file = "";
$user_mailto = $_SESSION["User"];
if (! isset($user_mailto["EMail"]))
    $toolbox->display_error(i("iZBM6s|No mail address."), i("gIhhVn|The user for the test di..."), 
            $user_requested_file);

// create mails to user. Prepare logbook.
include_once '../classes/efa_logbook.php';
$efa_logbook = new Efa_logbook($toolbox, $socket);
$mails_sent = $efa_logbook->send_logbooks(true);
if ($mails_sent > 0)
    $info = "<p>" . i("Ladp2w|Dispatch to %1 address s...", $mails_sent) . "</p>";
else
    $info = "<p><b>" . i("3XxxvY|Dispatch failed.") . "</b>" . i("iSCF3X|The most likely reason i...") . "</p>";

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
echo i("VK7X6Z| ** Test dispatch person...");
echo $info;
echo i("JwFqj0|<!-- END OF Content -->...");
end_script();
