<?php
/**
 * The page to show the data model.
 */
// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
$download_csv = (isset($_GET["download"])) ? intval($_GET["download"]) : 0;

$is_efa_cloud = (strcasecmp($toolbox->config->app_name, "efaCloud") == 0);
if ($is_efa_cloud)
    include_once "../classes/efa_tables.php";

// === APPLICATION LOGIC ==============================================================

$structure_html = "<h4>".i("yxtlEE|data structure")."</h4>";
$total_table_count = 0;
$total_record_count = 0;
$table_names = $socket->get_table_names();
$summary = "";
$csv = ($is_efa_cloud) ? i("xRpQLT|Table name;Table version...") : i("0i10mg|Table name;Column name;D...");
$csv .= "\n";
foreach ($table_names as $tn) {
    $record_count = $socket->count_records($tn);
    $column_names = $socket->get_column_names($tn);
    $column_types = $socket->get_column_types($tn);
    $structure_html .= "<h5>" . $tn . " " .
             i("RNkGtP|(%1 data records with %2...", $record_count, count($column_names)) . "</h5>";
    if ($is_efa_cloud && array_key_exists($tn, Efa_tables::$efa_data_key_fields)) {
        $data_key = Efa_tables::$efa_data_key_fields[$tn];
        $data_keys = "";
        $efa_versionized = ";";
        if ($is_efa_cloud)
            foreach ($data_key as $key_field) {
                if (strcasecmp($key_field, "ValidFrom") == 0) {
                    $structure_html .= "<p>" . i("aCcatt|The table is versionized...") . "</p>";
                    $efa_versionized = "x;";
                }
                $data_keys .= $key_field . ", ";
            }
        $efa_keyfixing_field = ($is_efa_cloud && array_key_exists($tn, Efa_tables::$efa_autoincrement_fields)) ? Efa_tables::$efa_autoincrement_fields[$tn] : false;
        $efa_fix_comment = "";
        $efa_key_comment = "";
        $efa_UUIDcomment = "";
        $efa_UUIDlistComment = "";
        $efa_fix_csv = "";
        $efa_key_csv = "";
        $efa_UUIDcsv = "";
    } else {
        $data_key = $socket->get_indexes($tn, ! $is_efa_cloud);
    }
    $total_record_count += $record_count;
    $total_table_count ++;
    $structure_html .= "<ul>";
    $all_columns = "";
    $c = 0;
    foreach ($column_names as $cn) {
        // efaCloud tables have a lot more structure meanings as legacy.
        if ($is_efa_cloud) {
            $efa_fix_comment = ($efa_keyfixing_field) ? ((strcasecmp($efa_keyfixing_field, $cn) == 0) ? " " .
                     i("pUUKZR|[key field with central ...") : "") : "";
            $efa_fix_csv = ($efa_keyfixing_field) ? ((strcasecmp($efa_keyfixing_field, $cn) == 0) ? "x;" : ";") : ";";
            $efa_key_comment = (strlen($efa_fix_comment) > 0) ? "" : ((strpos($data_keys, $cn . ",") === false) ? "" : " " .
                     i("J33vQj|[field with data key]"));
            $efa_key_csv = ((strlen($efa_fix_comment) > 0) && (strlen($efa_fix_csv) == 0)) ? "" : ((in_array(
                    $cn, $data_key)) ? "x;" : ";");
            $efa_UUIDcomment = (in_array($cn, Efa_tables::$UUID_field_names)) ? " [Objekt ID]" : ((in_array(
                    $cn, Efa_tables::$UUIDlist_field_names)) ? " " . i("m5fazU|[list of object IDs]") : "");
            $efa_UUIDcsv = (in_array($cn, Efa_tables::$UUID_field_names)) ? "1" : ((in_array($cn, 
                    Efa_tables::$UUIDlist_field_names)) ? "n;" : ";");
        } else 
            if (isset($data_key[$cn])) {
                $efa_key_comment = $data_key[$cn];
            }
        $cn_html = ((strlen($efa_fix_comment) > 0) || (strlen($efa_key_comment) > 0)) ? "<b>" . $cn . "</b>" : $cn;
        $structure_html .= "<li>" . $cn_html . " - " . $column_types[$c] . $efa_fix_comment . " " .
                 $efa_key_comment . $efa_UUIDcomment . "</li>";
        $ctp = explode("(", $column_types[$c]);
        $ctype = $ctp[0];
        $csize = ((count($ctp) > 1) && (strlen($ctp[1]) > 0)) ? intval(
                mb_substr($ctp[1], 0, mb_strlen($ctp[1]) - 1)) : 0;
        $csv .= $tn . ";" . $efa_versionized . $cn . ";" . $ctype . ";" . $csize . ";" . $efa_UUIDcsv .
                 $efa_key_csv . $efa_fix_csv . "\n";
        $all_columns .= $cn . ",";
        $c ++;
    }
    $structure_html .= "</ul>";
    if (strlen($all_columns) > 0)
        $all_columns = substr($all_columns, 0, strlen($all_columns) - 1);
    $summary .= $total_table_count . ";permission;" . $tn . ";" . $all_columns . ";" . $tn . ";1;<br>";
}
$structure_html .= "<h5>" . i("Kzbcet|In total %1 tables with ...", $total_table_count, $total_record_count) .
         "</h5>";
// $structure_html .= "<p>Für den Tabellenexport zusammengefasst:</p><p>" . $summary . "</p>";

// return file before page output starts.
if ($download_csv > 0) {
    $toolbox->return_string_as_zip($csv, "database_layout.csv");
}

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
echo i("QyymBR| ** Check the current da...");
echo '<div class="w3-container">';
echo $structure_html;
echo '</div>';
end_script();

    
