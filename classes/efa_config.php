<?php

class Efa_config
{

    /**
     * The different efa-configurationparts: efa2project.
     */
    public $project = [];

    /**
     * The different efa-configurationparts: efa2types.
     */
    public $types = [];

    /**
     * The different efa-configurationparts: efa2configuration.
     */
    public $config = [];

    /**
     * The compilation of all logbook definitions of all clients as associative array, the index being the
     * book name.
     */
    public $logbooks = [];

    /**
     * The compilation of all clubworkbook definitions of all clients as associative array, the index being
     * the book name.
     */
    public $clubworkbooks = [];

    /**
     * The summary of the compilation of all books
     */
    public $summary_books = "";

    /**
     * Current logbook provided by the reference client
     */
    public $current_logbook;

    /**
     * Sports year start provided by the reference client
     */
    public $sports_year_start;

    /**
     * tine stamp for the beginnig of the current logbook as provided by the reference client
     */
    public $logbook_start_time;

    /**
     * tine stamp for the end of the current logbook as provided by the reference client
     */
    public $logbook_end_time;

    /**
     * The common toolbox.
     */
    private $toolbox;

    /**
     * a local string builder for the recursive array display function.
     */
    private $str_builder;

    /**
     * a helper variable for the recursive array display function.
     */
    private $row_of_64_spaces = "                                                                ";

    /**
     * Construct the Util class. This parses the efa-configuration passed in the corresponding drectory
     * ../uploads/[efaCloudUserID] into csv files and .
     */
    public function __construct (Tfyh_toolbox $toolbox)
    {
        $this->toolbox = $toolbox;
        $this->load_efa_config();
    }

    /**
     * Compare all client type settings and issue warnings, if not equal.
     * 
     * @return String A String containing all issues found. Empty for a complete match at all clients.
     */
    public function compare_client_types ()
    {
        // öload the current configuration as reference
        $this->load_efa_config();
        
        $client_dirs = scandir("../uploads");
        $issues = "";
        foreach ($client_dirs as $client_dir) {
            if (is_numeric($client_dir)) {
                $json_filename = "../uploads/" . $client_dir . "/types.json";
                if (! file_exists($json_filename))
                    return i("hBPwLR|The type definition for ...", $client_dir) . "\n";
                $json_string = file_get_contents($json_filename);
                $client_types_raw = json_decode($json_string, true);
                if (! is_array($client_types_raw))
                    return i("Uqbi57|The type definition for ...", $client_dir) . "\n";
                // parse given types.
                $client_types = [];
                foreach ($client_types_raw as $type_record) {
                    if (! isset($client_types[$type_record["Category"]]))
                        $client_types[$type_record["Category"]] = [];
                    $client_types[$type_record["Category"]][intval($type_record["Position"])] = [
                            "Type" => $type_record["Type"],"Position" => $type_record["Position"],
                            "Value" => $type_record["Value"]
                    ];
                }
                // parse all reference categories. The set of categories is assumed to be always equal.
                foreach ($this->types as $category => $this_type_records) {
                    if (! isset($client_types[$category]))
                        $issues .= i("gF0grL|For %1 the category %2 i...", $client_dir, $category) . "\n";
                    elseif (! is_array($client_types[$category]) || (count($client_types[$category]) == 0))
                        $issues .= i("5jgFwZ|For %1, the category %2 ...", $client_dir, $category) . "\n";
                    else {
                        // check whether all types of the reference category have a matching type in the
                        // current client.
                        $missing_in_client_types = "";
                        foreach ($this_type_records as $this_type_record) {
                            $matched = false;
                            foreach ($client_types[$category] as $client_type_record)
                                if (strcasecmp($client_type_record["Type"], $this_type_record["Type"]) == 0)
                                    $matched = true;
                            if (! $matched)
                                $missing_in_client_types .= $this_type_record["Type"] . ", ";
                        }
                        if (strlen($missing_in_client_types) > 0)
                            $issues .= i("EwvOZD|For %1, %2 : %3 is missi...", $client_dir, $category, 
                                    $missing_in_client_types) . "\n";
                        
                        // check whether all types of the current client category have a matching type in
                        // the
                        // reference.
                        $missing_in_reference_types = "";
                        foreach ($client_types[$category] as $client_type_record) {
                            $matched = false;
                            foreach ($this_type_records as $this_type_record)
                                if (strcasecmp($this_type_record["Type"], $client_type_record["Type"]) == 0)
                                    $matched = true;
                            if (! $matched)
                                $missing_in_reference_types .= $client_type_record["Type"] . ", ";
                        }
                        if (strlen($missing_in_reference_types) > 0)
                            $issues .= i("w7n9cV|For %1 there is addition...", $client_dir, $category, 
                                    $missing_in_reference_types) . "\n";
                    }
                }
            }
        }
        return $issues;
    }

