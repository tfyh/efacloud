<?php
/**
 * Page display file. A generic error message display page.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox & internationalization.
include_once "../classes/init_i18n.php"; // usually this is included with init.php
include_once '../classes/tfyh_toolbox.php';
$toolbox = new Tfyh_toolbox();
load_i18n_resource($toolbox->config->language_code);

// ===== read error information
$lasterrorfile = "../log/lasterror.txt";
$last_error = file_get_contents($lasterrorfile);
if (($last_error !== false) && (count(explode(";", $last_error)) >= 3)) {
    // "error.php" is called with an error description file provided.
    $error_description = explode(";", $last_error);
    $source_file = $error_description[0];
    $headline = $error_description[1];
    $text = $error_description[2];
    $get_params = (isset($error_description[3])) ? $error_description[3] : "";
} else {
    // "error.php" is called without an error description file provided.
    file_put_contents($lasterrorfile, "-invalid-");
    $source_file = "no_source";
    $headline = i("CVStxl|Undefined error");
    $text = i("Fy3Nzi|It was no longer possibl...");
    $get_params = "";
}

$file_path_elements = explode("/", $source_file);
$index_last = count($file_path_elements) - 1;
$login_goto = $file_path_elements[$index_last - 1] . "/" . $file_path_elements[$index_last] . "?" . $get_params;

// return on concurrency limit violation
$too_many_sessions = (strcasecmp($toolbox->too_many_sessions_error_headline, $headline) == 0);
if ($too_many_sessions) {
    $toolbox->logger->log_init_login_error("error");
    $toolbox->load_warning("sessions", $source_file);
    // Return a very short String, no formatting.
    echo "<html><body><h4>" . i("rcxg1I|Application overload") . "</h4><p>" . $headline . "</p><p>" . $text .
             "</p></body></html>";
    if (function_exists("end_script"))
        end_script();
    exit();
}

// debug logging
if ($toolbox->config->debug_level > 0)
    file_put_contents("../log/debug_app.log", date("Y-m-d H:i:s") . "\n  Hit error " . $headline . "\n", 
            FILE_APPEND);

// throttle on too many the errors. This can be explicitly suppressed, e.g. for notification of app
// config errors.
$suppress_throttling = (strcmp(substr($headline, 0, 2), "!#") == 0);
if ($suppress_throttling)
    $headline = substr($headline, 2);
else {
    $toolbox->load_throttle("errors", $toolbox->config->settings_tfyh["init"]["max_errors_per_hour"], 
            i("2dhcic|Error with") . " " . $source_file);
    $toolbox->logger->log_init_login_error("error");
}

// Only run init.php if the error was NOT caused by an init.php check
if (strrpos($source_file, "error.php") === false) {
    $user_requested_file = __FILE__;
    include_once '../classes/init.php';
    if ($debug)
        file_put_contents("../log/debug_init.log", i("HP00DE|  error: ") . $last_error . "\n", FILE_APPEND);
} else {
    // if init.php is not run, initialize the menu and resume the session
    include_once '../classes/tfyh_menu.php';
    $menu = new Tfyh_menu("../config/access/pmenu", $toolbox);
    $session_open_result = $toolbox->app_sessions->session_open(- 1);
}

// redirect to the login.php page on invalid sessionuser, but valid triggering page (usually a
// session timeout event).
$session_user_valid = (isset($_SESSION) && isset($_SESSION["User"]) &&
         (strcasecmp($_SESSION["User"]["Rolle"], $toolbox->users->anonymous_role) != 0));
if ((! $session_user_valid) && (strrpos($source_file, "login.php") === false) &&
         (strrpos($source_file, "no_source") === false))
    header("Location: $app_root/forms/login.php?goto=" . urlencode($login_goto));

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
echo i("QsB6fc| ** Error ** Sorry, that...");
echo "<h3><br><br>" . $headline . "</h3>";
echo "<p>" . $text . "</p>";
if ($session_user_valid)
    echo "<p><br>" . i("9mmdUG|The session is still act...") . "</p>";
echo i("JZKR1D|</div>");
if (function_exists("end_script"))
    end_script();
else {
    echo file_get_contents('../config/snippets/page_03_footer');
    if (isset($socket))
        $socket->close();
}
