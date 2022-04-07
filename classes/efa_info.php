<?php

/**
 * class file for the specific handling of efainformation which is to be passed to different clients.
 */
class Efa_info
{

    /**
     * sentence to declare no entries.
     */
    private $no_entries_text = "aktuell keine Einträge.";

    /**
     * The data base connection socket.
     */
    private $socket;

    /**
     * The generic toolbox.
     */
    private $toolbox;

    /**
     * public Constructor.
     */
    public function __construct (Tfyh_toolbox $toolbox, Tfyh_socket $socket)
    {
        $this->socket = $socket;
        $this->toolbox = $toolbox;
    }

    /**
     * Remove unused columns of an array of rows. The first row contains the headline.
     * 
     * @param array $table
     *            the table wich shall be transformed
     * @return array the cleansed table
     */
    private function remove_unused_columns (array $table)
    {
        // add a "no entries text" to the table, if empty.
        if (count($table) <= 1)
            $table[] = [$this->no_entries_text
            ];
        
        // check columns used
        $is_used = [];
        foreach ($table[0] as $colname)
            $is_used[] = false;
        $r = 0;
        foreach ($table as $row) {
            for ($c = 0; $c < count($is_used); $c ++)
                if (($r > 0) && isset($row[$c]) && (strlen(strval($row[$c])) > 0))
                    $is_used[$c] = true;
            $r ++;
        }
        // remove unused columns
        for ($c = 0; $c < count($is_used); $c ++)
            if (! $is_used[$c])
                for ($r = 0; $r < count($table); $r ++)
                    unset($table[$r][$c]);
        return $table;
    }

    /**
     * Transform an array of rows into a html table. The first row contains the headline.
     * 
     * @param array $table
     *            the table wich shall be transformed
     * @param int $mode
     *            bit mask with 0x2 = headline on/off, 0x4 = use multiple columns. Use -1 for default
     * @return html-String representing the table
     */
    private function table_to_html (array $table, int $mode)
    {
        $headline_on = (($mode & 0x2) > 0);
        $single_column = (($mode & 0x4) <= 0);
        
        // create the layout
        $html = "<table>";
        if ($headline_on) {
            $html .= "<thead><tr>";
            if ($single_column) {
                $html .= "<th>";
                for ($c = 0; $c < count($table[0]); $c ++)
                    $html .= $table[0][$c] . ", ";
                $html = substr($html, 0, strlen($html) - 2);
                $html .= "</th>";
            } else
                for ($c = 0; $c < count($table[0]); $c ++)
                    $html .= "<th>" . $table[0][$c] . "</th>";
            $html .= "</tr></thead>";
        }
        $html .= "<tbody>";
        for ($r = 1; $r < count($table); $r ++) {
            $html .= "<tr>";
            if ($single_column) {
                $html .= "<td>";
                for ($c = 0; $c < count($table[$r]); $c ++)
                    $html .= $table[$r][$c] . "<br>";
                $html .= "</td>";
            } else
                for ($c = 0; $c < count($table[$r]); $c ++)
                    $html .= "<td>" . $table[$r][$c] . "</td>";
            $html .= "</tr>";
        }
        $html .= "</tbody></table>";
        return $html;
    }

    /**
     * Transform an array of rows into a csv table. The first row contains the headline.
     * 
     * @param array $table
     *            the table wich shall be transformed
     * @param int $mode
     *            bit mask with 0x2 = headline on/off, 0x4 = use multiple columns. Use -1 for default
     * @return html-String representing the table
     */
    private function table_to_csv (array $table, int $mode)
    {
        $headline_on = (($mode & 0x2) > 0);
        $single_column = (($mode & 0x4) <= 0);
        
        // create the layout
        $csv = "";
        if ($headline_on) {
            if ($single_column) {
                for ($c = 0; $c < count($table[0]); $c ++)
                    $csv .= $table[0][$c] . ", ";
                $csv = $this->toolbox->encode_entry_csv($csv);
                $csv = substr($csv, 0, strlen($csv) - 2) . "\n";
            } else {
                for ($c = 0; $c < count($table[0]); $c ++)
                    $csv .= $this->toolbox->encode_entry_csv($table[0][$c]) . ";";
                $csv = substr($csv, 0, strlen($csv) - 1) . "\n";
            }
        }
        for ($r = 1; $r < count($table); $r ++) {
            if ($single_column) {
                $rowtxt = "";
                for ($c = 0; $c < count($table[0]); $c ++)
                    $rowtxt .= $table[$r][$c] . ", ";
                $rowtxt = $this->toolbox->encode_entry_csv($rowtxt);
                $csv .= substr($rowtxt, 0, strlen($rowtxt) - 2) . "\n";
            } else {
                for ($c = 0; $c < count($table[0]); $c ++)
                    $csv .= $this->toolbox->encode_entry_csv($table[$r][$c]) . ";";
                $csv = substr($csv, 0, strlen($csv) - 1) . "\n";
            }
        }
        $csv = substr($csv, 0, strlen($csv) - 1);
        return $csv;
    }

