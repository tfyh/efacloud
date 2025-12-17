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
 * Page display file. Shows all mails available to public from ARB Verteler.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

echo "<!-- START OF content -->\n<div class='w3-container'>\n";
echo "<h3>" . i("jkjFR8|Changes to data") . "</h3>";
echo "<p>" .
         i(
                "jvNIEJ|Each change to data is r...") .
         "</p>";
$socket->cleanse_change_log(100); // keep changes for max. 100 days.
echo $socket->get_change_log();
echo "<!-- END OF Content -->\n</div>";
end_script();
