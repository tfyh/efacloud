<?php
/**
 *
 *       the tools-for-your-hobby framework
 *       ----------------------------------
 *       https://www.tfyh.org
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
 * A utility class to log actions, warnings and login fails. Uses the constructor setting to identify the
 * place where to put the log to.
 */
class Tfyh_logger
{

    /**
     * Use this constant to put the message to the "Done"-log. (Information logging)
     */
    public static $TYPE_DONE = 0;

    /**
     * Use this constant to put the message to the "Warn"-log. (Warnings-logging)
     */
    public static $TYPE_WARN = 1;

    /**
     * Use this constant to put the message to the "Fail"-log. (Error-logging)
     */
    public static $TYPE_FAIL = 2;

    /**
     * Path to save the "Done"-log.
     */
    private $log_app_info;

    /**
     * Path to save the "Warn"-log.
     */
    private $log_app_warnings;

    /**
     * Path to save the "Fail"-log.
     */
    private $log_app_errors;

    /**
     * Path to save the web site activities. Format is timestamp;type
     */
    private $log_init_login_error;

    /**
     * Path to save the web site activities. Format is timestamp;type
     */
    private $daily_inits_logins_errors;

    /**
     * Path to log all bulk transactions
     */
    private $log_app_bulk_txs;

    /**
     * The common toolbox used.
     */
    private $toolbox;

    private $logs = ["app_info.log","app_warnings.log","app_errors.log","app_init_login_error.log",
            "app_bulk_txs.log","sys_cronjobs.log","debug_init.log","sys_shutdowns.log","app_audit.log",
            "sys_timestamps.log","debug_app.log","debug_sql.log"
    ];

    private $activities_de = ["init" => "Seitenaufrufe","login" => "Tomeldungen","error" => "Error"
    ];

    /**
     * public Constructor.
     * 
     * @param array $toolbox
     *            the basic utilities of the application.
     */
    public function __construct (Tfyh_toolbox $toolbox)
    {
        $log_dir = "../log/";
        $this->log_app_info = $log_dir . "app_info.log";
        $this->log_app_warnings = $log_dir . "app_warnings.log";
        $this->log_app_errors = $log_dir . "app_errors.log";
        $this->log_init_login_error = $log_dir . "app_init_login_error.log";
        $this->log_app_bulk_txs = $log_dir . "app_bulk_txs.log";
        $this->daily_inits_logins_errors = $log_dir . "daily_inits_logins_errors.csv";
        $this->toolbox = $toolbox;
        // add the default logs to the logger configuration.
        foreach ($this->logs as $log)
            if (! in_array($log, $toolbox->config->settings_tfyh["logger"]["logs"]))
                $toolbox->config->settings_tfyh["logger"]["logs"][] = $log;
    }

    /**
     * Write a timestamp of a PHP script execution duration for monitoring and statistics. Shall be called at
     * the end of the script execution.
     * 
     * @param int $userID
     *            the user who requested the page
     * @param String $user_requested_file_timestamp
     *            the file which was requested. in case of api access: the transaction (e.g. api/nop)
     * @param float $php_script_started_at
     *            The time when the script started.
     */
    public function put_timestamp (int $userID, String $user_requested_tx_timestamp, 
            float $php_script_started_at)
    {
        $timestamp = strval(time()) . ";" . $userID . ";" . $user_requested_tx_timestamp . ";" .
                 substr(strval(microtime(true) - $php_script_started_at), 0, 6) . "\n";
        $filename = "../log/sys_timestamps.log";
        if (! file_exists($filename) || (filesize($filename) < 3))
            file_put_contents($filename, "timestamp;user;file;duration\n" . $timestamp);
        file_put_contents($filename, $timestamp, FILE_APPEND);
    }

    /**
     * Copy all logs to the log.previous extension and clear them.
     */
    public function rotate_logs ()
    {
        $maxsize = $this->toolbox->config->settings_tfyh["logger"]["maxsize"];
        foreach ($this->toolbox->config->settings_tfyh["logger"]["logs"] as $logfile) {
            $logpath = "../log/" . $logfile;
            if (file_exists($logpath) && (filesize($logpath) > $maxsize)) {
                if (file_exists($logpath . ".previous"))
                    unlink($logpath . ".previous");
                rename($logpath, $logpath . ".previous");
                file_put_contents($logpath, "");
            }
        }
    }

