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

$tmp_attachement_file = "";
$id = (isset($_SESSION["getps"][$fs_id]["id"])) ? $_SESSION["getps"][$fs_id]["id"] : 0;
if ($id == 0)
    $toolbox->display_error(i("9R86C3|Not allowed."), 
            i("FJDHAh|The page °%1° must be ca..." . "Nutzers aufgerufen werden.", $user_requested_file), 
            $user_requested_file);
$user_mailto = ($id == 0) ? false : $socket->find_record("efaCloudUsers", "ID", $id);
if ($user_mailto == false)
    $toolbox->display_error(i("PBQQGF|Not found"), i("cyN5oe|The user for sending the..."), 
            $user_requested_file);

// create mails to users. Prepare.
require_once '../classes/tfyh_mail_handler.php';
$cfg = $toolbox->config->get_cfg();
$cfg["mail_subject_acronym"] = $cfg["acronym"]; // acronym is the club's acronym in efaCloud.
$mail_handler = new Tfyh_mail_handler($toolbox->config->get_cfg());
$user_name = $user_mailto["Vorname"] . " " . $user_mailto["Nachname"];
$mailfrom = i("kqKRmK|Logbook") . " " . $mail_handler->mail_subject_acronym . " <" .
         $mail_handler->mail_schriftwart . ">";

// create mails one by one. Note: for ($isContinueEdit || $isTempSave) the
$anrede = (isset($user_mailto["Geschlecht"])) ? (strcasecmp("m", $user_mailto["Geschlecht"]) === 0) ? "<p>" .
         i("H3gNGJ|Dear") . " " : "<p>" . i("cr2i2h|Dear") . " " : "<p>" . i("i91IKQ|Dear") . " ";
$anrede .= $user_mailto[$toolbox->users->user_firstname_field_name] . " " .
         $user_mailto[$toolbox->users->user_lastname_field_name];
$plus_days = 2;
$deep_link = $app_root . "/forms/profil_aendern.php?id=" . $id . "&pw=1";
$login_token = $toolbox->create_login_token($user_mailto["EMail"], $plus_days, $deep_link);

$message = "<p>" . $anrede . "<br>" . i("fjQeZR|To set your password, pl...") . " \n<a href='" . $app_root .
         "/forms/login.php?token=" . urlencode($login_token) . "'>" . i("BuukBt|Direct access") . "</a>. " .
         i("s6B4AE|Please note that this wa...", $plus_days) . "<br>" . i("oGFs8D|Good luck!") . "<br>" .
         $mail_handler->mail_subscript . "</b>";
$message .= $mail_handler->mail_footer;
$this_mailto = $toolbox->strip_mail_prefix($user_mailto["EMail"]);
$mail_was_sent = $mail_handler->send_mail($mailfrom, $mailfrom, $this_mailto, "", "", 
        $mail_handler->mail_subject_acronym . i("K1bZta|Set password for efaClou..."), $message, "", "");
if ($mail_was_sent) {
    $info = "<p>" . i("mvC38h|Dispatch successful for:") . " '" . $this_mailto . "'.</p>";
    $toolbox->logger->log(0, $toolbox->users->session_user["@id"], 
            i("o94iyq|Login token sent to user...") . " " . $user_name . "(" . $id . ").");
} else {
    $info = "<p><b>" . i("Wk99iY| ** Dispatch failed ** f...") . " '" . $this_mailto . "'.</p>";
    $toolbox->logger->log(2, $toolbox->users->session_user["@id"], i("eAyB1z|Login token sent to user..."), 
            $user_name, $id);
}

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
echo i("rpXiYW| ** Send mails to users ...");
echo $info;
echo "</div>\n<!-- END OF Content -->";
end_script();

