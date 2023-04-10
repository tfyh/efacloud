<?php
/**
 * Page display file. Shows all mails available to public from ARB Verteler.
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

echo i("qJk3Th| ** Changes to data ** H...");
$socket->cleanse_change_log(100); // keep changes for max. 100 days.
echo $socket->get_change_log();
echo i("L6DB6I|<!-- END OF Content -->...");
end_script();
