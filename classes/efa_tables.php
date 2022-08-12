<?php

/**
 * class file for the specific handling of eFa tables, e. g. GUID generation, autoincrementation etc.
 */
class Efa_tables
{

    /**
     * The tables for which a key fixing is allowed
     */
    private $fixid_allowed = "efa2logbook efa2messages efa2boatdamages efa2boatreservations";

    /**
     * The 'base64' encoding map for the ecrid.
     */
    private static $ecmap = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789*-";

    /**
     * Debug level to add mor information for support cases.
     */
    private $debug_on;

    /**
     * the transaction warnings log
     */
    public $api_debug_log_path = "../log/debug_api.log";

    /**
     * maximum number of records per select chunk to avoid memory shortage
     */
    public static $select_chunk_size = 500;

    /**
     * The field which shall be autoincremented for tables for which a key fixing is allowed. MUST BE AN
     * INTEGER NUMBER.
     */
    public $fixid_auto_field = ["efa2logbook" => "EntryId","efa2messages" => "MessageId",
            "efa2boatdamages" => "Damage","efa2boatreservations" => "Reservation"
    ];

    /**
     * The list of timestamp fields.
     */
    public $timestampFields = ["LastModified","ValidFrom","InvalidFrom"
    ];

    /**
     * String denominating "forever" in efa (java.long.MAX_VALUE)
     */
    public $forever64 = "9223372036854775807";

    /**
     * Integer to check a validity. If strlen($value) > $forever_len_gt, this is for ever valid.
     */
    public $forever_len_gt = 13;

    /**
     * 32-bit integer denominating "forever" in efaCloud (2^31 - 1)
     */
    public $forever32 = 2147483647;

    /**
     * The data base connection socket.
     */
    public $socket;

    /**
     * The version code for the data base layout. If $db_layout_version >= 2 this reflects the server's
     * capability to use efaCLoud record management. Do not mix up with the API version.
     */
    public $db_layout_version;

    /**
     * The version of the data base layout targeted for this efaCloud software release. If the configuration
     * has a different version, The data base layout shall be adjusted during the upgrade procedure. Integer
     * value.
     */
    public $db_layout_version_target = 5;

    /**
     * Column names of those columns that represent the data key of the specific efa2 table. These key fields
     * are the same as in the efa client. One exception: efa2logbook with the additional Logbookname key
     * field. Within the client each year has a separate logbook with EntryIds starting from 1 at the first of
     * January. The server uses just one table with an additional data field Logbookname. The server side key
     * therefore is the EntryId plus the Logbookname.
     */
    public static $key_fields = ["efa2autoincrement" => ["Sequence" // block #1 single field keys
    ],"efa2boatstatus" => ["BoatId"
    ],"efa2clubwork" => ["Id" // because this is a UUID it is unique for all club workbooks
    ],"efa2crews" => ["Id"
    ],"efa2fahrtenabzeichen" => ["PersonId"
    ],"efa2logbook" => ["EntryId","Logbookname" // ADAPTATION, @efa: EntryId only
    ],"efa2messages" => ["MessageId"
    ],"efa2sessiongroups" => ["Id"
    ],"efa2statistics" => ["Id"
    ],"efa2status" => ["Id"
    ],"efa2waters" => ["Id"
    ],
            // block #2 double field keys: numeric plus BoatId
            "efa2boatdamages" => ["BoatId","Damage"
            ],"efa2boatreservations" => ["BoatId","Reservation"
            ],
            // block #3 versionized tables: Id plus ValidFrom key fields.
            "efa2boats" => ["Id","ValidFrom"
            ],"efa2destinations" => ["Id","ValidFrom"
            ],"efa2groups" => ["Id","ValidFrom"
            ],"efa2persons" => ["Id","ValidFrom"
            ]
    ];

    /**
     * Column names of those columns that represent a short version of the human readable record of the
     * specific table. Used in record find dialog.
     */
    public static $short_info_fields = 
    // efaCloud server tables
    ["efaCloudUsers" => ["Vorname","Nachname","efaAdminName"
    ],
            // efa tables
            "efa2autoincrement" => ["Sequence","IntValue","LongValue"
            ],
            "efa2boats" => ["Name","TypeSeats","TypeRigging","TypeType","Id","ValidFrom","InvalidFrom"
            ],"efa2boatdamages" => ["Damage","BoatId","Description","Severity","Fixed"
            ],"efa2boatreservations" => ["Reservation","BoatId","Reason","DateFrom","DateTo"
            ],"efa2boatstatus" => ["BoatText","Comment"
            ],"efa2clubwork" => ["Date","FirstLastName","Hours","Description","Id"
            ],"efa2crews" => ["Name"
            ],"efa2destinations" => ["Name","Distance","Id","ValidFrom","InvalidFrom"
            ],"efa2fahrtenabzeichen" => ["GUI_VORNAME","GUI_NACHNAME","GUI_LETZTESDATUM"
            ],"efa2groups" => ["Name","Id","ValidFrom","InvalidFrom"
            ],
            "efa2logbook" => ["Logbookname","EntryId","Date","BoatId","BoatName","AllCrewNames",
                    "DestinationId","DestinationName","Distance"
            ],"efa2messages" => ["From","Date","Subject"
            ],
            "efa2persons" => ["FirstName","LastName","MembershipNo","Id","ValidFrom","InvalidFrom"
            ],"efa2sessiongroups" => ["Name","Logbook","Route","StartDate","EndDate","Id"
            ],"efa2statistics" => ["Name","PubliclyAvailable","Id"
            ],"efa2status" => ["Name","Type","AutoSetOnAge","Id"
            ],"efa2waters" => ["Name","Details","Id"
            ]
    ];

    /**
     * Column names of those columns that represent the human readable name of a record of the specific table.
     * The name shall be created by appending all fields, separated by a blank (' ').
     */
    public static $name_fields = 
    // efaCloud server tables
    ["efaCloudUsers" => ["efacloudUserID","Vorname","Nachname","efaAdminName"
    ],
            // efa tables
            "efa2boats" => ["Name",""
            ],"efa2boatdamages" => ["Description"
            ],"efa2boatreservations" => ["Reason"
            ],"efa2boatstatus" => ["BoatText"
            ],"efa2clubwork" => ["Description"
            ],"efa2crews" => ["Name"
            ],"efa2destinations" => ["Name"
            ],"efa2fahrtenabzeichen" => ["GUI_VORNAME","GUI_NACHNAME"
            ],"efa2groups" => ["Name"
            ],"efa2logbook" => ["Logbookname","EntryId"
            ],"efa2messages" => ["Subject"
            ],"efa2persons" => ["FirstName","LastName"
            ],"efa2sessiongroups" => ["Name"
            ],"efa2statistics" => ["Name"
            ],"efa2status" => ["Name"
            ],"efa2waters" => ["Name"
            ]
    ];

    /**
     * a list of all fields of boolean data type. Needed for the datensatz_aendern.php form.
     */
    public static $boolean_fields = ["efa2boatdamages" => ["Fixed","Claim"
    ],"efa2boats" => ["OnlyWithBoatCaptain","ExcludeFromStatistics","Invisible","Deleted"
    ],"efa2boatstatus" => ["UnknownBoat"
    ],"efa2destinations" => ["StartIsBoathouse","Roundtrip","Invisible","Deleted"
    ],"efa2groups" => ["Invisible","Deleted"
    ],"efa2logbook" => ["Open"
    ],"efa2messages" => ["Read","ToBeMailed"
    ],
            "efa2persons" => ["Disability","ExcludeFromStatistics","ExcludeFromCompetition",
                    "ExcludeFromClubwork","BoatUsageBan","Invisible","Deleted"
            ],
            "efa2statistics" => ["PubliclyAvailable","FilterGenderAll",".FilterStatusAll",
                    "FilterSessionTypeAll","FilterBoatTypeAll","FilterBoatSeatsAll","FilterBoatRiggingAll",
                    "FilterBoatCoxingAll","FilterBoatOwnerAll","FilterPromptPerson","FilterPromptBoat",
                    "FilterPromptGroup","FilterFromToBoathouse","FilterOnlyOpenDamages",
                    "FilterAlsoOpenSessions","CompOutputShort","CompOutputRules",
                    "CompOutputAdditionalWithRequirements","CompOutputWithoutDetails",
                    "CompOutputAllDestinationAreas","OutputHtmlUpdateTable","OptionDistanceWithUnit",
                    "OptionTruncateDistance","OptionListAllNullEntries","OptionIgnoreNullValues",
                    "OptionOnlyMembersWithInsufficientClubwork","OptionSumGuestsAndOthers",
                    "OptionSumGuestsByClub"
            ],"efa2status" => ["AutoSetOnAge"
            ]
    ];

