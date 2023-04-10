<?php
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
    $toolbox->display_error(i("yMxAqF|Not allowed."), 
            i("9tMKlx|The °%1° page must be ca...", $user_requested_file), 
                    $user_requested_file);
else
    $trash_record = $socket->find_record_matched("efaCloudTrash", ["ID" => $id
    ]);
$tablename = $trash_record["Table"];
$ctrl_replaced = preg_replace('/[[:cntrl:]]/', '', $trash_record["TrashedRecord"]);
$trashed_record = json_decode($ctrl_replaced, true);

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
echo i("IknQ2j| ** Data record display ...", $tablename, $trash_record["TrashedAt"]);
foreach ($trashed_record as $key => $value) {
    echo "<tr><td>" . $key . "</td><td>" . $value . "</td></tr>\n";
}

echo "</table></div>"; 
end_script();
