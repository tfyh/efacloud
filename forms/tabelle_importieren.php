<?php
/**
 * The form for upload and import of multiple data records as csv-tables. Based on the Tfyh_form class, please
 * read instructions their to better understand this PHP-code part.
 * 
 * @author mgSoft
 */

// ===== special efacloud field idname conventions
$idnames = ["efa2autoincrement" => "Sequence","efa2boatstatus" => "BoatId","efa2clubwork" => "Id",
        "efa2crews" => "Id","efa2fahrtenabzeichen" => "PersonId","efa2messages" => "MessageId",
        "efa2sessiongroups" => "Id","efa2statistics" => "Id","efa2status" => "Id","efa2waters" => "Id"
];
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
    $idname = (isset($entered_data["Tabelle"]) && isset($idnames[$entered_data["Tabelle"]])) ? $idnames[$entered_data["Tabelle"]] : "ID";
    
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
                    $import_result .= $socket->import_table_from_csv(
                            $_SESSION["User"][$toolbox->users->user_id_field_name], $tablename, $tfilename, 
                            true, $idname);
                // only move on, if import did not return an error.
                if (strcmp(substr($import_result, 0, 1), "#") != 0)
                    $todo = $done + 1;
                else
                    $form_errors .= $import_result;
            }
        }
    } elseif ($done == 2) {
        // step 2 form was filled. Values were valid. Now execute import.
        $import_result = $socket->import_table_from_csv(
                $_SESSION["User"][$toolbox->users->user_id_field_name], $_SESSION["io_table"], 
                "../log/io/" . $_SESSION["io_file"], false, $idname);
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
echo i("Yxab32| ** Import table ** Here...");
if ($todo == 1) { // step 1. Texts for output
    echo i("Bi1XKi| ** When importing, the ...");
    echo $toolbox->form_errors_to_html($form_errors);
    echo $form_to_fill->get_html(true); // enable file upload
    echo $form_to_fill->get_help_html();
} elseif ($todo == 2) { // step 2. Texts for output
    echo i("MSsBTV| ** The file upload was ...");
    // no form errors possible at this step. just a button clicked.
    echo $import_result;
    echo $form_to_fill->get_html();
    echo $form_to_fill->get_help_html();
} elseif ($todo == 3) { // step 3. Texts for output
    echo $import_result;
    echo i("jKbHGN| ** The file import was ...");
}

// Help texts and page footer for output.
echo "</div>";
end_script();
