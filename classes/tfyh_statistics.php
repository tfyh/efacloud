<?php

/**
 * Container to hold the audit class. Shall be run by the cron jobs.
 */
class Tfyh_statistics
{

    /**
     * The pivoted array of timestamps
     */
    public $timestamps_pivot;

    /**
     * last timestamp er client
     */
    public $timestamps_last;

    /**
     * last timestamp er client
     */
    public $timestamps_count;

    /**
     * A caching variable for recursive pivot table to table formatting
     */
    private $string_builder;

    /**
     * empty Constructor.
     */
    public function __construct ()
    {}

    /**
     * Create an html readable summary of the application status to send it per mail to admins.
     */
    public function create_app_status_summary (Tfyh_toolbox $toolbox, Tfyh_socket $socket)
    {
        // Check logbooks
        $total_record_count = 0;
        // check table sizes
        $html = "<h4>" . i("bJ44LM|Tables and records") . "</h4>\n";
        $html .= "<table><tr><th>" . i("WWOePq|Table") . "</th><th>" . i("MzZXhV|Count of records") .
                 "</th></tr>\n";
        $table_names = $socket->get_table_names();
        $total_record_count = 0;
        foreach ($table_names as $tn) {
            $record_count = $socket->count_records($tn);
            $html .= "<tr><td>" . $tn . "</td><td>" . $record_count . "</td></tr>\n";
            $total_record_count += $record_count;
        }
        $html .= "<tr><td>" . i("wNrHCN|Total") . "</td><td>" . $total_record_count . "</td></tr></table>\n";
        
        // Check users and access rights
        $html .= $toolbox->users->get_all_accesses($socket, false);
        
        // Check accessses logged.
        $days_to_log = 14;
        $html .= "<h4>" . i("UU8ZrV|Accesses last %1 days", strval($days_to_log)) . "</h4>\n";
        include_once '../classes/tfyh_statistics.php';
        $tfyh_statistics = new Tfyh_statistics();
        file_put_contents("../log/efacloud_server_statistics.csv", 
                $tfyh_statistics->pivot_timestamps(86400, $days_to_log));
        $html .= "<table><tr><th>" . i("zOZlkQ|User number") . "</th><th>" . i("aCa6RR|User name") .
                 "</th><th>" . i("8N2N5h|Count of accesses") . "</th></tr>\n";
        $timestamps_count_all = 0;
        foreach ($tfyh_statistics->timestamps_count as $clientID => $timestamps_count) {
            $user = (intval($clientID) === - 1) ? i("8FHeg0|anonymous") : ((intval($clientID) === 0) ? i(
                    "phvRfA|undefined") : "User");
            $html .= "<tr><td>" . $clientID . "</td><td>" . $user . "</td><td>" . $timestamps_count .
                     "</td></tr>\n";
            $timestamps_count_all += $timestamps_count;
        }
        $html .= "<tr><td>" . i("nDBbCc|Total") . "</td><td></td><td>" . $timestamps_count_all .
                 "</td></tr></table>\n";
        
        // Check backup
        $html .= "<h4>" . i("0mGwCy|Backups") . "</h4>\n";
        $backup_dir = "../log/backup";
        $backup_files = scandir($backup_dir);
        $backup_files_size = 0;
        $backup_files_count = 0;
        $backup_files_youngest = 0;
        foreach ($backup_files as $backup_file) {
            if (strcasecmp(substr($backup_file, 0, 1), ".") != 0) {
                $backup_files_size += filesize($backup_dir . "/" . $backup_file);
                $lastmodified = filectime($backup_dir . "/" . $backup_file);
                if ($lastmodified > $backup_files_youngest)
                    $backup_files_youngest = $lastmodified;
                $backup_files_count ++;
            }
        }
        $html .= "<p>" . i("CGJnuw|%1 Backup-Archive totall...", strval($backup_files_count), 
                strval(intval($backup_files_size / 1024 / 102) / 10)) . " \n";
        $html .= i("z5D2q8|Latest backup of %1.", date("Y-m-d H:i:s", $backup_files_youngest)) . "</p>\n";
        
        return $html;
    }

