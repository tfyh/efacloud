<?php

/**
 *
 *       efaCloud
 *       --------
 *       https://www.efacloud.org
 *
 * Copyright  2018-2024  Martin Glade
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * class file for the client handler class.
 */
class Tx_handler
{

    /**
     * The efacloud message separator String and its replacement, as well as csv special characters.
     */
    private static $ems = "\n|-eFa-|\n";

    private static $emsr = "\n|-efa-|\n";

    private static $upload_file_path = "../uploads/";

    /**
     * The maximum and used API version supported. Version 1: first api version, first data base layout; 2:
     * added verify transaction; 3: include efaCloud record management fields. When sending a response the
     * $api_max_version is included as version number. When handling a request, the the version of the request
     * container is used to handle the request. The client will max out the version number.
     */
    private $api_max_version = 3;

    /**
     * The generic toolbox.
     */
    private $toolbox;

    /**
     * the Efa API class providung API transaction support.
     */
    private $efa_api;

    /**
     * The data base connection socket.
     */
    private $socket;

    /**
     * the currently handled request.
     */
    public $txc;

    /**
     * the transaction log
     */
    public static $api_log_path = "../log/api_info.log";

    // public for Efa_api keyfixing info only
    
    /**
     * the transaction error log
     */
    public static $api_error_log_path = "../log/api_errors.log";

    /**
     * the transaction warnings log
     */
    private static $api_warning_log_path = "../log/api_warnings.log";

    /**
     * the transaction warnings log, also used by Efa_api
     */
    public static $api_debug_log_path = "../log/debug_api.log";

    /**
     * The timestamp of the transaction start, created by init.php and posttx.php respectively
     */
    private $php_script_started_at;

    /**
     * Debug level to add mor information for support cases.
     */
    private $debug_on;

    /**
     * public Constructor.
     * 
     * @param Tfyh_toolbox $toolbox
     *            the application basic utilities
     * @param Tfyh_socket $socket
     *            the socket to connect to the data base
     * @param float $php_script_started_at
     *            the time the script started for performance analysis
     */
    public function __construct (Tfyh_toolbox $toolbox, Tfyh_socket $socket, float $php_script_started_at)
    {
        $this->php_script_started_at = $php_script_started_at;
        $this->toolbox = $toolbox;
        $this->socket = $socket;
        $cfg = $this->toolbox->config->get_cfg();
        $this->debug_on = $toolbox->config->debug_level > 0;
        include_once '../classes/efa_api.php';
        $this->efa_api = new Efa_api($toolbox, $socket);
    }

    /**
     * Set the session id, which is used by the Efa_api class within the NOP response.
     * 
     * @param String $api_session_id
     *            the id of the API-session
     */
    public function set_session_id (String $api_session_id)
    {
        $this->efa_api->set_session_id($api_session_id);
    }

    /**
     * Gather statistics on the exchanged content size for those who have bandwith limitations.
     * 
     * @param int $request_size
     *            the size of the request
     * @param int $response_size
     *            the size of the response
     * @param Tfyh_toolbox $toolbox
     *            the toolbox of this application
     * @param int $client_id
     *            the efacloudUserID of the client
     */
    public static function log_content_size (int $request_size, int $response_size, Tfyh_toolbox $toolbox, 
            int $client_id)
    {
        $size_filename = "../log/contentsize";
        if (! file_exists($size_filename))
            mkdir($size_filename);
        $size_filename .= "/" . $client_id;
        $size_table_header = "Date;requests;requSize;respSize";
        $today = date("Y-m-d");
        $latest_recent = time() - 1209600; // 14 days in seconds
        if (file_exists($size_filename))
            $sizes = $toolbox->read_csv_array($size_filename);
        else
            $sizes = [];
        $today_in = false;
        $start = (count($sizes) > 14) ? count($sizes) - 14 : 0;
        for ($i = $start; $i < count($sizes); $i ++) {
            $size = $sizes[$i];
            if ($sizes[$i]["Date"] == $today) {
                $sizes[$i]["requests"] = strval(intval($sizes[$i]["requests"]) + 1);
                $sizes[$i]["requSize"] = strval(intval($sizes[$i]["requSize"]) + $request_size);
                $sizes[$i]["respSize"] = strval(intval($sizes[$i]["respSize"]) + $response_size);
                $today_in = true;
            }
        }
        if (! $today_in) {
            $size["Date"] = $today;
            $size["requests"] = 1;
            $size["requSize"] = $request_size;
            $size["respSize"] = $response_size;
            $sizes[] = $size;
        }
        $out = $size_table_header . "\n";
        foreach ($sizes as $size) {
            if (strtotime($size["Date"]) >= $latest_recent)
                $out .= $size["Date"] . ";" . $size["requests"] . ";" . $size["requSize"] . ";" .
                         $size["respSize"] . "\n";
        }
        file_put_contents($size_filename, $out);
    }

