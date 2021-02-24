<?php
/**
 * The page to show the data model.
 */
// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

$is_efa_cloud = (strcasecmp($toolbox->config->app_name, "efaCloud") == 0);
if ($is_efa_cloud) {
    include_once "../classes/efa_tables.php";
    $efa_tables = new Efa_tables($toolbox, $socket);
    include_once "../classes/efa_dataedit.php";
    $efa_dataedit = new Efa_dataedit($toolbox, $socket);
}

// === APPLICATION LOGIC ==============================================================

$structure_html = "<h4>Datenstruktur</h4>";
$total_table_count = 0;
$table_names = $socket->get_table_names();
$summary = "";
foreach ($table_names as $tn) {
    $record_count = $socket->count_records($tn);
    $structure_html .= "<h5>" . $tn . " (" . $record_count . " Datensätze)</h5>";
    $data_key = ($is_efa_cloud) ? Efa_tables::$key_fields[$tn] : $socket->get_indexes($tn, ! $is_efa_cloud);
    $data_keys = "";
    if ($is_efa_cloud)
        foreach ($data_key as $key_field) {
            if (strcasecmp($key_field, "ValidFrom") == 0)
                $structure_html .= "<p>Die Tabelle ist versioniert.</p>";
            $data_keys .= $key_field . ", ";
        }
    $fixid_auto_field = ($is_efa_cloud && isset($efa_tables->fixid_auto_field[$tn])) ? $efa_tables->fixid_auto_field[$tn] : false;
    $fix_comment = "";
    $UUIDcomment = "";
    $UUIDlistComment = "";
    $total_record_count += $record_count;
    $total_table_count ++;
    $column_names = $socket->get_column_names($tn);
    $column_types = $socket->get_column_types($tn);
    $structure_html .= "<ul>";
    $all_columns = "";
    $c = 0;
    foreach ($column_names as $cn) {
        if ($is_efa_cloud) {
            $fix_comment = ($fixid_auto_field) ? ((strcasecmp($fixid_auto_field, $cn) == 0) ? " [Schlüssel mit zentraler Korrektur]" : "") : "";
            $key_comment = (strlen($fix_comment) > 0) ? "" : ((strpos($data_keys, $cn . ",") === false) ? "" : " [Feld für Datenschlüssel]");
            $UUIDcomment = (strpos($efa_dataedit->UUID_fields, $cn . ";") === false) ? "" : " [UUID]";
            $UUIDlistComment = (strpos($efa_dataedit->multi_UUID_fields, $cn . ";") === false) ? "" : " [UUID-Liste]";
        } else {
            $key_comment = $data_key[$cn];
        }
        $cn_html = ((strlen($fix_comment) > 0) || (strlen($key_comment) > 0)) ? "<b>" . $cn . "</b>" : $cn;
        $structure_html .= "<li>" . $cn_html . " - " . $column_types[$c]  . $fix_comment . $key_comment . $UUIDcomment . $UUIDlistComment . "</li>";
        $all_columns .= $cn . ",";
        $c++;
    }
    $structure_html .= "</ul>";
    $summary .= $total_table_count . ";permission;" . $cn . ";" . $all_columns . ";" . $cn . ";1;<br>";
}
$structure_html .= "<h5>In Summe " . $total_table_count . " Tabellen mit " . $total_record_count . " Datensätzen</h5>";
$structure_html .= "<p>Für den Tabellenexport zusammengefasst:</p><p>" . $summary . "</p>";

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Die aktuelle Datenstruktur prüfen</h3>
	<p>Hier findest Du die aktuelle Datenstruktur der Server-Datenbank.</p>
</div>

<?php
echo '<div class="w3-container">';
echo $structure_html;
echo '</div>';
end_script();

    