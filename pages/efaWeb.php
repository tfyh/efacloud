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
 * The boathouse client start page.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
// replace the default menu by the javascript menu version.
// The authentication and access rights provisioning was already done
// within init.
$menu = new Tfyh_menu("../config/access/wmenu", $toolbox);

// set a cookie to tell efaWeb the session and user.
// setcookie("tfyhUserID", $toolbox->users->session_user["@id"], 0);
// setcookie("tfyhSessionID", session_id(), 0);

include_once "../classes/efa_config.php";
$efa_config = new Efa_config($toolbox);
// set logbook allowance
$logbook_allowance = ((strcasecmp($toolbox->users->session_user["Rolle"], "admin") == 0) ||
         (strcasecmp($toolbox->users->session_user["Rolle"], "board") == 0)) ? "all" : "workflow";
// set logbook
$logbook_to_use = $efa_config->current_logbook;
// set name format
$name_format = $efa_config->config["NameFormat"];

// ===== start page output
// start with boathouse header, which includes the set of javascript references needed.
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

$personId = (isset($toolbox->users->session_user["PersonId"])) ? $toolbox->users->session_user["PersonId"] : "????????-????-????-????????????";
echo i("TdBhkc| ** Available boats ** T...", $logbook_to_use, $logbook_allowance, $personId);

// ===== create an API session and forward the API session id
$api_user_id = intval($toolbox->users->session_user["@id"]);
if ($api_user_id > 0) {
    echo "\n<script>var api_user_id = '" . $api_user_id . "';</script>";
    $api_session_id = $toolbox->app_sessions->api_session_start(intval($api_user_id), $socket, "new");
    echo "\n<script>var api_session_id = '" . $api_session_id . "';</script>";
}

echo $efa_config->pass_on_config();
echo "\n<script>var php_languageCode = '" . $toolbox->config->language_code . "';</script>\n";

echo file_get_contents('../config/snippets/page_03_footer_bths');
$script_completed = true;
