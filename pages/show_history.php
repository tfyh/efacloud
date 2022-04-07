<?php
/**
 * Page display file. Shows all mails available to public from ARB Verteler.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

$search_result_index = (isset($_SESSION["getps"][$fs_id]["searchresultindex"])) ? intval(
        $_SESSION["getps"][$fs_id]["searchresultindex"]) : 0;
if ($search_result_index == 0)
    $toolbox->display_error("Nicht zulässig.", 
            "Die Seite '" . $user_requested_file .
                     "' muss als Folgeseite von Datensatz finden aufgerufen werden.", $user_requested_file);
$tablename = (isset($_SESSION["efa2table"])) ? $_SESSION["efa2table"] : ((isset(
        $_SESSION["search_result"]["tablename"])) ? $_SESSION["search_result"]["tablename"] : "");
$search_result = $_SESSION["search_result"][$search_result_index];

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

?>
<!-- START OF content -->
<div class="w3-container">
	<h2>Versionsverlauf eines Datensatzes</h2>
	<p>Die Versionen sind neueste zuerst aufgeführt, jeweils nur die in der
		Version gegenüber der Vorversion veränderten Datenfelder. Verwendung
		gestattet nur zum geregelten Zweck.</p>
	<h4>Aus der Tabelle '<?php echo $tablename ?>'</h4>
<?php
if (isset($search_result["ecrhis"]))
    echo $socket->get_history_html($search_result["ecrhis"]);
else
    echo "Leider ist für diesen Datensatz keine Historie vorhanden.";
?>
	<!-- END OF Content -->
</div>

<?php
end_script();
