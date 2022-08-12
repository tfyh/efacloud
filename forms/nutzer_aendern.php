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

// ===== a dummy for a password which is not the right one. Must nevertheless be a valid one to
// pass all checks further down.
$keep_password = "keuk3HVpxHASrcRn6Mpf";
$new_user_indicator_password_hash = "aBN6HEzAH8pP83etSIAxWA28eSze";

// This page requires an id to be set for the user to update. If not set, or the id is 0, a new user will be
// created.
$is_new_user = false;
if (isset($_SESSION["getps"][$fs_id]["id"]) && (intval($_SESSION["getps"][$fs_id]["id"]) > 0))
    $id_to_update = intval($_SESSION["getps"][$fs_id]["id"]);
else {
    $is_new_user = true;
    $default_email = "PLEASE.CHANGE_@_THIS.ADDRESS.ORG";
    $empty_new_user = $socket->find_record_matched($toolbox->users->user_table_name, 
            ["EMail" => $default_email
            ]);
    if ($empty_new_user === false) {
        $user_to_add["Vorname"] = "Vorname";
        $user_to_add["Nachname"] = "Nachname";
        $user_to_add["EMail"] = $default_email;
        $user_to_add["Passwort_Hash"] = $new_user_indicator_password_hash;
        $efaCloudUserID = $_SESSION["User"][$toolbox->users->user_id_field_name];
        $user_to_add["LastModified"] = strval(time()) . "000";
        $id_to_update = $socket->insert_into($efaCloudUserID, $toolbox->users->user_table_name, $user_to_add);
    } else
        $id_to_update = $empty_new_user["ID"];
    $_SESSION["getps"][$fs_id]["id"] = $id_to_update;
}
$user_to_update = $socket->find_record_matched($toolbox->users->user_table_name, 
        ["ID" => $id_to_update
        ]);

if ($user_to_update === false)
    $toolbox->display_error("Nicht gefunden.", 
            "Der Nutzerdatensatz zur ID '" . $id_to_update . "' konnte nicht gefunden werden.", 
            $user_requested_file);
$user_name_display = $user_to_update["Vorname"] . " " . $user_to_update["Nachname"];

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = ($done == 0) ? 1 : $done;
$form_errors = "";
$form_layout = "../config/layouts/nutzer_aendern";

