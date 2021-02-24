<?php
/**
 * The form for user mailing self service.
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
$isTempSave = isset($_POST["save"]); // this is just refreshing the session
                                     // if validation fails, the same form will be displayed anew
                                     // with error messgaes
$todo = $done;
$form_errors = "";
$form_info = "";
$form_layout = "../config/layouts/mail_versenden";
$tmp_attachement_file = "";

if ($done > 0) {
    
    $form_filled = new Form($form_layout, $socket, $toolbox, "Mails", $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    
    if ($isTempSave) {
        $_SESSION["An"] = $entered_data["An"];
        $_SESSION["Betreff"] = $entered_data["Betreff"];
        $_SESSION["Nachricht"] = $entered_data["Nachricht"];
    }
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This only prevents any logic to apply, if form errors
        // occured.
    } elseif (($done == 1) && ! $isTempSave) {
        // get mailto list
        include_once "../classes/tfyh_list.php";
        $list = new Tfyh_list("../config/lists/mailverteiler", 0, $entered_data["An"], $socket, 
                $toolbox);
        $rows = $list->get_rows();
        $mailto_list = [];
        foreach ($rows as $row)
            $mailto_list[] = $row[0];
        $_SESSION["An"] = $entered_data["An"];
        $_SESSION["mailto_list"] = $mailto_list;
        $_SESSION["Betreff"] = $entered_data["Betreff"];
        $_SESSION["Nachricht"] = $entered_data["Nachricht"];
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
                 str_replace("\n", "<br />", $entered_data["Nachricht"]) .
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
        require_once '../classes/mail_handler.php';
        $mail_handler = new Mail_handler($toolbox->config->get_cfg());
        $successes = 0;
        $i = 0;
        $user_name = $_SESSION["User"]["Vorname"] . " " . $_SESSION["User"]["Nachname"];
        $mailfrom = "" . $user_name . " [FvSSP] <" . $mail_handler->mail_mailer . ">";
        $mailreplyto = " " . $_SESSION["User"]["EMail"];
        
        // create mails one by one. Note: for ($isContinueEdit || $isTempSave) the
        // $_SESSION["mailto_list"] is empty, for $isTest it contains the user himself only.
        foreach ($_SESSION["mailto_list"] as $mitgliedsnummer) {
            $user_mailto = $socket->find_record($toolbox->users->user_table_name, $toolbox->users->user_id_field_name, 
                    $mitgliedsnummer);
            $message = str_replace("\n", "<br />", $_SESSION["Nachricht"]);
            if (strpos($message, "{#Anrede#}") !== false) {
                $anrede = (strcasecmp("m", $user_mailto["Geschlecht"]) === 0) ? "<p>Lieber " : "<p>Liebe ";
                $anrede .= $user_mailto["Vorname"] . " " . $user_mailto["Nachname"];
                $message = str_replace("{#Anrede#}", $anrede, $message);
            }
            if (strpos($message, "{#Profil#}") !== false) {
                $profile = $toolbox->users->get_user_profile($mitgliedsnummer, $socket);
                $message = str_replace("{#Profil#}", $profile, $message);
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
                    "[FvSSP] " . $_SESSION["Betreff"], $message, $attachment1, $attachment2);
            if (! $mail_was_sent)
                $form_info .= "Versand fehlgeschlagen für: ' . $this_mailto . '.<br />";
            else
                $successes ++;
        }
        
        // create reciept to sender and remove attachment
        if (! $isContinueEdit && ! $isTempSave && ($successes > 0)) {
            $form_info .= "Die Nachricht wurde an " . $successes . " Mitglieder versendet. " .
                     $_SESSION["result"] = $form_info;
            $mail_was_sent = $mail_handler->send_mail($mailfrom, $mailreplyto, 
                    $_SESSION["User"]["EMail"], "", "", "[FvSSP] " . $_SESSION["Betreff"], 
                    "Die Nachricht wurde an " . $successes . " Mitglieder versendet. " . $form_info);
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
                $socket->insert_into($_SESSION["User"][$toolbox->users->user_id_field_name], "Mails", $record);
                // trigger showing of result without the send form.
                $todo = 3;
            }
        } else  // When testing show form again to be able to do adjustments.
            if ($isTest || $isContinueEdit || $isTempSave) {
                if ($isTest)
                    $form_info .= "Nach Testversand kann die Nachricht noch editiert werden.";
                elseif ($isContinueEdit)
                    $form_info .= "Geprüfte Nachricht ändern. ";
                else
                    $form_info .= "Die Sitzung wurde aktualisiert. ";
                $form_info .= "HINWEIS: Anlagen wurden entfernt und müssen erneut angefügt werden.";
                // prefill form with previous values.
                $form_filled = new Form($form_layout, $socket, $toolbox, $toolbox->users->user_table_name, 1, $fs_id);
                $preset["An"] = $_SESSION["An"];
                $preset["Betreff"] = $_SESSION["Betreff"];
                $preset["Nachricht"] = $_SESSION["Nachricht"];
                $form_filled->preset_values($preset);
                // remove attachment, as test sending is not stored permanently.
                unlink("../attachements/" . $_SESSION["Anlage1"]);
                unlink("../attachements/" . $_SESSION["Anlage2"]);
                // trigger new entry.
                $todo = 1;
            }
    }
}

// ==== continue with the definition and eventually initialization of form to fill in this step
if (($done == 0) || ($todo !== $form_filled->get_index())) {
    // use a new form for the very first form display or the next step.
    if ($done == 0)
        $todo = 1;
        $form_to_fill = new Form($form_layout, $socket, $toolbox, $toolbox->users->user_table_name, $todo, $fs_id);
} else {
    // or reuse the 'done' form, if validation failed (default behaviour,
    // here also for retry after test sending.)
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
	<h3>Mails an Verteiler versenden</h3>
	<h4>Hier kannst Du Mails an Verteiler, denen Du angehörst, versenden.</h4>
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

