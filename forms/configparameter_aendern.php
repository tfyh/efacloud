<?php
/**
 * The form for user profile self service. Based on the Form class, please read instructions their to better
 * understand this PHP-code part.
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
$form_layout = "../config/layouts/configparameter_aendern";

// ======== start with form filled in last step: check of the entered values.
if ($done > 0) {
    
    $form_filled = new Form($form_layout, $socket, $toolbox, $toolbox->config->parameter_table_name, $done, 
            $fs_id);
    $form_filled->read_entered(false);
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    $forms[$done] = $form_filled;
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This only prevents any logic to apply, if form errors
        // occured.
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

// ==== continue with the definition and eventually initialization of form to fill in this step
if (($done == 0) || ($todo !== $form_filled->get_index())) {
    // use a new form for the very first form display or the next step.
    if ($done == 0)
        $todo = 1;
    $form_to_fill = new Form($form_layout, $socket, $toolbox, $toolbox->config->parameter_table_name, 
            $todo, $fs_id);
    $form_to_fill->preset_values($toolbox->config->get_cfg(), true);
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
	<h3>Konfigurationsparameter ändern</h3>
	<p>Hier können die Konfigurationsparameter mit Ausnahme der
		Datenbankzugangsdaten geändert werden.</p>
</div>

<div class="w3-container">
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
