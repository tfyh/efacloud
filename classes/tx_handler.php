<?php

/**
 * class file for the client handler class. The only client so far is the efa-logbook.
 * 
 * @package efacloud
 * @subpackage classes
 * @author mgSoft
 */
/**
 * class file for the client handler class.
 */
class Tx_handler
{

    /**
     * The efacloud API version
     */
    public $version = 1;

    /**
     * The efacloud message separator String and its replacement, as well as csv special characters.
     */
    public $ems = "\n|-eFa-|\n";

    public $emsr = "\n|-efa-|\n";

    public $tx_req_delimiter = ";";

    public $tx_quotation = "\"";

    public $tx_resp_delimiter = ";";

    public $upload_file_path = "../uploads/";

    /**
     * The texts for the identification results to be returned to the client.
     */
    public $server_resonse_texts = [300 => "Transaction completed.",301 => "Primary key modified.",
            // 400 => "XHTTPrequest Error.", (client side javascript generated error)
            401 => "Syntax error.",402 => "Unknown client.",403 => "Authentication failed.",
            404 => "Server side busy.",
            // 405 => "Wrong transaction ID.", (client side javascript generated error)
            406 => "Overload detected.",407 => "No data base connection.",
            // 500 => "Internal server error.", this is never explicitly set by the code
            501 => "Transaction invalid.",502 => "Transaction failed."
        // 503 => "No server response in returned transaction response container",
        // 504 => "Transaction response container failed",
        // 505 => "Server response empty",
        // 506 => "Internet connection aborted",
        // 507 => "Could not decode server response"
    ];

    /**
     * The generic toolbox.
     */
    private $toolbox;

    /**
     * the Efa-tables class providung special table handling support.
     */
    private $efa_tables;

    /**
     * the currently handled request.
     */
    public $txc;

    /**
     * the transaction log
     */
    public $api_log_path = "../log/api.log";

    /**
     * the transaction error log
     */
    public $api_error_log_path = "../log/api_error.log";

    /**
     * the transaction warnings log
     */
    public $api_warnings_log_path = "../log/api_warnings.log";

    /**
     * public Constructor.
     * 
     * @param Toolbox $toolbox
     *            standard application toolbox
     * @param Efa_tables $efa_tables
     *            the efa_tables object to execute on transactions. If no execution is needed, e.g. for API
     *            testing, this can be ommitted.
     */
    public function __construct (Toolbox $toolbox, Efa_tables $efa_tables = null)
    {
        $this->toolbox = $toolbox;
        if ($efa_tables)
            $this->efa_tables = $efa_tables;
    }

    /**
     * Print an html string with all transaction container content of the current tx container for debugging.
     * 
     * @return the printed html String
     */
    public function txc_to_html ()
    {
        $html = "<h4>Container</h4><p>";
        $html .= "version: " . $this->txc["version"] . "<br>";
        $html .= "cID: " . $this->txc["cID"] . "<br>";
        $html .= "username: " . $this->txc["username"] . "<br>";
        $html .= "password (length): " . strlen($this->txc["password"]) . "<br>";
        $html .= "cresult_code: " . $this->txc["cresult_code"] . "<br>";
        $html .= "cresult_message: " . $this->txc["cresult_message"] . "</p>";
        $i = 1;
        foreach ($this->txc["requests"] as $tx_request) {
            $html .= "<h5>&nbsp;&nbsp;Transaction #$i</h5><p>";
            $html .= "&nbsp;&nbsp;&nbsp;ID: " . $tx_request["ID"] . "<br>";
            $html .= "&nbsp;&nbsp;&nbsp;retries: " . $tx_request["retries"] . "<br>";
            $html .= "&nbsp;&nbsp;&nbsp;type: " . $tx_request["type"] . "<br>";
            $html .= "&nbsp;&nbsp;&nbsp;tablename: " . $tx_request["tablename"] . "<br>";
            $html .= "&nbsp;&nbsp;&nbsp;result_code: " . $tx_request["result_code"] . "<br>";
            $html .= "&nbsp;&nbsp;&nbsp;result_message: " . $tx_request["result_message"] . "</p>";
            $html .= "<p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Transaction record:<br>";
            foreach ($tx_request["record"] as $key => $value)
                $html .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . $key . ": " . $value . "<br>";
            $html .= "</p>";
            $i ++;
        }
        return $html;
    }

