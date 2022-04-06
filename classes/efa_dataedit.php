<?php

/**
 * class file providing a generic data edit form for any efa table.
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
     * A set of input type defaults used for data entry based on the column type
     */
    private $input_type_defaults = ["bigint" => "text;;18;30","boolean" => "checkbox;;;",
            "date" => "date;;10;20","int" => "text;;10;12","longtext" => "textarea;;4;",
            "mediumtext" => "textarea;;4;","text" => "textarea;;4;","tinytext" => "textarea;;4;",
            "time" => "text;;8;10"
    ];

    /**
     * A set of all fields which carry a UUID. Some of the field names are used in multiple tables
     */
    public $UUID_fields = "BoatId;FixedByPersonId;ReportedByPersonId;Id;" .
             "CoxId;Crew1Id;Crew2Id;Crew3Id;Crew4Id;Crew5Id;Crew6Id;Crew7Id;Crew8Id;Crew9Id;Crew10Id;Crew11Id;Crew12Id;" .
             "Crew13Id;Crew14Id;Crew15Id;Crew16Id;Crew17Id;Crew18Id;Crew19Id;Crew20Id;Crew21Id;Crew22Id;Crew23Id;Crew24Id;" .
             "PersonId;DestinationId;StatusId;";

    /**
     * The tables to look up the name for the UUID
     */
    private $UUID_lookup_tables = ["BoatId" => "efa2boats","FixedByPersonId" => "efa2persons",
            "ReportedByPersonId" => "efa2persons","CoxId" => "efa2persons","Crew1Id" => "efa2persons",
            "Crew2Id" => "efa2persons","Crew3Id" => "efa2persons","Crew4Id" => "efa2persons",
            "Crew5Id" => "efa2persons","Crew6Id" => "efa2persons","Crew7Id" => "efa2persons",
            "Crew8Id" => "efa2persons","Crew9Id" => "efa2persons","Crew10Id" => "efa2persons",
            "Crew11Id" => "efa2persons","Crew12Id" => "efa2persons","Crew13Id" => "efa2persons",
            "Crew14Id" => "efa2persons","Crew15Id" => "efa2persons","Crew16Id" => "efa2persons",
            "Crew17Id" => "efa2persons","Crew18Id" => "efa2persons","Crew19Id" => "efa2persons",
            "Crew20Id" => "efa2persons","Crew21Id" => "efa2persons","Crew22Id" => "efa2persons",
            "Crew23Id" => "efa2persons","Crew24Id" => "efa2persons","PersonId" => "efa2persons",
            "DestinationId" => "efa2destinations","StatusId" => "efa2status"
    ];

    /**
     * A set of all fields which carry a timestamp. Some of the field names are used in multiple tables
     */
    private $timestamp_fields = "LastModified;InvalidFrom;ValidFrom;";

    /**
     * The tables for which a key fixing is allowed
     */
    private $versionized_list = "efa2boats efa2destinations efa2groups efa2persons";

    /**
     * A set of all fields which carry a UUID list. Some of the field names are used in multiple tables
     */
    public $multi_UUID_fields = "WatersIdList;MemberIdList";

    /**
     * public Constructor.
     */
    public function __construct (Tfyh_toolbox $toolbox, Tfyh_socket $socket)
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
        include_once "../classes/efa_tables.php";
        $select_chunk_size = Efa_tables::$select_chunk_size;
        
        $this->names = [];
        
        $records_found = 1;
        $start_row = 0;
        
        while ($records_found > 0) {
            $records_found = 0;
            // Boats
            $records = $this->socket->find_records_sorted_matched("efa2boats", [], $select_chunk_size, "", 
                    "Name", true, $start_row);
            if ($records !== false)
                for ($i = 0; $i < count($records); $i ++) {
                    $this->names["efa2boats"][$records[$i]["Id"]] = $records[$i]["Name"];
                    $records_found ++;
                }
            // Destinations
            $records = $this->socket->find_records_sorted_matched("efa2destinations", [], $select_chunk_size, 
                    "", "Name", true, $start_row);
            if ($records !== false)
                for ($i = 0; $i < count($records); $i ++) {
                    $this->names["efa2destinations"][$records[$i]["Id"]] = $records[$i]["Name"];
                    $records_found ++;
                }
            // Persons
            $records = $this->socket->find_records_sorted_matched("efa2persons", [], $select_chunk_size, "", 
                    "FirstName", true, $start_row);
            if ($records !== false)
                for ($i = 0; $i < count($records); $i ++) {
                    $fullname = $records[$i]["FirstName"] . " " . $records[$i]["LastName"];
                    $this->names["efa2persons"][$records[$i]["Id"]] = $fullname;
                    $records_found ++;
                }
            // Status
            $records = $this->socket->find_records_sorted_matched("efa2status", [], $select_chunk_size, "", 
                    "Name", true, $start_row);
            if ($records !== false)
                for ($i = 0; $i < count($records); $i ++) {
                    $this->names["efa2status"][$records[$i]["Id"]] = $records[$i]["Name"];
                    $records_found ++;
                }
            // Waters
            $records = $this->socket->find_records_sorted_matched("efa2waters", [], $select_chunk_size, "", 
                    "Name", true, $start_row);
            if ($records !== false)
                for ($i = 0; $i < count($records); $i ++) {
                    $this->names["efa2waters"][$records[$i]["Id"]] = $records[$i]["Name"];
                    $records_found ++;
                }
            $start_row += $select_chunk_size;
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
     * collect the input types for all data fields
     */
    private function collect_input_types (String $table_name)
    {
        $ctypes = $this->socket->get_column_types($table_name);
        $cnames = $this->socket->get_column_names($table_name);
        $input_types = [];
        for ($c = 0; $c < count($ctypes); $c ++) {
            $cname = $cnames[$c];
            $ctype_parts = explode("(", $ctypes[$c]);
            $ctype = strtolower(trim($ctype_parts[0]));
            $csize = intval(
                    (count($ctype_parts) > 1) ? substr($ctype_parts[1], 0, strlen($ctype_parts[1]) - 1) : 0);
            if (($csize == 12) && (strcasecmp($ctype, "varchar") == 0))
                $ctype = "boolean";
            $input_types[$cname] = "text;;20;50";
            foreach ($this->input_type_defaults as $type => $input_type_default) {
                if (strcasecmp($type, $ctype) == 0) {
                    $input_types[$cname] = $input_type_default;
                }
            }
        }
        return $input_types;
    }

    /**
     * Return the select options for a table field
     * 
     * @param String $fieldname            
     */
    private function get_select_options (String $fieldname)
    {
        $tablename = $this->UUID_lookup_tables[$fieldname];
        if (strlen($tablename) == 0)
            return "select config-error=config-error";
        elseif (strcasecmp($tablename, "efa2boats") == 0)
            return "select list:select:2+";
        elseif (strcasecmp($tablename, "efa2destinations") == 0)
            return "select list:select:3+";
        elseif (strcasecmp($tablename, "efa2persons") == 0)
            return "select list:select:4+";
        elseif (strcasecmp($tablename, "efa2status") == 0)
            return "select list:select:5+";
        return "select config-error=config-error";
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
     * Provide a form template which can be used by the Form calls to display a data edit form for the given
     * record. Will only contain fields which are not empty.
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
        $exclude_fields_matcher = "LastModified, LastModification, ClientSideKey, ChangeCount, ecrid, ecrown, ecrhis, ";
        $dont_show_matcher = "ecrhis, "; // never show the record history when editing the record.
        $layout_info = "<div class='w3-row'><div class='w3-col l1'>";
        $first_line = true;
        foreach ($record as $key => $value) {
            $is_UUID_field = (strpos($this->UUID_fields, $key . ";") !== false);
            // if the record contains a UUID collect the UUIDs, if not yet done.
            if ((count($this->names) == 0) && $is_UUID_field)
                $this->collect_UUIDs();
            $is_timestamp = (strpos($this->timestamp_fields, $key) !== false);
            if ($is_timestamp)
                $value = (strlen($value) > 13) ? "unlimited" : date("Y-m-d H:i:s", 
                        intval(substr($value, 0, strlen($value) - 3)));
            // Put the data key and the exclude fields as a non-input in the header
            if ((strpos($key_fields_matcher, $key) !== false) ||
                     (strpos($exclude_fields_matcher, $key) !== false)) {
                if ($value && (strpos($dont_show_matcher, $key) === false)) {
                    $template .= $layout_info . ";;_no_input;;";
                    if ($first_line == true) {
                        $first_line = false;
                        $layout_info = "</div></div>" . $layout_info;
                    }
                    $resolved_value = ($is_UUID_field &&
                             isset($this->names[$this->UUID_lookup_tables[$key]][$value])) ? $value . " <b>" .
                             $this->names[$this->UUID_lookup_tables[$key]][$value] . "</b>" : $value;
                    $template .= $key . " = " . $resolved_value . ";;;;\n";
                }
            }
        }
        $template .= "<div class='w3-row'><div class='w3-col l1'>;;_no_input;; ;;;;\n";
        $i = 0;
        $input_types = $this->collect_input_types($tablename);
        $record = Efa_tables::fix_boolean_text($tablename, $record);
        foreach ($record as $key => $value) {
            $csv_value = $this->toolbox->encode_entry_csv($value);
            $is_UUID_field = (strpos($this->UUID_fields, $key . ";") !== false);
            if ((strpos($key_fields_matcher, $key) === false) &&
                     (strpos($exclude_fields_matcher, $key) === false)) {
                $format_template = "</div></div><div class='w3-row'><div class='w3-col l2'>";
                if ($i % 2 == 1)
                    $format_template = "</div><div class='w3-col l2'>";
                if ($is_UUID_field)
                    $template .= $format_template . ";;" . $key . ";" . $csv_value . ";" . $key . ";" .
                             $this->get_select_options($key) . ";;20;50\n";
                else
                    $template .= $format_template . ";;" . $key . ";" . $csv_value . ";" . $key . ";" .
                             $input_types[$key] . "\n";
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

    /**
     * Change the text of a UUID entry to be efa-compatible: i. e. from "-1" to ""
     * 
     * @param String $tablename            
     * @param array $record            
     */
    public function fix_empty_UUIDs (String $tablename, array $record)
    {
        $UUID_fields = $this->UUID_fields;
        // fix for boolean (checkbox) values: efa expects "true" or nothing instead of "on" or nothing
        foreach ($record as $key => $value) {
            if ((strpos($UUID_fields, $key . ";")) && (intval($value) == - 1))
                $record[$key] = "";
        }
        return $record;
    }
}
?>
