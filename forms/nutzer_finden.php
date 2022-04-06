<?php
/**
 * The form for user profile self service.
 * Based on the Tfyh_form class, please read instructions their to better understand this PHP-code part.
 *
 * @author mgSoft
 */
// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
// ===== page does not need an active session
include_once "../classes/init.php";
include_once '../classes/tfyh_form.php';

$users_to_show_html = "";
$id = (isset($_SESSION["getps"][$fs_id]["id"])) ? intval($_SESSION["getps"][$fs_id]["id"]) : 0;

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = ($done == 0) ? 1 : $done;
$form_errors = "";
$form_layout = "../config/layouts/nutzer_finden";

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
        $is_all_texts = false;
        $sql_cmd = "";
        if ($id > 0)
            $sql_cmd .= "`ID` = '" . $id . "'";
        elseif (strlen($entered_data["Volltextsuche"]) > 0) {
            // search all entries and all text fields
            $sql_cmd .= "1";
            $is_all_texts = true;
            $search_string_lc = strtolower($entered_data["Volltextsuche"]);
        } else {
            foreach ($entered_data as $key => $value) {
                if (isset($value) && (strlen($value) > 0))
                    $sql_cmd .= "`" . $key . "` LIKE '%" . $value . "%' OR ";
            }
            $sql_cmd = substr($sql_cmd, 0, strlen($sql_cmd) - 4); // strip off last " OR "
        }
        // only proceed if something was entered.
        if (strlen($sql_cmd) > 0) {
            // get all current users
            $sql_cmd_pref = "SELECT * FROM `" . $toolbox->users->user_table_name . "` WHERE ";
            $res = $socket->query($sql_cmd_pref . $sql_cmd, false);
            // put all values to the array, with numeric autoincrementing key.
            $nutzerliste = [];
            $next_nutzer = $res->fetch_array();
            while ($next_nutzer) {
                $filtered_nutzer = [];
                $text_found = false;
                $key_matched = "";
                foreach ($next_nutzer as $key => $value) {
                    if (! is_numeric($key)) {
                        // join all text fields of filtered user
                        $filtered_nutzer[$key] = $value;
                        // if full text search, check field for sear string.
                        if ($is_all_texts && (strpos(strtolower($value), $search_string_lc) !== false)) {
                            $text_found = true;
                            $key_matched .= " in: " . $key . ";";
                        }
                    }
                }
                // add user to filtered list if it was no full text search,
                // then the filter was part of the SQL-Statement, or idf the
                // text was found.
                if ($text_found)
                    $filtered_nutzer["key_matched"] = $key_matched;
                if (! $is_all_texts || $text_found)
                    $nutzerliste[] = $filtered_nutzer;
                $next_nutzer = $res->fetch_array();
            }
            $todo = $done + 1;
        } else {
            $form_errors = "Für die Suche muss mindestens ein Feld einen Eintrag enthalten.";
        }
    }
    
    // if users were selected, create list output.
    if ($todo == 2) {
        $i = 0;
        foreach ($nutzerliste as $users_to_show) {
            $info = $users_to_show[$toolbox->users->user_id_field_name] . ": " . $users_to_show["Vorname"] . " " .
                     $users_to_show["Nachname"] . ".";
            if (isset($users_to_show["key_matched"]))
                $info .= " '<b>" . $entered_data["Volltextsuche"] . "</b>'" . $users_to_show["key_matched"] .
                         ", ";
                $info .= $toolbox->users->get_action_links($users_to_show["ID"]);
            $users_to_show_html .= $info . "<br />";
            $i ++;
        }
        if ($i === 0) {
            $users_to_show_html = "<b>Hinweis:</b><br>Kein passender Nutzer gefunden.";
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
?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Einen Nutzer finden</h3>
	<p>Hier kannst Du einen Nutzer unter Angabe der efaCloudUserID oder
		seines Vor oder Nachnamens finden.</p>
</div>

<div class="w3-container">
<?php

echo $toolbox->form_errors_to_html($form_errors);
if ($todo < 2)
    echo $form_to_fill->get_html();
    else
        echo $users_to_show_html;
        echo '<div class="w3-container"><ul>';
        echo $form_to_fill->get_help_html();
        echo "</ul></div>";
        
?></div><?php
end_script();

    