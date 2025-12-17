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
 * Generic record display file.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
$id = (isset($_GET["id"])) ? intval($_GET["id"]) : 0; // identify user via ID
if ($id == 0)
    $toolbox->display_error(i("CQTQ0R|Not allowed."), 
            i("pYJiV8|The °%1° page must be ca...", $user_requested_file), $user_requested_file);
else
    $trash_record = $socket->find_record_matched("efaCloudArchived", ["ID" => $id
    ]);
$tablename = $trash_record["Table"];
include_once "../classes/efa_tables.php";
include_once "../classes/efa_archive.php";

$efa_archive = new Efa_archive($toolbox, $socket, $toolbox->users->session_user["@id"]);
$archive_records = $efa_archive->get_all_archived_versions($trash_record);
$archived_record = $efa_archive->decode_archived_record($trash_record);
$records_timestamp_list = "";
if ($archive_records === false) {
    // non versionized record, just show the single timestamp
    $archived_for_time = $efa_archive->time_of_non_versionized_record($tablename, $archived_record);
    $archived_for_date = date($dfmt_d, $archived_for_time);
    $age_in_days = intval((time() - $archived_for_time) / 86400);
    $records_timestamp_list .= i("6NrPjS|Key date for archiving:") . " $archived_for_date<br>\n" .
             i("y10Kn9|Age in days:") . " $age_in_days<br>";
} else {
    // versionized record, show all versions timestamps
    $v = 1;
    $youngest = 0;
    foreach ($archive_records as $invalidFrom32 => $archive_record_version) {
        if ($invalidFrom32 > $youngest)
            $youngest = $invalidFrom32;
        $invalidFrom = date($dfmt_d, $invalidFrom32);
        $archive_id = $archive_record_version["ID"];
        $age_in_days = intval((time() - $invalidFrom32) / 86400);
        $records_timestamp_list .= $v . ". " . i("07MoEU|Version valid until:") . " $invalidFrom, " .
                 i("1Hbi5c|Age in days:") . " $age_in_days (ID: <a href='../pages/view_archive_record.php?id=" .
                 $archive_id . "'>" . $archive_id . "</a>)<br>";
        $v ++;
    }
    $age_in_days = intval((time() - $youngest) / 86400);
    $records_timestamp_list = i("WdYaFk|Object valid until:") . " " . date($dfmt_d, $youngest) . ", " .
             i("TCnjRe|Age in days:") . " $age_in_days<br>" . $records_timestamp_list;
}

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
echo i("9Jk1j0| ** Data record display...", $tablename, date($dfmt_dt, strtotime($trash_record["Time"])), 
        $trash_record["ID"], $records_timestamp_list);
foreach ($archived_record as $key => $value) {
    if (in_array($key, Efa_tables::$date_fields[$tablename]) && (strlen($value) > 0))
        $value = date($dfmt_d, strtotime($value));
    echo "<tr><td>" . $key . "</td><td>" . $value . "</td></tr>\n";
}
echo i("YzWJOY|</table></div>");

end_script();
