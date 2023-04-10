<?php
/**
 * Page display file. Shows all logs of the application.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

if (isset($_GET["category"]))
    $category = $_GET["category"];
else
    $category = "app";
if (isset($_GET["type"]))
    $type = $_GET["type"];
else
    $type = "info";

$selection = "<div class='w3-row'>";
$available_logs = $toolbox->config->settings_tfyh["logger"]["logs"];
$categories_to_show = ["api" => i("7DhJc1|Connection"),"app" => i("hOqvCZ|Server application"),
        "debug" => i("NapRG7|Debugging"),"sys" => i("PnEMKA|System messages")
];
$types_to_show = ["info" => i("Y1BObA|Information"),"warnings" => i("sbWQdK|Warnings"),
        "errors" => i("qZ10EG|Errors"),"bulk_txs" => i("EJN8bL|Bulk transactions"),
        "api" => i("rH1lEJ|Connection"),"app" => i("hjdoBL|Server application"),
        "cronjobs" => i("MzCIkc|Housekeeping tasks"),"db_audit" => i("c6Z279|Data base audit"),
        "efa_tools" => i("zwn7oV|Data base audit")
];
$configured_logs = [];
foreach ($available_logs as $available_log) {
    $category_and_type = explode("_", $available_log, 2);
    if (! isset($configured_logs[$category_and_type[0]]))
        $configured_logs[$category_and_type[0]] = [];
    $configured_logs[$category_and_type[0]][] = str_replace(".log", "", $category_and_type[1]);
}

foreach ($categories_to_show as $category_to_show => $category_display) {
    $heading = "<div class='w3-col l4'><h5>" . $category_display . "<h5><p>";
    $files_found = "";
    foreach ($types_to_show as $type_to_show => $type_display) {
        $filename = "../log/" . $category_to_show . "_" . $type_to_show . ".log";
        if (file_exists($filename))
            $files_found .= "<a href='?category=" . $category_to_show . "&type=" . $type_to_show .
                     "' class='formbutton'>" . $type_display . "</a><br><br>";
    }
    if (strlen($files_found) > 0)
        $selection .= $heading . $files_found . "</p></div>";
}
$selection .= "</div>";

$log = "<h4><b>" . $categories_to_show[$category] . ", " . $types_to_show[$type] . "</b> " .
         i("C3bKnx|from the file") . " '";
$filename = "../log/" . $category . "_" . $type . ".log";
$log .= $filename . "'</h4><ul>";
if (! file_exists($filename))
    $log .= i("0SC9ay|File does not exist.");
else {
    $split = "";
    if (strcasecmp($categories_to_show[$category], "Connection") == 0) {
        $log_lines = explode("\n[20", file_get_contents($filename));
        $split = "[20";
    } else
        $log_lines = explode("\n", file_get_contents($filename));
    for ($l = count($log_lines) - 1; $l >= 0; $l --)
        if (strlen($log_lines[$l]) > 5) {
            if (strcasecmp($categories_to_show[$category], "Server application") == 0)
                $log .= "<li>" . htmlspecialchars(str_replace(";", "; ", substr($log_lines[$l], 11))) . "</li>";
            else
                $log .= "<li>" . htmlspecialchars($split . $log_lines[$l]) . "</li>";
        }
}
$log .= "</ul>";

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

echo i("AAnHHF| ** Server messages ** I...");
echo $selection;
echo i("lTMUZN|</div><div class=°w3-ro...");
echo $log;
echo i("o5pCKD|</div><!-- END OF Conte...");
end_script();