    /**
     * Return an html or csv representation of all boats on the water
     * 
     * @param int $mode
     *            bit mask with 0x1 = html (1)/csv (0), 0x2 = headline on/off, 0x4 = count of columns 4/1. Use
     *            -1 for default
     * @return string the html or csv representation of the table with all boats on the water
     */
    public function get_on_the_water (int $mode)
    {
        $boats_on_the_water = $this->socket->find_records("efa2boatstatus", "CurrentStatus", "ONTHEWATER", 
                100);
        $table = [];
        $table[] = ["Fahrt Nr.","Boot","Startzeit","Ziel"
        ];
        if ($boats_on_the_water !== false) {
            // gather all data into an array
            foreach ($boats_on_the_water as $boat_on_the_water) {
                $trips = $this->socket->find_records("efa2logbook", "EntryId", $boat_on_the_water["EntryNo"], 
                        10);
                $boat = $this->socket->find_record("efa2boats", "Id", $boat_on_the_water["BoatId"]);
                $boatname = (($boat != false) && isset($boat["Name"])) ? $boat["Name"] : "Fremdboot";
                // $trips may contain entries from different logbooks, filter for the current ones.
                foreach ($trips as $trip) {
                    $destination = (isset($trip["DestinationId"])) ? $trip["DestinationId"] : $trip["DestinationName"];
                    if (isset($trip["DestinationId"])) {
                        $destination_record = $this->socket->find_record("efa2destinations", "Id", 
                                $trip["DestinationId"]);
                        $destination = (($destination_record != false) && isset($destination_record["Name"])) ? $destination_record["Name"] : "kein Ziel angegeben";
                    } else
                        $destination = $trip["DestinationName"];
                    if (is_null($destination) || (strlen($destination) == 0))
                        $destination = "-";
                    // filter those of the current logbook by year
                    if (strcmp(date("Y"), substr($trip["Date"], 0, 4)) == 0) {
                        $start_time = (strcmp(date("Y-m-d"), $trip["Date"]) != 0) ? date("d.m.Y", strtotime($trip["Date"])) . " - " .
                                 $trip["StartTime"] : $trip["StartTime"];
                                 $table[] = [strval($trip["EntryId"]),$boatname,$start_time,$destination
                        ];
                    }
                }
            }
        }
        $table = $this->remove_unused_columns($table);
        
        if ($mode == - 1)
            $mode = 3;
        if (($mode & 0x1) > 0)
            return $this->table_to_html($table, $mode);
        else
            return $this->table_to_csv($table, $mode);
    }

    /**
     * Return an html or csv representation of all not available
     * 
     * @param int $mode
     *            bit mask with 0x1 = html (1)/csv (0), 0x2 = headline on/off, 0x4 = count of columns 2/1. Use
     *            -1 for default
     * @return string the html or csv representation of the table with all boats not available
     */
    public function get_not_available (int $mode)
    {
        $boats_currently_not_available = $this->socket->find_records("efa2boatstatus", "CurrentStatus", 
                "NOTAVAILABLE", 100);
        $boats_never_available = $this->socket->find_records("efa2boatstatus", "BaseStatus", "NOTAVAILABLE", 
                100);
        $boats_not_available = array_merge($boats_currently_not_available, $boats_never_available);
        $boats_shown = [];
        $table = [];
        $table[] = ["Boot","Aktueller Status","Grundstatus"
        ];
        foreach ($boats_not_available as $boat_not_available) {
            $boat = $this->socket->find_record("efa2boats", "Id", $boat_not_available["BoatId"]);
            $boatname = (($boat != false) && isset($boat["Name"])) ? $boat["Name"] : "Fremdboot";
            if (! isset($boats_shown[$boatname])) {
                $table[] = [$boatname,$boat_not_available["CurrentStatus"],
                        $boat_not_available["BaseStatus"]
                ];
                $boats_shown[$boatname] = true;
            }
        }
        $table = $this->remove_unused_columns($table);
        
        if ($mode == - 1)
            $mode = 3;
        $table = $this->remove_unused_columns($table);
        if (($mode & 0x1) > 0)
            return $this->table_to_html($table, $mode);
        else
            return $this->table_to_csv($table, $mode);
    }

