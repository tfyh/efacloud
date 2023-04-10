<?php
/**
 * Page display file. Shows all mails available to public from ARB Verteler.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

// identify client for statistics to show
$type = (isset($_GET["type"])) ? intval($_GET["type"]) : 1;
$client_id = (isset($_GET["clientID"])) ? intval($_GET["clientID"]) : - 1;
if ($client_id >= 0)
    $client_record = $socket->find_record($toolbox->users->user_table_name, 
            $toolbox->users->user_id_field_name, $client_id);
else
    $client_record = false;
$files_to_show = [1 => "efacloud.log",2 => "synchErrors.log",3 => "auditinfo.txt"
];
$file_to_show = $files_to_show[$type];

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

echo i("WlX6QJ|<!-- START OF content -..."); 

if ($client_record !== false) {
    $filename = "../uploads/" . $client_id . "/$file_to_show";
    echo "<h3>" . i("A8OMxk|Client") . " #" . $client_id . " (" . $client_record["Vorname"] . " " .
             $client_record["Nachname"] . ")</h3><p>";
    echo "<h5>" . i("MBZSgY|Output of the last uploa...") . "'$file_to_show'</h5>";
    echo "<p>" . i("chH4U9|Uploaded:") . " " . date("Y-m-d H:i:s", filectime($filename)) . "</p>";
    $contents = "";
    if (file_exists($filename . ".previous"))
        $contents .= file_get_contents($filename . ".previous");
    $contents .= file_get_contents($filename);
    $lines = explode("\n", trim($contents));
    echo "<ul>";
    if ($type == 3) {
        for ($l = 0; $l < count($lines); $l ++)
            echo "<li>" . $lines[$l] . "</li>";
    } else {
        for ($l = count($lines) - 1; $l >= 0; $l --)
            if (strlen($lines[$l]) > 0)
                echo "<li>" . $lines[$l] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<h4>".i("fudXmI|This page was accessed w...")."</h4><p>";
}
echo "</p>";
echo i("ToJ4AJ|</div><!-- END OF Cont..."); 
end_script();