    /**
     * All fields which have INT or BIGINT dataty and must not be set to null or "".
     */
    public static $int_fields = [
            "efa2autoincrement" => ["IntValue","LongValue","ChangeCount","LastModified"
            ],"efa2boatdamages" => ["Damage","ChangeCount","LastModified","ecrown"
            ],"efa2boatreservations" => ["Reservation","ChangeCount","LastModified","ecrown"
            ],
            "efa2boats" => ["LastVariant","DefaultVariant","MaxNotInGroup","MaxCrewWeight","ValidFrom",
                    "InvalidFrom","ChangeCount","LastModified","ecrown"
            ],"efa2boatstatus" => ["ChangeCount","LastModified","ecrown"
            ],"efa2clubwork" => ["Flag","ChangeCount","LastModified","ecrown"
            ],"efa2crews" => ["BoatCaptain","ChangeCount","LastModified","ecrown"
            ],
            "efa2destinations" => ["PassedLocks","ValidFrom","InvalidFrom","ChangeCount","LastModified",
                    "ecrown"
            ],
            "efa2fahrtenabzeichen" => ["Abzeichen","AbzeichenAB","Kilometer","KilometerAB","ChangeCount",
                    "LastModified","ecrown"
            ],"efa2groups" => ["ValidFrom","InvalidFrom","ChangeCount","LastModified","ecrown"
            ],
            "efa2logbook" => ["EntryId","BoatVariant","BoatCaptain","EfbSyncTime","ChangeCount",
                    "LastModified","ecrown"
            ],"efa2messages" => ["MessageId","ChangeCount","LastModified","ecrown"
            ],"efa2persons" => ["ValidFrom","InvalidFrom","ChangeCount","LastModified","ecrown"
            ],"efa2sessiongroups" => ["ActiveDays","ChangeCount","LastModified","ecrown"
            ],
            "efa2statistics" => ["Position","AggregationDistanceBarSize","AggregationRowDistanceBarSize",
                    "AggregationCoxDistanceBarSize","AggregationSessionsBarSize",
                    "AggregationAvgDistanceBarSize","AggregationDurationBarSize","AggregationSpeedBarSize",
                    "CompYear","CompPercentFulfilled","ChangeCount","LastModified"
            ],
            "efa2status" => ["Membership","MinAge","MaxAge","ChangeCount","LastModified","ecrown"
            ],"efa2waters" => ["ChangeCount","LastModified","ecrown"
            ]
    
    ];

    /**
     * All data fields which are generated by the efacloud server, if they are not provided by the record
     * sender (API [efa], APIV3 [efaWeb, efaApp], Server UI, Server import). Shall always be read only for any
     * UI access. Includes key fields, except ValidFrom, Logbooknmame, BoatId.
     */
    public static $server_gen_fields = ["efa2autoincrement" => ["LastModification"
    ],"efa2autoincrement" => [],
            "efa2boatdamages" => ["Damage","ChangeCount","LastModified","LastModification",
                    "ClientSideKey","ecrid","ecrown","ecrhis"
            ],
            "efa2boatreservations" => ["Reservation","ChangeCount","LastModified","LastModification",
                    "ClientSideKey","ecrid","ecrown","ecrhis"
            ],
            "efa2boats" => ["Id","ChangeCount","LastModified","LastModification","ecrid","ecrown",
                    "ecrhis"
            ],
            "efa2boatstatus" => ["ChangeCount","LastModified","LastModification","ecrid","ecrown"
            ],
            "efa2clubwork" => ["Id","ChangeCount","LastModified","LastModification","ecrid","ecrown",
                    "ecrhis"
            ],
            "efa2crews" => ["Id","ChangeCount","LastModified","LastModification","ecrid","ecrown"
            ],
            "efa2destinations" => ["Id","ChangeCount","LastModified","LastModification","ecrid","ecrown"
            ],
            "efa2fahrtenabzeichen" => ["ChangeCount","LastModified","LastModification","ecrid","ecrown",
                    "ecrhis"
            ],
            "efa2groups" => ["Id","ChangeCount","LastModified","LastModification","ecrid","ecrown"
            ],
            "efa2logbook" => ["AllCrewIds","ChangeCount","LastModified","LastModification",
                    "ClientSideKey","ecrid","ecrown","ecrhis"
            ],
            "efa2messages" => ["MessageId","ChangeCount","LastModified","LastModification",
                    "ClientSideKey","ecrid","ecrown"
            ],
            "efa2persons" => ["Id","FirstLastName","ChangeCount","LastModified","LastModification",
                    "ecrid","ecrown","ecrhis"
            ],
            "efa2sessiongroups" => ["Id","ChangeCount","LastModified","LastModification","ecrid","ecrown",
                    "ecrhis"
            ],"efa2statistics" => ["Id","ChangeCount","LastModified","LastModification","ecrid"
            ],
            "efa2status" => ["Id","ChangeCount","LastModified","LastModification","ecrid","ecrown"
            ],
            "efa2waters" => ["Id","ChangeCount","LastModified","LastModification","ecrid","ecrown"
            ]
    ];

    /**
     * All data fields which are generated by the efa PC-client by default.
     */
    public static $api_client_gen_fields = [
            "efa2autoincrement" => ["Sequence","IntValue","LongValue","ChangeCount","LastModified"
            ],"efa2boatdamages" => ["Damage","ChangeCount","LastModified"
            ],
            "efa2boatreservations" => ["VirtualBoat","Reservation","VirtualReservationDate",
                    "VirtualPerson","ChangeCount","LastModified"
            ],"efa2boats" => ["Id","ChangeCount","LastModified"
            ],"efa2boatstatus" => ["CurrentStatus","EntryNo","ChangeCount","LastModified"
            ],
            "efa2clubwork" => ["Id","FirstLastName","Clubworkbookname","ChangeCount","LastModified"
            ],"efa2crews" => ["Id","ChangeCount","LastModified"
            ],"efa2destinations" => ["Id","ChangeCount","LastModified"
            ],"efa2fahrtenabzeichen" => ["ChangeCount","LastModified"
            ],"efa2groups" => ["Id","ChangeCount","LastModified"
            ],
            "efa2logbook" => ["AllCrewNames","AllCrewIds","Logbookname","ChangeCount","LastModified"
            ],"efa2messages" => ["MessageId","ChangeCount","LastModified"
            ],"efa2persons" => ["Id","FirstLastName","ChangeCount","LastModified"
            ],"efa2sessiongroups" => ["Id","ChangeCount","LastModified"
            ],"efa2statistics" => ["Id","ChangeCount","LastModified"
            ],"efa2status" => ["Id","ChangeCount","LastModified"
            ],"efa2waters" => ["Id","ChangeCount","LastModified"
            ]
    ];

    /**
     * All data fields which are generated by the efa client API V3 and subsequent by default.
     */
    public static $apiV3_client_gen_fields = [
            "efa2autoincrement" => ["Sequence","IntValue","LongValue","ecrid"
            ],"efa2boatdamages" => ["ecrid"
            ],"efa2boatreservations" => ["VirtualReservationDate","VirtualPerson","ecrid"
            ],"efa2boats" => ["Id","ecrid"
            ],"efa2boatstatus" => ["CurrentStatus","EntryNo","ecrid"
            ],"efa2clubwork" => ["Id","FirstLastName","Clubworkbookname","ecrid"
            ],"efa2crews" => ["Id","ecrid"
            ],"efa2destinations" => ["Id","ecrid"
            ],"efa2fahrtenabzeichen" => ["ecrid"
            ],"efa2groups" => ["Id","ecrid"
            ],"efa2logbook" => ["AllCrewNames","AllCrewIds","Logbookname","ecrid"
            ],"efa2messages" => ["ecrid"
            ],"efa2persons" => ["Id","FirstLastName","ecrid"
            ],"efa2sessiongroups" => ["Id","ecrid"
            ],"efa2statistics" => ["Id","ecrid"
            ],"efa2status" => ["Id","ecrid"
            ],"efa2waters" => ["Id","ecrid"
            ]
    ];

    /**
     * A list of all efa2 table names.
     */
    public $efa2tablenames = ["efa2autoincrement","efa2boatdamages","efa2boatreservations","efa2boats",
            "efa2boatstatus","efa2clubwork","efa2crews","efa2destinations","efa2fahrtenabzeichen","efa2groups",
            "efa2logbook","efa2messages","efa2persons","efa2sessiongroups","efa2statistics","efa2status",
            "efa2waters"
    ];

    /**
     * A list of the four verionized tables
     */
    public $is_versionized = ["efa2boats","efa2destinations","efa2groups","efa2persons"
    ];

    /**
     * The generic toolbox.
     */
    private $toolbox;

    /**
     * public Constructor.
     * 
     * @param Tfyh_toolbox $toolbox            
     * @param Tfyh_socket $socket            
     */
    public function __construct (Tfyh_toolbox $toolbox, Tfyh_socket $socket)
    {
        $this->socket = $socket;
        $this->toolbox = $toolbox;
        $cfg = $toolbox->config->get_cfg();
        $this->db_layout_version = (isset($cfg["db_layout"])) ? intval($cfg["db_layout"]) : 1;
        $this->debug_on = $toolbox->config->debug_level > 0;
        include_once "../classes/efa_audit.php";
    }

    /* --------------------------------------------------------------------------------------- */
    /* ------------------ WRITE DATA TO EFACLOUD - HELPER FUNCTIONS -------------------------- */
    /* --------------------------------------------------------------------------------------- */
    
    /**
     * Checks whether the table name starts with efa2
     * 
     * @param String $tablename
     *            name of table to check
     * @return boolean true, if the table name starts with efa2.
     */
    public function is_efa_table (String $tablename)
    {
        return (strpos($tablename, "efa2") === 0);
    }

