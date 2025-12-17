<?php
/**
 * The form for upload of the club logo.
 * 
 * @author mgSoft
 */

// ===== special efacloud field idname conventions
$idnames = ["efa2autoincrement" => "Sequence","efa2boatstatus" => "BoatId","efa2clubwork" => "Id",
        "efa2crews" => "Id","efa2fahrtenabzeichen" => "PersonId","efa2messages" => "MessageId",
        "efa2sessiongroups" => "Id","efa2statistics" => "Id","efa2status" => "Id","efa2waters" => "Id"
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
$form_layout = "../config/layouts/logo_hochladen";

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
        // step 1 form was filled. Values were valid
        if (strlen($_FILES['userfile']["name"]) < 1) {
            // Special case upload error. Userfile can not be checked after
            // being entered, must be checked
            // after upload was tried.
            $form_errors .= i("WFA42N|No file specified. pleas...");
        } else {
            $logo_file = "../resources/logo_verein.png";
            $tmp_upload_file = file_get_contents($_FILES['userfile']["tmp_name"]);
            if (! $tmp_upload_file)
                $form_errors .= i("R64uhq|Unknown error during upl...");
            else {
                $store_result = file_put_contents($logo_file, $tmp_upload_file);
                if ($store_result !== false)
                    $todo = $done + 1;
                else
                    $form_errors .= i(
                            "2JgEKN|An error occurred when s...", 
                            $tmp_upload_file, $logo_file);
            }
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
echo i("HQqMpM| ** Upload club logo ** ...");
if ($todo == 1) { // step 1. Texts for output
    echo $toolbox->form_errors_to_html($form_errors);
    echo $form_to_fill->get_html(true); // enable file upload
} elseif ($todo == 2) { // step 2. Texts for output
    echo "<p>" . i("AE2Uq5|The file upload was succ...") .
             "<br><a href='../forms/logo_hochladen.php'>" . i("PfOrhR|Change club logo") . "</a></p>";
}
// page footer for output.
echo "</div>"; 
end_script();