    /**
     * Remove obsolete files from the log directory according to the logger config
     */
    public function remove_obsolete ()
    {
        foreach ($this->toolbox->config->settings_tfyh["logger"]["obsolete"] as $obsolete)
            if (file_exists("../log/" . $obsolete))
                unlink("../log/" . $obsolete);
    }

    /**
     * Zip all logs into the monitoring report. Returns the monitoring report file name which sits in the
     * "../log/" directory
     */
    public function zip_logs ()
    {
        $lognames = [];
        $monitoring_report = "monitoring_report.zip";
        $cwd = getcwd();
        chdir("../log/");
        foreach ($this->toolbox->config->settings_tfyh["logger"]["logs"] as $logname)
            if (file_exists($logname))
                $lognames[] = $logname;
        $this->toolbox->zip_files($lognames, $monitoring_report);
        chdir($cwd);
        return $monitoring_report;
    }

    /**
     * log mass transactions.
     * 
     * @param string $type
     *            the activity type to be logged. Must not contain "\n" characters.
     * @param int $user_id
     *            the user for which the log note shall be taken
     * @param string $logNote
     *            the text for the log. Must not contain "\n" characters.
     */
    public function log_bulk_transaction (String $type, int $user_id, string $logNote)
    {
        $logStr = strval(time()) . ";" . $user_id . ";" . str_replace("\n", " / ", $type) . ";" .
                 str_replace("\n", " / ", $logNote) . "\n";
        file_put_contents($this->log_app_bulk_txs, $logStr, FILE_APPEND);
    }

    /**
     * Return all logged mass transactions which are younger than $maxAgeSeconds as list "<br>" separated.
     * Remove those which are older, if requested. Note: the stored list is "\n" spearated.
     * 
     * @param int $days_to_keep
     *            the limit of age for log notes to be listed. Older notes will be deleted when calling this
     *            function.
     * @param bool $remove_older
     *            set true to remove log entries which are older than $days_to_keep
     * @return string
     */
    public function list_and_cleanse_bulk_txs (int $days_to_keep, bool $remove_older)
    {
        $list = "";
        // read log file, split lines and check one by one
        $logfile_in = file_get_contents($this->log_app_bulk_txs);
        $logfile_lines = explode("\n", $logfile_in);
        // get current time
        $now = time();
        $logfile_out = "";
        
        $maxAgeSeconds = $days_to_keep * 24 * 3600;
        foreach ($logfile_lines as $line) {
            $elements = explode(";", $line);
            $period = $now - intval($elements[0]);
            if ($period < $maxAgeSeconds) {
                $logfile_out .= $line . "\n";
                // sort list backwards, most recent to be first.
                $list = date("Y-m-d H:i:s", $elements[0]) . ": " . i("U01t2T|User") . " #" . $elements[1] .
                         " mit '" . $elements[2] .
                         (($elements[3]) ? "'. " . i("T8bnu4|Note:") . " " . $elements[3] : "'.") . "<br>" .
                         $list;
            }
        }
        if ($remove_older == true)
            file_put_contents($this->log_app_bulk_txs, $logfile_out);
        return "<b>" . i("HY5ek8|Bulk transactions of the...", $days_to_keep) . "</b><br>" . $list;
    }

    /**
     * log activities.
     * 
     * @param string $type
     *            the activity type to be logged.
     */
    public function log_init_login_error (String $type)
    {
        file_put_contents($this->log_init_login_error, $type . ";" . time() . "\n", FILE_APPEND);
    }

