<?php
/**
 * The form for upload and import of persons' records (not efaCloudUsers, but efa2persons).
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once '../classes/tfyh_form.php';
include_once '../classes/efa_tables.php';

$tmp_upload_file = "";

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = ($done == 0) ? 1 : $done;
$form_errors = "";
$form_layout = "../config/layouts/personen_importieren";

// ======== Start with form filled in last step: check of the entered values.
if ($done > 0) {
    $form_filled = new Tfyh_form($form_layout, $socket, $toolbox, $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    $mode = (isset($entered_data["Modus"]) && intval($entered_data["Modus"]) > 0) ? intval(
            $entered_data["Modus"]) : $_SESSION["personsImportMode"];
    $_SESSION["personsImportMode"] = $mode;
    
    include_once '../classes/efa_record.php';
    $efa_record = new Efa_record($toolbox, $socket);
    $valid_records = array();
    $user_id = $_SESSION["User"][$toolbox->users->user_id_field_name];
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This avoids any change, if form errors occured.
    } elseif ($done == 1) {
        //
        // step 1 form was filled. Import verification
        //
        if (strlen($_FILES['userfile']["name"]) < 1) {
            // Special case upload error. Userfile can not be checked after
            // being entered, must be checked
            // after upload was tried.
            $form_errors .= i("Rlv6xi|No file specified. pleas...");
        } else {
            $tmp_upload_file = file_get_contents($_FILES['userfile']["tmp_name"]);
            if (! $tmp_upload_file)
                $form_errors .= i("7qmVxb|Unknown error during upl...");
            else {
                $_SESSION["io_file"] = $_FILES['userfile']["name"];
                $_SESSION["io_table"] = "efa2persons";
                if (! file_exists("../log/io"))
                    mkdir("../log/io");
                file_put_contents("../log/io/" . $_SESSION["io_file"], $tmp_upload_file);
                $records = $toolbox->read_csv_array("../log/io/" . $_SESSION["io_file"]);
                
                $import_check_info = (is_array($records)) ? count($records) . " " . i("zYFpuX|Records found.") .
                         "<br>" : i("nDkWmB|No records found.") . "<br>";
                $import_check_errors = "";
                $r = 0;
                foreach ($records as $record) {
                    // prepare information for record
                    $r ++;
                    $full_name = (isset($record["LastName"])) ? Efa_tables::virtual_full_name(
                            $record["FirstName"], $record["LastName"], $toolbox) : $record["Id"];
                    $import_check_prefix = i("cKcnOB|Check line") . " " . $r . ": " . $full_name;
                    // validate transaction for record
                    $validation1_result = $efa_record->check_unique_and_not_empty($record, "efa2persons", 
                            $mode);
                    if (strlen($validation1_result) > 0)
                        $import_check_errors .= $import_check_prefix . " - " . $validation1_result . ".<br>";
                    else {
                        $validation2_result = $efa_record->validate_record_APIv1v2("efa2persons", $record, 
                                $mode, $user_id);
                        if (is_array($validation2_result)) {
                            if ($validation2_result[1] && ! $validation2_result[2])
                                $import_check_errors .= $import_check_prefix . " - " .
                                         i(
                                                "dN1n95|The transaction cannot b...") .
                                         "<br>";
                            else
                                $import_check_info .= $import_check_prefix . " - ok.<br>";
                        } else
                            $import_check_errors .= $import_check_prefix . " - " . $validation2_result .
                                     ".<br>" . i("FmQZaR|Record:") . " " . json_encode($record) . "<br>";
                    }
                }
                // only move on, if import did not return an error.
                if (strlen($import_check_errors) == 0)
                    $todo = $done + 1;
                else
                    $form_errors .= $import_check_errors;
            }
        }
    } elseif ($done == 2) {
        //
        // step 2 import execution
        //
        $records = $toolbox->read_csv_array("../log/io/" . $_SESSION["io_file"]);
        $import_done_info = "";
        $r = 0;
        foreach ($records as $record) {
            // prepare information for record
            $r ++;
            $name_is_provided = (isset($record["LastName"]));
            $full_name = ($name_is_provided) ? Efa_tables::virtual_full_name($record["FirstName"], 
                    $record["LastName"], $toolbox) : $record["Id"];
            $import_done_prefix = i("RtlLSq|Execute line") . " " . $r . ": " . $full_name;
            // validate transaction for record, again. Things may have changed in the meanwhile.
            $validation1_result = $efa_record->check_unique_and_not_empty($record, "efa2persons", $mode);
            if (strlen($validation1_result) > 0)
                $import_done_errors .= $import_done_prefix . " - " . $validation1_result . ".<br>";
            else {
                $validation2_result = $efa_record->validate_record_APIv1v2("efa2persons", $record, $mode, 
                        $user_id);
                if (is_array($validation2_result)) {
                    if ($validation2_result[1] && ! $validation2_result[2])
                        $import_done_info .= $import_done_prefix . " - <b>" .
                                 i(
                                        "RqYBEi|The transaction cannot b...") .
                                 "</b><br>";
                    else {
                        // now execute, all checks again performed successfully, use the result of the
                        // validation, because this has the ecrid resolved.
                        $change_count = "0"; // for insert, will bei increased in register modification call
                        if ($mode == 2) {
                            $existing_record = $socket->find_record_matched("efa2persons", 
                                    ["ecrid" => $validation2_result[0]["ecrid"]
                                    ]);
                            $change_count = $existing_record["ChangeCount"];
                            // If only the Id was provided, remove the autogenerated and now empty
                            // "FirstLastName" field
                            if (! $name_is_provided)
                                unset($validation2_result[0]["FirstLastName"]);
                        }
                        $record_modified = Efa_tables::register_modification($validation2_result[0], time(), 
                                $change_count, Efa_record::$mode_name[$mode]);
                        $modification_result = $efa_record->modify_record("efa2persons", $record_modified, 
                                $mode, $user_id, false);
                        if (strlen($modification_result) == 0)
                            $import_done_info .= $import_done_prefix . " - " . i("O1BjoP|ok.") . "<br>";
                        else
                            $import_done_info .= $import_done_prefix . " - <b>" . $modification_result .
                                     "</b>.<br>";
                    }
                } else
                    $import_done_info .= $import_done_prefix . " - <b>" . $validation2_result . "</b>.<br>";
            }
        }
        
        unlink("../log/io/" . $_SESSION["io_file"]);
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
    $form_to_fill->select_options = [ "1=" . i("RUOkfa|create new"), "2=" . i("d7vLxb|modify")];
}

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
echo i("fU75vS| ** Import persons ** Da..."); 
if ($todo == 1) { // step 1. Texts for output
    echo i("POzcZe| ** File format and fiel...");
    echo $toolbox->form_errors_to_html($form_errors);
    echo $form_to_fill->get_html(true); // enable file upload
    echo $form_to_fill->get_help_html();
} elseif ($todo == 2) { // step 2. Texts for output
    echo i("0alDAs| ** The file upload and ...");
    // no form errors possible at this step. just a button clicked.
    echo $import_check_info;
    echo i("zmwOIb| ** In the next step, th...");
    // no form errors possible at this step. just a button clicked.
    echo $form_to_fill->get_html();
    echo $form_to_fill->get_help_html();
} elseif ($todo == 3) { // step 3. Texts for output
    echo i("02b3TQ| ** The file import was ...");
    echo "<p>" . $import_done_info . "</p><p>" . i("miz0SV|Done.") . "</p>";
}

// Help texts and page footer for output.
echo "</div>";
end_script();
