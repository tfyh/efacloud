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
if (is_null($app_src_path))
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
if (! isset($_GET["upgrade"])) {
    echo i("HVtA0e| ** Upgrade of the %1 ap...", $toolbox->config->app_name);
    if (strlen($version_server) == 0)
        echo "<h4>" . i("BwU3GN|No server version found....") . "</h4>";
    echo "<p>" . i("ZC5KSz|Currently installed:") . " <b>" . $current_version . "</b><br>" .
             i("b5wSVV|Installed at:") . " <b>" . date($dfmt_dt, $current_version_installed) . "</b></p>";
    
    echo i("nLzqlz| ** An upgrade cannot be...", $version_server);
} else {
    
    // ==============================================================================================
    // check loaded modules
    // ==============================================================================================
    $ref_config = ["bz2","calendar","Core","ctype","curl","date","dom","exif","fileinfo","filter","ftp",
            "gd","gettext","hash","iconv","json","libxml","mbstring","mysqli","openssl","pcre","pdo_mysql",
            "PDO","Phar","posix","Reflection","session","SimpleXML","SPL","standard","tokenizer","xml",
            "xmlreader","xmlwriter","xsl","zip","zlib"
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
    echo "<p>" . i("uz08Wi|Installed PHP modules we...") . "<br>";
    if (count($missing) > 0) {
        echo i("4SVUF8|The following modules ar...") . "<br>";
        foreach ($missing as $m)
            echo "'" . $m . "', ";
        echo i("HxsB1x|It is possible that %1 a...", $toolbox->config->app_name) . "<br><br>";
    } else
        i("2kJu6N|All modules of the refer...") . "<br><br>";
    
    // ==============================================================================================
    // fetch program source
    // ==============================================================================================
    echo "Lade den Quellcode von: " . $app_src_path . " ...<br>";
    file_put_contents("src.zip", file_get_contents($app_src_path));
    echo " ... abgeschlossen. Dateigröße: " . filesize("src.zip") . ".<br><br>";
    if (filesize("src.zip") < 1000) {
        echo "</p><p>" . i("uuZ8U8|The size of the source c...") . "</p></body></html>";
        exit();
    }
    
    // read settings, will be used as cache
    echo i("iM0FxL|Saving the existing conf...") . " ...<br>";
    $settings_db = file_get_contents("../config/settings_db");
    $settings_app = file_get_contents("../config/settings_app");
    $settings_colors = file_get_contents("../resources/app-colors.txt");
    
    // Unpack source files
    // ==============================================================================================
    echo i("GuEiQo|Unpacking and copying th...") . " ...<br>";
    $zip = new ZipArchive();
    $res = $zip->open('src.zip');
    if ($res === TRUE) {
        $zip->extractTo('..');
        $zip->close();
        chmod("../public/version", 0644);
        chmod("../public/copyright", 0644);
        echo " ... " . i("B9Tygk|removing files that are ...") . " ... <br>";
        foreach ($app_remove_files as $app_remove_file) {
            echo " --> " . $app_remove_file . "<br>";
            unlink($app_remove_file);
        }
        echo ' ... ' . i('vp2UUx|ready.') . ' ... <br><br>';
    } else {
        echo "</p><p>" . i("QVV55u|The size of the source c...") . "</p></p></body></html>";
        exit();
    }
    unlink("src.zip");
    echo i("AmEL6s|restoring the existing c...") . " ...<br>";
    
    // restore settings of data base connection, app-parameters and colors
    if ($settings_db)
        file_put_contents("../config/settings_db", $settings_db);
    if ($settings_app)
        file_put_contents("../config/settings_app", $settings_app);
    if ($settings_colors)
        file_put_contents("../resources/app-colors.txt", $settings_colors);
    
    // Set directories' access rights.
    // ==============================================================================================
    echo i("cmOGiF|Setting the access autho...") . " ...<br>";
    $restricted = ["all_mails_localhost","attachments","classes","config","install","log","pdfs","tcpdf",
            "templates","uploads"
    ];
    $open = ["api","forms","js","labels","pages","public","resources"
    ];
    foreach ($restricted as $dirname) {
        // some directories may not exist, because they do not contain source code.
        if (! file_exists("../" . $dirname))
            mkdir("../" . $dirname);
        chmod("../" . $dirname, 0700);
    }
    foreach ($open as $dirname) {
        // some directories may not exist, because they do not contain source code.
        if (! file_exists("../" . $dirname))
            mkdir("../" . $dirname);
        chmod("../" . $dirname, 0755);
    }
    echo ' ... ' . i('O8zMCW|all done.') . '<br></p>';
    
    // Audit result
    // ==============================================================================================
    include_once "../classes/tfyh_audit.php";
    $audit = new Tfyh_audit($toolbox, $socket);
    $audit->run_audit();
    echo "<h5>" . i("RHFSbA|Checking the result") . "</h5><p>" . i("wTWCqf|Audit protocol:") . '</p><p>';
    echo str_replace("\n", "<br>", 
            str_replace("<", "&lt;", 
                    str_replace(">", "&gt;", 
                            str_replace("&", "&amp;", file_get_contents("../log/app_audit.log")))));
    echo "<br>" . i("4plJZf|That was it.") . "</p>";
}
end_script();
