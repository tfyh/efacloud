<?php
/**
 * The page select a table for new records.
 */
// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once "../classes/efa_tables.php";

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
echo i("uVNY6O| ** Create a new record ...");

$forbidden = ["efa2autoincrement" => i("UGlDe1|Further system counters ..."),"efa2boats" => false,
        "efa2boatdamages" => i("xjjbhP|For new damage reports, ..."),
        "efa2boatreservations" => i("MuH95U|For new reservations ple..."),
        "efa2boatstatus" => i("fPOtOW|A new boat status record..."),"efa2clubwork" => false,
        "efa2crews" => false,"efa2destinations" => false,
        "efa2fahrtenabzeichen" => i("0K9Ayf|To add rowing badges, ef..."),"efa2groups" => false,
        "efa2logbook" => i("UgtfgC|For new trips please use..."),
        "efa2messages" => i("Cf48Vk|For new messages please ..."),"efa2persons" => false,
        "efa2sessiongroups" => false,"efa2statistics" => i("P4Mt4D|To add statistics, efa m..."),
        "efa2status" => false,"efa2waters" => false

];

foreach ($forbidden as $tablename => $forbidden) {
    $local_name = Efa_tables::locale_names($toolbox->config->language_code)[$tablename];
    echo "<tr><td>$local_name</td>";
    if ($forbidden)
        echo "<td>$forbidden</td></tr>";
    else
        echo "<td><a href='../forms/datensatz_aendern.php?table=" . $tablename . "&ecrid=new'>" .
                 i("0Bc3sn|New entry") . "</a></div>";
}

echo i("uPD1Dz|</table></div>");
end_script();

    
