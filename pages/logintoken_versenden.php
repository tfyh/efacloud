<?php
/**
 * The page to sen a login-token to any user.
 * 
 * @author mgSoft
 */
// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

$tmp_attachement_file = "";
$id = (isset($_SESSION["getps"][$fs_id]["id"])) ? $_SESSION["getps"][$fs_id]["id"] : 0;
if ($id == 0)
    $toolbox->display_error("Nicht zulässig.", 
            "Die Seite '" . $user_requested_file . "' muss mit der Angabe der ID des zu adressierenden " .
                     "Nutzers aufgerufen werden.", $user_requested_file);
$user_mailto = ($id == 0) ? false : $socket->find_record("efaCloudUsers", "ID", $id);
if ($user_mailto == false)
    $toolbox->display_error("Nicht gefunden.", 
            "Der Nutzer für den Versand des Login-Tokens wurde nicht gefunden.", $user_requested_file);

// create mails to users. Prepare.
require_once '../classes/tfyh_mail_handler.php';
$cfg = $toolbox->config->get_cfg();
$cfg["mail_subject_acronym"] = $cfg["acronym"]; // acronym is the club's acronym in efaCloud.
$mail_handler = new Tfyh_mail_handler($toolbox->config->get_cfg());
$user_name = $user_mailto["Vorname"] . " " . $user_mailto["Nachname"];
$mailfrom = "Fahrtenbuch " . $mail_handler->mail_subject_acronym . " <" . $mail_handler->mail_schriftwart . ">";

// create mails one by one. Note: for ($isContinueEdit || $isTempSave) the
$anrede = (isset($user_mailto["Geschlecht"])) ? (strcasecmp("m", $user_mailto["Geschlecht"]) === 0) ? "<p>Lieber " : "<p>Liebe " : "<p>Liebe(r) ";
$anrede .= $user_mailto[$toolbox->users->user_firstname_field_name] . " " .
         $user_mailto[$toolbox->users->user_lastname_field_name];
$plus_days = 2;
$deep_link = "../forms/profil_aendern.php?id=" . $id . "&pw=1";
$login_token = $toolbox->create_login_token($user_mailto["EMail"], $plus_days, $deep_link);

$message = "<p>" . $anrede . "<br>" . "Um Dein Passwort zu setzen, nutze bitte folgenden \n<a href='" .
         $app_root . "/forms/login.php?token=" . urlencode($login_token) .
         "'>Direkteinstieg</a>. " . "Bitte beachte, dass dieser Weg maximal " . $plus_days .
         " Tage lang funktioniert. " . "Danach wird er aus Sicherheitsgründen gesperrt.<br>" .
         "Viel Erfolg!<br>" . $mail_handler->mail_subscript . "</b>";
$message .= $mail_handler->mail_footer;
$this_mailto = $toolbox->strip_mail_prefix($user_mailto["EMail"]);
$mail_was_sent = $mail_handler->send_mail($mailfrom, $mailfrom, $this_mailto, "", "",
        $mail_handler->mail_subject_acronym . "Kennwort für efaCloud setzen", $message, "", "");
if ($mail_was_sent) {
    $info = "<p>Versand erfolgreich für: '" . $this_mailto . "'.</p>";
    $toolbox->logger->log(0, $_SESSION["User"]["efaCloudUserID"], 
            "Login token versendet an Nutzer: " . $user_name . "(" . $id . ").");
} else {
    $info = "<p><b>Versand fehlgeschlagen</b> für: '" . $this_mailto . " (<a href='" .
             $toolbox->config->app_url . "/forms/login.php?token=" . urlencode($login_token) .
             "'>Direkteinstieg</a>)" . "'.</p>";
    $toolbox->logger->log(2, $_SESSION["User"]["efaCloudUserID"], 
            "Login token an Nutzer: " . $user_name . "(" . $id . ") konnte nicht gesendet werden.");
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
	<h3>Mails Nutzer versenden</h3>
	<p>Eine Mail zum Direkteinstieg für die Vergabe eines Passwortes wurde
		zum Versand erzeugt.</p>
<?php
echo $info;
?>
	<!-- END OF Content -->
</div>
<?php
end_script();