    /**
     * Print a log string with all transaction container information for logging
     * 
     * @return the printed log String
     */
    private function txc_to_log ()
    {
        $log_string = "version:" . $this->txc["version"] . ", ";
        $log_string .= "cID:" . $this->txc["cID"] . ", ";
        $log_string .= "userID:" . $this->txc["userID"] . ", ";
        $log_string .= "password (length):" . strlen($this->txc["password"]) . ", ";
        $log_string .= "cresult_code:" . $this->txc["cresult_code"] . ", ";
        $log_string .= "cresult_message:" . $this->txc["cresult_message"] . "\n";
        return $log_string;
    }

    /**
     * Print a log string with all transaction information for logging
     * 
     * @param int $i
     *            the index of the transaction within the container
     * @param int $withMessageAndRecord
     *            set true to get a version with the transactio record and the result message (only error
     *            log).
     * @return the printed log String
     * @return string
     */
    private function tx_to_log (int $i, bool $withMessageAndRecord)
    {
        $tx_request = $this->txc["requests"][$i];
        $log_string = "(V" . $this->txc["version"] . ") ";
        $log_string = "client:" . $this->txc["userID"] . ", ";
        $log_string = "ID:" . $tx_request["ID"] . ", ";
        $log_string .= "retries:" . $tx_request["retries"] . ", ";
        $log_string .= "type:" . $tx_request["type"] . ", ";
        $log_string .= "tablename:" . $tx_request["tablename"] . ", ";
        $log_string .= "result_code:" . $tx_request["result_code"] . ", ";
        if ($withMessageAndRecord) {
            if (strlen($tx_request["record"]["contents"]) > 2000)
                $tx_request["record"]["contents"] = substr($tx_request["record"]["contents"], 0, 2000) . " ...";
            $log_string .= "record:" . json_encode($tx_request["record"]) . " // ";
            $log_string .= "result_message:" . str_replace("\n", " // ", trim($tx_request["result_message"]));
        } else {
            $log_string .= "record length:" . count($tx_request["record"]);
            $log_string .= ", result_message length:" . strlen($tx_request["result_message"]);
            $log_string .= ", lines:" . count(explode("\n", trim($tx_request["result_message"])));
        }
        
        return $log_string;
    }

