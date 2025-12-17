<?php
/**
 *
 *       efaCloud
 *       --------
 *       https://www.efacloud.org
 *
 * Copyright  2018-2024  Martin Glade
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


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

// This page requires an id to be set for the user to update. If not set, or the id is 0, a new user will be
// created.
$is_new_user = false;
if (isset($_SESSION["getps"][$fs_id]["id"]) && (intval($_SESSION["getps"][$fs_id]["id"]) > 0))
    $id_to_update = intval($_SESSION["getps"][$fs_id]["id"]);
elseif (isset($_SESSION["getps"][$fs_id]["newid"]) && (intval($_SESSION["getps"][$fs_id]["newid"]) > 0)) {
    $is_new_user = true;
    $id_to_update = intval($_SESSION["getps"][$fs_id]["newid"]);
} else {
    $is_new_user = true;
    $default_email = "PLEASE.CHANGE_@_THIS.ADDRESS.ORG";
    $empty_new_user = $socket->find_record_matched($toolbox->users->user_table_name, 
            ["EMail" => $default_email
            ]);
    if ($empty_new_user === false) {
        $user_to_add["Vorname"] = "John";
        $user_to_add["Nachname"] = "Doe";
        $user_to_add["EMail"] = $default_email;
        $user_to_add["Passwort_Hash"] = "-"; // use empty hash as default
        $efaCloudUserID = $toolbox->users->session_user["@id"];
        $user_to_add["LastModified"] = strval(time()) . "000";
        $id_to_update = $socket->insert_into($efaCloudUserID, $toolbox->users->user_table_name, $user_to_add);
        // set hash to identify user record later as new user record.
    } else
        $id_to_update = $empty_new_user["ID"];
    $_SESSION["getps"][$fs_id]["newid"] = $id_to_update;
}
$user_to_update = $socket->find_record_matched($toolbox->users->user_table_name, 
        ["ID" => $id_to_update
        ]);

if ($user_to_update === false)
    $toolbox->display_error(i("FHyizx|Not found"), i("BGJkSQ|The user record for ID °...", $id_to_update), 
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
                $form_errors .= i("xVSLCZ|The passwords must match...") . "<br>";
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
            $form_errors .= i("GmOMkX|The efaCloudUserID must ...") . "<br>";
        // efaCloudUserID must be in the range 0 to 1,000,000,000
        if ((intval($entered_data['efaCloudUserID']) <= 10) ||
                 (intval($entered_data['efaCloudUserID']) >= 1000000000))
            $form_errors .= i("015RH6|The efaCloudUserID must ...") . "<br>";
        
        // efaAdminName must be 4 to 10 characters
        if ((strlen($entered_data['efaAdminName']) < 4) || (strlen($entered_data['efaAdminName']) > 10))
            $form_errors .= i("WpcBzB|efaAdminName must be bet...") . "<br>";
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
            $form_errors .= i("iRGaIO|efaAdminName may only co...") . "<br>";
        
        // check uniqueness of login relevant IDs, compare "tabelle_importieren.php::$uniques"
        $uniques = ["efaCloudUserID","efaAdminName","EMail"
        ];
        $this_record_id = intval($user_to_update["ID"]);
        foreach ($uniques as $unique) {
            $existing_user_withUnique = $socket->find_record("efaCloudUsers", $unique, $entered_data[$unique]);
            $not_unique = ($existing_user_withUnique !== false) &&
                     (intval($existing_user_withUnique["ID"]) != $this_record_id);
            if ($not_unique)
                $form_errors .= i("ii65L9|The data field °%1° must...", $unique, $entered_data[$unique], 
                        $existing_user_withUnique["Vorname"], $existing_user_withUnique["Nachname"]) . "<br>";
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
        if (strlen($invalid) > 0)
            $form_errors .= i("3vkMSJ|Error checking data:") . " " . $invalid;
        // set password to its hash value.
        elseif (strcasecmp($entered_data['Passwort_delete'], "on") == 0) {
            $nutzer_to_update_after["Passwort_Hash"] = "-";
        } elseif (strcmp($keep_password, $entered_data["Passwort"]) !== 0) {
            if ((strcasecmp($user_to_update["Rolle"], "bths") == 0) || $is_new_user)
                $nutzer_to_update_after["Passwort_Hash"] = password_hash($entered_data['Passwort'], 
                        PASSWORD_DEFAULT);
            else
                $info = i("yR7zed|The password can only be...") . "<br>";
        }
        // Password shall be deleted
        unset($nutzer_to_update_after["Passwort_Wdh"]);
        unset($nutzer_to_update_after["Passwort_delete"]);
        
        // continue, if no errors were detected
        if (strlen($form_errors) == 0) {
            $todo = $done + 1;
            $changed = false;
            $record["ID"] = $id_to_update;
            foreach ($user_to_update as $key => $value) {
                if (isset($nutzer_to_update_after[$key]) &&
                         (strcmp($user_to_update[$key], $nutzer_to_update_after[$key]) !== 0) &&
                         (strcasecmp($key, "LastModified") !== 0)) {
                    $changed = true;
                    if (strcmp($key, "Passwort_Hash") == 0)
                        $info .= i("b25fYT|The password has been ch...") . "<br>";
                    else
                        $info .= i("J8QheD|%1 was changed from °%2°...", $key, 
                                htmlspecialchars($user_to_update[$key]), 
                                htmlspecialchars($nutzer_to_update_after[$key])) . "<br>";
                    $record[$key] = $nutzer_to_update_after[$key];
                }
            }
        }
        
        if ($changed && ! $form_errors) {
            $record["LastModified"] = strval(time()) . "000";
            $change_result = $socket->update_record($toolbox->users->session_user["@id"], 
                    $toolbox->users->user_table_name, $record, false);
            if (strlen($change_result) > 0) {
                $form_errors .= "<br />" . i("5pH8pE|Database update command ...") . " " . $change_result;
            } else {
                $toolbox->logger->log(0, intval($nutzer_to_update_after[$toolbox->users->user_id_field_name]), 
                        "Nutzer von Verwalter(in) " . $toolbox->users->session_user["@id"] .
                                 " geändert.");
            }
        } elseif (! $form_errors) {
            $info .= i("JjCWn9|No modified data has bee...") . "</p>";
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
        // auto-fill-in of PersonId
        if (! isset($user_to_update["PersonId"]) || (strlen($user_to_update["PersonId"]) < 30)) {
            // check name. If a person with the same name exists, add the PersonId.
            $matching = ["FirstName" => $user_to_update["Vorname"],
                    "LastName" => $user_to_update["Nachname"]
            ];
            $persons_with_same_name = $socket->find_records_sorted_matched("efa2persons", $matching, 1, "=", 
                    "InvalidFrom", false);
            if ($persons_with_same_name !== false) {
                $person_id = (count($persons_with_same_name) == 1) ? $persons_with_same_name[0]["Id"] : i(
                        "75hzYb|[several possibilities]");
                $form_to_fill->preset_value("PersonId", $person_id);
            }
        }
    }
}

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
echo "<!-- START OF content -->\n<div class='w3-container'>";

if ($is_new_user) {
    echo "<h3>" . i("ovmMoC|Create a new efaCloud us...") .
             "<sup class='eventitem' id='showhelptext_NutzerUndBerechtigungen'>&#9432;</sup> " .
             i("r7eyg9|with the ID %1 create", $id_to_update) . "</h3>";
    echo "<p>" . i("1HahpW|Here you can enter the p...") . "</p>";
} else {
    echo "<h3>" . i("A3uBQ0|Change the profile of %1", $user_name_display) .
             "<sup class='eventitem' id='showhelptext_NutzerUndBerechtigungen'>&#9432;</sup></h3>";
    echo "<p>" . i("xb6rnq|Here you can change the ...") . "</p>";
}
echo i("uVS3Gb| ** An efaCloud user can...");

echo $toolbox->form_errors_to_html($form_errors);
echo $form_to_fill->get_html();

if ($todo == 1) { // step 1. No special texts for output
    if (strcmp($toolbox->users->session_user["Rolle"], $toolbox->users->useradmin_role) == 0)
        echo "<br><a href='../pages/datensatz_loeschen.php?table=efaCloudUsers&ID=" . $user_to_update["ID"] .
                 "' style='float:left;'>" . i("O39Sks|FINALLY delete users") . "</a>";
    echo $form_to_fill->get_help_html();
} else {
    echo i("EADCwd| ** The data change is *...");
    echo (($form_errors) ? i("rT8wtF|not") : "");
    echo i("NeJqoO| ** performed. ** ");
    echo (($form_errors) ? "" : i("9mww2x|The following changes ha...") . "<br />" . $info);
    echo i("MPbwHx| ** Display changed prof...", $id_to_update);
}
echo i("77gvXM|</div>");
end_script();