    /**
     * Format the 32-bit validity value into a human readable date.
     * 
     * @param int $validity32
     *            the 32-bit validity value: 0 = undefined, 2147483647 = forever valid, other until date
     * @return string the 32-bit validity value as a human readable date
     */
    public function format_validity32 (int $validity32)
    {
        if ($validity32 == $this->forever32) {
            return "gültig";
        } elseif ($validity32 > 1) {
            return "bis " . date("d.m.Y", $validity32);
        } else {
            return "nicht definiert";
        }
    }

    /**
     * Format the 32-bit validity value into a human readable date.
     * 
     * @param String $validityStr
     *            the String formatted 64-bit validity value
     * @return int the 32-bit validity value
     */
    public function value_validity32 (String $validityStr)
    {
        if (strlen($validityStr) > $this->forever_len_gt) {
            return $this->forever32;
        } elseif (strlen($validityStr) < 3) {
            return 0;
        } else {
            $validity32str = substr($validityStr, 0, strlen($validityStr) - 3);
            return intval($validity32str);
        }
    }

    /**
     * Format a java long timestamp into a readable date and time
     * 
     * @param String $timestamp            
     */
    public function get_readable_date_time (String $timestamp)
    {
        if (strlen($timestamp) > $this->forever_len_gt)
            return "ewig";
        return date("Y-m-d H:i:s", intval(substr($timestamp, 0, strlen($timestamp) - 3)));
    }

    /**
     * Return the 'client side key' value for a record which will then be used to be stored in a ClientSideKey
     * field or compared to such a field's value. It is a concatenation of all key fields as defined in
     * self::$key_fields[], separated by a "|". The key fields are the same as in the client except for the
     * logbook. Within the client each year has a separate logbook with EntryIds starting from 1 at the first
     * of January. The server uses just one table with an additional data field Logbookname. The client side
     * key is just the EntryId without the Logbookname.
     * 
     * @param String $tablename
     *            name of table to find the record
     * @param array $record
     *            record to find
     * @return mixed the key as value for the ClientSideKey comparison, if for all key fields a value is
     *         provided, else false.
     */
    private function compile_clientSideKey (String $tablename, array $record)
    {
        $matching = $this->get_data_key($tablename, $record);
        if (! $matching || (count($matching) == 0))
            return false;
        if (strcasecmp($tablename, "efa2logbook") == 0)
            return $record["EntryId"];
        $values = "";
        foreach ($matching as $key => $value)
            $values .= $value . "|";
        return substr($values, 0, strlen($values) - 1);
    }

    /**
     * Return the key as defined in self::$key_fields[]
     * 
     * @param String $tablename
     *            name of table to find the record
     * @param String $clientSideKey
     *            the client side key formatted as done by getClientSideKey
     * @return mixed the key as associative array, if for all key fields a value is provided, else false.
     */
    private function get_clientSideKey_array (String $tablename, String $clientSideKey)
    {
        $clientSideKeyArray = [];
        $keys = self::$key_fields[$tablename];
        $clientSideKeyParts = explode("|", $clientSideKey);
        $field_index = 0;
        if (strcasecmp($tablename, "efa2logbook") == 0)
            foreach ($keys as $key) {
                if (isset($clientSideKeyParts[$field_index]))
                    $clientSideKeyArray[$key] = $clientSideKeyParts[$field_index];
                else
                    $clientSideKeyArray[$key] = "";
                $field_index ++;
            }
        return $clientSideKeyArray;
    }

    /**
     * Autoincrement the next free key value for tables which may use key fixing.
     * 
     * @param String $tablename
     *            the table for which the maximum numeric part of the key shall be found
     * @param String $logbookname
     *            the logbookname in case the table is the logbook to look only into the correct split part.
     * @return int next key value to use
     */
    public function autoincrement_key_field (String $tablename, String $logbookname)
    {
        if (! isset($this->fixid_auto_field[$tablename]))
            return 0;
        $field_to_autoincrement = $this->fixid_auto_field[$tablename];
        if (strcasecmp($tablename, "efa2logbook") == 0) {
            $top_records = $this->socket->find_records_sorted_matched($tablename, 
                    ["Logbookname" => $logbookname
                    ], 1, "=", $field_to_autoincrement, false);
        } else {
            // efa2messages efa2boatdamages efa2boatreservations
            $top_records = $this->socket->find_records_sorted_matched($tablename, [], 1, "", 
                    $field_to_autoincrement, false);
        }
        
        $max_value = intval($top_records[0][$field_to_autoincrement]);
        $max_value ++;
        return $max_value;
    }

    /**
     * Get the server record which is matching the provided client record. It may not have the same data key,
     * if a key mismatch was not yet fixed and the record has no efaCloud record Id. The typical client record
     * will not contain such an efaCloud record Id.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param String $tablename
     *            the table into which the record shall be imported.
     * @param array $client_record
     *            client record which shall be matched.
     * @param String $tablename            
     * @return the matching server record. False, if no match was found.
     */
    private function get_corresponding_server_record (array $client_verified, String $tablename, 
            array $client_record)
    {
        $server_record_key = $this->get_data_key($tablename, $client_record);
        if (! $server_record_key)
            return false;
        $efaCloudUserID = $client_verified[$this->toolbox->users->user_id_field_name];
        // find the record using directly the provided key, if either no key fixing was allowed, or if an
        // efaCloud record Id is provided
        if ((strpos($this->fixid_allowed, $tablename) === false) ||
                 (array_key_exists("ecrid", $client_record) && (strlen($client_record["ecrid"]) > 5)))
            return $this->socket->find_record_matched($tablename, $server_record_key);
        
        // if keyfixing is allowed and no efaCloud record Id is provided, get all records which need fixing
        // from this client. Note that not more than a couple of keys shall be with a key to fix.
        $records_to_fix = $this->socket->find_records_sorted_matched($tablename, 
                ["ClientSideKey" => "%" . $efaCloudUserID . ":%"
                ], 100, "LIKE", false, true, false);
        // none found which needs fixing, so return the record using the provided key
        if (! $records_to_fix)
            return $this->socket->find_record_matched($tablename, $server_record_key);
        
        // some found which need fixing. See whether one of those has the client record's key cached as
        // ClientSideKey
        $client_record_key_for_caching = $efaCloudUserID . ":" .
                 $this->compile_clientSideKey($tablename, $client_record);
        // if so, return this record which still needs fixing
        foreach ($records_to_fix as $record_to_fix)
            if (strcmp($record_to_fix["ClientSideKey"], $client_record_key_for_caching) == 0)
                return $record_to_fix;
        // if not, find the record using the provided key
        return $this->socket->find_record_matched($tablename, $server_record_key);
    }

