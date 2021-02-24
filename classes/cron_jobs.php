<?php

/**
 * static class container file for a daily jobs routine. It may be triggered by whatever, checks whther it was already run this day and if not, starts the sequence.
 */
class Cron_jobs
{

    /**
     * run all daily jobs.
     *
     * @param Toolbox $toolbox
     *            application toolbox
     * @param Socket $socket
     *            the socket to connect to the database
     * @param int $app_user_id
     *            the id of the invoking user.
     */
    public static function run_daily_jobs (Toolbox $toolbox, Socket $socket, int $app_user_id)
    {
        
        // Check whether a day went by.
        $time_last_run = (file_exists("../log/last_day_daily")) ? file_get_contents("../log/last_day_daily") : 0;
        $today = date("Y-m-d");
        if (strcmp($time_last_run, $today) == 0)        
            return;
        
        // refresh timer as first action, to avoid duplicate triggering by
        // different users.
        file_put_contents("../log/last_day_daily", $today);
        $toolbox->logger->log(Tfyh_logger::$TYPE_DONE, $app_user_id, 
                $today . ": Starting daily jobs.");
        
        // run a backup.
        $socket->cleanse_change_log(14);
        include_once ("../classes/backup_handler.php");
        $backup_handler = new backup_handler("../log/", $toolbox, $socket);
        $backup_handler->backup();
        $toolbox->logger->log(Tfyh_logger::$TYPE_DONE, $app_user_id,
                $today . ": Backup completed.");
        
        // Cleansing the change log and activity logs first
        $toolbox->logger->log(Tfyh_logger::$TYPE_DONE, $app_user_id,
                $today . ": Changelog cleansed.");
        $toolbox->logger->list_and_cleanse_entries(0, 100, true); // Tfyh_Logger::$TYPE_DONE == 0
        $toolbox->logger->list_and_cleanse_entries(1, 100, true); // Tfyh_Logger::$TYPE_WARN == 1
        $toolbox->logger->list_and_cleanse_entries(2, 100, true); // Tfyh_Logger::$TYPE_FAIL == 2
        $toolbox->logger->log(Tfyh_logger::$TYPE_DONE, $app_user_id,
                $today . ": Activity log cleansed.");
        
        // cleans the activities (inits, errors).
        $toolbox->logger->collect_and_cleanse_activities();
        $toolbox->logger->log(Tfyh_logger::$TYPE_DONE, $app_user_id,
                $today . ": Activity statistics collected.");
        
        $toolbox->logger->log(Tfyh_logger::$TYPE_DONE, $app_user_id, 
                $today . ": Daily jobs completed.");
    }

}
