<?php
/**
 * The form for user profile self service. Based on the Tfyh_form class, please read instructions their to better
 * understand this PHP-code part.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
// ===== page does not need an active session
include_once "../classes/init.php";
include_once '../classes/tfyh_form.php';

// === APPLICATION LOGIC ==============================================================
$search_result_index = (isset($_SESSION["getps"][$fs_id]["searchresultindex"])) ? intval(
        $_SESSION["getps"][$fs_id]["searchresultindex"]) : 0;
if ($search_result_index == 0)
    $toolbox->display_error("Nicht zulässig.", 
            "Die Seite '" . $user_requested_file .
                     "' muss als Folgeseite von Datensatz finden aufgerufen werden.", __FILE__);
$tablename = $_SESSION["efa2table"];
$search_result = $_SESSION["search_result"][$search_result_index];

// prepare form autogeneration
include_once "../classes/efa_tables.php";
include_once "../classes/efa_dataedit.php";
$efa_dataedit = new Efa_dataedit($toolbox, $socket);
$efa_tables = new Efa_tables($toolbox, $socket);

$form_errors = "";
$search_result_data_key = $efa_tables->get_data_key($tablename, $search_result);
if ($search_result_data_key == false) {
    $form_errors .= "Der Datensatzschlüssel ist leider unvollständig. Der Datensatz kann nicht bearbeitet werden.";
    $done = 0;
}

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = ($done == 0) ? 1 : $done;
$form_layout = "../config/layouts/dataedit";

// ======== start with form filled in last step: check of the entered values.
if ($done == 0) {
    // create form layout based on the record provided.
    $efa_dataedit->set_data_edit_template($tablename, $search_result);
} else {
    $form_filled = new Tfyh_form($form_layout, $socket, $toolbox, $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    
    // fix for boolean (checkbox) values: efa expects "true" or nothing instead of "on" or nothing
    $entered_data = Efa_tables::fix_boolean_text($tablename, $entered_data);
    $entered_data = $efa_dataedit->fix_empty_UUIDs($tablename, $entered_data);
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This avoids any change, if form errors occured.
    } elseif ($done == 1) {
        // get entered data
        $search_result_after = [];
        foreach ($entered_data as $key => $value)
            $search_result_after[$key] = $value;
        $search_result_after["ChangeCount"] = $search_result["ChangeCount"];
        // add unchanged data key
        foreach ($search_result_data_key as $key => $value)
            $search_result_after[$key] = $value;
        
        $todo = $done + 1;
        $info = "";
        $changed = false;
        foreach ($search_result as $key => $value) {
            if (isset($search_result_after[$key]) && (strcmp($value, $search_result_after[$key]) !== 0) &&
                     (strcasecmp($key, "LastModified") !== 0)) {
                $changed = true;
                $info .= $key . " wurde geändert von '" . htmlspecialchars($value) . "' auf '" .
                         htmlspecialchars($search_result_after[$key]) . "'.<br>";
            }
        }
        // log changes
        if ($changed && ! $form_errors) {
            $search_result_after["LastModified"] = strval(time()) . "000";
            $search_result_after["LastModification"] = "update";
            if (isset($search_result_after["ChangeCount"]))
                $search_result_after["ChangeCount"] = strval(intval($search_result_after["ChangeCount"]) + 1);
            else
                $search_result_after["ChangeCount"] = 1;
            $lmod = (strlen($value) > 13) ? "unlimited" : date("Y-m-d H:i:s", time());
            $info .= "LastModified: " . $lmod . ", LastModification: " .
                     $search_result_after["LastModification"] . ", ChangeCount: " .
                     $search_result_after["ChangeCount"] . "<br>";
            $change_result = $socket->update_record_matched(
                    $_SESSION["User"][$toolbox->users->user_id_field_name], $tablename, 
                    $search_result_data_key, $search_result_after, false);
            if ($change_result) {
                $form_errors .= "<br/>Datenbank Update-Kommando fehlgeschlagen: " . $change_result;
                $info = "";
            } else {
                $_SESSION["search_result"][$search_result_index] = $search_result_after;
                $toolbox->logger->log(0, 
                        intval($_SESSION["User"][$toolbox->users->user_id_field_name]), 
                        "Datensatz von #" . $_SESSION["User"][$toolbox->users->user_id_field_name] .
                                 " geändert.");
            }
        } elseif (! $form_errors) {
            $info = 'Es wurden keine veränderten Daten eingegeben, oder es liegen Fehler vor.' .
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
        // fix for boolean (checkbox) values: efa expects "true" or nothing instead of "on" or nothing
        $search_result = Efa_tables::fix_boolean_text($tablename, $search_result);
        $form_to_fill->preset_values($search_result);
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
	<h3>Einen Datensatz ändern</h3>
	<p>Hier können Sie einen beliebigen Datensatz <?php if (isset($tablename)) echo " <b>für die Tabelle " . $tablename . "</b>" ?> ändern. Bitte mit
		Vorsicht agieren, insbesondere bei Kennwörtern.</p>
</div>

<div class="w3-container">
<?php

echo $toolbox->form_errors_to_html($form_errors);
$get_parameter = "searchresultindex=" . $search_result_index;
echo $form_to_fill->get_html(false, $get_parameter);

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
    echo "<br><a href='../pages/view_record.php?searchresultindex=" . $search_result_index .
             "'>geänderten Datensatz anzeigen</a>";
    ?>
             </p>
<?php
}
?></div><?php
end_script();