    /**
     * Get the next mismatching key in this table for that client. For both insert and keyfixing requests the
     * server has to return an appropriate data key which shall be fixed. It is mandatory that this new data
     * key is not in use at the client side, because if so, the insertion of the fixed record at the client
     * side will fail. Now the set of client side keys is the set of keys of all records without key mismatch
     * plus the set of mismatching client side keys. To find a free client side key you need to iterate
     * through the server side keys until you’ve found one, which is not in set of client side keys. Because
     * all synchronous keys are part of both sets, they can be left out. Said that, a free client side key can
     * be detected at the server by iterating through the set of all server side keys of mismatching records
     * until one is detected which is not in the set of mismatching client side keys.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param array $tablename
     *            the table out of which the record shall be updated.
     * @param String $tablename            
     * @param String $api_log_path
     *            path to write the keyfixing activities to
     * @return the csv table with the new rtecord and the current key as it shall be returned to the client.
     *         False, if no further key mismatch exists for this table
     */
    private function get_next_key_to_fix (array $client_verified, String $tablename, String $api_log_path)
    {
        // get all records which need fixing from this client
        // Note that the mismatching client side key of those always contains at least one ":"
        $client_key_filter = "%" . $client_verified[$this->toolbox->users->user_id_field_name] . ":%";
        // Note that not more than a couple of keys shall be with a key to fix.
        $mismatching_server_side_records = $this->socket->find_records_sorted_matched($tablename, 
                ["ClientSideKey" => $client_key_filter
                ], 100, "LIKE", false, true, false);
        if (! $mismatching_server_side_records)
            return false;
        
        file_put_contents($api_log_path, 
                "[" . date("Y-m-d H:i:s") . "] - Transaction: " . count($mismatching_server_side_records) .
                         " mismatching_server_side_records.\n", FILE_APPEND);
        
        // collect mismatching keys
        $mismatching_client_side_keys = [];
        $mismatching_server_side_records_plus = []; // add the server side key for later use
        foreach ($mismatching_server_side_records as $mismatching_server_side_record) {
            // compile the data key of the record to fix
            $mismatching_server_side_key = $this->compile_clientSideKey($tablename, 
                    $mismatching_server_side_record);
            file_put_contents($api_log_path, 
                    "[" . date("Y-m-d H:i:s") . "] - Transaction: mismatching_server_side_key = " .
                             json_encode($mismatching_server_side_key) . "\n", FILE_APPEND);
            $mismatching_client_side_key_pair = explode(":", $mismatching_server_side_record["ClientSideKey"]);
            file_put_contents($api_log_path, 
                    "[" . date("Y-m-d H:i:s") . "] - Transaction: mismatching_client_side_key_pair = " .
                             json_encode($mismatching_client_side_key_pair) . "\n", FILE_APPEND);
            $mismatching_client_side_keys[] = $mismatching_client_side_key_pair[1];
            // add the comparable server side key to the record for later usage
            $mismatching_server_side_record["ServerSideKey"] = $this->compile_clientSideKey($tablename, 
                    $mismatching_server_side_record);
            $mismatching_server_side_records_plus[] = $mismatching_server_side_record;
        }
        
        // find a mismatching record with server side key which is not yet used on the client side.
        $free_at_client = false;
        foreach ($mismatching_server_side_records_plus as $mismatching_server_side_record) {
            $used_at_client = false;
            if (! $free_at_client)
                foreach ($mismatching_client_side_keys as $mismatching_client_side_key) {
                    if (strcmp($mismatching_client_side_key, $mismatching_server_side_record["ServerSideKey"]) ==
                             0)
                        $used_at_client = true;
                }
            if (! $used_at_client)
                $free_at_client = $mismatching_server_side_record;
        }
        
        // No such record was identified. That actually shall never happen. If so, abort fixing.
        if ($free_at_client === false) {
            file_put_contents($api_log_path, 
                    "[" . date("Y-m-d H:i:s") .
                             "] - Transaction: could not find free key to use at client side for key correction. key correction aborted.\n", 
                            FILE_APPEND);
            return "";
        }
        
        file_put_contents($api_log_path, 
                "[" . date("Y-m-d H:i:s") . "] - Transaction: Key value '" . $free_at_client["ServerSideKey"] .
                         "' is detected to be free_at_client.\n", FILE_APPEND);
        
        // provide the record to delete at the client side and the record to insert instead.
        $record_to_delete_key = $free_at_client["ClientSideKey"];
        unset($free_at_client["ServerSideKey"]);
        
        // write csv header
        $ret = "";
        $exclude_server_side_only_fields = "ClientSideKey LastModification";
        foreach ($free_at_client as $key => $value)
            if (strpos($exclude_server_side_only_fields, $key) === false)
                $ret .= $key . ";";
        $ret = substr($ret, 0, strlen($ret) - 1) . "\n";
        
        // write values of the record to be inserted
        foreach ($free_at_client as $key => $value)
            if (strpos($exclude_server_side_only_fields, $key) === false)
                $ret .= $this->toolbox->encode_entry_csv($value) . ";";
        $ret = substr($ret, 0, strlen($ret) - 1) . "\n";
        
        // replace the key of the new record by the current key.
        $record_to_delete_key_pair = explode(":", $record_to_delete_key);
        $record_to_delete_key_array = $this->get_clientSideKey_array($tablename, 
                $record_to_delete_key_pair[1]);
        foreach ($record_to_delete_key_array as $key => $current_value)
            $free_at_client[$key] = $current_value;
        
        // Write the new record with the current key for deletion. Although only key values are used
        // for deletion, the full set of values is written to stick to the csv table layout
        foreach ($free_at_client as $key => $value)
            if (strpos($exclude_server_side_only_fields, $key) === false)
                $ret .= $this->toolbox->encode_entry_csv($value) . ";";
        $ret = substr($ret, 0, strlen($ret) - 1);
        
        // Return new record and current key csv string.
        return $ret;
    }

    /* --------------------------------------------------------------------------------------- */
    /* ------------------ WRITE DATA TO EFACLOUD - PUBLIC FUNCTIONS -------------------------- */
    /* --------------------------------------------------------------------------------------- */
    
    /**
     * Compile a AllCrewIds String based on the Cox and Crew of a logbook record
     * 
     * @param array $record
     *            the logbook record to use.
     * @return string the AllCrewIds field
     */
    public function create_AllCrewIds_field (array $record)
    {
        $allCrewIds = (isset($record["CoxId"]) && (strlen($record["CoxId"]) > 0)) ? $record["CoxId"] . "," : "";
        for ($i = 1; $i <= 24; $i ++) {
            $fId = "Crew" . $i . "Id";
            $allCrewIds .= (isset($record[$fId]) && (strlen($record[$fId]) > 0)) ? $record[$fId] . "," : "";
        }
        if (strlen($allCrewIds) > 0)
            $allCrewIds = substr($allCrewIds, 0, strlen($allCrewIds) - 1);
        return $allCrewIds;
    }

    /**
     * Insert a record into a table using the API syntax and return the result as
     * "<result_code>;<result_message>". Set the LastModified and LastModification values, if not yet set.
     * Generate an efaCloud record Id, if efaCloud record management is enabled at the server side and no
     * efaCloud record Id is provided in the record to insert.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param String $tablename
     *            the table into which the record shall be imported.
     * @param array $record
     *            record which shall be inserted.
     * @param String $api_log_path
     *            path to write the keyfixing activities to
     * @return the api result-code and result
     */
    public function api_insert (array $client_verified, String $tablename, array $record, String $api_log_path)
    {
        if ($this->debug_on)
            file_put_contents($this->api_debug_log_path, 
                    date("Y-m-d H:i:s") . ": starting api_insert for client " .
                             $client_verified[$this->toolbox->users->user_id_field_name] . " at table " .
                             $tablename . ".\n", FILE_APPEND);
        // Check provided key and existing record. Error: 502 => "Transaction failed."
        $key = $this->get_data_key($tablename, $record);
        // There must be a complete data key to insert, because if not, the record can not be identified
        // afterwards and will be lost in the data base
        if ($key === false)
            return "502;Cannot insert record, key is incomplete or missing.";
        // There may be no ecrid in the record, even if the server has efaCloud record management enabled,
        // which happens at insert requests of clients which have no efaCloud record management. Generate the
        // ecrid in that case and add it to the record.
        if (! array_key_exists("ecrid", $record) || (strlen($record["ecrid"]) <= 5)) {
            $new_ecrids = self::generate_ecrids(1);
            $record["ecrid"] = $new_ecrids[0];
        }
        $efaCloudUserID = $client_verified[$this->toolbox->users->user_id_field_name];
        
        // It is checked, whether the key is used. If so, an error is returned, except for the
        // tables in which keys can be fixed.
        $record_matched = $this->socket->find_record_matched($tablename, $key);
        $key_was_modified = false;
        if ($record_matched !== false) {
            // restore ecrid
            $record["ecrid"] = $record_matched["ecrid"];
            // change owner
            if (in_array("ecrown", Efa_tables::$server_gen_fields[$tablename]))
                $record["ecrown"] = $client_verified[$this->toolbox->users->user_id_field_name];
            /*
             * check the different scenarios. #1: the record was not touched for more than 30 days.
             * (Corresponds to java constant
             * de.nmichael.efa.data.efacloud.SynchControl.synch_upload_look_back_ms in efa.) Than it will not
             * be in the set of changed records, but it is also assumed, that no key fixing is required. #2:
             * the record is very young. This happens be duplicate writing of messages. Note: the LastModified
             * value is in milliseconds and a String, time() in seconds and an integer (32 bit).
             */
            $last_modified_secs = intval(
                    substr($record_matched["LastModified"], strlen($record_matched["LastModified"]) - 3));
            $update_instead = (time() - $last_modified_secs) > (30 * 24 * 3600);
            $update_instead = $update_instead || (((time() - $last_modified_secs) < 10) &&
                     (strcasecmp($tablename, "efa2messages") == 0));
            if ($update_instead) {
                if ($this->debug_on)
                    file_put_contents($this->api_debug_log_path, 
                            date("Y-m-d H:i:s") . ": api_insert: record is already there, updating instead.\n", 
                            FILE_APPEND);
                // for new records: add the owner ID, if the table uses that field
                if (! isset($record["LastModified"]))
                    $record["LastModified"] = time() . "000";
                if (! isset($record["LastModification"]) && $this->is_efa_table($tablename))
                    $record["LastModification"] = "update";
                $result = $this->socket->update_record_matched($efaCloudUserID, $tablename, $key, $record, 
                        true);
                if (strlen($result) == 0)
                    return "300;Updated record instead of insert, provided key " . json_encode($key) .
                             " is already in use.";
                // 300 => "Transaction successful."
                else
                    return "502;" . $result; // 502 => "Transaction failed."
            } elseif ((strpos($this->fixid_allowed, $tablename) === false) ||
                     (array_key_exists("ecrid", $key) && (strlen($key["ecrid"]) > 5))) {
                if ($this->debug_on)
                    file_put_contents($this->api_debug_log_path, 
                            date("Y-m-d H:i:s") . ": api_insert: record is already there, done nothing.\n", 
                            FILE_APPEND);
                // they key used to identify the record is unambiguous, therefore insertion is refused.
                return "502;Cannot insert record, provided key [" . json_encode($key) . "] is already in use.";
            } else {
                if ($this->debug_on)
                    file_put_contents($this->api_debug_log_path, 
                            date("Y-m-d H:i:s") .
                                     ": api_insert: record is already there, Preparing ke fixing.\n", 
                                    FILE_APPEND);
                // the key shall be fixed. Copy the client side key to the ClientSideKey field
                // use the provided $key, not the $record, because the $record my have got an ecrid here
                $record["ClientSideKey"] = $client_verified[$this->toolbox->users->user_id_field_name] . ":" .
                         $this->compile_clientSideKey($tablename, $key);
                // autoincrement the numeric part of the key. Note that for the logbook tables that will
                // need the logbook name, because all logbooks are in one single table at the server
                // side.
                $record[$this->fixid_auto_field[$tablename]] = $this->autoincrement_key_field($tablename, 
                        (isset($record["Logbookname"]) ? $record["Logbookname"] : ""));
                $key_was_modified = true;
            }
        }
        
        // if an efaCloud record ID is provided an unused, fixable efa key is created by
        // autoincrementation.
        if (isset($record["ecrid"]) && (strpos($this->fixid_allowed, $tablename) !== false) && (! isset(
                $record[$this->fixid_auto_field[$tablename]]) ||
                 (strlen(strval($record[$this->fixid_auto_field[$tablename]])) == 0))) {
            // autoincrement the numeric part of the key. Note that for the logbook tables that will
            // need the logbook name, because all logbooks are in one single table at the server
            // side.
            $record[$this->fixid_auto_field[$tablename]] = $this->autoincrement_key_field($tablename, 
                    (isset($record["Logbookname"]) ? $record["Logbookname"] : ""));
        }
        
        // create AllCrewIds field, if this is the logbook table
        if (strcasecmp($tablename, "efa2logbook") == 0)
            $record["AllCrewIds"] = $this->create_AllCrewIds_field($record);
        
        // insert data record and return result
        if (! isset($record["LastModified"]))
            ! $record["LastModified"] = time() . "000";
        if (! isset($record["LastModification"]) && $this->is_efa_table($tablename))
            $record["LastModification"] = "insert";
        $result = $this->socket->insert_into($efaCloudUserID, $tablename, $record);
        
        // successful insertion. Complete task
        if (is_numeric($result) || (strlen($result) == 0)) {
            // adjust autoincrement
            if (strcasecmp($tablename, "efa2boatdamages") == 0) {
                $maxNumericID = $this->socket->find_records_sorted_matched($tablename, [], 1, "", "Damage", 
                        false);
                $setautoincrement = intval($maxNumericID["Damage"]);
                $this->socket->update_record($efaCloudUserID, "efa2autoincrement", 
                        ["Sequence" => "efa2boatdamages","IntValue" => $setautoincrement,
                                "LastModified" => time() . "000","LastModification" => "update"
                        ]);
            }
            if (strcasecmp($tablename, "efa2boatreservations") == 0) {
                $maxNumericID = $this->socket->find_records_sorted_matched($tablename, [], 1, "", 
                        "Reservation", false);
                $setautoincrement = intval($maxNumericID["Reservation"]);
                $this->socket->update_record($efaCloudUserID, "efa2autoincrement", 
                        ["Sequence" => "efa2boatreservations","IntValue" => $setautoincrement,
                                "LastModified" => time() . "000","LastModification" => "update"
                        ]);
            }
            if (strcasecmp($tablename, "efa2messages") == 0) {
                $maxNumericID = $this->socket->find_records_sorted_matched($tablename, [], 1, "", "MessageId", 
                        false);
                $setautoincrement = intval($maxNumericID["MessageId"]);
                $this->socket->update_record($efaCloudUserID, "efa2autoincrement", 
                        ["Sequence" => "efa2messages","LongValue" => $setautoincrement,
                                "LastModified" => time() . "000","LastModification" => "update"
                        ]);
            }
            
            // return response, depending on whether a key was modified, or not.
            if ($key_was_modified) {
                $fixing_request_csv = $this->get_next_key_to_fix($client_verified, $tablename, $api_log_path);
                if ($this->debug_on)
                    file_put_contents($this->api_debug_log_path, 
                            date("Y-m-d H:i:s") . ": api_insert: Key fixed to " . $result . " \n", FILE_APPEND);
                return "303;" . $fixing_request_csv; // 303 => "Transaction completed and data key
                                                         // mismatch detected."
            } else {
                if ($this->debug_on)
                    file_put_contents($this->api_debug_log_path, 
                            date("Y-m-d H:i:s") . ": api_insert: completed. Ecrid '" . $record["ecrid"] .
                                     "' \n", FILE_APPEND);
                // return the ecrid, if existing. Clients without efaCloud record management will without
                // effect ignore this information.
                if (array_key_exists("ecrid", $record) && (strlen($record["ecrid"]) > 5))
                    return "300;ecrid=" . $record["ecrid"]; // 300 => "Transaction completed."
                else
                    return "300;ok."; // 300 => "Transaction completed."
            }
        } else {
            if ($this->debug_on)
                file_put_contents($this->api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": api_insert: Failed. Reason: '" . $result . "' \n", 
                        FILE_APPEND);
            return "502;" . $result; // 502 => "Transaction failed."
        }
    }