    /**
     * Print a log string with all transaction container information for logging
     * 
     * @return the printed log String
     */
    public function txc_to_log ()
    {
        $log_string = "version:" . $this->txc["version"] . ", ";
        $log_string .= "cID:" . $this->txc["cID"] . ", ";
        $log_string .= "username:" . $this->txc["username"] . ", ";
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
    public function tx_to_log (int $i, bool $withMessageAndRecord)
    {
        $tx_request = $this->txc["requests"][$i];
        $log_string = "ID:" . $tx_request["ID"] . ", ";
        $log_string .= "retries:" . $tx_request["retries"] . ", ";
        $log_string .= "type:" . $tx_request["type"] . ", ";
        $log_string .= "tablename:" . $tx_request["tablename"] . ", ";
        $log_string .= "result_code:" . $tx_request["result_code"] . ", ";
        if ($withMessageAndRecord) {
            $log_string .= "record:" . json_encode($tx_request["record"]) . "\n";
            $log_string .= "result_message:" . $tx_request["result_message"];
        } else {
            $log_string .= "record length:" . count($tx_request["record"]);
            $log_string .= ", result_message length:" . strlen($tx_request["result_message"]);
            $log_string .= ", lines:" . count(explode("\n", $tx_request["result_message"]));
        }
        
        return $log_string;
    }

    /**
     * Create a single transaction request String for debugging and testing purposes.
     * 
     * @param int $id
     *            transaction ID
     * @param int $retries
     *            number of retries
     * @param String $type
     *            transaction type, like "update"
     * @param String $tablename
     *            affected table, like "efa2persons"
     * @param array $record
     *            the record to use, associative
     * @return string the plani transaction message
     */
    public function create_request (int $id, int $retries, String $type, String $tablename, array $record)
    {
        $txm_plain = $id . ";" . $retries . ";" . $type . ";" . $tablename;
        foreach ($record as $key => $value)
            $txm_plain .= ";" . $key . ";" . $this->toolbox->encode_entry_csv($value);
        return $txm_plain;
    }

    /**
     * Create a transaction container String for debugging and testing purposes.
     * 
     * @param int $id
     *            transaction container ID
     * @param int $version
     *            API version identifier
     * @param String $efaCloudUserId
     *            the requesting user's Id
     * @param String $password
     *            the requesting users password
     * @param array $txms
     *            the transaction messages as non-associative array. Use "Tx_handler->create_request()" to
     *            create those
     * @return the transaction container as plain text
     */
    public function create_container (int $id, int $version, String $efaCloudUserId, String $password, 
            array $txms)
    {
        $txc_plain = $version . ";" . $id . ";" . $efaCloudUserId . ";" . $password . ";";
        if (count($txms) == 0)
            return $txc_plain;
        else {
            foreach ($txms as $txm)
                $txc_plain .= $txm . $this->ems;
            $txc_plain = substr($txc_plain, 0, strlen($txc_plain) - strlen($this->ems));
            return $txc_plain;
        }
    }

    /**
     * Encode a plain text container. This converts the String first to UTF-8, encodes the result in base64
     * and replaces the characters "=/+" by "_-*" respectively.
     * 
     * @param String $txc_plain
     *            the transaction container . Use "Tx_handler->create_container()" to create those
     * @return the encoded String
     */
    public static function encode_container (String $txc_plain)
    {
        return str_replace("=", "_", 
                str_replace("/", "-", str_replace("+", "*", base64_encode(utf8_encode($txc_plain)))));
    }

    /**
     * Decode a plain text container. This replaces the characters "_-*" by "=/+" respectively. then decodes
     * the base64 sequence and finally decodes the resulting UTF-8 String to PHP native.
     * 
     * @param String $txc_encoded
     *            the transaction container . Use "Tx_handler->create_container()" to create those
     * @return the decoded String
     */
    public static function decode_container (String $txc_encoded)
    {
        return base64_decode(
                str_replace("_", "=", str_replace("-", "/", str_replace("*", "+", $txc_encoded))));
    }

    /**
     * Parse a transaction container according to the efacloud format and put it to the $this->txc variable
     * for further processing.
     * 
     * @param String $txc_base64_efa
     *            the transaction container, still in base64 efa encoding
     * @return the decoded transaction container.
     */
    public function parse_request_container (String $txc_base64_efa)
    {
        // create container header array
        $this->txc = [];
        $this->txc["version"] = $this->version;
        $this->txc["cID"] = 0;
        $this->txc["username"] = 'none';
        $this->txc["password"] = 'none';
        $this->txc["cresult_code"] = 401;
        $this->txc["cresult_message"] = 'Transaction invalid. ';
        $this->txc["requests"] = [];
        
        if (($txc_base64_efa == null) || (strlen($txc_base64_efa) < 5)) {
            $this->txc["cresult_message"] .= 'Transaction container missing, empty or too short.';
            file_put_contents($this->api_log_path, 
                    "[" . date("Y-m-d H:i:s") . "] - Container: content empty or too short.\n", FILE_APPEND);
            return;
        }
        // decode base64-efa and split into header and requests
        $txc_plain = self::decode_container($txc_base64_efa);
        $celements = explode(";", $txc_plain, 5);
        // check container syntax
        if (count($celements) != 5) {
            $this->txc["cresult_message"] .= 'Decoded transaction container has too few elements.';
            file_put_contents($this->api_log_path, 
                    "[" . date("Y-m-d H:i:s") . "] - Container: has too few elements.\n", FILE_APPEND);
            return;
        }
        if ($celements[0] != $this->version) {
            $this->txc["cresult_message"] .= 'Transaction container version does not match the server API version.';
            file_put_contents($this->api_log_path, 
                    "[" . date("Y-m-d H:i:s") .
                             "] - Container: version does not match the server API version.\n", FILE_APPEND);
            return;
        }
        
        // update container header array
        $this->txc["version"] = $celements[0];
        $this->txc["cID"] = $celements[1];
        $this->txc["username"] = $celements[2];
        $this->txc["password"] = $celements[3];
        $this->txc["cresult_code"] = 300;
        $this->txc["cresult_message"] = "ok.";
        
        // parse requests and add them to the container array
        $txc_requests = explode($this->ems, $celements[4]);
        file_put_contents($this->api_log_path, 
                "[" . date("Y-m-d H:i:s") . "] - Container: Received " . count($txc_requests) .
                         " transaction requests.\n", FILE_APPEND);
        $this->txc["requests"] = [];
        foreach ($txc_requests as $tx_request) {
            $tx_request_short = (strlen($tx_request) > 200) ? substr($tx_request, 0, 200) : $tx_request;
            file_put_contents($this->api_log_path, 
                    "[" . date("Y-m-d H:i:s") . "] - Transaction: '" . $tx_request_short . "'\n", FILE_APPEND);
            // split request header and record
            $elements = str_getcsv($tx_request, $this->tx_req_delimiter, $this->tx_quotation);
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
                $tx["result_message"] = "not processed";
                $tx_record = [];
                for ($i = 4; $i < count($elements); $i = $i + 2)
                    $tx_record[$elements[$i]] = $elements[$i + 1];
                $tx["record"] = $tx_record;
            }
            $this->txc["requests"][] = $tx;
        }
    }

