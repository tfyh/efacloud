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
 * Force to run the daily cron jobs
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

include_once ("../classes/cron_jobs.php");
unlink("../log/cronjobs_last_day");
$cronlog_before = file_get_contents("../log/sys_cronjobs.log");
Cron_jobs::run_daily_jobs($toolbox, $socket, $toolbox->users->session_user["@id"], true);
$cronlog_after = file_get_contents("../log/sys_cronjobs.log");
$cronlog_this = (mb_strlen($cronlog_after) > mb_strlen($cronlog_before)) ? mb_substr($cronlog_after, 
        mb_strlen($cronlog_before)) : $cronlog_after;

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');
echo i("qMjfvd| ** The daily maintenan...");
echo str_replace("\n", "<br>", $cronlog_this);

echo "  </p></div>";
end_script();