    /**
     * Compare all client type configuration settings and issue warnings, if not equal. This will just check a
     * subset of settings, since not all are relevant.
     * 
     * @return String A String containing all issues found. Empty for a complete match at all clients.
     */
    public function compare_client_configs ()
    {
        // öload the current configuration as reference
        $this->load_efa_config();
        // values to check for equality. Just a few, add when more is needed.
        $check_names = ["NameFormat","MustEnterDistance","MustEnterWatersForUnknownDestinations"
        ];
        $client_dirs = scandir("../uploads");
        $issues = "";
        foreach ($client_dirs as $client_dir) {
            if (is_numeric($client_dir)) {
                $json_filename = "../uploads/" . $client_dir . "/config.json";
                $json_string = file_get_contents($json_filename);
                $client_config_raw = json_decode($json_string, true);
                $client_config = [];
                foreach ($client_config_raw as $client_config_record)
                    $client_config[$client_config_record["Name"]] = $client_config_record["Value"];
                foreach ($check_names as $check_name)
                    if (strcasecmp(strval($client_config[$check_name]), strval($this->config[$check_name])) !=
                             0)
                        $issues .= i("iwE1Bg|For %1, the values in pa...", $client_dir, 
                                $check_name) . "\n";
            }
        }
        return $issues;
    }

    /**
     * Plausibilize the logbook period according to the logbook name, if it contains a year as number.
     * 
     * @param array $book_record            
     */
    private function summarize_book_record (array $book_record)
    {
        $book_name = $book_record["Type"] . " '" . $book_record["Name"] . "'";
        $summary_head = $book_name . " (" . $book_record["StartDate"] . " - " . $book_record["EndDate"] . ")";
        $book_year = 1900;
        $pos_book_year = strpos($book_record["Name"], "20");
        if (($pos_book_year !== false) && (mb_strlen($book_record["Name"]) >= ($pos_book_year + 4))) {
            $book_year = intval(substr($book_record["Name"], $pos_book_year));
        }
        if ($book_year < 2000)
            return $summary_head . ": " .
                     i("HFNMBp|The expected calendar ye...");
        // TODO replace $this->datetotime by $this->toolbox->datetotime and remove private function herein
        // from 2.3.2_12 onwards.
        $start_time = $this->datetotime($book_record["StartDate"]);
        $end_time = $this->datetotime($book_record["EndDate"]);
        // TODO replace $this->datetotime by $this->toolbox->datetotime and remove private function herein
        // from 2.3.2_12 onwards.
        if (($end_time - $start_time) < (364 * 86400))
            return $summary_head . ": " . i("ZgYTwD|The period of is shorter...");
        if (intval(date("Y", $end_time)) != $book_year)
            return $summary_head . ": " . i("FM9hJL|The end date does not ma...");
        return $summary_head . ": " . i("fANxN5|ok.");
    }

    // TODO remove private function herein from 2.3.2_12 onwards.
    /**
     * Convert a date String to a time for DE and ISO format dates (23.07.2021 and 2021-07-23)
     * 
     * @param String $date_string            
     */
    private function datetotime (String $date_string)
    {
        if (strpos(($date_string), ".") !== false) {
            $dmy = explode(".", $date_string);
            $date_string = $dmy[2] . "-" . $dmy[1] . "-" . $dmy[0];
        }
        return strtotime($date_string);
    }

