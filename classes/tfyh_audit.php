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

// TODO introduced to avoid a fata error when updating from < 2.3.2_13 to 2.3.2_13ff. in April 2023.
// Remove some day
if (! function_exists("i"))
    include_once "../classes/init_i18n.php";

/**
 * Container to hold the audit class. Shall be run by the cron jobs.
 */
class Tfyh_audit
{

    /**
     * The common toolbox used.
     */
    private $toolbox;

    /**
     * Tfyh_socket to data base.
     */
    private $socket;

    /**
     * The predefined set of publicly accessible directories for the framework. All other except the
     * javascript directory are forbidden. These include the basic set:
     * "_src","helpdocs","api","forms","i18n","js","license","pages","public","resources" and the
     * folders for source-download "src" (dilbo.org and efacloud.org) as well as needed subdomains
     * "demo" (dilbo.org, efacloud.org),"efaCloud" (brg-intern.de),"naerrischegesellen" (fvssp.de),
     */
    private static $tfyh_public_dirs = ["_src","src","demo","efa","efacloud","img","naerrischegesellen",
            "helpdocs","api","forms","i18n","js","license","pages","public","resources"
    ];

    /**
     * The prefix of the javascript files path, which is also publicly accessible.
     */
    private static $tfyh_javascript_dir_prefix = "js_";

    private $audit_log;

    private $audit_warnings;

    /**
     * public Constructor. Constructing the Audit class will rn all standard audit tasks
     *
     * @param Tfyh_toolbox $toolbox
     *            Common toolbox of application
     * @param Tfyh_socket $socket
     *            Common data base socket of application
     */
    public function __construct (Tfyh_toolbox $toolbox, Tfyh_socket $socket)
    {
        $this->toolbox = $toolbox;
        $this->socket = $socket;
    }

    /**
     * Scan the top level path and set the forbidden directories to be those which are not
     * explicitly public. In earlier versions of the tfyh_framework, this looked into
     * subdirectories. now only top level directories are distinguished. So it is only necessary to
     * check whether a directory is in the public set:
     * "api","forms","license","pages","public","resources". The remainder is always forbiden,
     * except "." and "..".
     */
    private function get_forbidden_dirs ()
    {
        
        file_put_contents("../log/tmp", "Scanning\n");
        
        $top_level_dirs = scandir("..");
        $forbidden_dirs = []; // Don't use the framework settings.
        foreach ($top_level_dirs as $top_level_dir) {
            // the javascript application part is in a js_xx directory, xx changes with each
            // version.
            if (is_dir(".." . DIRECTORY_SEPARATOR . $top_level_dir) &&
                    (strcmp($top_level_dir, ".") != 0) && (strcmp($top_level_dir, "..") != 0) && 
                    ! in_array($top_level_dir, self::$tfyh_public_dirs) &&
                    (strpos($top_level_dir, self::$tfyh_javascript_dir_prefix) !== 0)
                ) { 
                         $forbidden_dirs[] = $top_level_dir;
            }
        }
        return $forbidden_dirs;
    }

    /**
     * Scan the top level path and get the public directories to be those which are not public
     * including the javascript directory, but except "." and "..".
     */
    private function get_public_dirs ()
    {
        $top_level_dirs = scandir("..");
        $public_dirs = self::$tfyh_public_dirs; // Don't use the framework settings.
        foreach ($top_level_dirs as $top_level_dir) {
            // the javascript application part is in a js_xx directory, xx changes with each
            // version.
            if (is_dir($top_level_dir) && (strcmp($top_level_dir, ".") != 0) &&
                     (strcmp($top_level_dir, "..") != 0) &&
                     (strpos($top_level_dir, self::$tfyh_javascript_dir_prefix) === 0))
                $public_dirs[] = $top_level_dir;
        }
        return $public_dirs;
    }

    /**
     * Set the access rights for all top level directories and put or remove a .htaccess file
     * accordingly. Directories '.' and '..' will not be touched.
     */
    public function set_dirs_access_rights ()
    {
        // Limit access to forbidden directories
        $forbidden_dirs = $this->get_forbidden_dirs();
        foreach ($forbidden_dirs as $forbidden_dir) {
            if ((strcmp($forbidden_dir, ".") != 0) && (strcmp($forbidden_dir, "..") != 0)) {
                chmod("../" . $forbidden_dir, 0700);
                $htaccess_filename = "../" . $forbidden_dir . "/.htaccess";
                file_put_contents($htaccess_filename, "deny for all");
            }
        }
        // Open access to publicly available directories
        $public_dirs = $this->get_public_dirs();
        foreach ($public_dirs as $public_dir) {
            if ((strcmp($public_dir, ".") != 0) && (strcmp($public_dir, "..") != 0)) {
                chmod("../" . $public_dir, 0755);
                $htaccess_filename = "../" . $public_dir . "/.htaccess";
                if (file_exists($htaccess_filename))
                    unlink($htaccess_filename);
            }
        }
    }

