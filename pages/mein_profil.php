<?php
/**
 * The start of the session after successfull login.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
$sendPDF = (isset($_GET["sendPDF"])) ? $_GET["sendPDF"] : 0;

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');
echo i("TkljK3| ** Profile of ** "); 
echo $_SESSION["User"][$toolbox->users->user_firstname_field_name] . " " .
         $_SESSION["User"][$toolbox->users->user_lastname_field_name];
echo i("XLx3yi| ** This is the personal...");
$_SESSION["User"] = $socket->find_record_matched($toolbox->users->user_table_name, 
        [$toolbox->users->user_id_field_name => $_SESSION["User"][$toolbox->users->user_id_field_name]
        ]);
$page_errors = "";
$page_info = "";

if (strcasecmp($_SESSION["User"]["Rolle"], $_SESSION["User"]["Rolle"]) !== 0)
    echo "<p style='color:#f00'><b>" . i("VDv9EA|Currently logged in as") . " " . $_SESSION["User"]["Rolle"] .
             "</b></p>";
echo $toolbox->form_errors_to_html($page_errors);
echo $page_info;

echo $toolbox->users->get_user_profile($_SESSION["User"][$toolbox->users->user_id_field_name], $socket);
if (strcasecmp($_SESSION["User"]["Rolle"], "bths") !== 0)
    echo "<br><a href='../forms/profil_aendern.php'> &gt; " . i("DAwDVx|Change profile") . "</a>";

echo i("07OND4| ** Information on data ...");
end_script();
