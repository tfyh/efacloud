<?php
/**
 * snippet to start all forms and pages. Controls load, user identity and opens the data base access. Provides
 * the functions called on script end.
 */

// ===== MAINTENANCE AND DBUGGING =========================================
// Maintenance page can be inserted here by setting a value to $maintenance_until
$maintenance_until = ""; // e.g.: $maintenance_until = "31.1.2021, 18:00h";
if (strlen($maintenance_until) > 3)
    echo header("Location: ../public/maintenance.php?until=" . urlencode($maintenance_until));

// ===== global function to support performance monitoring
$perf_methods = [];
$perf_times = [];

// ===== performance logging
function perf_log (String $method)
{
    global $perf_methods, $perf_times;
    $perf_methods[] = $method;
    $perf_times[] = microtime(true);
}

// ===== global functions for application session control and monitoring.
// close the data base socket and echo the footer at the end of the script execution.
function end_script (bool $add_footer = true)
{
    global $toolbox;
    global $socket;
    global $connected;
    global $script_completed;
    global $debug;
    global $user_requested_action;
    global $php_script_started_at;
    if ($add_footer) {
        echo "\n<script>var php_languageCode = '" . $toolbox->config->language_code . "';</script>\n";
        echo file_get_contents('../config/snippets/page_03_footer');
    }
    if ($connected === true)
        $socket->close();
    $connected = false;
    $script_completed = true;
    if ($debug)
        file_put_contents(__DIR__ . "/../log/debug_init.log", 
                "  script closed at " . date("Y-m-d H:i:s") . ".\n", FILE_APPEND);
    $session_user = (isset($_SESSION["User"][$toolbox->users->user_id_field_name])) ? intval(
            $_SESSION["User"][$toolbox->users->user_id_field_name]) : 0;
    $toolbox->logger->put_timestamp($session_user, $user_requested_action, $php_script_started_at);
}

// if the script end was not reached, which happens typically in file download scripts, but also
// in error cases, shut down the data base connection.
function shutdown ()
{
    global $toolbox;
    global $socket;
    global $connected;
    global $script_completed;
    global $debug;
    global $user_requested_action;
    global $php_script_started_at;
    global $perf_methods, $perf_times;
    
    if (count($perf_methods) > 0) {
        $sys_performance_log = __DIR__ . "/../log/sys_performance.log";
        file_put_contents($sys_performance_log, "Page performance log.\n");
        for ($i = 0; $i < count($perf_methods); $i ++)
            file_put_contents($sys_performance_log, $perf_methods[$i] . ";" . $perf_times[$i] . "\n", 
                    FILE_APPEND);
    }
    if ($script_completed)
        return;
    if ($debug)
        file_put_contents(__DIR__ . "/../log/debug_init.log", 
                "  ### " . i("uvau7j|WARNING: script did not ...") . "\n", FILE_APPEND);
    if ($connected === true)
        $socket->close();
    $connected = false;
    file_put_contents(__DIR__ . "/../log/sys_shutdowns.log", 
            date("Y-m-d H:i:s") . ": " . i("u9WjgW|Shutting down %1. Script...", $user_requested_action, 
                    strval($php_script_started_at)) . "\n", FILE_APPEND);
    
    $error = error_get_last();
    if (($error !== NULL) && isset($error["type"]) && (intval($error["type"]) == E_ERROR)) {
        $errinfo = "File : " . $error["file"] . ", Line : " . $error["line"] . ", Message : " .
                 $error["message"];
        file_put_contents(__DIR__ . "/../log/sys_shutdowns.log", 
                date("Y-m-d H:i:s") . ": Last Error = " . $errinfo . "\n", FILE_APPEND);
        echo "<h1>" . i("Pj5VdW|Oops! A fatal error.") . "</h1><p>" . $errinfo . ".</p><p>" . i(
                "IGCugZ| ** Please help to impro...") . "</p>";
    }
}
register_shutdown_function('shutdown');

// ===== THE REAL INITIALIZATION SCRIPT =======================================
// ===== timestamps
$php_script_started_at = microtime(true);
$script_completed = false;

// ===== initialize toolbox & internationalization.
include_once "../classes/init_i18n.php"; // not part of init for api, logout and error
include_once '../classes/tfyh_toolbox.php';
$toolbox = new Tfyh_toolbox();
load_i18n_resource($toolbox->config->language_code);

