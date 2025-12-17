<?php
/**
 *
 *       the tools-for-your-hobby framework
 *       ----------------------------------
 *       https://www.tfyh.org
 *
 * Copyright  2018-2024  Martin Glade
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License. 
 */

/**
 * The application software upgrade page.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once '../classes/tfyh_audit.php';

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
    echo "<h3>" . i("w2PJel|Upgrade of the %1 applic...", $toolbox->config->app_name) . "</h3>";
    echo "<p>" . i("YYlWr8|The upgrade unpacks the ...") . "</p>";
    if (strlen($version_server) == 0)
        echo "<h4>" . i("BwU3GN|No server version found....") . "</h4>";
    echo "<p>" . i("ZC5KSz|Currently installed:") . " <b>" . $current_version . "</b><br>" .
             i("b5wSVV|Installed at:") . " <b>" . date($dfmt_dt, $current_version_installed) . "</b></p>";
    echo "<p>" . i("9dBgWW|An upgrade cannot be und...") . "</p>";
    echo "<p>" . i("d0xnMU|Please note: the process...") . "</p>";
    echo "<form action='?upgrade=1' method='post'>\n <input type='submit' class='formbutton' value='" .
             i("M4MjRw|Update to version - %1", $version_server) . "' /> </form>";
} else {
    
    // ==============================================================================================
    // register upgrade
    // ==============================================================================================
    // see https://stackoverflow.com/questions/5647461/how-do-i-send-a-post-request-with-php
    $app_url = $toolbox->config->app_url;
    if (strlen($app_url) > 0) {
        echo "<p>" . i("YNja1L|Your update to °%1° will...", $version_server, $app_root, 
                (new DateTime())->format("Y-m-d H:i:a")) . "'<br>";
        $url = $app_url . '/registration.php';
        $data = array('version' => $version_server,'server' => $app_root
        );
        // use key 'http' even if you send the request to https://...
        $options = array(
                'http' => array('header' => "Content-type: application/x-www-form-urlencoded\r\n",
                        'method' => 'POST','content' => http_build_query($data)
                )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        if ($result)
            echo " - successful registered to $url.</p>";
        else 
            echo " - failed to register to $url.</p>";
    } else
        echo "<p>" . i("oelARg|no URL to register upgra...") . "</p>";
    
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
    echo "<p>" . i("kfB6Rx|Installed PHP modules ch...");
    if (count($missing) > 0) {
        echo "<br>" . i("4SVUF8|The following modules ar...") . "<br>";
        foreach ($missing as $m)
            echo "'" . $m . "', ";
        echo i("HxsB1x|It is possible that %1 a...", $toolbox->config->app_name) . "</p>";
    } else
        i("Zg9SKd|ok") . "</p>";
    
    // ==============================================================================================
    // fetch program source
    // ==============================================================================================
    echo "<p>" . i("Bwa5WS|Loading the source code ...") . ": " . $app_src_path . " ...<br>";
    file_put_contents("src.zip", file_get_contents($app_src_path));
    echo " ... " . i("HAqb4l|completed. File size") . ": " . filesize("src.zip") . ".</p>";
    if (filesize("src.zip") < 1000) {
        echo "<p>" . i("uuZ8U8|The size of the source c...") . "</p></body></html>";
        exit(); // really exit. No test case left over.
    }
    
    // read settings, will be used as cache
    $mode_classic = file_exists("../config/settings_db");
    $settings_colors = file_get_contents("../resources/app-colors.txt");
    echo "<p>" . i("iM0FxL|Saving the existing conf...") . " ...<br>";
    if ($mode_classic) {
        $settings_db = file_get_contents("../config/settings_db");
        $settings_app = file_get_contents("../config/settings_app");
    } else {
        $cached_settings_files = ["appSettings" => false,"dbSettings" => false,"clubSettings" => false,
                "uiSettings" => false
        ];
        $settings_dir = "../config/settings";
        foreach ($cached_settings_files as $filename => $contents) {
            echo $filename . ": ";
            if (file_exists("$settings_dir/$filename")) {
                $cached_settings_files[$filename] = file_get_contents($settings_dir . "/" . $filename);
                echo strlen($cached_settings_files[$filename]) . "bytes, ";
            } else {
                echo "-, ";
            }
        }
    }
    echo "</p>";
    
    // Delete server side files.
    // ==============================================================================================
    echo "<p>" . i("rve23X|Deleting old code") . " ...<br>";

    function rrmdir ($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && ! is_link($dir . "/" . $object))
                        rrmdir($dir . DIRECTORY_SEPARATOR . $object);
                    else
                        unlink($dir . DIRECTORY_SEPARATOR . $object);
                }
            }
            rmdir($dir);
        }
    }
    // delete js_ branches
    $topleveldirs = scandir("..");
    foreach ($topleveldirs as $topleveldir) {
        if (strcmp(substr($topleveldir, 0, 3), "js_") == 0) {
            echo $topleveldir . " ... ";
            rrmdir($topleveldir);
        }
    }
    // delete other code
    $dirs_to_delete = ["api","classes","config","forms","install","log","pages","public","resources"
    ];
    foreach ($dirs_to_delete as $dir_to_delete) {
        if (is_dir("../$dir_to_delete")) {
            echo $dir_to_delete . " ... ";
            rrmdir($dir_to_delete);
        }
    }
    echo "<br>" . i("ag2Cwk|Done") . "</p>";
    
    // Unpack source files
    // ==============================================================================================
    echo "<p>" . i("GuEiQo|Unpacking and copying th...") . " ...<br>";
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
        exit(); // really exit. No test case left over.
    }
    unlink("src.zip");
    echo i("AmEL6s|restoring the existing c...") . " ...<br>";
    
    // restore settings of data base connection, app-parameters and colors
    if ($mode_classic) {
        if ($settings_db)
            file_put_contents("../config/settings_db", $settings_db);
        if ($settings_app)
            file_put_contents("../config/settings_app", $settings_app);
    } else {
        foreach ($cached_settings_files as $filename => $contents) {
            if ($contents !== false) {
                file_put_contents($settings_dir . "/" . $filename, $contents);
                echo $filename . " ... ";
            } else {
                echo " (not " . $filename . ") ... ";
            }
        }
    }
    // restore settings of colors
    if ($settings_colors)
        file_put_contents("../resources/app-colors.txt", $settings_colors);
    echo "</p>";
    
    // Set directory access rights and audit the upgrade result.
    // ==============================================================================================
    include_once "../classes/tfyh_audit.php";
    $audit = new Tfyh_audit($toolbox, $socket);
    $audit->set_dirs_access_rights();
    $audit->run_audit();
    echo "<h5>" . i("RHFSbA|Checking the result") . "</h5><p>" . i("RGjcvy|Done. For the audit prot...") .
             '</p>';
    // update the installation timestamp, which is the filemtime of "../public/version"
    file_put_contents("../public/version", file_get_contents("../public/version"));
    
    // ==============================================================================================
    // initialize the version, if the respective script is available.
    // ==============================================================================================
    if (file_exists("../classes/init_version.php"))
        include "../classes/init_version.php";
}
end_script();
