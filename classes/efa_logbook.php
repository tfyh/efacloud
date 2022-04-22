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
     * the array of column names to be included, German version.
     */
    private $pers_logbook_cols;

    /**
     * the array of column names to be included, German version.
     */
    private $table_tags;

    /**
     * public Constructor. Runs the anonymization.
     */
    public function __construct (Tfyh_toolbox $toolbox, Tfyh_socket $socket, Efa_dataedit $efa_dataedit, 
            String $current_logbook = "")
    {
        $this->socket = $socket;
        $this->toolbox = $toolbox;
        $this->efa_dataedit = $efa_dataedit;
        $cfg = $toolbox->config->get_cfg();
        $this->current_logbook = str_replace("JJJJ", date("Y"), $cfg["current_logbook"]);
        $this->pers_logbook_cols = (isset($cfg["pers_logbook_cols"]) && (strlen($cfg["pers_logbook_cols"]) > 0)) ? explode(",", $cfg["pers_logbook_cols"]) : [
                "Fahrtnummer","Datum","Boot","Steuermann","Mannschaft","Abfahrt","Ankunft","Ziel","Kilometer"
        ];
        $this->table_tags = [];
        $this->table_tags["table"] = (isset($cfg["pers_logbook_table"]) &&
                 (strlen($cfg["pers_logbook_table"]) > 0)) ? "<table style=\"" . $cfg["pers_logbook_table"] .
                 "\">" : "<table>";
        $this->table_tags["tr"] = (isset($cfg["pers_logbook_tr"]) && (strlen($cfg["pers_logbook_tr"]) > 0)) ? "<tr style=\"" .
                 $cfg["pers_logbook_tr"] . "\">" : "<tr>";
        $this->table_tags["th"] = (isset($cfg["pers_logbook_th"]) && (strlen($cfg["pers_logbook_th"]) > 0)) ? "<th style=\"" .
                 $cfg["pers_logbook_th"] . "\">" : "<th>";
        $this->table_tags["td"] = (isset($cfg["pers_logbook_td"]) && (strlen($cfg["pers_logbook_td"]) > 0)) ? "<td style=\"" .
                 $cfg["pers_logbook_td"] . "\">" : "<td>";
    }

    /**
     * Sent personal logbooks to all persons which have an Email field set (must contain a '@').
     * 
     * @param bool $only_me
     *            set true to send the logbook only to the session user (test purposes).
     * @return number
     */
    public function send_logbooks (bool $only_me = false)
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
            if (strlen($uuid) > 30) {
                $mailfrom = $mail_handler->system_mail_sender;
                $mailto = $logbook_recipients[$uuid]["Email"];
                $mailsubject = "[" . $cfg["acronym"] . "] persönliches Fahrtenbuch";
                $mailbody = "<html><body><p>Alle Fahrten für " . $logbook_recipients[$uuid]["FirstLastName"] .
                         " im Fahrtenbuch '" . $this->current_logbook . "' bis jetzt:</p>" . $personal_logbook .
                         $cfg["mail_subscript"] . $cfg["mail_footer"];
                if (! $only_me || (strcasecmp($mailto, $_SESSION["User"]["EMail"]) == 0)) {
                    $success = $mail_handler->send_mail($mailfrom, $mailfrom, $mailto, "", "", $mailsubject, 
                            $mailbody);
                    if ($success)
                        $mails_sent ++;
                }
            }
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
        
        $trips_list = new Tfyh_list("../config/lists/efaExportTables", 17, 
                "efa logbook (Mitglieder, aktuelles Jahr)", $this->socket, $this->toolbox, $sports_year_filter);
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
            
            // collect trips: Headline
            $person_trips_html = $this->table_tags["table"] . $this->table_tags["tr"] . "\n";
            foreach ($this->pers_logbook_cols as $pers_logbook_col)
                $person_trips_html .= $this->table_tags["th"] . $pers_logbook_col . "</th>";
            $person_trips_html .= "\n</tr>\n";
            $total_distance = 0;
            $trip_count = 0;
            
            // collect trips: Roows
            foreach ($person_trips as $person_trip) {
                
                // collect data
                $row = [];
                $row["Boot"] = (isset($person_trip["BoatId"])) ? $this->efa_dataedit->resolve_UUID(
                        $person_trip["BoatId"])[1] : $person_trip["BoatName"];
                $row["Steuermann"] = (isset($person_trip["CoxId"])) ? $this->efa_dataedit->resolve_UUID(
                        $person_trip["CoxId"])[1] : $person_trip["CoxName"];
                $row["Ziel"] = (isset($person_trip["DestinationId"])) ? $this->efa_dataedit->resolve_UUID(
                        $person_trip["DestinationId"])[1] : $person_trip["DestinationName"];
                $row["Kilometer"] = intval(
                        str_replace(" ", "", str_replace("km", "", $person_trip["Distance"])));
                $total_distance += $row["Kilometer"];
                $row["Mannschaft"] = "";
                for ($i = 1; $i <= 24; $i ++) {
                    $crewname = "";
                    if (isset($person_trip["Crew" . $i . "Id"])) {
                        $crewname = $this->efa_dataedit->resolve_UUID($person_trip["Crew" . $i . "Id"])[1];
                    } elseif (isset($person_trip["Crew" . $i . "Name"]))
                        $crewname .= $person_trip["Crew" . $i . "Name"];
                    if (strlen($crewname) > 2)
                        $row["Mannschaft"] .= $crewname . ", ";
                }
                if (strlen($row["Mannschaft"]) >= 2)
                    $row["Mannschaft"] = substr($row["Mannschaft"], 0, strlen($row["Mannschaft"]) - 2);
                $row["Fahrtnummer"] = $person_trip["EntryId"];
                $row["Datum"] = $person_trip["Date"];
                $row["Abfahrt"] = $person_trip["StartTime"];
                $row["Ankunft"] = $person_trip["EndTime"];
                
                // compile table row.
                $person_trips_html .= $this->table_tags["tr"];
                foreach ($this->pers_logbook_cols as $pers_logbook_col)
                    $person_trips_html .= $this->table_tags["td"] . $row[$pers_logbook_col] . "</td>";
                $person_trips_html .= "\n</tr>\n";
                $trip_count ++;
            }
            
            // add the sum only, if there is more than just one trip
            if ($trip_count > 1) {
                $sum = [];
                $sum["Fahrtnummer"] = "gesamt";
                $sum["Boot"] = $trip_count . " Fahrten";
                $sum["Kilometer"] = $total_distance;
                $person_trips_html .= $this->table_tags["tr"];
                foreach ($this->pers_logbook_cols as $pers_logbook_col)
                    $person_trips_html .= $this->table_tags["td"] . $sum[$pers_logbook_col] . "</td>";
                $person_trips_html .= "</tr>\n";
            }
            $person_trips_html .= "</table>\n";
            
            // only send personal logbooks which have at least a single trip listed.
            if ($trip_count > 0)
                $all_logbook_tables[$personUUID] = $person_trips_html;
        }
        return $all_logbook_tables;
    }
}
    