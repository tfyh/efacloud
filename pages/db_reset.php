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
 * A page to reset the complete data base.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
$do_reset_full = (strcmp($_GET["do_reset"], "full") == 0);
$do_reset_wo_users = (strcmp($_GET["do_reset"], "wo_users") == 0);
$do_reset = ($do_reset_full || $do_reset_wo_users);
if ($do_reset) {
    // ===== create data base
    include_once '../classes/efa_tools.php';
    $efa_tools = new Efa_tools($toolbox, $socket);
    $result_bootstrap = $efa_tools->init_efa_data_base(true, true, $do_reset_full);
}

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');
echo i("jmEZKD| ** Delete database %1 a...", $socket->get_db_name());

if ($do_reset_full) {
    echo "<p>" . i("PUAtWz|The database has been re...", $toolbox->users->session_user["@firstname"], 
            $toolbox->users->session_user["@lastname"]) . " " . i("OI0hj9|Please log out and log i...") . "<br><br>" .
             "<span class='formbutton'><a href='../pages/logout.php'>" . i("4RX8ec|Logout") .
             "</a></span><br></p>";
    echo "<p>" . i("WOnK0g|The following activity r...") . "<br>" . $result_bootstrap . "</p>";
} elseif ($do_reset_wo_users) {
    echo "<p>" . i("e551y9|The database has been re...") . "<br><br>" .
             "<span class='formbutton'><a href='../pages/logout.php'>" . i("sOWvNq|Logout") .
             "</a></span><br></p>";
    echo "<p>" . i("U7JDda|The following activity r...") . "<br>" . $result_bootstrap . "</p>";
} else {
    echo i("odpdN0| ** In really rare case...", $toolbox->users->session_user["@firstname"] . " " . $toolbox->users->session_user["@lastname"]);
    echo $socket->get_db_name() . " " . i("9hnY2G|at") . " " . $app_root;
    echo i("Edbkq1| ** --- ** will be dele...");
}
echo "</div>";
end_script();