    /**
     * Check the access rights for all top level directories and write the result to the audit log
     * and audit warnings. Directories '.' and '..' will not be checked.
     *
     * @return number count of needed corrections
     */
    public function check_dirs_access_rights ()
    {
        $corrections_needed = 0;
        $forbidden_dirs = $this->get_forbidden_dirs();
        $this->audit_log .= i("0kFZ0X|Forbidden directories ac...") . "\n";
        foreach ($forbidden_dirs as $forbidden_dir) {
            if ((strcmp($forbidden_dir, ".") != 0) && (strcmp($forbidden_dir, "..") != 0)) {
                $forbidden_dir = trim($forbidden_dir); // line breaks in settings_tfyh may cause
                                                       // blank
                                                       // insertion
                $is_valid_dir = (strlen($forbidden_dir) > 0) && file_exists("../" . $forbidden_dir);
                $is_unprotected_dir = (fileperms("../" . $forbidden_dir) != 0700);
                if ($is_valid_dir && $is_unprotected_dir) {
                    $this->audit_log .= "    " . i("5Crr2x|file permissons for") . " " .
                             $forbidden_dir . ": " .
                             self::permissions_string(fileperms("../" . $forbidden_dir)) . ".\n";
                    $corrections_needed ++;
                }
                $htaccess_filename = "../" . $forbidden_dir . "/.htaccess";
                if ($is_valid_dir && ! file_exists($htaccess_filename)) {
                    $corrections_needed ++;
                    $this->audit_warnings .= "    " .
                             i("5W5E6D|Missing %1 file.", $htaccess_filename) . "\n";
                }
            }
        }
        // Open access to publicly available directories
        $this->audit_log .= i("O1HWlA|Publicly available direc...") . "\n";
        $public_dirs = $this->get_public_dirs();
        foreach ($public_dirs as $public_dir) {
            if ((strcmp($public_dir, ".") != 0) && (strcmp($public_dir, "..") != 0)) {
                if ((fileperms("../" . $public_dir) % 0755) != 0) {
                    $this->audit_log .= "    " . i("Vu1bh5|file permissons for") . " " . $public_dir .
                             ": " . self::permissions_string(fileperms("../" . $public_dir)) . ".\n";
                    $corrections_needed ++;
                }
                $htaccess_filename = "../" . $public_dir . "/.htaccess";
                if (file_exists($htaccess_filename)) {
                    $corrections_needed ++;
                    $this->audit_warnings .= "    " .
                             i("t32eUO|Extra °%1° removed.", $htaccess_filename) . "\n";
                }
            }
        }
        return $corrections_needed;
    }