// ===== register the requested file for later authorization
if (! isset($user_requested_file)) {
    $user_requested_file = "none"; // fool the lint check for unset variables.
    $toolbox->display_error(i("cq6KYu|Invalid call"), i("5LFPnH|No page identification w..."), 
            $user_requested_file);
}
$file_path_elements = explode("/", $user_requested_file);
$index_last = count($file_path_elements) - 1;
$user_requested_action = $file_path_elements[$index_last - 1] . "/" . $file_path_elements[$index_last];
// resolve app root URL for use in scripts.
$app_root = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
         "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
// cut off get parameters
$app_root = (strrpos($app_root, "?") !== false) ? substr($app_root, 0, strrpos($app_root, "?")) : $app_root;
// cut off last two path elements
$app_root = (strrpos($app_root, "/") !== false) ? substr($app_root, 0, strrpos($app_root, "/")) : "Server_missing/somehow";
$app_root = substr($app_root, 0, strrpos($app_root, "/")); // e.g.: "https://rcwb.de/efacloud"
$app_domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
         "://$_SERVER[HTTP_HOST]"; // e.g.:
                                  // "https://rcwb.de"
$app_subdirectory = mb_substr($app_root, mb_strlen($app_domain) + 1); // e.g.: "efacloud"
                                                                      
// ===== throttle to prevent from machine attacks. Will return to the user in overload situations.
$toolbox->load_throttle("inits", $toolbox->config->settings_tfyh["init"]["max_inits_per_hour"], 
        $user_requested_file);
$toolbox->logger->log_init_login_error("init");

// ===== remove any existiong tfyh user and session cookie
setcookie("tfyhUserID", "", time() - 3600);
setcookie("tfyhSessionID", "", time() - 3600);

// ===== Try to open an existing session.
$session_open_result = $toolbox->app_sessions->session_open(- 1);
// load throttling
if ($session_open_result == false) {
    $script_completed = true;
    $toolbox->display_error($toolbox->too_many_sessions_error_headline, 
            i("ATSnFO|There are too many users..."), $user_requested_file);
}
// keep anonymous sessions only, if a form was requested (like login or registrations).
if (! isset($_SESSION["User"]))
    $_SESSION["User"] = $toolbox->users->get_empty_user();
$user_id = (isset($_SESSION["User"][$toolbox->users->user_id_field_name])) ? intval(
        $_SESSION["User"][$toolbox->users->user_id_field_name]) : - 1;
$is_user_request_for_form = strcasecmp($file_path_elements[$index_last - 1], "forms") == 0;
if (! $is_user_request_for_form && ($user_id == - 1)) {
    // drop app session, if no form was requested
    $toolbox->app_sessions->session_close(
            i("CW7uhM|anonymous request for no...", $file_path_elements[$index_last - 1], 
                    $file_path_elements[$index_last]), "");
}

$debug = ($toolbox->config->debug_level > 0);
if ($debug)
    file_put_contents("../log/debug_init.log", 
            date("Y-m-d H:i:s") . "\n  " . i("JCP71T|File: %1  User after se...", $user_requested_file, 
                    $user_id, 
                    ((isset($_SESSION["User"]["Rolle"])) ? $_SESSION["User"]["Rolle"] : i(
                            "YXYsQR|[undefined]"))) . "\n", FILE_APPEND);

// ===== identify current context, i. e. the parent directory's parent.
// The application holds all executable code in directories at the application root. Multiple
// applications of such type may reside in one web server serving different tenants. The session
// must
// recognise, if the application root was changed, to prevent users from using their access rights
// in any
// other tenant.
$context = getcwd();
$context = substr($context, 0, strrpos($context, "/"));
if ($debug) {
    $session_context_prev = (isset($_SESSION["context"])) ? $_SESSION["context"] : i("pRZsYG|[not available]");
    $session_user_dbg = (isset($_SESSION["User"])) ? $_SESSION["User"] : i("353Us1|[not available]");
    file_put_contents("../log/debug_init.log", 
            "  " . i("RHGlRZ|Session context: %1, cur...", $session_context_prev, $context) . "\n", 
            FILE_APPEND);
}

// ===== add the context, if not yet added and check it.
if (! isset($_SESSION["context"]))
    $_SESSION["context"] = $context;
