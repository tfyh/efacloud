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
 * static class file for the sql definition of efa tables to build and adjust the table layout. 
 */
class Efa_db_layout
{

    /**
     * The version of the data base layout targeted for this efaCloud software release. If the configuration
     * has a different version, The data base layout shall be adjusted during the upgrade procedure. Integer
     * value.
     */
    public static $db_layout_version_target = 12;

    /**
     * The data base layout, to be read from a ../config/db_layout/Vx file. . It is an array of versions,
     * therein elements as associative array of tables, therein as associative array of columns, each column
     * with a definition being a csv-list for 'type;size;null allowed;default;unique;autoincrement'. Default
     * is always filled, the two values "(n.r.)" and "(kein)" stand for "not relevant" (which corresponds to
     * text fields) and "explicitly no default set" for autoincrement fields which must not have a default
     * value.
     */
    private static $db_layout = [];

    /**
     * The sql command to add missing table columns (way #2. Start with adding missing columns, adjust null to
     * 0 and end with adjusting the collumn types.). Definition is e.g.
     * $db_layout[$db_layout_version]["efa2autoincrement"]["ChangeCount"] = "bigint;0;NOT NULL;0",
     */
    public static $sql_add_column_command = "ALTER TABLE `{table}` ADD `{column}` {type} {null} {default};";

    /**
     * A statment to ensure all int / bigint fields have a = rather than NULL as default to simplify
     * comparisons (way #2. Start with adding missing columns, adjust null to 0 and end with adjusting the
     * collumn types.)
     */
    public static $sql_column_null_to_zero_adjustment = "UPDATE `{table}` SET `{column}` = 0 WHERE ISNULL(`{column}`);";

    /**
     * The commands to adjust a specific column to use the correct layout. (way #2. Start with adding missing
     * columns, adjust null to 0 and end with adjusting the collumn types.)
     */
    public static $sql_change_column_command = "ALTER TABLE `{table}` CHANGE `{column}` `{column}` {type} {null} {default};";

    /**
     * Initialize the layout version and return the respective associative array.
     * 
     * @param int $layout_version
     *            the version to get
     */
    public static function db_layout (int $layout_version)
    {
        self::read_layout($layout_version);
        return self::$db_layout[$layout_version];
    }

    /**
     * Read the layout version file
     * 
     * @param int $layout_version
     *            the version to read
     */
    private static function read_layout (int $layout_version)
    {
        if (isset(self::$db_layout[$layout_version]) && is_array(self::$db_layout[$layout_version]))
            return;
        self::$db_layout[$layout_version] = [];
        $layout_definition = file_get_contents("../config/db_layout/V" . $layout_version);
        $layout_def_lines = explode("\n", $layout_definition);
        foreach ($layout_def_lines as $layout_def_line) {
            if ((strlen($layout_def_line) > 0) && (strcasecmp(substr($layout_def_line, 0, 1), "#") != 0)) {
                $table_n_column = explode(";", $layout_def_line, 2)[0];
                $definition = explode(";", $layout_def_line, 2)[1];
                $tablename = explode(".", $table_n_column)[0];
                $columnname = explode(".", $table_n_column)[1];
                if (! isset(self::$db_layout[$layout_version][$tablename]))
                    self::$db_layout[$layout_version][$tablename] = [];
                self::$db_layout[$layout_version][$tablename][$columnname] = $definition;
            }
        }
    }

    /**
     * Write a layout to a layout file. Just for quality control purposes.
     * 
     * @param array $db_layout
     *            the layout to be written. Will be in "../config/db_layout/exported"
     */
    private static function write_layout (array $db_layout)
    {
        $layout_str = "";
        foreach ($db_layout as $table_name => $table_columns) {
            $layout_str .= "\n";
            foreach ($db_layout[$table_name] as $column_name => $column_definition)
                $layout_str .= $table_name . "." . $column_name . ";" . $column_definition . "\n";
        }
        file_put_contents("../config/db_layout/exported", $layout_str);
    }

    /**
     * Return a default which shall replace all null values, when a column changes from NULL-allowed to NOT
     * NULL. That means return true, if the column allows NULL values or a default, if not. If there is no
     * default. return also true.
     * 
     * @param int $db_layout_version
     *            data base layout version to use
     * @param String $tablename
     *            table to use
     * @param String $coluumnname
     *            column to use
     */
    public static function is_null_column_to_update (int $db_layout_version, String $tablename, 
            String $columnname)
    {
        $db_layout = self::db_layout(intval($db_layout_version));
        $definition = explode(";", $db_layout[$tablename][$columnname]);
        $null = strtoupper($definition[2]);
        if (strcmp($null, "NULL") == 0)
            return true;
        $default = $definition[3];
        if ((strcasecmp($default, "(n.r.)") == 0) || (strcasecmp($default, "(kein)") == 0))
            return true;
        return $default;
    }

    /**
     * Build the sql command based on the definition here and the template like self::$sql_add_column_command
     * 
     * @param String $db_layout_version
     *            data base layout version to use
     * @param String $tablename
     *            table to use
     * @param String $coluumnname
     *            column to use
     * @param String $template
     *            template to use
     */
    public static function build_sql_column_command (int $db_layout_version, String $tablename, 
            String $columnname, String $template)
    {
        $db_layout = self::db_layout(intval($db_layout_version));
        $sql_cmd = str_replace("{table}", $tablename, str_replace("{column}", $columnname, $template));
        $definition = explode(";", $db_layout[$tablename][$columnname]);
        $type = strtoupper($definition[0]);
        if (intval($definition[1]) > 0)
            $type .= "(" . $definition[1] . ")";
        $null = strtoupper($definition[2]);
        // notice special cases like TEXT / MEDIUMTEXT (' TABLE `efa2boatdamages` ADD `ecrhis`
        // TEXT(65535) NULL;')
        // or fields without default value (only in efaCloud tables, for autoincrement fields, partner URL,
        // first & last name of efaCloudUser)
        $default = $definition[3];
        $default = (strcasecmp($default, "(n.r.)") == 0) || (strcasecmp($default, "(kein)") == 0) ? "" : "DEFAULT " .
                 $definition[3];
        return str_replace("{type}", $type, 
                str_replace("{null}", $null, str_replace("{default}", $default, $sql_cmd)));
    }

