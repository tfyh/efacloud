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
     * efa uuid resolving utility class.
     */
    private $efa_uuids;

    /**
     * Efa_tables utility class.
     */
    private $efa_config;

    /**
     * an array with all logbook periods known from the respective client configuration.
     */
    private $logbook_periods;

    /**
     * The maximum number of recipients to get the personal logbook. This limit is needed to avoid memory
     * overflow.
     */
    private $max_no_recipients_personal_logbook = 1000;

    /**
     * the array of column names to be included, German version.
     */
    private $pers_logbook_cols;

    /**
     * the array of column names to be included, German version.
     */
    private $pers_logbook_colnames_en;

    /**
     * the array of column names to be included, German version.
     */
    private $table_tags;

    /**
     * public Constructor. Runs the anonymization.
     */
    public function __construct (Tfyh_toolbox $toolbox, Tfyh_socket $socket)
    {
        $this->socket = $socket;
        $this->toolbox = $toolbox;
        include_once "../classes/efa_uuids.php";
        $this->efa_uuids = new Efa_uuids($toolbox, $socket);
        include_once "../classes/efa_config.php";
        $this->efa_config = new Efa_config($toolbox);
        $this->efa_config->load_efa_config();
        
        // special case: the column names of the personal logbook need e reverse translation
        // Create an associative array for this task. Translate the English names:
        $pers_logbook_colnames_en = explode(",", 
                "EntryId,Date,Boat,Cox,Crew,StartTime,EndTime,Destination,Distance");
        // make sure the i18n resource definition matches the column name definitions above
        $personal_logbook_colnames_local = explode(",", 
                i("BqM9Lt|EntryId,Date,Boat,Cox,Cr..."));
        // Than map the local names to the English names.
        $this->pers_logbook_colnames_en = [];
        for ($c = 0; $c < count($personal_logbook_colnames_local); $c ++)
            $this->pers_logbook_colnames_en[$personal_logbook_colnames_local[$c]] = $pers_logbook_colnames_en[$c];
        
        // now prepare the table layout.
        $cfg = $toolbox->config->get_cfg();
        $personal_logbook_columns = (isset($cfg["pers_logbook_cols"]) &&
                 (strlen($cfg["pers_logbook_cols"]) > 0)) ? $cfg["pers_logbook_cols"] : $personal_logbook_colnames_local;
        $this->pers_logbook_cols = explode(",", $personal_logbook_columns);
        
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
        $logbook_recipients_list = new Tfyh_list("../config/lists/logbook_management", 6, "", $this->socket, 
                $this->toolbox);
        
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
                $mailsubject = "[" . $cfg["acronym"] . "] " . i("7UNg40|Personal logbook");
                $mailbody = "<html><body><p>" . i("oR4Ytj|All trips for %1 in logb...", 
                        $logbook_recipients[$uuid]["FirstLastName"], $this->efa_config->current_logbook) .
                         "</p>" . $personal_logbook . $cfg["mail_subscript"] . $cfg["mail_footer"];
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

    public function check_in_period (String $logbookname, String $date)
    {}

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
        
        $trips_list = new Tfyh_list("../config/lists/efaExportTables", 17, "", $this->socket, $this->toolbox, 
                $sports_year_filter);
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
            
            // collect trips: Rows
            foreach ($person_trips as $person_trip) {
                
                // collect data
                $row = [];
                $row["Boat"] = (isset($person_trip["BoatId"]) && (strlen($person_trip["BoatId"]) > 0)) ? $this->efa_uuids->resolve_UUID(
                        $person_trip["BoatId"])[1] : $person_trip["BoatName"];
                $row["Cox"] = (isset($person_trip["CoxId"]) && (strlen($person_trip["CoxId"]) > 0)) ? $this->efa_uuids->resolve_UUID(
                        $person_trip["CoxId"])[1] : $person_trip["CoxName"];
                $row["Destination"] = (isset($person_trip["DestinationId"]) &&
                         (strlen($person_trip["DestinationId"]) > 0)) ? $this->efa_uuids->resolve_UUID(
                                $person_trip["DestinationId"])[1] : $person_trip["DestinationName"];
                $row["Distance"] = intval(
                        str_replace(" ", "", str_replace("km", "", $person_trip["Distance"])));
                $total_distance += $row["Distance"];
                $row["Crew"] = "";
                for ($i = 1; $i <= 24; $i ++) {
                    $crewname = "";
                    if (isset($person_trip["Crew" . $i . "Id"]) &&
                             (strlen($person_trip["Crew" . $i . "Id"]) > 0)) {
                        $crewname = $this->efa_uuids->resolve_UUID($person_trip["Crew" . $i . "Id"])[1];
                    } elseif (isset($person_trip["Crew" . $i . "Name"]))
                        $crewname .= $person_trip["Crew" . $i . "Name"];
                    if (strlen($crewname) > 2)
                        $row["Crew"] .= $crewname . ", ";
                }
                if (strlen($row["Crew"]) >= 2)
                    $row["Crew"] = mb_substr($row["Crew"], 0, mb_strlen($row["Crew"]) - 2);
                $row["EntryId"] = $person_trip["EntryId"];
                $row["Date"] = $person_trip["Date"];
                $row["StartTime"] = $person_trip["StartTime"];
                $row["EndTime"] = $person_trip["EndTime"];
                
                // compile table row.
                $person_trips_html .= $this->table_tags["tr"];
                foreach ($this->pers_logbook_cols as $pers_logbook_col)
                    $person_trips_html .= $this->table_tags["td"] .
                             $row[$this->pers_logbook_colnames_en[$pers_logbook_col]] . "</td>";
                $person_trips_html .= "\n</tr>\n";
                $trip_count ++;
            }
            
            // add the sum only, if there is more than just one trip
            if ($trip_count > 1) {
                $sum = [];
                $sum["EntryId"] = i("Z4dgHJ|Total");
                $sum["Boat"] = $trip_count . " " . i("rckzWH|Trips");
                $sum["Distance"] = $total_distance;
                $person_trips_html .= $this->table_tags["tr"];
                foreach ($this->pers_logbook_cols as $pers_logbook_col)
                    $person_trips_html .= $this->table_tags["td"] .
                             $sum[$this->pers_logbook_colnames_en[$pers_logbook_col]] . "</td>";
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
    
