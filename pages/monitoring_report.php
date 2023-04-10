<?php
/**
 * Page display file. A generic contruction message display page.
 *
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

// === APPLICATION LOGIC ==============================================================
include_once '../classes/tfyh_statistics.php';
$statistics = new Tfyh_statistics();
$app_status_summary = "<html>" . $statistics->create_app_status_summary($toolbox, $socket) . "</html>";
file_put_contents("../log/app_status_summary.html", $app_status_summary);
$app_statistics = $statistics->pivot_timestamps(86400, 14);
file_put_contents("../log/app_statistics.csv", $app_statistics);
// create report as zip of log files.
$monitoring_report = "../log/" . $toolbox->logger->zip_logs();
$toolbox->return_file_to_user($monitoring_report, "application/x-binary");

end_script();
