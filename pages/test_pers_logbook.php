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
$user_mailto = $_SESSION["User"];
if (! isset($user_mailto["EMail"]))
    $toolbox->display_error("Keine Mail-Adresse.", 
            "Der Nutzer für den Testversand des persönlichen Logbuchs hat keine Mail-Adresse hinterlegt.", 
            $user_requested_file);

// create mails to user. Prepare logbook.
include_once '../classes/efa_dataedit.php';
$efa_dataedit = new Efa_dataedit($toolbox, $socket);
include_once '../classes/efa_logbook.php';
$efa_logbook = new Efa_logbook($toolbox, $socket, $efa_dataedit);
$mails_sent = $efa_logbook->send_logbooks(true);
if ($mails_sent > 0)
    $info = "<p>Versand an $mails_sent Adressse erfolgreich.</p>";
else
    $info = "<p><b>Versand fehlgeschlagen</b>. Der wahrscheinlichste Grund ist, dass in Deinem " .
             "Fahrtenbuch keine Fahrten sind, alternativ könnte noch sein, dass Deine Email-Adresse " .
             "als efaCloudUser nicht mit der E-Mail-Adresse übereinstimmt, die in efa hinterlegt ist.</p>";

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Testversand persönliches Fahrtenbuch</h3>
<?php
echo $info;
?>
	<!-- END OF Content -->
</div>
<?php
end_script();
