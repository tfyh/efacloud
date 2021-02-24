<?php

/**
 * This class provides a list segment for a web file. <p>The definition must be a CSV-file, all entries
 * without line breaks, with the first line being always "id;permission;name;select;from;where;options" and
 * the following lines the respective values. The values in select, from, where and options are combined to
 * create the needed SQL-statement to retrieve the list elements from the data base.</p><p>options
 * are<ul><li>sort=[-]column[.[-]column]: order by the respective column in ascending or descending (-)
 * order</li><li>filter=column.value: filter the column for the given value, always using the LIKE operator
 * with '*' before and after the value</li><li>link=[link]: link the first column to the given url e.g.
 * '../forms/change_user.php?id=ID' replacing the column name at the end (here: ID) by the respective
 * value.</li></ul></p> <p>The list is always displayed as a table grid. It will show the default sorting, if
 * no sorting option is provided.</p>
 */
class Tfyh_list
{

    /**
     * Definition of all lists in configuration file. Will be read once upon construction from $file_path.
     */
    private $list_definitions;

    /**
     * id of list within list definitions. Will be read once upon construction from $file_path.
     */
    private $id;

    /**
     * Definition of list. Will be read once upon construction from $file_path.
     */
    private $list_definition;

    /**
     * The socket used for DB-Access.
     */
    private $socket;

    /**
     * The common toolbox used.
     */
    private $toolbox;

    /**
     * The index of this form in a multistep form.
     */
    private $index;

    /**
     * an array of all columns of this list
     */
    private $columns;

    /**
     * the default table name for this list
     */
    private $table_name;

    /**
     * the list set chosen (lists file name)
     */
    private $list_set;

    /**
     * the list set chosen (lists file name)
     */
    private $list_set_permissions;

    /**
     * the list of sort options using the format [-]column[.[-]column]
     */
    private $osorts_list;

    /**
     * the column of the filter option for this list
     */
    private $ofilter;

    /**
     * the value of the filter option for this list
     */
    private $ofvalue;

    /**
     * the value of edit link column. If this value is set, the list will display an edit link within the data
     * value of this column
     */
    private $record_link_col;

    /**
     * the value of the edit link which is expanded by the data value, e.g. '../change_user.php?id='
     */
    private $record_link;

    /**
     * Build a list based on the definition provided in the csv file at $file_path.
     * 
     * @param String $file_path
     *            path to file with list definitions. List definitions contain of:
     *            id;permission;name;select;from;where;options. For details see class description.
     * @param int $id
     *            the id of the list to use. Set to 0 to use name identification.
     * @param String $name
     *            the name of the list to use. Will be used, if id == 0, else it is ignored. Set "" to get the
     *            last list of a set, which initializes the set.
     * @param Socket $socket
     *            the socket to connect to the data base
     * @param Toolbox $toolbox
     *            the application basic utilities
     */
    public function __construct (String $file_path, int $id, String $name, Socket $socket, Toolbox $toolbox)
    {
        $this->socket = $socket;
        $this->toolbox = $toolbox;
        $this->list_definition = [];
        $this->list_set = substr($file_path, strrpos($file_path, "/") + 1);
        $this->id = $id;
        $this->list_set_permissions = "";
        $this->list_definitions = $toolbox->read_csv_array($file_path);
        // if definitions could be found, parse all and get own.
        if ($this->list_definitions !== false) {
            foreach ($this->list_definitions as $list_definition) {
                if ($id === 0) {
                    if ((strcasecmp($list_definition["name"], $name) === 0) || (! $name)) {
                        $this->list_definition = $list_definition;
                        $this->id = intval($list_definition["id"]);
                    }
                } else {
                    if (intval($list_definition["id"]) === $id)
                        $this->list_definition = $list_definition;
                }
                if (strpos($this->list_set_permissions, $list_definition["permission"]) === false)
                    $this->list_set_permissions .= $list_definition["permission"] . ",";
            }
            if ($this->list_definition) {
                $this->table_name = $this->list_definition["from"];
                $this->columns = explode(",", $this->list_definition["select"]);
                $this->parse_options($this->list_definition["options"]);
            }
        } else
            $this->table_name = "";
        $this->record_link_col = "";
        $this->record_link = "";
    }

    /**
     * Check whether the list is properly initialized.
     * 
     * @return boolean true, if the either the list is ok or the $id is 0, false, if not
     */
    public function is_valid ()
    {
        return strlen($this->table_name) > 0;
    }

