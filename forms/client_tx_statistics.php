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
// if validation fails, the same form will be displayed anew with error messgaes
$todo = ($done == 0) ? 1 : $done;
$form_errors = "";
$form_layout = "../config/layouts/client_tx_statistics";
$users_to_show_html = "";

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
        if (intval($entered_data["type"]) < 2)
            echo header("Location: ../pages/show_logs_client.php?clientID=" . $entered_data["clientID"]);
        else
            echo header("Location: ../pages/client_tx_statistics.php?clientID=" . $entered_data["clientID"]);
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
        $available_clients = scandir("../uploads");
        $select_options_list = [];
        foreach ($available_clients as $available_client) {
            if (is_dir("../uploads/" . $available_client)) {
                $client = $socket->find_record($toolbox->users->user_table_name, 
                        $toolbox->users->user_id_field_name, $available_client);
                if ($client !== false)
                    $select_options_list[] = $available_client . "=" . $client["Vorname"] . " " .
                             $client["Nachname"];
            }
        }
        if (count($select_options_list) == 0)
            $select_options_list[] = "0=keine Client Statistik vorhanden";
        $form_to_fill->select_options = $select_options_list;
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
	<h3>Client-Log-Dateien einsehen oder als Grafik anzeigen</h3>
	<p>Hier kannst Du die von einen Client zuletzt gesendeten Logdateien
		und Statistiken als Grafik ansehen.</p>
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

    