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
include_once '../classes/efa_uuids.php';
$efa_uuids = new Efa_uuids($toolbox, $socket);

$tmp_upload_file = "";

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = ($done == 0) ? 1 : $done;
$form_errors = "";
$form_layout = "../config/layouts/fahrten_importieren";

// ======== Start with form filled in last step: check of the entered values.
if ($done > 0) {
    $form_filled = new Tfyh_form($form_layout, $socket, $toolbox, $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    
    include_once '../classes/efa_record.php';
    $efa_record = new Efa_record($toolbox, $socket);
    $valid_records = array();
    $user_id = $toolbox->users->session_user["@id"];
    
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
            $form_errors .= i("QEeiO2|No file specified. pleas...");
        } else {
            $tmp_upload_file = file_get_contents($_FILES['userfile']["tmp_name"]);
            if (! $tmp_upload_file)
                $form_errors .= i("RAACdM|Unknown error during upl...");
            else {
                $_SESSION["io_file"] = $_FILES['userfile']["name"];
                $_SESSION["io_table"] = "efa2logbook";
                if (! file_exists("../log/io"))
                    mkdir("../log/io");
                file_put_contents("../log/io/" . $_SESSION["io_file"], $tmp_upload_file);
                // check for duplicate header fields
                $header = explode("\n", $tmp_upload_file, 2)[0];
                $fields = [];
                foreach (explode(";", $header) as $fieldname)
                    $fields[$fieldname] = (isset($fields[$fieldname])) ? $fields[$fieldname] + 1 : 1;
                foreach ($fields as $name => $count)
                    if ($count > 1)
                        $import_check_errors .= i("o7TETm|The following data field...") . $name . "<br>";
                $records = Tfyh_toolbox::static_read_csv_array("../log/io/" . $_SESSION["io_file"]);
                // now check each record
                $import_check_info = "";
                $import_check_errors = "";
                $r = 0;
                foreach ($records as $record) {
                    // perf_log("Fahrten importieren, prüfen, record #" . $r);
                    // check header once
                    if ($r == 0) {
                        $mismatching_names = "";
                        $field_names_table = $socket->get_column_names("efa2logbook");
                        foreach ($record as $key => $value)
                            if (! in_array($key, $field_names_table))
                                $mismatching_names .= $key . ",";
                        if (strlen($mismatching_names) > 0)
                            $import_check_errors .= i("qn2cjQ|The following data field...", 
                                    $mismatching_names) . "<br>";
                    }
                    // check records.
                    $record_resolved = $efa_uuids->resolve_session_record($record);
                    // perf_log("Fahrten importieren, prüfen, resolved #" . $r);
                    $r ++;
                    $key_str = i("xhJ9cc|Logbook %1, Trip #%2", $record["Logbookname"], $record["EntryId"]);
                    $import_check_prefix = i("42jTgj|Check line") . " " . $r . ": " . $key_str;
                    $data_key = Efa_tables::get_data_key("efa2logbook", $record);
                    $existing_session = $socket->find_record_matched("efa2logbook", $data_key);
                    // perf_log("Fahrten importieren, prüfen, retrieved #" . $r);
                    $mode = ($existing_session == false) ? 1 : 2;
                    $mode_explanation = ($mode == 1) ? i("rmbd4P|The trip is added.") : i(
                            "AyanBa|The trip is updated.");
                    $record_resolved = $efa_record->map_and_remove_extra_name_fields($record_resolved, 
                            "efa2logbook");
                    $validation1_result = $efa_record->check_unique_and_not_empty($record_resolved, 
                            "efa2logbook", $mode);
                    if (strlen($validation1_result) > 0)
                        $import_done_info .= $import_check_prefix . " - $validation1_result.<br>";
                    else {
                        // perf_log("Fahrten importieren, Prüfschritt 2");
                        $validation2_result = $efa_record->validate_record_APIv1v2("efa2logbook", 
                                $record_resolved, $mode, $user_id);
                        if (is_array($validation2_result)) {
                            if ($validation2_result[1] && ! $validation2_result[2]) {
                                $import_check_info .= $import_check_prefix . " - " .
                                         i("ZF44WW|The trip already exists,...") . "<br>";
                            } else
                                $import_check_info .= $import_check_prefix . " - $mode_explanation<br>";
                        } else
                            $import_check_errors .= $import_check_prefix . " - " . $validation2_result .
                                     ".<br>";
                    }
                }
                
                // only move on, if import did not return an error.
                if (strlen($import_check_errors) == 0)
                    $todo = $done + 1;
                else
                    $form_errors .= $import_check_errors;
                // perf_log("Fahrten importieren, prüfen, Abschluss");
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
            // perf_log("Fahrten importieren, record #" . $r);
            $record_resolved = $efa_uuids->resolve_session_record($record);
            if (strlen($record_resolved["EndDate"]) == 0)
                unset($record_resolved["EndDate"]);
            $r ++;
            $key_str = $record["Logbookname"] . ": " . $record["EntryId"];
            $import_done_prefix = i("YEU1wy|Load row") . " " . $r . ": " . $key_str;
            $data_key = Efa_tables::get_data_key("efa2logbook", $record);
            $existing_session = $socket->find_record_matched("efa2logbook", $data_key);
            $mode = ($existing_session == false) ? 1 : 2;
            // perf_log("Fahrten importieren, Modus = $mode");
            $mode_explanation = ($mode == 1) ? i("thc1PS|The trip has been added.") : i(
                    "KwSMMI|The trip has been update...");
            $record_resolved = $efa_record->map_and_remove_extra_name_fields($record_resolved, "efa2logbook");
            $validation1_result = $efa_record->check_unique_and_not_empty($record_resolved, "efa2logbook", 
                    $mode);
            if (strlen($validation1_result) > 0)
                $import_done_info .= $import_done_prefix . " - $validation1_result.<br>";
            else {
                // perf_log("Fahrten importieren, Importschritt 2");
                $validation2_result = $efa_record->validate_record_APIv1v2("efa2logbook", $record_resolved, 
                        $mode, $user_id);
                if (! is_array($validation2_result)) {
                    $import_done_info .= $import_done_prefix . " - $validation2_result.<br>";
                } else {
                    if ($validation2_result[1] && ! $validation2_result[2])
                        $import_done_info .= $import_done_prefix . " - " .
                                 i("Aujsqx|The trip already exists,...") . "<br>";
                    else {
                        $change_count = (isset($record["ChangeCount"])) ? intval($record["ChangeCount"]) : 1;
                        $prepared_record = Efa_tables::register_modification($validation2_result[0], time(), 
                                $change_count, Efa_record::$mode_name[$mode]);
                        if (isset($prepared_record["EndDate"]) && (strlen($prepared_record["EndDate"]) < 5))
                            unset($prepared_record["EndDate"]);
                        $modification_result = $efa_record->modify_record("efa2logbook", $prepared_record, 
                                $mode, $user_id, false);
                        if (strlen($modification_result) == 0)
                            $import_done_info .= $import_done_prefix . " - $mode_explanation.<br>";
                        else
                            $import_done_info .= $import_done_prefix . " - " . $modification_result . "<br>";
                    }
                }
                // perf_log("Fahrten importieren, Abschluss");
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
}

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
echo i("atUZOz| ** Import trips ** Here...");
if ($todo == 1) { // step 1. Texts for output
    echo i("JTl0ci| ** File format and fiel...");
    echo $toolbox->form_errors_to_html($form_errors);
    echo $form_to_fill->get_html(true); // enable file upload
    echo $form_to_fill->get_help_html();
} elseif ($todo == 2) { // step 2. Texts for output
    echo i("7MLPuQ| ** The file upload and ...");
    // no form errors possible at this step. just a button clicked.
    echo $import_check_info;
    echo i("0AKx35| ** In the next step, th...");
    // no form errors possible at this step. just a button clicked.
    echo $form_to_fill->get_html(false);
    echo $form_to_fill->get_help_html();
} elseif ($todo == 3) { // step 3. Texts for output
    echo i("x1uDKx| ** The file import was ...");
    echo "<p>" . $import_done_info . "</p>";
}

// Help texts and page footer for output.
echo "</div>";
end_script();
