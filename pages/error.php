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
// set default text, which will show up, if "error.php" is called without providing an error
// description.
file_put_contents($lasterrorfile, 
        "no_source;Unbestimmter Fehler;Die Sitzung wurde aufgrund eines unbestimmten Fehlers beendet.");

$file_path_elements = explode("/", $source_file);
$index_last = count($file_path_elements) - 1;
$login_goto = $file_path_elements[$index_last - 1] . "/" . $file_path_elements[$index_last];

include_once '../classes/tfyh_toolbox.php';
$toolbox = new Tfyh_toolbox();
$session_user_valid = (isset($_SESSION) && isset($_SESSION["User"]) &&
         (strcasecmp($_SESSION["User"]["Rolle"], $toolbox->users->anonymous_role) != 0));

// the error may have been caused by an init check. Then the source of the error is this error.php
// file itself. Do not redo those init checks then to avoid endless loops.
if (strrpos($source_file, "error.php") === false) {
    // ===== initialize toolbox and socket and start session.
    $user_requested_file = __FILE__;
    include_once '../classes/init.php';
    // Putting the error to the debug file is needed, if the page was called programmatically and
    // the feedback there not displayed to the user.
    if ($debug)
        file_put_contents("../log/debug_init.log", "  Error: " . $last_error . "\n", FILE_APPEND);
} else {
    include_once '../classes/tfyh_menu.php';
    $menu = new Tfyh_menu("../config/access/pmenu", $toolbox);
}

// count the errors. This can be explicitly suppressed and is always suppressed, if
// overload happens. In user run trigger overload scenarios users tend to retry heavily. That will
// then sustain the overload indication by all the rtries counted as errors.
$blocking_overload = (strcasecmp($toolbox->overload_error_headline, $headline) == 0);
$suppress_counting = (strcmp(substr($headline, 0, 2), "!#") == 0);
if ($suppress_counting)
    $headline = substr($headline, 2);

if ($debug)
    file_put_contents("../log/debug_app.log", date("Y-m-d H:i:s") . "\n  Hit error " . $headline . "\n", 
            FILE_APPEND);

if ($blocking_overload) {
    // overload detected.
    if (! file_exists("../log/overload"))
        mkdir("../log/overload");
    $last_warning_mail = "../log/overload/last_warning_mail";
    if (! file_exists($last_warning_mail) || (intval(file_get_contents($last_warning_mail) < (time() - 600)))) {
        // Send Mail to webmaster and return a very short String.
        require_once '../classes/tfyh_mail_handler.php';
        $mail_handler = new Tfyh_mail_handler($toolbox->config->get_cfg());
        $mail_was_sent = $mail_handler->send_mail($mail_handler->system_mail_sender, 
                $mail_handler->system_mail_sender, $mail_handler->mail_webmaster, "", "", 
                "Lastabwehr bei " . $source_file, 
                "Warnung: Die Lastabwehr auf der Anwendungseite " . $source_file . " hat angeschlagen.");
        if ($mail_was_sent)
            file_put_contents($last_warning_mail, strval(time()));
    }
    
    // Log the timestamps
    $init_pointer = intval(file_get_contents("../log/inits/pointer"));
    $timestamps = "-------------------- Timestamps ------------------\n" . "init timestamps:\n";
    for ($i = - 3; $i < 4; $i ++)
        $timestamps .= strval($init_pointer + $i) . ";" . date("Y-m-d H:i:s", 
                intval(file_get_contents("../log/inits/" . $init_pointer + $i))) . "\n";
    $error_pointer = intval(file_get_contents("../log/inits/pointer"));
    $timestamps .= "error timestamps:\n";
    for ($i = - 3; $i < 4; $i ++)
        $timestamps .= strval($error_pointer + $i) . ";" . date("Y-m-d H:i:s", 
                intval(file_get_contents("../log/errors/" . $error_pointer + $i))) . "\n";
    
    // Log the session details, delete the session
    session_start();
    $session_data = "";
    $timestamps = "-------------------- Server data ------------------\n";
    foreach ($_SERVER as $parm => $value)
        $session_data .= "$parm = '$value'\n";
    $session_data .= "-------------------- \$_SESSION ------------------\n";
    $session_data .= json_encode($_SESSION);
    $session_data .= "\n-------------------- \$_SESSION ------------------";
    file_put_contents("../log/overload/" . strval(time()), $timestamps . $session_data);
    $toolbox->app_sessions->session_close();
    sleep(1);
    
    // Return a very short String, no formatting.
    echo "<html><body><h4>Überlast der Website</h4><p>" . $headline . "</p><p>" . $text . "</p></body></html>";
    if (function_exists("end_script"))
        end_script();
    if (isset($socket))
        $socket->close();
    exit();
} elseif (! $suppress_counting && $session_user_valid) {
    // no overload detected.
    // Count the error, if such error shall be counted.
    $toolbox->load_throttle("errors/", $toolbox->config->settings_tfyh["init"]["max_errors_per_hour"]);
    $toolbox->logger->log_init_login_error("error");
}
// redirect on invalid session, valid page, and no overload to the login page.
if ((! $session_user_valid) && (strrpos($source_file, "login.php") === false) &&
         (strrpos($source_file, "no_source") === false))
    header("Location: $app_root/forms/login.php?goto=" . $login_goto);

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
if (isset($menu)) // the menu was not initialized, in the case that the init call was skipped above.
    echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
?>
<!-- START OF content -->
<div class="w3-container">
	<h2>Fehler</h2>
	<h3>Das hat leider nicht geklappt.</h3>
<?php
echo "<h3><br><br>" . $headline . "</h3>";
echo "<p>" . $text . "</p>";

if ($blocking_overload) {
    require_once '../classes/tfyh_mail_handler.php';
    $mail_handler = new Tfyh_mail_handler($toolbox->config->get_cfg());
    $mail_handler->send_mail($mail_handler->system_mail_sender, $mail_handler->system_mail_sender, 
            $mail_handler->mail_webmaster, "", "", "Lastabwehr hat angeschlagen.", 
            "Warnung: Die Lastabwehr auf der Anwendungseite " . $source_file . " hat angeschlagen.");
}
?>
</div>
<?php
if (function_exists("end_script"))
    end_script();
else {
    echo file_get_contents('../config/snippets/page_03_footer');
    if (isset($socket))
        $socket->close();
}
