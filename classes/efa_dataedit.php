<?php

/**
 * class file providing a generic data edit form for any efa table.
 *
 * @package efacloud
 * @subpackage classes
 * @author mgSoft
 */
class Efa_dataedit
{

    /**
     * The data base connection socket.
     */
    private $socket;

    /**
     * The generic toolbox.
     */
    private $toolbox;

    /**
     * a set of all Names of boats, destinations, persons and waters and the UUID to use for them.
     */
    private $names;

    /**
     * A set of all fields which carry a UUID. Some of the field names are used in multiple tables
     */
    public $UUID_fields = "BoatId;FixedByPersonId;ReportedByPersonId;Id;" .
             "CoxId;Crew1Id;Crew2Id;Crew3Id;Crew4Id;Crew5Id;Crew6Id;Crew7Id;Crew8Id;Crew9Id;Crew10Id;Crew11Id;Crew12Id;" .
             "Crew13Id;Crew14Id;Crew15Id;Crew16Id;Crew17Id;Crew18Id;Crew19Id;Crew20Id;Crew21Id;Crew22Id;Crew23Id;Crew24Id;" .
             "PersonId;DestinationId;StatusId;";

    /**
     * A set of all fields which carry a UUID. Some of the field names are used in multiple tables
     */
    private $timestamp_fields = "LastModified;InvalidFrom;ValidFrom;";

    /**
     * The tables for which a key fixing is allowed
     */
    private $versionized_list = "efa2boats efa2destinations efa2groups efa2persons";

    /**
     * A set of all fields which carry a UUID list. Some of the field names are used in multiple
     * tables
     */
    public $multi_UUID_fields = "WatersIdList;MemberIdList";

    /**
     * public Constructor.
     */
    public function __construct (Toolbox $toolbox, Socket $socket)
    {
        $this->socket = $socket;
        $this->toolbox = $toolbox;
        $this->names = [];
    }

    /**
     * Collect all UUIDs
     */
    private function collect_UUIDs ()
    {
        $this->names = [];
        // Boats
        $records = $this->socket->find_records_sorted_matched("efa2boats", [], 5000, "", "Name", true, true);
        
        $this->UUIDs["efa2boats"] = [];
        for ($i = 0; $i < count($records); $i ++) {
            $this->names["efa2boats"][$records[$i]["Id"]] = $records[$i]["Name"];
        }
        // Destinations
        $records = $this->socket->find_records_sorted_matched("efa2destinations", [], 5000, "", "Name", true, true);
        $this->UUIDs["efa2destinations"] = [];
        for ($i = 0; $i < count($records); $i ++) {
            $this->names["efa2destinations"][$records[$i]["Id"]] = $records[$i]["Name"];
        }
        // Persons
        $records = $this->socket->find_records_sorted_matched("efa2persons", [], 5000, "", "FirstName", true, true);
        $this->UUIDs["efa2persons"] = [];
        for ($i = 0; $i < count($records); $i ++) {
            $fullname = $records[$i]["FirstName"] . " " . $records[$i]["LastName"];
            $this->names["efa2persons"][$records[$i]["Id"]] = $fullname;
        }
        // Waters
        $records = $this->socket->find_records_sorted_matched("efa2waters", [], 5000, "", "Name", true, true);
        $this->UUIDs["efa2waters"] = [];
        for ($i = 0; $i < count($records); $i ++) {
            $this->names["efa2waters"][$records[$i]["Id"]] = $records[$i]["Name"];
        }
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
            $this->collect_UUIDs();
        $tablename = "";
        if (isset($this->names["efa2boats"][$UUID]) && (strlen($this->names["efa2boats"][$UUID]) > 1))
            $tablename = "efa2boats";
        elseif (isset($this->names["efa2destinations"][$UUID]) && (strlen($this->names["efa2destinations"][$UUID]) > 1))
            $tablename = "efa2destinations";
        elseif (isset($this->names["efa2persons"][$UUID]) && (strlen($this->names["efa2persons"][$UUID]) > 1))
            $tablename = "efa2persons";
        elseif (isset($this->names["efa2waters"][$UUID]) && (strlen($this->names["efa2waters"][$UUID]) > 1))
            $tablename = "efa2waters";
        if (strlen($tablename) == 0)
             return ["unresolved", $UUID];
        return [$tablename,$this->names[$tablename][$UUID]
        ];
    }

    /**
     * Find out to which table belongs the UUID and return the select options for it
     *
     * @param String $UUID            
     */
    private function get_select_options (String $UUID = null)
    {
        if ($UUID == null)
            return "select ";
        $table_and_name = $this->resolve_UUID($UUID);
        if (strlen($table_and_name[0]) == 0)
            return "select config-error=config-error";
        $select = "select ";
        foreach ($this->names[$table_and_name[0]] as $UUID => $name)
            $select .= $UUID . "=" . str_replace(";", ",,", $name) . ";";
        return '"' . substr($select, 0, strlen($select) - 1) . '"';
    }

