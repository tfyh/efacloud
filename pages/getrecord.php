<?php

/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2022 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
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
