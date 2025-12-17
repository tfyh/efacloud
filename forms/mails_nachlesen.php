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
 * The form for user mailing self service. Based on the Tfyh_form class, please read instructions their to
 * better understand this PHP-code part.
 * 
 * @author mgSoft
 */
/**
 * Change log: 29.09.2021: changed ../config/lists/mailverteiler to ../config/lists/mailverteiler_lesen to be
 * able to differentiate the rights to send and the rights to read.
 */
// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once '../classes/tfyh_form.php';

// there are two ways to select a "verteiler", either via the get-value "verteiler", or via the
// POST-value "To".
$verteiler = (isset($_GET["verteiler"])) ? intval($_GET["verteiler"]) : 0;

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = ($done == 0) ? 1 : $done;
$form_errors = "";
$form_info = "<p>" . i("o6WA71|In the first step, pleas...") . "</p>";
$form_layout = "../config/layouts/mail_nachlesen";

// ======== Start with form filled in last step: check of the entered values.
if ($done > 0) {
    $form_filled = new Tfyh_form($form_layout, $socket, $toolbox, $done, $fs_id);
    $form_filled->read_entered();
    // $form_errors = $form_filled->check_validity(); no check of form errors to allow direct
    // addressing of "verteiler"
    $entered_data = $form_filled->get_entered();
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This only prevents any logic to apply, if form errors
        // occured.
    } elseif ($done == 1) {
        // get mailto list
        include_once "../classes/tfyh_list.php";
        // there are two ways to select a "verteiler", either via the get-value "verteiler", or via
        // the POST-value "To". Check permission.
        if ($verteiler == 0)
            $list = new Tfyh_list("../config/lists/mailverteiler_lesen", intval($entered_data["An"]), "", 
                    $socket, $toolbox);
        else
            $list = new Tfyh_list("../config/lists/mailverteiler_lesen", $verteiler, "", $socket, $toolbox);
        if (! $list)
            $toolbox->display_error(i("hfr2Zo|Invalid mail distributio..."), 
                    i("xS4f0M|The selected mailing lis..."), $user_requested_file);
        // get mailto list
        if (! $toolbox->users->is_allowed_item($list->get_permission()))
            $toolbox->display_error(i("2D9pBg|Invalid mail distributio..."), 
                    i("QvmvUh|Mails to the selected ma..."), $user_requested_file);
        $count_of_mails = 25;
        $form_info = "<p>" . i("NAOKEm|Here are the last %1 mai...", $count_of_mails, $list->get_list_name()) .
                 "</p>";
        $mails_list = $socket->find_records("Mails", "Verteiler", $list->get_list_id(), 1000);
        $mails_to_skip = ($mails_list === false) ? $count_of_mails : count($mails_list) - $count_of_mails;
        $todo = 2;
        $mails_formatted = "";
        $i = 1;
        foreach ($mails_list as $mail_listed) {
            if ($i > $mails_to_skip) {
                $mail_from_user = $socket->find_record($toolbox->users->user_table_name, 
                        $toolbox->users->user_id_field_name, $mail_listed[$toolbox->users->user_id_field_name]);
                $mailfrom = $mail_from_user["Vorname"] . " " . $mail_from_user["Nachname"];
                $mailto = $list->get_list_name();
                $subject = $mail_listed["Betreff"];
                $body = str_replace("\n", "<br>", $mail_listed["Nachricht"]);
                $mails_formatted = "<p>#" . $i . " <b>" . i("1393xk|Sent:") . "</b> " .
                         $mail_listed["versendetAm"] . "<br /><b>" . i("yhYGfZ|From:") . "</b> " . $mailfrom .
                         "<br /><b>" . i("6KXRE6|To:") . "</b> " . $mailto . "<br /><b>" . i(
                                "XMHPag|Subject:") . "</b> " . $subject . "</p><br />" . $body . "<br />" .
                         i("hv0TyN|Attachment:") . " " . $mail_listed["Attachment"] . " " .
                         i("RyaYQ8|(Can be made available o...") . "<hr>\n" . $mails_formatted;
            }
            $i ++;
        }
    }
}

// ==== continue with the definition and eventually initialization of form to fill for the next step
if (isset($form_filled) && ($todo == $form_filled->get_index())) {
    // redo the 'done' form, if the $todo == $done, i. e. the validation failed.
    $form_to_fill = $form_filled;
} else {
    // if it is the start or all is fine, use a form for the coming step.
    $form_to_fill = new Tfyh_form($form_layout, $socket, $toolbox, $todo, $fs_id);
}

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
echo "<!-- START OF content -->\n<div class='w3-container'>\n";
echo "<h3>" . i("99XIGm|Read previousl sent mail...") . "</h3>";
echo "<p>" . i("sN71rx|Please select the distri...") .
         "</p>";
echo $toolbox->form_errors_to_html($form_errors);
echo $form_info;
if ($todo == 1)
    echo $form_to_fill->get_html();
elseif ($todo == 2)
    echo $mails_formatted; // enable file upload
echo "<!-- END OF form -->\n</div>";
end_script();

    
