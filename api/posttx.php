<?php

/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2022 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

// ===== simulate a broken connection start
if (false) { sleep(50); exit(); }
// ===== simulate a broken connection end

// ===== timestamps
$php_script_started_at = microtime(true);
$debug = true;

// ===== redirect error repoting.
$err_file = "../log/php_error.log";
if (filesize($err_file) > 200000) {
    copy($err_file, $err_file . ".previous");
    file_put_contents($err_file, "");
}
error_reporting(E_ERROR);
ini_set("error_log", $err_file);

// ===== initialize toolbox & internationalization.
include_once "../classes/init_i18n.php"; // usually this is included with init.php
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
    // That is to not disclose valid client IDs to the internet.
    if ($valid_client) {
        // find the own session
        $sessions = scandir("../log/sessions");
        $own_session = false;
        foreach ($sessions as $session) {
            if (strpos($session, "~") === 0) {
                $session_user_id = intval(explode(";", file_get_contents("../log/sessions/" . $session))[2]);
                if ($session_user_id == $requesting_client)
                    $own_session = $session;
            }
        }
        if ($own_session !== false) {
            // revalidate the api-session
            $toolbox->app_sessions->session_verify_and_update($requesting_client, $own_session);
            // store the own read access
            file_put_contents("../log/lra/" . $requesting_client, date("Y-m-d H:i:s"));
        }
    } else
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

// ===== Authenticate the user based on existing sessions
$api_user_verified = false;
$api_session_id = null;
if ($debug)
    file_put_contents($posttx_debuglog, 
            date("Y-m-d H:i:s") . "\n  Verifying '" . $user_to_verify[$toolbox->users->user_id_field_name] .
                     "' against " . mb_strlen($tx_handler->txc["password"]) . " characters password: ", 
                    FILE_APPEND);
$api_user_verified = ((mb_strlen($tx_handler->txc["password"]) > 20) && $toolbox->app_sessions->session_verify_and_update(
        $api_user_id, $tx_handler->txc["password"])); // Session IDs usually have at least 20 characters
if ($api_user_verified) {
    $api_session_id = $tx_handler->txc["password"];
    if ($debug)
        file_put_contents($posttx_debuglog, "session ok.\n", FILE_APPEND);
    
    // ===== Authenticate the user based on user / pwd
} else {
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
        $passwort_hash = $auth_provider->get_pwhash($api_user_id);
    }
    // verify password.
    $api_user_verified = (strlen($passwort_hash) > 10) && (strlen($tx_handler->txc["password"]) > 0) &&
             password_verify($tx_handler->txc["password"], $passwort_hash);
    if ($api_user_verified) {
        if ($debug)
            file_put_contents($posttx_debuglog, "password ok.\n", FILE_APPEND);
        // check, whether an API-session for this user exists
    }
}
// if also user/password did not provide authentication, return failure to client.
if ($api_user_verified === false) {
    if ($debug)
        file_put_contents($posttx_debuglog, "FAILED.\n", FILE_APPEND);
    $tx_handler->txc["cresult_code"] = 403;
    $tx_handler->txc["cresult_message"] = "Authentication failed.";
    // no further transaction processing on authentication errors
    $tx_handler->send_response_and_exit();
}

// ===== open an API-session, if the first transaction of this container is a NOP request
$is_session_start = (isset($tx_handler->txc["requests"][0]) && isset($tx_handler->txc["requests"][0]["type"]) &&
         (strcasecmp($tx_handler->txc["requests"][0]["type"], "NOP") == 0));
if ($is_session_start) {
    // open a new session
    $api_session_id = $toolbox->app_sessions->api_session_start($api_user_id, $socket, "new");
} else
    // for user/password authentication, $api_session_id will be null
    $api_session_id = $toolbox->app_sessions->api_session_start($api_user_id, $socket, $api_session_id);

// session allocation failed. Return overload error
if ($api_session_id === false) {
    $tx_handler->txc["cresult_code"] = 406;
    $tx_handler->txc["password"] = ""; // remove the password, no further use
    $tx_handler->txc["cresult_message"] = "Too many sessions open, please try again later.";
    // no further transaction processing on authentication errors
    $tx_handler->send_response_and_exit();
}

// Authentication and session allocation successful
$tx_handler->txc["cresult_code"] = 300;
$tx_handler->txc["password"] = ""; // remove the password, no further use
$tx_handler->txc["cresult_message"] = "Ok.";
$tx_handler->set_session_id($api_session_id);
if (! isset($toolbox->users->session_user))
    $toolbox->users->set_session_user($user_to_verify);

// ===== Handle the transactions
// Set the $toolbox->users->session_user for later use in the application.
$toolbox->users->session_user["appType"] = $tx_handler->txc["appType"];
if ($debug)
    file_put_contents($posttx_debuglog, 
            date("Y-m-d H:i:s") . "\n  User after session check: appUserID " .
                     $toolbox->users->session_user["@id"] . ", Rolle: " .
                     $toolbox->users->session_user["Rolle"] . ", Anwendungstyp = " .
                     $toolbox->users->session_user["appType"] . "\n", FILE_APPEND);

// ===== add listeners to the socket - removed 27.02.2022, V2.3.1_08 as last with the staement

// ===== check for daily cron jobs run only at session opening actions.
$first_tx_type = $tx_handler->txc["requests"][0]["type"];
if ($is_session_start) {
    include_once ("../classes/cron_jobs.php");
    Cron_jobs::run_daily_jobs($toolbox, $socket, $toolbox->users->session_user["@id"]);
}

include_once '../classes/tfyh_menu.php';
$menu = new Tfyh_menu("../config/access/api", $toolbox);
if ($debug)
    file_put_contents($posttx_debuglog, "  Request handling started at " . date("H:i:s") . ".\n", FILE_APPEND);
$tx_handler->handle_request_container($toolbox->users->session_user, $menu);
if ($debug)
    file_put_contents($posttx_debuglog, "  Request handling completed at " . date("H:i:s") . ".\n", 
            FILE_APPEND);

// ===== send the result to the client
// the information on the way of verification is needed, to close the opened session
// at the end, if this was not a NOP or OPEN request.
$tx_handler->send_response_and_exit();
