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
$form_layout = "../config/layouts/client_tx_statistics";
$users_to_show_html = "";

// ======== start with form filled in last step: check of the entered values.
if ($done > 0) {
    
    $form_filled = new Form($form_layout, $socket, $toolbox, $toolbox->users->user_table_name, $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    $forms[$done] = $form_filled;
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This only prevents any logic to apply, if form errors
        // occured.
    } elseif ($done == 1) {
        if (intval($entered_data["type"]) == 1)
            echo header("Location: ../pages/show_logs.php?clientID=" . $entered_data["clientID"]);
            elseif (intval($entered_data["type"]) == 2)
                echo header("Location: ../pages/client_tx_statistics.php?clientID=" . $entered_data["clientID"]);
                
    }
}

// ==== continue with the definition and eventually initialization of form to fill in this step
if (($done == 0) || ($todo !== $form_filled->get_index())) {
    // use a new form for the very first form display or the next step.
    if ($done == 0)
        $todo = 1;
        $form_to_fill = new Form($form_layout, $socket, $toolbox, $toolbox->users->user_table_name, $todo, $fs_id);
    $available_clients = scandir("../uploads");
    $select_options_list = [];
    foreach ($available_clients as $available_client) {
        if (is_dir("../uploads/" . $available_client)) {
            $client = $socket->find_record($toolbox->users->user_table_name, $toolbox->users->user_id_field_name, $available_client);
            if ($client !== false)
                $select_options_list[] = $available_client . "=" . $client["Vorname"] . " " . $client["Nachname"];
        }
    }
    if (count($select_options_list) == 0)
        $select_options_list[] = "0=keine Client Statistik vorhanden";
    
    $form_to_fill->select_options = $select_options_list;
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
	<h3>Internetzugriffszeiten und Log-Dateien einsehen</h3>
	<p>Hier kannst Du für einen Client die zuletzt von ihm gesendeten<br>Internetzugriffszeiten und API-Logdateien ansehen.</p>
</div>

<div class="w3-container">
<?php

echo $toolbox->form_errors_to_html($form_errors);
if ($todo < 2) {
    echo $form_to_fill->get_html($fs_id);
    echo '<h5><br />Ausfüllhilfen</h5><ul>';
    echo $form_to_fill->get_help_html();
    echo "</ul>";
} else
    echo $users_to_show_html;

?></div><?php
end_script();

    