    /**
     * Parse a transaction container according to the efacloud format and put it to the $this->txc variable
     * for further processing. The header is checked for a version, containerID, user and password entry and
     * at least one transaction. All these elements must be present, the first three numeric. If the check
     * fails, the shall nevertheless be sent, providing the API client the failure reason.
     * 
     * @param String $txc_base64_efa
     *            the transaction container, still in base64 efa encoding
     * @return nothing. See the txc["cresult_code"] for the parsing result.
     */
    public function parse_request_container (String $txc_base64_efa)
    {
        if ($this->debug_on)
            file_put_contents(self::$api_debug_log_path, 
                    date("Y-m-d H:i:s") . ": parse_request_container of " . strlen($txc_base64_efa) .
                             " characters length.\n", FILE_APPEND);
        
        // create container header array
        $this->txc = [];
        $this->txc["version"] = 0;
        $this->txc["cID"] = 0;
        $this->txc["userID"] = 0;
        $this->txc["password"] = 'none';
        $this->txc["cresult_code"] = 401;
        $this->txc["cresult_message"] = 'Transaction invalid.';
        $this->txc["requests"] = [];
        
        if (($txc_base64_efa == null) || (strlen($txc_base64_efa) < 5)) {
            $this->txc["cresult_message"] .= 'Transaction container missing, empty or too short.';
            file_put_contents(self::$api_log_path, 
                    "[" . date("Y-m-d H:i:s") . "] - Container: content empty or too short: '" .
                             json_encode($txc_base64_efa) . "' // ", FILE_APPEND);
            if ($this->debug_on)
                file_put_contents(self::$api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": parse_request_container failed. " .
                                 $this->txc["cresult_message"] . ".\n", FILE_APPEND);
            return;
        }
        
        // decode base64-efa and split into header and requests
        $txc_plain = base64_decode(
                str_replace("_", "=", str_replace("-", "/", str_replace("*", "+", $txc_base64_efa))));
        $celements = explode(";", $txc_plain, 5);
        // check container syntax
        if (count($celements) != 5) {
            $this->txc["cresult_message"] .= 'Decoded transaction container has too few elements: ' .
                     count($celements);
            file_put_contents(self::$api_log_path, 
                    "[" . date("Y-m-d H:i:s") . "] - Container: has too few elements.\n", FILE_APPEND);
            if ($this->debug_on)
                file_put_contents(self::$api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": parse_request_container failed. " .
                                 $this->txc["cresult_message"] . ".\n", FILE_APPEND);
            return;
        }
        
        // check API version
        $api_request_version = intval($celements[0]);
        if ($api_request_version > $this->api_max_version) {
            $this->txc["cresult_message"] .= 'Transaction container version ' . $api_request_version .
                     ' too high for the server data base layout version: ' . $this->api_max_version;
            $this->txc["version"] = $this->api_max_version;
            file_put_contents(self::$api_log_path, 
                    "[" . date("Y-m-d H:i:s") .
                             "] - Container: version does not match the server API version.\n", FILE_APPEND);
            if ($this->debug_on)
                file_put_contents(self::$api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": parse_request_container failed. " .
                                 $this->txc["cresult_message"] . ".\n", FILE_APPEND);
            return;
        }
        
        // update container header array
        $this->txc["version"] = $api_request_version;
        $this->txc["cID"] = intval($celements[1]);
        if (strpos($celements[2], "@") !== false) {
            $user_id = intval(substr($celements[2], 1));
            $this->txc["appType"] = 1; // call by efaWeb, sessions may be changed and aborted.
        } else {
            $user_id = intval($celements[2]);
            $this->txc["appType"] = 0; // call by efa, sessions must not be changed and aborted.
        }
        $this->txc["userID"] = $user_id;
        if ($this->txc["version"] * $this->txc["cID"] * $this->txc["userID"] == 0) {
            $this->txc["cresult_message"] .= "One of version, cID, or userID is either not numeric or missing.";
            file_put_contents(self::$api_log_path, 
                    "[" . date("Y-m-d H:i:s") .
                             "] - Container: One of version, cID, or userID is either not numeric or missing.\n", 
                            FILE_APPEND);
            if ($this->debug_on)
                file_put_contents(self::$api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": parse_request_container failed. " .
                                 $this->txc["cresult_message"] . ".\n", FILE_APPEND);
            return;
        }
        $this->txc["password"] = $celements[3];
        $this->txc["cresult_code"] = 301;
        $this->txc["cresult_message"] = "Syntax ok. User to be verified.";
        
        // parse requests and add them to the container array
        $txc_requests = explode(self::$ems, $celements[4]);
        $container_description = "Container V" . $this->txc["version"] . ": Received from " .
                 $this->txc["userID"] . " with " . count($txc_requests) . " transaction requests.";
        file_put_contents(self::$api_log_path, "[" . date("Y-m-d H:i:s") . "] - $container_description\n", 
                FILE_APPEND);
        if ($this->debug_on)
            file_put_contents(self::$api_debug_log_path, 
                    date("Y-m-d H:i:s") . ": parse_request_container header completed: " .
                             $container_description . ".\n", FILE_APPEND);
        $this->txc["requests"] = [];
        foreach ($txc_requests as $tx_request) {
            // split request header and record
            $elements = str_getcsv($tx_request, ";", "\"");
            $tx = [];
            if ((count($elements) < 4) || (count($elements) % 2 != 0)) {
                $tx["ID"] = (isset($elements[0])) ? $elements[0] : "0";
                $tx["retries"] = (isset($elements[1])) ? $elements[1] : "0";
                $tx["type"] = (isset($elements[2])) ? $elements[2] : "parsing_error";
                $tx["tablename"] = (isset($elements[3])) ? $elements[3] : "undefined";
                $tx["result_code"] = 501;
                $tx["result_message"] = "invalid count of parameters in transaction request: " .
                         count($elements);
            } else {
                $tx["ID"] = $elements[0];
                $tx["retries"] = $elements[1];
                $tx["type"] = $elements[2];
                $tx["tablename"] = $elements[3];
                $tx["result_code"] = 900;
                $tx["result_message"] = "not yet parsed nor processed";
                $tx_record = [];
                for ($i = 4; $i < count($elements); $i = $i + 2)
                    $tx_record[$elements[$i]] = $elements[$i + 1];
                $tx["record"] = $tx_record;
            }
            if ($this->debug_on)
                file_put_contents(self::$api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": parse_request_container transaction completed: #" . $tx["ID"] .
                                 ": " . $tx["type"] . " " . $tx["tablename"] . ": " . $tx["result_code"] . " " .
                                 $tx["result_message"] . ".\n", FILE_APPEND);
            $this->txc["requests"][] = $tx;
        }
    }

