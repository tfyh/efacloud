<?php

/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2022 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

// ===== timestamps
$php_script_started_at = microtime(true);
$debug = true;

// ===== initialize toolbox & internationalization.
include_once "../classes/init_i18n.php";  // usually this is included with init.php
include_once '../classes/tfyh_toolbox.php';
$toolbox = new Tfyh_toolbox();
load_i18n_resource($toolbox->config->language_code);

// ===== debug initialization
$debug = ($toolbox->config->debug_level > 0);
$posttx_debuglog = "../log/debug_posttx.log";

// check for last other write action request. This request is public, very simple, just
// posttx.php?lowa=[clientId]. Returns the last recorded write access of any other client
// as unix timestamp in second.
if (isset($_POST["lowa"])) {
    $requesting_client = intval($_POST["lowa"]);
    $valid_client = false;
    if (! file_exists("../log/lra")) {
        mkdir("../log/lra");
        mkdir("../log/lwa");
    }
    $clients = scandir("../log/lwa");
    $lowa = 1;
    foreach ($clients as $client) {
        if (($client != ".") && ($client != "..")) {
            $lwa = (file_exists("../log/lwa/" . $client)) ? intval(
                    trim(file_get_contents("../log/lwa/" . $client))) : 2;
            if (intval($client) == $requesting_client)
                $valid_client = true; // if the write access was by the requesting client, ignore it
            elseif (($lowa < $lwa) && ($lwa < time())) {
                // lwa must not be greater than now. Then it may be a 13 digit timestamp, in any case is
                // erroneous.
                $lowa = $lwa;
            }
        }
    }
    // if the request is issued from an unknown client, return a fake value.
    // That is to not dosclose valid client IDs to the internet.
    if ($valid_client)
        file_put_contents("../log/lra/" . $requesting_client, date("Y-m-d H:i:s"));
    else
        $lowa = strval(time() - rand(123, 36000));
    
    // note: java timestamps are millis, PHP seconds.
    $resp = $lowa . "000; [" . date("Y-m-d H:i:s", intval($lowa)) . "] was last other write access for " .
             $requesting_client;
    include_once "../classes/tx_handler.php";
    Tx_handler::log_content_size(intval($_SERVER['CONTENT_LENGTH']), strlen($resp), $toolbox, 
            $requesting_client); // strlen instead of mb_strlen, because number of bytes is needed.
    echo $resp;
    // shortened end script procedure for lowa transaction.
    $toolbox->logger->put_timestamp($requesting_client, "api/lowa", $php_script_started_at);
    exit();
}

// ===== construct Efa-tables and client handler for the next checks.
include_once '../classes/tfyh_socket.php';
$socket = new Tfyh_socket($toolbox);
// ===== parse tx container and return, if the syntax is invalid
include_once "../classes/tx_handler.php";
$tx_handler = new Tx_handler($toolbox, $socket, $php_script_started_at);
$txc = (isset($_POST["txc"])) ? trim($_POST["txc"]) : "";
$tx_handler->parse_request_container(trim($txc));
if ($tx_handler->txc["cresult_code"] >= 400)
    $tx_handler->send_response_and_exit();

// ===== trigger transactions load throttling.
$loadTxOk = $toolbox->load_throttle("api_inits", 
        $toolbox->config->settings_tfyh["init"]["max_inits_per_hour"], "posttx.php");
if ($loadTxOk !== true) {
    $tx_handler->txc["cresult_code"] = 406;
    $tx_handler->txc["cresult_message"] = "Overload detected.";
    // prevent from redoing too frequently
    sleep(3);
    if ($debug)
        file_put_contents($posttx_debuglog, "  Request container rejected due to overload.\n", FILE_APPEND);
    $tx_handler->send_response_and_exit();
}

// ===== Test the data base connection
$connected = $socket->open_socket();

if ($connected !== true) {
    $tx_handler->txc["result_code"] = 407;
    $tx_handler->txc["result_message"] = "Web server failed to connect to the data base.";
    $tx_handler->send_response_and_exit();
}

// ===== Identify the user
$api_user_id = $tx_handler->txc["userID"];
$user_to_verify = $socket->find_record($toolbox->users->user_table_name, $toolbox->users->user_id_field_name, 
        $api_user_id);
if ($user_to_verify === false) {
    $tx_handler->txc["cresult_code"] = 402;
    $tx_handler->txc["cresult_message"] = "Unknown client.";
    $tx_handler->send_response_and_exit();
}

// ===== Authenticate the user
// try by app session: reading the user from an existing session will return false on failure
$session_user_id = (mb_strlen($tx_handler->txc["password"]) > 20) &&
         $toolbox->app_sessions->session_user_id($tx_handler->txc["password"]);

