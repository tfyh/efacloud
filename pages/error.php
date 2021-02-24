<?php
/**
 * Page display file. A generic error message display page.
 *
 * @author mgSoft
 */
// ===== read error information
$lasterrorfile = "../log/lasterror.txt";
$last_error = file_get_contents($lasterrorfile);
$error_description = explode(";", $last_error);
$source_file = $error_description[0];
$headline = $error_description[1];
$text = $error_description[2];
// set default text, which will show up, if "error.php" is called without providing an error description.
file_put_contents($lasterrorfile, 
        "no_source;Unbestimmter Fehler;Die Sitzung wurde aufgrund eines unbestimmten Fehlers beendet.");

// the error may have been caused ba an init check. Then the source of the error is this error.php file itself
// Do not redo those init checks then to avoid endless loops.
if (strrpos($source_file, "error.php") !== false) {
    // ===== initialize toolbox and socket and start session.
    $user_requested_file = __FILE__;
    include_once '../classes/init.php';
    // Putting the error to the debug file is needed, it the page was called programmatically and the feedback
    // there not displayed to the user.
    if ($debug)
        file_put_contents("../log/initdebug.log", "  Error: " . $last_error . "\n", FILE_APPEND);
} else {
    include_once '../classes/toolbox.php';
    $toolbox = new Toolbox();
    include_once '../classes/menu.php';
    $menu = new Menu("../config/access/pmenu", $toolbox);
}

// count the errors. This can be explicitly suppressed and is always suppressed, if
// overload happens. In user run trigger overload scenarios users tend to retry heavily. That will
// then sustain the overload indication by all the rtries counted as errors. 
$suppress_counting = (strcmp(substr($headline, 0, 2), "!#") == 0);
if ($suppress_counting)
    $headline = substr($headline, 2);
    $blocking_overload = (strcasecmp($toolbox->overload_error_headline, $headline) == 0);
if (! $blocking_overload && ! $suppress_counting) {
    $toolbox->load_throttle("errors/", 300);
    $toolbox->logger->log_activity("err");
}

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
if (isset($menu))    // the menu was not initialized, in the case that the init call was skipped above.
    echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
?>
<!-- START OF content -->
<div class="w3-container">
	<h2>Fehler</h2>
	<h3>Das hat leider nicht geklappt.</h3>
	<h3>
		<br> <br><?php echo $headline; ?></h3>
	<p><?php echo $text; ?></p>
	<p>Zurück zur Sitzung geht es per Browser-Befehl, wenn sie nicht
		beendet wurde.</p>
</div>

<?php
if ($blocking_overload) {
    require_once '../classes/mail_handler.php';
    $mail_handler = new Mail_handler($toolbox->config->get_cfg());
    $mail_handler->send_mail($mail_handler->system_mail_sender, $mail_handler->system_mail_sender, 
            $mail_handler->mail_webmaster, "", "", "Lastabwehr hat angeschlagen.", 
            "Warnung: Die Lastabwehr auf der Anwendungseite hat angeschlagen.");
}
if (function_exists("end_script"))
    end_script();
else {
    echo file_get_contents('../config/snippets/page_03_footer');
    if (isset($socket))
        $socket->close();
}