// ======== Start with form filled in last step: check of the entered values.
if ($done > 0) {
    $form_filled = new Tfyh_form($form_layout, $socket, $toolbox, $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    if (strlen($entered_data["Passwort"]) == 0) {
        $form_filled->preset_value("Passwort", $keep_password);
        $form_filled->preset_value("Passwort_Wdh", $keep_password);
    }
    if (isset($entered_data["efaAdminName"]))
        $entered_data["efaAdminName"] = strtolower($entered_data["efaAdminName"]);
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This avoids any change, if form errors occured.
    } elseif ($done == 1) {
        // get user data stored
        $nutzer_to_update_after = [];
        foreach ($user_to_update as $key => $value)
            $nutzer_to_update_after[$key] = $value;
        // Password was changed, check identity of password and repetition
        if (! strcmp($entered_data['Passwort'], $keep_password)) {
            // -------------------------------
            // password and repetition must be identical.
            if ($entered_data['Passwort'] != $entered_data['Passwort_Wdh']) {
                $form_errors .= "Die Passwörter müssen übereinstimmen. " .
                         "Dein Passwort wird nicht geändert.<br>";
                $form_filled->preset_value("Passwort", $keep_password);
                $form_filled->preset_value("Passwort_Wdh", $keep_password);
            }
        }
        
        // efaCloudUserID must be an integer
        $efaclouduserid_ok = true;
        $c = 0;
        $allowed_chars = "0123456789";
        while ($efaclouduserid_ok && ($c < strlen($entered_data['efaCloudUserID']))) {
            $efaclouduserid_char = substr($entered_data['efaCloudUserID'], $c, 1);
            if (strpos($allowed_chars, $efaclouduserid_char) === false)
                $efaclouduserid_ok = false;
            $c ++;
        }
        if (! $efaclouduserid_ok)
            $form_errors .= "Die efaCloudUserID muss eine ganze Zahl sein.<br>";
        // efaCloudUserID must be in the range 0 to 1,000,000,000
        if ((intval($entered_data['efaCloudUserID']) <= 0) ||
                 (intval($entered_data['efaCloudUserID']) >= 1000000000))
            $form_errors .= "Die efaCloudUserID muss größer als 0 und kleiner als 1.000.000.000 sein.<br>";
        // efaAdminName must be 4 to 10 characters
        if ((strlen($entered_data['efaAdminName']) < 4) || (strlen($entered_data['efaAdminName']) > 10))
            $form_errors .= "efaAdminName muss zwischen 4 und 10 Zeichen lang sein.<br>";
        // efaAdminName must be lower case characters, without blanks etc
        $admin_name_ok = true;
        $c = 0;
        $allowed_chars = "_0123456789abcdefghijklmnopqrstuvwxyz";
        while ($admin_name_ok && ($c < strlen($entered_data['efaAdminName']))) {
            $admin_char = substr($entered_data['efaAdminName'], $c, 1);
            if (strpos($allowed_chars, $admin_char) === false)
                $admin_name_ok = false;
            $c ++;
        }
        if (! $admin_name_ok)
            $form_errors .= "efaAdminName darf nur Zeichen a-z, 0-9 und '_' enthalten. Keine Großbuchstaben, keine Sonderzeichen, keine Leerzeichen.<br>";
        
        // EMail must be unique.
        $mail_address_used = $socket->find_record_matched($toolbox->users->user_table_name, 
                ["EMail",$entered_data['EMail']
                ], true);
        if (($mail_address_used !== false) && ($mail_address_used[$toolbox->users->user_id_field_name] !==
                 $user_to_update[$toolbox->users->user_id_field_name])) {
            $form_errors .= 'Die E-Mail-Adresse "' . $entered_data['EMail'];
            $form_errors .= '" ist bereits belegt von ' . $mail_address_used["Vorname"] . " " .
                     $mail_address_used["Nachname"] .
                     '. Deine E-Mail-Adresse kann daher nicht geändert werden. ';
        }
        // now copy changed values, except password (will be done later)
        foreach ($entered_data as $key => $value) {
            if (strcasecmp($key, "Passwort") === 0) {
                $form_errors .= $toolbox->check_password($value);
            } else
                $nutzer_to_update_after[$key] = $value;
        }
        
        // check $nutzer_to_update_after whether all values match validity criteria
        $info = "";
        $invalid = $toolbox->users->check_user_profile($nutzer_to_update_after);
        if ($invalid)
            $form_errors .= "Fehler bei der Überprüfung der Daten: " . $invalid;
        // set password to its hash value.
        elseif (strcmp($keep_password, $entered_data["Passwort"]) !== 0)
            if ((strcasecmp($user_to_update["Rolle"], "bths") == 0) ||
                     (strcasecmp($user_to_update["Passwort_Hash"], $new_user_indicator_password_hash) == 0))
                $nutzer_to_update_after["Passwort_Hash"] = password_hash($entered_data['Passwort'], 
                        PASSWORD_DEFAULT);
            else
                $info = "Das Kennwort kann nur für neue Nutzer und Nutzer mit der Rolle 'bths' gesetzt werden.<br>";
        
        // continue, if no errors were detected
        if (strlen($form_errors) == 0) {
            $todo = $done + 1;
            $changed = false;
            $record["ID"] = $id_to_update;
            foreach ($user_to_update as $key => $value) {
                if (isset($nutzer_to_update_after[$key]) && (strcmp($user_to_update[$key], 
                        $nutzer_to_update_after[$key]) !== 0) && (strcasecmp($key, "LastModified") !== 0)) {
                    $changed = true;
                    $value_is_password = (strcmp($key, "Passwort_Hash") == 0);
                    if ($value_is_password) {
                        $info .= "Das Kennwort wurde geändert.<br>";
                        $record[$key] = password_hash($entered_data['Passwort'], PASSWORD_DEFAULT);
                    } else {
                        $info .= $key . " wurde geändert von '" . htmlspecialchars($user_to_update[$key]) .
                                 "' auf '" . htmlspecialchars($nutzer_to_update_after[$key]) . "'.<br>";
                        $record[$key] = $nutzer_to_update_after[$key];
                    }
                }
            }
        }
        
        if ($changed && ! $form_errors) {
            $record["LastModified"] = strval(time()) . "000";
            $change_result = $socket->update_record($_SESSION["User"][$toolbox->users->user_id_field_name], 
                    $toolbox->users->user_table_name, $record, false);
            if (strlen($change_result) > 0) {
                $form_errors .= "<br/>Datenbank Update-Kommando fehlgeschlagen. Fehlermeldung :" .
                         $change_result;
            } else {
                $toolbox->logger->log(0, intval($nutzer_to_update_after[$toolbox->users->user_id_field_name]), 
                        "Nutzer von Verwalter(in) " . $_SESSION["User"][$toolbox->users->user_id_field_name] .
                                 " geändert.");
            }
        } elseif (! $form_errors) {
            $info .= 'Es wurden keine veränderten Daten eingegeben, oder es liegen Fehler vor.' .
                     '  Es wurde daher nichts geändert.</p>';
        }
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
    if (($todo == 1)) {
        // preset values on first step.
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
?>
<!-- START OF content -->
<div class="w3-container">
<?php
if ($is_new_user) {
    echo "<h3>Den neuen Nutzer mit der ID " . $id_to_update . " anlegen</h3>";
    echo "<p>Hier können Sie das Profil des neuen Nutzers eingeben.</p>";
} else {
    echo "<h3>Das Profil von " . $user_name_display . " ändern</h3>";
    echo "<p>Hier können Sie das Profil des Nutzers ändern.</p>";
}
?> 
</div>

<div class="w3-container">
<?php

echo $toolbox->form_errors_to_html($form_errors);
echo $form_to_fill->get_html($fs_id);

if ($todo == 1) { // step 1. No special texts for output
    echo '<h5><br />Ausfüllhilfen</h5><ul>';
    echo $form_to_fill->get_help_html();
    echo "</ul>";
} else {
    ?>
    <p>
		<b>Die Datenänderung ist <?php  echo (($form_errors) ? "nicht" : ""); ?> durchgeführt.</b>
	</p>
	<p>
		<?php
    echo (($form_errors) ? "" : "Folgende Änderungen wurden vorgenommen:<br />" . $info);
    ?>
             </p>
	<p>
		<a href="../pages/nutzer_profil.php?id=<?php  echo $id_to_update; ?>">Geändertes
			Profil des Nutzers anzeigen.</a><br /> <a
			href="../forms/nutzer_aendern.php?id=<?php  echo $id_to_update; ?>">Nutzer
			weiter ändern.</a>
	</p>
<?php
}
?></div><?php
end_script();