    /**
     * Build the sql command set (multiple commands) to create a table: DROP TABLE, CREATE TABLE (with all
     * columns), ALTER ... ADD UNIQUE, and ALTER ... MODIFY ... AUTO_INCREMENT
     * 
     * @param String $db_layout_version
     *            data base layout version to use
     * @param String $tablename
     *            table to use
     */
    public static function build_sql_add_table_commands (String $db_layout_version, String $tablename)
    {
        $db_layout = self::db_layout(intval($db_layout_version));
        $sql_cmd = "DROP TABLE `" . $tablename . "`; ";
        $sql_cmd = "CREATE TABLE `" . $tablename . "` ( ";
        $columns = self::$db_layout[$db_layout_version][$tablename];
        $uniques = "";
        $autoincrements = "";
        $i = 0;
        $sql_cmds[0] = [""
        ];
        foreach ($columns as $cname => $cdefinition) {
            $definition = explode(";", $cdefinition);
            $type = strtoupper($definition[0]);
            if (intval($definition[1]) > 0)
                $type .= "(" . $definition[1] . ")";
            $type .= " ";
            $null = strtoupper($definition[2]) . " ";
            $default = $definition[3];
            $default = ((strcasecmp($default, "(n.r.)") == 0) || (strcasecmp($default, "(kein)") == 0)) ? "" : "DEFAULT " .
                     $definition[3];
            $sql_cmd .= "`" . $cname . "` " . $type . $null . $default . ", ";
            if (strlen($definition[4]) > 0) {
                $i ++;
                $sql_cmds[$i] = "ALTER TABLE `" . $tablename . "` ADD UNIQUE(`" . $cname . "`)";
            }
            if (strlen($definition[5]) > 0) {
                $i ++;
                $sql_cmds[$i] = "ALTER TABLE `" . $tablename . "` MODIFY `" . $cname .
                         "` INT UNSIGNED NOT NULL AUTO_INCREMENT";
            }
        }
        $sql_cmds[0] = mb_substr($sql_cmd, 0, mb_strlen($sql_cmd) - 2) . ")";
        return $sql_cmds;
    }

    /**
     * Get a layout defintion for use at the API, NOP transaction
     * 
     * @param String $db_layout_version
     *            data base layout version to get
     */
    public static function get_layout (int $db_layout_version)
    {
        $db_layout = self::db_layout(intval($db_layout_version));
        $db_layout_string = "V" . $db_layout_version;
        $db_tables = $db_layout;
        foreach ($db_tables as $tname => $tcolumns) {
            $db_layout_string .= "|T|" . "t:" . $tname;
            foreach ($tcolumns as $cname => $cdef) {
                $db_layout_string .= "|C|" . "c:" . $cname . "=" . str_replace(";", "|", $cdef);
            }
        }
        return $db_layout_string;
    }

    /**
     * Compare the existing data base layout and match to a version
     * 
     * @param int $max_version
     *            the maximum version to check for
     */
    // TODO function since Jan 2023 / 2.3.2_09 obsolete. Remove some day
    private static function compare_db_layout (Tfyh_socket $socket, int $max_version)
    {
        $db_layout_read = [];
        $table_names = $socket->get_table_names();
        foreach ($table_names as $table_name) {
            $db_layout_read[$table_name] = [];
            $column_names = $socket->get_column_names($table_name);
            $column_types = $socket->get_column_types($table_name);
            $cn = 0;
            foreach ($column_names as $column_name) {
                $db_layout_read[$table_name][$column_name] = $column_types[$cn];
                $cn ++;
            }
        }
        
        $not_matching = "";
        for ($v = 1; $v <= $max_version; $v ++) {
            $matched = true;
            $not_matching .= "\n" . i("Ne1yvo|Compare version") . " " . $v . "\n";
            $not_matching .= "--------------------\n";
            $db_layout = self::db_layout($v);
            foreach ($db_layout as $table_name => $table_columns) {
                if (isset($db_layout_read[$table_name])) {
                    foreach ($db_layout[$table_name] as $column_name => $column_definition) {
                        if (! isset($db_layout_read[$table_name][$column_name])) {
                            $not_matching .= i("l9y94L|Missing column %1.%2", $table_name, $column_name) . "\n";
                            $matched = false;
                        }
                    }
                } else {
                    $not_matching .= i("mp2bOE|Missing table %1.", $table_name) . "\n";
                    $matched = false;
                }
            }
            foreach ($db_layout_read as $table_name => $table_columns) {
                if (isset($db_layout[$table_name])) {
                    foreach ($db_layout_read[$table_name] as $column_name => $column_definition) {
                        if (! isset($db_layout[$table_name][$column_name])) {
                            $not_matching .= i("wiUFNS|Additional column %1.%2", $table_name, $column_name) . "\n";
                            $matched = false;
                        }
                    }
                } else {
                    $not_matching .= i("uflrtX|Additional table %1.", $table_name) . "\n";
                    $matched = false;
                }
            }
            if ($matched)
                return $v;
        }
        file_put_contents("../log/db_layout_check.log", $not_matching);
        return i("Vpmha6|No matching version foun...") .
                 " '../log/db_layout_check.log'";
    }
}
    