    /**
     * collect all logbooks from all clients into a dedicated json file.
     */
    private function compile_books ()
    {
        $client_dirs = scandir("../uploads");
        $logbooks = [];
        $clubworkbooks = [];
        $summary = "";
        foreach ($client_dirs as $client_dir) {
            if (is_numeric($client_dir)) {
                $json_filename = "../uploads/" . $client_dir . "/project.json";
                $json_string = file_get_contents($json_filename);
                $client_project_cfg = json_decode($json_string, true);
                foreach ($client_project_cfg as $client_project_record) {
                    if (strcasecmp($client_project_record["Type"], "Logbook") == 0) {
                        if (! isset($logbooks[$client_project_record["Name"]])) {
                            $logbooks[$client_project_record["Name"]] = $client_project_record;
                            $summary .= $this->summarize_book_record($client_project_record) . "\n";
                        }
                    }
                    if (strcasecmp($client_project_record["Type"], "Clubworkbook") == 0) {
                        if (! isset($clubworkbooks[$client_project_record["Name"]])) {
                            $clubworkbooks[$client_project_record["Name"]] = $client_project_record;
                            $summary .= $this->summarize_book_record($client_project_record) . "\n";
                        }
                    }
                }
            }
        }
        file_put_contents("../config/client_cfg/logbooks.json", json_encode($logbooks));
        file_put_contents("../config/client_cfg/clubworkbooks.json", json_encode($clubworkbooks));
        file_put_contents("../config/client_cfg/summary.txt", $summary);
        $this->summary_books = $summary;
    }

    /**
     * This parses all available efa-configuration XML-files as found in the directory ../uploads] and stores
     * them as json in the same directory. For the reference client it updates the "../config/client_cfg/"
     * directory for the reference client and b) passes relevant values to the config_app configuration and c)
     * loads the configuration into memory.
     */
    public function parse_client_configs ()
    {
        // parse one by one
        $client_dirs = scandir("../uploads");
        $cfg = $this->toolbox->config->get_cfg();
        $ref_client_id = intval($cfg["reference_client"]);
        foreach ($client_dirs as $client_dir) {
            if (is_numeric($client_dir)) {
                $client_id = intval($client_dir);
                $this->parse_client_config(($client_id == $ref_client_id) ? - 1 : $client_id);
            }
        }
        // Make sure the books are updated, even if there is no setting for the reference client.
        $this->compile_books();
    }

    /**
     * This parses the efa-configuration XML-files of the reference client as found in the directory
     * ../uploads/[efaCloudUserID] and stores it as json in the same directory. If ($efaCloudUserID_client ==
     * -1) it is a) also stored in the "../config/client_cfg/" directory and b) relevant values are passed to
     * the config_app configuration and c) the configuration is loaded.
     * 
     * @param int $efaCloudUserID_client
     *            set to the efaCloud UserID of the client to parse. Set to -1 to use configured reference
     *            client.
     * @return string a result message
     */
    public function parse_client_config (int $efaCloudUserID_client = -1)
    {
        // get reference client information
        $cfg = $this->toolbox->config->get_cfg();
        $use_reference_client = ($efaCloudUserID_client < 0);
        $client_to_parse = ($use_reference_client) ? intval($cfg["reference_client"]) : $efaCloudUserID_client;
        if ($client_to_parse <= 0)
            return i("V7c5k8|no reference client iden..."); // all configuration arrays will be empty
        
        $client_files = scandir("../uploads/" . $client_to_parse);
        include_once "../classes/tfyh_xml.php";
        $xml = new Tfyh_xml($this->toolbox);
        $from_to = ["efa2project" => "project","efa2types" => "types","efa2config" => "config"
        ];
        foreach ($client_files as $client_file) {
            foreach ($from_to as $from => $to) {
                if (strpos($client_file, "$from") !== false) {
                    $cfg_filename = "../uploads/" . $client_to_parse . "/" . $client_file;
                    $cfg_file = file_get_contents($cfg_filename);
                    if ($cfg_file !== false) {
                        $xml->read_xml($cfg_file, false);
                        // TODO remove csv file export for PHP usage, since 2.3.2_07 / 2.12.2022 obsolete
                        $config_csv = $xml->get_csv("data", "record");
                        if ($use_reference_client)
                            file_put_contents("../config/client_cfg/$to.csv", $config_csv);
                        file_put_contents("../config/$client_to_parse/$to.csv", $config_csv);
                        // firstly only Javascript usage
                        $config_array = $xml->get_array("data", "record");
                        if ($use_reference_client)
                            file_put_contents("../config/client_cfg/$to.json", json_encode($config_array));
                        file_put_contents("../uploads/$client_to_parse/$to.json", json_encode($config_array));
                    } else {
                        // TODO remove csv file delete for PHP usage, since 2.3.2_07 / 2.12.2022 obsolete
                        unlink("../uploads/$client_to_parse/$to.csv");
                        unlink("../uploads/$client_to_parse/$to.json");
                    }
                }
            }
        }
        
        if ($use_reference_client) {
            // compile all logbooks and clubworkbooks to a full list
            $this->compile_books();
            // reload the configuration
            $this->load_efa_config();
            // some configuration must be transferred into the toolbox application configuration
            $cfg["efa_NameFormat"] = $this->config["NameFormat"];
            $this->toolbox->config->store_app_config($cfg);
        }
        
        return i("iRfNHa|completed for reference ...", $client_to_parse);
    }

