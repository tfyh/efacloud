<?php

/**
 * class file for the backup handler class. provides a backup of all data base tabes, yet no schema backup.
 */
include_once '../classes/tfyh_socket.php';

class Tfyh_backup_handler
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
     * Path to save the backup.
     */
    private $backupPath;

    /**
     * Index of the next daily backup.
     */
    private $index_primary;

    /**
     * Index of the next 10-day backup.
     */
    private $index_secondary;

    /**
     * public Constructor.
     * 
     * @param String $logDir
     *            Directory to which the logs are written.
     * @param Tfyh_toolbox $toolbox
     *            Common toolbox of application
     * @param Tfyh_socket $socket
     *            Common data base socket of application
     */
    public function __construct (String $logDir, Tfyh_toolbox $toolbox, Tfyh_socket $socket)
    {
        $this->backupPath = $logDir . "backup/";
        if (! file_exists($this->backupPath))
            mkdir($this->backupPath, 0755);
        
        $this->backupParamsFile = $logDir . "backup/params";
        $params_file = file_get_contents($this->backupParamsFile);
        if ($params_file == false) {
            $this->index_primary = 0;
            $this->index_secondary = 0;
        } else {
            $params = explode("\n", trim($params_file));
            $this->index_primary = $params[0];
            $this->index_secondary = $params[1];
        }
        
        $this->socket = $socket;
        $this->toolbox = $toolbox;
    }

    /**
     * unmask a backup file and produce a zip archive for restore.
     * 
     * @param String $filename            
     * @param String $backup_mask            
     * @return String the backup zip archive or an error message starting with "#Error"
     */
    public function unmask (String $filename, String $backup_mask)
    {
        $backup_masked = file_get_contents($filename);
        if (! $backup_masked)
            return i("AkIopi|#Error: masked backup °%...", $filename);
        $zipbase64_unmasked = $this->toolbox->xor64($backup_masked, $backup_mask);
        $zipbinary = base64_decode($zipbase64_unmasked);
        if ($zipbinary == false)
            return i("Cd30Mz|#Error: decoding unmaske...", $filename);
        else
            return $zipbinary;
    }

    /**
     * Runs a complete backup cycle. Reads all tables and stores them into the current primary backup folder.
     * If it was existing it is first deleted and then recreated. The folder increases from "p0/" to "p9/".
     * When overwriting "p9/" its contents is moved to the current secondary backup folder "s0/" to "s9/".
     * Again this is achieved by overwriting the contents.
     */
    public function backup ()
    {
        // check for configuration of backup mail sending
        $backup_mailbox_parameter = $this->socket->find_record($this->toolbox->config->parameter_table_name, 
                "Name", "Backup_Mailbox");
        if ($backup_mailbox_parameter === false)
            $backup_mailbox = "";
        else
            $backup_mailbox = $backup_mailbox_parameter["Value"];
        $update_secondary = ($this->index_primary == 0);
        $send_backup_mail = ($update_secondary && (strpos($backup_mailbox, "@") !== false));
        if ($send_backup_mail) {
            // create mail handler to backup mailbox. Must be done prior to cwd change.
            require_once '../classes/tfyh_mail_handler.php';
            $mail_handler = new Tfyh_mail_handler($this->toolbox->config->get_cfg());
        }
        
        // preparation. The full function runs in the backup-path as current directory.
        $cwd = getcwd();
        chdir($this->backupPath);
        file_put_contents("logbackup", 
                "[" . date("Y-m-d H:i:s") . "]: " . i("7tTMfJ|Starting backup.") . "\n");
        $primary_zip = "primary_backup_" . $this->index_primary . ".zip";
        $secondary_zip = "secondary_backup_" . $this->index_secondary . ".zip";
        
        // update secondary backup, if required
        if ($update_secondary) {
            // delete current secondary backup and move primary at its place
            unlink($secondary_zip);
            rename($primary_zip, $secondary_zip);
            // increment index. It will be stored at the end.
            $this->index_secondary ++;
            if ($this->index_secondary >= 10)
                $this->index_secondary = 0;
        }
        
        // update current primary backup
        unlink($primary_zip);
        $tablenames = $this->socket->get_table_names();
        foreach ($tablenames as $tablename) {
            $csv = $this->socket->get_table_as_csv($tablename);
            file_put_contents("logbackup", 
                    "[" . date("Y-m-d H:i:s") . "]: " . i("1zKW1z|Exporting.") . " " . $tablename . "\n", 
                    FILE_APPEND);
            file_put_contents($tablename, $csv);
        }
        file_put_contents("logbackup", "[" . date("Y-m-d H:i:s") . "]: " . i("yByDpt|Zipping files.") . "\n", 
                FILE_APPEND);
        $this->toolbox->zip_files($tablenames, $primary_zip);
        file_put_contents("logbackup", "[" . date("Y-m-d H:i:s") . "]: " . i("tpeQge|Removing files.") . "\n", 
                FILE_APPEND);
        foreach ($tablenames as $tablename) {
            unlink($tablename);
        }
        
        // send zip file, if a backup-mailbox is configured
        if ($send_backup_mail && file_exists($primary_zip)) {
            file_put_contents("logbackup", 
                    "[" . date("Y-m-d H:i:s") . "]: " . i("3tUUPL|sending backup per mail.") . "\n", 
                    FILE_APPEND);
            // encode zip file
            $zipbinary = fread(fopen($primary_zip, "r"), filesize($primary_zip));
            $zipbase64 = base64_encode($zipbinary);
            // the encoded data are stored and kept until mail sending is completed for debugging
            // purposes.
            $backup_zip_encoded_name = "backup_p" . $this->index_primary . "_base64.txt";
            file_put_contents($backup_zip_encoded_name, $zipbase64);
            
            // mask zip file
            $backup_mask_parameter = $this->socket->find_record("Parameter", "Name", "Backup_Mask");
            if ($backup_mask_parameter === false)
                $backup_mask = "6vjXEhaXiOAsuJDeOU3u/7qjN9nWPDL1ZjW4DJmQc3AzC7chh/4T1cuRZNhxLSeh";
            else
                $backup_mask = $backup_mask_parameter["Value"];
            $zipbase64_masked = $this->toolbox->xor64($zipbase64, $backup_mask);
            $backup_zip_masked_name = "backup_p" . $this->index_primary . "_masked.txt";
            file_put_contents($backup_zip_masked_name, $zipbase64_masked);
            
            // send mail
            $message = "<p>" . i("x8SrYA|Dear receiver,") . "</p><p>" . i(
                    "2hxIEV|Please find attached the...") . "</p>";
            $message .= "</p>" . $mail_handler->mail_subscript . $mail_handler->mail_footer;
            $mail_was_sent = $mail_handler->send_mail($mail_handler->system_mail_sender, 
                    $mail_handler->system_mail_sender, $backup_mailbox, "", "", 
                    "data backup of your application", $message, $backup_zip_masked_name);
            unlink($backup_zip_encoded_name);
            unlink($backup_zip_masked_name);
        }
        
        // increment primary index and store indices.
        file_put_contents("logbackup", "[" . date("Y-m-d H:i:s") . "]: checking for secondary copy.\n", 
                FILE_APPEND);
        $this->index_primary ++;
        if ($this->index_primary >= 10)
            $this->index_primary = 0;
        file_put_contents("params", $this->index_primary . "\n" . $this->index_secondary);
        
        // change back to original working directory after completion
        file_put_contents("logbackup", "[" . date("Y-m-d H:i:s") . "]: done.\n\n", FILE_APPEND);
        chdir($cwd);
    }
}
