<?php
/**
 * The form for user profile self service.
 * Based on the Form class, please read instructions their to better understand this PHP-code part.
 *
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
// ===== page does not need an active session
include_once "../classes/init.php";
include_once '../classes/form.php';

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = $done;
$form_errors = "";
$form_layout = "../config/layouts/nutzer_aendern";

// This page requires an id to be set for the user to update. The form for user self service changes
// is "profil_aendern.php".
if (isset($_GET["id"]) || isset($_SESSION["id_to_update"])) {
    $id_to_update = (isset($_GET["id"])) ? intval($_GET["id"]) : intval($_SESSION["id_to_update"]);
    if ($id_to_update > 0) {
        $user_to_update = $socket->find_record_matched($toolbox->users->user_table_name, [ "ID" => $id_to_update ], false);
    } else {
        $user_to_add["Vorname"] = ".";
        $user_to_add["Nachname"] = ".";
        $user_to_add["EMail"] = date("ymdHis") . "@efacloud.tfyh.org"; // EMail String must be unique
        $efaCloudUserID = $_SESSION["User"][$toolbox->users->user_id_field_name];
        $user_to_add["LastModified"] = strval(time()) . "000";
        $id_to_update = $socket->insert_into($efaCloudUserID, $toolbox->users->user_table_name, $user_to_add, false);
        $user_to_update = $socket->find_record_matched($toolbox->users->user_table_name, [ "efaCloudUserId" => $id_to_update ], false);
    }
} else
    $toolbox->display_error("Nicht zulässig.", 
            "Die Seite '" . $user_requested_file .
                     "' muss mit der Angabe der id des zu ändernden Nutzers aufgerufen werden.", __FILE__);
$_SESSION["id_to_update"] = $id_to_update;
$user_name_display = $user_to_update["Vorname"] . " " . $user_to_update["Nachname"];
// ===== a dummy for a password which is not the right one. Must nevertheless be a valid one to
// pass all checks further down.
$keep_password = "keuk3HVpxHASrcRn6Mpf";

// ======== start with form filled in last step: check of the entered values.
if ($done > 0) {
    
    $form_filled = new Form($form_layout, $socket, $toolbox, $toolbox->users->user_table_name, $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    if (strlen($entered_data["Passwort"]) == 0) {
        $form_filled->preset_value("Passwort", $keep_password);
        $form_filled->preset_value("Passwort_Wdh", $keep_password);
    }
    $forms[$done] = $form_filled;
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This only prevents any logic to apply, if form errors
        // occured.
    } elseif ($done == 1) {
        // get user data stored
        $nutzer_to_update_after[] = [];
        foreach ($user_to_update as $key => $value)
            $nutzer_to_update_after[$key] = $value;
        // Password was changed, check identity of password and repetition
        if (! strcmp($entered_data['Passwort'], $keep_password)) {
            // -------------------------------
            // password and repetition must be identical.
            if ($entered_data['Passwort'] != $entered_data['Passwort_Wdh']) {
                $form_errors .= "Die Passwörter müssen übereinstimmen. " . "Dein Passwort wird nicht geändert.<br>";
                $form_filled->preset_value("Passwort", $keep_password);
                $form_filled->preset_value("Passwort_Wdh", $keep_password);
            }
        }
        // EMail must be unique.
        $mail_address_used = $socket->find_record_matched($toolbox->users->user_table_name, [ "EMail", $entered_data['EMail'] ], true);
        if (($mail_address_used !== false) &&
                 ($mail_address_used[$toolbox->users->user_id_field_name] !== $user_to_update[$toolbox->users->user_id_field_name])) {
            $form_errors .= 'Die E-Mail-Adresse "' . $entered_data['EMail'];
            $form_errors .= '" ist bereits belegt von ' . $mail_address_used["Vorname"] . " " .
                     $mail_address_used["Nachname"] . '. Deine E-Mail-Adresse kann daher nicht geändert werden. ';
        }
        // now copy changed values, except password (will be done later)
        foreach ($entered_data as $key => $value) {
            if (strcasecmp($key, "Passwort") === 0) {
                $form_errors .= $toolbox->check_password($value);
            } else
                $nutzer_to_update_after[$key] = $value;
        }
        
        // check $nutzer_to_update_after whether all values match validity criteria
        $invalid = $toolbox->users->check_user_profile($nutzer_to_update_after);
        if ($invalid)
            $form_errors .= "Fehler bei der Überprüfung der Daten: " . $invalid;
        // set password to its hash value.
        elseif ((strcmp($keep_password, $entered_data["Passwort"]) !== 0) &&
                 (strcasecmp($user_to_update["Rolle"], "bths") == 0))
            $nutzer_to_update_after["Passwort_Hash"] = password_hash($entered_data['Passwort'], PASSWORD_DEFAULT);
        
        // continue, if no errors were detected
        if (strlen($form_errors) == 0) {
            $todo = $done + 1;
            $info = "";
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
            $change_result = $socket->update_record($_SESSION["User"][$toolbox->users->user_id_field_name], $toolbox->users->user_table_name, $record, 
                    false);
            if ($change_result === false) {
                $form_errors .= "<br/>Datenbank Update-Kommando fehlgeschlagen.";
            } else {
                $toolbox->logger->log(Tfyh_logger::$TYPE_DONE, intval($nutzer_to_update_after[$toolbox->users->user_id_field_name]), 
                        "Nutzer von Verwalter(in) " . $_SESSION["User"][$toolbox->users->user_id_field_name] . " geändert.");
            }
        } elseif (! $form_errors) {
            $info = 'Es wurden keine veränderten Daten eingegeben, oder es liegen Fehler vor.' .
                     '  Es wurde daher nichts geändert.</p>';
        }
        $todo = $done + 1;
    }
}

// ==== continue with the definition and eventually initialization of form to fill in this step
if (($done == 0) || ($todo !== $form_filled->get_index())) {
    // use a new form for the very first form display or the next step.
    if ($done == 0)
        $todo = 1;
        $form_to_fill = new Form($form_layout, $socket, $toolbox, $toolbox->users->user_table_name, $todo, $fs_id);
    if ($id_to_update > 0) {
        $nutzer_to_update_before = $socket->find_record_matched($toolbox->users->user_table_name, [ "ID" => $id_to_update ]);
        // preset password to $keep_password, to know whether it was changed. $keep_password is a
        // valid password, to survice the checks applied in the second step.
        $nutzer_to_update_before["Passwort"] = $keep_password;
        $nutzer_to_update_before["Passwort_Wdh"] = $keep_password;
        $form_to_fill->preset_values($nutzer_to_update_before);
    }
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
<!-- START OF content -->
<div class="w3-container">
<?php
if (strlen($user_name_display) > 3)
    echo "<h3>Das Profil von " . $user_name_display . " ändern</h3>";
else
    echo "<h3>Den neuen Nutzer mit der ID " . $id_to_update . " anlegen</h3>";
?> 
	<p>Hier können Sie das Profil des Nutzers ändern.</p>

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
    echo (($form_errors) ? "" : "Folgende Änderungen wurden vorgenommen:<br />");
    echo $info;
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
