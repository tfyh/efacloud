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
     * Special purpose collection of membership numbers instead of names per person UUID for logbook
     * generation.
     */
    public $membership_numbers;

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
        $this->membership_numbers = [];
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
        if (! isset($this->names[$tablename]))
            $this->names[$tablename] = [];
        if (! isset($this->uuids[$tablename]))
            $this->uuids[$tablename] = [];
        $id_index = $list->get_field_index("Id");
        $invfrom_index = $list->get_field_index("InvalidFrom");
        $name_index = $list->get_field_index("Name");
        $first_name_index = $list->get_field_index("FirstName");
        $last_name_index = $list->get_field_index("LastName");
        $is_persons = (strpos($tablename, "efa2persons") !== false);
        $membership_number_index = ($is_persons) ? $list->get_field_index("MembershipNo") : false;
        if ($list_id <= 10) {
            // the first 10 lists contain only the last valid record for versionized tables
            foreach ($rows as $row) {
                if (isset($row[$id_index]) && (strlen($row[$id_index]) > 30)) {
                    $name = ($is_persons) ? Efa_tables::virtual_full_name($row[$first_name_index], 
                            $row[$last_name_index], $this->toolbox) : $row[$name_index];
                    // this index provides a name for a UUID
                    $this->names[$tablename][$row[$id_index]] = $name;
                    // this index provides a UUID for a name
                    $this->uuids[$tablename][$name] = $row[$id_index];
                    $records_found ++;
                }
            }
        } else {
            // lists 11 .. 14 contain all records for versionized tables.
            foreach ($rows as $row) {
                if (isset($row[$id_index]) && (strlen($row[$id_index]) > 30) && isset($row[$invfrom_index]) &&
                         (strlen($row[$invfrom_index]) > 8)) {
                    $name = ($is_persons) ? Efa_tables::virtual_full_name($row[$first_name_index], 
                            $row[$last_name_index], $this->toolbox) : $row[$name_index];
                    // add indices to the one existing by using an not existing Id "@V"
                    // to provide a name for a UUID at a given time
                    if (! isset($this->names[$tablename]["@V"]))
                        $this->names[$tablename]["@V"] = [];
                    if (! isset($this->names[$tablename]["@V"][$row[$id_index]]))
                        $this->names[$tablename]["@V"][$row[$id_index]] = [];
                    $this->names[$tablename]["@V"][$row[$id_index]][$row[$invfrom_index]] = $name;
                    // same with Ids
                    // to provide a UUID for a name at a given time
                    if (! isset($this->uuids[$tablename]["@V"]))
                        $this->uuids[$tablename]["@V"] = [];
                    if (! isset($this->uuids[$tablename]["@V"][$name]))
                        $this->uuids[$tablename]["@V"][$name] = [];
                    $this->uuids[$tablename]["@V"][$name][$row[$invfrom_index]] = $row[$id_index];
                    // special case: resolve Id to mebership number for logbook export
                    if ($is_persons) {
                        $membership_number_index = $list->get_field_index("MembershipNo");
                        $this->membership_numbers[$row[$id_index]] = $row[$membership_number_index];
                    }
                    $records_found ++;
                }
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
        // lists 1 .. 10
        $records_found += $this->collect_arrays_per_table(1, "name_boats");
        $records_found += $this->collect_arrays_per_table(4, "name_destinations");
        $records_found += $this->collect_arrays_per_table(5, "name_groups");
        $records_found += $this->collect_arrays_per_table(6, "name_persons");
        $records_found += $this->collect_arrays_per_table(9, "name_status");
        $records_found += $this->collect_arrays_per_table(10, "name_waters");
        // lists 11 .. 14
        $records_found += $this->collect_arrays_per_table(11, "Vname_boats");
        $records_found += $this->collect_arrays_per_table(12, "Vname_destinations");
        $records_found += $this->collect_arrays_per_table(13, "Vname_groups");
        $records_found += $this->collect_arrays_per_table(14, "Vname_persons");
        return $records_found;
    }

    /**
     * Find out to which versionized table belongs the UUID and return the repective name for the UUID
     * 
     * @param String $tablename
     *            the name of the table to which the UUID to resolve belongs
     * @param String $UUID
     *            the UUID to resolve
     * @param int $valid_at
     *            the time at which this UUIDs record shall be valid. Set 0 (or leave out) to get the most
     *            recent records entry.
     * @return array [ tablename, name ];
     */
    private function resolve_UUID_versionized (String $tablename, String $UUID, int $valid_at)
    {
        $name_versions = $this->names[$tablename]["@V"][$UUID];
        $valid_from_32 = 0;
        // sort the versions in ascending order of invalidity
        // this assumes that all records are at least after time = 1.000.000.000 which is the 9th Sept 2001.
        ksort($name_versions);
        foreach ($name_versions as $invalid_from => $name) {
            $invalid_from_32 = Efa_tables::value_validity32($invalid_from);
            if (($valid_from_32 <= $valid_at) && ($valid_at <= $invalid_from_32))
                return [$tablename,$name
                ];
            $valid_from_32 = $invalid_from_32;
        }
        // no valid entry was found. Use last one from the non versionized index
        return [$this->names[$tablename][$UUID],$name
        ];
    }

    /**
     * Find out to which table belongs the UUID and return the name for it
     * 
     * @param String $UUID
     *            the UUID to resolve
     * @param int $valid_at
     *            the time at which this UUIDs record shall be valid. Set 0 (or leave out) to get the most
     *            recent records entry.
     * @return array [ tablename, name ];
     */
    public function resolve_UUID (String $UUID, int $valid_at = 0)
    {
        if (count($this->names) == 0)
            $this->collect_arrays();
        $tablename = "";
        if (isset($this->names["efa2boats"][$UUID]) && (strlen($this->names["efa2boats"][$UUID]) > 1))
            $tablename = "efa2boats";
        elseif (isset($this->names["efa2destinations"][$UUID]) &&
                 (strlen($this->names["efa2destinations"][$UUID]) > 1))
            $tablename = "efa2destinations";
        elseif (isset($this->names["efa2groups"][$UUID]) && (strlen($this->names["efa2groups"][$UUID]) > 1))
            $tablename = "efa2persons";
        elseif (isset($this->names["efa2persons"][$UUID]) && (strlen($this->names["efa2persons"][$UUID]) > 1))
            $tablename = "efa2persons";
        elseif (isset($this->names["efa2status"][$UUID]) && (strlen($this->names["efa2status"][$UUID]) > 1))
            $tablename = "efa2status";
        elseif (isset($this->names["efa2waters"][$UUID]) && (strlen($this->names["efa2waters"][$UUID]) > 1))
            $tablename = "efa2waters";
        if (strlen($tablename) == 0)
            return ["unresolved",$UUID
            ];
        if (($valid_at > 0) && in_array($tablename, Efa_tables::$versionized_table_names)) {
            // if a validity constraint is given, the first steps can still be used to identify the table
            return $this->resolve_UUID_versionized($tablename, $UUID, $valid_at);
        }
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