    /**
     * Collect and cleanse the init_login_error log. Is reads the log and creates an array with the count of
     * activities per type, except the current day. It deletes the collected activities from the activities
     * log and appends the count per type with the date of yesterday to the daily count log.
     */
    public function collect_and_cleanse_init_login_error_log ()
    {
        // set time of today, midnight. made for Europe/Berlin
        $now = new DateTime();
        $now->setTimezone(new DateTimeZone("Europe/Berlin"));
        $today = $now->format("Y-m-d");
        $start_of_today = strtotime($today . " 00:00:00");
        $collectedAt = date("Y-m-d", $start_of_today - 14400); // four hours back from midnight
                                                               
        // collect all previous activities into array
        $activities = explode("\n", file_get_contents($this->log_init_login_error));
        $collected = [];
        $remainder = "";
        foreach ($activities as $activity) {
            if (strlen($activity) > 5) {
                $parts = explode(";", $activity);
                if ((count($parts) > 1) && (intval($parts[1]) < $start_of_today)) {
                    if (! isset($collected[$parts[0]]))
                        $collected[$parts[0]] = 1;
                    else
                        $collected[$parts[0]] ++;
                } else
                    $remainder .= $activity . "\n";
            }
        }
        // replace activities by remainder
        file_put_contents($this->log_init_login_error, $remainder);
        
        // write array.
        $collectedAll = "";
        foreach ($collected as $type => $count)
            $collectedAll .= $collectedAt . ";" . $type . ";" . $count . "\n";
        if (strlen($collectedAll) > 2)
            file_put_contents($this->daily_inits_logins_errors, $collectedAll, FILE_APPEND);
    }

    /**
     * return the activities per day for the last $count_of_days as named array. The
     * get_activities_array()["_types_"] field lists the available types in correct order.
     * 
     * @param int $count_of_days            
     */
    private function get_inits_logins_errors_array (int $count_of_days)
    {
        if (! file_exists($this->daily_inits_logins_errors))
            return [];
        $activities_per_day = explode("\n", file_get_contents($this->daily_inits_logins_errors));
        $today = strtotime(date("Y-m-d")) + 12 * 3600;
        $activities = [];
        $activity["_types_"] = [];
        // collect activities backwards. newest are at end.
        for ($i = count($activities_per_day); $i >= 0; $i --) {
            if (isset($activities_per_day[$i])) {
                $activity = explode(";", $activities_per_day[$i]);
                $day = strtotime($activity[0]);
                if ((($today - $day) / (24 * 3600)) < $count_of_days) {
                    if (! isset($activities[$activity[0]])) // one row per day
                        $activities[$activity[0]] = [];
                    $activities[$activity[0]][$activity[1]] = $activity[2];
                    if (! isset($activities["_types_"][$activity[1]])) // one row per day
                        $activities["_types_"][$activity[1]] = $activity[2];
                    else
                        $activities["_types_"][$activity[1]] += $activity[2];
                }
            }
        }
        return $activities;
    }

    /**
     * return the inits, logins and errors per day for the last $count_of_days as html table.
     * 
     * @param int $count_of_days            
     */
    public function get_activities_html (int $count_of_days)
    {
        global $dfmt_d, $dfmt_dt;
        $activities = $this->get_inits_logins_errors_array($count_of_days);
        $activity_types = $activities["_types_"];
        // format activities header
        $html = "<table><tr><th>" . i("FPuc1j|Date") . "</th>";
        foreach ($activity_types as $activity_type => $activity_type_count)
            $html .= "<th>" . $this->activities_de[$activity_type] . "</th>";
        $html .= "</tr><tr>";
        // format activities data
        foreach ($activities as $activity_date => $activity_date_types) {
            if (strcmp($activity_date, "_types_") !== 0) {
                $html .= "<td>" . date($dfmt_d, strtotime($activity_date)) . "</td>";
                foreach ($activity_types as $activity_type => $activity_type_count)
                    $html .= "<td>" .
                             ((isset($activity_date_types[$activity_type])) ? $activity_date_types[$activity_type] : "-") .
                             "</td>";
                $html .= "</tr><tr>";
            }
        }
        // format activities sum
        $html .= "<td><b>" . i("KZvGMc|Total %1 days", $count_of_days) . "</b></td>";
        foreach ($activity_types as $activity_type => $activity_type_count)
            $html .= "<td><b>" . $activity_type_count . "</b></td>";
        $html .= "</tr></table>";
        
        return $html;
    }