    /**
     * Update a record within a table using the API syntax and return the result as
     * "<result_code>;<result_message>". Set the LastModified and LastModification values, if not yet set.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param array $tablename
     *            the table out of which the record shall be updated.
     * @param array $record
     *            record which shall be used for updating.
     */
    public function api_update (array $client_verified, String $tablename, array $record)
    {
        if ($this->debug_on)
            file_put_contents($this->api_debug_log_path, 
                    date("Y-m-d H:i:s") . ": starting api_update for " . json_encode($record) . "\n", 
                    FILE_APPEND);
        
        // See comments in api_insert for the semantics of the preliminary checks
        $key = $this->get_data_key($tablename, $record);
        if ($key === false) {
            if ($this->debug_on)
                file_put_contents($this->api_debug_log_path, 
                        date("Y-m-d H:i:s") .
                                 ": api_update: Cannot update record, key is incomplete or missing in '" .
                                 $record . "'\n", FILE_APPEND);
            return "502;Cannot update record, key is incomplete or missing.";
        }
        $record_matched = $this->get_corresponding_server_record($client_verified, $tablename, $record);
        if ($record_matched === false) {
            if ($this->debug_on)
                file_put_contents($this->api_debug_log_path, 
                        date("Y-m-d H:i:s") .
                                 ": api_update: Cannot update record, no record matching the given key was found for record '" .
                                 json_encode($record) . "'\n", FILE_APPEND);
            return "502;Cannot update record, no record matching the given key was found.";
        }
        
        // add update information to record
        $efaCloudUserID = $client_verified[$this->toolbox->users->user_id_field_name];
        if (! isset($record["LastModified"]))
            $record["LastModified"] = time() . "000";
        if (! isset($record["LastModification"]) && $this->is_efa_table($tablename))
            $record["LastModification"] = "update";
        // add ecrid, if not yet contained
        if (! array_key_exists("ecrid", $record_matched) || (strlen($record_matched["ecrid"]) <= 5)) {
            $new_ecrids = self::generate_ecrids(1);
            if ($this->debug_on)
                file_put_contents($this->api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": api_update: Generated new ecrid '" . $new_ecrids[0] . " for " .
                                 json_encode($record) . "\n", FILE_APPEND);
            $record["ecrid"] = $new_ecrids[0];
        }
        
        // create AllCrewIds field, if this is the logbook table
        if (strcasecmp($tablename, "efa2logbook") == 0)
            $record["AllCrewIds"] = $this->create_AllCrewIds_field($record);
        
        // update and return result
        $result = $this->socket->update_record_matched($efaCloudUserID, $tablename, $key, $record, true);
        if (strlen($result) == 0) {
            if ($this->debug_on)
                file_put_contents($this->api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": api_update: Completed successfully for " .
                                 json_encode($record) . ".\n", FILE_APPEND);
            return "300;ok."; // 300 => "Transaction successful."
        } else {
            if ($this->debug_on)
                file_put_contents($this->api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": api_update: Failed. Reason:" . $result . "\n", FILE_APPEND);
            return "502;" . $result; // 502 => "Transaction failed."
        }
    }

    /**
     * Remove all fields from the record and create a "deleted record" to memorize deletion. In order to work,
     * the record must contain all its data fields. The following fields are NOT deleted:
     * Efa_tables::$key_fields, 'ecrid', 'InvalidFrom', Efa_audit::$assert_not_empty. 'ChangeCount' is
     * increased, 'LastModified' updated and 'LastModification' set to 'delete'.
     * 
     * @param array $tablename
     *            the table out of which the record shall be deleted.
     * @param array $record
     *            record which shall be deleted.
     * @return the deleted record containing all fields for the subsequent update command or false, if the
     *         record did not contain any information which needs modification, including the LastModified
     *         field. If the record was already cleared, returns false
     */
    public function clear_record_for_delete (String $tablename, array $record)
    {
        $record_emptied = [];
        $changes_needed = false;
        // create a copy with just the key fieldsfor efa tables. Keep the keys propagation information and
        // owner, because depending on the client which will be informed on the deletion either the one or the
        // other will be needed.
        foreach ($record as $key => $value) {
            if (in_array($key, self::$key_fields[$tablename]) || (strcasecmp($key, "ecrid") == 0) ||
                     (strcasecmp($key, "InvalidFrom") == 0) ||
                     in_array($key, Efa_audit::$assert_not_empty[$tablename]))
                // keep relevant values
                $record_emptied[$key] = $record[$key];
            elseif ((strcasecmp($key, "LastModification") == 0) && (strcasecmp($value, "delete") != 0))
                // register change if last modification was not delete
                $changes_needed = true;
            elseif ((strcasecmp($key, "ecrhis") == 0) && (strlen($value) > 0)) {
                // ensure the history is removed instead of continued, when the socket executes the
                // modification.
                $record_emptied[$key] = "REMOVE!";
                $changes_needed = true;
            } elseif ((strcasecmp($key, "ChangeCount") != 0) && (strcasecmp($key, "LastModified") != 0) &&
                     (strcasecmp($key, "LastModification") != 0)) {
                // register change and clear value, if it still exists, except for ChangeCount,
                // LastModification, and LastModified; because they will never be empty.
                if (strlen($record[$key]) > 0) {
                    if (in_array($key, Efa_tables::$int_fields[$tablename])) {
                        $record_emptied[$key] = 0; // integer values must not be ""
                        $changes_needed = (intval($record[$key]) != 0);
                    } else {
                        $record_emptied[$key] = "";
                        $changes_needed = true;
                    }
                }
            }
        }
        if (! $changes_needed)
            return false;
        // update the last modification event
        $record_emptied["ChangeCount"] = intval($record["ChangeCount"]) + 1;
        $record_emptied["LastModified"] = time() . "000";
        $record_emptied["LastModification"] = "delete";
        return $record_emptied;
    }

