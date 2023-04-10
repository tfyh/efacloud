<?php

/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2022 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
$record_to_delete = false;
if (isset($_GET["table"]) && isset($_GET["ID"])) {
    $id_name = "ID";
    $id_value = $_GET["ID"];
} elseif (isset($_GET["table"]) && isset($_GET["ecrid"])) {
    $id_name = "ecrid";
    $id_value = $_GET["ecrid"];
} else
    $toolbox->display_error(i("VhNoKp|Not allowed."), 
            i("zcNkU0|Page °%1° must be called...", $user_requested_file), __FILE__);

$record_to_delete = $socket->find_record_matched($_GET["table"], [$id_name => $id_value
]);
if ($record_to_delete !== false) {
    include_once "../classes/efa_tables.php";
    if (Efa_tables::is_efa_table($_GET["table"])) {
        // efa records are propagated to clients, therefore need to keep a delete stub
        include_once "../classes/efa_record.php";
        $efa_record = new Efa_record($toolbox, $socket);
        $delete_result = $efa_record->modify_record($_GET["table"], $record_to_delete, 3, 
                $_SESSION["User"][$toolbox->users->user_id_field_name], false);
    } else {
        // efacloud records are only stored at the server side and cabn be deleted right away.
        $delete_result = $socket->delete_record($_SESSION["User"][$toolbox->users->user_id_field_name], 
                $_GET["table"], $record_to_delete["ID"]);
        $delete_result = (strlen($delete_result) == 0) ? 0 : 2;
    }
} else
    $delete_result = i("MJ2srE|Record does not exist");

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

echo i("9Mf4oC| ** Delete record ** ");
if (! is_numeric($delete_result))
    echo "<p>" . i("xq5ret|The record with the %1 °...", $id_name, $id_value, $_GET["table"]) . " " .
             $delete_result;
else {
    if (intval($delete_result) == 1)
        echo "<p>" . i("AUhcRm|The record with the %1 °...", $id_name, $id_value, $_GET["table"]);
    else
        echo "<p>" . i("uf5AMD|The record with the %1 °...", $id_name, $id_value, $_GET["table"]);
}
echo "</p>";
if (intval($delete_result) == 2)
    echo "<p>" . i("0xNazF|Unfortunately, a trash r...") . "</p>";
echo i("QhtP9h| ** &gt;&gt; View change...");
end_script(true);
