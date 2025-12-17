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
 * The form for user profile self service. Based on the Tfyh_form class, please read instructions their to
 * better understand this PHP-code part.
 * 
 * @author mgSoft
 */
// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
// ===== page does not need an active session
include_once "../classes/init.php";
include_once '../classes/tfyh_form.php';

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = ($done == 0) ? 1 : $done;
$form_errors = "";
$form_layout = "../config/layouts/client_tx_statistics";
$users_to_show_html = "";

// ======== Start with form filled in last step: check of the entered values.
if ($done > 0) {
    $form_filled = new Tfyh_form($form_layout, $socket, $toolbox, $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This avoids any change, if form errors occured.
    } elseif ($done == 1) {
        if (intval($entered_data["type"]) < 4)
            echo header(
                    "Location: ../pages/show_logs_client.php?clientID=" . $entered_data["clientID"] . "&type=" .
                             $entered_data["type"]);
        else
            echo header("Location: ../pages/client_tx_statistics.php?clientID=" . $entered_data["clientID"]);
    }
}

// ==== continue with the definition and eventually initialization of form to fill for the next step
if (isset($form_filled) && ($todo == $form_filled->get_index())) {
    // redo the 'done' form, if the $todo == $done, i. e. the validation failed.
    $form_to_fill = $form_filled;
} else {
    // if it is the start or all is fine, use a form for the coming step.
    $form_to_fill = new Tfyh_form($form_layout, $socket, $toolbox, $todo, $fs_id);
    if (($todo == 1)) {
        $available_clients = scandir("../uploads");
        $select_options_list = [];
        foreach ($available_clients as $available_client) {
            if (is_dir("../uploads/" . $available_client)) {
                $client = $socket->find_record($toolbox->users->user_table_name, 
                        $toolbox->users->user_id_field_name, $available_client);
                if ($client !== false)
                    $select_options_list[] = $available_client . "=" . $client["Vorname"] . " " .
                             $client["Nachname"];
            }
        }
        if (count($select_options_list) == 0)
            $select_options_list[] = "0=" . i("lBnu0m|no client statistics ava...");
        $form_to_fill->select_options = $select_options_list;
        $radio_options = ["1=" . i("QZLbIG|the log file"),"2=" . i("VDMUsh|the Synchronisation erro..."),
                "3=" . i("Aa7tu3|the configuration audit ..."),
                "4=" . i("J54Euo|The amount of transactio...")
        ];
        $form_to_fill->radio_options = $radio_options;
    }
}

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
echo i("HixbNW| ** View client log file...");
echo $toolbox->form_errors_to_html($form_errors);
if ($todo < 2) {
    echo $form_to_fill->get_html();
    echo $form_to_fill->get_help_html();
} else
    echo $users_to_show_html;

echo "</div>";
end_script();

    