    /**
     * Pivot the timestamps according to the pivoting period.
     * 
     * @param int $period
     *            pivoting period in seconds
     * @param int $count
     *            count of periods to pivot
     */
    public function pivot_timestamps (int $period, int $count)
    {
        $timestamps_file = file_get_contents("../log/sys_timestamps.log");
        $timestamps_file_wo_header = explode("\n", $timestamps_file, 2)[1];
        $timestamps_previous_file = file_get_contents("../log/sys_timestamps.log.previous");
        $timestamps_all = $timestamps_previous_file . "\n" . $timestamps_file_wo_header;
        $timestamps_lines = explode("\n", $timestamps_file);
        $timestamps_pivot = [];
        $this->timestamps_last = [];
        $this->timestamps_count = [];
        // end the monitoring iterval at the next full hour.
        $periods_end_at = strtotime(date("Y-m-d H") . ":00:00") + 3600;
        // and start it according to the period length and count requested.
        $periods_start_at = $periods_end_at - $count * $period;
        // Read timestamps file
        for ($l = 1; $l < count($timestamps_lines); $l ++) {
            // skip first line (header)
            $ts_parts = explode(";", trim($timestamps_lines[$l]));
            if (count($ts_parts) >= 4) {
                $ts_time = intval($ts_parts[0]);
                $ts_period_index = intval(($ts_time - $periods_start_at) / $period);
                if (($ts_period_index >= 0) && ($ts_period_index < $count)) {
                    $ts_user = intval($ts_parts[1]);
                    if (! isset($timestamps_pivot[$ts_user]))
                        $timestamps_pivot[$ts_user] = [];
                    if (! isset($this->timestamps_last[$ts_user]))
                        $this->timestamps_last[$ts_user] = 0;
                    if (! isset($this->timestamps_count[$ts_user]))
                        $this->timestamps_count[$ts_user] = 0;
                    $ts_period_start = $periods_start_at + $ts_period_index * $period;
                    // an api container may contain more than one transaction.
                    $ts_accesses = explode(",", $ts_parts[2]);
                    // use the average duration per transaction within the container for monitoring
                    $ts_duration = $ts_parts[3] / count($ts_accesses);
                    // pivot numbers
                    foreach ($ts_accesses as $ts_access) {
                        $this->timestamps_count[$ts_user] ++;
                        if ($ts_time > $this->timestamps_last[$ts_user])
                            $this->timestamps_last[$ts_user] = $ts_time;
                        $ts_access_group = explode("/", $ts_access)[0];
                        $ts_access_type = explode("/", $ts_access)[1];
                        // initialze pivot table structure
                        if (! isset($timestamps_pivot[$ts_user][$ts_access_group])) {
                            $timestamps_pivot[$ts_user][$ts_access_group] = [];
                        }
                        if (! isset($timestamps_pivot[$ts_user][$ts_access_group][$ts_access_type])) {
                            $timestamps_pivot[$ts_user][$ts_access_group][$ts_access_type] = [];
                            for ($i = 0; $i < $count; $i ++) {
                                $period_index = $periods_start_at + $period * $i;
                                $timestamps_pivot[$ts_user][$ts_access_group][$ts_access_type][$period_index] = [];
                                $timestamps_pivot[$ts_user][$ts_access_group][$ts_access_type][$period_index]["sum"] = 0.0;
                                $timestamps_pivot[$ts_user][$ts_access_group][$ts_access_type][$period_index]["max"] = 0.0;
                                $timestamps_pivot[$ts_user][$ts_access_group][$ts_access_type][$period_index]["count"] = 0;
                            }
                        }
                        // add timestamp
                        $timestamps_pivot[$ts_user][$ts_access_group][$ts_access_type][$ts_period_start]["sum"] += $ts_duration;
                        if ($ts_duration >
                                 $timestamps_pivot[$ts_user][$ts_access_group][$ts_access_type][$ts_period_start]["max"])
                            $timestamps_pivot[$ts_user][$ts_access_group][$ts_access_type][$ts_period_start]["max"] = $ts_duration;
                        $timestamps_pivot[$ts_user][$ts_access_group][$ts_access_type][$ts_period_start]["count"] ++;
                    }
                }
            }
        }
        $this->timestamps_pivot = $timestamps_pivot;
        
        // format pivot
        $pivot_linear = "Group;Type;Period;Count;Sum (ms);Average (ms)\n";
        foreach ($timestamps_pivot as $ts_user => $pivot_user)
            foreach ($pivot_user as $ts_access_group => $pivot_access_group)
                foreach ($pivot_access_group as $ts_access_type => $pivot_access_type)
                    foreach ($pivot_access_type as $ts_access_period => $pivot_access_period) {
                        $pivot_linear .= $ts_access_group . ";" . $ts_access_type . ";" .
                                 date("Y-m-d H:i:s", $ts_access_period) . ";" . $pivot_access_period["count"] .
                                 ";" . intval($pivot_access_period["sum"] * 1000) . ";";
                        if ($pivot_access_period["count"] > 0)
                            $pivot_linear .= substr(
                                    strval(
                                            intval(
                                                    1000 * $pivot_access_period["sum"] /
                                                             $pivot_access_period["count"])), 0, 6);
                        else
                            $pivot_linear .= "0";
                        $pivot_linear .= "\n";
                    }
        
        return $pivot_linear;
    }