    /**
     * This method verifies the transaction token and sets the current transaction array.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param Tfyh_menu $menu
     *            used to identify whether the enclosed transactions are permitted for the user
     * @return nothing. All results are put into the transaction container.
     */
    public function handle_request_container (array $client_verified, Tfyh_menu $menu)
    {
        if ($this->debug_on)
            file_put_contents(self::$api_debug_log_path, 
                    date("Y-m-d H:i:s") . ": handle_request_container started.\n", FILE_APPEND);
        for ($i = 0; $i < count($this->txc["requests"]); $i ++) {
            file_put_contents(self::$api_log_path, 
                    "[" . date("Y-m-d H:i:s") . "] - Transaction: to execute " . $this->tx_to_log($i, false) .
                             "\n", FILE_APPEND);
            $this->execute_transaction($client_verified, $i, $menu);
            $isError = (intval($this->txc["requests"][$i]["result_code"]) >= 400);
            if ($isError) {
                file_put_contents(self::$api_error_log_path, 
                        "[" . date("Y-m-d H:i:s") . "] - Transaction: did not execute " .
                                 $this->tx_to_log($i, true) . "\n", FILE_APPEND);
                file_put_contents(self::$api_log_path, 
                        "[" . date("Y-m-d H:i:s") . "] - Transaction: failed " . $this->tx_to_log($i, true) .
                                 "\n", FILE_APPEND);
            } else
                file_put_contents(self::$api_log_path, 
                        "[" . date("Y-m-d H:i:s") . "] - Transaction: executed " . $this->tx_to_log($i, false) .
                                 "\n", FILE_APPEND);
        }
    }

    /**
     * This method logs the dropped transactions of a failed container.
     */
    private function log_dropped_container_transactions ()
    {
        for ($i = 0; $i < count($this->txc["requests"]); $i ++)
            file_put_contents(self::$api_warning_log_path, 
                    "[" . date("Y-m-d H:i:s") . "] - Transaction: log_dropped_container_transactions\n" .
                             $this->tx_to_log($i, true) . "\n", FILE_APPEND);
    }

