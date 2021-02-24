<?php

// ===== initialize toolbox & access control.
include_once '../classes/toolbox.php';
$toolbox = new Toolbox("../config/settings");
include_once '../classes/menu.php';
$menu = new Menu("../config/access/api", $toolbox);

// ===== trigger transactions load throttling.
$loadTxOk = $toolbox->load_throttle("api_txs/", 3000);
if ($loadTxOk !== true) {
    // return overload error. Do not return the txc ID, but the version.
    $resp = str_replace("=", "_", 
            str_replace("/", "-", str_replace("+", "*", base64_encode("1;0;407;Overload detected"))));
    echo $resp;
    // setup errors go to the main program log, not to the API logs
    $toolbox->logger->log(Tfyh_logger::$TYPE_FAIL, 0, "Overload detected.");
    exit();
}

// ===== construct Efa-tables and client handler for the next checks.
include_once '../classes/socket.php';
$socket = new Socket($toolbox);
include_once "../classes/efa_tables.php";
$efa_tables = new Efa_tables($toolbox, $socket);

// ===== parse tx container and return, if the syntax is invalid
include_once "../classes/tx_handler.php";
$tx_handler = new Tx_handler($toolbox, $efa_tables);
$tx_handler->parse_request_container(trim($_POST["txc"]));
if (! isset($tx_handler->txc["cresult_code"]) || $tx_handler->txc["cresult_code"] >= 400) {
    // ===== trigger error load throttling.
    $loadErrOk = $toolbox->load_throttle("api_errors/", 100);
    if ($loadErrOk !== true) {
        // return overload error. Do not return the txc ID, but the version.
        $resp = str_replace("=", "_", 
                str_replace("/", "-", str_replace("+", "*", base64_encode("1;0;" . $loadErrOk))));
        file_put_contents($tx_handler->api_error_log_path, 
                "[" . date("Y-m-d H:i:s") . "] - Container (loadErr): " . $loadErrOk . ".\n", FILE_APPEND);
        echo $resp;
        exit();
    }
    $tx_handler->send_response();
}

// ===== Test the data base connection
$connected = $socket->open_socket();
if ($connected !== true) {
    $tx_handler->txc["result_code"] = 407;
    $tx_handler->txc["result_message"] = "Web server failed to connect to the data base.";
    $tx_handler->log_dropped_container_transactions();
    $tx_handler->send_response();
}

// ===== identify user
$efaCloudUserID = intval($tx_handler->txc["username"]);
$client_to_verify = $socket->find_record_matched($toolbox->users->user_table_name, 
        [$toolbox->users->user_id_field_name => $efaCloudUserID
        ], true);
if (! $client_to_verify) {
    $tx_handler->txc["cresult_code"] = 402;
    $tx_handler->txc["cresult_message"] = "The user was not found.";
    file_put_contents($tx_handler->api_warning_log_path, 
            "[" . date("Y-m-d H:i:s") . "] - Container: An unknown user (ID provided: " . $efaCloudUserID .
                     ") tried to access the API.");
    $tx_handler->log_dropped_container_transactions();
    $tx_handler->send_response();
}

// ===== check password existence
if (strlen($client_to_verify["Passwort_Hash"]) < 10) {
    $tx_handler->txc["cresult_code"] = 403;
    $tx_handler->txc["cresult_message"] = "The user " . $client_to_verify[$toolbox->users->user_id_field_name] .
             " has no valid password hash set in the data base.";
    file_put_contents($tx_handler->api_warning_log_path, 
            "[" . date("Y-m-d H:i:s") . "] - Container: The user " . $efaCloudUserID .
                     " tried to access the API, but has no password hash set in data base.");
    $tx_handler->log_dropped_container_transactions();
    $tx_handler->send_response();
}

// ===== check password correctness
$verified = password_verify($tx_handler->txc["password"], $client_to_verify["Passwort_Hash"]);
if (! $verified) {
    $tx_handler->txc["cresult_code"] = 403;
    $tx_handler->txc["cresult_message"] = "Incorrect password";
    file_put_contents($tx_handler->api_warning_log_path, 
            "[" . date("Y-m-d H:i:s") . "] - Container: The user " . $efaCloudUserID .
                     " tried to access the API with an incorrect password.");
    $tx_handler->log_dropped_container_transactions();
    $tx_handler->send_response();
}

// ===== handle all transactions
$tx_handler->handle_request_container($client_to_verify, $menu);

// ===== send the result to the client
$tx_handler->send_response();
// limit the log file size to 500 kB
if (filesize($tx_handler->api_log_path) > 500000)
    rename($tx_handler->api_log_path, $tx_handler->api_log_path . ".previous");
if (filesize($tx_handler->api_warning_log_path) > 500000)
    rename($tx_handler->api_warning_log_path, $tx_handler->api_warning_log_path . ".previous");
if (filesize($tx_handler->api_error_log_path) > 500000)
    rename($tx_handler->api_error_log_path, $tx_handler->api_error_log_path . ".previous");

