<?php

/**
 * class file for logbook reading, auditing and formatting capabilities.
 */
class Efa_logbook
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
     * Efa data edit class to resolve the UUIDs.
     */
    private $efa_dataedit;

    /**
     * The maximum number of recipients to get the personal logbook. This limit is needed to avoid memory
     * overflow.
     */
    private $max_no_recipients_personal_logbook = 1000;

    /**
     * Current logbook, i. e. logbook used for personal logbook extraction.
     */
    private $current_logbook;

    /**
     * public Constructor. Runs the anonymization.
     */
    public function __construct (Tfyh_toolbox $toolbox, Tfyh_socket $socket, Efa_dataedit $efa_dataedit, 
            String $current_logbook = "")
    {
        $this->socket = $socket;
        $this->toolbox = $toolbox;
        $this->efa_dataedit = $efa_dataedit;
        $this->current_logbook = str_replace("JJJJ", date("Y"), $current_logbook);
    }

    /**
     * Sent personal logbooks to all persons which have an Email field set (must contain a '@').
     */
    public function send_logbooks ()
    {
        // find all persons with an Email address provided. Get not more than
        // $max_no_recipients_personal_logbook records to avoid memory problems.
        include_once "../classes/tfyh_list.php";
        $logbook_recipients_list = new Tfyh_list("../config/lists/logbook_management", 6, 
                "Empfänger des persönlichen Logbuchs", $this->socket, $this->toolbox);
        
        // collect Ids and put to associative array. Filter invalid ones.
        $logbook_recipients = [];
        $logbook_recipient_Ids = [];
        foreach ($logbook_recipients_list->get_rows() as $logbook_recipient_row) {
            $logbook_recipient = $logbook_recipients_list->get_named_row($logbook_recipient_row);
            $logbook_recipient_Ids[] = $logbook_recipient["Id"];
            $logbook_recipients[$logbook_recipient["Id"]] = $logbook_recipient;
        }
        
        // create all the personal logbooks
        $personal_logbooks = $this->get_logbook_for($logbook_recipient_Ids);
        
        // send all logbooks.
        include_once '../classes/tfyh_mail_handler.php';
        $cfg = $this->toolbox->config->get_cfg();
        $mail_handler = new Tfyh_mail_handler($cfg);
        $mails_sent = 0;
        
        foreach ($personal_logbooks as $uuid => $personal_logbook) {
            $mailfrom = $mail_handler->system_mail_sender;
            $mailto = $logbook_recipients[$uuid]["Email"];
            $mailsubject = "[" . $cfg["acronym"] . "] persönliches Fahrtenbuch";
            $mailbody = "<html><body><p>Alle Fahrten für " . $logbook_recipients[$uuid]["FirstLastName"] .
                     " im Fahrtenbuch '" . $this->current_logbook . "' bis jetzt:</p>" . $personal_logbook .
                     $cfg["mail_subscript"] . $cfg["mail_footer"];
            $success = $mail_handler->send_mail($mailfrom, $mailfrom, $mailto, "", "", $mailsubject, 
                    $mailbody);
            //if ($success)
                $mails_sent ++;
        }
        
        // return the count of personal logbooks sent.
        return $mails_sent;
    }

    /**
     *
     * @return the start of the sport year which is the current sport year based on the sports_year_start
     *         configuration parameter as time (seconds since 1970).
     */
    private function get_sports_year_start ()
    {
        $cfg = $this->toolbox->config->get_cfg();
        $sports_year_start_month = (intval($cfg["sports_year_start"]) < 10) ? "0" . $cfg["sports_year_start"] : $cfg["sports_year_start"];
        $sports_year_start_this_year = strtotime(date("Y") . "-" . $sports_year_start_month . "-01");
        if ($sports_year_start_this_year < time())
            return $sports_year_start_this_year;
        else
            return strtotime(strval(intval(date("Y")) - 1) . "-" . $sports_year_start_month . "-01");
    }

    /**
     * Get all logbook entries for a set of persons based on his/her UUID
     * 
     * @param array $personUUIDs
     *            UUIDs of persons to get the trips for.
     * @return array for each person an html table with the trips found.
     */
    private function get_logbook_for (array $personUUIDs)
    {
        // get all trips of the current sports year.
        include_once '../classes/tfyh_list.php';
        $sports_year_filter = ["{sports_year_start}" => date("Y-m-d", $this->get_sports_year_start())
        ];
        
        $trips_list = new Tfyh_list("../config/lists/efa", 17, "efa logbook (Mitglieder, aktuelles Jahr)", 
                $this->socket, $this->toolbox, $sports_year_filter);
        $all_trips_raw = $trips_list->get_rows();
        $all_trips = [];
        foreach ($all_trips_raw as $trip_raw)
            $all_trips[] = $trips_list->get_named_row($trip_raw);
        
        $all_logbook_tables = [];
        foreach ($personUUIDs as $personUUID) {
            $person_trips = [];
            foreach ($all_trips as $trip) {
                if (strcmp($trip["CoxId"], $personUUID) == 0)
                    $person_trips[] = $trip;
                else {
                    for ($i = 1; $i <= 24; $i ++) {
                        if (isset($trip["Crew" . $i . "Id"]) &&
                                 (strcmp($trip["Crew" . $i . "Id"], $personUUID) == 0))
                            $person_trips[] = $trip;
                    }
                }
            }
            
            $person_trips_html = "<table><tr><th>Fahrtnummer</th><th>Datum</th><th>Boot</th>" .
                     "<th>Steuermann</th><th>Mannschaft</th><th>Abfahrt</th>" .
                     "<th>Ankunft</th><th>Ziel</th><th>Kilometer</th></tr>\n";
            $total_distance = 0;
            $trip_count = 0;
            foreach ($person_trips as $person_trip) {
                $boatname = (isset($person_trip["BoatId"])) ? $this->efa_dataedit->resolve_UUID(
                        $person_trip["BoatId"])[1] : $person_trip["BoatName"];
                $coxname = (isset($person_trip["CoxId"])) ? $this->efa_dataedit->resolve_UUID(
                        $person_trip["CoxId"])[1] : $person_trip["CoxName"];
                $destinationname = (isset($person_trip["DestinationId"])) ? $this->efa_dataedit->resolve_UUID(
                        $person_trip["DestinationId"])[1] : $person_trip["DestinationName"];
                $distance_km = intval(str_replace(" ", "", str_replace("km", "", $person_trip["Distance"])));
                $total_distance += $distance_km;
                $crew = "";
                for ($i = 1; $i <= 24; $i ++) {
                    $crewname = "";
                    if (isset($person_trip["Crew" . $i . "Id"])) {
                        $crewname = $this->efa_dataedit->resolve_UUID($person_trip["Crew" . $i . "Id"])[1];
                    } elseif (isset($person_trip["Crew" . $i . "Name"]))
                        $crewname .= $person_trip["Crew" . $i . "Name"];
                    if (strlen($crewname) > 2)
                        $crew .= $crewname . ", ";
                }
                if (strlen($crew) >= 2)
                    $crew = substr($crew, 0, strlen($crew) - 2);
                
                $person_trips_html .= "<tr><td>" . $person_trip["EntryId"] . "</td><td>" . $person_trip["Date"] .
                         "</td><td>" . $boatname . "</td><td>" . $coxname . "</td>\n<td>" . $crew .
                         "</td>\n<td>" . $person_trip["StartTime"] . "</td><td>" . $person_trip["EndTime"] .
                         "</td><td>" . $destinationname . "</td><td>" . $distance_km . " km</td></tr>\n";
                $trip_count ++;
            }
            // add the sum only, if there is more than just one trip
            if ($trip_count > 1)
                $person_trips_html .= "<tr><td>gesamt</td><td></td><td>in " . $trip_count .
                         " Fahrten</td><td></td><td></td><td></td><td></td><td></td><td>" . $total_distance .
                         " km</td></tr></table>";
            // only send personal logbooks which have at least a single trip listed.
            if ($trip_count > 0)
                $all_logbook_tables[$personUUID] = $person_trips_html;
        }
        return $all_logbook_tables;
    }
}
    