    /**
     * Return an html or csv representation of all boats reserved in the next 14 days
     * 
     * @param int $mode
     *            bit mask with 0x1 = html (1)/csv (0), 0x2 = headline on/off, 0x4 = count of columns 2/1. Use
     *            -1 for default
     * @return string the html or csv representation of the table with all boats not available
     */
    public function get_reserved (int $mode)
    {
        $boat_reservations = $this->socket->find_records("efa2boatreservations", "", "", 1000);
        $table = [];
        $table[] = ["Boot","Von","Bis"
        ];
        
        $now = time();
        $relevant_period_start = $now - 2 * 24 * 3600;
        $relevant_period_end = $now + 30 * 24 * 3600;
        foreach ($boat_reservations as $boat_reservation) {
            $from = strtotime($boat_reservation["DateFrom"]);
            $until = strtotime($boat_reservation["DateTo"]);
            $to_be_listed = (($relevant_period_start < $from) && ($from < $relevant_period_end)) ||
                     (($relevant_period_start < $until) && ($until < $relevant_period_end));
            if ($to_be_listed) {
                $boat = $this->socket->find_record("efa2boats", "Id", $boat_reservation["BoatId"]);
                $boatname = (($boat != false) && isset($boat["Name"])) ? $boat["Name"] : "Fremdboot";
                $table[] = [$boatname,$boat_reservation["DateFrom"] . " " . $boat_reservation["TimeFrom"],
                        $boat_reservation["DateTo"] . " " . $boat_reservation["TimeTo"]
                ];
            }
        }
        $table = $this->remove_unused_columns($table);
        
        if ($mode == - 1)
            $mode = 3;
        if (($mode & 0x1) > 0)
            return $this->table_to_html($table, $mode);
        else
            return $this->table_to_csv($table, $mode);
    }

    /**
     * Return an html or csv representation of all boats with damages in "NOTUSEABLE" status
     * 
     * @param int $mode
     *            bit mask with 0x1 = html (1)/csv (0), 0x2 = headline on/off, 0x4 = count of columns 2/1. Use
     *            -1 for default
     * @return string the html or csv representation of the table with all boats not usable
     */
    public function get_not_usable (int $mode)
    {
        
        // get all damages which lead to a not usable boat
        $damages_not_usable = $this->socket->find_records("efa2boatdamages", "Severity", "NOTUSEABLE", 100);
        $boats_not_usable = [];
        foreach ($damages_not_usable as $damage_not_usable) {
            // filter those which have already been fixed
            if (strcasecmp($damage_not_usable["Fixed"], "true") !== 0) {
                $boat = $this->socket->find_record("efa2boats", "Id", $damage_not_usable["BoatId"]);
                if (! isset($boats_not_usable[$boat["Name"]]))
                    $boats_not_usable[$boat["Name"]] = [];
                $boats_not_usable[$boat["Name"]][] = $damage_not_usable;
            }
        }
        // list all boats with all damages.
        $table = [];
        $table[] = ["Boot","offener Bootsschaden"
        ];
        foreach ($boats_not_usable as $boatname => $damages) {
            $damages_desc = "";
            foreach ($damages as $damage) {
                if (($mode & 0x1) > 0)
                    $damages_desc .= $damage["Description"] . "<br>";
                else
                    $damages_desc .= $damage["Description"] . " // ";
            }
            $table[] = [$boatname,$damages_desc
            ];
        }
        $table = $this->remove_unused_columns($table);
        
        if (($mode & 0x1) > 0)
            return $this->table_to_html($table, $mode);
        else
            return $this->table_to_csv($table, $mode);
    }

    /**
     * Check whether the info is allowed for the user. Returns true, if so. Else false
     * 
     * @param
     *            String type the type of information requested
     * @param array $client_verified
     *            the verified client which requests the execution
     * @return bool the check result
     */
    public function is_allowed_info (array $client_verified, String $type)
    {
        $cfg = $this->toolbox->config->get_cfg();
        // be aware that hte confg parameter name has a prefix "pblic_" for better configuratuion readability
        $publicly_allowed = isset($cfg[$type]) && (strlen("public_" . $cfg[$type]) > 1);
        if ($publicly_allowed)
            return true;
        $client_not_anonymous = isset($client_verified["Rolle"]) &&
                 (strcasecmp($client_verified["Rolle"], $this->toolbox->users->anonymous_role) != 0);
        return $client_not_anonymous;
    }

    /**
     * Get the information of the requested type as state in the transaction record.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param array $client_record
     *            client record which carries the request for information.
     * @return the requested info or an appropriate error code.
     */
    public function api_info (array $client_verified, array $client_record)
    {
        $tx_resoponse = "502;Info type not recognized: " . json_encode($client_record);
        if (isset($client_record["type"]) && $this->is_allowed_info($client_verified, $client_record["type"])) {
            if (strcasecmp("onthewater", $client_record["type"]) == 0) {
                $tx_resoponse = "300;" . $this->get_on_the_water(intval($client_record["mode"]));
            } elseif (strcasecmp("notavailable", $client_record["type"]) == 0) {
                $tx_resoponse = "300;" . $this->get_not_available(intval($client_record["mode"]));
            } elseif (strcasecmp("notusable", $client_record["type"]) == 0) {
                $tx_resoponse = "300;" . $this->get_not_usable(intval($client_record["mode"]));
            } elseif (strcasecmp("reserved", $client_record["type"]) == 0) {
                $tx_resoponse = "300;" . $this->get_reserved(intval($client_record["mode"]));
            }
        } elseif (isset($client_record["type"]))
            $tx_resoponse = "300;Der Zugang zu dieser Information wurde nicht gestattet.";
        
        return $tx_resoponse;
    }
}
    
