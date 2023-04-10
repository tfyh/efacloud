<?php
/**
 * Page display file. Shows all logs of the application.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
$cfg = $toolbox->config->get_cfg();
$reference_client_id = $cfg["reference_client"];
if (strlen($reference_client_id) == 0)
    $reference_client_id = i("r6ReJu|[no reference client def...");

include_once "../classes/efa_config.php";
$efa_config = new Efa_config($toolbox);
$efa_config->parse_client_configs();
$efa_config->load_efa_config();
$type_issues = str_replace("\n", "<br>", $efa_config->compare_client_types());
$shall_be_identical = i("6HRo0G|It is strongly recommend...");
if (strlen($type_issues) == 0)
    $type_issues = i("CoAYmk|No differences in the ty...");
else
    $type_issues .= $shall_be_identical;
$config_issues = str_replace("\n", "<br>", $efa_config->compare_client_configs());
if (strlen($config_issues) == 0)
    $config_issues = i("sL3Wq9|No differences in the re...");
else
    $config_issues .= $shall_be_identical;
$logbooks_html = $efa_config->display_array($efa_config->logbooks);
$clubworkbooks_html = $efa_config->display_array($efa_config->clubworkbooks);
$project_cfg_html = $efa_config->display_array($efa_config->project);
$types_cfg_html = $efa_config->display_array($efa_config->types);
$config_cfg_html = $efa_config->display_array($efa_config->config);

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

echo i("t6vg13| ** Efa-client configur...", $reference_client_id);
echo $efa_config->current_logbook;
echo i("CDDC8L| ** Period:  ** ");
echo date($dfmt_d, $efa_config->logbook_start_time) . " - " . date($dfmt_d, $efa_config->logbook_end_time);
echo i("Cvnhnq| ** Start of the sports ...");
echo $efa_config->sports_year_start;
echo i("nIMVa2| ** Differences in the c...");
echo "<p>" . $type_issues . "</p>";
echo "<p>" . $config_issues . "</p>";
echo i("pHuTRf| ** Logbooks of all clie...");
echo $logbooks_html;
echo i("p2mcau| ** Club workbooks of al...");
echo $clubworkbooks_html;
echo i("MT1L4O| ** Project settings use...");
echo $project_cfg_html;
echo i("ntVguG| ** Type definitions ** ");
echo $types_cfg_html;
echo i("bCVSSd| ** efa client programme...");
echo $config_cfg_html;
echo i("IDOKUa|<!-- END OF Content -->...");
end_script();
