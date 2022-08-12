<?php
/**
 * The application software upgrade page.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

// Source Code path.
// ====== Depends on the ../config/settings_tfyh file.
$app_src_path = $toolbox->config->settings_tfyh["upgrade"]["src_path"];
if (is_null($$app_src_path))
    $app_src_path = $app_root . "/_src/server.zip";
$app_version_path = $toolbox->config->settings_tfyh["upgrade"]["version_path"];
if (is_null($app_version_path))
    $app_version_path = $app_root . "/_src/version";
$app_remove_files = $toolbox->config->settings_tfyh["upgrade"]["remove_files"];
if (is_null($app_remove_files))
    $app_remove_files = [];
$current_version = (file_exists("../public/version")) ? file_get_contents("../public/version") : "undefined";
$current_version_installed = (file_exists("../public/version")) ? filemtime("../public/version") : 0;
$version_server = (isset($app_version_path) && (strlen($app_version_path) > 0)) ? file_get_contents(
        $app_version_path) : "undefined";

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');
?>

<?php
if (! isset($_GET["upgrade"])) {
    ?>
<h3>Upgrade der <?php echo $toolbox->config->app_name; ?>-Anwendung</h3>
<p>Das Upgrade entpackt den Code und überschreibt dabei die vorhandenen
	Code-Dateien. Alle Bestandsdaten, wie zum Beispiel logs, uploads,
	backups usw. bleiben erhalten. Die Datenbank wird nicht modifiziert.</p>
<?php
    echo "<p>Aktuell ist installiert: <b>" . $current_version . "</b><br>Installationszeitpunkt war: <b>" .
             date("d.m.Y H:i:s", $current_version_installed) . "</b></p>";
    ?>
<p>Ein Upgrade kann nicht rückgängig gemacht werden. Es empfiehlt sich
	daher, vorher ein backup des Codes zu ziehen.</p>
<form action='?upgrade=1' method='post'>
	<input type='submit' class='formbutton'
		value='Jetzt auf - <?php echo $version_server ?> - aktualisieren' />
</form>
<p>Bitte beachten Sie: der Vorgang startet mit dem Klick auf den Knopf
	sofort und dauert nur wenige Sekunden.</p>

<?php
} else {
    
    // ==============================================================================================
    // check loaded modules
    // ==============================================================================================
    $ref_config = ["bz2","calendar","Core","ctype","curl","date","dom","exif","fileinfo","filter","ftp",
            "gd","gettext","hash","iconv","json","libxml","mbstring","mysqli","openssl","pcre","pdo_mysql",
            "PDO","Phar","posix","Reflection","session","SimpleXML","sockets","SPL","standard","tokenizer",
            "xml","xmlreader","xmlwriter","xsl","zip","zlib"
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
    echo "<p >Installierte PHP-Module wurden geprüft.<br>";
    if (count($missing) > 0) {
        echo "Die folgenden Module fehlen auf dem Server im Vergleich zur Referenzinstallation:<br>";
        foreach ($missing as $m)
            echo "'" . $m . "', ";
        echo "Es ist möglich, dass " . $toolbox->config->app_name .
                 " auch ohne diese Module läuft, wurde aber nicht getestet.<br><br>";
    } else
        "Alle Module der Referenzinstallation sind vorhanden.<br><br>";
    
    // ==============================================================================================
    // fetch program source
    // ==============================================================================================
    echo "Lade den Quellcode von: " . $app_src_path . " ...<br>";
    file_put_contents("src.zip", file_get_contents($app_src_path));
    echo " ... abgeschlossen. Dateigröße: " . filesize("src.zip") . ".<br><br>";
    if (filesize("src.zip") < 1000) {
        echo "</p><p>Die Größe des Quellcode-Archivs ist zu klein. Da hat " .
                 "etwas mit dem Download nicht geklappt. Deswegen bricht der Prozess hier ab.</p></body></html>";
        exit();
    }
    
    // read settings, will be used as cache
    echo "Sichere die vorhandene Konfiguration ...<br>";
    $settings_db = file_get_contents("../config/settings_db");
    $settings_app = file_get_contents("../config/settings_app");
    $settings_colors = file_get_contents("../resources/app-colors.txt");
    
    // Unpack source files
    // ==============================================================================================
    echo "Entpacke und kopiere das Quellcode-Archiv ...<br>";
    $zip = new ZipArchive();
    $res = $zip->open('src.zip');
    if ($res === TRUE) {
        $zip->extractTo('..');
        $zip->close();
        chmod("../public/version", 0644);
        chmod("../public/copyright", 0644);
        echo ' ... entferne nicht mehr benötigte Dateien. ... <br>';
        foreach ($app_remove_files as $app_remove_file) {
            echo " --> " . $app_remove_file . "<br>";
            unlink($app_remove_files);
        }
        echo ' ... fertig. ... <br><br>';
    } else {
        echo "</p><p>Das Quellcode-Archiv konnte nicht entpackt werden. Da hat etwas mit dem Download " .
                 "nicht geklappt. Deswegen bricht der Prozess hier ab.</p></p></body></html>";
        exit();
    }
    unlink("src.zip");
    echo "Stelle die vorhandene Konfiguration wieder her ...<br>";
    
    // restore settings of data base connection, app-parameters and colors
    if ($settings_db)
        file_put_contents("../config/settings_db", $settings_db);
    if ($settings_app)
        file_put_contents("../config/settings_app", $settings_app);
    if ($settings_colors)
        file_put_contents("../resources/app-colors.txt", $settings_colors);
    
    // Set directories' access rights.
    // ==============================================================================================
    echo "Setze die Zugriffsberechtigung der angelegten Dateistruktur ...<br>";
    $restricted = ["all_mails_localhost","attachments","classes","config","install","log","pdfs","tcpdf",
            "templates","uploads"
    ];
    $open = ["api","forms","js","labels","pages","public","resources"
    ];
    foreach ($restricted as $dirname) {
        // some directories may not exist, because they do not contain source code.
        if (! file_exists($dirname))
            mkdir($dirname);
        chmod($dirname, 0700);
    }
    foreach ($open as $dirname) {
        // some directories may not exist, because they do not contain source code.
        if (! file_exists($dirname))
            mkdir($dirname);
        chmod($dirname, 0755);
    }
    echo ' ... Durchführung fertig.<br></p>';
    
    // Audit result
    // ==============================================================================================
    include_once "../classes/tfyh_audit.php";
    $audit = new Tfyh_audit($toolbox, $socket);
    $audit->run_audit();
    echo '<h5>Überprüfe das Ergebnis</h5><p>Audit-Protokoll:</p><p>';
    echo str_replace("\n", "<br>", 
            str_replace("<", "&lt;", 
                    str_replace(">", "&gt;", 
                            str_replace("&", "&amp;", file_get_contents("../log/app_audit.log")))));
    echo "<br>Das war's.</p>";
}
end_script();