    /**
     * returns true, if the input Strin is a valid UUID
     */
    public function isUUID (String $toCheck = null)
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
     * Return the data key to identify the matching record
     *
     * @param String $tablename
     *            name of the efa table to which this record belongs
     * @param array $record
     *            the record which shall be modified.
     */
    public function get_data_key (String $tablename, array $record)
    {
        $key_fields = Efa_tables::$key_fields[$tablename];
        $data_key = [];
        foreach ($key_fields as $key_field)
            $data_key[$key_field] = $record[$key_field];
        return $data_key;
    }

    /**
     * Provide a form template which can be used by the Form calls to display a data edit form for
     * the given record. Will only contain fields which are not empty.
     *
     * @param String $tablename
     *            name of the efa table to which this record belongs
     * @param array $record
     *            the record which shall be modified.
     */
    public function set_data_edit_template (String $tablename, array $record)
    {
        $template = "tags;required;name;value;label;type;class;size;maxlength\n";
        include_once "../classes/efa_tables.php";
        $key_fields = Efa_tables::$key_fields[$tablename];
        $key_fields_matcher = $key_fields[0] . ",";
        if (isset($key_fields[1]))
            $key_fields_matcher .= $key_fields[1] . ",";
        if (isset($key_fields[2]))
            $key_fields_matcher .= $key_fields[2] . ",";
        $exclude_fields_matcher = "LastModified, LastModification, ClientSidKey, ";
        foreach ($record as $key => $value) {
            $is_UUID_field = (strpos($this->UUID_fields, $key . ";") !== false);
            $is_timestamp = (strpos($this->timestamp_fields, $key) !== false);
            if ($is_timestamp)
                $value = (strlen($value) > 13) ? "unlimited" : date("Y-m-d H:i:s", 
                        intval(substr($value, 0, strlen($value) - 3)));
            // Put the data key and the fields: LastModified, LastModification and ClientSideKey as
            // a non-input in the header
            if ((strpos($key_fields_matcher, $key) !== false) || (strpos($exclude_fields_matcher, $key) !== false))
                if ($value)
                    $template .= "<div class='w3-row'><div class='w3-col l1'>;;_no_input;;" . $key . " = " . $value .
                             ";;;;\n";
                // if the record contains a UUID collect the UUIDs, if not yet done.
                elseif ((count($this->names) == 0) && $is_UUID_field)
                    $this->collect_UUIDs();
        }
        $template .= "<div class='w3-row'><div class='w3-col l1'>;;_no_input;; ;;;;\n";
        $i = 0;
        foreach ($record as $key => $value) {
            $csv_value = $this->toolbox->encode_entry_csv($value);
            $is_UUID_field = (strpos($this->UUID_fields, $key . ";") !== false);
            if ((strpos($key_fields_matcher, $key) === false) && (strpos($exclude_fields_matcher, $key) === false)) {
                $format_template = "</div></div><div class='w3-row'><div class='w3-col l2'>";
                if ($i % 2 == 1)
                    $format_template = "</div><div class='w3-col l2'>";
                if ($is_UUID_field)
                    $template .= $format_template . ";;" . $key . ";" . $csv_value . ";" . $key . ";" .
                             $this->get_select_options($value) . ";;20;50\n";
                else
                    $template .= $format_template . ";;" . $key . ";" . $csv_value . ";" . $key . ";text;;20;50\n";
                $i ++;
            }
        }
        $is_versionized_table = (strpos($this->versionized_list, $tablename) !== false);
        if ($is_versionized_table)
            $template .= "</div></div><div class='w3-row'><div class='w3-col l1'>;;_no_input;;<br><b>bei versionierten Listen ist leider keine Bearbeitung möglich.</b><br>;;;;\n";
        else
            $template .= "</div></div><div class='w3-row'><div class='w3-col l1'><div style='float:right'> <br>;;submit;Jetzt ändern;;submit;formbutton;;\n";
        $template .= "</div></div></div>;;_no_input;;;;;;\n";
        $template .= "<li><span class='helptext'>;;_help_text;;Dies ist ein automatisch aus dem Datensatz generiertes Formular. Es enthält nur Felder, die auch im Datensatz enthalten sind.;;;;\n";
        if ($is_versionized_table)
            $template .= "</span></li><li><span class='helptext'>;;_help_text;;Die Tabelle ist historisiert, Einträge haben einen Zeitbezug. Diese Datensätze können zur Zeit hier nicht bearbeitet werden.;;;;\n";
        else
            $template .= "</span></li><li><span class='helptext'>;;_help_text;;Für alle Felder ist Texteingabe vorgesehen, auch wenn es sich um Zahlen, Auswahlfelder oder ein Datum handelt;;;;\n";
        $template .= "</span></li>;;_help_text;;;;;;";
        file_put_contents("../config/layouts/dataedit", $template);
    }
}
?>
