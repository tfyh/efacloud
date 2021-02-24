<?php
/**
 * snippet to start all forms and pages. Controls load, user identity and opens the data base access. Provides
 * the functions called on script end.
 */

// ===== MAINTENANCE AND LOGGING PART =========================================
// Maintenance page can be inserted here by setting a value to $maintenance_until
$maintenance_until = ""; // e.g.: $maintenance_until = "31.1.2021, 18:00h";
if (strlen($maintenance_until) > 3)
    echo header("Location: ../public/maintenance.php?until=" . urlencode($maintenance_until));

// ===== initdebug.log will be written, if $debug is set to true
$debug = true;
if ($debug && (filesize("../log/initdebug.log") > 250000))
    rename("../log/initdebug.log", "../log/initdebug.log.previous");

// ===== global function definition.
// close the data base socket and echo the footer at the end of the script execution.
function end_script ()
{
    global $socket;
    global $connected;
    global $script_completed;
    global $debug;
    
    echo file_get_contents('../config/snippets/page_03_footer');
    if ($connected === true)
        $socket->close();
    $connected = false;
    $script_completed = true;
    if ($debug)
        file_put_contents(__DIR__ . "/../log/initdebug.log", 
                "  script closed at " . date("Y-m-d H:i:s") . ".\n", FILE_APPEND);
}

// if the script end was not reached, which happens typically in file download scripts, but also
// in error cases, shut down the data base connection.
function shutdown ()
{
    global $socket;
    global $connected;
    global $script_completed;
    global $debug;
    
    if ($script_completed)
        return;
    if ($debug)
        file_put_contents(__DIR__ . "/../log/initdebug.log", 
                "  ### WARNING: script did not reach the page footer output.\n", FILE_APPEND);
    if ($connected === true)
        $socket->close();
    $connected = false;
}
register_shutdown_function('shutdown');

// ===== THE REAL INITIALIZATION SCRIPT =======================================
// ===== initialize toolbox
include_once '../classes/toolbox.php';
$toolbox = new Toolbox();
// init parameters definition
$cfg = $toolbox->config->get_cfg();

$_max_inits_per_hour = isset($cfg["init__max_inits_per_hour"]) ? intval($cfg["init__max_inits_per_hour"]) : 3000;
$_max_errors_per_hour = isset($cfg["init__max_errors_per_hour"]) ? intval($cfg["init__max_errors_per_hour"]) : 100;
$_max_concurrent_sessions = isset($cfg["init__max_concurrent_sessions"]) ? intval(
        $cfg["init__max_concurrent_sessions"]) : 25;
$_max_session_duration = isset($cfg["init__max_session_duration"]) ? intval(
        $cfg["init__max_session_duration"]) : 600; // seconds

if ($_max_inits_per_hour == 0)
    $_max_inits_per_hour = 3000;
if ($_max_errors_per_hour == 0)
    $_max_errors_per_hour = 100;
if ($_max_concurrent_sessions == 0)
    $_max_concurrent_sessions = 25;
if ($_max_session_duration == 0)
    $_max_session_duration = 600;

$_user_no_log = 1818;
$_anonymous_role_name = $toolbox->users->anonymous_role;

// PRELIMINARY SECURITY CHECKS
// ===== throttle to prevent from machine attacks.
$toolbox->load_throttle("inits/", $_max_inits_per_hour);

// ===== check, whether the calling file was set. Prerequisite for user
// authorization
if (! isset($user_requested_file)) {
    $user_requested_file = "none"; // statement just to ensure the lint check
                                   // recognizes a setting.
    $toolbox->display_error("Unzulässiger Aufruf", 
            "Bei dem Aufruf wurde keine Seitenidentifikation in der Initialisierung gefunden.", 
            $user_requested_file);
}

// Count and cleanse all open sessions.
if (! file_exists("../log/sessions"))
    mkdir("../log/sessions");
$sessions = (file_exists("../log/sessions")) ? scandir("../log/sessions") : [];
$open_session_count = 0;
foreach ($sessions as $session) {
    if (substr($session, 0, 1) != ".") {
        $started_at = intval(file_get_contents("../log/sessions/" . $session));
        if ((time() - $started_at) > $_max_session_duration)
            unlink("../log/sessions/" . $session);
        else
            $open_session_count ++;
    }
}
// Limit use to $_max_concurrent_sessions parallel sessions.
if ($open_session_count > $_max_concurrent_sessions)
    // this call will not return, but terminate the activity
    $toolbox->display_error("!#Temporäre Überlast.", 
            "Leider sind im Moment schon " . $_max_concurrent_sessions .
                     " Eingangskanäle belegt. Mehr können wir leider nicht gleichzeitig bearbeiten." .
                     "Bitte versuchen Sie es später noch einmal. Wir bitten um Verständnis.", 
                    $user_requested_file);

// ===== identify current context, i. e. the parent directory's parent.
// The application holds all executable code in directories at the application root. Multiple applications of
// such type may reside in one web server serving different tenants. The session must recognise, if the
// application root was changed, to prevent users from using their access rights in any other tenant.
$context = getcwd();
$context = substr($context, 0, strrpos($context, "/"));
// ===== start or resume session
$toolbox->start_session();
if ($debug) {
    $session_context_prev = (isset($_SESSION["context"])) ? $_SESSION["context"] : "[nicht vorhanden]";
    $session_user_dbg = (isset($_SESSION["User"])) ? $_SESSION["User"] : "[nicht vorhanden]";
    file_put_contents("../log/initdebug.log", 
            date("Y-m-d H:i:s") . "\n  File: " . $user_requested_file . "\n  Sessionkontext: " .
                     $session_context_prev . ", aktuell: " . $context . "\n  Sessionuser: " .
                     json_encode($session_user_dbg) . "\n", FILE_APPEND);
}
$script_completed = false;

