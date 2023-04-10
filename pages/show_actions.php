<?php
/**
 * Page display file. Shows all recent activities.
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

echo i("UngRbx| ** Access statistics **...");
// show activites summary last two weeks.
echo $toolbox->logger->get_activities_html(14);
echo i("KmCBHj|<!-- END OF Content -->...");
end_script();