    public function pivot_user_timestamps_html (int $user_id)
    {
        global $dfmt_d, $dfmt_dt;
        $first_line = ["access_group" => true,"access_type" => true
        ];
        $indent = ["access_type" => "<tr><td></td>","period_start" => "<tr><td></td><td></td>"
        ];
        $timestamps_htmltable = "<table><th>" . i("5yN29k|Type") . "</th><th>" . i("kgufgC|Function") .
                 "</th><th>" . i("eCGctK|Date") . "</th><th>" . i("Jl8rPJ|Number") . "</th><th>" .
                 i("xf8yxL|Average") . "</th><th>" . i("7e8XGU|Maximum") . "</th></tr>\n";
        $ts_pivot = $this->timestamps_pivot[$user_id];
        foreach ($ts_pivot as $ts_access_group => $ts_access_group_pivot) {
            $timestamps_htmltable .= "<tr><td>$ts_access_group</td>";
            $first_line["access_group"] = true;
            foreach ($ts_access_group_pivot as $ts_access_type => $ts_access_type_pivot) {
                $timestamps_htmltable .= (($first_line["access_group"]) ? "<td>$ts_access_type</td>" : $indent["access_type"] .
                         "<td>$ts_access_type</td>");
                $first_line["access_group"] = false;
                $first_line["access_type"] = true;
                foreach ($ts_access_type_pivot as $ts_period_start => $ts_access_kpis) {
                    if ($ts_access_kpis["count"] > 0) {
                        $value = date($dfmt_d, $ts_period_start);
                        $timestamps_htmltable .= (($first_line["access_type"]) ? "<td>$value</td>" : $indent["period_start"] .
                                 "<td>$value</td>");
                        $first_line["access_type"] = false;
                        for ($i = 0; $i < 3; $i ++) {
                            if ($i == 0)
                                $value = ($ts_access_kpis["count"] == 0) ? "-" : intval(
                                        100 * $ts_access_kpis["count"]) / 100;
                            elseif ($i == 1)
                                $value = ($ts_access_kpis["count"] == 0) ? "-" : intval(
                                        100 * $ts_access_kpis["sum"] / $ts_access_kpis["count"]) / 100;
                            elseif ($i == 2)
                                $value = ($ts_access_kpis["max"] == 0) ? "-" : intval(
                                        100 * $ts_access_kpis["max"]) / 100;
                            $timestamps_htmltable .= "<td>$value</td>";
                        }
                        $timestamps_htmltable .= "</tr>\n";
                    }
                }
            }
        }
        return $timestamps_htmltable . "</table>";
    }

    /**
     * Recursive html display of an array using a pivot table type.
     * 
     * @param array $a
     *            the array to display
     * @param int $level
     *            the recursion level. To start the recursion, use 0 or leave out.
     */
    public function display_array_as_table (array $a, int $level = 0)
    {
        if ($level == 0)
            $this->str_builder = "<table>";
        $i = 0;
        foreach ($a as $key => $value) {
            $prefix = "<tr><td>";
            for ($t = 0; $t < $level; $t ++)
                $prefix .= "</td><td>";
            $this->str_builder .= ($i == 0) ? (($level == 0) ? "<tr><td>" : "</td><td>") : $prefix;
            if (is_array($value)) {
                $this->str_builder .= $key . "\n";
                $this->display_array_as_table($value, $level + 1);
            } elseif (is_object($value))
                $this->str_builder .= "$key : [object]";
            else
                $this->str_builder .= "$key : $value";
            $this->str_builder .= "</td></tr>\n";
            $i ++;
        }
        if ($level == 0)
            return $this->str_builder . "</table>\n";
    }
}
