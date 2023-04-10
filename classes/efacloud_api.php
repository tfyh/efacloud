<?php

/**
 * This class provides views for bookings.
 */
class Efacloud_api
{

    /**
     * Explanation texts for server result codes. array key = code, value = explanation text. Shall be
     * identical to the cGlobals.js $_resultMessages definition. No i18n translations, system messages.
     */
    public static $result_messages = [300 => "Transaction completed.",
            301 => "Container parsed. User yet to be verified.",
            302 => "API version of container not supported. Maximum API level exceeded.",
            303 => "Transaction completed with key fixed.",304 => "Transaction forbidden.",
            400 => "XHTTPrequest Error.",401 => "Syntax error.",402 => "Unknown client.",
            403 => "Authentication failed.",404 => "Server side busy.",405 => "Wrong transaction ID.",
            406 => "Overload detected.",407 => "No data base connection.",
            500 => "Transaction container aborted.",501 => "Transaction invalid.",502 => "Transaction failed.",
            503 => "Transaction missing in container.",504 => "Transaction container decoding failed.",
            505 => "Server response empty.",506 => "Internet connection aborted.",
            507 => "Could not decode server response."
    ];

    /**
     * The transaction separator String
     */
    public static $transaction_separator = "\n|-eFa-|\n";

    /**
     * The transaction separator replacement String
     */
    public static $transaction_separator_replacement = "\n|-efa-|\n";

    /**
     * The efacloud server URL to be connected to
     */
    private $server;

    /**
     * The efacloudUserID to be used for the connection
     */
    private $clientID;

    /**
     * The password of the efacloudUser to be used for the connection
     */
    private $password;

    /**
     * The first transaction id to be used for the connection
     */
    private $tx_id = 42;

    /**
     * The first transaction container id to be used for the connection
     */
    private $txc_id = 42;

    /**
     * Status of the container: open for append, locked (full or in sending process)
     */
    private $txc_open;

    /**
     * The queue of transactions which shall be sent. Maximum capacity is 10 transactions.
     */
    private $txc_messages = [];

    /**
     * The queue of transactions which shall be sent. Maximum capacity is 10 transactions.
     */
    private $txc_header = [];

    /**
     * Construct the instance. Pass the URL, client ID and client password
     */
    function __construct (String $server, int $clientID, String $password)
    {
        $this->server = $server;
        $this->clientID = $clientID;
        $this->password = $password;
        $this->init_container();
    }

    /**
     * Encode a plain text container. This converts the String first to UTF-8, encodes the result in base64
     * and replaces the characters "=/+" by "_-*" respectively.
     * 
     * @param String $txc_plain
     *            the transaction container . Use "Tx_handler->create_container()" to create those
     * @return the encoded String
     */
    private static function encode_container (String $txc_plain)
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
    private static function decode_container (String $txc_encoded)
    {
        return base64_decode(
                str_replace("_", "=", str_replace("-", "/", str_replace("*", "+", $txc_encoded))));
    }

    /**
     * clear the container hesader and remove all messages from the container
     */
    private function init_container ()
    {
        $this->txc_header["version"] = 1;
        $this->txc_header["cID"] = 0;
        $this->txc_header["cresult_code"] = 502;
        $this->txc_header["cresult_message"] = "[default on construction]"; // no i18n, system message
        $this->txc_messages = [];
        $this->txc_open = true;
    }

    /**
     * Creates a transaction container String as plain text.
     * 
     * @return the transaction container as plain text
     */
    private function create_container ()
    {
        $this->txc_id ++;
        $this->txc_header["cID"] = $this->txc_id;
        $txc_plain = $this->txc_header["version"] . ";" . $this->txc_header["cID"] . ";" . $this->clientID .
                 ";" . $this->password . ";";
        foreach ($this->txc_messages as $txID => $transaction) {
            $txm_plain = $txID . ";" . $transaction["retries"] . ";" . $transaction["type"] . ";" .
                     $transaction["tablename"];
            foreach ($transaction["record"] as $key => $value) {
                $enc_value = $this->encode_entry_csv($value);
                $enc_value = str_replace(self::$transaction_separator, 
                        self::$transaction_separator_replacement, $enc_value);
                $txm_plain .= ";" . $key . ";" . $this->encode_entry_csv($value);
            }
            $txc_plain .= $txm_plain . self::$transaction_separator;
        }
        $txc_plain = substr($txc_plain, 0, strlen($txc_plain) - strlen(self::$transaction_separator));
        return $txc_plain;
    }