    /**
     * Take the current transaction container, build an appropriate response container from it and send it
     * back. Transactions must be handled before building the response. The response will have the same
     * verison as the request, even if the server can do more.
     * 
     * @return this function will not return, but send a response back to the API user and exit.
     */
    public function send_response_and_exit ()
    {
        file_put_contents(self::$api_log_path, 
                "[" . date("Y-m-d H:i:s") . "] - Container: send response " . $this->txc_to_log(), FILE_APPEND);
        if ($this->debug_on)
            file_put_contents(self::$api_debug_log_path, 
                    date("Y-m-d H:i:s") . ": send_response - " . $this->txc_to_log(), FILE_APPEND);
        $cresult_message = $this->txc["cresult_message"];
        $cresult_message = str_replace(self::$ems, self::$emsr, $cresult_message);
        $cresult_message = utf8_encode($this->csv_entry_encode($cresult_message));
        
        $efaCloudUserID = intval($this->txc["userID"]);
        if (intval($this->txc["cresult_code"]) >= 400) {
            file_put_contents(self::$api_error_log_path, 
                    "[" . date("Y-m-d H:i:s") . "] - Container failed for user " . $efaCloudUserID .
                             ". Result code: " . $this->txc["cresult_code"] . ". Result message: " .
                             $this->txc["cresult_message"] . "\n", FILE_APPEND); //
            $this->log_dropped_container_transactions();
        }
        
        $resp = $this->txc["version"] . ";" . $this->txc["cID"] . ";" . $this->txc["cresult_code"] . ";" .
                 $cresult_message . ";";
        $timestamp_txs = "";
        for ($i = 0; $i < count($this->txc["requests"]); $i ++) {
            $resp .= $this->txc["requests"][$i]["ID"] . ";" . $this->txc["requests"][$i]["result_code"] . ";";
            $result_message = $this->txc["requests"][$i]["result_message"];
            $result_message = str_replace(self::$ems, self::$emsr, $result_message);
            if (intval($this->txc["requests"][$i]["result_code"]) >= 400) {
                file_put_contents(self::$api_error_log_path, 
                        "[" . date("Y-m-d H:i:s") . "] - Transaction: " . $this->txc["requests"][$i]["type"] .
                                 " transaction " . $this->txc["requests"][$i]["ID"] . " (" .
                                 $this->txc["requests"][$i]["tablename"] . ") failed for user " .
                                 $efaCloudUserID . ". Result code: " .
                                 $this->txc["requests"][$i]["result_code"] . ". Result message: " .
                                 $this->txc["requests"][$i]["result_message"] . "\n", FILE_APPEND);
            }
            $timestamp_txs = (strlen($timestamp_txs) == 0) ? "api/" .
                     strtolower($this->txc["requests"][$i]["type"]) : "api/multiple";
            
            // the result message neither needs utf-8 encoding (the values are already encoded) nor
            // csv encoding (the values are as well already approporiately quotes).
            $resp .= $result_message . self::$ems;
        }
        if (count($this->txc["requests"]) > 0)
            $resp = substr($resp, 0, strlen($resp) - strlen(self::$ems));
        $resp = str_replace("=", "_", str_replace("/", "-", str_replace("+", "*", base64_encode($resp))));
        $content_size = (isset($_SERVER['CONTENT_LENGTH'])) ? intval($_SERVER['CONTENT_LENGTH']) : 0;
        self::log_content_size($content_size, strlen($resp), $this->toolbox, $efaCloudUserID);
        if ($this->debug_on)
            file_put_contents(self::$api_debug_log_path, 
                    date("Y-m-d H:i:s") . ": send_response - start streaming " . strlen($resp) .
                             " characters.\n", FILE_APPEND);
        
        // this echo below is the resonse sending
        echo $resp;
        // this echo above is the resonse sending
        
        $user_id = (isset($this->txc["userID"])) ? intval($this->txc["userID"]) : - 1;
        $this->toolbox->logger->put_timestamp($user_id, $timestamp_txs, $this->php_script_started_at);
        if ($this->debug_on)
            file_put_contents(self::$api_debug_log_path, 
                    date("Y-m-d H:i:s") . ": send_response - completed.\n", FILE_APPEND);
        exit();
    }

