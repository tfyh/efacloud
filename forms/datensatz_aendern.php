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
$search_result_index = (isset($_GET["searchresult"])) ? intval($_GET["searchresult"]) : 0;
if ($search_result_index == 0)
    $toolbox->display_error("Nicht zulässig.", 
            "Die Seite '" . $user_requested_file . "' muss als Folgeseite von Datensatz finden aufgerufen werden.", __FILE__);
$tablename = $_SESSION["efa2table"];
$search_result = $_SESSION["search_result"][$search_result_index];

// prepare form autogeneration
include_once "../classes/efa_tables.php";
include_once "../classes/efa_dataedit.php";
$efa_dataedit = new Efa_dataedit($toolbox, $socket);
$search_result_data_key = $efa_dataedit->get_data_key($tablename, $search_result);

// if validation fails, the same form will be displayed anew with error messgaes
$todo = $done;
$form_errors = "";
$form_layout = "../config/layouts/dataedit";

// ======== start with form filled in last step: check of the entered values.
if ($done == 0) {
    // create form layout based on the record provided.
    $efa_dataedit->set_data_edit_template($tablename, $search_result);
} else {
    $form_filled = new Form($form_layout, $socket, $toolbox, $tablename, $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    $forms[$done] = $form_filled;
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This only prevents any logic to apply, if form errors
        // occured.
    } elseif ($done == 1) {
        // get entered data
        $search_result_after = [];
        foreach ($entered_data as $key => $value)
            $search_result_after[$key] = $value;
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
        if ($changed && ! $form_errors) {
            $search_result_after["LastModified"] = strval(time()) . "000";
            $search_result_after["LastModification"] = "update";
            if (isset($search_result_after["ChangeCount"]))
                $search_result_after["ChangeCount"] = strval(intval($search_result_after["ChangeCount"]) + 1);
            else
                $search_result_after["ChangeCount"] = 1;
            $lmod = (strlen($value) > 13) ? "unlimited" : date("Y-m-d H:i:s", time());
            $info .= "LastModified: " . $lmod . ", LastModification: " . $search_result_after["LastModification"] .
                     ", ChangeCount: " . $search_result_after["ChangeCount"] . "<br>";
            $change_result = $socket->update_record_matched($_SESSION["User"][$toolbox->users->user_id_field_name], $tablename, 
                    $search_result_data_key, $search_result_after, false);
            if ($change_result) {
                $form_errors .= "<br/>Datenbank Update-Kommando fehlgeschlagen: " . $change_result;
                $info = "";
            } else {
                $toolbox->logger->log(Tfyh_logger::$TYPE_DONE, intval($_SESSION["User"][$toolbox->users->user_id_field_name]), 
                        "Datensatz von #" . $_SESSION["User"][$toolbox->users->user_id_field_name] . " geändert.");
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
        $form_to_fill = new Form($form_layout, $socket, $toolbox, $tablename, $todo, $fs_id);
    $form_to_fill->preset_values($search_result);
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
	<h3>Einen Datensatz ändern</h3>
	<p>Hier können Sie einen beliebigen Datensatz <?php if (isset($tablename)) echo " <b>für die Tabelle " . $tablename . "</b>" ?> ändern. Bitte mit
		Vorsicht agieren, insbesondere bei Kennwörtern.</p>
</div>

<div class="w3-container">
<?php

echo $toolbox->form_errors_to_html($form_errors);
$get_parameter = "searchresult=" . $search_result_index;
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
    ?>
             </p>
<?php
}
?></div><?php
end_script();
