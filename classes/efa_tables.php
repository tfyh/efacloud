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


// TODO introduced to avoid a fata error when updating from < 2.3.2_13 to 2.3.2_13ff. in April 2023. Remove
// some day
if (! function_exists("i"))
    include_once "../classes/init_i18n.php";

/**
 * Fully static class file for the specific handling of eFa tables, e. g. GUID generation, autoincrementation
 * etc.
 */
class Efa_tables
{

    /* -------------------------------------------------------------------- */
    /* ---- STATIC STRUCTURE DECLARATIONS ----- */
    /* ---- *_fields arrays = [ table names => [ efa column names ] ----- */
    /* -------------------------------------------------------------------- */
    
    /**
     * Column names of those columns that represent the data key of the specific efa2 table. These key fields
     * are the same as in the efa client. One exception: efa2logbook with the additional Logbookname key
     * field. Within the client each year has a separate logbook with EntryIds starting from 1 at the first of
     * January. The server uses just one table with an additional data field Logbookname. The server side key
     * therefore is the EntryId plus the Logbookname.
     */
    public static $efa_data_key_fields = [
            "efa2autoincrement" => ["Sequence" // ARRAY SECTION #1: single field keys
            ],"efa2boatstatus" => ["BoatId"
            ],"efa2clubwork" => ["Id" // as uuid it is unique for all club workbooks
            ],"efa2crews" => ["Id"
            ],"efa2fahrtenabzeichen" => ["PersonId"
            ],"efa2logbook" => ["EntryId","Logbookname" // @efaCloud, but @efa: EntryId only
            ],"efa2messages" => ["MessageId"
            ],"efa2sessiongroups" => ["Id"
            ],"efa2statistics" => ["Id"
            ],"efa2status" => ["Id"
            ],"efa2waters" => ["Id"
            ],
            // ARRAY SECTION #2 double field keys: numeric plus BoatId
            "efa2boatdamages" => ["BoatId","Damage"
            ],"efa2boatreservations" => ["BoatId","Reservation"
            ],
            // ARRAY SECTION #3 versionized tables: Id plus ValidFrom key fields.
            "efa2boats" => ["Id","ValidFrom"
            ],"efa2destinations" => ["Id","ValidFrom"
            ],"efa2groups" => ["Id","ValidFrom"
            ],"efa2persons" => ["Id","ValidFrom"
            ],"efaCloudUsers" => ["ID"
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
            ],"efa2clubwork" => ["Date","FirstLastName","Hours","Description"
            ],"efa2crews" => ["Name"
            ],"efa2destinations" => ["Name","Distance","ValidFrom","InvalidFrom"
            ],"efa2fahrtenabzeichen" => ["GUI_VORNAME","GUI_NACHNAME","GUI_LETZTESDATUM"
            ],"efa2groups" => ["Name","ValidFrom","InvalidFrom"
            ],
            "efa2logbook" => ["Logbookname","EntryId","Date","BoatId","BoatName","AllCrewNames",
                    "DestinationId","DestinationName","Distance"
            ],"efa2messages" => ["From","Date","Subject"
            ],"efa2persons" => ["FirstName","LastName","MembershipNo","ValidFrom","InvalidFrom"
            ],"efa2sessiongroups" => ["Name","Logbook","Route","StartDate","EndDate"
            ],"efa2statistics" => ["Name","PubliclyAvailable"
            ],"efa2status" => ["Name","Type","AutoSetOnAge"
            ],"efa2waters" => ["Name","Details"
            ],"efaCloudUsers" => ["Vorname","Nachname","efaCloudUserID"
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
            ],"efaCloudUsers" => ["Vorname","Nachname"
            ]
    ];

    /**
     * efa virtual fields, i. e. fields which are generated based on the content of the record itself to
     * facilitate display.
     */
    public static $virtual_fields = [
            "efa2boatreservations" => ["VirtualBoat","VirtualPerson","VirtualReservationDate"
            ],"efa2clubwork" => ["FirstName","LastName","FirstLastName","NameAffix"
            ],"efa2logbook" => ["AllCrewIds","AllCrewNames"
            ],"efa2persons" => ["FirstLastName"
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
            ],
            "efaCloudUsers" => ["ID","efaCloudUserID","Workflows","Concessions","Subskriptionen",
                    "LastModified"
            ]
    
    ];

