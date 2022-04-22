<?php
/**
 * The form for user workflow assignment. Based on the Tfyh_form class, please read instructions their to
 * better understand this PHP-code part.
 * 
 * @author mgSoft
 */
// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once '../classes/tfyh_form.php';

// This page requires an id to be set for the user to update.
if (isset($_SESSION["getps"][$fs_id]["id"]) && (intval($_SESSION["getps"][$fs_id]["id"]) > 0))
    $id_to_update = intval($_SESSION["getps"][$fs_id]["id"]);
else
    $toolbox->display_error("Nicht zulässig.", 
            "Die Seite '" . $user_requested_file .
                     "' muss mit der Angabe der id des zu ändernden Nutzers aufgerufen werden.", 
                    $user_requested_file);
// This page requires an id to be set for the user to update.
if (isset($_SESSION["getps"][$fs_id]["type"]))
    $type = $_SESSION["getps"][$fs_id]["type"];
else
    $toolbox->display_error("Nicht zulässig.", 
            "Die Seite '" . $user_requested_file . "' muss mit der Angabe des Rechtetyps aufgerufen werden.", 
            $user_requested_file);

// get the user which shall be changed.
$user_to_update = $socket->find_record_matched($toolbox->users->user_table_name, 
        ["ID" => $id_to_update
        ]);
if ($user_to_update === false)
    $toolbox->display_error("Nicht gefunden.", 
            "Der Nutzerdatensatz zur ID '" . $id_to_update . "' konnte nicht gefunden werden.", 
            $user_requested_file);
$user_name_display = $user_to_update["Vorname"] . " " . $user_to_update["Nachname"];

// retrieve current data, to preset form
$workflows_before = intval($user_to_update["Workflows"]);
$workflows_set = $toolbox->read_csv_array("../config/access/workflows");
$concessions_before = intval($user_to_update["Concessions"]);
$concessions_set = $toolbox->read_csv_array("../config/access/concessions");

foreach ($workflows_set as $workflow)
    $preset[$workflow["Name"]] = (($workflows_before & $workflow["Flag"]) > 0) ? "on" : false;
foreach ($concessions_set as $concession)
    $preset[$concession["Name"]] = (($concessions_before & $concession["Flag"]) > 0) ? "on" : false;

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = ($done == 0) ? 1 : $done;
$form_errors = "";
if (strcasecmp($type, "efaAdmin") == 0)
    $form_layout = "../config/layouts/efaAdmin_rechte_aendern";
elseif (strcasecmp($type, "efaWeb") == 0)
    $form_layout = "../config/layouts/efaWeb_rechte_aendern";

// ======== Start with form filled in last step: check of the entered values.
if ($done > 0) {
    $form_filled = new Tfyh_form($form_layout, $socket, $toolbox, $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This only prevents any logic to apply, if form errors
        // occured.
    } elseif ($done == 1) {
        // retrieve user workflows and form data and update user workflows
        $mask = $workflows_before;
        $workflows_after = 0;
        // bitwise set or delete flags
        foreach ($workflows_set as $workflow) {
            $mask = 0xFFFFFFFF ^ intval($workflow["Flag"]);
            if (isset($entered_data[$workflow["Name"]])) {
                // this was a form field, use the form input
                if (strlen($entered_data[$workflow["Name"]]) > 0)
                    $workflows_after = $workflows_after | intval($workflow["Flag"]);
                else
                    $workflows_after = $workflows_after & $mask;
            } else {
                // this was no form field, kepp the flag as is
                $workflows_after = $workflows_after | ($mask & $workflows_before);
            }
        }
        // retrieve user concessions and form data and update user concessions
        $mask = $concessions_before;
        $concessions_after = 0;
        // bitwise set or delete flags
        foreach ($concessions_set as $concession) {
            $mask = 0xFFFFFFFF ^ intval($concession["Flag"]);
            if (isset($entered_data[$concession["Name"]])) {
                if (strlen($entered_data[$concession["Name"]]) > 0)
                    $concessions_after = $concessions_after | intval($concession["Flag"]);
                else
                    $concessions_after = $concessions_after & $mask;
            } else {
                // this was no form field, kepp the flag as is
                $concessions_after = $concessions_after | ($mask & $concessions_before);
            }
        }
        $record_for_update["ID"] = $user_to_update["ID"];
        $record_for_update["Workflows"] = $workflows_after;
        $record_for_update["Concessions"] = $concessions_after;
        $res = $socket->update_record($_SESSION["User"][$toolbox->users->user_id_field_name], 
                $toolbox->users->user_table_name, $record_for_update);
        if ($res === false)
            $form_errors .= "Datenbankstatement ist fehlgeschlagen.";
        $todo = $done + 1;
        // retrieve updated data for display
        $works_list = "<table>" . $toolbox->users->get_user_services(strtolower("Workflows"), 
                "Allgemeine Rechte", $workflows_after) . $toolbox->users->get_user_services(
                strtolower("Concessions"), "Berechtigungen für efa Nachrichten und efaWeb", $concessions_after) . "</table>";
    }
}

// ==== continue with the definition and eventually initialization of form to fill for the next step
if (isset($form_filled) && ($todo == $form_filled->get_index())) {
    // redo the 'done' form, if the $todo == $done, i. e. the validation failed.
    $form_to_fill = $form_filled;
} else {
    // if it is the start or all is fine, use a form for the coming step.
    $form_to_fill = new Tfyh_form($form_layout, $socket, $toolbox, $todo, $fs_id);
    if ($todo == 1)
        $form_to_fill->preset_values($preset);
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
	<h3>
		Die <b><?php echo $type . "-Berechtigungen für " . $user_to_update["Vorname"] . " " . $user_to_update["Nachname"]; ?></b>
		ändern
	</h3>
</div>

<div class="w3-container">
<?php

echo $toolbox->form_errors_to_html($form_errors);
echo $form_to_fill->get_html();

if ($todo == 1) { // step 1. No special texts for output
} elseif ($todo == 2) {
    echo "<p>Die " . $type . "-Berechtigungen für <b>" . $user_name_display .
             "</b> wurden geändert.</p><p>Ab dem " . "nächsten Login gilt für ihn:<br>" . $works_list .
             "</p><p><a href='../forms/efa_rechte_aendern.php?id=" . $user_to_update["ID"] .
             "&type=efaAdmin'>Zurück zu seinen efa Admin-Berechtigungen</a></p><p><a href='../forms/efa_rechte_aendern.php?id=" .
             $user_to_update["ID"] . "&type=efaWeb'>Zurück zu seinen efaWeb-Berechtigungen</a></p>";
}

echo '<div class="w3-container"><ul>';
echo $form_to_fill->get_help_html();
echo "</ul></div>";
?>

</div>
<?php
end_script();