    /**
     * Delete a record within a table using the API syntax and return the result as
     * "<result_code>;<result_message>". For the 16 common efa tables data records are not deleted, but rather
     * emptied except the data key and the LastModified and Last Modification fields. That is the only way to
     * inform an offline client afterwards about the record deletion.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param array $tablename
     *            the table out of which the record shall be deleted.
     * @param array $record_or_key
     *            record, or at least the key of the record which shall be deleted.
     */
    public function api_delete (array $client_verified, String $tablename, array $record_or_key)
    {
        if ($this->debug_on)
            file_put_contents($this->api_debug_log_path, date("Y-m-d H:i:s") . ": starting api_delete\n", 
                    FILE_APPEND);
        
        // See comments in api_insert for the semantics of the preliminary checks
        $record_key = $this->get_data_key($tablename, $record_or_key);
        if ($record_key === false) {
            if ($this->debug_on)
                file_put_contents($this->api_debug_log_path, 
                        date("Y-m-d H:i:s") .
                                 ": api_delete: Cannot delete record, key is incomplete or missing in '" .
                                 $record_or_key . "'\n", FILE_APPEND);
            return "502;Cannot delete record, key is incomplete or missing.";
        }
        $record_matched = $this->get_corresponding_server_record($client_verified, $tablename, $record_or_key);
        if ($record_matched === false) {
            if ($this->debug_on)
                file_put_contents($this->api_debug_log_path, 
                        date("Y-m-d H:i:s") .
                                 ": api_delete: Cannot delete record, no record matching the given key [" .
                                 json_encode($record_key) . "] was found.\n", FILE_APPEND);
            return "502;Cannot delete record, no record matching the given key [" . json_encode($record_key) .
                     "] was found.";
        }
        $efaCloudUserID = $client_verified[$this->toolbox->users->user_id_field_name];
        
        // if this is an efa2 table empty the record rather than delete it.
        // This is to ensure that the deletion can be propagated to other clients.
        if ($this->is_efa_table($tablename)) {
            $record_emptied = $this->clear_record_for_delete($tablename, $record_matched);
            // and update the record with the copy
            if ($record_emptied != false)
                $result = $this->socket->update_record_matched($efaCloudUserID, $tablename, $record_key, 
                        $record_emptied);
        } else // delete the record
            $result = $this->socket->delete_record_matched($efaCloudUserID, $tablename, $record_key);
        
        // return the result
        if (strlen($result) == 0) {
            if ($this->debug_on)
                file_put_contents($this->api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": api_update: Completed successfully.\n", FILE_APPEND);
            return "300;ok"; // 300 => "Transaction successful."
        } else {
            if ($this->debug_on)
                file_put_contents($this->api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": api_update: Failed. Reason:" . $result . "\n", FILE_APPEND);
            return "502;" . $result; // 502 => "Transaction failed."
        }
    }

    /**
     * Remove a ClientSideKey entry after the mismatch was fixed at the client side. Only if the client has no
     * efaCloud record management enabled.
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param array $tablename
     *            the table out of which the record shall be fixed.
     * @param array $fixed_record_reference
     *            reference to fixed record in which the ClientSideKey field shall be removed. Usually just
     *            the records server side data key. MUST NOT CONTAIN AN ECRID FIELD.
     * @param String $api_log_path
     *            path to write the keyfixing activities to
     */
    public function api_keyfixing (array $client_verified, String $tablename, array $fixed_record_reference, 
            String $api_log_path)
    {
        if ($this->debug_on)
            file_put_contents($this->api_debug_log_path, date("Y-m-d H:i:s") . ": starting api_keyfixing\n", 
                    FILE_APPEND);
        
        if (strpos($this->fixid_allowed, $tablename) === false) {
            // 502 => "Transaction failed." if the table must not be fixed.
            if ($this->debug_on)
                file_put_contents($this->api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": api_keyfixing aborted. Not allowed for " . $tablename . ".\n", 
                        FILE_APPEND);
            return "502;" . $tablename . ": no key fixing for this table.";
        }
        if (array_key_exists("ecrid", $fixed_record_reference) &&
                 (strlen($fixed_record_reference["ecrid"]) > 5)) {
            if ($this->debug_on)
                file_put_contents($this->api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": api_keyfixing aborted. Ecrid available.\n", FILE_APPEND);
            return "502;Keyfixing is not allowed for data records with an efaCloud record ID (ecrid).";
        }
        
        // identify, whether the keyfixing record is empty. Ignore the Logbookname field, because the
        // keyfixing record for the logbook always contains the Logbookname, even if there is no key to fix.
        $is_empty_record = (count($fixed_record_reference) == 0) || ((count($fixed_record_reference) == 1) &&
                 (strcasecmp($tablename, "efa2logbook") == 0) &&
                 (isset($fixed_record_reference["Logbookname"])));
        
        // keyfixing may be called with an empty keyfixing record to get the next mismatching
        // record's key. If, however, a key of a fixed record is provided, fix it
        if (! $is_empty_record) {
            $server_key_of_fixed_record = $this->get_data_key($tablename, $fixed_record_reference);
            if ($server_key_of_fixed_record === false) {
                // 502 => "Transaction failed." if the table must not be fixed.
                return "502;" . $tablename . ": incomplete key for fixing in this table. Record: " .
                         json_encode($fixed_record_reference) . ", expected key fields: " .
                         json_encode(self::$key_fields[$tablename]);
            }
            // get record to fix
            $record_to_remove_clientsidekey = $this->socket->find_record_matched($tablename, 
                    $server_key_of_fixed_record);
            // fix it, if found. Ignore, if not.
            if ($record_to_remove_clientsidekey) {
                // replace clientSideKey entry be a "previous key" remark
                $previous = explode(":", $record_to_remove_clientsidekey["ClientSideKey"]);
                $update_fields_for_fixed_record["ClientSideKey"] = "corrected from " . $previous[1] .
                         " at client " . $previous[0];
                $res = $this->socket->update_record_matched(
                        $client_verified[$this->toolbox->users->user_id_field_name], $tablename, 
                        $server_key_of_fixed_record, $update_fields_for_fixed_record);
                if ($this->debug_on)
                    file_put_contents($this->api_debug_log_path, 
                            date("Y-m-d H:i:s") . ": api_keyfixing processed. result: " . $res . ".\n", 
                            FILE_APPEND);
            }
        }
        
        // check for more key which need fixing
        $return_message = $this->get_next_key_to_fix($client_verified, $tablename, $api_log_path);
        if (! $return_message) {
            if ($this->debug_on)
                file_put_contents($this->api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": api_keyfixing completed. No more keys to fix.\n", FILE_APPEND);
            return "300;";
        } else {
            if ($this->debug_on)
                file_put_contents($this->api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": api_keyfixing completed. More keys to fix: " . $return_message .
                                 ".\n", FILE_APPEND);
            return "303;" . $return_message;
        }
    }

    /* --------------------------------------------------------------------------------------- */
    /* ----------------------- READ DATA FROM EFACLOUD --------------------------------------- */
    /* --------------------------------------------------------------------------------------- */
    
