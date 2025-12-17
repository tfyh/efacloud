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
 * The form for upload and import of multiple data records as csv-tables. Based on the Tfyh_form class, please
 * read instructions their to better understand this PHP-code part.
 * 
 * @author mgSoft
 */
// TODO überarbeiten!!

// compare "nutzer_aendern.php::$uniques"
$uniques = ["efaCloudUsers" => ["efaCloudUserID","efaAdminName","EMail"
]
];

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once '../classes/tfyh_form.php';

$tmp_upload_file = "";

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = ($done == 0) ? 1 : $done;
$form_errors = "";
$form_layout = "../config/layouts/tabelle_importieren";

// ======== Start with form filled in last step: check of the entered values.
if ($done > 0) {
    $form_filled = new Tfyh_form($form_layout, $socket, $toolbox, $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    $table_name = $entered_data["Tabelle"];
    $column_names = $socket->get_column_names($table_name);
    // legacy id names are ID and uid. Try those first.
    $idname = (in_array("uid", $column_names)) ? "uid" : ((in_array("ID", $column_names)) ? "ID" : "???");
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This avoids any change, if form errors occured.
    } elseif ($done == 1) {
        // step 1 form was filled. Values were valid
        if (strlen($_FILES['userfile']["name"]) < 1) {
            // Special case upload error. Userfile can not be checked after
            // being entered, must be checked
            // after upload was tried.
            $form_errors .= i("mYd52x|No file specified. Pleas...");
        } else {
            $tmp_upload_file = file_get_contents($_FILES['userfile']["tmp_name"]);
            if (! $tmp_upload_file)
                $form_errors .= i("xJsUjf|Unknown error during upl...");
            else {
                $_SESSION["io_file"] = $_FILES['userfile']["name"];
                $_SESSION["io_table"] = $entered_data["Tabelle"];
                $tfilename = "../log/io/" . $_SESSION["io_file"];
                file_put_contents($tfilename, $tmp_upload_file);
                
                // unique fields check
                $tablename = $_SESSION["io_table"];
                $import_result = "";
                if (isset($uniques[$tablename]) && is_array($uniques[$tablename]) &&
                         (count($uniques[$tablename]) > 0)) {
                    $records = $toolbox->read_csv_array($tfilename);
                    $r = 0;
                    foreach ($records as $record) {
                        $r ++;
                        $id_this_record = (isset($record["ID"]) && (strlen($record["ID"]) > 0)) ? intval(
                                $record["ID"]) : 0;
                        foreach ($uniques[$tablename] as $unique_field) {
                            $matching = (isset($record[$unique_field])) ? $socket->find_records_matched(
                                    $tablename, 
                                    [$unique_field => $record[$unique_field]
                                    ], 2) : false;
                            if ($matching !== false) {
                                foreach ($matching as $mrecord) {
                                    if (intval($mrecord["ID"]) != $id_this_record)
                                        $import_result .= i("iU8UQg|#Line %1 The data field ...", $r, 
                                                $unique_field, $mrecord["ID"]) . "<br>";
                                }
                            }
                        }
                    }
                }
                
                // do import verification
                if (strlen($import_result) == 0)
                    $import_result .= $socket->import_table_from_csv($toolbox->users->session_user["@id"], 
                            $tablename, $tfilename, true, $idname);
                // only move on, if import did not return an error.
                if (strcmp(substr($import_result, 0, 1), "#") != 0)
                    $todo = $done + 1;
                else
                    $form_errors .= $import_result;
            }
        }
    } elseif ($done == 2) {
        // step 2 form was filled. Values were valid. Now execute import.
        $import_result = $socket->import_table_from_csv($toolbox->users->session_user["@id"], 
                $_SESSION["io_table"], "../log/io/" . $_SESSION["io_file"], false, $idname);
        $todo = $done + 1;
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
echo "<h3>" . i("17392l|Import table") . "</h3>";
echo "<p>" . i("p4vCuA|This form is import a ta...") . "</p>";

if ($todo == 1) { // step 1. Texts for output
    echo "<p>" . i("UTmyA8|For importing an ID must...") . "</p>";
    echo "<p>" . i("nBgz5e|Tables to be imported mu...") . "</p>";
    echo "<p>" . i("A0zktK|Tables to be imported th...") . "</p>";
    echo $toolbox->form_errors_to_html($form_errors);
    echo $form_to_fill->get_html(true); // enable file upload
    echo $form_to_fill->get_help_html();
} elseif ($todo == 2) { // step 2. Texts for output
    echo "<p>" . i("Fg6pWu|The file upload was succ...") . "</p>";
    echo "<p>" . i("FFjdFF|In the next step, the ta...") . "</p>";
    // no form errors possible at this step. just a button clicked.
    echo $import_result;
    echo $form_to_fill->get_html();
    echo $form_to_fill->get_help_html();
} elseif ($todo == 3) { // step 3. Texts for output
    echo $import_result;
    echo "<p>" . i("KLYCCf|The file import was carr...") . "</p>";
}

// Help texts and page footer for output.
echo "</div>";
end_script();