    /**
     * Simple csv entry encoder. If the $entry contains one of ' \n', ';' '"' all "-quotes are duplicated and
     * one '"' added at front and end.
     * 
     * @param String $entry
     *            entry which shall be encoded
     * @return String the encoded entry.
     */
    private function encode_entry_csv (String $entry = null)
    {
        if (is_null($entry))
            return "";
        if ((strpos($entry, "\n") !== false) || (strpos($entry, ";") !== false) ||
                 (strpos($entry, "\"") !== false))
            return "\"" . str_replace("\"", "\"\"", $entry) . "\"";
        return $entry;
    }

    /**
     * Append a single transaction for later sending.
     * 
     * @param String $type
     *            transaction type, like "update"
     * @param String $tablename
     *            affected table, like "efa2persons"
     * @param array $record
     *            the record to use, associative
     * @return ID of transaction appended, if successfull, false, if the queue capacity limit of 10
     *         transactions is reached.
     */
    public function append_transaction (String $type, String $tablename, array $record)
    {
        if (! $this->txc_open)
            return false;
        $tx = array();
        $this->tx_id ++;
        $tx["retries"] = 0;
        $tx["type"] = $type;
        $tx["tablename"] = $tablename;
        $tx["record"] = $record;
        $tx["result_code"] = 502;
        $tx["result_message"] = i("Wixvhp|[default on construction...");
        $this->txc_messages[$this->tx_id] = $tx;
        if (count($this->txc_messages) == 10)
            $this->txc_open = false;
        return $this->tx_id;
    }

    /**
     * add a container error to all messages in the buffer
     */
    private function add_container_error_to_messages ()
    {
        foreach ($this->txc_messages as $tx_id => $tx_message) {
            $this->txc_messages[$tx_id]["result_code"] = $this->txc_header["cresult_code"];
            $this->txc_messages[$tx_id]["result_message"] = i("S0Lse6|transaction container er...")." " .
                     $this->txc_header["cresult_message"] . " ". i("ipgDJE|Transaction ignored.");
        }
    }

    /**
     * Parse the container and handle errors
     * 
     * @param String $decoded_response
     *            The response received from efaCloud
     */
    private function parse_response_container (String $decoded_response)
    {
        $response_array = explode(";", $decoded_response, 5);
        $version = $response_array[0]; // version mismatch currently ignored
        $cID = $response_array[1]; // cID mismatch currently ignored
        $cresult_code = intval($response_array[2]);
        $cresult_message = $response_array[3];
        if ($cresult_code >= 400)
            $this->add_container_error_to_messages();
        else {
            $tx_responses = explode(self::$transaction_separator, $response_array[4]);
            foreach ($tx_responses as $tx_response) {
                $response_array = explode(";", $tx_response, 3);
                $txID = intval($response_array[0]);
                if (isset($this->txc_messages[$txID])) {
                    $this->txc_messages[$txID]["result_code"] = $response_array[1];
                    $this->txc_messages[$txID]["result_message"] = $response_array[2];
                }
            }
        }
    }

    /**
     * Create a transaction container String for debugging and testing purposes.
     * 
     * @param array $transactions
     *            the transaction messages as non-associative array. Use "Tx_handler->create_request()" to
     *            create those
     * @return the transaction result container as plain text
     */
    public function send_container ()
    {
        // close container. No more adding possible from now on.
        $this->txc_open = false;
        $data = array('txc' => $this->encode_container($this->create_container())
        );
        $options = array(
                'http' => array('header' => "Content-type: application/x-www-form-urlencoded\r\n",
                        'method' => 'POST','content' => http_build_query($data)
                )
        );
        $context = stream_context_create($options);
        $response = file_get_contents($this->server, false, $context);
        
        if ($response === false) {
            $this->txc_header["cresult_code"] = 400;
            $this->txc_header["cresult_message"] = i("JMdyYP|Server access failed com..." ." " .
                     i("WwI0Sx|Either your server URL i..."));
            $this->add_container_error_to_messages();
            return false;
        }
        $response_decoded = $this->decode_container($response);
        
        $this->parse_response_container($response_decoded);
        return true;
    }

    /**
     * reste the communication. This will delete all previous results.
     */
    public function reset ()
    {
        $this->init_container();
    }

    /**
     * Get the result of a transaction
     * 
     * @param int $tx_id
     *            the id of the transaction for which the result shall be provided
     * @return the result as [(int) result_code, (String) result_message]. If the container has not been sent,
     *         this will be an error code and message.
     */
    public function get_result (int $tx_id)
    {
        if ($this->txc_open)
            return [502,i("bJGu9Q|The transaction has not ...")
            ];
        if (! isset($this->txc_messages[$tx_id]))
            return [502,i("BFNYBY|The requested transactio...")
            ];
        return [$this->txc_messages[$tx_id]["result_code"],$this->txc_messages[$tx_id]["result_message"]
        ];
    }
}
