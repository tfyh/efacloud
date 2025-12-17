<?php
/**
 *
 *       the tools-for-your-hobby framework
 *       ----------------------------------
 *       https://www.tfyh.org
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
 * This class provides a list segment for a web file. <p>The definition must be a CSV-file, all entries
 * without line breaks, with the first line being always "id;permission;name;select;from;where;options" and
 * the following lines the respective values. The values in select, from, where and options are combined to
 * create the needed SQL-statement to retrieve the list elements from the data base.</p><p>options
 * are<ul><li>sort=[-]column[.[-]column]: order by the respective column in ascending or descending (-)
 * order</li><li>filter=column.value: filter the column for the given value, always using the LIKE operator
 * with '*' before and after the value</li><li>link=[link]: link the first column to the given url e.g.
 * '../forms/change_user.php?id=id' replacing the column name at the end (here: id) by the respective
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
    public $list_definition;

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
     * an array of all compounds of this list. A compound is a String with replaceble elements to provide a
     * summary information on a data record within a single String.
     */
    private $compounds;

    /**
     * the default table name for this list
     */
    private $table_name;

    /**
     * the list set chosen (lists config file name)
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
     * the seconds how long the list may be cached
     */
    private $ocache_seconds;

    /**
     * the maximum number of rows in the list
     */
    private $maxrows;

    /**
     * filter for duplicates, only return the first of multiple, table must be sorted for that column
     */
    private $firstofblock;

    /**
     * filter for duplicates, only return the first of multiple, table must be sorted for that column
     */
    private $firstofblock_col;

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
     * the pivot-table settings, i. e. array(row_field, column_field, data_field, aggregation)
     */
    public $pivot;

    /**
     * Limit the size on an entry to a number of characters
     */
    public $entry_size_limit = 0;

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
     * @param Tfyh_socket $socket
     *            the socket to connect to the data base
     * @param Tfyh_toolbox $toolbox
     *            the application basic utilities
     * @param array $args
     *            a set of values which will be replaced in the list definition, e.g. [ "{name]" => "John" ]
     *            for a list with a condition (`Name` = '{name}')
     */
    public function __construct (String $file_path, int $id, String $name, Tfyh_socket $socket, 
            Tfyh_toolbox $toolbox, array $args = [])
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
                // check whether i18n replacement is needed
                if ($this->toolbox->is_valid_i18n_reference($list_definition["name"]))
                    $list_definition["name"] = i($list_definition["name"]);
                if ($this->toolbox->is_valid_i18n_reference($name))
                    $name = i($name);
                // do not parse comments.
                if (strcasecmp($list_definition["id"], "#") !== 0) {
                    if ($id === 0) {
                        if ((strcasecmp($list_definition["name"], $name) === 0) || (! $name)) {
                            $this->list_definition = $this->replace_args($list_definition, $args);
                            $this->id = intval($list_definition["id"]);
                        }
                    } else {
                        if (intval($list_definition["id"]) === $id) {
                            $this->list_definition = $this->replace_args($list_definition, $args);
                        }
                    }
                    if (strpos($this->list_set_permissions, $list_definition["permission"]) === false)
                        $this->list_set_permissions .= $list_definition["permission"] . ",";
                }
            }
            if (count($this->list_definition) > 0) {
                $this->table_name = $this->list_definition["from"];
                $this->parse_options($this->list_definition["options"]);
                // beware of the sequence! parse_columns needs parsed options.
                $this->parse_columns();
            } else {
                $toolbox->logger->log(2, $this->toolbox->users->session_user["@id"], 
                        "undefined list called: $id, '$name'; set: $file_path, [" . count($this->list_definition) . "]");
            }
        } else {
            $this->table_name = "";
            $this->record_link_col = "";
            $this->record_link = "";
        }
    }

    /**
     * Update a list definition by replacing place holders, usually in braces, but not necessary. plain String
     * replacement is used, no special logic - only ";" are replaced, for security reasons.
     * 
     * @param array $list_definition
     *            the definition of the list to update
     * @param array $args
     *            a set of values which will be replaced in the list definition, e.g. [ "{name]" => "John" ]
     *            for a list with a condition (`Name` = '{name}')
     * @return String the updated list definition
     */
    private function replace_args (array $list_definition, array $args)
    {
        if (! is_array($args) || (count($args) == 0))
            return $list_definition;
        $updated = $list_definition;
        foreach ($updated as $key => $value)
            foreach ($args as $template => $used) {
                $used_secure = (strpos($used, ";") !== false) ? i("KtXJLq|{invalid parameter with ...") : $used;
                $updated[$key] = str_replace($template, $used_secure, $updated[$key]);
            }
        return $updated;
    }

    /**
     * Get the arguments use in a list definition as comma separated string.
     * 
     * @param array $list_definition
     *            the definition of the list to check
     * @return String the parameters of the list definition
     */
    public function get_args (array $list_definition)
    {
        $args = "";
        foreach ($list_definition as $key => $value) {
            $brace_open = - 1;
            while ($brace_open !== false) {
                $brace_open = strpos($value, "{", $brace_open + 1);
                $brace_close = ($brace_open === false) ? false : strpos($value, "}", $brace_open);
                if (($brace_close !== false) && ($brace_open < $brace_close))
                    $args .= "," . substr($value, $brace_open + 1, $brace_close - $brace_open - 1);
            }
        }
        if (strlen($args) > 0)
            return substr($args, 1);
        return "";
    }

    /**
     * Remove all cached files for all lists.
     */
    public static function clear_caches ()
    {
        if (! file_exists("../log/cache"))
            mkdir("../log/cache");
        $files = scandir("../log/cache");
        foreach ($files as $file)
            if (strcmp(substr($file, 0, 1), ".") != 0)
                unlink("../log/cache/$file");
    }

    /**
     * Get the list from a cached file rather than from the data base
     * 
     * @param int $max_age            
     */
    private function get_rows_from_cache ()
    {
        // is cache enabled? If not return.
        if ($this->ocache_seconds == 0)
            return false;
        // is descriptor readable? If not return.
        $desc = file_get_contents("../log/cache/" . $this->list_set . "." . $this->id . ".desc");
        if ($desc === false)
            return false;
        // is the cache still valid? If not return.
        $desc = json_decode($desc, true);
        if (! isset($desc["retrieved"]) || ((time() - intval($desc["retrieved"])) > $this->ocache_seconds))
            return false;
        $list_as_array = $this->toolbox->read_csv_array(
                "../log/cache/" . $this->list_set . "." . $this->id . ".csv");
        // was the cache successfully read? If not return.
        if (count($list_as_array) == 0)
            return false;
        // does the layout of the cache match the current list layout? If not return.
        foreach ($this->columns as $column_name)
            if (! isset($list_as_array[0][$column_name]))
                return false;
        // read the cache into the rows.
        $rows = [];
        foreach ($list_as_array as $record) {
            $c = 0;
            $row = [];
            foreach ($this->columns as $column_name) {
                $row[$c] = $record[$column_name];
                $c ++;
            }
            $rows[] = $row;
        }
        return $rows;
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
     * Simple getter
     * 
     * @return array the list definitions for this list as retrieved from the list definition file.
     */
    public function get_list_definition ()
    {
        return $this->list_definition;
    }

    /**
     * Parse all columns within a list definition and split those which are direct columns from the compound
     * columns
     * 
     * @param String $select            
     */
    private function parse_columns ()
    {
        $this->columns = [];
        $this->compounds = [];
        
        $columns = explode(",", $this->list_definition["select"]);
        $c = 0;
        $this->firstofblock_col = - 1;
        foreach ($columns as $colraw) {
            $column = trim($colraw); // ignroe leading and trailing spaces in column names.
            if (strpos($column, "=") !== false) {
                $cname = substr($column, 0, strpos($column, "="));
                $cexpr = explode("$", substr($column, strpos($column, "=") + 1));
                $this->compounds[$cname] = $cexpr;
                $this->columns[] = $cname;
            } elseif (strlen($column) > 0) {
                if (strpos($column, ":") !== false) {
                    $this->columns[] = explode(":", $column, 2)[0];
                    $this->data_types[] = explode(":", $column, 2)[1];
                } else {
                    $this->columns[] = $column;
                    $this->data_types[] = false;
                }
                if (strcasecmp($column, $this->firstofblock) == 0)
                    $this->firstofblock_col = $c;
            }
            $c ++;
        }
    }

    /**
     * Add all compounds based on their definition and a raw list row. It is recommended to call this function
     * only if (count($this->compounds) > 0).
     * 
     * @param array $row            
     */
    private function build_compounds (array $fetched_db_row)
    {
        $compound_row = [];
        $c = 0;
        foreach ($this->columns as $column)
            if (isset($this->compounds[$column])) {
                // build the compound string
                $compound = "";
                foreach ($this->compounds[$column] as $csnippet)
                    if (substr($csnippet, 0, 1) == "$") {
                        $compound .= $csnippet;
                    } else {
                        $ceindex = intval(substr($csnippet, 0, 1));
                        $compound .= ($ceindex > 0) ? strval($fetched_db_row[$ceindex - 1]) .
                                 substr($csnippet, 1) : $csnippet;
                    }
                // add the compound string to the row
                $compound_row[] = $compound;
            } else {
                // add the next data base field to the row.
                $compound_row[] = $fetched_db_row[$c];
                $c ++;
            }
        return $compound_row;
    }

    /**
     * Parse the options String containing the sort and filter options, e.g. "sort=-name&filter=doe" or
     * "sort=ID&link=id=../forms/change_place.php?id="
     * 
     * @param String $options_list            
     */
    public function parse_options (String $options_list)
    {
        $options = explode("&", $options_list);
        $this->osorts_list = "";
        $this->ofilter = "";
        $this->ofvalue = "";
        $this->ocache_seconds = 0;
        $this->pivot = [];
        $this->record_link = "";
        $this->record_link_col = "";
        $this->firstofblock = "";
        $this->maxrows = 0; // 0 = no limit.
        foreach ($options as $option) {
            $option_pair = explode("=", $option, 2);
            if (strcasecmp("sort", $option_pair[0]) === 0)
                $this->osorts_list = $option_pair[1];
            if (strcasecmp("filter", $option_pair[0]) === 0)
                $this->ofilter = $option_pair[1];
            if (strcasecmp("fvalue", $option_pair[0]) === 0)
                $this->ofvalue = $option_pair[1];
            if (strcasecmp("firstofblock", $option_pair[0]) === 0)
                $this->firstofblock = $option_pair[1];
            if (strcasecmp("cache_seconds", $option_pair[0]) === 0)
                $this->ocache_seconds = intval($option_pair[1]);
            if (strcasecmp("maxrows", $option_pair[0]) === 0)
                $this->maxrows = intval($option_pair[1]);
            if (strcasecmp("pivot", $option_pair[0]) === 0)
                $this->pivot = explode(".", $option_pair[1]);
            if (strcasecmp("link", $option_pair[0]) === 0) {
                $this->record_link_col = explode(":", $option_pair[1])[0];
                $this->record_link = urldecode(explode(":", $option_pair[1])[1]);
            }
        }
    }

    /**
     * little internal helper for SQL-statement assembling used by display and zip download.
     * 
     * @param String $osorts_list
     *            the list of sort options using the format [-][#]column[.[-][#]column]. Set "" to use the
     *            list definition default. The [-] sets the sorting to descendent. the [#] enforces numeric
     *            sorting for integers stored as Varchar
     * @param String $ofilter
     *            the column for filter option for this list. Set "" to use the list definition default.
     * @param String $ofvalue
     *            the value for filter option for this list. Set "" to use the list definition default.
     * @param int $maxrows
     *            the maximum number of rows to be fetched. Set to -1 to use the list default setting.
     * @return string the correct and complete select sql statement
     */
    private function build_sql_cmd (String $osorts_list, String $ofilter, String $ofvalue, int $maxrows)
    {
        $osl = (strlen($osorts_list) == 0) ? $this->osorts_list : $osorts_list;
        $of = (strlen($ofilter) == 0) ? $this->ofilter : $ofilter;
        $ofv = (strlen($ofvalue) == 0) ? $this->ofvalue : $ofvalue;
        $mxr = ($maxrows == - 1) ? $this->maxrows : $maxrows;
        $limit = ($mxr > 0) ? " LIMIT 0, " . $mxr . "" : "";
        
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
                    if (substr($osort, 0, 1) == '#')
                        $order_by .= "CAST(`" . $this->table_name . "`.`" . substr($osort, 1) .
                                 "` AS UNSIGNED) " . $sortmode;
                    else
                        $order_by .= "`" . $this->table_name . "`.`" . $osort . "`" . $sortmode;
                }
                $order_by = mb_substr($order_by, 0, mb_strlen($order_by) - 1);
            }
        }
        
        // interprete filter
        $where = $this->list_definition["where"];
        if ((count($this->toolbox->users->session_user) > 0) && (strpos($where, "\$mynumber") !== false))
            $where = str_replace("\$mynumber", $this->toolbox->users->session_user["@id"], $where);
        if ((strlen($of) > 0) && (strlen($ofv) > 0)) {
            $where = " WHERE (" . $where . ") AND (`" . $this->table_name . "`.`" . $of . "` LIKE '" .
                     str_replace('*', '%', $ofv) . "')";
        } else {
            $where = " WHERE " . $where;
        }
        
        // assemble SQL-statement
        $sql_cmd = "SELECT ";
        $join_statement = ""; // special case: Inner Join using the
                              // $this->toolbox->users->user_id_field_name
        foreach ($this->columns as $column) {
            // get all columns except compound expressions
            if (! isset($this->compounds[$column])) {
                if (strpos($column, ">") !== false) {
                    // lookup case
                    $id_to_match = explode(">", $column, 2)[0];
                    $remainder = explode(">", $column, 2)[1];
                    $table_to_look_into = explode(".", $remainder, 2)[0];
                    $remainder = explode(".", $remainder, 2)[1];
                    $column_to_use_there = explode("@", $remainder, 2)[0];
                    $id_matched = explode("@", $remainder, 2)[1];
                    $sql_cmd .= "`" . $table_to_look_into . "`.`" . $column_to_use_there . "`, ";
                    $join_statement = " INNER JOIN `" . $table_to_look_into . "` ON `" . $table_to_look_into .
                             "`.`" . $id_matched . "`=`" . $this->table_name . "`.`" . $id_to_match . "` ";
                } else {
                    // direct value
                    $sql_cmd .= "`" . $this->table_name . "`.`" . $column . "`, ";
                }
            }
        }
        $sql_cmd = mb_substr($sql_cmd, 0, mb_strlen($sql_cmd) - 2);
        $sql_cmd .= " FROM `" . $this->table_name . "`" . $join_statement . $where . $order_by . $limit;
        return $sql_cmd;
    }

    /**
     * Return a zip-Downnload-link for this list based on its definition or the provided options.
     * 
     * @param String $osorts_list
     *            the list of sort options using the format [-]column[.[-]column]. Set "" to use the list
     *            definition default.
     * @param String $ofilter
     *            the filter option for this list. Set "" to use the list definition default.
     * @param String $ofvalue
     *            the value for filter option for this list. Set "" to use the list definition default.
     * @param bool $pivot
     *            optional. Set true to get the pivot table instead of the list.
     * @return string html formatted table for web display.
     */
    public function get_zip_link (String $osorts_list, String $ofilter, String $ofvalue, bool $pivot)
    {
        $sort_string = (strlen($osorts_list) == 0) ? "" : "&sort=" . $osorts_list;
        $filter_string = (strlen($ofilter) == 0) ? "" : "&filter=" . $ofilter;
        $fvalue_string = (strlen($ofvalue) == 0) ? "" : "&fvalue=" . $ofvalue;
        $zip_type = ($pivot) ? "2" : "1";
        return "?id=" . $this->list_definition["id"] . "&satz=" . $this->list_set . "&zip=" . $zip_type .
                 $sort_string . $filter_string . $fvalue_string;
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
     * @param bool $short
     *            optional. Set true to get without filter, and download option.
     * @return string html formatted table for web display.
     */
    public function get_html (String $osorts_list, String $ofilter, String $ofvalue, bool $short = false)
    {
        global $dfmt_d, $dfmt_dt;
        if (count($this->list_definition) === 0)
            return "<p>" . i("4t2ytU|Application configuratio...") . "</p>";
        
        $max_rows = 100;
        // build table header
        $list_html = "";
        if (! $short) {
            $list_html .= i("nLel3k|Sort with one click on t...") . " \n";
            if (strlen($this->record_link_col) > 0)
                $list_html .= i("erIeb6|View details and change ...", $this->record_link_col) . "\n";
        }
        $list_html .= "<div style='overflow-x: auto; white-space: nowrap; margin-top:12px; margin-bottom:10px;'>";
        $list_html .= "<table style='border: 2px solid transparent;'><thead><tr>";
        // identify ID-column for change link
        $col = - 1;
        $col_id = - 1;
        
        foreach ($this->columns as $column) {
            // identify ID-column for change link
            $col ++;
            if ((strcasecmp($column, $this->record_link_col) == 0) && (strlen($this->record_link) > 0))
                $col_id = $col;
            $is_lookup = (strpos($column, ">") !== false);
            $is_compound = (isset($this->compounds[$column]));
            $col_short = ($is_lookup) ? substr($column, strpos($column, ">") + 1, 
                    strpos($column, "@") - strpos($column, ">") - 1) : $column;
            $pos_sorts = (isset($osorts_list)) ? strpos($osorts_list, $column) : false;
            if (($pos_sorts !== false) && (($pos_sorts == 0) ||
                     (substr($osorts_list, $pos_sorts - 1, 1) == ".") ||
                     (substr($osorts_list, $pos_sorts - 1, 1) == "-"))) {
                $desc_sort = (substr($osorts_list, $pos_sorts - 1, $pos_sorts) == "-");
                $desc_sort = $desc_sort && ($pos_sorts > 0);
                $ctext = ($desc_sort) ? $col_short . '<br /><b>&nbsp;&nbsp;&#9650;</b> ' : $col_short .
                         '<br /><b>&nbsp;&nbsp;&#9660;</b> ';
                $csort = ($desc_sort) ? $column : '-' . $column;
            } else {
                $ctext = $col_short;
                $csort = $column;
            }
            $ofstring = (strlen($ofilter) > 0) ? "&filter=" . $ofilter . "&fvalue=" . $ofvalue : "";
            $listparameter_str = (isset($_GET["listparameter"])) ? "&listparameter=" . $_GET["listparameter"] : "";
            // Columns with lookup positions can not be sorted.
            if ($is_lookup || $is_compound)
                $list_html .= "<th>" . $ctext . "</th>";
            else
                $list_html .= "<th><a class='table-header' href='?id=" . $this->id . "&satz=" . $this->list_set .
                         "&sort=" . $csort . $ofstring . $listparameter_str . "'>" . $ctext . "</a></th>";
        }
        $list_html .= "</tr></thead><tbody>";
        
        // read list data
        $list_rows = $this->get_rows($osorts_list, $ofilter, $ofvalue, $max_rows + 1);
        $count_of_list_rows = count($list_rows);
        if (! $short)
            $list_html = (($count_of_list_rows > 0) ? "<b>" .
                     (($count_of_list_rows > $max_rows) ? "&gt;&nbsp;" : "") . $max_rows . " " .
                     i("C94hEq|Records found.") . "</b> " : "<b>" . i("KFwEDL|no records found.") . "</b> ") .
                     $list_html;
        
        // display data as table
        for ($i = 0; ($i < $max_rows) && ($i < $count_of_list_rows); $i ++) {
            $row = $list_rows[$i];
            $row_str = "<tr>";
            $data_cnt = 0;
            $set_id = "0";
            foreach ($row as $data) {
                $column = $this->columns[$data_cnt];
                $link_user_id = (strcasecmp($this->table_name, $this->toolbox->users->user_table_name) == 0) && (strcasecmp(
                        $column, $this->toolbox->users->user_id_field_name) == 0);
                $is_record_link_col = (strcasecmp($column, $this->record_link_col) == 0);
                if ($this->data_types[$data_cnt] && (strlen($data) > 0)) {
                    if (strcasecmp($this->data_types[$data_cnt], "d") == 0)
                        $data = date($dfmt_d, strtotime($data));
                    elseif (strcasecmp($this->data_types[$data_cnt], "dt") == 0)
                        $data = date($dfmt_dt, strtotime($data));
                    elseif (strcasecmp($this->data_types[$data_cnt], "f") == 0)
                        $data = str_replace(".", ",", strval($data));
                    elseif (strcasecmp($this->data_types[$data_cnt], "p") == 0)
                        $data = str_replace(".", ",", strval($data * 100)) . "%";
                    elseif (strcasecmp($this->data_types[$data_cnt], "u") == 0)
                        $data = date($dfmt_dt, intval($data));
                }
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
            $list_html .= $row_str;
        }
        $list_html .= "</tbody></table></div>\n";
        if ($count_of_list_rows > $max_rows) {
            $list_html .= " " . i("ZmqZlm|List capped after %1 rec...", $max_rows);
            if (! $short)
                $list_html .= " " . i("1ci7d1|Please use the filter or...");
        }
        
        // provide zip-Link
        $reference_this_page = $this->get_zip_link($osorts_list, $ofilter, $ofvalue, false);
        
        // provide filter form
        if (! $short) {
            $list_html .= "<form action='" . $reference_this_page . "'>" . i("Rhz5mZ|Filter in column:") .
                     "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
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
            $list_html .= "<br>" . i("afDbIr|Value ") .
                     " <input type='text' name='fvalue' class='forminput' value='" . $ofvalue .
                     "'  style='width:19em' />" . "&nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' value='" .
                     i("efjxwi|show filtered list") . "' class='formbutton'/></form>";
            $list_html .= "</p><p>" . i("OrvMhQ|get as csv-download file...") . " <a href='" .
                     $reference_this_page . "'>" . $this->table_name . ".zip</a>";
            $list_html .= "<br><br>" . i("TxBnFe|PLEASE NOTE: Use the inf...") . "</p>";
        }
        return $list_html;
    }

    /**
     * Get the sql-code which is used to retreive the rows. Only for debugging purposes.
     * 
     * @return string
     */
    public function get_sql ()
    {
        return $this->build_sql_cmd($this->osorts_list, $this->ofilter, $this->ofvalue, $this->maxrows);
    }

    /**
     * Provide a list with all data retrieved. Simple database get, no mapping to field names included.
     * 
     * @param String $osorts_list
     *            the list of sort options using the format [-]column[.[-]column]. Set "" to use the list
     *            definition default.
     * @param String $ofilter
     *            the filter option for this list. Set "" to use the list definition default.
     * @param String $ofvalue
     *            the value for filter option for this list. Set "" to use the list definition default.
     * @param int $maxrows
     *            the maximum number of rows to be fetched. omit to use the list default setting.
     * @return array an array of all rows - each row being a normal array itself. To empty array on no match.
     */
    public function get_rows (String $osorts_list = "", String $ofilter = "", String $ofvalue = "", 
            int $maxrows = -1)
    {
        // normal operation
        $osl = (strlen($osorts_list) == 0) ? $this->osorts_list : $osorts_list;
        $of = (strlen($ofilter) == 0) ? $this->ofilter : $ofilter;
        $ofv = (strlen($ofvalue) == 0) ? $this->ofvalue : $ofvalue;
        $mxr = ($maxrows == - 1) ? $this->maxrows : $maxrows;
        
        if (count($this->list_definition) === 0)
            return "<p>" . i("3MjQY3|Application configuratio...") . "</p>";
        
        // try the cache first
        $use_cache = ($this->ocache_seconds > 0);
        $csv = "";
        if ($use_cache) {
            if (! file_exists("../log/cache"))
                mkdir("../log/cache");
            $rows = $this->get_rows_from_cache();
            if ($rows !== false)
                return $rows;
            // cache could not be used. Clear it.
            $cache_file = "../log/cache/" . $this->list_set . "." . $this->id;
            if (file_exists($cache_file . ".desc"))
                unlink($cache_file . ".desc");
            if (file_exists($cache_file . ".csv"))
                unlink($cache_file . ".csv");
            // Build table header for cache refresh
            foreach ($this->columns as $column)
                $csv .= str_replace('"', '""', $column) . ";";
            $csv = mb_substr($csv, 0, mb_strlen($csv) - 1);
        }
        
        // assemble SQL-statement and read data
        $sql_cmd = $this->build_sql_cmd($osl, $of, $ofv, $mxr);
        $has_compounds = (count($this->compounds) > 0);
        $res = $this->socket->query($sql_cmd, $this);
        if ($res === false)
            return [[$this->socket->get_error()
            ]
            ];
        $rows = [];
        $firstofblock_filter = ($this->firstofblock_col >= 0);
        $lastfirstvalue = null;
        if (isset($res->num_rows) && (intval($res->num_rows) > 0)) {
            $row = $res->fetch_row();
            while ($row) {
                $filtered = ($firstofblock_filter && ! is_null($lastfirstvalue) &&
                         (strcmp(strval($row[$this->firstofblock_col]), $lastfirstvalue) == 0));
                if (! $filtered) {
                    $rows[] = ($has_compounds) ? $this->build_compounds($row) : $row;
                    if ($firstofblock_filter)
                        $lastfirstvalue = strval($row[$this->firstofblock_col]);
                }
                if ($use_cache && ! $filtered) {
                    $row_str = "";
                    foreach ($row as $data) {
                        if ((strpos($data, "\"") !== false) || (strpos($data, "\n") !== false) ||
                                 (strpos($data, ";") !== false))
                            $row_str .= '"' . str_replace('"', '""', $data) . '";';
                        else
                            $row_str .= $data . ";";
                    }
                    $row_str = mb_substr($row_str, 0, mb_strlen($row_str) - 1);
                    $csv .= "\n" . $row_str;
                }
                $row = $res->fetch_row();
            }
        }
        
        // TODO permissions check. Remove caching.
        
        if ($use_cache) {
            file_put_contents($cache_file . ".csv", $csv);
            file_put_contents($cache_file . ".desc", 
                    json_encode(
                            ["table" => $this->table_name,"retrieved" => time()
                            ]));
        }
        return $rows;
    }

    /**
     * Get the index of a field within a row of the list.
     * 
     * @param String $field_name
     *            name of the field to identify
     * @return number|boolean the index, if matched, else false.
     */
    public function get_field_index (String $field_name)
    {
        $i = 0;
        foreach ($this->columns as $column_name) {
            if (strcasecmp($field_name, $column_name) == 0)
                return $i;
            $i ++;
        }
        return false;
    }

    /**
     * Identify the fields of the row by naming the $row array, e.g. from "array(2) { [0]=> string(1) "6"
     * [1]=> string(4) "1111" }" to "array(2) { ["ID"]=> string(1) "6" ["Besuchernummer"]=> string(4) "1111"
     * }"
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
     * Provide a csv file, filter and sorting according to default. csv data content is UTF-8 encoded - i. e.
     * uses the data as they are provided by the data base.
     * 
     * @param array $verified_user
     *            The user to whom this list was provided. For logging and access control.
     * @param array $only_first_of
     *            If this value is set to a valid column name, only those rows are returned for which this
     *            column value differs from the row before. Used for versionized tables to get the most recent
     *            only.
     * @return a csv String. False on all errors.
     */
    public function get_csv (array $verified_user, $only_first_of = null)
    {
        if (count($this->list_definition) === 0)
            return false;
        $csv = "";
        $check_col_pos = - 1;
        $c = 0;
        // build table header
        foreach ($this->columns as $column) {
            // pass the first only for a specific id, remember the column index to check.
            if (! is_null($only_first_of) && (strcasecmp($column, $only_first_of) == 0))
                $check_col_pos = $c;
            $csv .= str_replace('"', '""', $column) . ";";
            $c ++;
        }
        if (! $csv)
            return false;
        
        $csv = mb_substr($csv, 0, mb_strlen($csv) - 1) . "\n";
        // assemble SQL-statement and read data
        $rows = $this->get_rows();
        $last_checked = null;
        $use_entry_size_limit = ($this->entry_size_limit > 0);
        $entry_size_limit = ($this->entry_size_limit < 10) ? 10 : $this->entry_size_limit;
        foreach ($rows as $row) {
            $row_str = "";
            $c = 0;
            foreach ($row as $data) {
                if ($use_entry_size_limit && (strlen($data) > $entry_size_limit))
                    $data = substr($data, 0, $entry_size_limit - 3) . "...";
                if ((strpos($data, "\"") !== false) || (strpos($data, "\n") !== false) ||
                         (strpos($data, ";") !== false))
                    $row_str .= '"' . str_replace('"', '""', $data) . '";';
                else
                    $row_str .= $data . ";";
            }
            // pass the first only for a specific id, think of below condition in negative for those
            // dropped.
            if (($check_col_pos != $c) || is_null($last_checked) || (strcmp($data, $last_checked) != 0))
                $csv .= mb_substr($row_str, 0, mb_strlen($row_str) - 1) . "\n";
        }
        return $csv;
    }

    /**
     * Provide a csv file zipped and then base64 encoded, filter and sorting according to default.
     * 
     * @return mixed a base64 encoded zip file.
     */
    public function get_base64 ()
    {
        if (count($this->list_definition) === 0)
            $csv = "[" . i("ZPBjQx|No list definition found...") . "]";
        else
            $csv = $this->get_csv($this->toolbox->users->session_user);
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
        global $dfmt_d, $dfmt_dt;
        if (count($this->list_definition) === 0)
            return "<p>" . i("eG3R6d|Application configuratio...") . "</p>";
        
        $csv = $this->get_csv($this->toolbox->users->session_user);
        
        // add timestamp, source and destination
        $destination = $this->toolbox->users->session_user["@fullname"] . " (" .
                 $this->toolbox->users->session_user["@id"] . ", " .
                 $this->toolbox->users->session_user["Rolle"] . ")";
        $csv .= "\n" . i("JX2kP6|Provided on %1 by %2 to ...", date($dfmt_dt), $_SERVER['HTTP_HOST'], 
                $destination) . "\n";
        $this->toolbox->return_string_as_zip($csv, $this->table_name . ".csv");
    }
}