// ===== add the context, if not yer added.
if (! isset($_SESSION["context"]))
    $_SESSION["context"] = $context;
elseif (strcmp($_SESSION["context"], $context) != 0) {
    // wrong tenant. Clear all user settings, because they stem from a different tenant.
    $prev_context = $_SESSION["context"];
    $_SESSION["User"] = [];
    $session_user = false;
    // display error.
    $toolbox->display_error("Unzulässiger Kontextwechsel", 
            "Ein Wechsel vom Kontext: " . $prev_context . " zu " . $context .
                     " ist nicht zulässig. Die Sitzung wurde beendet.", $user_requested_file);
}

// log activity
if (! isset($_SESSION["User"]) || ! isset($_SESSION["User"][$toolbox->users->user_id_field_name]) ||
         ($_SESSION["User"][$toolbox->users->user_id_field_name] != $_user_no_log))
    $toolbox->logger->log_activity("init");

// update the session, if not timed out.
if (isset($_SESSION["time_of_last_init"])) {
    // time of last init is set.
    $time_of_last_init = $_SESSION["time_of_last_init"];
    $time = time();
    // Check for timeout
    if (($time - $time_of_last_init) > $_max_session_duration) {
        // timed out
        $_SESSION = array();
        session_destroy();
        $toolbox->display_error("Die Sitzung wurde beendet.", 
                "Die Sitzung wurde beendet, weil entweder in den letzten " . ($_max_session_duration / 60) .
                         " Minuten keine neue Seite aufgerufen wurde " .
                         "oder in der aufgerufenen Seite kein Nutzer mehr " .
                         "eindeutig als Sitzungseigentümer identifiziert werden konnte.", $user_requested_file);
    }
}
// Session was revalidated, refresh time of last init ...
$_SESSION["time_of_last_init"] = time();
// ... and refresh the session token at disk to be able to count the open sessions
file_put_contents("../log/sessions/" . session_id(), "" . time());

// DATA BASE ACCESS
// ===== initialize the data base socket. Test the data base connection
include_once '../classes/socket.php';
$connected = false;
if (! isset($dbconnect)) {
    $socket = new Socket($toolbox);
    $connected = $socket->open_socket();
    if ($connected !== true)
        $toolbox->display_error("Datenbankverbindung fehlgeschlagen", $connected, $user_requested_file);
}

// ===== try to resolve user and select menu template
$menu_template = "pmenu";
if (isset($_SESSION["User"]) && isset($_SESSION["User"][$toolbox->users->user_id_field_name])) {
    // if the user is a known user, load all available information from the data base
    $user_in_db = $socket->find_record($toolbox->users->user_table_name, $toolbox->users->user_id_field_name, 
            $_SESSION["User"][$toolbox->users->user_id_field_name]);
    // use all user information from the data base, but keep temporary information on session user.
    if ($user_in_db != false)
        foreach ($user_in_db as $user_record_key => $user_record_value)
            $_SESSION["User"][$user_record_key] = $user_record_value;
    // make sure all relevant access information is set.
    if (! isset($_SESSION["User"]["Rolle"]))
        $_SESSION["User"]["Rolle"] = $toolbox->users->anonymous_role;
    if ($toolbox->users->user_subscriptions && ! isset($_SESSION["User"]["Subskriptionen"]))
        $_SESSION["User"]["Subskriptionen"] = 0;
    if ($toolbox->users->user_workflows && ! isset($_SESSION["User"]["Workflows"]))
        $_SESSION["User"]["Workflows"] = 0;
    if (strcasecmp($_SESSION["User"]["Rolle"], $toolbox->users->anonymous_role) != 0)
        $menu_template = "imenu";
} else {
    // if the user is not a known user, make sure all access information is appropriately set.
    if (! isset($_SESSION["User"]))
        $_SESSION["User"] = [];
    $_SESSION["User"][$toolbox->users->user_id_field_name] = "-1";
    $_SESSION["User"]["Rolle"] = $toolbox->users->anonymous_role;
    $_SESSION["User"]["Subskriptionen"] = 0;
    $_SESSION["User"]["Workflows"] = 0;
}
if ($debug)
    file_put_contents("../log/initdebug.log", 
            "  User after DB check: " . json_encode($_SESSION["User"]) . "\n", FILE_APPEND);
    
// ===== load menu.
include_once '../classes/menu.php';
$menu = new Menu("../config/access/" . $menu_template, $toolbox);

// ===== authorize user
if (! $menu->is_allowed_menu_item($user_requested_file)) {
    $toolbox->display_error("Nicht zulässig.", 
            "Für die Rolle '" . $_SESSION["User"]["Rolle"] . "' besteht im Menü " . $menu_template .
                     ", keine Berechtigung, die Seite '" . $user_requested_file .
                     "' aufzurufen. In den Subskriptionen und Workflows wurde auch keine passende " .
                     "Berechtigung gefunden. ", $user_requested_file);
}

// ===== form sequence check
$done = 0;
$fs_id = "";
if (isset($_GET["fseq"])) {
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
}