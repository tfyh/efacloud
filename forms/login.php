<?php
/**
 * The login form for all activites on this application except registration. Based on the Tfyh_form class, please
 * read instructions their to better understand this PHP-code part.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
// ===== page shall be available for anonymous users.
include_once "../classes/init.php";
include_once '../classes/tfyh_form.php';

$deeplink = "";
if (isset($_SESSION["getps"][$fs_id]["goto"]) && (strlen($_SESSION["getps"][$fs_id]["goto"]) > 0))
    $deeplink = $_SESSION["getps"][$fs_id]["goto"];
$use_as_role = "";
if (isset($_SESSION["getps"][$fs_id]["as"]) && (strlen($_SESSION["getps"][$fs_id]["as"]) > 0)) {
    $use_as_role = $_SESSION["getps"][$fs_id]["as"];
}

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = ($done == 0) ? 1 : $done;
$form_errors = "";
$form_result = "";
$form_layout = "../config/layouts/login";

// ======== try to login via a token.
// If a token was set, this will always exit either via redirect to the home.php or with an error display
if (isset($_SESSION["getps"][$fs_id]["token"])) {
    $plain_text = $toolbox->decode_login_token($_GET["token"]);
    if ($plain_text === false) {
        $form_errors .= "Das hat leider nicht geklappt. " .
                 "Der mitgelieferte login-Token ist nicht oder nicht mehr gültig.";
    } else {
        // plain_text contains: validity, user mail, deep link (optional), padding
        $user_mail = $plain_text[1];
        $user_to_login = $socket->find_record($toolbox->users->user_table_name, "EMail", $user_mail);
        if (! $user_to_login) {
            $form_errors .= "Das hat leider nicht geklappt. " . "Der Nutzer '" . $user_mail .
                     "' des login-Tokens ist nicht (mehr) registriert.";
        } else {
            // Verification successful. Refresh all user data.
            $toolbox->logger->log_init_login_error("login");
            $_SESSION["User"] = $user_to_login;
            $_SESSION["login_failures"] = 0;
            // redirect to user home page
            if (count($plain_text) < 4) // no deep link parameter
                header("Location: ../pages/home.php");
            else
                header("Location: ../" . $plain_text[2]);
            exit(0);
        }
    }
}

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
        // Step 1, basic verification, send token if necessary.
        $verified = false;
        $token_sent = false;
        $login_is_email = false;
        $login_is_id = false;
        $login_is_account = false;
        
        // user identification
        // ----------------------------------------------------------------------
        // Check the account information (EMail, account name or user ID) to identify user
        if (filter_var($entered_data["Account"], FILTER_VALIDATE_EMAIL) !== false) {
            // mail formatted ID
            $login_is_email = true;
            $user_to_login = $socket->find_record($toolbox->users->user_table_name, 
                    $toolbox->users->user_mail_field_name, $entered_data['Account']);
        } elseif (is_numeric($entered_data["Account"])) {
            // numeric ID
            $login_is_id = true;
            $user_to_login = $socket->find_record($toolbox->users->user_table_name, 
                    $toolbox->users->user_id_field_name, $entered_data['Account']);
        } else {
            // alphanumeric ID, but not e-mail address
            $login_is_account = true;
            $user_to_login = $socket->find_record($toolbox->users->user_table_name, 
                    $toolbox->users->user_account_field_name, $entered_data['Account']);
        }
        
        // check entered password or send token
        if ($user_to_login === false) {
            // user was not matched in data base
            $form_errors .= "Der Nutzer konnte nicht identifiziert werden.";
        } else {
            // user was retrieved from data base
            $passwort_hash = $user_to_login["Passwort_Hash"];
            // if no password hash is available, check with alternative authentication provider
            $auth_provider_class_file = "../authentication/auth_provider.php";
            if ((strlen($passwort_hash) <= 10) && file_exists($auth_provider_class_file)) {
                include_once $auth_provider_class_file;
                $auth_provider = new Auth_provider();
                $passwort_hash = $auth_provider->get_pwhash(
                        $user_to_login[$toolbox->users->user_id_field_name]);
            }
            if (strlen($entered_data["Passwort"]) > 0) {
                // password was provided
                if (strlen($passwort_hash) > 10)
                    // user has permanent password. Possibly provided by $auth_provider
                    $verified = password_verify($entered_data["Passwort"], $passwort_hash);
            } else {
                // no password was provided
                if (strlen($passwort_hash) > 10) {
                    // The user has defined a permanent password, then it must be used.
                    // He may reset this permanent password to get one-time session tokens.
                    $form_errors .= "Wenn ein permanentes Kennwort definiert ist, kann man nicht mit einem Einmalkennwort arbeiten. ";
                    $form_errors .= "Das permanente Kennwort kann <a href='../forms/reset_password.php'>HIER</a> gelöscht werden. ";
                } elseif ($login_is_id) {
                    // The user has not defined a permanent password, then she/he must not use the numeric ID
                    // as login.
                    $form_errors .= "Mit der Nutzernummer als Loginangabe kann kein Einmalkennwort angefordert werden. ";
                    $form_errors .= "Bitte die Mail-Adresse als Loginangabe nutzen. ";
                } elseif ($login_is_account && (! isset($user_to_login["EMail"]) ||
                         (filter_var($user_to_login["EMail"], FILTER_VALIDATE_EMAIL) === false))) {
                    // The user has not defined a permanent password, then she/he must have set an e-mail
                    // address.
                    $form_errors .= "Zu diesem Account ist keine e-Mail-Adresse hinterlegt. ";
                    $form_errors .= "Bitte die Mail-Adresse als Accountangabe nutzen. ";
                } else 
                    if (filter_var($entered_data["Account"], FILTER_VALIDATE_EMAIL) === false) {
                        // The user has not defined a permanent password, then she/he must use the
                        // e-mail address as lgoin.
                        $form_errors .= "Mit der Nutzernummer als Accountangabe kann kein Einmalkennwort angefordert werden. ";
                        $form_errors .= "Bitte die Mail-Adresse als Accountangabe nutzen. ";
                    } else {
                        // The user was appropriately identified and shall get a session token
                        $mail_mitglied = $user_to_login["EMail"];
                        if (strlen($mail_mitglied) < 3) {
                            // no e-mail address available. Should actually never happen.
                            $form_errors .= "Keine Mail für diesen Nutzer / diese Nutzerin hinterlegt, daher kein Versand eines Einmalkennwortes möglich.";
                        } else {
                            $mail_mitglied = $toolbox->strip_mail_prefix($mail_mitglied);
                            // user has no permanent password, send token.
                            $appUserID = $user_to_login[$toolbox->users->user_id_field_name];
                            include_once '../classes/tfyh_token_handler.php';
                            $token_handler = new Tfyh_token_Handler("../log/tokens.txt");
                            include_once '../classes/tfyh_mail_handler.php';
                            $mail_handler = new Tfyh_mail_handler($toolbox->config->get_cfg());
                            $user_is_anonym = (strcasecmp($user_to_login["Rolle"], "anonym") == 0);
                            $token = ($user_is_anonym) ? "" : $token_handler->get_new_token($appUserID, 
                                    $toolbox);
                            // Compile Mail to user.
                            $subject = "Einmalkennwort für " . $toolbox->config->app_name . "  '" . $token .
                                     "'";
                            $body .= "<p>Liebe/r " . $user_to_login["Vorname"] . " " .
                                     $user_to_login["Nachname"] . ",</p>";
                            // user with user rights !Anonym" shall not get a token
                            if (strcasecmp($user_to_login["Rolle"], "anonym") == 0) {
                                $body .= "<p>Die Registrierung muss vom Verwalter noch abgeschlossen werden. Erst danach kann " .
                                         " ein Einmalkennwort versendet werden.<p>";
                            } else {
                                // user shall get a token
                                $body .= "<p>Mit dem Einmalkennwort '" . $token .
                                         "' besteht die Möglichkeit sich für die nächsten " .
                                         strval($token_handler->token_validity_period / 60) .
                                         " Minuten in der Anwendung anzumelden. Danach ist es ungültig." .
                                         " Groß- oder Kleinschreibung spielt keine Rolle.<p>";
                            }
                            $body .= $mail_handler->mail_subscript;
                            $body .= "<p>PS: Im Nutzerprofil besteht die Möglichkeit, ein dauerhaftes Kennwort zu hinterlegen.<p>";
                            $body .= $mail_handler->mail_footer;
                            $send_success = $mail_handler->send_mail($mail_handler->system_mail_sender, 
                                    $mail_handler->system_mail_sender, $mail_mitglied, "", "", $subject, $body);
                            if ($send_success) {
                                $form_result .= "<b>Das Einmalkennwort wurde an '" . $mail_mitglied .
                                         "' versendet.</b>";
                                $toolbox->logger->log(0, $appUserID, 
                                        "Einmalkennwort an Nutzer versendet.");
                                $token_sent = true;
                            } else {
                                $form_errors .= "Das Einmalkennwort konnte nicht versendet werden. <br>";
                            }
                        }
                    }
            }
        }
        // user identification completed.
        // -----------------------------
        // check verification result and trigger next action.
        if ($verified == true) {
            // with password, verification finished. Refresh all user data.
            // Token verification see $done === 2
            $_SESSION["User"] = $socket->find_record($toolbox->users->user_table_name, 
                    $toolbox->users->user_id_field_name, $user_to_login[$toolbox->users->user_id_field_name]);
            // This exception is made in order to see the real user activities. Admin activities
            // are anyway loogged in detail, thus successful admin logins are deemed non critical.
            if ($user_to_login[$toolbox->users->user_id_field_name] != 1818)
                $toolbox->logger->log_init_login_error("login");
            if (isset($_SESSION["User"]["LastLogin"]))
                $socket->update_record($_SESSION["User"][$toolbox->users->user_id_field_name], 
                        $toolbox->users->user_table_name, 
                        ["ID" => $_SESSION["User"]["ID"],"LastLogin" => time()
                        ]);
                $todo = 3;
            $login_failures = 0;
            $_SESSION["login_failures"] = 0;
        } elseif ($token_sent == true) {
            // no password, token was sent. The token_handler controls the number of tokens per
            // user, to avoid bots generating tokens at random.
            $todo = 2;
            $login_failures = 0;
            $_SESSION["Registering_user"] = $user_to_login;
            $_SESSION["login_failures"] = 0;
        } else 
            if ($user_to_login !== false) {
                // no token, no verification, so a login failure. Increase wait time on login failures.
                if (isset($user_to_login[$toolbox->users->user_id_field_name]))
                    $appUserID = $user_to_login[$toolbox->users->user_id_field_name];
                else
                    $appUserID = 0;
                if (isset($_SESSION["login_failures"])) {
                    $login_failures = $_SESSION["login_failures"] + 1;
                    $toolbox->logger->log(1, $appUserID, 
                            "Falsches Kennwort beim login.");
                } else
                    $login_failures = 1;
                $_SESSION["login_failures"] = $login_failures;
                if ($login_failures > 0) {
                    $toolbox->load_throttle("errors/", $toolbox->config->settings_tfyh["init"]["max_errors_per_hour"]);
                    $form_errors .= "Fehler beim login.<br>Bereits " . $login_failures .
                             " Fehlversuche. Bitte noch einmal versuchen, " .
                             "aber mit jedem Versuch dauert es länger.</p>";
                    // try and eroor will become slower and slower.
                    sleep(2 * $login_failures);
                    $toolbox->logger->log(1, $appUserID, 
                            "Falsches Kennwort beim login.");
                    $toolbox->logger->log_init_login_error("error");
                }
            }
    } elseif ($done === 2) {
        // step 2: user has got a token mail, verify token.
        include_once '../classes/tfyh_token_handler.php';
        $token_handler = new Tfyh_token_Handler("../log/tokens.txt");
        $appUserID = $token_handler->get_user_and_update($entered_data["Token"]);
        if ($appUserID == - 1) {
            $form_errors .= "Das Einmalkennwort ist falsch oder abgelaufen. ";
            $form_errors .= "Ein Einmalkennwort kann jederzeit neu angefordert werden. Dazu einfach von vorne mit dem Einloggen beginnen.";
        } elseif ($appUserID == - 2) {
            $form_errors .= "Für diesen Nutzer sind zu viele Sitzungen offen.";
            $toolbox->logger->log(1, $appUserID, 
                    "Für diesen Nutzer sind zu viele Sitzungen offen.");
        } else {
            // login successful
            $user_to_login = $socket->find_record($toolbox->users->user_table_name, 
                    $toolbox->users->user_id_field_name, $appUserID);
            // transfer user now to session
            $_SESSION["User"] = $user_to_login;
            $toolbox->logger->log_init_login_error("login");
            $verified == true;
            $todo = 3;
        }
    }
    
    if ($todo === 3) {
        if (strlen($use_as_role) > 0) {
            if (! $menu->is_allowed_role_change($_SESSION["User"]["Rolle"], $use_as_role))
                $toolbox->display_error("Rolle nicht zulässig.", 
                        "Der Nutzer darf die Rolle " . $use_as_role . " nicht einnehmen.", 
                        $user_requested_file);
            else
                $_SESSION["User"]["Rolle"] = $use_as_role;
        }
        
        // step 3: user is verified.
        // Use this to trigger daily jobs. It will only be performed once per day, so performance
        // impact is low.
        include_once ("../classes/cron_jobs.php");
        Cron_jobs::run_daily_jobs($toolbox, $socket, $_SESSION["User"][$toolbox->users->user_id_field_name]);
        // now redirect to the deeplink or the users home page.
        if (strlen($deeplink) > 0)
            echo header("Location: ../" . str_replace("%2F", "/", $deeplink));
        else
            header("Location: ../pages/home.php");
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
?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Login für registrierte Nutzer</h3>
</div>

<div class="w3-container">
<?php

echo $toolbox->form_errors_to_html($form_errors);
echo $form_result;
echo $form_to_fill->get_html($fs_id);

// ======== start with the display of either the next form, or the error messages.
if ($todo == 1) { // step 1. 
    echo "<a href='../forms/reset_password.php'>Kennwort vergessen?</a>";
} elseif ($todo == 2) { // step 2. no special texts for output
} elseif ($todo == 3) { // step 3.
}

echo '<h5><br />Ausfüllhilfen</h5><ul>';
echo $form_to_fill->get_help_html();
echo "</ul>";
?></div><?php
end_script();