    /**
     * This method verifies the transaction token and sets the current transaction array.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param Menu $menu
     *            used to identify whether the enclosed transactions are permitted for the user
     * @return nothing. All results are put into the transaction container.
     */
    public function handle_request_container (array $client_verified, Menu $menu)
    {
        for ($i = 0; $i < count($this->txc["requests"]); $i ++) {
            file_put_contents($this->api_log_path, 
                    "[" . date("Y-m-d H:i:s") . "] - Transaction: to execute " . $this->tx_to_log($i, false) .
                             "\n", FILE_APPEND);
            $this->execute_transaction($client_verified, $i, $menu);
            $isError = (intval($this->txc["requests"][$i]["result_code"]) >= 400);
            if ($isError) {
                file_put_contents($this->api_error_log_path, 
                        "[" . date("Y-m-d H:i:s") . "] - Transaction: did not execute " .
                                 $this->tx_to_log($i, true) . "\n", FILE_APPEND);
                file_put_contents($this->api_log_path, 
                        "[" . date("Y-m-d H:i:s") . "] - Transaction: failed " . $this->tx_to_log($i, false) .
                                 "\n", FILE_APPEND);
            } else
                file_put_contents($this->api_log_path, 
                        "[" . date("Y-m-d H:i:s") . "] - Transaction: executed " . $this->tx_to_log($i, false) .
                                 "\n", FILE_APPEND);
        }
    }

    /**
     * This method logs the dropped transactions of a failed container.
     */
    public function log_dropped_container_transactions ()
    {
        for ($i = 0; $i < count($this->txc["requests"]); $i ++)
            file_put_contents($this->api_warning_log_path, 
                    "[" . date("Y-m-d H:i:s") . "] - Transaction: log_dropped_container_transactions\n" .
                             $this->tx_to_log($i, true) . "\n", FILE_APPEND);
    }

