<?php
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

$only_pw = (isset($_SESSION["getps"][$fs_id]["pw"])) ? intval($_SESSION["getps"][$fs_id]["pw"]) : 0;
// ===== a dummy for a password which is not the right one. Must nevertheless be a valid one to
// pass all checks further down, except for the "only passwort case, where a password entry is forced.
$keep_password = ($only_pw == 1) ? "" : "keuk3HVpxHASrcRn6Mpf";

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = ($done == 0) ? 1 : $done;
$form_errors = "";
$form_layout = ($only_pw == 1) ? "../config/layouts/pw_aendern" : "../config/layouts/profil_aendern";

// ======== Start with form filled in last step: check of the entered values.
if ($done > 0) {
    $form_filled = new Tfyh_form($form_layout, $socket, $toolbox, $done, $fs_id);
    $form_filled->read_entered();
    $entered_data = $form_filled->get_entered();
    if (strlen($entered_data["Passwort"]) == 0) {
        $form_filled->preset_value("Passwort", $keep_password);
        $form_filled->preset_value("Passwort_Wdh", $keep_password);
    }
    $form_errors = $form_filled->check_validity();
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This avoids any change, if form errors occured.
    } elseif ($done == 1) {
        foreach ($entered_data as $key => $value)
            $_SESSION["User"][$key] = $value;
        // Passwort geändert.
        if (! strcmp($entered_data['Passwort'], $keep_password)) {
            // -------------------------------
            // passwords must be identical.
            if ($entered_data['Passwort'] != $entered_data['Passwort_Wdh']) {
                $form_errors .= i("scIPSc|The passwords must match...") .
                         "<br>";
                $form_filled->preset_value("Passwort", $keep_password);
                $form_filled->preset_value("Passwort_Wdh", $keep_password);
            }
            // password hash will be changed later, if this form has no errors.
        }
        // if it is set, EMail must be unique. It will not be set in case this is a password change.
        if (isset($entered_data['EMail']) && (strlen($entered_data['EMail']) > 0)) {
            $mail_address_used = $socket->find_record_matched($toolbox->users->user_table_name, 
                    ["EMail" => $entered_data['EMail']
                    ], true);
            if (($mail_address_used !== false) && ($mail_address_used[$toolbox->users->user_id_field_name] !==
                     $_SESSION["User"][$toolbox->users->user_id_field_name])) {
                $form_errors .= i(
                        'SC6SXl|The email address °%1° i...', 
                        $entered_data["EMail"], $mail_address_used["Vorname"], $mail_address_used["Nachname"]) .
                 " ";
    }
}
// continue, if no errors were detected
if (strlen($form_errors) == 0)
    $todo = $done + 1;
}

// change of user data, performed, data check was successful.
if ($todo == 2) {
// create change statement and Change record. The user to update is always the verified user
// himself.
$user_to_update = $socket->find_record_matched($toolbox->users->user_table_name, 
        [$toolbox->users->user_id_field_name => $_SESSION["User"][$toolbox->users->user_id_field_name]
        ], false);
$record["ID"] = $user_to_update["ID"];
$info = "";
foreach ($user_to_update as $key => $value) {
    $value_is_password = (strcasecmp($key, "Passwort_Hash") == 0);
    $value_is_dummy_password = (strcmp($keep_password, $entered_data["Passwort"]) === 0);
    $user_has_password = isset($_SESSION["User"]["Passwort"]);
    if ($value_is_password && $user_has_password && ! $value_is_dummy_password) {
        $info .= i("655rFb|The password has been ch...") . "<br>";
        $record[$key] = password_hash($entered_data['Passwort'], PASSWORD_DEFAULT);
    }
    $value_is_changed = isset($entered_data[$key]) &&
             (strcmp($user_to_update[$key], $entered_data[$key]) !== 0);
    $value_is_lastmodified = (strcasecmp($key, "LastModified") === 0);
    $log_modification = $value_is_changed && ! $value_is_password && ! $value_is_lastmodified;
    if (isset($entered_data[$key]) && $log_modification) {
        $info .= i("GsvRx8|%1 was changed from °%2°...", $key, $user_to_update[$key], $entered_data[$key]) .
                 "<br>";
        $record[$key] = $entered_data[$key];
    }
}
// last check of new record. $record is not complete, only changes, check entered data.
$form_errors .= $toolbox->users->check_user_profile($entered_data);
// update user record.
if (! $form_errors) {
    $record["LastModified"] = strval(time()) . "000";
    $form_errors = $socket->update_record($_SESSION["User"][$toolbox->users->user_id_field_name], 
            $toolbox->users->user_table_name, $record, false);
    
    // start user feedback creation
    require_once '../classes/tfyh_mail_handler.php';
    $mail_handler = new Tfyh_mail_handler($toolbox->config->get_cfg());
    $info_html = '<p><b>Daten geändert.</b><br/>".i("Folgende Änderungen wurden vorgenommen:")."<br>' . $info .
             '</p><p><a href = "../pages/mein_profil.php">".i("Zurück zum Profil.")."</a></p>';
    $toolbox->logger->log(0, intval($_SESSION["User"][$toolbox->users->user_id_field_name]), 
            i("a8gRBK|Profile changed."));
    
    // if only other data were changed, create a change note to the "Schriftwart"
    $mail_handler->send_mail($mail_handler->system_mail_sender, $mail_handler->system_mail_sender, 
            $mail_handler->mail_schriftwart, "", "", 
            i("e1szHW|Change of profile data a...", 
                    $_SESSION["User"][$toolbox->users->user_id_field_name]), 
            i("cFEi41|The following changes ha...") . "<br>" . $info);
} else
    $todo = 1;
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
// retrieve current data. The verified user is the user to update.
$user_to_update = $socket->find_record_matched($toolbox->users->user_table_name, 
        ["ID" => $_SESSION["User"]["ID"]
        ], false);
// preset password to $keep_password, to know whether it was changed. $keep_password is a
// valid password, to survice the checks applied in the second step.
$user_to_update["Passwort"] = $keep_password;
$user_to_update["Passwort_Wdh"] = $keep_password;
$form_to_fill->preset_values($user_to_update);
}
}

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
echo i("xnSRHk| ** Change personal prof...");
echo $toolbox->form_errors_to_html($form_errors);
if ($todo < 2) { // step 1. No special texts for output
    echo $form_to_fill->get_html();
    echo $form_to_fill->get_help_html();
} else
    echo $info;

echo i("qraFMw|</div>");
end_script();
