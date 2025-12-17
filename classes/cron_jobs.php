<?php

/**
 * static class container file for a daily jobs routine. It may be triggered by whatever, checks whther it was
 * already run this day and if not, starts the sequence.
 */
include_once '../classes/tfyh_cron_jobs.php';

class Cron_jobs extends Tfyh_cron_jobs
{

    /**
     * run all daily jobs.
     * 
     * @param Tfyh_toolbox $toolbox
     *            application toolbox
     * @param Tfyh_socket $socket
     *            the socket to connect to the database
     * @param int $app_user_id
     *            the id of the invoking user.
     * @param bool $skip_configured_jobs
     *            set true to skip the application configured jobs
     */
    public static function run_daily_jobs (Tfyh_toolbox $toolbox, Tfyh_socket $socket, int $app_user_id, 
            bool $skip_configured_jobs = false)
    {
        $daily_run = Tfyh_cron_jobs::run_daily_jobs($toolbox, $socket, $app_user_id);
        
        // add application specific cron jobs here.
        // The sequence is an implicit priority, in case one of the jobs fails.
        if ($daily_run) {
            
            // OPEN LOG
            // --------
            $cronlog = "../log/sys_cronjobs.log";
            file_put_contents($cronlog, date("Y-m-d H:i:s") . " +0: specific efaCloud cronjobs started.\n", 
                    FILE_APPEND);
            $cron_started = time();
            $last_step_ended = time();
            
            // PROJECT CONFIGURED JOBS
            // -----------------------
            // Keep always first to run, in case later jobs fail they will execute
            // Run the configured cron jobs as personal logbook or monitoring report.
            if (! $skip_configured_jobs) {
                self::run_configured_jobs($toolbox, $socket, $app_user_id);
                file_put_contents($cronlog, 
                        date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) .
                                 ": Configured jobs completed.\n", FILE_APPEND);
                $last_step_ended = time();
            }
            
            // VIRTUAL FIELD COMPLETION
            // ----------------------------------
            // Add missing values in helper data fields which are build of multiple direct fields.
            include '../classes/efa_record.php';
            $efa_record = new Efa_record($toolbox, $socket);
            $added_virtuals = $efa_record->check_and_add_empty_virtual_fields($app_user_id);
            file_put_contents($cronlog, 
                    date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) .
                             ": Added missing virtual fields: " . $added_virtuals . ".\n", FILE_APPEND);
            $last_step_ended = time();
            
            // END-DATE DELETION FOR END-DATE == DATE
            // --------------------------------------
            // Needed, because efa will not accept equal Date and EndDate in a logbook entry
            $to_be_corrected = new Tfyh_list("../config/lists/efaAuditCorruptData", 17, "", $socket, $toolbox);
            $to_be_corrected_rows = $to_be_corrected->get_rows();
            $ecrid_index = $to_be_corrected->get_field_index("ecrid");
            $end_date_index = $to_be_corrected->get_field_index("EndDate");
            $record = [ "EndDate" => "" ];
            foreach($to_be_corrected_rows as $row) {
                $ecrid = $row[$ecrid_index];
                $matching_keys = [ "ecrid" => $ecrid ];
                $success = $socket->update_record_matched($app_user_id, "efa2logbook", $matching_keys, $record);
            }
            $last_step_ended = time();
            