elseif (strcmp($_SESSION["context"], $context) != 0) {
    // wrong tenant. Clear all user settings, because they stem from a different tenant.
    $prev_context = $_SESSION["context"];
    $toolbox->app_sessions->session_close("Forbidden context change", "");
    $script_completed = true;
    $toolbox->display_error(i("ckQubu|Invalid context switch"), 
            i("XtZapR|A change from context: %...", $prev_context, $context), $user_requested_file);
}

// ===== initialize the data base socket. Test the data base connection
include_once '../classes/tfyh_socket.php';
$connected = false;
if (! isset($dbconnect)) {
    $socket = new Tfyh_socket($toolbox);
    $connected = $socket->open_socket();
    if ($connected !== true) {
        $script_completed = true;
        $toolbox->display_error(i("A9f60R|Database connection fail..."), $connected, $user_requested_file);
    }
}

// ===== resolve and update user
// cache the current role which may be different from the users default role (less powerful, for testing
// purposes)
$cached_session_role = (isset($_SESSION["User"]) && isset($_SESSION["User"]["Rolle"])) ? $_SESSION["User"]["Rolle"] : $toolbox->users->anonymous_role;
$_SESSION["User"] = $toolbox->users->get_empty_user();
// re-read user from data base with possibly updated properties
if ($user_id >= 0) {
    $refreshed_user = $socket->find_record($toolbox->users->user_table_name, 
            $toolbox->users->user_id_field_name, $user_id);
    if ($refreshed_user != false) {
        $_SESSION["User"] = $refreshed_user;
        // restore the session role
        $_SESSION["User"]["Rolle"] = $cached_session_role;
    }
}
// ===== load menu
$menu_template = (strcasecmp($_SESSION["User"]["Rolle"], $toolbox->users->anonymous_role) == 0) ? "pmenu" : "imenu";
if ($debug)
    file_put_contents("../log/debug_init.log", 
            "  " . i("eI2ua5|User after DB check: app...", $user_id) . $_SESSION["User"]["Rolle"] . "\n", 
            FILE_APPEND);
include_once '../classes/tfyh_menu.php';
$menu = new Tfyh_menu("../config/access/" . $menu_template, $toolbox);

// ===== authorize user for action
if (! $menu->is_allowed_menu_item($user_requested_file)) {
    $script_completed = true;
    $toolbox->display_error(i("lTNFEv|Not allowed."), 
            i("D7SPTM|The role °%1° has no per...", $_SESSION["User"]["Rolle"], $user_requested_action), 
            $user_requested_file);
}

// ===== form sequence check. Using the fs_id all actions can be distinguished in a multitab
// user session. Actually these tokens are generated for all pages, not only forms, but for
// forms they are crucial.
$done = 0;
$fs_id = "";
if (isset($_GET["fseq"])) {
    $seq_error_head = i("WWu9LQ|Error in squence of form...");
    $seq_error_text = i("usHKvV|An invalid form sequence...");
    $script_completed = true; // for any of the following errors
    if (strlen($_GET["fseq"]) != 6)
        $toolbox->display_error($seq_error_head, $seq_error_text, $user_requested_file);
    $done = intval(substr($_GET["fseq"], 5, 1));
    if ($done == 0)
        $toolbox->display_error($seq_error_head, $seq_error_text, $user_requested_file);
    $fs_id = substr($_GET["fseq"], 0, 5);
    if (! isset($_SESSION["forms"]))
        $toolbox->display_error(i("x8hxVv|Timeout due to inactivit..."), 
                i("yf8erz|Unfortunately, form proc..."), $user_requested_file);
    if (! isset($_SESSION["forms"][$fs_id]))
        $toolbox->display_error($seq_error_head, $seq_error_text, $user_requested_file);
    $script_completed = false; // continued execution
} else {
    $fs_id = $toolbox->generate_token(5, true);
    $_SESSION["forms"][$fs_id] = [];
    $_SESSION["getps"][$fs_id] = [];
}
// ===== collect all values of the Get parameter, merge them over all form sequence steps
foreach ($_GET as $gkey => $gvalue)
    $_SESSION["getps"][$fs_id][$gkey] = $gvalue;

// now set socket listeners, if required
// was for efaCloud partners, now removed (27.02.2022)
