<?php
/**
 * Page display file. Shows all mails available to public from ARB Verteler.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

$ecrid = (isset($_GET["ecrid"])) ? $_GET["ecrid"] : false;
$tablename = (isset($_GET["table"])) ? $_GET["table"] : false;
$restore = (isset($_GET["restore_version"])) ? intval($_GET["restore_version"]) : 0;
$record = $socket->find_record($tablename, "ecrid", $ecrid);
$modify_result = "";

// restore a version, if requested.
if ($restore > 0) {
    // cache the history
    $history = $record["ecrhis"];
    // clear the record and build anew
    include_once "../classes/efa_record.php";
    $record = Efa_record::clear_record_for_delete($tablename, $record);
    // first restore the history
    $record["ecrhis"] = $history;
    // now rebuild the record
    $versions = $socket->get_history_array($history);
    $record_version = [];
    foreach ($versions as $version)
        if ($version["version"] <= $restore)
            $record_version = array_merge($record_version, $version["record_version"]);
    if (isset($record_version["ecrid"]) && (strcasecmp($record_version["ecrid"], $record["ecrid"]) == 0)) {
        include_once "../classes/efa_tables.php";
        $record_version = Efa_tables::register_modification($record_version, time(), 
                $record_version["ChangeCount"], "update");
        include_once "../classes/efa_record.php";
        $efa_record = new Efa_record($toolbox, $socket);
        $modify_result = $efa_record->modify_record($tablename, $record_version, 2, 
                $toolbox->users->session_user["@id"], false);
        $record = $socket->find_record($tablename, "ecrid", $ecrid);
    }
}

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

echo i("Z7mnAX| ** Version history of a...", $tablename);
if (strlen($modify_result) > 0)
    echo "<h5>" . i("f8865P|Version V%1 of the recor...", $restore, $tablename, $ecrid) . " " . $modify_result .
             "<h5>";
if ($record === false)
    echo i("GkURzg|The record in table °%1°...", $tablename, $ecrid);
if (isset($record["ecrhis"]))
    echo $socket->get_history_html($record["ecrhis"], 
            "../pages/show_history.php?table=" . $tablename . "&ecrid=" . $ecrid);
else
    echo i("pH7Sis|Unfortunately, there is ...");
echo "</div>\n<!-- END OF Content -->";

end_script();