    /**
     * Get the table name parameter for this list.
     * 
     * @return String the table name parameter for this list.
     */
    public function get_table_name ()
    {
        return $this->table_name;
    }

    /**
     * Simple getter
     * 
     * @return String the list's name.
     */
    public function get_list_name ()
    {
        return $this->list_definition["name"];
    }

    /**
     * Simple getter
     * 
     * @return String the list's id.
     */
    public function get_list_id ()
    {
        return $this->list_definition["id"];
    }

    /**
     * Simple getter
     * 
     * @return String the list set's compiled permission String
     */
    public function get_set_permission ()
    {
        return $this->list_set_permissions;
    }

    /**
     * Simple getter
     * 
     * @return String the list's permission String
     */
    public function get_permission ()
    {
        return $this->list_definition["permission"];
    }

    /**
     * Simple getter
     * 
     * @return array all list definitions retrieved from the list definition file. Will be false, if the list
     *         definition ile was not found.
     */
    public function get_all_list_definitions ()
    {
        return $this->list_definitions;
    }

    /**
     * Parse the options String containing the sort and filter options, e.g. "sort=-name&filter=doe"
     * 
     * @param String $options_list            
     */
    public function parse_options (String $options_list)
    {
        $options = explode("&", $options_list);
        $this->osorts_list = "";
        $this->ofilter = "";
        $this->ofvalue = "";
        $this->record_link = "";
        $this->record_link_col = "";
        
        foreach ($options as $option) {
            $option_pair = explode("=", $option);
            if (strcasecmp("sort", $option_pair[0]) === 0)
                $this->osorts_list = $option_pair[1];
            if (strcasecmp("filter", $option_pair[0]) === 0)
                $this->ofilter = $option_pair[1];
            if (strcasecmp("fvalue", $option_pair[0]) === 0)
                $this->ofvalue = $option_pair[1];
            if (strcasecmp("link", $option_pair[0]) === 0) {
                $separate_at = strrpos($option_pair[1], "=");
                $this->record_link = substr($option_pair[1], 0, $separate_at + 1);
                $this->record_link_col = substr($option_pair[1], $separate_at);
            }
        }
    }

    /**
     * little internal helper for SQL-statement assembling used by display and zip download.
     * 
     * @param String $osorts_list
     *            the list of sort options using the format [-]column[.[-]column]. Set "" to use the list
     *            definition default.
     * @param String $ofilter
     *            the column for filter option for this list. Set "" to use the list definition default.
     * @param String $ofvalue
     *            the value for filter option for this list. Set "" to use the list definition default.
     * @return string the correct and complete select sql statement
     */
    private function build_sql_cmd (String $osorts_list, String $ofilter, String $ofvalue)
    {
        $osl = (strlen($osorts_list) == 0) ? $this->osorts_list : $osorts_list;
        $of = (strlen($ofilter) == 0) ? $this->ofilter : $ofilter;
        $ofv = (strlen($ofvalue) == 0) ? $this->ofvalue : $ofvalue;
        
        // interprete sorts
        $order_by = "";
        if (strlen($osl) > 0) {
            $osorts = explode(".", $osl);
            if (count($osorts) > 0) {
                $order_by = " ORDER BY ";
                foreach ($osorts as $osort) {
                    $sortmode = " ASC,";
                    if (strcasecmp(substr($osort, 0, 1), "-") === 0) {
                        $sortmode = " DESC,";
                        $osort = substr($osort, 1);
                    }
                    $order_by .= "`" . $this->table_name . "`.`" . $osort . "`" . $sortmode;
                }
                $order_by = substr($order_by, 0, strlen($order_by) - 1);
            }
        }
        
        // interprete filter
        $where = $this->list_definition["where"];
        if ((count($_SESSION["User"]) > 0) && (strpos($where, "\$mynumber") !== false))
            $where = str_replace("\$mynumber", $_SESSION["User"]["Besuchernummer"], $where);
        if ((strlen($of) > 0) && (strlen($ofv) > 0)) {
            $where = " WHERE (" . $where . ") AND (`" . $this->table_name . "`.`" . $of . "` LIKE '" .
                     str_replace('*', '%', $ofv) . "')";
        } else {
            $where = " WHERE " . $where;
        }
        
        // assemble SQL-statement
        $sql_cmd = "SELECT ";
        $join_statement = ""; // special case: Inner Join using the Besuchernummer
        foreach ($this->columns as $column) {
            if (strpos($column, ".") !== false) {
                $sql_cmd .= "`" . str_replace(".", "`.`", $column) . "`, ";
                $join_statement = " INNER JOIN `Mitgliederliste` ON `" . $this->table_name .
                         "`.`Besuchernummer`=`Mitgliederliste`.`Besuchernummer` ";
            } else
                $sql_cmd .= "`" . $this->table_name . "`.`" . $column . "`, ";
        }
        $sql_cmd = substr($sql_cmd, 0, strlen($sql_cmd) - 2);
        $sql_cmd .= " FROM `" . $this->table_name . "`" . $join_statement . $where . $order_by;
        
        return $sql_cmd;
    }

