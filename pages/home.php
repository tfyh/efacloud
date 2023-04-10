<?php
/**
 * The start of the session after successfull login.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

// ==== check for updates
$versions_string = file_get_contents(
        'https://efacloud.org/src/scanversions.php?own=' .
                 htmlspecialchars(file_get_contents("../public/version")));
$versions = explode("|", $versions_string);
rsort($versions);
$latest_version = $versions[0];
$current_version = (file_exists("../public/version")) ? file_get_contents("../public/version") : "";
if (strcasecmp($latest_version, $current_version) != 0)
    $version_notification = "<b>" . i("p71s5H|Note:") . "</b> " . i("4XUWj8|A more recent program ve...") . " " .
             $latest_version .
             ". <a href='../pages/ec_upgrade.php'>&nbsp;&nbsp;<b>==&gt; ".i("hB8ltL|UPDATE")."</a></b>.";
else
    $version_notification = "Ihr efaCloud Server ist auf dem neuesten Stand.";

$verified_user = $socket->find_record_matched($toolbox->users->user_table_name, 
        [$toolbox->users->user_id_field_name => $_SESSION["User"][$toolbox->users->user_id_field_name]
        ]);
$short_home = (strcasecmp($_SESSION["User"]["Rolle"], "admin") != 0) &&
         (strcasecmp($_SESSION["User"]["Rolle"], "board") != 0);

// if the login is for the boathouse user, force it always to efaWeb. No efaCloud for this role.
if (strcasecmp($verified_user["Rolle"], "bths") == 0) {
    end_script(false);
    header("Location: ../pages/efaWeb.php");
}

// ==== parse configurations
include_once "../classes/efa_config.php";
$efa_config = new Efa_config($toolbox);
$efa_config->parse_client_configs();

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');
echo i("UXT258| ** Welcome, %1 ** ", $verified_user["Vorname"] . " " . $verified_user["Nachname"]);
echo "<p>efaCloudUserID: " . $verified_user[$toolbox->users->user_id_field_name] . ", ";
echo "efaAdmin Name: " . $verified_user["efaAdminName"] . ", ";
echo "e-Mail: " .
         ((isset($verified_user["EMail"]) && (strlen($verified_user["EMail"]) > 0)) ? $verified_user["EMail"] : i(
                "2hvcxV|not provided.")) . ".<br>";
echo $version_notification . ".</p>";
echo i("le6KSU| ** Boats underway ** ");
if (! $short_home) {
    include_once "../classes/efa_config.php";
    $efa_config = new Efa_config($toolbox);
    echo "<h4>" . i("Z4VNs0|Active clients") . "</h4>" .
             $efa_config->get_last_accesses_API($socket, true, true) . "";
    echo "</div>";
} else
    echo i("pARkCd| ** Please use efaWeb to...");
end_script();