    /**
     * Return a list of a table using the API syntax and return the result as "<result-code><rms><result>"
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param int $api_version
     *            The API version of the client request. For efaCloud record management at least 2
     * @param String $table_name
     *            the name of the table to be read. Ihe table name is "@All", the table names and their record
     *            count is returned.
     * @param array $filter
     *            the filter condition. An array containing field names and values and a ["?"] field with the
     *            filter condition. If the filter condition is "@All", and the table name is "@All", all
     *            records of all tables will be returned.
     * @param bool $keys_only
     *            set true to return the data keys and modification of records matching rather than the full
     *            records themselves.
     */
    public function api_select (array $client_verified, int $api_version, String $table_name, array $filter, 
            bool $keys_only)
    {
        if ($this->debug_on)
            file_put_contents($this->api_debug_log_path, 
                    date("Y-m-d H:i:s") . ": starting api_select for client " .
                             $client_verified[$this->toolbox->users->user_id_field_name] .
                             " at table $table_name and api version $api_version.\n", FILE_APPEND);
        $condition = "=";
        if ($filter["?"]) {
            $condition = $filter["?"];
            unset($filter["?"]);
        }
        // find the select mode and count records, if needed
        $get_record_counts_of_db = (strcasecmp($table_name, "@All") == 0);
        $get_all_records_of_db = $get_record_counts_of_db && (strcasecmp($condition, "@All") == 0);
        $ret = "";
        if ($get_record_counts_of_db) {
            $tnames = $this->socket->get_table_names(true);
            foreach ($tnames as $tname) {
                $record_count = $this->socket->count_records($tname, $filter, $condition);
                $ret .= $tname . "=" . $record_count . ";";
            }
            // return the count, if not a full DB dump is requested.
            if (! $get_all_records_of_db)
                return "300;" . $ret;
        } else {
            $tnames = [$table_name
            ];
        }
        
        $csv_rows_cnt = 0;
        // iterate over all tables. This is only a loop for a full dump, else there is just one table to go.
        foreach ($tnames as $tname) {
            
            if ($this->debug_on)
                file_put_contents($this->api_debug_log_path, 
                        date("Y-m-d H:i:s") . ": continued api_select for table $table_name.\n", FILE_APPEND);
            // add the condition to match the logbook, if the logbookname is part of the filter,
            $isLogbooktable = (strcasecmp($tname, "efa2logbook") == 0);
            if ($isLogbooktable && isset($filter["Logbookname"]))
                $condition .= ",=";
            // add the condition to match the clubwork bokk, if the clubworkbookname is part of the filter,
            $isClubworkbooktable = (strcasecmp($tname, "efa2clubwork") == 0);
            if ($isClubworkbooktable && isset($filter["Clubworkbookname"]))
                $condition .= ",=";
            
            // decide, whether to include efaCloud record management information, based on the requests
            // protocol version.
            $include_ecrm_fields = ($api_version >= 3);
            
            $csvtable = ($get_all_records_of_db) ? "###T###" . $tname . "###=###" : "";
            $header = ""; // header row to be created just at top
            $key_fields = self::$key_fields[$tname];
            $key_field_list = ",";
            foreach ($key_fields as $key_field)
                // use all keys for tables other than efa2logbook.
                // For the latter use all keys except the "Logbookname".
                // Note that Clubwork uses UUIDs as key which are unique for all years.
                $key_field_list .= (! $isLogbooktable || (strcasecmp($key_field, "Logbookname") != 0)) ? $key_field .
                         "," : "";
            $key_field_list .= "LastModified,LastModification,";
            if ($include_ecrm_fields)
                $key_field_list .= "ecrid,";
            
            // Exclude fields which will never be handled by the client.
            // 1. the server side key cache for keyfixing and AllCrewIds field, 2. the logbook and
            // clubworkbook selector, 3. the record history and the records copy recipients
            $fields_to_exclude_from_full = ",ClientSideEntryId,AllCrewIds," . "Logbookname,Clubworkbookname," .
                     "ecrhis,";
            // 4. if the client does not support the efacloud record management
            // exclude record id, owner and additional copy to fields
            if (! $include_ecrm_fields)
                $fields_to_exclude_from_full .= "ecrid,ecrown,ecract,";
            // Note: it doesn't hurt to exclude fields which are not existing anyways.
            
            $isFirstRow = true; // filter to identify, whether a header shall be created
            $start_row = 0;
            // iterate through all rows in chunks, use defalt ordering. Note taht this may not create a
            // consistent snapshot, if the table is changed in the meanwhile. But for the purpose of
            // repetitive synchronization tis is no risk.
            $records = $this->socket->find_records_sorted_matched($tname, $filter, self::$select_chunk_size, 
                    $condition, "", true, $start_row);
            
            while (($records !== false) && (count($records) > 0)) {
                
                if ($this->debug_on)
                    file_put_contents($this->api_debug_log_path, 
                            date("Y-m-d H:i:s") . ": processing records from #$start_row onwards.\n", 
                            FILE_APPEND);
                // add the condition to match the logbook, if the logbookname is part of the filter,
                foreach ($records as $record) {
                    // drop empty rows
                    if (count($record) > 0) {
                        $csvrow = "";
                        // set last 9 characters of "LastModified" to "000000230" for records without ecrid to
                        // trigger an update for ecrid generation.
                        if (! array_key_exists("ecrid", $record) || (strlen($record["ecrid"]) <= 5))
                            $record["LastModified"] = substr($record["LastModified"], 0, 
                                    strlen($record["LastModified"]) - 9) . "000000230";
                        
                        foreach ($record as $field_name => $value) {
                            $field_name_checker = "," . $field_name . ",";
                            // use the column, if it is a key field, and a key is requested, or if it is not
                            // to be
                            // excluded and a full record is requested.
                            $use_column = ($keys_only) ? (strpos($key_field_list, $field_name_checker) !==
                                     false) : (strpos($fields_to_exclude_from_full, $field_name_checker) ===
                                     false);
                            if ($use_column) {
                                if ($isFirstRow)
                                    $header .= $field_name . ";";
                                // the value needs proper csv-quotation, field name not.
                                if ((strpos($value, ";") !== false) || (strpos($value, "\n") !== false) ||
                                         (strpos($value, "\"") !== false))
                                    $csvrow .= '"' . str_replace('"', '""', $value) . '";';
                                else
                                    $csvrow .= $value . ';';
                            }
                        }
                        
                        // before writing the first row, put the header w/o the dangling ';'
                        if ($isFirstRow) {
                            $csvtable = substr($header, 0, strlen($header) - 1) . "\n";
                            $isFirstRow = false;
                        }
                        // put the row w/o the dangling ';'
                        if (strlen($csvrow) > 0)
                            $csvrow = substr($csvrow, 0, strlen($csvrow) - 1);
                        $csvtable .= $csvrow . "\n";
                        $csv_rows_cnt ++;
                    }
                }
                $start_row += self::$select_chunk_size;
                // the following statment must be the very same as above before the loop.
                $records = $this->socket->find_records_sorted_matched($tname, $filter, 
                        self::$select_chunk_size, $condition, "", true, $start_row);
            }
        }
        
        // cut off the last \n and return the result.
        if (strlen($csvtable) > 0)
            $csvtable = substr($csvtable, 0, strlen($csvtable) - 1);
        if ($this->debug_on)
            file_put_contents($this->api_debug_log_path, 
                    date("Y-m-d H:i:s") .
                             ": api_select for table $table_name completed. Returning $csv_rows_cnt records.\n", 
                            FILE_APPEND);
        
        return "300;" . $csvtable;
    }

    /**
     * Return a list of a table using the API syntax and return the result."
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param String $listname
     *            the name of the list to be used.
     * @param String $setname
     *            the name of the lis set to use.
     * @param String $logbookname
     *            the the name of the logbook to use, if applicable.
     * @param int $last_modified_min
     *            the minimum LastModified vaöue to apply (seconds).
     */
    public function api_list (array $client_verified, String $listname, array $record, int $last_modified_min)
    {
        $logbookname = (isset($record["logbookname"])) ? $record["logbookname"] : date("Y");
        if (! isset($record["setname"]))
            return "502;Missing set name in record";
        if (! file_exists("../config/lists/" . $record["setname"]))
            return "502;Invali set name in record";
        $list_args = ["{LastModified}" => strval($last_modified_min) . "000",
                "{Logbookname}" => $logbookname
        ];
        include_once "../classes/tfyh_list.php";
        $list = new Tfyh_list("../config/lists/" . $record["setname"], 0, $listname, $this->socket, 
                $this->toolbox, $list_args);
        $list->entry_size_limit = 50;
        $is_versionized = ($list->get_field_index("InvalidFrom") !== false);
        $csv = $list->get_csv($client_verified, ($is_versionized) ? "Id" : null);
        return "300;" . $csv;
    }

    /* --------------------------------------------------------------------------------------- */
    /* ----------------------- API SUPPORT FUNCTIONS ----------------------------------------- */
    /* --------------------------------------------------------------------------------------- */
    
    /**
     * Trigger a backup of all tables creating a zip archive of text files. There is a two stage backup
     * process with 10 backups at each stage. So this gives you 10 days daily backup and 10 backups with a 10
     * day period between each, i. e. a 100 day backup regime. This function will also trigger a move of the
     * API log file to an indexed version, when a secondary backup is triggered. By this also the api log is
     * regularly moved and overwritten.
     * 
     * @param String $api_log_path
     *            the log path for the api transactions
     * @return string the transaction result
     */
    public function api_backup (String $api_log_path)
    {
        include_once "../classes/tfyh_backup_handler.php";
        $backup_handler = new Tfyh_backup_handler("../log/", $this->toolbox, $this->socket);
        $backup_index = $backup_handler->backup();
        return "300;" . $backup_index;
    }

    /**
     * Trigger the cron_jobs as defined in the Cron_jobs class.
     * 
     * @param int $user_id
     *            the efaCloudUserID of the user which triggered the job execution.
     * @return string the transaction result
     */
    public function api_cronjobs (int $user_id)
    {
        include_once ("../classes/cron_jobs.php");
        Cron_jobs::run_daily_jobs($this->toolbox, $this->socket, $user_id);
        return "300;jobs comopleted";
    }