    /**
     * Return a html code of this list based on its definition or the provided options.
     * 
     * @param String $osorts_list
     *            the list of sort options using the format [-]column[.[-]column]. Set "" to use the list
     *            definition default.
     * @param String $ofilter
     *            the filter option for this list. Set "" to use the list definition default.
     * @param String $ofvalue
     *            the value for filter option for this list. Set "" to use the list definition default.
     * @return string html formatted table for web display.
     */
    public function get_html (String $osorts_list, String $ofilter, String $ofvalue)
    {
        if (count($this->list_definition) === 0)
            return "<p>Application configuration error: list not defined. " .
                     "Please check with your administrator.</p>";
        
        // build table header
        $row_count_max = 100;
        $list_html = "Aufsteigend sortieren mit einem Klick auf den Spaltenkopf (nur blaue), " .
                 "absteigend mit einem zweiten Klick auf denselben Spaltenkopf.\n";
        $list_html .= "<div style='overflow-x: auto; white-space: nowrap; margin-top:12px; margin-bottom:10px;'>";
        $list_html .= "<table style='border: 2px solid transparent;'><thead><tr>";
        // identify ID-column for change link
        $col = - 1;
        $col_id = - 1;
        foreach ($this->columns as $column) {
            // identify ID-column for change link
            $col ++;
            if ((strcasecmp($column, "ID") == 0) && (strlen($this->ofclink) > 0))
                $col_id = $col;
            $is_lookup = (strpos($column, ".") !== false);
            $col_short = ($is_lookup) ? substr($column, strpos($column, ".") + 1) : $column;
            $pos_sorts = strpos($osorts_list, $column);
            if (($pos_sorts !== false) && (($pos_sorts == 0) ||
                     (substr($osorts_list, $pos_sorts - 1, 1) == ".") ||
                     (substr($osorts_list, $pos_sorts - 1, 1) == "-"))) {
                $desc_sort = ($pos_sorts > 0) && (substr($osorts_list, $pos_sorts - 1, $pos_sorts) == "-");
                $ctext = ($desc_sort) ? $col_short . '<br /><b>&nbsp;&nbsp;&#9650;</b> ' : $col_short .
                         '<br /><b>&nbsp;&nbsp;&#9660;</b> ';
                $csort = ($desc_sort) ? $column : '-' . $column;
            } else {
                $ctext = $col_short;
                $csort = $column;
            }
            $ofstring = (strlen($ofilter) > 0) ? "&filter=" . $ofilter . "&fvalue=" . $ofvalue : "";
            // Columns with lookup positions can not be sorted.
            if ($is_lookup)
                $list_html .= "<th>" . $ctext . "</th>";
            else
                $list_html .= "<th><a class='table-header' href='?id=" . $this->id . "&satz=" . $this->list_set .
                         "&sort=" . $csort . $ofstring . "'>" . $ctext . "</a></th>";
        }
        $list_html .= "</tr></thead><tbody>";
        
        // assemble SQL-statement and read data
        $sql_cmd = $this->build_sql_cmd($osorts_list, $ofilter, $ofvalue, $_SESSION["User"]);
        $res = $this->socket->query($sql_cmd);
        $res_count_rows = $res->num_rows;
        $list_html = (($res_count_rows) ? "<b>" . $res_count_rows . " Datensätze gefunden.</b> " : "<b>keine Datensätze gefunden.</b> ") .
                 $list_html;
        if (intval($res_count_rows) > 0)
            $row = $res->fetch_row();
        $row_count = 0;
        // display data as table
        while ($row && ($row_count < $row_count_max)) {
            $row_str = "<tr>";
            $data_cnt = 0;
            $set_id = "0";
            foreach ($row as $data) {
                $link_user_id = (strcasecmp($this->table_name, $this->toolbox->users->user_table_name) == 0) &&
                         (strcasecmp($this->columns[$data_cnt], $this->toolbox->users->user_id_field_name) == 0);
                $is_record_link_col = (strcasecmp($this->columns[$data_cnt], $this->record_link_col) == 0);
                if ($link_user_id) {
                    $row_str .= "<td><b><a href='../pages/nutzer_profil.php?nr=" . $data . "'>" . $data .
                             "</a></b></td>";
                } elseif ($is_record_link_col) {
                    $row_str .= "<td><b><a href='" . $this->record_link . $data . "'>" . $data .
                             "</a></b></td>";
                } else
                    $row_str .= "<td>" . $data . "</td>";
                if ($data_cnt == $col_id)
                    $set_id = $data;
                $data_cnt ++;
            }
            $row_str .= "</tr>\n";
            $row_count ++;
            $list_html .= $row_str;
            $row = $res->fetch_row();
        }
        $list_html .= "</tbody></table></div>\n";
        if ($row && ($row_count >= $row_count_max))
            $list_html .= " Liste nach " . $row_count_max .
                     " Einträgen gekappt. Bitte den Filter nutzen, oder sich die komplette Liste herunterladen.";
        
        // provide zip-Link
        $sort_string = (strlen($osorts_list) == 0) ? "" : "&sort=" . $osorts_list;
        $filter_string = (strlen($ofilter) == 0) ? "" : "&filter=" . $ofilter;
        $fvalue_string = (strlen($ofvalue) == 0) ? "" : "&fvalue=" . $ofvalue;
        $reference_this_page = "?id=" . $this->list_definition["id"] . "&satz=" . $this->list_set . "&zip=1" .
                 $sort_string . $filter_string . $fvalue_string;
        
        // provide filter form
        $list_html .= "<form action='" . $reference_this_page .
                 "'>Filtern in Spalte:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        $list_html .= "<input type='hidden' name='id' value='" . $this->id . "' />";
        $list_html .= "<input type='hidden' name='satz' value='" . $this->list_set . "' />";
        if (strlen($osorts_list) > 0)
            $list_html .= "<input type='hidden' name='sort' value='" . $osorts_list . "' />";
        $list_html .= "<select name='filter' class='formselector' style='width:20em'>";
        foreach ($this->columns as $column) {
            if (strpos($column, ".") === false) {
                if (strcasecmp($column, $ofilter) == 0)
                    $list_html .= '<option value="' . $column . '" selected>' . $column . "</option>\n";
                else
                    $list_html .= '<option value="' . $column . '">' . $column . "</option>\n";
            }
        }
        $list_html .= "</select>";
        $list_html .= "<br>Wert = (wildcard *): <input type='text' name='fvalue' class='forminput' value='" .
                 $ofvalue . "'  style='width:19em' />" .
                 "&nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' value='gefilterte Liste anzeigen' class='formbutton'/></form>";
        $list_html .= "</p><p>Liste als Csv-Datei herunterladen: <a href='" .
                 $reference_this_page . "'>" . $this->table_name . ".zip</a>";
        $list_html .= "<br><br>BITTE BEACHTEN: Eine Weitergabe von hier exportierten Listen ist nicht gestattet.</p>";
        return $list_html;
    }

