<?php

//TODO introduced to avoid a fata error when updating from < 2.3.2_13 to 2.3.2_13ff. in April 2023. Remove some day
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
     * Execute the full audit and log the result to "../log/audit.log"
     */
    public function run_audit ()
    {
        // Header
        $actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
                 "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $audit_log = "Auditing '" . $this->toolbox->config->app_name . "' at '" . $actual_link . "', version '" .
                 file_get_contents("../public/version") . "'\n";
        
        // Check web server directory access settings
        $audit_log .= i("h00O8b|Starting audit at:") . " " . date("Y-m-d H:i:s") . "\n";
        $forbidden_dirs = explode(",", $this->toolbox->config->settings_tfyh["config"]["forbidden_dirs"]);
        $public_dirs = explode(",", $this->toolbox->config->settings_tfyh["config"]["public_dirs"]);
        $audit_warnings = "";
        
        // Lock access to forbidden directories
        $audit_log .= i("0kFZ0X|Forbidden directories ac...") . "\n";
        $changed = 0;
        foreach ($forbidden_dirs as $forbidden_dir) {
            $forbidden_dir = trim($forbidden_dir); // line breaks in settings_tfyh may cause blank insertion
            if (file_exists("../" . $forbidden_dir) && (fileperms("../" . $forbidden_dir) != 0700)) {
                $audit_log .= "    " . i("5Crr2x|file permissons for") . " " . $forbidden_dir . ": " .
                         $this->permissions_string(fileperms("../" . $forbidden_dir)) . ".\n";
                chmod("../" . $forbidden_dir, 0700);
            }
            $htaccess_filename = "../" . $forbidden_dir . "/.htaccess";
            if (file_exists("../" . $forbidden_dir) && ! file_exists($htaccess_filename)) {
                $changed ++;
                file_put_contents($htaccess_filename, "deny for all");
                $audit_warnings = "    " . i("EAFHgk|Missing %1 added.", $htaccess_filename) . "\n";
            }
        }
        if ($changed == 0)
            $audit_log .= ".htaccess files ok.\n";
        else
            $audit_log .= $changed . " " . i("KcQ7EG|.htaccess files added.") . "\n";
        
        // Open access to publicly available directories
        $audit_log .= i("O1HWlA|Publicly available direc...") . "\n";
        $changed = 0;
        foreach ($public_dirs as $public_dir) {
            if ((fileperms("../" . $public_dir) % 0755) != 0) {
                $audit_log .= "    " . i("Vu1bh5|file permissons for") . " " . $public_dir . ": " .
                         $this->permissions_string(fileperms("../" . $public_dir)) . ".\n";
                chmod("../" . $public_dir, 0755);
            }
            $htaccess_filename = "../" . $public_dir . "/.htaccess";
            if (file_exists($htaccess_filename)) {
                $changed ++;
                unlink($htaccess_filename);
                $audit_warnings = "    " . i("t32eUO|Extra °%1° removed.", $htaccess_filename) . "\n";
            }
        }
        if ($changed == 0)
            $audit_log .= i("89mWdC|.htaccess files ok.") . "\n";
        else
            $audit_log .= $changed . " " . i("H2g0xi|.htaccess files removed.") . "\n";
        
        // reflect settings for support cases
        $audit_log .= i("QmZI3x|Framework configuration ...") . "\n";
        foreach ($this->toolbox->config->settings_tfyh as $module => $settings) {
            $audit_log .= $module . ":\n";
            foreach ($this->toolbox->config->settings_tfyh[$module] as $key => $value) {
                if (is_bool($this->toolbox->config->settings_tfyh[$module][$key]) ||
                         is_array($this->toolbox->config->settings_tfyh[$module][$key]))
                    $value = json_encode($value);
                $audit_log .= "    " . $key . " = " . $value . "\n";
            }
        }
        // Add configuration information for support cases
        $audit_log .= i("R7RsS0|Configuration:") . "\n";
        $cfg = $this->toolbox->config->get_cfg();
        foreach ($cfg as $key => $value) {
            if ((strcasecmp($key, "db_up") == 0) || (strcasecmp($key, "db_user") == 0))
                $audit_log .= "    " . $key . " = " . mb_strlen($value) . " " . i("DkY0nv|characters long.") .
                         "\n";
            else
                $audit_log .= "    " . $key . " = " . json_encode($value) . "\n";
        }
        
        // check table sizes
        $audit_log .= i("uwYaTR|Table configuration chec...") . "\n";
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
                    $audit_log .= $warning_message;
                    $audit_warnings = $warning_message;
                }
            }
            $table_record_count_list .= "    " . $tn . " [" . $record_count . "*" . $columns_count . $history .
                     "], \n";
        }
        $table_record_count_list .= i("0gJ1yz|in total [%1*%2] records...", $total_record_count, 
                $total_columns_count, $total_table_count);
        $audit_log .= $table_record_count_list . "\n";
        
        // Check users and access rights
        $audit_log .= i("eTvuhh|Users and access rights ...") . " ... \n";
        $audit_log .= str_replace("Count of", "    Count of", 
                $this->toolbox->users->get_all_accesses($this->socket, true));
        
        // Check backup
        $audit_log .= "\n" . i("ulXKGl|Backup check") . "... \n";
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
        $audit_log .= "    " . i("VtVRHk|%1 backup files with a t...", strval($backup_files_count), 
                strval(intval($backup_files_size / 1024 / 102) / 10)) . "\n";
        
        // add application and clients configuration dump for efaCloud
        if (file_exists("../classes/efa_config.php")) {
            include_once "../classes/efa_config.php";
            $efa_config = new Efa_config($this->toolbox);
            $efa_config->parse_client_configs();
            $audit_log .= i("jtlDMv|Configuration dump:") . "\n";
            $audit_log .= $efa_config->display_array_text($this->toolbox->config->get_cfg(), "  ");
            // add clients configuration dump
            $audit_log .= i("O00zsH|Client Configurations:") . "\n";
            $clients = scandir("../uploads");
            $indent0 = "      ";
            foreach ($clients as $user_id) {
                if (is_numeric($user_id)) {
                    // no i18n in the following block
                    $audit_log .= "  efaCloudUserID: " . $user_id . "\n";
                    $efa_config->load_efa_config(intval($user_id));
                    $audit_log .= "    project:\n";
                    $audit_log .= $efa_config->display_array_text($efa_config->project, $indent0) . "\n";
                    $audit_log .= "    types:\n";
                    $audit_log .= $efa_config->display_array_text($efa_config->types, $indent0) . "\n";
                }
            }
        }
        
        // Finish
        $audit_log .= i("0Q3eYq|Audit completed.") . "\n";
        
        file_put_contents("../log/app_audit.log", $audit_log);
        if (strlen($audit_warnings) > 0)
            file_put_contents("../log/audit.warnings", $audit_warnings);
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
    private function permissions_string (int $perms)
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
        
        // Todere
        $info .= (($perms & 0x0004) ? 'r' : '-');
        $info .= (($perms & 0x0002) ? 'w' : '-');
        $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x') : (($perms & 0x0200) ? 'T' : '-'));
        
        return $info;
    }
}
