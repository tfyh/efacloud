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
    if ($add_footer)
        echo file_get_contents('../config/snippets/page_03_footer');
    if ($connected === true)
        $socket->close();
    $connected = false;
    $script_completed = true;
    if ($debug)
        file_put_contents(__DIR__ . "/../log/debug_init.log", 
                "  script closed at " . date("Y-m-d H:i:s") . ".\n", FILE_APPEND);
    $session_user = (isset($_SESSION["User"][$toolbox->users->user_id_field_name])) ? $_SESSION["User"][$toolbox->users->user_id_field_name] : 0;
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
    
    if ($script_completed)
        return;
    if ($debug)
        file_put_contents(__DIR__ . "/../log/debug_init.log", 
                "  ### WARNING: script did not reach the page footer output.\n", FILE_APPEND);
    if ($connected === true)
        $socket->close();
    $connected = false;
    file_put_contents(__DIR__ . "/../log/sys_shutdowns.log", 
            date("Y-m-d H:i:s") . ": Shutting down " . $user_requested_action . ". Script started at " .
                     $php_script_started_at . "\n", FILE_APPEND);
}
register_shutdown_function('shutdown');

// ===== THE REAL INITIALIZATION SCRIPT =======================================
// ===== timestamps
$php_script_started_at = microtime(true);
$script_completed = false;

// ===== initialize toolbox and register the requested file for later authorization
include_once '../classes/tfyh_toolbox.php';
$toolbox = new Tfyh_toolbox();
if (! isset($user_requested_file)) {
    $user_requested_file = "none"; // fool the lint check for unset variables.
    $toolbox->display_error("Unzulässiger Aufruf", 
            "Bei dem Aufruf wurde keine Seitenidentifikation in der Initialisierung gefunden.", 
            $user_requested_file);
}
$file_path_elements = explode("/", $user_requested_file);
$index_last = count($file_path_elements) - 1;
$user_requested_action = $file_path_elements[$index_last - 1] . "/" . $file_path_elements[$index_last];
// resolve app root URL for use in scripts.
$app_root = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
// cut off get parameters
$app_root = (strrpos($app_root, "?") !== false) ? substr($app_root, 0, strrpos($app_root, "?")) : $app_root;
// cut off last two path elements
$app_root = (strrpos($app_root, "/") !== false) ? substr($app_root, 0, strrpos($app_root, "/")) : "Server missing/somehow";
$app_root = substr($app_root, 0, strrpos($app_root, "/"));   // e.g.: "https://rcwb.de/efacloud"
$app_domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";   // e.g.: "https://rcwb.de"
$app_subdirectory = substr($app_root, strlen($app_domain) + 1);    // e.g.: "efacloud"

// ===== throttle to prevent from machine attacks. Will return to the user in overload situations.
$toolbox->load_throttle("inits/", $toolbox->config->settings_tfyh["init"]["max_inits_per_hour"]);
$toolbox->logger->log_init_login_error("init");

// ===== remove any existiong tfyh user and session cookie
setcookie("tfyhUserID", "", time() - 3600);
setcookie("tfyhSessionID", "", time() - 3600);

// ===== Try to open an existing session.
$session_open_result = $toolbox->app_sessions->session_open(-1);
if ($session_open_result == false) {
    $script_completed = true;
    $toolbox->display_error($toolbox->overload_error_headline, 
            "Die Anwendung ist überlastet. Bitte versuchen Sie es später noch einmal. Wir bitten um Verständnis.", 
            $user_requested_file);
}
if (! isset($_SESSION["User"]))
    $_SESSION["User"] = $toolbox->users->get_empty_user();
$debug = ($toolbox->config->debug_level > 0);
if ($debug)
    file_put_contents("../log/debug_init.log", 
            date("Y-m-d H:i:s") . "\n  File: " . $user_requested_file .
                     "\n  User after session check: appUserID " .
                     $_SESSION["User"][$toolbox->users->user_id_field_name] . ", Rolle: " .
                     $_SESSION["User"]["Rolle"] . "\n", FILE_APPEND);