    /**
     * All data fields which are generated by the efacloud server, if they are not provided by the record
     * sender (API [efa], APIV3 [efaWeb, efaApp], Server UI, Server import). Shall always be read only for any
     * UI access. Includes key fields, except ValidFrom, Logbooknmame, BoatId.
     */
    public static $server_gen_fields = ["efa2autoincrement" => ["LastModification","ecrid"
    ],
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
            ],"efaCloudUsers" => ["ID","LastModified"
            ]
    ];

    /**
     * All data fields which contain a date value. For formatting purposes and empty-to-NULL-conversion.
     */
    public static $date_fields = ["efa2autoincrement" => [],
            "efa2boatdamages" => ["ReportDate","FixDate"
            ],"efa2boatreservations" => ["DateFrom","DateTo"
            ],"efa2boats" => ["ManufactionDate","PurchaseDate","SellingDate"
            ],"efa2boatstatus" => [],"efa2clubwork" => ["Date"
            ],"efa2crews" => [],"efa2destinations" => [],"efa2fahrtenabzeichen" => [],"efa2groups" => [],
            "efa2logbook" => ["Date","EndDate"
            ],"efa2messages" => ["Date"
            ],"efa2persons" => [],"efa2sessiongroups" => ["StartDate","EndDate"
            ],"efa2statistics" => ["DateFrom","DateTo"
            ],"efa2status" => [],"efa2waters" => []
    ];

    /**
     * All data fields which contain a time value. For formatting purposes.
     */
    public static $time_fields = ["efa2boatdamages" => ["ReportTime","FixTime"
            ],"efa2boatreservations" => ["TimeFrom","TimeTo"
            ],"efa2logbook" => ["StartTime","EndTime"
            ],"efa2messages" => ["Time"]
    ];
    
    /**
     * All fields which contain a date value that indicates the period to which this record belongs, when
     * doing a name lookup in versionized tables.
     */
    public static $period_indication_fields = ["efa2autoincrement" => false,
            "efa2boatdamages" => "ReportDate","efa2boatreservations" => "DateFrom","efa2boats" => false,
            "efa2boatstatus" => false,"efa2clubwork" => "Date","efa2crews" => false,
            "efa2destinations" => false,"efa2fahrtenabzeichen" => false,"efa2groups" => false,
            "efa2logbook" => "Date","efa2messages" => "Date","efa2persons" => false,
            "efa2sessiongroups" => "StartDate","efa2statistics" => "DateFrom","efa2status" => false,
            "efa2waters" => false
    ];

    /**
     * The field which shall be autoincremented in the respective four efa2 tables. The fields type MUST BE
     * INTEGER. Note that for efa2logbook the autoincrementation is not done with a autoincrement counter, as
     * in the other three, because efa uses multiple logbooks. For all autoincremented keys keyfixing is
     * allowed. NOTE From 2.3.2_09 onwards key fixing of message records will no more take place, but messages
     * with an exiting MessageId are dropped.
     */
    public static $efa_autoincrement_fields = ["efa2logbook" => "EntryId","efa2messages" => "MessageId",
            "efa2boatdamages" => "Damage","efa2boatreservations" => "Reservation"
    ];

    /* -------------------------------------------------------------------- */
    /* ---- STATIC STRUCTURE DECLARATIONS --------------------------------- */
    /* ---- *_names arrays = [ column / table names ] --------------------- */
    /* -------------------------------------------------------------------- */
    
    /**
     * A set of all fields which carry a UUID. Some of the field names are used in multiple tables
     */
    public static $UUID_field_names = ["BoatId","FixedByPersonId","ReportedByPersonId","Id","CoxId",
            "Crew1Id","Crew2Id","Crew3Id","Crew4Id","Crew5Id","Crew6Id","Crew7Id","Crew8Id","Crew9Id",
            "Crew10Id","Crew11Id","Crew12Id","Crew13Id","Crew14Id","Crew15Id","Crew16Id","Crew17Id","Crew18Id",
            "Crew19Id","Crew20Id","Crew21Id","Crew22Id","Crew23Id","Crew24Id","PersonId","DestinationId",
            "StatusId"
    ];

    /**
     * A set of all fields which carry a UUID list. Some of the field names are used in multiple tables
     */
    public static $UUIDlist_field_names = ["WatersIdList","MemberIdList"
    ];

    /**
     * The list of timestamp fields.
     */
    public static $timestamp_field_names = ["LastModified","ValidFrom","InvalidFrom"
    ];

    /**
     * The system fields created by efaCloud. Used to identify whether real content is within the record.
     */
    public static $ecr_system_field_names = ["ecrid","ecrown","ecrhis"
    ];

    /**
     * The system fields created by efa. Used to identify whether real content is within the record.
     */
    public static $efa_system_field_names = ["ChangeCount","LastModified","LastModification"
    ];

    /**
     * A list of the four verionized tables
     */
    public static $versionized_table_names = ["efa2boats","efa2destinations","efa2groups","efa2persons"
    ];

    /* ---------------------------------------- */
    /* ---- STATIC PARAMETER DECLARATIONS ----- */
    /* ---------------------------------------- */
    
    /**
     * maximum number of records per select chunk to avoid memory shortage
     */
    public static $select_chunk_size = 500;

    /**
     * the maximum age in days corresponding to 2147483648 seconds to avoid an overflow error (~68 years)
     */
    public static $forever_days = 24855;

    /**
     * String denominating "forever" in efa (java.long.MAX_VALUE)
     */
    public static $forever64 = "9223372036854775807";

    /**
     * Integer to check a validity. If strlen($value) > $forever_len_gt, this is for ever valid.
     */
    public static $forever_len_gt = 13;

    /**
     * 32-bit integer denominating "forever" in efaCloud (2^31 - 1)
     */
    public static $forever32int = 2147483647;

    /**
     * The 'base64' encoding map for the ecrid.
     */
    private static $ecmap = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789*-";

    /* -------------------------------------------------------------------- */
    /* ---- HELPER FUNCTIONS -------------------------------------------- */
    /* -------------------------------------------------------------------- */
    
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
        if (! isset(self::$boolean_fields[$tablename]))
            return $record;
        // fix for boolean (checkbox) values: efa expects "true" or nothing instead of "on" or nothing
        foreach ($record as $key => $value) {
            if (in_array($key, self::$boolean_fields[$tablename])) {
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
     * Checks whether the table name starts with efa2
     * 
     * @param String $tablename
     *            name of table to check
     * @return boolean true, if the table name starts with efa2.
     */
    public static function is_efa_table (String $tablename)
    {
        return (strpos($tablename, "efa2") === 0);
    }

    /**
     * Checks whether the provided $to_check is a valid ecrid
     * 
     * @param String $to_check
     *            String to check
     * @return boolean true, if the $to_check is a valid ecrid.
     */
    public static function is_ecrid (String $to_check)
    {
        if (strlen($to_check) != 12)
            return false;
        for ($i = 0; $i < 12; $i ++)
            if (strpos(self::$ecmap, substr($to_check, $i, 1)) === false)
                return false;
        return true;
    }

    /**
     * Format the 32-bit validity value into a human readable date.
     * 
     * @param int $validity32
     *            the 32-bit validity value: 0 = undefined, 2147483647 = forever valid, other until date
     * @return string the 32-bit validity value as a human readable date
     */
    public static function format_validity32 (int $validity32)
    {
        global $dfmt_d, $dfmt_dt;
        if ($validity32 == self::$forever32int) {
            return i("7998Uw|valid");
        } elseif ($validity32 > 1) {
            return i("aGIzxm|until") . " " . date($dfmt_d, $validity32);
        } else {
            return i("2t62D0|not defined");
        }
    }

    /**
     * Format the 32-bit validity value into a human readable date.
     * 
     * @param String $validityStr
     *            the String formatted 64-bit validity value
     * @return int the 32-bit validity value
     */
    public static function value_validity32 (String $validityStr = null)
    {
        if (is_null($validityStr))
            return 0;
        if (strlen($validityStr) > self::$forever_len_gt) {
            return self::$forever32int;
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
    public static function get_readable_date_time (String $timestamp)
    {
        if (strlen($timestamp) > self::$forever_len_gt)
            return "ewig";
        return date("Y-m-d H:i:s", intval(substr($timestamp, 0, strlen($timestamp) - 3)));
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
        foreach (self::$name_fields[$tablename] as $name_field)
            $name .= $record[$name_field] . " ";
        if (strlen($name) > 0)
            $name = substr($name, 0, strlen($name) - 1);
        return $name;
    }

    /**
     * Get the local table names, currently only for locale 'de' as associative array.
     * 
     * @param String $locale
     *            = "de" the locale to be used. The see ../config/db_layout/names_translated_$locale file must
     *            contain the translations.
     * @return array the associative array with the locale names
     */
    public static function locale_names (String $locale = "de")
    {
        $translations = explode("\n", file_get_contents("../config/db_layout/names_translated_$locale"));
        $locale_names = [];
        foreach ($translations as $translation) {
            $parts = explode("=", $translation, 2);
            $locale_name = (strlen($parts[1]) > 0) ? $parts[1] : $parts[0];
            $locale_names[$parts[0]] = $locale_name;
        }
        return $locale_names;
    }

    /**
     * Return the key of a record as associative array. This is preferred the ecrid, or if unavailable, the
     * efa data key is returned.
     * 
     * @param String $tablename
     *            name of table to identify the key fields
     * @param array $record
     *            record to get key for.
     * @return mixed the key as associative array, if a valid key is provided, else false.
     */
    public static function get_record_key (String $tablename, array $record)
    {
        if (isset($record["ecrid"]))
            return ["ecrid" => $record["ecrid"]
            ];
        else
            return self::get_data_key($tablename, $record);
    }

    /**
     * Return the EFA DATA KEY of a record as associative array with all values as defined in
     * self::$efa_data_key_fields[$tablename]. If one of these key fields is missing within the record, false
     * is returned.
     * 
     * @param String $tablename
     *            name of table to identify the key fields
     * @param array $record
     *            record to get key for.
     * @return mixed the key as associative array, if for all key fields a value is provided, else false.
     */
    public static function get_data_key (String $tablename, array $record)
    {
        $data_key = [];
        $keys = self::$efa_data_key_fields[$tablename];
        foreach ($keys as $key) {
            if (isset($record[$key]))
                $data_key[$key] = $record[$key];
            else
                return false;
        }
        return $data_key;
    }

    /**
     * Check whether two records are equal in their values. This includes all fields. Equality is checked
     * after String conversion and NULL or a key not set is regarded equal to "". Example: [ "a" => 123, "b"
     * => '', "c" => null ] is equal to [ "a" => 123, "b" => null ], but not to [ "a" => 0, "b" => '-', "c" =>
     * null ].
     * 
     * @param array $a
     *            first record
     * @param array $b
     *            second record
     * @param bool $echo_diff
     *            set true to echo any detected difference. THis echoing will not be translated, always
     *            English.
     * @return boolean true, if equal, else false.
     */
    public static function records_are_equal (array $a, array $b, bool $echo_diff)
    {
        // check for extra $a keys
        foreach ($a as $key => $value) {
            if (! array_key_exists($key, $b) && ! is_null($a[$key]) && (strlen($a[$key]) > 0)) {
                if ($echo_diff)
                    echo "<br>Extra key for a: '" . $key . "'<br>";
                return false;
            }
        }
        // check for extra $b keys
        foreach ($b as $key => $value) {
            if (! array_key_exists($key, $a) && ! is_null($b[$key]) && (strlen($b[$key]) > 0)) {
                if ($echo_diff)
                    echo "<br>Extra key for b: '" . $key . "'<br>";
                return false;
            }
        }
        // check all values
        foreach ($a as $key => $value) {
            // check for identical null values - null is equivalent to ""
            if (is_null($a[$key]) || (strlen(strval($a[$key])) == 0)) {
                if (! is_null($b[$key]) && (strlen(strval($b[$key])) > 0)) {
                    if ($echo_diff)
                        echo "<br>Empty to value mismatch for key: '" . $key . "'<br>";
                    return false;
                }
            } else {
                // conpare existing as String, because the SQL data interface also provides Strings
                if (strcmp(strval($a[$key]), strval($b[$key])) !== 0) {
                    if ($echo_diff)
                        echo "<br>Value mismatch for key: '" . $key . "'<br>";
                    return false;
                }
            }
        }
        // everything's equal.
        return true;
    }

    /* -------------------------------------------------------------------- */
    /* ---- DATA MODIFICATION FUNCTIONS ----------------------------------- */
    /* -------------------------------------------------------------------- */
    
    /**
     * Get the next free key value for the four efa2 tables which have numeric IDs (see
     * Efa_tables::$efa_autoincrement_fields for a list).
     * 
     * @param String $tablename
     *            the table for which the maximum numeric part of the key shall be found
     * @param String $logbookname
     *            the logbookname in case the table is the logbook to look only into the correct split part.
     * @param Tfyh_socket $socket
     *            The data base socket to retrieve the persons names for the AllCrewNames field. This is
     *            passed in oder to make this and all caller functions static.
     * @return int next key value to use
     */
    public static function autoincrement_key_field (String $tablename, String $logbookname, 
            Tfyh_socket $socket)
    {
        $field_name = self::$efa_autoincrement_fields[$tablename];
        if (strcasecmp($tablename, "efa2logbook") == 0) {
            // efa2logbook
            $top_records = $socket->find_records_sorted_matched($tablename, 
                    ["Logbookname" => $logbookname
                    ], 1, "=", $field_name, false);
        } elseif (array_key_exists($tablename, self::$efa_autoincrement_fields)) {
            // efa2messages, efa2boatdamages, efa2boatreservations
            $top_records = $socket->find_records_sorted_matched($tablename, [], 1, "", $field_name, false);
        } else
            return 0;
        $max_value = intval($top_records[0][$field_name]);
        $max_value ++;
        return $max_value;
    }

    /* -------------------------------------------------------------------- */
    /* ---- VIRTUAL FIELD SUPPORT FUNCTIONS ------------------------------- */
    /* -------------------------------------------------------------------- */
    
    /**
     * Compile a AllCrewIds String based on the Cox and Crew of a logbook record
     * 
     * @param array $record
     *            the logbook record to use.
     * @return string the AllCrewIds field
     */
    private static function create_AllCrewIds_field (array $record)
    {
        $allCrewIds = (isset($record["CoxId"]) && (strlen($record["CoxId"]) > 0)) ? $record["CoxId"] . "," : "";
        for ($i = 1; $i <= 24; $i ++) {
            $fId = "Crew" . $i . "Id";
            $allCrewIds .= (isset($record[$fId]) && (strlen($record[$fId]) > 0)) ? $record[$fId] . "," : "";
        }
        if (strlen($allCrewIds) > 0)
            $allCrewIds = mb_substr($allCrewIds, 0, mb_strlen($allCrewIds) - 1);
        if (strlen($allCrewIds) == 0)
            $allCrewIds = "-"; // if the field is left empty it will always be re-generated
        return $allCrewIds;
    }

    /**
     * Compile a AllCrewNames String based on the Cox and Crew of a logbook record
     * 
     * @param array $record
     *            the logbook record to use.
     * @param $toolbox Tfyh_toolbox
     *            the common toolbox
     * @param Tfyh_socket $socket
     *            the data base socket to use. This is passed in oder to make this and all caller functions
     *            static.
     * @param Efa_uuids $efa_uuids
     *            a uuid to name resolver, to speed up the process. May be omitted, if not available.
     * @return string the AllCrewNames field, or '-', if there is no crew name (e.g. deleted records).
     */
    private static function create_AllCrewNames_field (array $record, Tfyh_toolbox $toolbox, 
            Tfyh_socket $socket, Efa_uuids $efa_uuids = null)
    {
        $allCrewIds = explode(",", self::create_AllCrewIds_field($record));
        $resolvedNames = [];
        if (is_null($efa_uuids)) {
            foreach ($allCrewIds as $uuid) {
                $person_last_records = $socket->find_records_sorted_matched("efa2persons", 
                        ["Id" => $uuid
                        ], 1, "=", "InvalidFrom", false);
                if ($person_last_records !== false)
                    $resolvedNames[$uuid] = self::virtual_full_name($person_last_records[0]["FirstName"], 
                            $person_last_records[0]["LastName"], $toolbox);
            }
        } else {
            foreach ($allCrewIds as $uuid)
                $resolvedNames[$uuid] = $efa_uuids->resolve_UUID($uuid)[1];
        }
        $allCrewNames = (isset($record["CoxId"]) && (strlen($record["CoxId"]) > 0)) ? $resolvedNames[$record["CoxId"]] .
                 ", " : ((isset($record["CoxName"]) && (strlen($record["CoxName"]) > 0)) ? $record["CoxName"] .
                 ", " : "");
        for ($i = 1; $i <= 24; $i ++) {
            $fId = "Crew" . $i . "Id";
            $fName = "Crew" . $i . "Name";
            $allCrewNames .= (isset($record[$fId]) && (strlen($record[$fId]) > 0) &&
                     isset($resolvedNames[$record[$fId]])) ? $resolvedNames[$record[$fId]] . ", " : ((isset(
                            $record[$fName]) && (strlen($record[$fName]) > 0)) ? $record[$fName] . ", " : "");
        }
        if (strlen($allCrewNames) > 1)
            $allCrewNames = mb_substr($allCrewNames, 0, mb_strlen($allCrewNames) - 2);
        if (strlen($allCrewNames) == 0)
            $allCrewNames = "-"; // if the field is left empty it will always be re-generated
        return $allCrewNames;
    }

    /**
     * Get the full name according to the configured syntax. If both $first and $last are empty, '-' is
     * returned.
     * 
     * @param String $first
     *            first name
     * @param String $last
     *            last name
     * @param Tfyh_toolbox $toolbox
     *            the toolbox carrying the configuration.
     * @return String the full name
     */
    public static function virtual_full_name (String $first = null, String $last = null, Tfyh_toolbox $toolbox)
    {
        if ((!isset($first) || (strlen($first) == 0)) && (!isset($last) || (strlen($last) == 0)))
            return "-";
        if (strcasecmp($toolbox->config->get_cfg()["efa_NameFormat"], "FIRSTLAST") == 0)
            $full_name = $first . " " . $last;
        else
            $full_name = $last . ", " . $first;
        return trim($full_name);
    }

    /**
     * Add all virtual fields which shall be system generated, like FirstLastName. All of those will at least
     * contain one character and be it a blank to avoid repetitive recreation.
     * 
     * @param array $record_to_modify
     *            the record which shall be modified in the data base and therefore get all missing system
     *            fields.
     * @param String $tablename
     *            the table's name to know which fields are system fields.
     * @param Tfyh_toolbox $toolbox
     *            the common toolbox.This is passed in oder to make this and all caller functions static.
     * @param Tfyh_socket $socket
     *            The data base socket to retrieve the persons names for the AllCrewNames field. This is
     *            passed in oder to make this and all caller functions static.
     * @param Efa_uuids $efa_uuids
     *            a uuid to name resolver, to speed up the process. May be omitted, if not available.
     * @return array the completed record or false if nothing was changed.
     */
    public static function add_virtual_fields (array $record_to_modify, String $tablename, 
            Tfyh_toolbox $toolbox, Tfyh_socket $socket, Efa_uuids $efa_uuids = null)
    {
        global $dfmt_d, $dfmt_dt;
        $changed = false;
        if (strcasecmp($tablename, "efa2boatreservations") == 0) {
            // VirtualBoat, VirtualPerson need lookup
            if (! is_null($efa_uuids)) {
                $virtual_boat = $efa_uuids->resolve_UUID($record_to_modify["BoatId"] ?? "")[1];
                $virtual_fullname = $efa_uuids->resolve_UUID($record_to_modify["PersonId"] ?? "")[1];
            } else {
                $boat_last_records = $socket->find_records_sorted_matched("efa2boats", 
                        ["Id" => $record_to_modify["BoatId"]
                        ], 1, "=", "InvalidFrom", false);
                $virtual_boat = $boat_last_records[0]["Name"];
                $person_last_records = $socket->find_records_sorted_matched("efa2persons", 
                        ["Id" => $record_to_modify["PersonId"]
                        ], 1, "=", "InvalidFrom", false);
                $virtual_fullname = self::virtual_full_name($person_last_records[0]["FirstName"], 
                        $person_last_records[0]["LastName"], $toolbox);
            }
            // A field with a single blank will be returned as empty by the database.
            // that will trigger a repetitive re-adding, if first and last name are empty
            if (strlen($virtual_fullname) == 1)
                $virtual_fullname = "-";
            // VirtualReservationDate w/o lookup
            $virtual_reservation_date = date($dfmt_d, strtotime($record_to_modify["DateFrom"] ?? "")) . " " .
                     substr($record_to_modify["TimeFrom"] ?? "", 0, 5) . " - " .
                     date($dfmt_d, strtotime($record_to_modify["DateTo"] ?? "")) . " " .
                     substr($record_to_modify["TimeTo" ?? ""], 0, 5);
            if (strcasecmp($record_to_modify["VirtualBoat"], $virtual_boat) != 0) {
                $record_to_modify["VirtualBoat"] = $virtual_boat;
                $changed = true;
            }
            if (strcasecmp($record_to_modify["VirtualPerson"] ?? "", $virtual_fullname) != 0) {
                $record_to_modify["VirtualPerson"] = $virtual_fullname;
                $changed = true;
            }
            if (strcasecmp($record_to_modify["VirtualReservationDate"] ?? "", $virtual_reservation_date) != 0) {
                $record_to_modify["VirtualReservationDate"] = $virtual_reservation_date;
                $changed = true;
            }
        } elseif (strcasecmp($tablename, "efa2clubwork") == 0) {
            // TODO: lookup of Name-Affix in efa_uuids. To avoid multiple lookup of persons
            $person_last_records = $socket->find_records_sorted_matched("efa2persons", 
                    ["Id" => $record_to_modify["PersonId"]
                    ], 1, "=", "InvalidFrom", false);
            $virtual_name_affix = $person_last_records[0]["NameAffix"] ?? "";
            $record_name_affix = $record_to_modify["NameAffix"] ?? "";
            if (strcasecmp($record_name_affix, $virtual_name_affix) != 0) {
                $record_to_modify["NameAffix"] = $virtual_name_affix;
                $changed = true;
            }
        } elseif (strcasecmp($tablename, "efa2fahrtenabzeichen") == 0) {
            // TODO all "GUI_" ... fields VORNAME, NACHNAME, JAHRGANG, ANZANBZEICHEN, ANZKM,
            // ANZABZEICHENAB,
            // ANZKMAB, LETZTESJAHR, LETZEKM, LETZTESDATUM, VERSION, SCHLUESSEL, SIGNATUR, STATUS.
        } elseif (strcasecmp($tablename, "efa2logbook") == 0) {
            $all_crew_ids = self::create_AllCrewIds_field($record_to_modify);
            if (! isset($record_to_modify["AllCrewIds"]) ||
                     strcasecmp($record_to_modify["AllCrewIds"], $all_crew_ids) != 0) {
                $record_to_modify["AllCrewIds"] = $all_crew_ids;
                $changed = true;
            }
            $all_crew_names = self::create_AllCrewNames_field($record_to_modify, $toolbox, $socket, 
                    $efa_uuids);
            if (! isset($record_to_modify["AllCrewNames"]) ||
                     strcasecmp($record_to_modify["AllCrewNames"], $all_crew_names) != 0) {
                $record_to_modify["AllCrewNames"] = $all_crew_names;
                $changed = true;
            }
        } elseif (strcasecmp($tablename, "efa2persons") == 0) {
            $virtual_fullname = self::virtual_full_name($record_to_modify["FirstName"] ?? "", 
                    $record_to_modify["LastName"] ?? "", $toolbox);
            if (strlen($virtual_fullname) == 1)
                $virtual_fullname = "-";
            if (! isset($record_to_modify["FirstLastName"]) ||
                     (strcasecmp($record_to_modify["FirstLastName"], $virtual_fullname) != 0)) {
                $record_to_modify["FirstLastName"] = $virtual_fullname;
                $changed = true;
            }
        }
        if ($changed)
            return $record_to_modify;
        else
            return false;
    }

    /**
     * Register a modification in order to trigger client synchronisation: increase ChangeCount and
     * LastModified by 1 and 1sec respectively. In contrast to Efa_tables::add_system_fields() this always
     * overwrites any existing values.
     * 
     * @param array $to_record
     *            record to be changed
     * @param int $last_modified_secs
     *            time to be used for LastModified in seconds, "000" will be appended
     * @param String $change_count
     *            current change count as String (like the record is provided by $socket)
     * @param String $last_modification
     *            modification to register
     * @return array the record modified to reflect the modification.
     */
    public static function register_modification (array $to_record, int $last_modified_secs, 
            String $change_count, String $last_modification)
    {
        $to_record["LastModified"] = strval($last_modified_secs) . "000";
        $to_record["ChangeCount"] = strval(intval($change_count) + 1);
        $to_record["LastModification"] = $last_modification;
        return $to_record;
    }

    /**
     * Add all fields which shall be generated by the server. The policy: 1. for insert only: autoincrement
     * fields (self::$efa_autoincrement_fields) and owner ("ecrown") are set or overwritten, 2. Statistic IDs
     * (ecrid, UUID) and record version management values "LastModified", "LastModification" and "ChangeCount"
     * are set, if missing. See Efa_tables::$server_gen_fields for a list.
     * 
     * @param array $record_to_modify
     *            the record which shall be modified in the data base and therefore get all missing system
     *            fields.
     * @param String $tablename
     *            the table's name to know which fields are system fields.
     * @param int $mode
     *            Set to mode of operations: 1 = insert, 2 = update.
     * @param int $efaCloudUserID
     *            The user ID which is put to the ecrown field if empty.
     * @param Tfyh_socket $socket
     *            The data base socket to autoincrement the Sequence counters. This is passed in oder to make
     *            this and all caller functions static.
     * @return array the completed record
     */
    public static function add_system_fields_APIv3 (array $record_to_modify, String $tablename, int $mode, 
            int $efaCloudUserID, Tfyh_socket $socket)
    {
        // always set the owner and autoincrement field for insert
        if ($mode == 1) {
            if (in_array("ecrown", self::$server_gen_fields[$tablename]))
                $record_to_modify["ecrown"] = $efaCloudUserID;
            if (array_key_exists($tablename, self::$efa_autoincrement_fields)) {
                $logbookname = (isset($record_to_modify["Logbookname"])) ? $record_to_modify["Logbookname"] : "";
                $record_to_modify[self::$efa_autoincrement_fields[$tablename]] = self::autoincrement_key_field(
                        $tablename, $logbookname, $socket);
            }
        }
        // fill in the other system fields, if not existing
        foreach (self::$server_gen_fields[$tablename] as $server_gen_field) {
            if (! isset($record_to_modify[$server_gen_field]) ||
                     (strlen($record_to_modify[$server_gen_field]) == 0)) {
                // record version management fields
                if (strcasecmp($server_gen_field, "LastModified") == 0)
                    $record_to_modify[$server_gen_field] = time() . "000";
                elseif (strcasecmp($server_gen_field, "LastModification") == 0)
                    $record_to_modify[$server_gen_field] = ($mode == 1) ? "insert" : "update";
                elseif (strcasecmp($server_gen_field, "ChangeCount") == 0)
                    $record_to_modify[$server_gen_field] = (isset($record_to_modify[$server_gen_field])) ? intval(
                            $record_to_modify[$server_gen_field]) + 1 : 1;
                // Statistic IDs
                elseif (strcasecmp($server_gen_field, "ecrid") == 0)
                    $record_to_modify[$server_gen_field] = self::generate_ecrids(1)[0];
                elseif (strcasecmp($server_gen_field, "Id") == 0)
                    $record_to_modify[$server_gen_field] = Tfyh_toolbox::static_create_GUIDv4();
            }
        }
        return $record_to_modify;
    }

    /**
     * For API level below V3 it is assumed, that all checks and system field generation is performed on the
     * client side, except the efaCloud system fields ecrid, ecrown, LastModification. For $mode == 1 ecrid
     * and ecrown are set, for all modes LastModification is set. All othe fields are unchanged.
     * 
     * @param array $record_to_modify
     *            the record which shall be modified in the data base and therefore get all missing system
     *            fields.
     * @param String $tablename
     *            the table's name to know which fields are system fields.
     * @param int $mode
     *            Set to mode of operations: 1 = insert, 2 = update.
     * @param int $efaCloudUserID
     *            The user ID which is put to the ecrown field if empty.
     * @return array the completed record
     */
    public static function add_system_fields_APIv1v2 (array $record_to_modify, String $tablename, int $mode, 
            int $efaCloudUserID)
    {
        if ($mode == 1) {
            if (in_array("ecrown", self::$server_gen_fields[$tablename]))
                $record_to_modify["ecrown"] = $efaCloudUserID;
            if (! isset($record_to_modify["ecrid"]) || (strlen($record_to_modify["ecrid"]) < 10))
                $record_to_modify["ecrid"] = self::generate_ecrids(1)[0];
        }
        $record_to_modify["LastModification"] = ($mode == 1) ? "insert" : "update";
        return $record_to_modify;
    }

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
}