    /**
     * Take the current transaction container, build an appropriate response container from it and send it
     * back. Transactions must be handled before building the response.
     * 
     * @return this function will not return, bt send a response back and exit.
     */
    public function send_response ()
    {
        file_put_contents($this->api_log_path, 
                "[" . date("Y-m-d H:i:s") . "] - Container: send response " . $this->txc_to_log() . "\n", 
                FILE_APPEND);
        $cresult_message = $this->txc["cresult_message"];
        $cresult_message = str_replace($this->ems, $this->ems, $cresult_message);
        $cresult_message = utf8_encode($this->csv_entry_encode($cresult_message));
        
        $efaCloudUserID = intval($this->txc["username"]);
        if (intval($this->txc["cresult_code"]) >= 400)
            file_put_contents($this->api_error_log_path, 
                    "[" . date("Y-m-d H:i:s") . "] - Container failed for user " . $efaCloudUserID .
                             ". Result code: " . $this->txc["cresult_code"] . ". Result message: " .
                             $this->txc["cresult_message"] . "\n", FILE_APPEND);
        
        $resp = $this->txc["version"] . ";" . $this->txc["cID"] . ";" . $this->txc["cresult_code"] . ";" .
                 $cresult_message . ";";
        for ($i = 0; $i < count($this->txc["requests"]); $i ++) {
            $resp .= $this->txc["requests"][$i]["ID"] . ";" . $this->txc["requests"][$i]["result_code"] . ";";
            $result_message = $this->txc["requests"][$i]["result_message"];
            $result_message = str_replace($this->ems, $this->ems, $result_message);
            
            if (intval($this->txc["requests"][$i]["result_code"]) >= 400)
                file_put_contents($this->api_error_log_path, 
                        "[" . date("Y-m-d H:i:s") . "] - Transaction: " . $this->txc["requests"][$i]["type"] .
                                 " transaction " . $this->txc["requests"][$i]["ID"] . " (" .
                                 $this->txc["requests"][$i]["tablename"] . ") failed for user " .
                                 $efaCloudUserID . ". Result code: " .
                                 $this->txc["requests"][$i]["result_code"] . ". Result message: " .
                                 $this->txc["requests"][$i]["result_message"] . "\n", FILE_APPEND);
            
            // the result message neither nees utf-8 encoding (the values are already encoded) nor
            // csv encoding (the values are as well already approporiately quotes).
            $resp .= $result_message . $this->ems;
        }
        $resp = substr($resp, 0, strlen($resp) - strlen($this->ems));
        $resp = str_replace("=", "_", str_replace("/", "-", str_replace("+", "*", base64_encode($resp))));
        echo $resp;
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
     * @param Menu $menu
     *            used to identify whether the enclosed transactions are permitted for the user
     * @return nothing. All results are put into the transaction container.
     */
    public function execute_transaction (array $client_verified, int $index, Menu $menu)
    {
        if (! $this->efa_tables)
            return;
        
        $efaCloudUserID = $client_verified[$this->toolbox->users->user_id_field_name];
        // parse transaction header. Note: no header element can ever contan a ';' character,
        // therefore a simple explode ist sufficient.
        
        $txtype = $this->txc["requests"][$index]["type"];
        $txtablename = $this->txc["requests"][$index]["tablename"];
        $record = $this->txc["requests"][$index]["record"];
        $result_code = $this->txc["requests"][$index]["result_code"];
        
        // check user rights
        $transaction_path = "../api/" . $this->txc["requests"][$index]["type"];
        $is_allowed = $menu->is_allowed_menu_item($transaction_path, $client_verified);
        if (! $is_allowed)
            $tx_response = "502;Transaction '" . $this->txc["requests"][$index]["type"] .
                     "' not allowed for role '" . $client_verified["Rolle"] . "'";
        elseif ($result_code != 900) 
            $tx_response = $result_code . ";" . $this->txc["requests"][$index]["result_message"];
                
        // Build tables
        elseif (strcasecmp($txtype, "createtable") == 0) {
            $tx_response = $this->efa_tables->api_createtable($client_verified, $txtablename, $record);
        } elseif (strcasecmp($txtype, "addcolumns") == 0)
            $tx_response = $this->efa_tables->api_addcolumns($client_verified, $txtablename, $record);
        elseif (strcasecmp($txtype, "autoincrement") == 0)
            $tx_response = $this->efa_tables->api_autoincrement($client_verified, $txtablename, 
                    array_keys($record)[0]);
        elseif (strcasecmp($txtype, "unique") == 0)
            $tx_response = $this->efa_tables->api_unique($client_verified, $txtablename, 
                    array_keys($record)[0]);
        
        // Write data
        elseif (strcasecmp($txtype, "insert") == 0)
            $tx_response = $this->efa_tables->api_insert($client_verified, $txtablename, $record, 
                    $this->api_log_path);
        elseif (strcasecmp($txtype, "update") == 0)
            $tx_response = $this->efa_tables->api_update($client_verified, $txtablename, $record);
        elseif (strcasecmp($txtype, "delete") == 0)
            $tx_response = $this->efa_tables->api_delete($client_verified, $txtablename, $record);
        elseif (strcasecmp($txtype, "keyfixing") == 0)
            $tx_response = $this->efa_tables->api_keyfixing($client_verified, $txtablename, $record, 
                    $this->api_log_path);
        
        // Read data
        elseif (strcasecmp($txtype, "synch") == 0)
            $tx_response = $this->efa_tables->api_select($client_verified, $txtablename, $record, true);
        elseif (strcasecmp($txtype, "select") == 0)
            $tx_response = $this->efa_tables->api_select($client_verified, $txtablename, $record, false);
        elseif (strcasecmp($txtype, "list") == 0)
            $tx_response = $this->efa_tables->api_list($client_verified, $txtablename);
        
        // Support functions
        elseif (strcasecmp($txtype, "nop") == 0) {
            $wait_for_secs = intval(trim($record["sleep"]));
            $wait_for_secs = ($wait_for_secs > 100) ? 100 : $wait_for_secs;
            if ($wait_for_secs > 0)
                sleep($wait_for_secs);
            $tx_response = "300;ok.";
        } elseif (strcasecmp($txtype, "backup") == 0)
            $tx_response = $this->efa_tables->api_backup($this->api_log_path);
        elseif (strcasecmp($txtype, "cronjobs") == 0) {
            // manage the logs to avoid overrun
            rename($this->api_log_path, $this->api_log_path . "." . date("d"));
            file_put_contents($this->api_log_path, "[" . date("Y-m-d H:i:s") . "]: log continued.\n");
            if (intval(date("d")) == 1) {
                rename($this->api_error_log_path, $this->api_error_log_path . "." . date("m"));
                file_put_contents($this->api_error_log_path, "[" . date("Y-m-d H:i:s") . "]: log continued.\n");
            }
            // run the standard cron_jobs
            $tx_response = $this->efa_tables->api_cronjobs(
                    $client_verified[$this->toolbox->users->user_id_field_name]);
        } elseif (strcasecmp($txtype, "upload") == 0)
            $tx_response = $this->api_upload($client_verified, $txtablename, $record);
        
        // parsing error
        elseif (strcasecmp($txtype, "parsing_error") == 0)
            $tx_response = $this->txc["requests"][$index]["result_code"] . ";" .
                     $this->txc["requests"][$index]["result_message"];
        else
            // 501 => "Transaction invalid."
            $tx_response = "501;Transaction invalid. Invalid type used: " . $txtype . " for table " .
                     $txtablename . ".";
        
        // pass the result to the transaction
        $this->txc["requests"][$index]["result_code"] = substr($tx_response, 0, 3);
        $this->txc["requests"][$index]["result_message"] = substr($tx_response, 4);
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
            return "502;No upload data provided.";
        if (! isset($record["filepath"]) || (strlen($record["filepath"]) == 0))
            return "502;No valid file path provided.";
        if (! isset($record["contents"]) || (strlen($record["contents"]) == 0))
            return "502;No contents provided.";
        if (strlen($record["contents"]) > 500000)
            return "502;Upload size limit exceeded.";
        if (strpos($record["filepath"], "../") !== false)
            return "502;String '../' is not allowed in aupload file path.";
        $upload_file_path = $this->upload_file_path .
                 $client_verified[$this->toolbox->users->user_id_field_name] . "/" . $record["filepath"];
        $upload_dir_path = substr($upload_file_path, 0, strrpos($upload_file_path, "/"));
        if (! file_exists($upload_dir_path))
            mkdir($upload_dir_path, true);
        chmod($upload_dir_path, 0755);
        file_put_contents($upload_dir_path . "/.htaccess", "deny for all");
        $contents_to_write = ($isBinary || $isZip) ? base64_decode($record["contents"]) : $record["contents"];
        if ($contents_to_write) {
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
?>