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


// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
if (! isset($_GET["table"])) {
    echo i("sgBY7F|Error: No table name was...");
    exit();
} elseif (! isset($_GET["ecrid"])) {
    echo i("zR3M1H|Error: No efacloud recor...");
    exit();
}

$record = $socket->find_record($_GET["table"], "ecrid", $_GET["ecrid"]);
if ($record == false) {
    echo i("jVHp9d|Error: The record in tab...", $_GET["table"], 
            $_GET["ecrid"]);
    exit();
}

foreach ($record as $key => $value) {
    if (strcmp($key, "ecrhis") !== 0)
        echo "<b>$key</b>: $value<br>\n";
}
if (isset($record["ecrhis"])) {
    echo "<hr><b>".i("YhzWIM|Change history")."</b>";
    echo $socket->get_history_html($record["ecrhis"]);
}
end_script(false);
