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
$form_layout = "../config/layouts/api_test";
$users_to_show_html = "";

// ======== start with form filled in last step: check of the entered values.
if ($done > 0) {
    
    $form_filled = new Form($form_layout, $socket, $toolbox, "Parameter", $done, $fs_id);
    $form_filled->read_entered(false);
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    $forms[$done] = $form_filled;
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This only prevents any logic to apply, if form errors
        // occured.
    } elseif ($done == 1) {
        $todo = 2;
        // if data were entered, build transaction, send it and analyse response.
        include_once '../classes/tx_handler.php';
        $tx_handler = new Tx_handler($toolbox);
        $results_to_show_html = "<h4>Ergebnis:</h4><p>";
        // if an encoded container is entered, use that one
        if ($entered_data["txc"]) {
            $txc_encoded = $entered_data["txc"];
            $txc_plain = Tx_handler::decode_container($entered_data["txc"]);
            $results_to_show_html .= "<b>Transaction container plain text:</b><br>" . $txc_plain . "<br>";
        } else {
            // else create plain text transaction request message(s)
            file_put_contents("../api/test_record.csv", 
                    $entered_data["recordfields1"] . "\n" . $entered_data["recorddata1"]);
            $record = $toolbox->read_csv_array("../api/test_record.csv");
            if (! $record[0])
                $record[0] = [];
            $txms_plain[] = $tx_handler->create_request($entered_data["ID1"], $entered_data["retries1"], 
                    $entered_data["type1"], $entered_data["tablename1"], $record[0]);
            if (intval($entered_data["ID2"]) > 0) {
                file_put_contents("../api/test_record.csv", 
                        $entered_data["recordfields2"] . "\n" . $entered_data["recorddata2"]);
                $txms_plain[] = $tx_handler->create_request($entered_data["ID2"], $entered_data["retries2"], 
                        $entered_data["type2"], $entered_data["tablename2"], $record[0]);
            }
            // create plain text and encoded container and display both.
            $txc_plain = $tx_handler->create_container(intval($entered_data["cID"]), intval($entered_data["version"]), 
                    $entered_data[$toolbox->users->user_id_field_name], $entered_data["password"], $txms_plain);
            $results_to_show_html .= "<b>Transaction container plain text:</b><br>" . $txc_plain . "<br>";
            $txc_encoded = Tx_handler::encode_container($txc_plain);
        }
        $txc_encoded_wrapped = wordwrap($txc_encoded, 100, "<br>", true);
        $results_to_show_html .= "<b>Transaction container encoded String (line break added all 100 characters):</b><br>" .
                 $txc_encoded_wrapped . "<br>";
        
        // now post the message
        // get the host URL, see https://stackoverflow.com/questions/6768793/get-the-full-url-in-php
        // and note the security implications: $_SERVER[HTTP_HOST] is set by the client.
        $actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
                 "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $actual_link = substr($actual_link, 0, strrpos($actual_link, "/"));  // cut off file name
        $actual_link = substr($actual_link, 0, strrpos($actual_link, "/"));  // cut off "forms"
        $post_URL = $actual_link . "/api/posttx.php";
        $results_to_show_html .= "<b>Transaction posted to:</b><br>" . $post_URL . "<br>";
        $post_data["txc"] = $txc_encoded;
        // post the message, See
        // https://stackoverflow.com/questions/5647461/how-do-i-send-a-post-request-with-php
        // use key 'http' even if you send the request to https://...
        $options = array(
                'http' => array(
                        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                        'method' => 'POST',
                        'content' => http_build_query($post_data)
                )
        );
        $context = stream_context_create($options);
        $post_result = file_get_contents($post_URL, false, $context);
        if ($post_result === FALSE) {
            $results_to_show_html .= "<b>Transaction post failed. Aborting.</b></p>";
        } else {
            $post_result_wrapped = wordwrap($post_result, 100, "<br>", true);
            $results_to_show_html .= "<b>Transaction post response (line break added all 100 characters):</b><br>" .
                     $post_result_wrapped . "<br>";
            $post_result_decoded = Tx_handler::decode_container($post_result);
            $results_to_show_html .= "<b>Transaction post response decoded:</b><br>" . utf8_encode($post_result_decoded) .
                     "</p>";
        }
    }
}

// ==== continue with the definition and eventually initialization of form to fill in this step
if (($done == 0) || ($todo !== $form_filled->get_index())) {
    // use a new form for the very first form display or the next step.
    if ($done == 0)
        $todo = 1;
        $form_to_fill = new Form($form_layout, $socket, $toolbox, $toolbox->users->user_table_name, $todo, $fs_id);
    if ($todo == 1) {
        $todo = 1;
        $table_names = $socket->get_table_names(false);
        $select_options_list = [];
        foreach ($table_names as $tn)
            $select_options_list[] = $tn . "=" . $tn;
        $form_to_fill->select_options = $select_options_list;
        $form_to_fill->preset_value("tablename1", "~3");
        $form_to_fill->preset_value("type1", "~6");
    }
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
	<h3>Die API testen</h3>
	<p>Hier können Sie die API testen.</p>
</div>

<div class="w3-container">
<?php

echo $toolbox->form_errors_to_html($form_errors);
if ($todo < 2) {
    echo $form_to_fill->get_html($fs_id);
    echo '<h5><br />Ausfüllhilfen</h5><ul>';
    echo $form_to_fill->get_help_html();
    echo "</ul>";
} else {
    echo $results_to_show_html;
}
?></div><?php
end_script();

    