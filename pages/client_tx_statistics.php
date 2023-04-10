<?php
/**
 * An overview on the transactions with the server.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once "../classes/client_tx_statistics.php";
$client_id = (isset($_GET["clientID"])) ? intval($_GET["clientID"]) : 0; // identify client for statistics to
                                                                         // show
if (! $client_id) {
    $toolbox->display_error(i("uBlPNN|Not allowed."), 
            i("5bm14Z|Page °%1° must be called...", $user_requested_file), $user_requested_file);
}

include_once "../classes/tfyh_statistics.php";
$statistics = new Tfyh_statistics();
$statistics->pivot_timestamps(86400, 14);
$stats_html = $statistics->pivot_user_timestamps_html($client_id);

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

echo i("jToBj0| ** Access statistics fo...", $client_id, 
        date($dfmt_dt, $statistics->timestamps_last[$client_id]), $stats_html);
end_script();
