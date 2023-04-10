<?php

/**
 * class file for resolving the UUIDs into names.
 */
class Efa_uuids
{

    /**
     * The data base connection socket. Made public for constructors of efa_api, efa_archive, efa_data,
     * efa_tools, and tx_handler.
     */
    public $socket;

    /**
     * The generic toolbox.
     */
    private $toolbox;

    /**
     * a set of all Names of boats, destinations, persons and waters as associative array with the UUID being
     * the key.
     */
    private $names;

    /**
     * a set of all UUIDs of boats, destinations, persons and waters as associative array with the name (for
     * persons full name = first name + last name) being the key.
     */
    private $uuids;

    /**
     * public Constructor.
     * 
     * @param Tfyh_toolbox $toolbox
     *            application toolbox
     * @param Tfyh_socket $socket
     *            the socket to connect to the database
     */
    public function __construct (Tfyh_toolbox $toolbox, Tfyh_socket $socket)
    {
        $this->socket = $socket;
        $this->toolbox = $toolbox;
        $this->names = [];
        $this->uuids = [];
        include_once "../classes/efa_tables.php";
    }

    /**
     * returns true, if the input Strin is a valid UUID
     */
    public static function isUUID (String $toCheck = null)
    {
        if (is_null($toCheck))
            return false;
        if (strlen($toCheck) != 36)
            return false;
        $parts = explode("-", $toCheck);
        if (count($parts) != 5)
            return false;
        if (strlen($parts[0]) != 8)
            return false;
        if (strlen($parts[1]) != 4)
            return false;
        if (strlen($parts[2]) != 4)
            return false;
        if (strlen($parts[3]) != 4)
            return false;
        return true;
    }

    /**
     * Collect for a single table all UUIDs and reference arrays
     * 
     * @param int $list_id
     *            the ID of the list to use
     * @param String $list_name
     *            the name of the list to use
     * @return int the count of records found.
     */
    private function collect_arrays_per_table (int $list_id, String $list_name)
    {
        $list = new Tfyh_list("../config/lists/efaAuditUUIDnames", $list_id, $list_name, $this->socket, 
                $this->toolbox);
        $records_found = 0;
        $tablename = $list->get_table_name();
        $rows = $list->get_rows();
        $this->names[$tablename] = [];
        $this->uuids[$tablename] = [];
        $id_index = $list->get_field_index("Id");
        $name_index = $list->get_field_index("Name");
        $first_name_index = $list->get_field_index("FirstName");
        $last_name_index = $list->get_field_index("LastName");
        $is_persons = (strpos($tablename, "efa2persons") !== false);
        foreach ($rows as $row) {
            if (strlen($row[$id_index]) > 30) {
                $name = ($is_persons) ? Efa_tables::virtual_full_name($row[$first_name_index], 
                        $row[$last_name_index], $this->toolbox) : $row[$name_index];
                $this->names[$tablename][$row[$id_index]] = $name;
                $this->uuids[$tablename][$name] = $row[$id_index];
                $records_found ++;
            }
        }
        return $records_found;
    }

    /**
     * Collect all UUIDs and names reference arrays
     */
    private function collect_arrays ()
    {
        $this->names = [];
        $this->uuids = [];
        $records_found = 0;
        $records_found += $this->collect_arrays_per_table(1, "name_boats");
        $records_found += $this->collect_arrays_per_table(4, "name_destinations");
        $records_found += $this->collect_arrays_per_table(5, "name_groups");
        $records_found += $this->collect_arrays_per_table(6, "name_persons");
        $records_found += $this->collect_arrays_per_table(9, "name_status");
        $records_found += $this->collect_arrays_per_table(10, "name_waters");
        return $records_found;
    }