            // USAGE STATISTICS
            // ----------------
            include_once "../classes/tfyh_statistics.php";
            $tfyh_statistics = new Tfyh_statistics();
            file_put_contents("../log/efacloud_server_statistics.csv", 
                    $tfyh_statistics->pivot_timestamps(86400, 14));
            file_put_contents($cronlog, 
                    date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) . ": Statistics created.\n", 
                    FILE_APPEND);
            $last_step_ended = time();
            
            // DATA ARCHIVING AND DELETION
            // ---------------------------
            include_once "../classes/efa_archive.php";
            $efa_archive = new Efa_archive($toolbox, $socket, $app_user_id);
            $archive_info = $efa_archive->records_to_archive();
            file_put_contents($cronlog, 
                    date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) . ": Archived: " . $archive_info .
                             ".\n", FILE_APPEND);
            $last_step_ended = time();
            include_once "../classes/efa_tools.php";
            $efa_tools = new Efa_tools($toolbox, $socket);
            $purge_info = $efa_tools->purge_outdated_deleted();
            file_put_contents($cronlog, 
                    date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) .
                             ": Delete stubs purged/existing: " . $purge_info . ".\n", FILE_APPEND);
            $purge_info = $efa_tools->purge_trashed();
            file_put_contents($cronlog, 
                    date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) . ": Delete outdated from trash: " .
                             $purge_info . ".\n", FILE_APPEND);
            $last_step_ended = time();
            
            // TODO remove fix for versions 2.3.1_11..2.3.2_00 later (inserted August 2022, 2.3.2_01)
            // after removal make Efa_archive::$archive_settings private.
            foreach (Efa_archive::$archive_settings as $for_table => $unused_setting) {
                $autocorrected_result = $efa_archive->autocorrect_archive_stubs($for_table, 30);
                if (strlen($autocorrected_result) > 0) {
                    file_put_contents($cronlog, 
                            date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) .
                                     ": Autocorrection of archive references in $for_table: $autocorrected_result.\n", 
                                    FILE_APPEND);
                    $last_step_ended = time();
                }
            }
            file_put_contents($cronlog, 
                    date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) .
                             ": Autocorrection of archive references completed.\n", FILE_APPEND);
            $last_step_ended = time();
            // end of fix for versions 2.3.1_11..2.3.2_00 later (inserted August 2022, 2.3.2_01)
            
            // PARSE THE CLIENT CONFIGURATION
            // ------------------------------
            include_once "../classes/efa_config.php";
            $efa_config = new Efa_config($toolbox);
            $efa_config->check_and_correct_efaCloudConfig();
            $client_config_parsing_result = $efa_config->parse_client_config();
            file_put_contents($cronlog, 
                    date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) .
                             ": Client data parsing completed, $client_config_parsing_result.\n", FILE_APPEND);
            $last_step_ended = time();
            
            // TABLE AUDITING
            // --------------
            include_once "../classes/efa_audit.php";
            $efa_audit = new Efa_audit($toolbox, $socket);
            $audit_log = $efa_audit->data_integrity_audit(false);
            file_put_contents($cronlog, $audit_log, FILE_APPEND);
            file_put_contents($cronlog, 
                    date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) .
                             ": Database integrity audit completed.\n", FILE_APPEND);
            $last_step_ended = time();
            
            // CLOSE LOG
            // ---------
            file_put_contents($cronlog, 
                    date("Y-m-d H:i:s") . ": Cron jobs done. Total cron jobs duration = " .
                             (time() - $cron_started) . ".\n", FILE_APPEND);
        }
    }

    /**
     * Returns true, if a task is due today based on the $task_day specification: starts with a letter (D =
     * Daily, W = Weekly, M = Monthly), continues with a number (for D : 1 always, for W : 1 = Monday, 2 =
     * Tuesday asf., for M : day of month, 31 is the same as ultimo).
     * 
     * @param String $task_day            
     */
    private static function due_today (String $task_day)
    {
        $period = substr($task_day, 0, 1);
        $day = intval(substr(trim($task_day), 1));
        // daily run
        if (strcasecmp($period, "D") == 0) {
            file_put_contents("../log/due_today.log", date("Y-m-d") . ": due today, $task_day = daily run", 
                    FILE_APPEND);
            return true;
        }
        // weekly run
        if ((strcasecmp($period, "W") == 0) && ($day == intval(date("w")))) {
            file_put_contents("../log/due_today.log", date("Y-m-d") . ": due today, $task_day = weekly run", 
                    FILE_APPEND);
            return true;
        }
        // monthly run, any day
        if ((strcasecmp($period, "M") == 0) && ($day == intval(date("j")))) {
            file_put_contents("../log/due_today.log", date("Y-m-d") . ": due today, $task_day = monthly run", 
                    FILE_APPEND);
            return true;
        }
        // monthly run, ultimo. 86400 seconds are 1 day
        if ((strcasecmp($period, "M") == 0) && ($day == 31) && (intval(date("j", time() + 86400)) == 1)) {
            file_put_contents("../log/due_today.log", date("Y-m-d") . ": due today, $task_day = ultimo run", 
                    FILE_APPEND);
            return true;
        }
        return false;
    }

    /**
     * Jobs can be configured to be run together with the cron jobs trigger, which should be called on a daily
     * basis. A job consists of a scheduled day (see Tfyh_tasks->due_today for details) and a task type. Task
     * types are: persLogbook = send a personal logbook extract to all valid efa2persons who have an Email
     * address provided.
     * 
     * @param Tfyh_toolbox $toolbox
     *            application toolbox
     * @param Tfyh_socket $socket
     *            the socket to connect to the database
     */
    private static function run_configured_jobs (Tfyh_toolbox $toolbox, Tfyh_socket $socket, int $app_user_id)
    {
        // get the job list
        $cfg = $toolbox->config->get_cfg();
        if (! isset($cfg["configured_jobs"]) || (mb_strlen($cfg["configured_jobs"]) < 4))
            return;
        
        // decode the jobs
        $configured_jobs = explode("\n", $cfg["configured_jobs"]);
        
        $cronlog = "../log/sys_cronjobs.log";
        $cron_started = time();
        $last_step_ended = $cron_started;
        file_put_contents($cronlog, date("Y-m-d H:i:s") . " +0: configured efaCloud cronjobs started.\n", 
                FILE_APPEND);
        
        // run the jobs
        foreach ($configured_jobs as $configured_job) {
            $configured_job_parts = explode(" ", trim($configured_job));
            file_put_contents($cronlog, 
                    date("Y-m-d H:i:s") . " +0: checking " . trim($configured_job) . ".\n", FILE_APPEND);
            $due_today = Cron_jobs::due_today($configured_job_parts[0]);
            if ($due_today)
                file_put_contents($cronlog, 
                        date("Y-m-d H:i:s") . " +0: " . $configured_job_parts[1] . " is due today.\n", 
                        FILE_APPEND);
            $type = $configured_job_parts[1];
            if ($due_today) {
                
                if (strcasecmp($type, "persLogbook") == 0) {
                    include_once '../classes/efa_logbook.php';
                    $efa_logbook = new Efa_logbook($toolbox, $socket);
                    file_put_contents($cronlog, 
                            date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) .
                                     ": Starting to send personal logbooks.\n", FILE_APPEND);
                    $mails_sent = $efa_logbook->send_logbooks();
                    $toolbox->logger->log(0, $app_user_id, 
                            "Persönliches Fahrtenbuch gesendet an " . $mails_sent . " Personen.");
                    file_put_contents($cronlog, 
                            date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) .
                                     ": Personal logbook sent to " . $mails_sent . " recipients.\n", 
                                    FILE_APPEND);
                    $last_step_ended = time();
                } elseif (strcasecmp($type, "monitoring") == 0) {
                    include_once '../classes/efa_tools.php';
                    $efa_tools = new Efa_tools($toolbox, $socket);
                    $app_status_summary = $efa_tools->create_app_status_summary($toolbox, $socket);
                    $statistics_filename = "../log/efacloud_server_statistics.csv";
                    
                    // create report as zip of log files.
                    $monitoring_report = "../log/" . $toolbox->logger->zip_logs();
                    $admins = $socket->find_records("efaCloudUsers", "Rolle", "admin", 30);
                    include_once '../classes/tfyh_mail_handler.php';
                    $cfg = $toolbox->config->get_cfg();
                    $mail_handler = new Tfyh_mail_handler($cfg);
                    $mails_sent = 0;
                    foreach ($admins as $admin) {
                        $mailfrom = $mail_handler->system_mail_sender;
                        $mailto = $admin["EMail"];
                        $mailsubject = "[" . $cfg["acronym"] . "] Regelbericht efaCloud Überwachung";
                        $mailbody = "<html><body>" . $app_status_summary . $cfg["mail_subscript"] .
                                 $cfg["mail_footer"];
                        $success = $mail_handler->send_mail($mailfrom, $mailfrom, $mailto, "", "", 
                                $mailsubject, $mailbody, $statistics_filename, $monitoring_report);
                        if ($success)
                            $mails_sent ++;
                    }
                    
                    include_once '../classes/tfyh_logger.php';
                    $toolbox->logger->log(0, $app_user_id, 
                            "Regelbericht efaCloud Überwachung gesendet an " . $mails_sent . " Personen.");
                    unlink($monitoring_report);
                    file_put_contents($cronlog, 
                            date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) .
                                     ": Monitoring report sent to " . $mails_sent . " recipients.\n", 
                                    FILE_APPEND);
                    $last_step_ended = time();
                }
            }
        }
    }
}
