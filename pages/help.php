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

echo i("Z4iAHN| ** Help in efaCloud ** ..."); 
$helpdocs = scandir("../helpdocs/" . $toolbox->config->language_code);
foreach ($helpdocs as $helpdoc) {
    $helpdoc_name = str_replace(".html", "", $helpdoc);
    $info_link = "<sup class='eventitem' id='showhelptext_" . str_replace(".html", "", $helpdoc_name) . "'>&#9432;</sup>";
    if (substr($helpdoc_name, 0, 1) != ".")
        echo "<li>" . $helpdoc_name . $info_link . "</li>";
}
echo i("C1HUuR| ** More information is ..."); 
end_script();
