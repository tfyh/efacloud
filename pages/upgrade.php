<?php
/**
 * The public home page.
 *
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');
?>

<!-- Image header -->
<div class="w3-container">
	<img src="../resources/efacloud-logo_512.png" alt="efacloud_logo"
		style="width: 50%">
</div>

<?php
if (! isset($_GET["version"])) {
    
    $versions_string = file_get_contents('https://efacloud.org/src/scanversions.php');
    $versions = explode("|", $versions_string);
    ?>
<h3 style='text-align: center'>Upgrade der efaCloud-Server-Anwendung</h3>
<p style='text-align: center'>Das Upgrade entpackt den Code und
	überschereibt dabei die vorhandenen Code-Dateien. Alle Bestandsdaten,
	wie zum Beispiel logs, uploads, backups usw. bleiben erhalten. Die
	Datenbank wird nicht modifiziert.</p>
<p style='text-align: center'>Ein Upgrade kann nicht rückgängig gemacht
	werden.Es besteht allerdings die Möglichkeit, auf demselben Weg ein
	Downgrade auf alle noch verfügbaren Versionen durchzuführen.</p>
<p style='text-align: center'>     
    <?php
    foreach ($versions as $version)
        if (strlen($version) > 1)
            echo "<a href='?version=" . urlencode($version) . "'><b>" . $version . "</b></a><br />";
    ?>
	<br />
</p>
<p style='text-align: center'>Bitte beachten Sie: der Vorgang startet
	mit dem Klick auf den Link sofort und dauert nur wenige Sekunden.</p>


<?php
} else {
    
    $version_to_install = $_GET["version"];
    // Source Code path.
    // ==============================================================================================
    $efacloud_src_path = "https://efacloud.org/src/" . $version_to_install . "/efacloud_server.zip";
    // ==============================================================================================
    // check loaded modules
    // ==============================================================================================
    $ref_config = [
            "bz2",
            "calendar",
            "Core",
            "ctype",
            "curl",
            "date",
            "dom",
            "exif",
            "fileinfo",
            "filter",
            "ftp",
            "gd",
            "gettext",
            "hash",
            "iconv",
            "json",
            "libxml",
            "mbstring",
            "mysqli",
            "openssl",
            "pcre",
            "pdo_mysql",
            "PDO",
            "Phar",
            "posix",
            "Reflection",
            "session",
            "SimpleXML",
            "sockets",
            "SPL",
            "standard",
            "tokenizer",
            "xml",
            "xmlreader",
            "xmlwriter",
            "xsl",
            "zip",
            "zlib"
    ];
    $this_config = get_loaded_extensions();
    $missing = [];
    foreach ($ref_config as $rcfg) {
        $contained = false;
        foreach ($this_config as $tcfg) {
            $contained = $contained || (strcmp($tcfg, $rcfg) == 0);
        }
        if (! $contained)
            $missing[] = $rcfg;
    }
    echo "<p  style='text-align: center'>Installierte PHP-Module wurden geprüft.<br>";
    if (count($missing) > 0) {
        echo "Die folgenden Module fehlen auf dem Server im Vergleich zur Referenzinstallation:<br>";
        foreach ($missing as $m)
            echo "'" . $m . "', ";
        echo "Es ist möglich, dass efaCloud auch ohne diese Module läuft, wurde aber nicht getestet.<br><br>";
    } else
        "Alle Module der Referenzinstallation sind vorhanden.<br><br>";
    
    // fetch program source
    // ==============================================================================================
    echo "Lade den Quellcode von: " . $efacloud_src_path . " ...<br>";
    file_put_contents("src.zip", file_get_contents($efacloud_src_path));
    echo " ... abgeschlossen. Dateigröße: " . filesize("src.zip") . ".<br><br>";
    if (filesize("src.zip") < 1000) {
        echo "</p><p style='text-align: center'>Die Größe des Quellcode-Archivs ist zu klein. Da hat " .
                 "etwas mit dem Download nicht geklappt. Deswegen bricht der Prozess hier ab.</p></body></html>";
        exit();
    }
    
    // read settings, will be used as cache in case of install
    echo "Sichere die vorhandene Konfiguration ...<br>";
    $settings_db = file_get_contents("../config/settings_db");
    $settings_app = file_get_contents("../config/settings_app");
    
    // Unpack source files
    // ==============================================================================================
    echo "Entpacke und kopiere das Quellcode-Archiv ...<br>";
    $zip = new ZipArchive();
    $res = $zip->open('src.zip');
    if ($res === TRUE) {
        $zip->extractTo('..');
        $zip->close();
        echo "Aktualisiere Versionsangabe ...<br>";
        file_put_contents("../config/version", $version_to_install);
        echo ' ... fertig. ... <br><br>';
    } else {
        echo "</p><p>Das Quellcode-Archiv konnte nicht entpackt werden. Da hat etwas mit dem Download " .
                 "nicht geklappt. Deswegen bricht der Prozess hier ab.</p></p></body></html>";
        exit();
    }
    unlink("src.zip");
    echo "Stelle die vorhandene Konfiguration wieder her ...<br>";
    // restore settings, in case of upgrade
    if ($settings_db)
        file_put_contents("../config/settings_db", $settings_db);
    if ($settings_app)
        file_put_contents("../config/settings_app", $settings_app);
    
    // Set directories' access rights.
    // ==============================================================================================
    echo "Setze die Zugriffsberechtigung der angelegten Dateistruktur ...<br>";
    $restricted = ["classes","config","log","uploads"
    ];
    $open = ["api","forms","js","pages","resources","install"
    ];
    foreach ($restricted as $dirname)
        chmod($dirname, 0700);
    foreach ($open as $dirname)
        chmod($dirname, 0755);
    echo ' ... fertig.<br></p>';
}
?>
<p style='text-align: center'>&nbsp;</p>
<p style='text-align: center'>
	<small>&copy; efacloud - nmichael.de</small>
</p>
<?php
end_script();