    /**
     * Provide a list with all data retrieved. Simple database get, no mapping to field names included.
     */
    public function get_rows ()
    {
        if (count($this->list_definition) === 0)
            return "<p>Application configuration error: list not defined. " .
                     "Please check with your administrator.</p>";
        
        // assemble SQL-statement and read data
        $sql_cmd = $this->build_sql_cmd($this->osorts_list, $this->ofilter, $this->ofvalue, []);
        $res = $this->socket->query($sql_cmd);
        $rows = [];
        if (intval($res->num_rows) > 0) {
            $row = $res->fetch_row();
            while ($row) {
                $rows[] = $row;
                $row = $res->fetch_row();
            }
        }
        return $rows;
    }

    /**
     * Identify the fields of the row by naming the $row array, e.g. from "array(2) { ["ID"]=> string(1) "6"
     * ["Besuchernummer"]=> string(4) "1111" }" to "array(2) { [0]=> string(1) "6" [1]=> string(4) "1111" }"
     * 
     * @param array $row
     *            row to set names. Shall be a row that was retrieved by get_rows().
     */
    public function get_named_row (array $row)
    {
        $named_row = [];
        $i = 0;
        foreach ($this->columns as $column_name) {
            $named_row[$column_name] = $row[$i];
            $i ++;
        }
        return $named_row;
    }