    /**
     * return the inits, logins and errors per day for the last $count_of_days as csv, one line per day.
     * 
     * @param int $count_of_days            
     */
    public function get_activities_csv (int $count_of_days)
    {
        $activities = $this->get_inits_logins_errors_array($count_of_days);
        $activity_types = $activities["_types_"];
        // format activities header
        $text = i("gDeoVF|Date;");
        foreach ($activity_types as $activity_type => $activity_type_count)
            $text .= $activity_type . ";";
        $text = mb_substr($text, 0, mb_strlen($text) - 1) . "\n";
        // format activities data
        foreach ($activities as $activity_date => $activity_date_types) {
            $text .= $activity_date . ";";
            foreach ($activity_types as $activity_type => $activity_type_count)
                $text .= ((isset($activity_date_types[$activity_type])) ? $activity_date_types[$activity_type] : "") .
                         ";";
            $text = mb_substr($text, 0, mb_strlen($text) - 1) . "\n";
        }
        // format activities sum
        $text .= i("1smfqD|Total %1 days;", $count_of_days);
        foreach ($activity_types as $activity_type => $activity_type_count)
            $text .= $activity_type_count . ";";
        $text = mb_substr($text, 0, mb_strlen($text) - 1);
        return $text;
    }

    /**
     * log application information into app_info.log, app_warnings.log and app_errors.log.
     * 
     * @param int $type
     *            one of $TYPE_DONE ( == 0), $TYPE_WARN ( == 1), $TYPE_FAIL ( == 2)
     * @param int $user_id
     *            the user for which the log note shall be taken
     * @param string $logNote
     *            the text for the log
     */
    public function log (int $type, int $user_id, string $logNote)
    {
        $logPath = ($type == self::$TYPE_DONE) ? $this->log_app_info : (($type == self::$TYPE_WARN) ? $this->log_app_warnings : $this->log_app_errors);
        $logStr = strval(time()) . ";" . date("d.m H:i:s", time()) . ";" . $user_id . ";" . $logNote . "\n";
        if (filesize($logPath) > 500000) {
            copy($logPath, $logPath . ".previous");
            file_put_contents($logPath, i("YfDoBA|Continued") . "\n");
        }
        file_put_contents($logPath, $logStr, FILE_APPEND);
    }

    /**
     * Return all logged actions which are younger than $maxAgeSeconds as list "\n" separated. Remove those
     * which are older, if requested.
     * 
     * @param int $type
     *            one of $TYPE_DONE, $TYPE_WARN, $TYPE_FAIL
     * @param int $days_to_keep
     *            the limit of age for log notes to be listed. Older notes will be deleted when calling this
     *            function.
     * @param bool $remove_older
     *            set true to remove log entries which are older than $days_to_keep
     * @return string
     */
    public function list_and_cleanse_entries (int $type, int $days_to_keep, bool $remove_older)
    {
        $list = "";
        $logPath = ($type == 0) ? $this->log_app_info : (($type == 1) ? $this->log_app_warnings : $this->log_app_errors);
        if (! file_exists($logPath))
            return "";
        
        // read log file, split lines and check one by one
        $logfile_in = file_get_contents($logPath);
        $logfile_lines = explode("\n", $logfile_in);
        // get current time
        $now = time();
        $logfile_out = "";
        
        $maxAgeSeconds = $days_to_keep * 24 * 3600;
        foreach ($logfile_lines as $line) {
            $elements = explode(";", $line);
            $period = $now - intval($elements[0]);
            if ($period < $maxAgeSeconds) {
                $logfile_out .= $line . "\n";
                // sort list backwards, most recent to be first.
                $list = $elements[1] . ": " . $elements[2] . ", " .
                         ((isset($elements[3])) ? $elements[3] : "-") . "\n" . $list;
            }
        }
        if ($remove_older == true)
            file_put_contents($logPath, $logfile_out);
        return $list;
    }
}
