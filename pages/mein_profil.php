<?php
/**
 *
 *       efaCloud
 *       --------
 *       https://www.efacloud.org
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
echo $toolbox->users->session_user[$toolbox->users->user_firstname_field_name] . " " .
         $toolbox->users->session_user[$toolbox->users->user_lastname_field_name];
echo i("XLx3yi| ** This is the personal...");
$session_user = $socket->find_record_matched($toolbox->users->user_table_name, 
        [$toolbox->users->user_id_field_name => $toolbox->users->session_user["@id"]
        ]);
$toolbox->users->set_session_user($session_user); 
$page_errors = "";
$page_info = "";

if (strcasecmp($toolbox->users->session_user["Rolle"], $toolbox->users->session_user["Rolle"]) !== 0)
    echo "<p style='color:#f00'><b>" . i("VDv9EA|Currently logged in as") . " " . $toolbox->users->session_user["Rolle"] .
             "</b></p>";
echo $toolbox->form_errors_to_html($page_errors);
echo $page_info;

echo $toolbox->users->get_user_profile($toolbox->users->session_user["@id"], $socket);
if (strcasecmp($toolbox->users->session_user["Rolle"], "bths") !== 0)
    echo "<br><a href='../forms/profil_aendern.php'> &gt; " . i("DAwDVx|Change profile") . "</a>";

echo i("07OND4| ** Information on data ...");
end_script();
