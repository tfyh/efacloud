<?php
/**
 * The start of the session after successfull login.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

// ==== error handling
$user_id = (isset($_GET["id"])) ? intval($_GET["id"]) : 0; // identify user via ID
$user_nr = (isset($_GET["nr"])) ? intval($_GET["nr"]) : 0; // identify user via efaCloudUserID
$user_to_show = false;
if ($user_id == 0) {
    if ($user_nr > 0)
        $user_to_show = $socket->find_record_matched($toolbox->users->user_table_name, 
                [$toolbox->users->user_id_field_name => $user_nr
                ]);
} else {
    $user_to_show = $socket->find_record_matched($toolbox->users->user_table_name, ["ID" => $user_id
    ]);
    $efaCloudUserID = $user_to_show[$toolbox->users->user_id_field_name];
}
if ($user_to_show === false) {
    $toolbox->display_error(i("5UcqeO|Not allowed."), 
            i("ov4KAp|Page °%1° must be called...", $user_requested_file), $user_requested_file);
} else {
    $efaCloudUserID = $user_nr;
    $user_id = $user_to_show["ID"];
}

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');
echo i("C13cVm| ** Profile of %1 ** ", 
        $user_to_show[$toolbox->users->user_firstname_field_name] . " " .
                 $user_to_show[$toolbox->users->user_lastname_field_name]);

// update user data
echo $toolbox->users->get_user_profile_on_ID($user_to_show["ID"], $socket, false);
echo "<p><b>" . i("JIIqc3|For this user the follow...") . "</b><br>";
$_SESSION["search_result"] = [];
$_SESSION["search_result"][1] = $user_to_show;
$_SESSION["search_result"]["tablename"] = "efaCloudUsers";

echo $toolbox->users->get_action_links($user_id) .
         "<a href='../pages/show_history.php?searchresultindex=1'> - " . i("biT1cV|Change history") .
         "</a></p>";
echo i("wLHGFz| ** Information on data ...");
end_script();
