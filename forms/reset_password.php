<?php
/**
 * The form to reset a password. Based on the Tfyh_form class, please read instructions their to better
 * understand this PHP-code part.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once '../classes/tfyh_form.php';

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = ($done == 0) ? 1 : $done;
$form_errors = "";
$form_result = "";
$form_layout = "../config/layouts/reset_password";

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
        // user identification
        // ----------------------------------------------------------------------
        // Check the account infomartion (EMail or Mitgliedsnummer) identify user
        if (filter_var($entered_data["Account"], FILTER_VALIDATE_EMAIL) !== false)
            $user_to_update = $socket->find_record($toolbox->users->user_table_name, "EMail", 
                    $entered_data['Account']);
        else
            $form_errors .= i("sOovvb|Deleting the permanent p...") . " ";
        
        // check entered password or send token
        // ------------------------------------
        if (! $user_to_update) {
            // user was not matched in data base
            $form_errors .= i("ZQSvHw|The user could not be id...");
        } else {
            // user was matched in data base. Send token.
            // user has no permanent password, send token.
            $Mitgliedsnummer = $user_to_update[$toolbox->users->user_id_field_name];
            $mail_mitglied = $toolbox->strip_mail_prefix($user_to_update["EMail"]);
            include_once '../classes/tfyh_token_handler.php';
            $token_handler = new Tfyh_token_Handler("../log/tokens.txt");
            include_once '../classes/tfyh_mail_handler.php';
            $mail_handler = new Tfyh_mail_Handler($toolbox->config->get_cfg());
            $token = $token_handler->get_new_token($Mitgliedsnummer, $toolbox);
            // Compile Mail to user.
            $subject = i("SUuG3E|One-time password for de...", $token);
            $body = "<p>" . i("dF0DWJ|Hello %1 %2,", $user_to_update["Vorname"], $user_to_update["Nachname"]) .
                     "</p>";
            $body .= "<p>" . i("Nz8G7x|The one-time password °%...", $token, 
                    strval($token_handler->token_validity_period / 60));
            ;
            $body .= " " . i("43SSV8|It does not matter wheth...") . "<p>";
            $body .= "<p>" . i("JmUHGQ|Afterwards, logging in w...") . "<p>";
            $body .= $mail_handler->mail_subscript . $mail_handler->mail_footer;
            $send_success = $mail_handler->send_mail($mail_handler->system_mail_sender, 
                    $mail_handler->system_mail_sender, $mail_mitglied, "", "", $subject, $body);
            if ($send_success) {
                $form_result .= "<b>" . i("PeJo45|The one-time password wa...", $mail_mitglied) . "</b>";
                $toolbox->logger->log(0, $Mitgliedsnummer, i("7G15iQ|One-time password for pa..."));
                $token_sent = true;
                $_SESSION["Registering_user"] = $user_to_update;
                $todo = $done + 1;
            } else {
                $form_errors .= i("ULBtgs|The one-time password co...") . " <br>";
                // NUR ZU TESTZWECKEN
                // $form_errors .= " es ist " . $token . "<br>";
                // $todo = 2;
                // NUR ZU TESTZWECKEN
            }
        }
    } elseif ($done === 2) {
        // user has no permanent password, verify token.
        include_once '../classes/tfyh_token_handler.php';
        $token_handler = new Tfyh_token_handler("../log/tokens.txt");
        $app_user_id = $token_handler->get_user_and_update($entered_data["Token"]);
        if ($app_user_id == - 1)
            $form_errors .= i("K7CeYe|The one-time password is...");
        elseif ($app_user_id == - 2)
            $form_errors .= i("MMoUVY|Too many sessions open f...");
        else {
            // password changes will change the last modified time stamp, because they have
            // no impact on the users data
            $sql_cmd = "UPDATE `" . $toolbox->users->user_table_name . "` SET `Passwort_Hash` = '' " .
                     "WHERE `" . $toolbox->users->user_table_name . "`.`" . $toolbox->users->user_id_field_name .
                     "` = " . $app_user_id;
            if ($socket->query($sql_cmd) === false)
                $form_errors .= i("ceZU15|The deletion of the pass...");
            else
                $todo = $done + 1;
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
echo i("HKR5lY| ** Delete password ** D...");
echo $form_result;
echo $toolbox->form_errors_to_html($form_errors);
echo $form_to_fill->get_html();

// ======== start with the display of either the next form, or the error messages.
if ($todo == 1) { // step 1. no special texts for output
} elseif ($todo == 2) { // step 2. No special texts
} elseif ($todo == 3) { // step 3.
    echo i("nO9bzi| ** After deleting the p...");
}

echo $form_to_fill->get_help_html();
echo i("0Wguyh|</div>");
end_script();

