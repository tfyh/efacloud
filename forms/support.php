<?php
/**
 * Based on the Tfyh_form class, please read instructions their to better understand this PHP-code part.
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
$form_layout = "../config/layouts/support";

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
        $url = 'https://www.efacloud.org/support/request.php';
        $data = $entered_data;
        if (isset($entered_data["SendLogs"]) && (strlen($entered_data["SendLogs"]) > 0)) {
            $monitoring_report = $toolbox->logger->zip_logs();
            $data["monitoring_report_zip"] = str_replace("=", "_", 
                    str_replace("/", "-", 
                            str_replace("+", "*", 
                                    base64_encode(utf8_encode(file_get_contents($monitoring_report))))));
        }
        
        unset($data["SendLogs"]);
        $options = array(
                'http' => array('header' => "Content-type: application/x-www-form-urlencoded\r\n",
                        'method' => 'POST','content' => http_build_query($data)
                )
        );
        $context = stream_context_create($options);
        $post_result = file_get_contents($url, false, $context);
        if ($post_result === false)
            $post_result = "Übertragung an den efaCloud Server gescheitert.";
        $todo = 2;
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

<div class="w3-container">
	<h3>Supportanfrage oder Feedback senden</h3>
	<?php

echo $toolbox->form_errors_to_html($form_errors);

// ======== start with the display of either the next form, or the error messages.
if ($todo == 1) {
    ?>
	<p>Bitte lassen Sie mich Ihr Anliegen wissen. Ich versuche sobald wie
		möglich zu helfen. In Urlaubszeiten kann es dabei schon mal zu
		Verzögerungen kommen, normalerweise ist eine Antwort in etwa einer
		Woche zu erwarten.</p>
	<p>Ich freue mich auch über Feedback zu efaCloud, Anregungen und
		Wünsche. Dieses Formular geht an 'efacloud.org'.</p>
<?php
    // step 1. Show form.
    echo $form_to_fill->get_html($fs_id);
    // insert help text as right hand menu for mobile access
    echo '<h5><br />Ausfüllhilfen</h5><ul>';
    echo $form_to_fill->get_help_html();
    echo "</ul>";
} elseif ($todo == 2) {
    echo "<p>Vielen Dank für Ihre Anfrage. Der efacloud.org Server meldet:</p><p>";
    echo $post_result;
    echo "</p><p>Der Vorgang ist damit abgeschlossen.</p>";
}
?>
</div>
<?php
end_script();
