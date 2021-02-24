<?php

/**
 * A utility class to log actions, warnings and login fails. Uses the constructor setting to
 * identify the place where to put the log to.
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
    private $logPathDones;

    /**
     * Path to save the "Warn"-log.
     */
    private $logPathWarns;

    /**
     * Path to save the "Fail"-log.
     */
    private $logPathFails;

    /**
     * Path to save the web site activities. Format is timestamp;type
     */
    private $logPathActivities;

    /**
     * Path to save the mass transactions
     */
    private $logPathMassTransactions;

    /**
     * Path to save the last count of login failures.
     */
    private $logPathLoginFails;

    /**
     * public Constructor.
     *
     * @param String $logDir
     *            Directory to which the logs are written.
     */
    public function __construct (String $logDir)
    {
        $this->logPathDones = $logDir . "dones.txt";
        $this->logPathWarns = $logDir . "warns.txt";
        $this->logPathFails = $logDir . "fails.txt";
        $this->logPathActivities = $logDir . "activities.txt";
        $this->logPathMassTransactions = $logDir . "mass_transactions.txt";
        $this->logPathActPerDay = $logDir . "actPerDay.txt";
        $this->logPathLoginFails = $logDir . "login_failures/";
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
    public function log_mass_transaction (String $type, int $user_id, string $logNote)
    {
        $logStr = strval(time()) . ";" . $user_id . ";" . str_replace("\n", " / ", $type) . ";" .
                 str_replace("\n", " / ", $logNote) . "\n";
        file_put_contents($this->logPathMassTransactions, $logStr, FILE_APPEND);
    }

    /**
     * Return all logged mass transactions which are younger than $maxAgeSeconds as list "<br>"
     * separated. Remove those which are older, if requested. Note: the stored list is "\n"
     * spearated.
     *
     * @param int $days_to_keep
     *            the limit of age for log notes to be listed. Older notes will be deleted when
     *            calling this function.
     * @param bool $remove_older
     *            set true to remove log entries which are older than $days_to_keep
     * @return string
     */
    public function list_and_cleanse_mass_transactions (int $days_to_keep, bool $remove_older)
    {
        $list = "";
        // read log file, split lines and check one by one
        $logfile_in = file_get_contents($this->logPathMassTransactions);
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
                $list = date("Y-m-d H:i:s", $elements[0]) . ": User #" . $elements[1] . " mit '" .
                         $elements[2] . (($elements[3]) ? "'. Hinweis:" . $elements[3] : "'.") .
                         "<br>" . $list;
            }
        }
        if ($remove_older == true)
            file_put_contents($this->logPathMassTransactions, $logfile_out);
        return "<b>Sammeltransaktionen der letzten " . $days_to_keep .
                 " Tage</b><br>" . $list;
    }

    /**
     * log activities.
     *
     * @param string $type
     *            the activity type to be logged.
     */
    public function log_activity (String $type)
    {
        file_put_contents($this->logPathActivities, $type . ";" . time() . "\n", FILE_APPEND);
    }

    /**
     * Collect and cleanse activities. Is reads the activities log and creates an array with the
     * count of activities per type, except the current day. It deletes the collected activities
     * from the activities log and appends the count per type with the date of yesterday to the
     * daily count log.
     */
    public function collect_and_cleanse_activities ()
    {
        // set time of today, midnight. made for Europe/Berlin
        $now = new DateTime();
        $now->setTimezone("Europe/Berlin");
        $today = $now->format("Y-m-d");
        $start_of_today = strtotime($today . " 00:00:00");
        $collectedAt = date("Y-m-d", $start_of_today - 14400); // four hours back from midnight
                                                               
        // collect all previous activities into array
        $activities = explode("\n", file_get_contents($this->logPathActivities));
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
        file_put_contents($this->logPathActivities, $remainder);
        
        // write array.
        $collectedAll = "";
        foreach ($collected as $type => $count)
            $collectedAll .= $collectedAt . ";" . $type . ";" . $count . "\n";
        if (strlen($collectedAll) > 2)
            file_put_contents($this->logPathActPerDay, $collectedAll, FILE_APPEND);
    }

    /**
     * return the activities per day for the last $count_of_days as html table.
     *
     * @param int $count_of_days            
     */
    public function get_activities_html (int $count_of_days)
    {
        $activities_per_day = explode("\n", file_get_contents($this->logPathActPerDay));
        $today = strtotime(date("Y-m-d")) + 12 * 3600;
        $activities = [];
        $activity_types = [];
        // collect activities backwards. newest are at end.
        for ($i = count($activities_per_day); $i >= 0; $i --) {
            $activity = explode(";", $activities_per_day[$i]);
            $day = strtotime($activity[0]);
            if ((($today - $day) / (24 * 3600)) < $count_of_days) {
                if (! isset($activities[$activity[0]])) // one row per day
                    $activities[$activity[0]] = [];
                $activities[$activity[0]][$activity[1]] = $activity[2];
                if (! isset($activity_types[$activity[1]])) // one row per day
                    $activity_types[$activity[1]] = $activity[2];
                else
                    $activity_types[$activity[1]] += $activity[2];
            }
        }
        
        // format activities header
        $html = "<table><tr><th>Zugriffsstatistik</th>";
        foreach ($activity_types as $activity_type => $activity_type_count)
            $html .= "<th>" . $activity_type . "</th>";
        $html .= "</tr><tr>";
        // format activities data
        foreach ($activities as $activity_date => $activity_date_types) {
            $html .= "<td>" . $activity_date . "</td>";
            foreach ($activity_types as $activity_type => $activity_type_count)
                $html .= "<td>" . $activity_date_types[$activity_type] . "</td>";
            $html .= "</tr><tr>";
        }
        // format activities sum
        $html .= "<td><b>Summe " . $count_of_days . " Tage</b></td>";
        foreach ($activity_types as $activity_type => $activity_type_count)
            $html .= "<td><b>" . $activity_type_count . "</b></td>";
        $html .= "</tr></table>";
        
        return $html;
    }

    /**
     * log actions, warnings and login fails.
     *
     * @param int $type
     *            one of $TYPE_DONE, $TYPE_WARN, $TYPE_FAIL
     * @param int $user_id
     *            the user for which the log note shall be taken
     * @param string $logNote
     *            the text for the log
     */
    public function log (int $type, int $user_id, string $logNote)
    {
        $logPath = ($type == self::$TYPE_DONE) ? $this->logPathDones : (($type == self::$TYPE_WARN) ? $this->logPathWarns : $this->logPathFails);
        $logStr = strval(time()) . ";" . date("d.m H:i:s", time()) . ";" . $user_id . ";" . $logNote . "\n";
        file_put_contents($logPath, $logStr, FILE_APPEND);
    }

    /**
     * Return all logged actions which are younger than $maxAgeSeconds as list "\n" separated.
     * Remove those which are older, if requested.
     *
     * @param int $type
     *            one of $TYPE_DONE, $TYPE_WARN, $TYPE_FAIL
     * @param int $days_to_keep
     *            the limit of age for log notes to be listed. Older notes will be deleted when
     *            calling this function.
     * @param bool $remove_older
     *            set true to remove log entries which are older than $days_to_keep
     * @return string
     */
    public function list_and_cleanse_entries (int $type, int $days_to_keep, bool $remove_older)
    {
        $list = "";
        $logPath = ($type == Tfyh_Logger::$TYPE_DONE) ? $this->logPathDones : (($type ==
                 Tfyh_Logger::$TYPE_WARN) ? $this->logPathWarns : $this->logPathFails);
        
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
                // sort list backwards, most recet to be first.
                $list = date("Y-m-d H:i:s", $elements[0]) . ": " . $elements[1] . ", " . $elements[2] .
                         "\n" . $list;
            }
        }
        if ($remove_older == true)
            file_put_contents($logPath, $logfile_out);
        return $list;
    }
}