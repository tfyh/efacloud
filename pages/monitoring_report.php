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
