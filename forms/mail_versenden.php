<?php
/**
 * The form for user mailing self service. Based on the Tfyh_form class, please read instructions their to better
 * understand this PHP-code part.
 * 
 * @author mgSoft
 */
// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once '../classes/tfyh_form.php';

$isTempSave = isset($_POST["save"]); // this is just refreshing the session
                                     // if validation fails, the same form will be displayed anew
                                     // with error messages
$tmp_attachement_file = "";
$listparameter = [];
$list_indication = "";
if (isset($_SESSION["getps"][$fs_id]["listparameter"])) {
    $listparameter["{listparameter}"] = $_SESSION["getps"][$fs_id]["listparameter"];
    $list_indication = "Verwendeter Paramter der Liste: {listparameter} = " .
             $listparameter["{listparameter}"] . "\n.";
}

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = ($done == 0) ? 1 : $done;
$form_errors = "";
$form_info = "";
$form_layout = "../config/layouts/mail_versenden";

// ======== Start with form filled in last step: check of the entered values.
if ($done > 0) {
    $form_filled = new Tfyh_form($form_layout, $socket, $toolbox, $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    
    if ($isTempSave) {
        $_SESSION["An"] = $entered_data["An"];
        $_SESSION["Betreff"] = $entered_data["Betreff"];
        $_SESSION["Nachricht"] = $entered_data["Nachricht"];
        $_SESSION["Nachricht_htmle"] = htmlentities(utf8_decode($entered_data["Nachricht"]));
    }
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This only prevents any logic to apply, if form errors
        // occured.
    } elseif (($done == 1) && ! $isTempSave) {
        // get mailto list
        include_once "../classes/tfyh_list.php";
        $list = new Tfyh_list("../config/lists/mailverteiler", 0, $entered_data["An"], $socket, 
                $toolbox, $listparameter);
        $rows = $list->get_rows();
        $mailto_list = [];
        foreach ($rows as $row)
            $mailto_list[] = $row[0];
        $_SESSION["An"] = $entered_data["An"];
        $_SESSION["mailto_list"] = $mailto_list;
        $_SESSION["Betreff"] = $entered_data["Betreff"];
        $_SESSION["Nachricht"] = $entered_data["Nachricht"];
        $_SESSION["Nachricht_htmle"] = htmlentities(utf8_decode($entered_data["Nachricht"]));
        if (strlen($_SESSION["Nachricht_htmle"]) == 0)
            // hit an invalid character, try without utf_8 decode.
            $_SESSION["Nachricht_htmle"] = htmlentities($entered_data["Nachricht"]);
        if (strlen($_SESSION["Nachricht_htmle"]) == 0)
            // still hit an invalid character, use plain
            $_SESSION["Nachricht_htmle"] = $_SESSION["Nachricht"];
        // copy uploaded attachments and remember their location
        if (file_exists($_FILES['userfile1']["tmp_name"])) {
            $_SESSION["Anlage1"] = date("YmdHi", time()) . "_" . $_FILES['userfile1']["name"];
            copy($_FILES['userfile1']["tmp_name"], "../attachements/" . $_SESSION["Anlage1"]);
        } else
            $_SESSION["Anlage1"] = "";
        if (file_exists($_FILES['userfile2']["tmp_name"])) {
            $_SESSION["Anlage2"] = date("YmdHi", time()) . "_" . $_FILES['userfile2']["name"];
            copy($_FILES['userfile2']["tmp_name"], "../attachements/" . $_SESSION["Anlage2"]);
        } else
            $_SESSION["Anlage2"] = "";
        $form_info = "<p><b>Empfänger: </b>" . $_SESSION["An"] . " (Anzahl: " .
                 count($_SESSION["mailto_list"]) . ")</p><p>" . "<p><b>Betreff: </b>" .
                 $entered_data["Betreff"] . "</p><p>" . "<p><b>Nachricht:</b><br />" .
                 str_replace("\n", "<br />", $_SESSION["Nachricht_htmle"]) .
                 "<p><b>Anlage 1:</b><br />" . $_SESSION["Anlage1"] . "<br /><b>Anlage 2:</b><br />" .
                 $_SESSION["Anlage2"] . "</p><hr /><br />";
        $todo = 2;
    } elseif (($done == 2) || $isTempSave) {
        
        // check for test mode. If this is a test, replace mailto-list by user mail
        $isTest = ($entered_data["Testversand"]);
        if ($isTest) {
            $_SESSION["mailto_list"] = [];
            $_SESSION["mailto_list"][] = $_SESSION["User"][$toolbox->users->user_id_field_name];
        }
        
        // check for continued edit mode after test. If this is a continuation of editing,
        // delete the mailto-list
        $isContinueEdit = (isset($_GET["edit"]) && (intval($_GET["edit"]) == 1));
        if ($isContinueEdit || $isTempSave)
            $_SESSION["mailto_list"] = [];
        
        // create mails to users. Prepare.
        require_once '../classes/tfyh_mail_handler.php';
        $mail_handler = new Tfyh_mail_handler($toolbox->config->get_cfg());
        $successes = 0;
        $i = 0;
        $user_name = $_SESSION["User"]["Vorname"] . " " . $_SESSION["User"]["Nachname"];
        $mailfrom = "" . $user_name . " " . $mail_handler->mail_subject_acronym . " <" .
                 $mail_handler->mail_mailer . ">";
        $mailreplyto = " " . $_SESSION["User"]["EMail"];
        
        // create mails one by one. Note: for ($isContinueEdit || $isTempSave) the
        // $_SESSION["mailto_list"] is empty, for $isTest it contains the user himself only.
        $message_template = str_replace("\n", "<br />", $_SESSION["Nachricht_htmle"]);
        foreach ($_SESSION["mailto_list"] as $mailto_user_id) {
            $user_mailto = $socket->find_record($toolbox->users->user_table_name, 
                    $toolbox->users->user_id_field_name, $mailto_user_id);
            $message = $message_template;
            if (strpos($message, "{#Anrede#}") !== false) {
                $anrede = (isset($user_mailto["Geschlecht"])) ? (strcasecmp("m", 
                        $user_mailto["Geschlecht"]) === 0) ? "<p>Lieber " : "<p>Liebe " : "<p>Liebe(r) ";
                $anrede .= $user_mailto[$toolbox->users->user_firstname_field_name] . " " .
                         $user_mailto[$toolbox->users->user_lastname_field_name];
                $message = str_replace("{#Anrede#}", $anrede, $message);
            }
            if (strpos($message, "{#Profil#}") !== false) {
                $profile = $toolbox->users->get_user_profile($mailto_user_id, $socket);
                $message = str_replace("{#Profil#}", $profile, $message);
            }
            if (strpos($message, "{#LoginToken+") !== false) {
                $message_parts = explode("{#LoginToken+", $message);
                $token_params = explode("#}", $message_parts[1])[0];
                $message_end = explode("#}", $message_parts[1])[1];
                $plus_days = intval(explode("+", $token_params)[0]);
                $deep_link = (count(explode("+", $token_params)) > 1) ? explode("+", $token_params)[1] : "../pages/home.php";
                $login_token = $toolbox->create_login_token($user_mailto["EMail"], $plus_days, 
                        $deep_link);
                // add a line feed to ensure thet the link itself will not be broken by line feed
                // insertion (998 characters limit rule).
                $message = $message_parts[0] . "\n<a href='" . $toolbox->config->app_url .
                         "/forms/login.php?token=" . urlencode($login_token) . "'>Direkteinstieg</a>" .
                         $message_end;
            }
            foreach ($user_mailto as $key => $value) {
                if (strpos($message, "{#" . $key . "#}") !== false)
                    $message = str_replace("{#" . $key . "#}", $value, $message);
            }
            $message .= $mail_handler->mail_footer;
            $this_mailto = $toolbox->strip_mail_prefix($user_mailto["EMail"]);
            $attachment1 = ($_SESSION["Anlage1"]) ? "../attachements/" . $_SESSION["Anlage1"] : "";
            $attachment2 = ($_SESSION["Anlage2"]) ? "../attachements/" . $_SESSION["Anlage2"] : "";
            $mail_was_sent = $mail_handler->send_mail($mailfrom, $mailreplyto, $this_mailto, "", "", 
                    $mail_handler->mail_subject_acronym . $_SESSION["Betreff"], $message, 
                    $attachment1, $attachment2);
            if (! $mail_was_sent)
                $form_info .= "Versand fehlgeschlagen für: '" . $this_mailto . "'.<br />";
            else
                $successes ++;
        }
        
        // create reciept to sender and remove attachment
        if (! $isContinueEdit && ! $isTempSave && ($successes > 0)) {
            $mail_db_insert_result = "Keine Speicherung, Testmodus.";
            if (! $isTest) {
                // move attachement into sent-directory.
                // Attachments therefore get a preceding reverse timestamp in the name.
                rename("../attachements/" . $_SESSION["Anlage1"], 
                        "../attachements/sent/" . $_SESSION["Anlage1"]);
                rename("../attachements/" . $_SESSION["Anlage2"], 
                        "../attachements/sent/" . $_SESSION["Anlage2"]);
                // store mail to database for logging purposes
                $record[$toolbox->users->user_id_field_name] = $_SESSION["User"][$toolbox->users->user_id_field_name];
                $record["versendetAm"] = date("Y-m-d H:i:s");
                $record["Verteiler"] = $_SESSION["An"];
                $record["Anzahl"] = $successes;
                $record["Betreff"] = $_SESSION["Betreff"];
                $record["Nachricht"] = $_SESSION["Nachricht"];
                $record["Anlage1"] = $_SESSION["Anlage1"];
                $record["Anlage2"] = $_SESSION["Anlage2"];
                $mail_db_insert_result = $socket->insert_into(
                        $_SESSION["User"][$toolbox->users->user_id_field_name], "Mails", $record);
                
                // trigger showing of result without the send form.
                $todo = 3;
            }
            $form_info .= "Die Nachricht wurde an " . $successes .
                     " Mitglieder versendet und in der Datenbank gespeichert mit dem Ergebnis: " .
                     $mail_db_insert_result;
            $_SESSION["result"] = $form_info;
            $mail_was_sent = $mail_handler->send_mail($mailfrom, $mailreplyto, 
                    $_SESSION["User"]["EMail"], "", "", 
                    $mail_handler->mail_subject_acronym . $_SESSION["Betreff"], 
                    $form_info . $list_indication);
        } else { // When testing show form again to be able to do adjustments.
            if ($isTest || $isContinueEdit || $isTempSave) {
                if ($isTest)
                    $form_info .= "Nach Testversand kann die Nachricht noch editiert werden.";
                elseif ($isContinueEdit)
                    $form_info .= "Geprüfte Nachricht ändern. ";
                else
                    $form_info .= "Die Sitzung wurde aktualisiert. ";
                $form_info .= "HINWEIS: Anlagen wurden entfernt und müssen erneut angefügt werden.";
                // prefill form with previous values.
                $form_filled = new Tfyh_form($form_layout, $socket, $toolbox, 1, $fs_id);
                $preset["An"] = $_SESSION["An"];
                $preset["Betreff"] = $_SESSION["Betreff"];
                $preset["Nachricht"] = $_SESSION["Nachricht"];
                $form_filled->preset_values($preset);
                // remove attachment, as test sending is not stored permanently.
                unlink("../attachements/" . $_SESSION["Anlage1"]);
                unlink("../attachements/" . $_SESSION["Anlage2"]);
                // trigger new entry.
                $todo = 1;
            } elseif ($successes == 0) {
                $form_info .= "Weil diese Mail an niemanden gesendet werden konnte, " .
                         "wurde sie auch nicht in den versendeten Mails gespeichert.<br />";
                $todo = 3;
            }
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
	<h3>Mails an Verteiler versenden</h3>
	<p>Die Mails werden einzeln versendet, immer mit nur einem
		"An"-Empfänger. Je nach Menge kann das etwas dauern und auch auf der
		Empfängerseite zu Spam-Einordnung führen. Daher bitte unbedingt
		sparsam verwenden.</p>
	<p>
		<b>ACHTUNG:</b> Nach 10 Minuten endet die Sitzung, wenn nicht eine
		neue Seite aufgerufen wird.
	
	
	<p>
<?php
echo $toolbox->form_errors_to_html($form_errors);
echo $form_info;
if ($todo < 3)
    echo $form_to_fill->get_html(true); // enable file upload
?>
	<!-- END OF form -->

</div>
<?php
end_script();

