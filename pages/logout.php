<?php
/**
 * Page display file. Logout page.
 *
 * @author mgSoft
 */

// ===== initialize toolbox & internationalization.
include_once "../classes/init_i18n.php";  // usually this is included with init.php
include_once '../classes/tfyh_toolbox.php';
$toolbox = new Tfyh_toolbox();
load_i18n_resource($toolbox->config->language_code);

// remove all remnants of the session.
session_start(); // you need to start the session to be able to destroy it.
                 // delete the extra session file which was stored for load throttling (session
                 // counter)
$toolbox->app_sessions->session_close("logout", session_id());

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
// ===== page shall be available for anonymous users.
// This will also invalidate the $_SESSION["User"]
include_once "../classes/init.php";

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

echo i("FVAYy8| ** Logged off ** Logo...");
end_script();