    /**
     * Provide a csv file zipped and then base64 encoded, filter and sorting according to default.
     * 
     * @return a base64 encoded zip file.
     */
    public function get_base64 ()
    {
        $csv = "";
        // build table header
        foreach ($this->columns as $column)
            $csv .= str_replace('"', '""', $column) . ";";
        $csv = substr($csv, 0, strlen($csv) - 1) . "\n";
        // assemble SQL-statement and read data
        $sql_cmd = $this->build_sql_cmd("", "", "", $_SESSION["User"]);
        $res = $this->socket->query($sql_cmd);
        if (intval($res->num_rows) > 0)
            $row = $res->fetch_row();
        while ($row) {
            $row_str = "";
            foreach ($row as $data) {
                if ((strpos($data, "\"") !== false) || (strpos($data, "\n") !== false) ||
                         (strpos($data, ";") !== false))
                    $row_str .= '"' . str_replace('"', '""', $data) . '";';
                else
                    $row_str .= $data . ";";
            }
            $csv .= substr($row_str, 0, strlen($row_str) - 1) . "\n";
            $row = $res->fetch_row();
        }
        // zip into binary $zip_contents
        $zip_filename = $this->zip($csv, $this->table_name . ".csv");
        $handle = fopen($zip_filename, "rb");
        $zip_contents = fread($handle, filesize($zip_filename));
        fclose($handle);
        unlink($zip_filename);
        // encode base64 and return
        return base64_encode($zip_contents);
    }

    /**
     * Provide a csv file for download. Will not return, but exit via $toolbox->return_string_as_zip()
     * function.
     * 
     * @param String $osorts_list
     *            the list of sort options using the format [-]column[.[-]column]. Set "" to use the list
     *            definition default.
     * @param String $ofilter
     *            the filter option for this list. Set "" to use the list definition default.
     * @param String $ofvalue
     *            the filter value for this list. Set "" to use the list definition default.
     * @return if this function terminates normally, it will not return, but start a download. If errors
     *         occur, it will return an eroor String.
     */
    public function get_zip (String $osorts_list, String $ofilter, String $ofvalue)
    {
        if (count($this->list_definition) === 0)
            return "<p>Application configuration error: list not defined. " .
                     "Please check with your administrator.</p>";
        
        $csv = "";
        
        // normal list export
        // build table header
        foreach ($this->columns as $column)
            $csv .= str_replace('"', '""', $column) . ";";
        $csv = substr($csv, 0, strlen($csv) - 1) . "\n";
        
        // assemble SQL-statement and read data
        $sql_cmd = $this->build_sql_cmd($osorts_list, $ofilter, $ofvalue, $_SESSION["User"]);
        $res = $this->socket->query($sql_cmd);
        if (intval($res->num_rows) > 0)
            $row = $res->fetch_row();
        while ($row) {
            $row_str = "";
            foreach ($row as $data) {
                if ((strpos($data, "\"") !== false) || (strpos($data, "\n") !== false) ||
                         (strpos($data, ";") !== false))
                    $row_str .= '"' . str_replace('"', '""', $data) . '";';
                else
                    $row_str .= $data . ";";
            }
            $csv .= substr($row_str, 0, strlen($row_str) - 1) . "\n";
            $row = $res->fetch_row();
        }
        
        // add timestamp, source and destination
        $destination = $_SESSION["User"][$this->toolbox->users->user_firstname_field_name] . " " .
                 $_SESSION["User"][$this->toolbox->users->user_lastname_field_name] . " (" .
                 $_SESSION["User"][$this->toolbox->users->user_id_field_name] . ", " .
                 $_SESSION["User"]["Rolle"] . ")";
        $csv .= "\nBereitgestellt am " . date("d.m.Y H:i:s") . " von " . $_SERVER['HTTP_HOST'] . " an " .
                 $destination . "\nWeitergabe dieser Liste ist nicht gestattet.\n";
        $this->toolbox->return_string_as_zip($csv, $this->table_name . ".csv");
    }
}
