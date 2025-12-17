<?php
/**
 *
 *       the tools-for-your-hobby framework
 *       ----------------------------------
 *       https://www.tfyh.org
 *
 * Copyright  2018-2024  Martin Glade
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License. 
 */

/**
 * Page display file. Logout page.
 * 
 * @author mgSoft
 */

// ===== redirect error repoting.
$err_file = "../log/php_error.log";
if (filesize($err_file) > 200000)
    copy($err_file, $err_file . ".previous");
error_reporting(E_ERROR | E_WARNING);
ini_set("error_log", $err_file);

// ===== initialize toolbox & internationalization.
include_once "../classes/init_i18n.php"; // usually this is included with init.php
include_once '../classes/tfyh_toolbox.php';
$toolbox = new Tfyh_toolbox();
load_i18n_resource($toolbox->config->language_code);

// remove all remnants of the session.
if (session_status() === PHP_SESSION_NONE)
    session_start(); // you need to start the session to be able to destroy it.
                     // delete the extra session file which was stored for
                     // load throttling (session counter)
$toolbox->app_sessions->web_session_close("logout");

if (isset($_GET["goto"]) && (strlen($_GET["goto"]) > 0))
    header("Location: " . $_GET["goto"]);

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
// ===== page shall be available for anonymous users.
// This will also invalidate the $toolbox->users->session_user
include_once "../classes/init.php";

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

echo "<!-- START OF content -->\n<div class='w3-container'>\n";
echo "<h3><br><br><br><br>" . i("cxAly0|Logged off") . "</h3>";
echo "<p>" . i("bvy48a|Logoff was successful.") . "</p>\n</div>";
end_script();