    /**
     * This loads the efa-configuration files into the respective associative arrays $this->project,
     * $this->types, $this->config.
     * 
     * @param int $efaCloudUserID_client
     *            set to -1 to use the reference client configuration as parsed into "../config/client_cfg/"
     *            directory or to the respective client ID to use the "../uploads/$efaCloudUserID_client/"
     *            directory as source for the configuration json files.
     */
    public function load_efa_config (int $efaCloudUserID_client = -1)
    {
        // file names to load
        $cfg_file_types = ["project","clubworkbooks","logbooks","types","config"
        ];
        $cfg_arrays = [];
        foreach ($cfg_file_types as $cfg_file_type) {
            if ($efaCloudUserID_client < 0)
                $json_filename = (file_exists("../config/client_cfg/$cfg_file_type.json")) ? "../config/client_cfg/$cfg_file_type.json" : "../config/client_cfg_default/$cfg_file_type.json";
            else
                $json_filename = "../uploads/$efaCloudUserID_client/$cfg_file_type.json";
            if (file_exists($json_filename)) {
                $json_string = file_get_contents($json_filename);
                $cfg_arrays[$cfg_file_type] = json_decode($json_string, true);
            } else
                $cfg_arrays[$cfg_file_type] = [];
        }
        
        // load the project configuration json
        $this->project = [];
        foreach ($cfg_arrays["project"] as $project_record) {
            if (! isset($this->project[$project_record["Type"] . "s"]))
                $this->project[$project_record["Type"] . "s"] = [];
            $this->project[$project_record["Type"] . "s"][] = $project_record;
        }
        // load logbooks
        $this->logbooks = $cfg_arrays["logbooks"];
        $this->clubworkbooks = $cfg_arrays["clubworkbooks"];
        
        // load the types json
        $this->types = [];
        foreach ($cfg_arrays["types"] as $type_record) {
            if (! isset($this->types[$type_record["Category"]]))
                $this->types[$type_record["Category"]] = [];
            $this->types[$type_record["Category"]][intval($type_record["Position"])] = [
                    "Type" => $type_record["Type"],"Position" => $type_record["Position"],
                    "Value" => $type_record["Value"]
            ];
        }
        // sort
        foreach ($this->types as $category => $unsorted) {
            ksort($unsorted);
            $this->types[$category] = $unsorted;
        }
        
        // load the client configuration json
        $this->config = [];
        foreach ($cfg_arrays["config"] as $config_record) {
            $this->config[$config_record["Name"]] = (isset($config_record["Value"])) ? $config_record["Value"] : "";
        }
        
        // assign the direct variables
        // default is the srver configuration setting
        $this->current_logbook = str_replace("JJJJ", date("Y"), 
                $this->toolbox->config->get_cfg()["current_logbook"]);
        // overwrite it with the reference client current logbook, if available.
        if ((intval($this->toolbox->config->get_cfg()["reference_client"]) > 0) &&
                 isset($this->project["Boathouses"][0]["CurrentLogbookEfaBoathouse"]))
            $this->current_logbook = $this->project["Boathouses"][0]["CurrentLogbookEfaBoathouse"];
        // find the the reference client's sports year start via the logbook.
        $logbook_period = $this->get_book_period($this->current_logbook, true);
        $this->logbook_start_time = (isset($logbook_period["start_time"])) ? $logbook_period["start_time"] : 0;
        $this->logbook_end_time = (isset($logbook_period["end_time"])) ? $logbook_period["end_time"] : 0;
        $logbook_period["end_time"];
        $this->sports_year_start = (isset($logbook_period["sports_year_start"])) ? $logbook_period["sports_year_start"] : 1;
    }