$verified = (($session_user_id !== false) && ($session_user_id == $api_user_id));
if ($verified) {
    if ($debug)
        file_put_contents($posttx_debuglog, 
                date("Y-m-d H:i:s") . "\n  Existing session: verified '" .
                         $user_to_verify[$toolbox->users->user_firstname_field_name] . " " .
                         $user_to_verify[$toolbox->users->user_lastname_field_name] . "' against session ID '" .
                         $tx_handler->txc["password"] . "'.\n", FILE_APPEND);
    $api_session_id = $tx_handler->txc["password"];
    $is_new_session = false;
    $session_opened = $toolbox->app_sessions->session_open($api_user_id, $api_session_id);
    
    if (! $session_opened) {
        $tx_handler->txc["cresult_code"] = 406;
        $tx_handler->txc["cresult_message"] = "Failed to reuse your existing session, please try again later.";
        $tx_handler->send_response_and_exit();
    }
    $tx_handler->txc["cresult_code"] = 300;
    $tx_handler->txc["cresult_message"] = "Ok.";
} // try by password
else {
    if ($debug)
        file_put_contents($posttx_debuglog, 
                date("Y-m-d H:i:s") . "\n  New session: verifying '" .
                         $user_to_verify[$toolbox->users->user_firstname_field_name] . " " .
                         $user_to_verify[$toolbox->users->user_lastname_field_name] . "' against " .
                         mb_strlen($tx_handler->txc["password"]) . " characters password.\n", FILE_APPEND);
    // get password hash for user either from data base or from auth_provider
    $passwort_hash = $user_to_verify["Passwort_Hash"];
    // if no password hash is available, check with alternative authentication provider
    $auth_provider_class_file = "../authentication/auth_provider.php";
    if ((strlen($passwort_hash) <= 10) && file_exists($auth_provider_class_file)) {
        if ($debug)
            file_put_contents($posttx_debuglog, 
                    date("Y-m-d H:i:s") . "\n  ... getting password hash from external auth provider.\n", 
                    FILE_APPEND);
        include_once $auth_provider_class_file;
        $auth_provider = new Auth_provider();
        $passwort_hash = $auth_provider->get_pwhash($user_to_verify[$toolbox->users->user_id_field_name]);
    }
    // verify password.
    $verified = (strlen($passwort_hash) > 10) && (strlen($tx_handler->txc["password"]) > 0) &&
             password_verify($tx_handler->txc["password"], $passwort_hash);
    if ($verified) {
        $api_session_id = $toolbox->app_sessions->create_app_session_id();
        $is_new_session = true;
        // ===== open an app session, if the first transaction of this container is a NOP request
        if (isset($tx_handler->txc["requests"][0]) && isset($tx_handler->txc["requests"][0]["type"]) &&
                 strcasecmp($tx_handler->txc["requests"][0]["type"], "NOP") == 0) {
            $session_open = $verified && $toolbox->app_sessions->session_open($api_user_id, $api_session_id);
            $session_opened = $toolbox->app_sessions->session_open($api_user_id, $api_session_id);
            if (! $session_opened) {
                $tx_handler->txc["cresult_code"] = 406;
                $tx_handler->txc["cresult_message"] = "Too many sessions open, please try again later.";
                $tx_handler->send_response_and_exit();
            }
            $tx_handler->txc["cresult_code"] = 300;
            $tx_handler->txc["cresult_message"] = "Ok. New session opened.";
        } else {
            $tx_handler->txc["cresult_code"] = 300;
            $tx_handler->txc["cresult_message"] = "Ok.";
        }
    } else {
        $tx_handler->txc["cresult_code"] = 403;
        $tx_handler->txc["cresult_message"] = "Authentication failed.";
        $tx_handler->send_response_and_exit();
    }
}

// Set the $_SESSION["User"] for later use in the application.
if (! isset($_SESSION["User"]))
    $_SESSION["User"] = $user_to_verify;
$_SESSION["User"]["appType"] = $tx_handler->txc["appType"];
if ($debug)
    file_put_contents($posttx_debuglog, 
            date("Y-m-d H:i:s") . "\n  User after session check: appUserID " .
                     $_SESSION["User"][$toolbox->users->user_id_field_name] . ", Rolle: " .
                     $_SESSION["User"]["Rolle"] . ", Anwendungstyp = " . $_SESSION["User"]["appType"] . "\n", 
                    FILE_APPEND);

// ===== add listeners to the socket - removed 27.02.2022, V2.3.1_08 as last with the staement

// ===== check for daily cron jobs run only at session opening actions.
if ($is_new_session) {
    include_once ("../classes/cron_jobs.php");
    Cron_jobs::run_daily_jobs($toolbox, $socket, $_SESSION["User"][$toolbox->users->user_id_field_name]);
}

include_once '../classes/tfyh_menu.php';
$menu = new Tfyh_menu("../config/access/api", $toolbox);
if ($debug)
    file_put_contents($posttx_debuglog, "  Request handling started at " . date("H:i:s") . ".\n", FILE_APPEND);
$tx_handler->handle_request_container($_SESSION["User"], $menu);
if ($debug)
    file_put_contents($posttx_debuglog, "  Request handling completed at " . date("H:i:s") . ".\n", 
            FILE_APPEND);

// ===== send the result to the client
// the information on the way of verification is needed, to close the opened session
// at the end, if this was not a NOP or OPEN request.
$tx_handler->send_response_and_exit();
