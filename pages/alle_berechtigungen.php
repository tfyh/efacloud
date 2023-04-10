<?php
/**
 * An overview on all accesses currently granted.
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

// page heading, identical for all workflow steps
echo i("JRi7Qo| ** All permissions ** A...");
echo $toolbox->users->get_all_accesses($socket);

echo "<h4>" . i("VCcc4J|Permissions per role") . "</h4>";
$menu_file_path = "../config/access/imenu";
$audit_menu = new Tfyh_menu($menu_file_path, $toolbox);
echo $audit_menu->get_allowance_profile_html($menu_file_path);

echo i("uDpZVK| ** Information on data ...");
end_script();