    /**
     * This method executes a single transaction and returns the result message. The result_code and the
     * result_message fields of the transaction are also set according to the transaction result.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param array $transaction_request
     *            the transaction request which shall be parsed and processed. It will be changed.
     * @param Tfyh_menu $menu
     *            used to identify whether the enclosed transactions are permitted for the user
     * @return nothing. All results are put into the transaction container.
     */
    private function execute_transaction (array $client_verified, int $index, Tfyh_menu $menu)
    {
        if ($this->debug_on) {
            $log_string = date("Y-m-d H:i:s") . ": execute_transaction #" .
                     $this->txc["requests"][$index]["ID"] . " " . $this->txc["requests"][$index]["type"] . " " .
                     $this->txc["requests"][$index]["tablename"] . " " .
                     json_encode($this->txc["requests"][$index]["record"]) . "\n";
            file_put_contents(self::$api_debug_log_path, $log_string, FILE_APPEND);
        }
        
        $efaCloudUserID = $client_verified[$this->toolbox->users->user_id_field_name];
        $api_version = $this->txc["version"];
        file_put_contents("../log/lastAPIversion", $api_version);
        // parse transaction header. Note: no header element can ever contan a ';' character,
        // therefore a simple explode ist sufficient.
        
        $txtype = $this->txc["requests"][$index]["type"];
        $txtablename = $this->txc["requests"][$index]["tablename"];
        $record = $this->txc["requests"][$index]["record"];
        $result_code = $this->txc["requests"][$index]["result_code"];
        $tx_response = "501;programming fault. Please raise support request to 'efacloud.org'.";
        
        $type_recognized = true;
        // check user rights
        $tx_type = $this->txc["requests"][$index]["type"];
        $tx_tablename = $this->txc["requests"][$index]["tablename"];
        $transaction_path = "../api/" . $tx_type;
        $is_allowed = $menu->is_allowed_menu_item($transaction_path, $client_verified);
        if (! $is_allowed) {
            $tx_response = "502;Transaction '" . $this->txc["requests"][$index]["type"] .
                     "' not allowed in table " . $tx_tablename . " for role '" . $client_verified["Rolle"] .
                     "'";
            if ($this->debug_on)
                file_put_contents(self::$api_debug_log_path, 
                        date("Y-m-d H:i:s") .
                                 ":   aborting execute_transaction because of insufficient user rights.\n", 
                                FILE_APPEND);
        } elseif ($result_code != 900)
            $tx_response = $result_code . ";" . $this->txc["requests"][$index]["result_message"];
        
        // Write data
        elseif (strcasecmp($txtype, "insert") == 0)
            $tx_response = $this->efa_api->api_modify($client_verified, $txtablename, $record, 1, 
                    $api_version);
        elseif (strcasecmp($txtype, "update") == 0)
            $tx_response = $this->efa_api->api_modify($client_verified, $txtablename, $record, 2, 
                    $api_version);
        elseif (strcasecmp($txtype, "delete") == 0)
            $tx_response = $this->efa_api->api_modify($client_verified, $txtablename, $record, 3, 
                    $api_version);
        elseif (strcasecmp($txtype, "keyfixing") == 0)
            $tx_response = $this->efa_api->api_keyfixing($client_verified, $txtablename, $record);
        
        // Read data
        elseif (strcasecmp($txtype, "synch") == 0)
            $tx_response = $this->efa_api->api_select($client_verified, $api_version, $txtablename, $record, 
                    true);
        elseif (strcasecmp($txtype, "select") == 0)
            $tx_response = $this->efa_api->api_select($client_verified, $api_version, $txtablename, $record, 
                    false);
        elseif (strcasecmp($txtype, "list") == 0) {
            $last_modified_min = (isset($record["LastModified"])) ? intval($record["LastModified"]) : 0;
            $tx_response = $this->efa_api->api_list($client_verified, $txtablename, $record, 
                    $last_modified_min);
        } //
          
        // Support functions
        elseif (strcasecmp($txtype, "nop") == 0)
            $tx_response = $this->efa_api->api_nop($client_verified, $record, $this->api_max_version);
        elseif (strcasecmp($txtype, "verify") == 0)
            $tx_response = $this->efa_api->api_verify($record, $api_version);
        elseif (strcasecmp($txtype, "backup") == 0)
            $tx_response = $this->efa_api->api_backup(self::$api_log_path);
        elseif (strcasecmp($txtype, "info") == 0) {
            include_once "../classes/efa_info.php";
            $efa_info = new Efa_info($this->toolbox, $this->socket);
            $tx_response = $efa_info->api_info($client_verified, $record);
        } elseif (strcasecmp($txtype, "cronjobs") == 0) {
            // manage the logs to avoid overrun
            rename(self::$api_log_path, self::$api_log_path . "." . date("d"));
            file_put_contents(self::$api_log_path, "[" . date("Y-m-d H:i:s") . "]: log continued.\n");
            if ($this->debug_on) {
                rename(self::$api_debug_log_path, self::$api_debug_log_path . "." . date("d"));
                file_put_contents(self::$api_debug_log_path, 
                        "[" . date("Y-m-d H:i:s") . "]: log continued.\n", FILE_APPEND);
            }
            if (intval(date("d")) == 1) {
                rename(self::$api_error_log_path, self::$api_error_log_path . "." . date("m"));
                file_put_contents(self::$api_error_log_path, 
                        "[" . date("Y-m-d H:i:s") . "]: log continued.\n");
            }
            // run the standard cron_jobs
            $tx_response = $this->efa_api->api_cronjobs(
                    $client_verified[$this->toolbox->users->user_id_field_name]);
        } elseif (strcasecmp($txtype, "upload") == 0) {
            $tx_response = $this->api_upload($client_verified, $txtablename, $record);
            if ($this->debug_on)
                file_put_contents(self::$api_debug_log_path, 
                        date("Y-m-d H:i:s") . ":   execute_transaction upload result: $tx_response.\n", 
                        FILE_APPEND);
        } elseif (strcasecmp($txtype, "parsing_error") == 0)
            // parsing error
            $tx_response = $this->txc["requests"][$index]["result_code"] . ";" .
                     $this->txc["requests"][$index]["result_message"];
        
        // Error to return on unrecognized transaction type
        if (! $type_recognized) {
            // 501 => "Transaction invalid."
            $tx_response = "501;Transaction invalid. Invalid type used: " . $txtype . " for table " .
                     $txtablename . " (API version of request = " . $api_version . ").";
            if ($this->debug_on)
                file_put_contents(self::$api_debug_log_path, 
                        date("Y-m-d H:i:s") . ":   execute_transaction with invalid function code.\n", 
                        FILE_APPEND);
        }
        
        // pass the result to the transaction
        $result_code = substr($tx_response, 0, 3);
        $this->txc["requests"][$index]["result_code"] = $result_code;
        $this->txc["requests"][$index]["result_message"] = substr($tx_response, 4);
        
        // log failures
        if (intval($result_code) > 300)
            file_put_contents(self::$api_error_log_path, 
                    date("Y-m-d H:i:s") . ": tx" . $this->txc["requests"][$index]["ID"] . " - " .
                             $this->txc["requests"][$index]["type"] . "@" .
                             $this->txc["requests"][$index]["tablename"] . " " .
                             $this->txc["requests"][$index]["result_code"] . ": " .
                             $this->txc["requests"][$index]["result_message"] . "\n", FILE_APPEND);
        
        // time stamp all requests for the fast synch option and last activity display
        if (intval($this->txc["requests"][$index]["result_code"]) < 400) {
            $wasWriteAccess = ((strcasecmp($txtype, "insert") == 0) || (strcasecmp($txtype, "update") == 0) ||
                     (strcasecmp($txtype, "delete") == 0));
            if ($wasWriteAccess) {
                if ($this->debug_on)
                    file_put_contents(self::$api_debug_log_path, 
                            date("Y-m-d H:i:s") . ":   execute_transaction remembered write access.\n", 
                            FILE_APPEND);
                file_put_contents("../log/lwa/" . strval($efaCloudUserID), strval(time()));
                // notification of new reservations, damages or admin messages
                include_once "../classes/efa_notifier.php";
                $efa_notifier = new Efa_notifier($this->toolbox, $this->socket);
                $efa_notifier->notify_api_write_event($this->txc["requests"][$index]);
            } else {
                file_put_contents("../log/lra/" . strval($efaCloudUserID), date("Y-m-d H:i:s"));
            }
        }
        if ($this->debug_on)
            file_put_contents(self::$api_debug_log_path, 
                    date("Y-m-d H:i:s") . ": execute_transaction #" . $this->txc["requests"][$index]["ID"] .
                             " completed with result: " . $this->txc["requests"][$index]["result_code"] . ":" .
                             substr($this->txc["requests"][$index]["result_message"], 0, 250) . " ... .\n", 
                            FILE_APPEND);
    }

