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
 * The page to sen a login-token to any user.
 * 
 * @author mgSoft
 */
// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once '../classes/efa_logbook.php';
include_once '../classes/efa_tables.php';

$tmp_attachement_file = "";
$user_mailto = $toolbox->users->session_user;
$user_person_record = $socket->find_record_matched("efa2persons", 
        ["Id" => $toolbox->users->session_user["PersonId"],"InvalidFrom" => Efa_tables::$forever64
        ]);
// Beware of the difference. The person record uses "Email", the efacloud user "EMail" (Capital 'M'). Only, if
// the person record has an email address set, a logbook will be sebt.
if ($user_person_record === false)
    $toolbox->display_error(i("ZbjL4D|You do not have a relate..."), 
            i(
                    "OrsZ7Z|Because you are not a sp..."), 
            $user_requested_file);
if (! isset($user_person_record["Email"]))
    $toolbox->display_error(i("iZBM6s|No mail address."), i("gIhhVn|The user for the test di..."), 
            $user_requested_file);

// create mails to user. Prepare logbook.
$efa_logbook = new Efa_logbook($toolbox, $socket);
$mails_sent = $efa_logbook->send_logbooks(true);
if ($mails_sent > 0)
    $info = "<p>" . i("Ladp2w|Dispatch to %1 address s...", $mails_sent) . "</p>";
else
    $info = "<p><b>" . i("3XxxvY|Dispatch failed.") . "</b>" . i("iSCF3X|The most likely reason i...") . "</p>";

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
echo i("VK7X6Z| ** Test dispatch person...");
echo $info;
echo i("JwFqj0|<!-- END OF Content -->...");
end_script();
