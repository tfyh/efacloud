<?php
/**
 * The login form for all activites on this application except registration. Based on the Form class, please
 * read instructions their to better understand this PHP-code part.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once '../classes/form.php';

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = $done;
$form_errors = "";
$form_result = "";
$form_layout = "../config/layouts/reset_password";

// ======== start with form filled in last step: check of the entered values.
if ($done > 0) {
    
    $form_filled = new Form($form_layout, $socket, $toolbox, $toolbox->users->user_table_name, $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    $forms[$done] = $form_filled;
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This only prevents any logic to apply, if form errors
        // occured.
    } elseif ($done == 1) {
        // user identification
        // ----------------------------------------------------------------------
        // Check the account infomartion (EMail or Mitgliedsnummer) identify user
        if (filter_var($entered_data["Account"], FILTER_VALIDATE_EMAIL) !== false)
            $user_to_update = $socket->find_record($toolbox->users->user_table_name, "EMail", 
                    $entered_data['Account']);
        else
            $form_errors .= "Das Löschen des permanenten Kennworts ist nur mit der Angabe " .
                     "einer hinterlegten E-Mail-Adresse möglich. ";
        
        // check entered password or send token
        // ------------------------------------
        if (! $user_to_update) {
            // user was not matched in data base
            $form_errors .= "Der Nutzer konnte nicht identifiziert werden.";
        } else {
            // user was matched in data base. Send token.
            // user has no permanent password, send token.
            $Mitgliedsnummer = $user_to_update[$toolbox->users->user_id_field_name];
            $mail_mitglied = $toolbox->strip_mail_prefix($user_to_update["EMail"]);
            include_once '../classes/token_handler.php';
            $token_handler = new Token_Handler("../log/tokens.txt");
            include_once '../classes/mail_handler.php';
            $mail_handler = new Mail_Handler($toolbox->config->get_cfg());
            $token = $token_handler->get_new_token($Mitgliedsnummer, $toolbox);
            // Compile Mail to user.
            $subject = "Einmalkennwort für das Löschen des Passworts der Anwendung '" . $token . "'";
            $body = "<p>Hallo " . $user_to_update["Vorname"] . " " . $user_to_update["Nachname"] . ",</p>";
            $body .= "<p>Mit dem Einmalkennwort '" . $token . "' kann für die nächsten ";
            $body .= strval($token_handler->token_validity_period / 60) .
                     " Minuten das permanente Passwort der Anwendung gelöscht werden.";
            $body .= " Ob die Buchstaben groß oder klein geschrieben werden, spielt keine Rolle.<p>";
            $body .= "<p>Danach ist die ANmeldung per Einmalkennwort wieder möglich und damit auch " .
                     "sich ein neues permanentes Passwort einzurichten.<p>";
            $body .= $mail_handler->mail_subscript . $mail_handler->mail_footer;
            $send_success = $mail_handler->send_mail($mail_handler->system_mail_sender, 
                    $mail_handler->system_mail_sender, $mail_mitglied, "", "", $subject, $body);
            if ($send_success) {
                $form_result .= "<b>Das Einmalkennwort wurde an '" . $mail_mitglied . "' versendet.</b>";
                $toolbox->logger->log(Tfyh_logger::$TYPE_DONE, $Mitgliedsnummer, 
                        "Einmalkennwort zum Passwortrücksetzen versendet.");
                $token_sent = true;
                $_SESSION["Registering_user"] = $user_to_update;
                $todo = $done + 1;
            } else {
                $form_errors .= "Das Einmalkennwort konnte nicht versendet werden. <br>";
                // NUR ZU TESTZWECKEN
                // $form_errors .= " es ist " . $token . "<br>";
                // $todo = 2;
                // NUR ZU TESTZWECKEN
            }
        }
    } elseif ($done === 2) {
        // user has no permanent password, verify token.
        include_once '../classes/token_handler.php';
        $token_handler = new Token_Handler("../log/tokens.txt");
        $app_user_id = $token_handler->get_user_and_update($entered_data["Token"]);
        if ($app_user_id == - 1)
            $form_errors .= "Das Einmalkennwort ist falsch oder abgelaufen.";
            elseif ($app_user_id == - 2)
            $form_errors .= "Für diesen Nutzer sind zu viele Sitzungen offen.";
        else {
            // password changes will change the last modified time stamp, because they have
            // no impact on the users data
            $sql_cmd = "UPDATE `".$toolbox->users->user_table_name."` SET `Passwort_Hash` = '' " .
                    "WHERE `".$toolbox->users->user_table_name."`.`".$toolbox->users->user_id_field_name."` = " . $app_user_id;
            if ($socket->query($sql_cmd) === false)
                $form_errors .= "Das Löschen des Passworts in der Datenbank ist fehlgeschlagen.";
            else
                $todo = $done + 1;
        }
    }
}

// ==== continue with the definition and eventually initialization of form to fill in this step
if (($done == 0) || ($todo !== $form_filled->get_index())) {
    // use a new form for the very first form display or the next step.
    if ($done == 0)
        $todo = 1;
    $form_to_fill = new Form($form_layout, $socket, $toolbox, $toolbox->users->user_table_name, $todo, $fs_id);
} else {
    // or reuse the 'done' form, if validation failed.
    $form_to_fill = $form_filled;
}

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
?>
<!-- START OF grid_9 container -->
<div class="w3-container">
	<h2>Passwort löschen</h2>
	<h3>Das permanente Passwort für die Anwendung löschen</h3>
</div>
<div class="w3-container">

<?php

echo $form_result;
echo $toolbox->form_errors_to_html($form_errors);
echo $form_to_fill->get_html($fs_id);

// ======== start with the display of either the next form, or the error messages.
if ($todo == 1) { // step 1. no special texts for output
    ?>
		<p>Hier kann das permanente Passwort für die Anwendung gelöscht
		werden. Danach ist die Anmeldung per Einmalkennwort wieder möglich und
		ein neues permanentes Passwort kann so hinterlegt werden.</p>

<?php
} elseif ($todo == 2) { // step 2. No special texts
} elseif ($todo == 3) { // step 3.
    ?>
	<p>
		Nachdem nun das permanente Passwort gelöscht ist, ist <a
			href="../forms/login.php">HIER</a> die Anmeldung wieder mit einem Einmalkennwort
		möglich. Ein neues permanentes Passwort setzt man unter "Mein
		Profil".
	</p>
<?php
}

echo '<h5><br />Ausfüllhilfen</h5><ul>';
echo $form_to_fill->get_help_html();
echo "</ul>";
?></div><?php
end_script();