    /**
     * Return the start and end time (PHP seconds) and the sports year start for a specific logbook.
     * 
     * @param String $book_name
     *            the name of the logbook or clubworkbook
     * @param bool $is_logbook
     *            set true for a logbook to check and false for a clubworkbook
     * @return array with the fields "start_time" (seconds since epoch), "end_time" (seconds since epoch),
     *         "sports_year_start" (month, 1 .. 12), "book_matched" (bool)
     */
    public function get_book_period (String $book_name, bool $is_logbook)
    {
        $ret = ["book_matched" => false
        ];
        // find the the reference client's sports year start via the logbook.
        $sports_year_start = false;
        $book = false;
        if ($is_logbook) {
            if (isset($this->logbooks) && isset($this->logbooks[$book_name]))
                $book = $this->logbooks[$book_name];
        } else {
            if (isset($this->clubworkbooks) && isset($this->clubworkbooks[$book_name]))
                $book = $this->clubworkbooks[$book_name];
        }
        
        if ($book !== false) {
            $ret["book_matched"] = true;
            // start of day
            $ret["start_time"] = strtotime($this->toolbox->check_and_format_date($book["StartDate"]));
            // end of day
            $ret["end_time"] = strtotime($this->toolbox->check_and_format_date($book["EndDate"])) + 22 * 3600;
            $sports_year_start = substr($book["StartDate"], 0, 6);
        }
        // if failed, use the server confiuration setting
        if ($is_logbook && ($sports_year_start == false)) {
            $sports_year_start = $this->toolbox->config->get_cfg()["sports_year_start"];
            $current_year = intval(date("Y"));
            $logbook_start_time = strtotime($current_year . "-" . $this->sports_year_start . "-1");
            $next_year = $current_year + 1;
            $logbook_end_time = strtotime($next_year . "-" . $this->sports_year_start . "-1") - 2 * 3600;
        }
        $ret["sports_year_start"] = $sports_year_start;
        
        return $ret;
    }

    /**
     * Recursive html display of an array using the &lt;ul&gt; list type.
     * 
     * @param array $a
     *            the array to display
     * @param int $level
     *            the recursion level. To start the recursion, use 0 or leave out.
     */
    public function display_array (array $a, int $level = 0)
    {
        if ($level == 0)
            $this->str_builder = "";
        $indent = substr($this->row_of_64_spaces, 0, $level * 2);
        $this->str_builder .= $indent . "<ul>\n";
        $indent .= " ";
        foreach ($a as $key => $value) {
            $this->str_builder .= $indent . "<li>";
            if (is_array($value)) {
                $this->str_builder .= $key . "\n";
                $this->display_array($value, $level + 1);
            } elseif (is_object($value))
                $this->str_builder .= "$key : [object]";
            else
                $this->str_builder .= "$key : $value";
            $this->str_builder .= "</li>\n";
        }
        $this->str_builder .= $indent . "</ul>\n";
        if ($level == 0)
            return $this->str_builder;
    }

    /**
     * Recursive text display of an array using the &lt;ul&gt; list type.
     * 
     * @param array $a
     *            the array to display
     * @param String $indent0
     *            the indentation at level 0
     * @param int $level
     *            the recursion level. To start the recursion, use 0 or leave out. Do not use any other value!
     */
    public function display_array_text (array $a, String $indent0, int $level = 0)
    {
        if ($level == 0)
            $this->str_builder = "";
        $indent = substr($this->row_of_64_spaces, 0, $level * 2);
        $indent .= " ";
        foreach ($a as $key => $value) {
            $this->str_builder .= $indent0 . $indent;
            if (is_array($value)) {
                $this->str_builder .= $key . ":\n";
                $this->display_array_text($value, $indent0, $level + 1);
            } elseif (is_object($value))
                $this->str_builder .= "$key: [object]" . "\n";
            else
                $this->str_builder .= "$key: $value" . "\n";
        }
        if ($level == 0)
            return $this->str_builder;
    }

    /**
     * Provide a script entry to pass the efa client configuration to efaWeb or the efaCloud javascript
     * environment. Here: single array
     * 
     * @return string the html code t include in the respective file.
     */
    private function json_file_to_script (String $cfg_filename, String $cfg_varname)
    {
        // read configuration as was stored by the client
        $config_file = "../config/client_cfg/" . $cfg_filename;
        if (file_exists($config_file))
            $config_contents = file_get_contents($config_file);
        // on no success read default
        if (! file_exists($config_file) || (strlen($config_contents) < 10)) {
            $config_file_default = "../config/client_cfg_default/" . $cfg_filename;
            $config_contents = file_get_contents($config_file_default);
        }
        return "<script>\nvar $cfg_varname = " . $config_contents . ";\n" . "</script>\n";
    }

