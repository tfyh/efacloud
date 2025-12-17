<?php
/**
 *
 *       efaCloud
 *       --------
 *       https://www.efacloud.org
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
                $report_date = strtotime($record["ReportDate"]);
                $damage_is_recent = (time() - $report_date) < (14 * 86400); // damages shall only be repoorted, if not older than 14 days.
                $to_be_notified = isset($cfg["notify_damage_to"]) && (strlen($cfg["notify_damage_to"]) > 4) && $damage_is_recent;
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
                    $message = "<p>" .
                             i("NY2pjM|A new boat damage record...", htmlentities(utf8_decode($boat["Name"])), 
                                    htmlentities(utf8_decode($full_name))) . "</p>";
                }
            } elseif (strcasecmp($tablename, "efa2messages") == 0) {
                // prepare a notification message for a damage
                $sent_on = strtotime($record["Date"]);
                $recently_sent = (time() - $sent_on) < (14 * 86400); // messages shall only be repoorted, if not older than 14 days.
                $to_be_notified_admin = isset($cfg["notify_admin_message_to"]) &&
                    (strlen($cfg["notify_admin_message_to"]) > 4) && $recently_modified;
                $is_to_admin = (strcasecmp($record["To"], "ADMIN") == 0);
                $to_be_notified_boatm = isset($cfg["notify_boatm_message_to"]) &&
                         (strlen($cfg["notify_boatm_message_to"]) > 4);
                $is_to_boatm = (strcasecmp($record["To"], "BOATM") == 0);
                if (($to_be_notified_admin && $is_to_admin) || ($to_be_notified_boatm && $is_to_boatm)) {
                    $mailto = ($is_to_admin) ? $cfg["notify_admin_message_to"] : $cfg["notify_boatm_message_to"];
                    $subject = i("5KSMXt|[efa] New message to ADM...", $record["From"]) . " " .
                             $record["Subject"];
                    $message = "<p>" . i("0za9nH|A new message to the adm...", 
                            htmlentities(utf8_decode($record["From"]))) .
                             htmlentities(utf8_decode($record["Subject"])) . ".</p>";
                }
            } elseif (strcasecmp($tablename, "efa2boatreservations") == 0) {
                // prepare a notification message for a boat reservation.
                $last_modified = intval($record["LastModified"] / 1000);
                $recently_modified = (time() - $last_modified) < (14 * 86400); // reservations shall only be repoorted, if not older than 14 days.
                $to_be_notified = isset($cfg["notify_reservation_to"]) &&
                    (strlen($cfg["notify_reservation_to"]) > 4) && $recently_modified;
                if ($to_be_notified) {
                    if (isset($record["BoatId"]))
                        $boat = $this->socket->find_record("efa2boats", "Id", $record["BoatId"]);
                    else
                        $boat = ["Name" => "kein Boot angegeben"
                        ];
                    $mailto = $cfg["notify_reservation_to"];
                    $subject = i("ZjgTv2|[efa] New boat reservati...", $boat["Name"]) . ", " .
                             $record["VirtualReservationDate"];
                    $message = "<p>" . i("ha2Cdj|A new boat reservation f...", $boat["Name"], 
                            $record["VirtualReservationDate"]) . "</p>";
                }
            }
            // if a notification message shall be sent, add the record and send it.
            if (strlen($mailto) > 4) {
                include_once "../classes/tfyh_mail_handler.php";
                $mail_handler = new Tfyh_mail_handler($cfg);
                $message .= "<p>" . i("YdTDBW|The details of the entry...") . "<br>";
                foreach ($record as $key => $value)
                    $message .= $key . ": " . str_replace("\n", "<br>\n", htmlentities($value)) . "<br>";
                $message .= "</p><p>" . i("wOmMPf|The logbook") . "</p>" . $mail_handler->mail_footer;
                $mailto_list = explode(",", $mailto);
                $i = 0;
                foreach ($mailto_list as $mailto_single) {
                    $mail_handler->send_mail($mail_handler->system_mail_sender, 
                            $mail_handler->system_mail_sender, trim($mailto_single), 
                            ($i == 0) ? $mail_handler->mail_schriftwart : "", "", $subject, $message);
                    $i++;
                }
            }
        }
    }
}    