    /**
     * Upload a text file to the uploads section.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param String $filetype
     *            the type of the file to upload. Currently only "text" is allowed.
     * @param array $record
     *            record which shall be used for file path and file contents. Must contain a "path" and a
     *            "contents" field. The path is a relative path, but must not contain the “../”-String to
     *            prevent from access to higher level directories.
     * @return string the transaction result
     */
    private function api_upload (array $client_verified, String $filetype, array $record)
    {
        $isText = (strcasecmp($filetype, "text") === 0);
        $isBinary = (strcasecmp($filetype, "binary") === 0);
        $isZip = (strcasecmp($filetype, "zip") === 0);
        if (! $isBinary && ! $isText && ! $isZip)
            return "502;Only filetypes zip, binary and text allowed, used " . $filetype;
        if (! $record)
            return "300;Transcation successful, but no upload data provided.";
        if (! isset($record["filepath"]) || (strlen($record["filepath"]) == 0))
            return "502;No valid file path provided.";
        if (! isset($record["contents"]) || (strlen($record["contents"]) == 0))
            return "300;Transcation successful, but no contents provided.";
        if (strlen($record["contents"]) > 500000)
            return "300;Transcation successful, but upload size limit exceeded, contents will be dropped.";
        if (strpos($record["filepath"], "/") !== false)
            return "502;String '/' is not allowed in aupload file path.";
        $efaCloudUserID = $client_verified[$this->toolbox->users->user_id_field_name];
        $upload_dir_path = self::$upload_file_path . $efaCloudUserID;
        if (! file_exists($upload_dir_path))
            mkdir($upload_dir_path, true);
        $upload_file_path = $upload_dir_path . "/" . $record["filepath"];
        chmod($upload_dir_path, 0755);
        file_put_contents($upload_dir_path . "/.htaccess", "deny for all");

        $contents_to_write = ($isBinary || $isZip) ? base64_decode($record["contents"]) : $record["contents"];
        if ($contents_to_write) {
            // write files
            $written_bytes_count = file_put_contents($upload_file_path, $contents_to_write);
            if ($isZip) {
                $zip = new ZipArchive();
                if ($zip->open($upload_file_path) === TRUE) {
                    $unzip_success = $zip->extractTo($upload_dir_path);
                    $zip->close();
                    if (! $unzip_success)
                        return "502;" . $upload_file_path . ": Failed to unzip archive. Nothing written.";
                }
            }
            include_once "../classes/efa_config.php";
            $efa_config = new Efa_config($this->toolbox);
            $efa_config->parse_client_config($efaCloudUserID);
            $efa_config->load_efa_config();
        } else
            return "502;" . $upload_file_path . ": Failed to decode contents. Nothing written.";
        return "300;" . $upload_file_path . ": " . $written_bytes_count . " Bytes written.";
    }

    /**
     * Encodes a String as csv-value. If it contains either '"' or '"', all inner '"' are doubled and the
     * String enclosed in '"' at the begnning and end. Else it is return unchanged.
     * 
     * @param String $entry            
     * @return string|String
     */
    private function csv_entry_encode (String $entry)
    {
        if ((strpos($entry, ";") !== false) || (strpos($entry, '"') !== false))
            return '"' . str_replace('"', '""', $entry) . '"';
        else
            return $entry;
    }
}