    /**
     * Provide a script entry to pass the efa client configuration to efaWeb or the efaCloud javascript
     * environment. Here: full configuration
     * 
     * @return string the html code t include in the respective file.
     */
    public function pass_on_config ()
    {
        $html = "";
        $html .= $this->json_file_to_script("types.json", "efaTypes");
        $html .= $this->json_file_to_script("project.json", "efaProjectCfg");
        $html .= $this->json_file_to_script("config.json", "efaConfig");
        $html .= "<script>\nvar efaCloudCfg = " . json_encode($this->toolbox->config->get_cfg()) . ";\n" .
                 "</script>\n";
        $names_translated_file = file_get_contents("../config/db_layout/names_translated_" . $this->toolbox->config->language_code);
        $html .= "<script>\nvar namesTranslated_php = `" . str_replace("`", "\`", $names_translated_file) .
                 "`;\n" . "</script>\n";
        return $html;
    }

    /**
     * Get an HTML table listing the size of the content transferred over the API.
     * 
     * @param String $client_dir
     *            the name of the directory where the content size table is stored, i.e. the efaCloud user id.
     * @param String $client_name
     *            the name of the client to display at the table header line.
     * @param int $count_of_lines
     *            the count of content size records to be shown, max is 14.
     */
    private function get_content_sizes (String $client_dir, String $client_name, int $count_of_lines)
    {
        global $dfmt_d, $dfmt_dt;
        if (file_exists("../log/contentsize/" . $client_dir)) {
            $active_client_table = trim(file_get_contents("../log/contentsize/" . $client_dir));
            // reverse order, most recent on top
            $active_client_lines = explode("\n", $active_client_table);
            $active_client_table = "";
            $n = count($active_client_lines);
            for ($l = $n - 1; ($l > 0) && ($l > ($n - $count_of_lines) - 2); $l --) {
                if ($l == $n - 1)
                    $active_client_header = "<tr><th style='width:25%'>" . $client_name .
                             "</th><th style='width:25%'>" . i("gCxn2q|Number of requests") .
                             "</th><th style='width:25%'>" . i("MnUz4K|Size Requests") .
                             "</th><th style='width:25%'>" . i("bq7XVl|Size responses") . "</th></tr>";
                $values = explode(";", $active_client_lines[$l]);
                $values[0] = date($dfmt_d, strtotime($values[0]));
                $values[2] = str_replace(".", ",", intval(intval($values[2]) / 10485.76) / 100) . " MByte";
                $values[3] = str_replace(".", ",", intval(intval($values[3]) / 10485.76) / 100) . " MByte";
                $active_client_table .= "<tr><td>" . $values[0] . "</td><td>" . $values[1] . "</td><td>" .
                         $values[2] . "</td><td>" . $values[3] . "</td></tr>\n";
            }
            return "<table>" . $active_client_header . $active_client_table . "</table>";
        }
    }

    /**
     * Return a String with the list of all available books
     * 
     * @param array $client_project
     *            the project configuration to use
     * @param String $list_title
     *            The title für the list, like "Fahrtenbücher" or "Vereinsarbeitsbücher"
     * @param String $list_type
     *            The type to use in the configuration like "Logbook" or "Clubworkbook"
     * @param String $show_dates
     *            The type to use in the configuration like "Logbook" or "Clubworkbook"
     */
    private function booklist (array $client_project, String $list_title, String $list_type, bool $show_dates)
    {
        $l = 0;
        $booklist = "<br>$list_title: ";
        foreach ($client_project as $project_record) {
            if (strcasecmp($project_record["Type"], "$list_type") == 0) {
                $booklist .= $project_record["Name"];
                $booklist .= ($show_dates) ? " (" . str_replace(".20", ".", $project_record["StartDate"]) . "-" .
                         str_replace(".20", ".", $project_record["EndDate"]) . "), " : ", ";
                $l ++;
            }
        }
        if ($l > 0)
            $booklist = mb_substr($booklist, 0, mb_strlen($booklist) - 2);
        else
            $booklist = "";
        return $booklist;
    }