    /**
     * get the size of a specific column to adjust the client max length property. Returns 0 on unsized
     * columns like INT, BIGINT, DATE or similar. Used for efa2groups memberIdList.
     * 
     * @param String $table_name
     *            table to search in
     * @param String $column_name
     *            column to search for
     * @return int the size of the column, if provided, else 0.
     */
    private function column_size (String $table_name, String $column_name)
    {
        $column_names = $this->socket->get_column_names($table_name);
        $column_definitions = $this->socket->get_column_types($table_name);
        for ($i = 0; $i < count($column_names); $i ++)
            if (strcmp($column_names[$i], $column_name) == 0)
                if (strpos($column_definitions[$i], "(") === false)
                    return 0;
                else {
                    $start = strpos($column_definitions[$i], "(") + 1;
                    $end = strpos($column_definitions[$i], ")");
                    $size = substr($column_definitions[$i], $start, $end - $start);
                    return intval($size);
                }
        return 0;
    }

    /**
     * Return some configuration settings as add-on to nop
     * 
     * @param array $client_verified
     *            the verified client which requests the execution
     * @param array $record
     *            the record provided within the transaction
     */
    public function api_nop (array $client_verified, array $record)
    {
        // wait some time as this is also a nop function.
        $wait_for_secs = intval(trim($record["sleep"]));
        $wait_for_secs = ($wait_for_secs > 100) ? 100 : $wait_for_secs;
        if ($wait_for_secs > 0)
            sleep($wait_for_secs);
        $tx_response = "300";
        // add the synchronisation period settings
        $cfg = $this->toolbox->config->get_cfg();
        $tx_response .= ";synch_check_period=" . intval($cfg["synch_check_period"]);
        $tx_response .= ";synch_period=" . intval($cfg["synch_period"]);
        // add the efa2groups member id list field size
        $group_memberidlist_size = $this->column_size("efa2groups", "MemberIdList");
        if ($group_memberidlist_size != 1024)
            $tx_response .= ";group_memberidlist_size=" . $group_memberidlist_size;
        // add the server welcome message
        $username = $client_verified[$this->toolbox->users->user_firstname_field_name] . " " .
                 $client_verified[$this->toolbox->users->user_lastname_field_name] . " (Rolle: " .
                 $client_verified["Rolle"] . ")";
        $version = (file_exists("../public/version")) ? file_get_contents("../public/version") : "";
        // add the table layout
        include_once "../classes/efa_db_layout.php";
        $db_layout = Efa_db_layout::get_layout($this->db_layout_version);
        $tx_response .= ";server_welcome_message=efaCloud Server Version '" . $version . "'//verbunden als '" .
                 $username . "';db_layout=" . $db_layout;
        if (isset($_SESSION["API_sessionid"]))
            $tx_response .= ";API_sessionid=" . $_SESSION["API_sessionid"];
        return $tx_response;
    }

    /**
     * Return the verification result for an efaCloudUser
     * 
     * @param array $record
     *            the record provided within the transaction
     * @param int $api_version
     *            The API version of the client request. For efaCloud record management at least 2
     */
    public function api_verify (array $record, int $api_version)
    {
        if ($api_version < 2)
            return "501;API version must be 2 or higher.";
        // get the user which shall be verified
        if (isset($record["efaAdminName"])) {
            $login_field = "efaAdminName";
            $login_value = $record["efaAdminName"];
        } elseif (isset($record[$this->toolbox->users->user_id_field_name])) {
            $login_field = $this->toolbox->users->user_id_field_name;
            $login_value = $record[$this->toolbox->users->user_id_field_name];
        } else
            return "402;Neither efaAdminName nor " . $this->toolbox->users->user_id_field_name .
                     " provided in VERIFY transaction record. Verification impossible.";
        if (! isset($record["password"]) || (strlen($record["password"]) < 8))
            return "403;No password provided or password too short in VERIFY transaction record. Verification impossible.";
        
        // get the user to verify the credentials for
        $user_to_verify = $this->socket->find_record($this->toolbox->users->user_table_name, $login_field, 
                $login_value);
        
        // and the user's password hash
        $auth_provider_class_file = "../authentication/auth_provider.php";
        if ((strlen($user_to_verify["Passwort_Hash"]) < 10) && file_exists($auth_provider_class_file)) {
            include_once $auth_provider_class_file;
            $auth_provider = new Auth_provider();
            $user_to_verify["Passwort_Hash"] = $auth_provider->get_pwhash(
                    $user_to_verify[$this->toolbox->users->user_id_field_name]);
        }
        
        if (strlen($user_to_verify["Passwort_Hash"]) < 10)
            return "403:user has no password hash set in its profile.";
        
        $verified = password_verify($record["password"], $user_to_verify["Passwort_Hash"]);
        $user_keys_csv = "";
        $user_values_csv = "";
        foreach ($user_to_verify as $key => $value) {
            if ((strcasecmp($key, "Passwort_Hash") != 0) && (strcasecmp($key, "ecrhis") != 0)) {
                $user_keys_csv .= $key . ";";
                $user_values_csv .= $this->toolbox->encode_entry_csv($value) . ";";
            }
        }
        $user_keys_csv = substr($user_keys_csv, 0, strlen($user_keys_csv) - 1);
        $user_values_csv = substr($user_values_csv, 0, strlen($user_values_csv) - 1);
        $user_record_csv = $user_keys_csv . "\n" . $user_values_csv;
        return ($verified) ? "300;" . $user_record_csv : "403:credentials in VERIFY transaction record were not verified.";
    }

    /* --------------------------------------------------------------------------------------- */
    /* ---------- TABLE AND RECORD MANAGMENENT SUPPORT FUNCTIONS ----------------------------- */
    /* --------------------------------------------------------------------------------------- */
    
    /**
     * Generate a set of efaCloud record Ids. Use 9 bytes created by the standard
     * “openssl_random_pseudo_bytes” PHP function and map three times three of them into a four character
     * base64 type sequence like for the transaction container, i. e. with the following special characters:
     * “-” instead of “/” and “*” instead of “+” (e.g. “Z6K3mpORQ5y6”).
     * 
     * @return array the created ecrids as numbered array
     */
    public static function generate_ecrids (int $count)
    {
        $ecrid_hex = strtolower(bin2hex(openssl_random_pseudo_bytes(9 * $count)));
        $ecrids = [];
        for ($e = 0; $e < $count; $e ++) {
            $ecrid = "";
            for ($b = 0; $b < 3; $b ++) {
                $hex = substr($ecrid_hex, $e * 18 + $b * 6, 6);
                $int = intval($hex, 16);
                for ($i = 0; $i < 4; $i ++) {
                    $ecrid .= substr(self::$ecmap, $int % 64, 1);
                    $int = intval($int / 64);
                }
            }
            $ecrids[] = $ecrid;
        }
        return $ecrids;
    }

    /**
     * Return the data key of a record. Precedence has the efaCloud record Id, but if it is not available, the
     * efa data key is retuned. That means: In case the record contains an ecrid field, the return key will be
     * [ "ecrid" => <ecrid_value> ], else it will be an associated array with all values as defined in
     * self::$key_fields[]. If one of these key fields is then not set within the record, false is returned.
     * For key field definition see also comment on self::$key_fields.
     * 
     * @param String $tablename
     *            name of table to find the record
     * @param array $record
     *            record to find
     * @return mixed the key as associative array, if for all key fields a value is provided, else false.
     */
    public function get_data_key (String $tablename, array $record)
    {
        $data_key = [];
        if (isset($record["ecrid"]))
            $data_key["ecrid"] = $record["ecrid"];
        else {
            $keys = self::$key_fields[$tablename];
            foreach ($keys as $key) {
                if (isset($record[$key]))
                    $data_key[$key] = $record[$key];
                else
                    return false;
            }
        }
        return $data_key;
    }

    /**
     * Change the text of a boolean entry to be efa-compatible: i. e. from "on" to "true" or vice versa
     * 
     * @param String $tablename
     *            the table in which the record is located.
     * @param array $record
     *            the record to modify
     */
    public static function fix_boolean_text (String $tablename, array $record)
    {
        // fix for boolean (checkbox) values: efa expects "true" or nothing instead of "on" or nothing
        foreach ($record as $key => $value) {
            if (isset(self::$boolean_fields[$tablename . "." . $key])) {
                if (strlen($value) > 0) {
                    if (strcasecmp($value, "on") == 0)
                        $record[$key] = "true";
                    if (strcasecmp($value, "true") == 0)
                        $record[$key] = "on";
                } else
                    $record[$key] = "";
            }
        }
        return $record;
    }

    /**
     * Get the name of the record using the Efa_tables::$name_fields and delimiting all values by " ".
     * 
     * @param String $tablename
     *            the table in which the record is located.
     * @param array $record
     *            the record to get the name for
     * @return string the name to use. May not be unique.
     */
    public static function get_name (String $tablename, array $record)
    {
        $name = "";
        foreach (Efa_tables::$name_fields[$tablename] as $name_field)
            $name .= $record[$name_field] . " ";
        if (strlen($name) > 0) $name = substr($name, 0, strlen($name) - 1);
        return $name;
    }
}