<?php
/**
 * A page to audit the complete data base.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once "../classes/efa_tables.php";
include_once '../classes/efa_db_layout.php';
include_once "../classes/efa_tools.php";
$efa_tools = new Efa_tools($toolbox, $socket);

// ===== Improve data base status, if requested
$improve = (isset($_GET["do_improve"])) ? $_GET["do_improve"] : "";
$do_improve = (strcmp($improve, "now") == 0);
$improvements = "";

// ==== parse configurations to ensure they are up to date before the audit starts.
include_once "../classes/efa_config.php";
$efa_config = new Efa_config($toolbox);
$efa_config->parse_client_configs();

// maximum number of records which will be added an ecrid, if missing, in one go. Should never be hit.
$max_add_ecrids = 1000;
if ($do_improve) {
    $upgrade_success = $efa_tools->upgrade_efa_tables(true);
    $improvements = ($upgrade_success) ? "<b>Fertig</b><br>" . i("yXukLG|The table layout has bee...") . " " : i(
            "8c5ooi| ** Error ** The table l...") . " ";
    $added_ecrids = $efa_tools->add_ecrids($max_add_ecrids);
    $improvements .= (($added_ecrids > 0) ? i("lT47yu|%1 ecrids were added. (T...", $added_ecrids, 
            $max_add_ecrids) . "<br>" : ".");
    $improvements .= "<br>";
    if ($upgrade_success) {
        $cfg_db = $toolbox->config->get_cfg_db();
        $cfg_db["db_layout_version"] = Efa_db_layout::$db_layout_version_target;
        $cfg_db["db_up"] = Tfyh_toolbox::swap_lchars($cfg_db["db_up"]);
        $cfgStr = serialize($cfg_db);
        $cfgStrBase64 = base64_encode($cfgStr);
        $byte_cnt = file_put_contents("../config/settings_db", $cfgStrBase64);
        $improvements .= i("Isis2Z|The database configurati...", $byte_cnt) . "<br>";
    }
    $improvements .= '<br>';
}
$optimization_needed = false;

// ===== Configuration check
$db_layout_config = "<b>" . i("NrXqXo|Result of the configurat...") . "</b><ul>";
// compare the current version. $efa_tools still remembers the version before improvement
$layout_cfg_is_target = (intval(Efa_db_layout::$db_layout_version_target) ==
         intval($efa_tools->db_layout_version));
if ($layout_cfg_is_target) {
    $db_layout_config .= "<li>" . i("CfkyuH|Ok. Configuration parame...") . " " .
             Efa_db_layout::$db_layout_version_target;
} else {
    $optimization_needed = true;
    $db_layout_config .= "<li>" . i("XJvUh4|NOT OK.") . "</li><li>";
    $db_layout_config .= i("PqTYzs|Deposited in the configu...") . " " . $efa_tools->db_layout_version .
             "</li><li>";
    $db_layout_config .= i("K9R4zK|Default for this program...") . " " .
             Efa_db_layout::$db_layout_version_target;
}
$db_layout_config .= "</li></ul>";

// ===== Size check
// start with the data base size in kB
// ===================================
$audit_result = "<li><b>" . i("SqtY4N|List of tables by size") . "</b></li>\n<ul>";
$table_sizes = $socket->get_table_sizes_kB();
$total_size = 0;
$table_record_count_list = "<b>" . i("NKmdC2|Size check: Tables and r...") . "</b><ul>";
$total_record_count = 0;
$total_table_count = 0;
foreach ($table_sizes as $table_name => $table_size) {
    $record_count = $socket->count_records($table_name);
    $total_record_count += $record_count;
    $total_size += intval($table_size);
    $total_table_count ++;
    $table_record_count_list .= "<li>$table_name: $record_count " . i("xhYaXI|Records") .
             ", $table_size kB]</li>";
}
$table_record_count_list .= "<li>" . i("qHF8o7|in total:") . " $total_record_count " . i("jnzy2g|Records") .
         ", $total_table_count Tabellen, $total_size kB.</li></ul>";

// ===== Layout implementation check
$verification_result = "<b>" . i("e5VOu1|Result of layout check") . "</b><ul><li>";
$db_layout_verified = $efa_tools->update_database_layout(
        $_SESSION["User"][$toolbox->users->user_id_field_name], Efa_db_layout::$db_layout_version_target, true);
if ($db_layout_verified) {
    $verification_result .= i("sk2yd0|Ok. The layout matches t...", Efa_db_layout::$db_layout_version_target);
} else {
    $optimization_needed = true;
    $verification_result .= i("1Pq6VT|NOT OK.") . "</li><li>" . str_replace("\n", "</li><li>", 
            str_replace(i("DQYc1I|Verification failed"), "<b>" . i("P1TqTx|Verification failed") . "</b>", 
                    file_get_contents("../log/sys_db_audit.log")));
}
$verification_result .= "</li></ul>";

// ===== Ecrid filling check
$total_no_ecrids_count = 0;
$no_ecrid_record_count_list = "<b>" . i("YOdyR9|Records without ecrid") .
         "<sup class='eventitem' id='showhelptext_UUIDecrid'>&#9432;</sup>" . "</b><ul>";
foreach ($table_sizes as $tn => $table_size) {
    if (isset($efa_tools->ecrid_at[$tn]) && ($efa_tools->ecrid_at[$tn] == true)) {
        $records_wo_ecrid = $socket->find_records_sorted_matched($tn, ["ecrid" => ""
        ], $max_add_ecrids, "NULL", "", true);
        $no_ecrids_count = ($records_wo_ecrid === false) ? 0 : count($records_wo_ecrid);
        $colnames = $socket->get_column_names($tn);
        if (! in_array("ecrid", $colnames)) {
            $no_ecrids_count = $socket->count_records($tn);
        }
        $total_no_ecrids_count += $no_ecrids_count;
        if ($no_ecrids_count > 0)
            $no_ecrid_record_count_list .= "<li>" . $tn . " [" .
                     (($no_ecrids_count == $max_add_ecrids) ? strval($max_add_ecrids) . "+" : $no_ecrids_count) .
                     "]</li>";
    }
}
if ($total_no_ecrids_count > 0) {
    $optimization_needed = true;
    $no_ecrid_record_count_list .= "<li>" . i("FEabHF|NOT OK") . "</li></ul>";
} else
    $no_ecrid_record_count_list .= "<li>" . i("EAfgop|Ok. All records contain ...") . "</li></ul>";

// ===== data integrity auditing
include_once "../classes/efa_audit.php";
$efa_audit = new Efa_audit($toolbox, $socket);
$period_integrity_result = $efa_audit->period_correctness_audit();
$period_integrity_result = "<b>" . i("ArvB70|Result of the period con...") . "</b><ul>" .
         $period_integrity_result . "</ul>";
$data_integrity_result = $efa_audit->data_integrity_audit(true);
$data_integrity_result_list = "<b>" . i("7Jw6Iu|Result of data integrity...") . "</b><ul>" .
         $data_integrity_result . "</ul>";

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');
echo i("bU8d0n| ** Audit for database %...", $socket->get_db_name());

echo $improvements;
echo $db_layout_config;
echo $verification_result;
echo $no_ecrid_record_count_list;
if ($optimization_needed)
    echo '<p><a href="?do_improve=now"><span class="formbutton">' . i("MQEsnc|Correct now - Wait - tak...") .
             '</span></a><br /><br /></p>';
echo $table_record_count_list;
echo $period_integrity_result;
echo $data_integrity_result_list;

echo i("hPmRBV|</div>");
end_script();