    /**
     * Get the last access of every API client
     * 
     * @param Tfyh_socket $socket
     *            the common data base access socket
     * @param bool $html
     *            set true to get html encoded output, false for plain text
     * @param bool $contentsize
     *            set true to get the contentsize table, false to omit.
     * @return string the last access information on every client.
     */
    public function get_last_accesses_API (Tfyh_socket $socket, bool $html, bool $contentsize)
    {
        $client_dirs = scandir("../uploads");
        $cfg_types = ["project","types","config"
        ];
        $client_read_accesses = scandir("../log/lra");
        $active_clients_txt = "";
        $active_clients_html = "<ul>";
        $content_size_tables = "";
        $client_dirs = scandir("../uploads");
        $client_names = [];
        foreach ($client_dirs as $client_dir) {
            if (is_numeric($client_dir)) {
                $active_clients_html .= "<li>";
                $client_record = $socket->find_record("efaCloudUsers", "efaCloudUserID", $client_dir);
                if ($client_record !== false) {
                    $client_names[$client_dir] = $client_record["Vorname"] . " " . $client_record["Nachname"];
                    $active_client_txt = "<b>" . $client_names[$client_dir] . "</b> (#" .
                             $client_record["efaCloudUserID"] . ", " . $client_record["Rolle"] . ")";
                    if (file_exists("../log/lra/" . $client_dir))
                        $active_client_txt .= ", " . i("z1kw92|last activity:") . " " .
                                 date("d.m.Y H:i", strtotime(file_get_contents("../log/lra/" . $client_dir)));
                    else
                        $active_client_txt .= ", " . i("4fsAVc|last activity not known");
                    $active_client_txt .= ", " . i("uvguxW|existing configuration:") . " ";
                    $cfg_list = "";
                    foreach ($cfg_types as $cfg_type) {
                        if (file_exists("../uploads/$client_dir/$cfg_type.json"))
                            $cfg_list .= $cfg_type . ", ";
                    }
                    if (mb_strlen($cfg_list) > 0)
                        $cfg_list = mb_substr($cfg_list, 0, mb_strlen($cfg_list) - 2);
                    $active_client_txt .= $cfg_list;
                    if (file_exists("../uploads/$client_dir/project.json")) {
                        $client_project = json_decode(
                                file_get_contents("../uploads/$client_dir/project.json"), true);
                        $active_client_txt .= $this->booklist($client_project, i("UEM4kg|Logbooks"), "Logbook", 
                                false);
                        $active_client_txt .= $this->booklist($client_project, i("2jDemj|Clubwork books"), 
                                "ClubworkBook", false);
                    }
                    $active_clients_txt .= str_replace("<br>", "\n", 
                            str_replace("<b>", "", str_replace("</b>", "", $active_client_txt))) . "\n";
                    $active_clients_html .= $active_client_txt;
                    $is_boathouse = (strcasecmp($client_record["Rolle"], "bths") == 0);
                } else {
                    $active_clients_html .= i(
                            "mwgz0K|For the still existing c...", 
                            $client_dir);
                }
                $active_clients_html .= "</li>";
                $active_clients_txt .= "\n";
                if (strcasecmp($client_record["Rolle"], "bths") == 0)
                    $content_size_tables .= $this->get_content_sizes($client_dir, $client_names[$client_dir], 
                            4) . "<br>";
            }
        }
        if (strlen($content_size_tables) > 0)
            $content_size_tables = "<h4>" .
                     i("52B267|Traffic volume of the la...") . "</h4>" .
                     $content_size_tables;
        return ($html) ? $active_clients_html . "</ul>" . $content_size_tables : $active_client_txt;
    }

    /**
     * This is to correct the frequent mistyping of logbook names with years rather than with "JJJJ". Will
     * only work until 2099 and with a four digit year indicator.
     */
    public function check_and_correct_efaCloudConfig ()
    {
        $cfg = $this->toolbox->config->get_cfg();
        $changed = false;
        foreach (["current_logbook","current_logbook2","current_logbook3","current_logbook4"
        ] as $logbook_name) {
            if (isset($cfg[$logbook_name]) && (strlen($cfg[$logbook_name]) >= 4) &&
                     (strpos($cfg[$logbook_name], "JJJJ") === false) &&
                     (strpos($cfg[$logbook_name], "20") !== false)) {
                $pos20xx = strpos($cfg[$logbook_name], "20");
                if (strlen($cfg[$logbook_name]) >= ($pos20xx + 4)) {
                    $cfg[$logbook_name] = str_replace(substr($cfg[$logbook_name], $pos20xx, 4), "JJJJ", 
                            $cfg[$logbook_name]);
                    $changed = true;
                }
            }
        }
        if ($changed)
            $this->toolbox->config->store_app_config($cfg);
    }
}    
