<?php

class Efa_config
{

    /**
     * The different efa-configurationparts: config.
     */
    public $config;

    /**
     * The different efa-configurationparts: types.
     */
    public $types;

    /**
     * The common toolbox.
     */
    private $toolbox;

    /**
     * Construct the Util class. This reads the configuration, initilizes the logger and the navigation menu,
     * asf.
     */
    public function __construct (Tfyh_toolbox $toolbox)
    {
        if (! file_exists("../config/efa/configuration.csv"))
            $this->parse_XML();
        
        $config_all_rows = $toolbox->read_csv_array("../config/efa/configuration.csv");
        foreach ($config_all_rows as $config_row) {
            $this->config[$config_row["Name"]] = $config_row["Value"];
        }
        
        $types_all_rows = $toolbox->read_csv_array("../config/efa/types.csv");
        foreach ($types_all_rows as $type_row) {
            if (! isset($this->types[$type_row["Category"]]))
                $this->types[$type_row["Category"]] = [];
            $this->types[$type_row["Category"]][$type_row["Position"]] = $type_row["Type"] . ":" .
                     $type_row["Value"];
        }
    }

    /**
     * Parse a set of xml config files into csv
     */
    public function parse_XML ()
    {
        $config_files = scandir("../config/efa");
        include_once "../classes/tfyh_xml.php";
        $xml = new Tfyh_xml($this->toolbox);
        foreach ($config_files as $config_file) {
            $extension = explode(".", $config_file)[1];
            if (strcasecmp(substr($extension, 0, 4), "efa2") == 0) {
                $xml->read_xml(file_get_contents("../config/efa/" . $config_file), false);
                file_put_contents("../config/efa/" . str_replace($extension, "csv", $config_file), 
                        $xml->get_csv("data", "record"));
            }
        }
    }
}    