// ===== identify current context, i. e. the parent directory's parent.
// The application holds all executable code in directories at the application root. Multiple
// applications of such type may reside in one web server serving different tenants. The session must
// recognise, if the application root was changed, to prevent users from using their access rights in any
// other tenant.
$context = getcwd();
$context = substr($context, 0, strrpos($context, "/"));
if ($debug) {
    $session_context_prev = (isset($_SESSION["context"])) ? $_SESSION["context"] : "[nicht vorhanden]";
    $session_user_dbg = (isset($_SESSION["User"])) ? $_SESSION["User"] : "[nicht vorhanden]";
    file_put_contents("../log/debug_init.log", 
            "  Sessionkontext: " . $session_context_prev . ", aktuell: " . $context . "\n", FILE_APPEND);
}

// ===== add the context, if not yet added and check it.
if (! isset($_SESSION["context"]))
    $_SESSION["context"] = $context;
elseif (strcmp($_SESSION["context"], $context) != 0) {
    // wrong tenant. Clear all user settings, because they stem from a different tenant.
    $prev_context = $_SESSION["context"];
    $toolbox->app_sessions->session_close();
    $script_completed = true;
    $toolbox->display_error("Unzulässiger Kontextwechsel", 
            "Ein Wechsel vom Kontext: " . $prev_context . " zu " . $context .
                     " ist nicht zulässig. Die Sitzung wurde beendet.", $user_requested_file);
}

// ===== initialize the data base socket. Test the data base connection
include_once '../classes/tfyh_socket.php';
$connected = false;
if (! isset($dbconnect)) {
    $socket = new Tfyh_socket($toolbox);
    $connected = $socket->open_socket();
    if ($connected !== true) {
        $script_completed = true;
        $toolbox->display_error("Datenbankverbindung fehlgeschlagen", $connected, $user_requested_file);
    }
}

// ===== resolve and update user
$userID = intval($_SESSION["User"][$toolbox->users->user_id_field_name]);
$cached_session_role = $_SESSION["User"]["Rolle"];
$_SESSION["User"] = $toolbox->users->get_empty_user();
// re-read user from data base with possibly updated properties
if ($userID >= 0) {
    $refreshed_user = $socket->find_record($toolbox->users->user_table_name, 
            $toolbox->users->user_id_field_name, $userID);
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
            "  User after DB check: appUserID: " . $_SESSION["User"][$toolbox->users->user_id_field_name] .
                     ", Rolle: " . $_SESSION["User"]["Rolle"] . "\n", FILE_APPEND);
include_once '../classes/tfyh_menu.php';
$menu = new Tfyh_menu("../config/access/" . $menu_template, $toolbox);

// ===== authorize user for action
if (! $menu->is_allowed_menu_item($user_requested_file)) {
    $script_completed = true;
    $toolbox->display_error("Nicht zulässig.", 
            "Die Rolle '" . $_SESSION["User"]["Rolle"] . "' ist nicht für die Aktion '" .
                     $user_requested_action . "' berechtigt. " .
                     "In Subskriptionen, Workflows und Concessions wurde auch keine passende " .
                     "Berechtigung gefunden. ", $user_requested_file);
}

// ===== form sequence check. Using the fs_id all actions can be distinguished in a multitab
// user session. Actually these tokens are generated for all pages, not only forms, but for
// forms they are crucial.
$done = 0;
$fs_id = "";
if (isset($_GET["fseq"])) {
    $script_completed = true; // for any of the following errors
    if (strlen($_GET["fseq"]) != 6)
        $toolbox->display_error("Fehler in der Formularsequenz.", 
                "Es wurde eine ungültige Formularsequenz angegeben.", $user_requested_file);
    $done = intval(substr($_GET["fseq"], 5, 1));
    if ($done == 0)
        $toolbox->display_error("Fehler in der Formularsequenz.", 
                "Es wurde eine ungültige Sequenzziffer angegeben.", $user_requested_file);
    $fs_id = substr($_GET["fseq"], 0, 5);
    if (! isset($_SESSION["forms"][$fs_id]))
        $toolbox->display_error("Fehler in der Formularsequenz.", 
                "Es wurde eine ungültige Formular-ID angegeben.", $user_requested_file);
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
    