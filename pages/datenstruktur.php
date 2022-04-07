<?php
/**
 * The page to show the data model.
 */
// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
$download_csv = (isset($_GET["download"])) ? intval($_GET["download"]) : 0;

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
$csv .= ($is_efa_cloud) ? "Tabellenname;Tabelle versioniert;Spaltenname;" .
         "Datentyp;Datenlänge;UUID;Schlüssel;Schlüsselkorrektur\n" : "Tabellenname;Spaltenname;Datentyp;Datenlänge\n";
foreach ($table_names as $tn) {
    $record_count = $socket->count_records($tn);
    $structure_html .= "<h5>" . $tn . " (" . $record_count . " Datensätze)</h5>";
    $data_key = ($is_efa_cloud) ? Efa_tables::$key_fields[$tn] : $socket->get_indexes($tn, ! $is_efa_cloud);
    $data_keys = "";
    $efa_versionized = ";";
    if ($is_efa_cloud)
        foreach ($data_key as $key_field) {
            if (strcasecmp($key_field, "ValidFrom") == 0) {
                $structure_html .= "<p>Die Tabelle ist versioniert.</p>";
                $efa_versionized = "x;";
            }
            $data_keys .= $key_field . ", ";
        }
    $efa_fixid_auto_field = ($is_efa_cloud && isset($efa_tables->fixid_auto_field[$tn])) ? $efa_tables->fixid_auto_field[$tn] : false;
    $efa_fix_comment = "";
    $efa_key_comment = "";
    $efa_UUIDcomment = "";
    $efa_UUIDlistComment = "";
    $efa_fix_csv = "";
    $efa_key_csv = "";
    $efa_UUIDcsv = "";
    $total_record_count += $record_count;
    $total_table_count ++;
    $column_names = $socket->get_column_names($tn);
    $column_types = $socket->get_column_types($tn);
    $structure_html .= "<ul>";
    $all_columns = "";
    $c = 0;
    foreach ($column_names as $cn) {
        // efaCloud tables have a lot more structure meanings as legacy.
        if ($is_efa_cloud) {
            $efa_fix_comment = ($efa_fixid_auto_field) ? ((strcasecmp($efa_fixid_auto_field, $cn) == 0) ? " [Schlüssel mit zentraler Korrektur]" : "") : "";
            $efa_fix_csv = ($efa_fixid_auto_field) ? ((strcasecmp($efa_fixid_auto_field, $cn) == 0) ? "x;" : ";") : ";";
            $efa_key_comment = (strlen($efa_fix_comment) > 0) ? "" : ((strpos($data_keys, $cn . ",") === false) ? "" : " [Feld für Datenschlüssel]");
            $efa_key_csv = ((strlen($efa_fix_comment) > 0) && (strlen($efa_fix_csv) == 0)) ? "" : ((strpos($data_keys, 
                    $cn . ",") === false) ? ";" : "x;");
            $efa_UUIDcomment = (strpos($efa_dataedit->UUID_fields, $cn . ";") === false) ? "" : " [UUID]";
            $efa_UUIDlistComment = (strpos($efa_dataedit->multi_UUID_fields, $cn . ";") === false) ? "" : " [UUID-Liste]";
            $efa_UUIDcsv = (strpos($efa_dataedit->UUID_fields, $cn . ";") === false) ? ((strpos(
                    $efa_dataedit->multi_UUID_fields, $cn . ";") === false) ? ";" : "n;") : "1;";
        } else if (isset($data_key[$cn])) {
            $efa_key_comment = $data_key[$cn];
        }
        $cn_html = ((strlen($efa_fix_comment) > 0) || (strlen($efa_key_comment) > 0)) ? "<b>" . $cn . "</b>" : $cn;
        $structure_html .= "<li>" . $cn_html . " - " . $column_types[$c] . $efa_fix_comment . " " . $efa_key_comment .
                 $efa_UUIDcomment . $efa_UUIDlistComment . "</li>";
        $ctp = explode("(", $column_types[$c]);
        $ctype = $ctp[0];
        $csize = ((count($ctp) > 1) && (strlen($ctp[1]) > 0)) ? intval(
                substr($ctp[1], 0, strlen($ctp[1]) - 1)) : 0;
                $csv .= $tn . ";" . $efa_versionized . $cn . ";" . $ctype . ";" . $csize . ";" . $efa_UUIDcsv . $efa_key_csv .
                 $efa_fix_csv . "\n";
        $all_columns .= $cn . ",";
        $c ++;
    }
    $structure_html .= "</ul>";
    if (strlen($all_columns) > 0)
        $all_columns = substr($all_columns, 0, strlen($all_columns) - 1);
    $summary .= $total_table_count . ";permission;" . $tn . ";" . $all_columns . ";" . $tn . ";1;<br>";
}
$structure_html .= "<h5>In Summe " . $total_table_count . " Tabellen mit " . $total_record_count .
         " Datensätzen</h5>";
$structure_html .= "<p>Für den Tabellenexport zusammengefasst:</p><p>" . $summary . "</p>";

// return file before page output starts.
if ($download_csv > 0) {
    $toolbox->return_string_as_zip($csv, "Datenstruktur.csv");
}

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

    