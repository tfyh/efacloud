<?php
/**
 * snippet to execute after upgrade to this version.
 */
// toolbox und socket must be reinitialzid to reload the settings_tfyh application configuration, which may
// have changed. However, the classes cannot be redeclared - we have to live with the old ones for now.
echo "Initialisierung der neuen Version ... ";
// though the class cannot be redeclared, the objects can be reinstantiated, needed to reload all
// configuration
$toolbox = new Tfyh_toolbox();
$socket = new Tfyh_socket($toolbox);
// ===== Basic data base access checks
echo "Datenbankzugang ...";
$connected = $socket->open_socket();
if ($connected)
    echo "ok ...";
else {
    echo "da klappt was nicht. Abbruch.";
    exit();
}

// ===== restart session for 2.3.3_06+
if (method_exists($toolbox->app_sessions,'web_session_start'))
    $toolbox->app_sessions->web_session_start("init_version.php", $socket);
    
// ===== Basic data base class checks
echo "Tabellen ...";
include_once '../classes/efa_tables.php';
echo "ok ...";
echo "Werkzeuge ...";
include_once '../classes/efa_tools.php';
$efa_tools = new Efa_tools($toolbox, $socket);
echo "ok ...<br>";

// ===== Basic data base layout checks
include_once '../classes/efa_db_layout.php';
$db_audit_needed = "";
$user_id = (isset($toolbox->users->session_user["@id"])) ? $toolbox->users->session_user["@id"] : ((isset(
        $_SESSION["User"])) ? $_SESSION["User"][$toolbox->users->user_id_field_name] : - 1);
if (intval(Efa_db_layout::$db_layout_version_target) !=
         intval($toolbox->config->get_cfg_db()["db_layout_version"]))
    $db_audit_needed .= "In der Konfiguration ist eine falsche Version für das Datenbank-Layout hinterlegt. ";
if (! $efa_tools->update_database_layout($user_id, 
        Efa_db_layout::$db_layout_version_target, true))
    $db_audit_needed .= "Die Auditierung meldet Abweichung in Details des Datenbank-Layouts. ";

// ===== Ecrid filling check
$total_no_ecrids_count = 0;
$table_names = $socket->get_table_names();
foreach ($table_names as $tn) {
    if (isset($efa_tools->ecrid_at[$tn]) && ($efa_tools->ecrid_at[$tn] == true)) {
        $records_wo_ecrid = $socket->find_records_sorted_matched($tn, ["ecrid" => ""
        ], 10, "NULL", "", true);
        $no_ecrids_count = ($records_wo_ecrid === false) ? 0 : count($records_wo_ecrid);
        $colnames = $socket->get_column_names($tn);
        if (! in_array("ecrid", $colnames))
            $no_ecrids_count = $socket->count_records($tn);
        $total_no_ecrids_count += $no_ecrids_count;
    }
}
if ($total_no_ecrids_count > 0)
    $db_audit_needed .= "Es wurden Datensätze ohne ecrid-Wert gefunden. ";

// ===== Reflect upgrade result
echo "<p><b>Vielen Dank für die Aktualisierung!</b><br>";
if (strlen($db_audit_needed) > 0) {
    echo "Bei der Überprüfung der Datenbank wurde festgestellt, dass sie nicht komplett für diese Version vorbereitet ist.<br>";
    echo $db_audit_needed . "<br>";
    echo "<b>Bitte führe jetzt erst ein Datenbank-Audit durch und die dort empfohlenen Korrekturen.</b><br><br>";
    echo "<a href='../pages/db_audit.php'><input type='submit' class='formbutton' value='Audit starten'></a></p>";
} else {
    echo "Die Version " . file_get_contents("../public/version") . " ist nun betriebsbereit.";
    echo "<br>Für efaWeb bitte beachten: Browser-Cache leeren (&lt;Strg&gt; + F5) und einen Wartungslauf manuell oder über Nacht abwarten.";
    echo "<br>Diese Seite nun nicht neu laden, sondern als nächstes:<br><br>";
    echo "<a href='../pages/home.php'><input type='submit' class='formbutton' value='Loslegen'></a></p>";
}