    /**
     * Execute the full audit and log the result to "../log/audit.log"
     */
    public function run_audit ()
    {
        // Header
        $actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
                 "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $this->audit_log = date("Y-m-d H:i:s") . ": Starting audit '" .
                 $this->toolbox->config->app_name . "' at '" . $actual_link . "', version '" .
                 file_get_contents("../public/version") . "'\n";
        
        // Check web server directory access settings
        $this->audit_log .= i("h00O8b|Starting audit at:") . " " . date("Y-m-d H:i:s") . "\n";
        $this->audit_warnings = "";
        
        // Check access to forbidden directories
        $corrections_needed = $this->check_dirs_access_rights();
        if ($corrections_needed > 0)
            $this->set_dirs_access_rights();
        
        // reflect settings for support cases
        $this->audit_log .= i("QmZI3x|Framework configuration ...") . "\n";
        foreach ($this->toolbox->config->settings_tfyh as $module => $settings) {
            $this->audit_log .= $module . ":\n";
            foreach ($this->toolbox->config->settings_tfyh[$module] as $key => $value) {
                if (is_bool($this->toolbox->config->settings_tfyh[$module][$key]) ||
                         is_array($this->toolbox->config->settings_tfyh[$module][$key]))
                    $value = json_encode($value);
                $this->audit_log .= "    " . $key . " = " . $value . "\n";
            }
        }
        
        // Add configuration information for support cases
        $this->audit_log .= i("R7RsS0|Configuration:") . "\n";
        $cfg = $this->toolbox->config->get_cfg();
        foreach ($cfg as $key => $value) {
            if ((strcasecmp($key, "db_up") == 0) || (strcasecmp($key, "db_user") == 0))
                $this->audit_log .= "    " . $key . " = " . mb_strlen($value) . " " .
                         i("DkY0nv|characters long.") . "\n";
            else
                $this->audit_log .= "    " . $key . " = " . json_encode($value) . "\n";
        }
        
        // check table sizes
        $this->audit_log .= i("uwYaTR|Table configuration chec...") . "\n";
        $table_names = $this->socket->get_table_names();
        $table_record_count_list = "";
        $total_record_count = 0;
        $total_columns_count = 0;
        $total_table_count = 0;
        foreach ($table_names as $tn) {
            $record_count = $this->socket->count_records($tn);
            $columns = $this->socket->get_column_names($tn);
            $columns_count = ($columns === false) ? 0 : count($columns);
            $total_record_count += $record_count;
            $total_table_count ++;
            $total_columns_count += $columns_count;
            $history = "";
            if (isset($this->toolbox->config->settings_tfyh["history"][$tn])) {
                $history = ", hist:" . $this->toolbox->config->settings_tfyh["history"][$tn] . "." .
                         $this->toolbox->config->settings_tfyh["maxversions"][$tn];
                if (! in_array($this->toolbox->config->settings_tfyh["history"][$tn], $columns)) {
                    $warning_message = "    " . i("SWDxtq|Missing history column °...", 
                            $this->toolbox->config->settings_tfyh["history"][$tn], $tn) . "\n";
                    $this->audit_log .= $warning_message;
                    $this->audit_warnings = $warning_message;
                }
            }
            $table_record_count_list .= "    " . $tn . " [" . $record_count . "*" . $columns_count .
                     $history . "], \n";
        }
        $table_record_count_list .= i("0gJ1yz|in total [%1*%2] records...", $total_record_count, 
                $total_columns_count, $total_table_count);
        $this->audit_log .= $table_record_count_list . "\n";
        
        // Check users and access rights
        $this->audit_log .= i("eTvuhh|Users and access rights ...") . " ... \n";
        $this->audit_log .= str_replace("Count of", "    Count of", 
                $this->toolbox->users->get_all_accesses($this->socket, true));
        
        // Check backup
        $this->audit_log .= "\n" . i("ulXKGl|Backup check") . "... \n";
        $backup_dir = "../log/backup";
        $backup_files = file_exists($backup_dir) ? scandir($backup_dir) : [];
        $backup_files_size = 0;
        $backup_files_count = 0;
        foreach ($backup_files as $backup_file) {
            if (strcasecmp(substr($backup_file, 0, 1), ".") != 0) {
                $backup_files_size += filesize($backup_dir . "/" . $backup_file);
                $backup_files_count ++;
            }
        }
        $this->audit_log .= "    " .
                 i("VtVRHk|%1 backup files with a t...", strval($backup_files_count), 
                        strval(intval($backup_files_size / 1024 / 102) / 10)) . "\n";
        
        // tfyh legacy case: add application and clients configuration dump for efaCloud
        if (file_exists("../classes/efa_config.php")) {
            include_once "../classes/efa_config.php";
            $efa_config = new Efa_config($this->toolbox);
            $efa_config->parse_client_configs();
            $this->audit_log .= i("jtlDMv|Configuration dump:") . "\n";
            $this->audit_log .= $efa_config->display_array_text($this->toolbox->config->get_cfg(), 
                    "  ");
            // add clients configuration dump
            $this->audit_log .= i("O00zsH|Client Configurations:") . "\n";
            $clients = scandir("../uploads");
            $indent0 = "      ";
            foreach ($clients as $user_id) {
                if (is_numeric($user_id)) {
                    // no i18n in the following block
                    $this->audit_log .= "  userID: " . $user_id . "\n";
                    $efa_config->load_efa_config(intval($user_id));
                    $this->audit_log .= "    project:\n";
                    $this->audit_log .= $efa_config->display_array_text($efa_config->project, 
                            $indent0) . "\n";
                    $this->audit_log .= "    types:\n";
                    $this->audit_log .= $efa_config->display_array_text($efa_config->types, 
                            $indent0) . "\n";
                }
            }
        }
        
        // Finish
        $this->audit_log .= i("0Q3eYq|Audit completed.") . "\n";
        
        file_put_contents("../log/app_audit.log", $this->audit_log);
        if (strlen($this->audit_warnings) > 0)
            file_put_contents("../log/audit.warnings", $this->audit_warnings);
        elseif (file_exists("../log/audit.warnings"))
            unlink("../log/audit.warnings");
    }

    /**
     * Provide a readable String for the file permissions, see:
     * https://www.php.net/manual/de/function.fileperms.php
     *
     * @param int $perms            
     * @return string
     */
    public static function permissions_string (int $perms)
    {
        switch ($perms & 0xF000) {
            case 0xC000: // Tfyh_socket
                $info = 's';
                break;
            case 0xA000: // Symbolischer Link
                $info = 'l';
                break;
            case 0x8000: // Regulär
                $info = 'r';
                break;
            case 0x6000: // Block special
                $info = 'b';
                break;
            case 0x4000: // Directory
                $info = 'd';
                break;
            case 0x2000: // Character special
                $info = 'c';
                break;
            case 0x1000: // FIFO pipe
                $info = 'p';
                break;
            default: // unknown
                $info = 'u';
        }
        
        // Besitzer
        $info .= (($perms & 0x0100) ? 'r' : '-');
        $info .= (($perms & 0x0080) ? 'w' : '-');
        $info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x') : (($perms & 0x0800) ? 'S' : '-'));
        
        // Gruppe
        $info .= (($perms & 0x0020) ? 'r' : '-');
        $info .= (($perms & 0x0010) ? 'w' : '-');
        $info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x') : (($perms & 0x0400) ? 'S' : '-'));
        
        // Other
        $info .= (($perms & 0x0004) ? 'r' : '-');
        $info .= (($perms & 0x0002) ? 'w' : '-');
        $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x') : (($perms & 0x0200) ? 'T' : '-'));
        
        return $info;
    }
}