    /**
     * Find out to which table belongs the UUID and return the name for it
     * 
     * @param String $UUID            
     * @return array [ tablename, name ];
     */
    public function resolve_UUID (String $UUID)
    {
        if (count($this->names) == 0)
            $this->collect_arrays();
        $tablename = "";
        if (isset($this->names["efa2boats"][$UUID]) && (strlen($this->names["efa2boats"][$UUID]) > 1))
            $tablename = "efa2boats";
        elseif (isset($this->names["efa2destinations"][$UUID]) &&
                 (strlen($this->names["efa2destinations"][$UUID]) > 1))
            $tablename = "efa2destinations";
        elseif (isset($this->names["efa2persons"][$UUID]) && (strlen($this->names["efa2persons"][$UUID]) > 1))
            $tablename = "efa2persons";
        elseif (isset($this->names["efa2status"][$UUID]) && (strlen($this->names["efa2status"][$UUID]) > 1))
            $tablename = "efa2status";
        elseif (isset($this->names["efa2waters"][$UUID]) && (strlen($this->names["efa2waters"][$UUID]) > 1))
            $tablename = "efa2waters";
        if (strlen($tablename) == 0)
            return ["unresolved",$UUID
            ];
        return [$tablename,$this->names[$tablename][$UUID]
        ];
    }

    /**
     * Find the UUID for a name.
     * 
     * @param String $table_name
     *            the name of the table to look in.
     * @param String $name
     *            the name value to look for.
     * @return array [ tablename, name ];
     */
    public function resolve_name (String $table_name, String $name)
    {
        if (count($this->uuids) == 0)
            $this->collect_arrays();
        if (isset($this->uuids[$table_name][$name]) && ($this->isUUID($this->uuids[$table_name][$name])))
            return $this->uuids[$table_name][$name];
        else
            return false;
    }

    /**
     * Replace names to UUIDs for a session record.
     * 
     * @param array $record
     *            the session record for which all fields shal be resolved to UUIDs, if possible.
     * @return array the session record with the UUIDs.
     */
    public function resolve_session_record (array $record)
    {
        if (count($this->uuids) == 0)
            $this->collect_arrays();
        
        // Boat
        if (isset($record["BoatName"]) && (strlen($record["BoatName"]) > 0)) {
            $boat_id = $this->resolve_name("efa2boats", $record["BoatName"]);
            if ($boat_id !== false) {
                unset($record["BoatName"]);
                $record["BoatId"] = $boat_id;
            }
        }
        // Cox
        if (isset($record["CoxName"]) && (strlen($record["CoxName"]) > 0)) {
            $cox_id = $this->resolve_name("efa2persons", $record["CoxName"]);
            if ($cox_id !== false) {
                unset($record["CoxName"]);
                $record["CoxId"] = $cox_id;
            }
        }
        // Crew
        for ($i = 1; $i <= 24; $i ++) {
            $crew_name_field = "Crew" . $i . "Name";
            $crew_id_field = "Crew" . $i . "Id";
            if (isset($record[$crew_name_field]) && (strlen($record[$crew_name_field]) > 0)) {
                $crew_id = $this->resolve_name("efa2persons", $record[$crew_name_field]);
                if ($crew_id !== false) {
                    unset($record[$crew_name_field]);
                    $record[$crew_id_field] = $crew_id;
                }
            }
        }
        // Destination
        if (isset($record["DestinationName"]) && (strlen($record["DestinationName"]) > 0)) {
            $destination_id = $this->resolve_name("efa2destinations", $record["DestinationName"]);
            if ($destination_id !== false) {
                unset($record["DestinationName"]);
                $record["DestinationId"] = $destination_id;
            }
        }
        // Waters
        if (isset($record["WatersNameList"]) && (strlen($record["WatersNameList"]) > 0)) {
            $waters_names = explode(";", $record["WatersNameList"]);
            $waters_ids = "";
            $all_waters_resolved = true;
            foreach ($waters_names as $waters_name) {
                $waters_id = $this->resolve_name("efa2waters", $waters_name);
                if ($waters_id === false)
                    $all_waters_resolved = false;
                else
                    $waters_ids .= $waters_id . ";";
            }
            if ($all_waters_resolved) {
                unset($record["WatersNameList"]);
                $waters_ids = substr($waters_ids, 0, strlen($waters_ids) - 1);
                $record["WatersIdList"] = $waters_ids;
            }
        }
        return $record;
    }
}