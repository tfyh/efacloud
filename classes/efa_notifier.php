<?php

class Efa_notifier
{

    /**
     * The common toolbox.
     */
    private $toolbox;

    /**
     * The data base access socket.
     */
    private $socket;

    /**
     * Construct the Util class. This reads the configuration, initilizes the logger and the navigation menu,
     * asf.
     */
    public function __construct (Tfyh_toolbox $toolbox, Tfyh_socket $socket)
    {
        $this->toolbox = $toolbox;
        $this->socket = $socket;
    }

    /**
     * Notification of an API write transaction: new reservation, new damage, new admin message to a mail
     * account.
     * 
     * @param array $tx            
     */
    public function notify_api_write_event (array $tx)
    {
        $tablename = $tx["tablename"];
        $record = $tx["record"];
        $mailto = "";
        $subject = "";
        $message = "";
        if (strcasecmp($tx["type"], "insert") == 0) {
            $cfg = $this->toolbox->config->get_cfg();
            
            if (strcasecmp($tablename, "efa2boatdamages") == 0) {
                // prepare a notification message for a damage
                $to_be_notified = isset($cfg["notify_damage_to"]) && (strlen($cfg["notify_damage_to"]) > 4);
                $severity_unusable = (strcasecmp($record["Severity"], "NOTUSEABLE") == 0);
                $notify_all_damages = ! isset($cfg["notify_damage_unusable_only"]) ||
                         (strlen($cfg["notify_damage_unusable_only"]) == 0);
                if ($to_be_notified && ($severity_unusable || $notify_all_damages)) {
                    if (isset($record["BoatId"]))
                        $boat = $this->socket->find_record("efa2boats", "Id", $record["BoatId"]);
                    else
                        $boat = ["Name" => i("ZH4LGD|No boat specified")
                        ];
                    $full_name = i("zErjpE|Person could not be foun...");
                    if (isset($record["ReportedByPersonId"])) {
                        $person = $this->socket->find_record("efa2persons", "Id", 
                                $record["ReportedByPersonId"]);
                        if ($person !== false) {
                            include_once '../classes/efa_tables.php';
                            $full_name = Efa_tables::virtual_full_name($person["FirstName"], 
                                    $person["LastName"], $this->toolbox);
                        }
                    }
                    $mailto = $cfg["notify_damage_to"];
                    $subject = i("Q49rLD|[efa] New boat reservati...") . " " . $boat["Name"];
                    $message = "<p>" . i(
                            "NY2pjM|A new boat damage record...", 
                            htmlentities(utf8_decode($boat["Name"])), htmlentities(utf8_decode($full_name))) .
                             "</p>";
                }
            } elseif (strcasecmp($tablename, "efa2messages") == 0) {
                // prepare a notification message for a damage
                $to_be_notified = isset($cfg["notify_admin_message_to"]) &&
                         (strlen($cfg["notify_admin_message_to"]) > 4);
                $is_to_admin = (strcasecmp($record["To"], "ADMIN") == 0);
                if ($to_be_notified && $is_to_admin) {
                    $mailto = $cfg["notify_admin_message_to"];
                    $subject = i("5KSMXt|[efa] New message to ADM...", $record["From"]) . " " .
                             $record["Subject"];
                    $message = "<p>" . i("0za9nH|A new message to the adm...", 
                            htmlentities(utf8_decode($record["From"]))) .
                             htmlentities(utf8_decode($record["Subject"])) . ".</p>";
                }
            } elseif (strcasecmp($tablename, "efa2boatreservations") == 0) {
                // prepare a notification message for a boat reservation.
                $to_be_notified = isset($cfg["notify_reservation_to"]) &&
                         (strlen($cfg["notify_reservation_to"]) > 4);
                if ($to_be_notified) {
                    if (isset($record["BoatId"]))
                        $boat = $this->socket->find_record("efa2boats", "Id", $record["BoatId"]);
                    else
                        $boat = ["Name" => "kein Boot angegeben"
                        ];
                    $mailto = $cfg["notify_reservation_to"];
                    $subject = i("ZjgTv2|[efa] New boat reservati...", $boat["Name"]) . ", " .
                             $record["VirtualReservationDate"];
                    $message = "<p>" . i("ha2Cdj|A new boat reservation f...", 
                            $boat["Name"], $record["VirtualReservationDate"]) . "</p>";
                }
            }
            // if a notification message shall be sent, add the record and send it.
            if (strlen($mailto) > 4) {
                include_once "../classes/tfyh_mail_handler.php";
                $mail_handler = new Tfyh_mail_handler($cfg);
                $message .= "<p>" . i("YdTDBW|The details of the entry...") . "<br>";
                foreach ($record as $key => $value)
                    $message .= $key . ": " . htmlentities(utf8_decode($value)) . "<br>";
                $message .= "</p><p>" . i("wOmMPf|The logbook") . "</p>" . $mail_handler->mail_footer;
                $mail_handler->send_mail($mail_handler->system_mail_sender, $mail_handler->system_mail_sender, 
                        $mailto, $mail_handler->mail_schriftwart, "", $subject, $message);
            }
        }
    }
}    
