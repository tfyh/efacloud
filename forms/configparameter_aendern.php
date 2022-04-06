<?php
/**
 * The form for changing the application configuration parameter. Based on the Tfyh_form class, please read
 * instructions their to better understand this PHP-code part.
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
$form_layout = "../config/layouts/configparameter_aendern";

// ======== Start with form filled in last step: check of the entered values.
if ($done > 0) {
    $form_filled = new Tfyh_form($form_layout, $socket, $toolbox, $done, $fs_id);
    $form_filled->read_entered(false);
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This avoids any change, if form errors occured.
    } elseif ($done == 1) {
        // write settings
        $settings_path = "../config/settings";
        $cfgStr = serialize($entered_data);
        $cfgStrBase64 = base64_encode($cfgStr);
        $info = "<p>" . $settings_path . '_app wird geschrieben ... ';
        $byte_cnt = file_put_contents($settings_path . "_app", $cfgStrBase64);
        $info .= $byte_cnt . " Bytes.</p>";
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
    $form_to_fill->preset_values($toolbox->config->get_cfg(), true);
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
	<h3>Konfigurationsparameter ändern</h3>
	<p>Hier können die Konfigurationsparameter mit Ausnahme der
		Datenbankzugangsdaten geändert werden.</p>
<?php

echo $toolbox->form_errors_to_html($form_errors);
if ($todo == 1) {
    echo $form_to_fill->get_html($fs_id);
    echo '<h5><br />Ausfüllhilfen</h5><ul>';
    echo $form_to_fill->get_help_html();
    echo "</ul>";
}
if ($todo == 2) {
    echo $info;
}
?></div><?php
end_script();
