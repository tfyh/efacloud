<?php
/**
 * The form for user profile self service. Based on the Tfyh_form class, please read instructions their to
 * better understand this PHP-code part.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
// ===== page does not need an active session
include_once "../classes/init.php";
include_once '../classes/tfyh_form.php';
include_once '../classes/efa_tables.php';
include_once '../classes/efa_record.php';
$efa_record = new Efa_record($toolbox, $socket);

// === APPLICATION LOGIC ==============================================================
if (! isset($_SESSION["getps"][$fs_id]["table"]) || ! isset($_SESSION["getps"][$fs_id]["ecrid"]))
    $toolbox->display_error(i("8ARdau|Not allowed."), 
            i("6skWc5|Page °%1° must be called...", $user_requested_file), __FILE__);
$tablename = $_SESSION["getps"][$fs_id]["table"];
$ecrid = $_SESSION["getps"][$fs_id]["ecrid"];
$add_new = strcasecmp($ecrid, "new") == 0;
$app_user_id = $_SESSION["User"][$toolbox->users->user_id_field_name];
if ($add_new) {
    $record = ["ValidityFromDate" => date("Y-m-d"),"ValidityFromTime" => date("H:i")
    ];
} else {
    $record = $socket->find_record_matched($tablename, ["ecrid" => $ecrid
    ]);
    if ($record === false)
        $toolbox->display_error(i("M19SkN|Not found"), 
                i("xh2TM5|The record to be changed...", $ecrid, $tablename), __FILE__);
}

// the form templates to use for data edit, depending on the chosen table
$form_templates = ["efa2autoincrement" => 2,"efa2boatdamages" => 3,"efa2boatreservations" => 4,
        "efa2boats" => 5,"efa2boatstatus" => 6,"efa2clubwork" => 7,"efa2crews" => 8,"efa2destinations" => 9,
        "efa2fahrtenabzeichen" => 10,"efa2groups" => 11,"efa2logbook" => 12,"efa2messages" => 13,
        "efa2persons" => 14,"efa2sessiongroups" => 15,"efa2statistics" => 16,"efa2status" => 17,
        "efa2waters" => 18
];

// the lookup tables needed as in efaWeb to auto-fill the id/name fields
$lookups_needed = ["efa2autoincrement" => "",
        "efa2boatdamages" => "efaWeb_boats:BoatId;" . "efaWeb_persons:ReportedByPersonId,FixedByPersonId",
        "efa2boatreservations" => "efaWeb_boats:BoatId;" . "efaWeb_persons:PersonId","efa2boats" => "",
        "efa2boatstatus" => "efaWeb_boats:BoatId","efa2clubwork" => "",
        "efa2crews" => "efaWeb_persons:CoxId,Crew1Id,Crew2Id,Crew3Id,Crew4Id,Crew5Id,Crew6Id,Crew7Id," .
                 "Crew8Id,Crew9Id,Crew10Id,Crew11Id,Crew12Id,Crew13Id,Crew14Id,Crew15Id,Crew16Id,Crew17Id," .
                 "Crew18Id,Crew19Id,Crew20Id,Crew21Id,Crew22Id,Crew23Id,Crew24Id",
                "efa2destinations" => "efaWeb_waters:WatersIdList",
                "efa2groups" => "efaWeb_persons:MemberIdList,LookupPersonId",
                "efa2logbook" => "efaweb_virtual_boatVariants:BoatId;" . "efaWeb_destinations:DestinationId;" .
                 "efaWeb_persons:CoxId,Crew1Id,Crew2Id,Crew3Id,Crew4Id,Crew5Id,Crew6Id,Crew7Id," .
                 "Crew8Id,Crew9Id,Crew10Id,Crew11Id,Crew12Id,Crew13Id,Crew14Id,Crew15Id,Crew16Id,Crew17Id," .
                 "Crew18Id,Crew19Id,Crew20Id,Crew21Id,Crew22Id,Crew23Id,Crew24Id;" .
                 "efaWeb_sessiongroups:SessionGroupId;" . "efaWeb_waters:WatersIdList","efa2messages" => "",
                "efa2persons" => "efaWeb_boats:DefaultBoatId;" . "efaWeb_status:StatusId",
                "efa2sessiongroups" => "","efa2statistics" => "","efa2status" => "","efa2waters" => ""
];

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = 1; // Special form setup: layouts 2 .. 18 are alternative layouts, see $form_templates,
           // i.e. start with 2 .. 18, continue with 19
$form_errors = "";
$form_layout = "../config/layouts/dataedit_" . $form_templates[$tablename];

// ======== start with form filled in last step: check of the entered values.
if ($done == 0) {
    // create form layout based on the table used for data edit.
} else {
    $form_filled = new Tfyh_form($form_layout, $socket, $toolbox, $done, $fs_id);
    $entered_data = [];
    $changed_data = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, "changed-") === 0)
            $changed_data[substr($key, strlen("changed-"))] = $value;
        else
            $entered_data[$key] = $value;
    }
    
    // fix for boolean (checkbox) values: efa expects "true" or nothing instead of "on" or nothing
    $changed_data = Efa_tables::fix_boolean_text($tablename, $changed_data);
    // register changes and try the new record for update
    $changes = "";
    foreach ($record as $key => $value) {
        if (isset($changed_data[$key]) &&
                 (strcasecmp(strval($record[$key]), strval($changed_data[$key])) !== 0)) {
            $changes .= $key . ": '" . strval($record[$key]) . "' => '" . strval($changed_data[$key]) . "'<br>";
            $record[$key] = $changed_data[$key];
        }
    }
    $is_versionized_table = in_array($tablename, Efa_tables::$versionized_table_names);
    $new_boat = false;
    $wayOfChange = 0;
    $current_record_ecrid = $record["ecrid"];
    if ($is_versionized_table) {
        $wayOfChange = intval($entered_data["wayOfChange"]);
    }
    if ((strlen($changes) == 0) && ($wayOfChange == 0) && ! $add_new) {
        $changes = i("5rekMK|No changes made.");
    } else {
        // ------------------------------------------------------------------------------------
        // -------------- versionized records -------------------------------------------------
        // ------------------------------------------------------------------------------------
        if ($is_versionized_table) {
            if ($add_new) {
                // set invalid from, but no other field
                $valid_from = (isset($entered_data["ValidityFromDate"]) &&
                         (strlen($entered_data["ValidityFromDate"]) > 0)) ? strtotime(
                                $entered_data["ValidityFromDate"] . " " . $entered_data["ValidityFromTime"]) .
                         "000" : 0;
                if ($valid_from == 0)
                    $form_errors .= i("Yhu6l5|The validity start date ...");
                else {
                    $new_record = [];
                    $new_record["Id"] = Tfyh_toolbox::static_create_GUIDv4();
                    $new_record["ValidFrom"] = $valid_from;
                    $new_record["InvalidFrom"] = Efa_tables::$forever64;
                    $column_names = $socket->get_column_names($tablename);
                    foreach ($changed_data as $key => $value) {
                        if (in_array($key, $column_names))
                            $new_record[$key] = $changed_data[$key];
                    }
                    $new_record = Efa_tables::register_modification($new_record, time(), 0, "insert");
                    $validated_record = $efa_record->validate_record_APIv3($tablename, $new_record, 1, 
                            $app_user_id, false);
                    if (is_array($validated_record))
                        $modify_result = $efa_record->modify_record($tablename, $validated_record, 1, 
                                $app_user_id, false);
                    else
                        $modify_result = $validated_record;
                    if (strlen($modify_result) == 0) {
                        $new_ecrid = $validated_record["ecrid"];
                        $changes .= i("B0quSZ|A record with the unique...", $new_ecrid, $tablename) . " " .
                                 "<a href='../pages/view_record.php?table=" . $tablename . '&ecrid=' .
                                 $new_ecrid . "'>" . i("FlfcWr|view") . "</a>. ";
                        if (strcasecmp($tablename, "efa2boats") == 0)
                            $new_boat = $validated_record["Id"]; // add a new boat status below.
                    } else
                        $form_errors .= $modify_result . "<br>" . i("sYfgN8|The record was not creat...");
                }
            } else { // update. Ways of change = 0=change content;1=change InvalidFrom;2=create new version
                $record_valid_from32 = (isset($record["ValidFrom"])) ? Efa_tables::value_validity32(
                        $record["ValidFrom"]) : 0;
                unset($record["ValidFrom"]); // when changing a record, the ValidFrom must never change
                if ($wayOfChange > 0) {
                    // set invalid from, but no other field
                    $invalid_from = (isset($entered_data["ValidityFromDate"]) &&
                             (strlen($entered_data["ValidityFromDate"]) > 0)) ? strtotime(
                                    $entered_data["ValidityFromDate"] . " " . $entered_data["ValidityFromTime"]) .
                             "000" : Efa_tables::$forever64;
                    $record["InvalidFrom"] = $invalid_from;
                    $changes .= "InvalidFrom => " . $invalid_from;
                }
                if ($wayOfChange == 2) {
                    $record_invalid_from32 = Efa_tables::value_validity32($record["InvalidFrom"]);
                    // create a new version
                    if ((strlen($record["InvalidFrom"]) > 15))
                        $form_errors .= i("sOvbNc| ** For the delimitation...", $changes);
                    elseif (($record_invalid_from32 - $record_valid_from32) < 86400)
                        $form_errors .= i("PJnOSl| ** For the delimitation...", $changes);
                    
                    else {
                        $delimited_record = Efa_tables::register_modification($record, time(), 0, "update");
                        $modify_result = $efa_record->modify_record($tablename, $delimited_record, 2, 
                                $app_user_id, false);
                        if (strlen($modify_result) != 0) {
                            $form_errors .= " " . i("Pv4vGW|The old version could no...") . " $modify_result";
                        } else {
                            $changes .= " " . i("pbDY3P|The validity of the old ...");
                            // create new record first, because if this fails, the current one should neither
                            // change
                            $new_record = $record;
                            $new_record["ValidFrom"] = $record["InvalidFrom"];
                            $new_record["InvalidFrom"] = Efa_tables::$forever64;
                            unset($new_record["ecrid"]);
                            $new_record = Efa_tables::register_modification($new_record, time(), 0, "insert");
                            $validated_record = $efa_record->validate_record_APIv3($tablename, $new_record, 1, 
                                    $app_user_id, false);
                            if (is_array($validated_record))
                                $copy_result = $efa_record->modify_record($tablename, $validated_record, 1, 
                                        $app_user_id, false);
                            else
                                $copy_result = $validated_record;
                            if (strlen($copy_result) == 0) {
                                $changes .= ", " . i("GzZrqa|a new version has been c...");
                                $current_record_ecrid = $validated_record["ecrid"];
                                // update the existing by the delimited record
                            } else
                                $changes .= ", " . i("PXYG2n|a new version could not ...") . " $copy_result.";
                        }
                    }
                } else {
                    // changes of the existing version: either data changes (0) or validity period change (1)
                    if (strlen($form_errors) == 0) {
                        $record = Efa_tables::register_modification($record, time(), $record["ChangeCount"], 
                                "update");
                        $validated_record = $efa_record->validate_record_APIv3($tablename, $record, 2, 
                                $app_user_id, false);
                        if (is_array($validated_record))
                            $modify_result = $efa_record->modify_record($tablename, $validated_record, 2, 
                                    $app_user_id, false);
                        else
                            $modify_result = $validated_record;
                    }
                }
                if (strlen($modify_result) > 0)
                    $form_errors .= $modify_result . "<br>" . i("u8kMxd|The changes: %1 cannot b...", 
                            $changes);
            }
        }
        // ----------------------------------------------------------------------------------------
        // -------------- non versionized records -------------------------------------------------
        // ----------------------------------------------------------------------------------------
        // no "else", because for a new boat both the boat and the boat status record will be added
        if (! $is_versionized_table || ($new_boat != false)) {
            if ($add_new) {
                $new_record = [];
                if ($new_boat != false) {
                    $new_record["BoatId"] = $new_boat;
                    $new_record["BaseStatus"] = "AVAILABLE";
                    $new_record["CurrentStatus"] = "AVAILABLE";
                    $new_record["ShowInList"] = "AVAILABLE";
                    $tablename = "efa2boatstatus";
                } else {
                    $column_names = $socket->get_column_names($tablename);
                    foreach ($changed_data as $key => $value) {
                        if (in_array($key, $column_names))
                            $new_record[$key] = $changed_data[$key];
                    }
                }
                $new_record = Efa_tables::register_modification($new_record, time(), 0, "insert");
                $validated_record = $efa_record->validate_record_APIv3($tablename, $new_record, 1, 
                        $app_user_id, false);
                if (is_array($validated_record))
                    $modify_result = $efa_record->modify_record($tablename, $validated_record, 1, 
                            $app_user_id, false);
                else
                    $modify_result = $validated_record;
                if (strlen($modify_result) == 0) {
                    // successful insertion. Increment autoincrement counter first.
                    $current_record_ecrid = $validated_record["ecrid"];
                    $efa_record->update_efa2autoincrement($tablename, $validated_record, $app_user_id);
                    $new_ecrid = $validated_record["ecrid"];
                    $changes .= i("uUKDIW|A record with the unique...", $new_ecrid, $tablename) . " " .
                             "<a href='../pages/view_record.php?table=" . $tablename . '&ecrid=' . $new_ecrid .
                             "'>" . i("4PkFAz|view") . "</a>. ";
                    // successful insertion of a new boat, add a boat status record.
                    if (strcasecmp($tablename, "efa2boats") == 0)
                        $new_boat = $validated_record["Id"]; // add a new boat status below.
                } else
                    $form_errors .= $modify_result . "<br>" . i("oSuy4P|The record was not creat...");
                $modify_result = $efa_record->modify_record($tablename, $new_record, 1, $app_user_id, false);
            } else {
                $record = Efa_tables::register_modification($record, time(), $record["ChangeCount"], "update");
                $validated_record = $efa_record->validate_record_APIv3($tablename, $record, 2, $app_user_id, 
                        false);
                if (is_array($validated_record))
                    $modify_result = $efa_record->modify_record($tablename, $validated_record, 2, 
                            $app_user_id, false);
                else
                    $modify_result = $validated_record;
                if (strlen($modify_result) > 0)
                    $form_errors .= $modify_result . "<br>" . i("s4u2Lr|The changes: %1 cannot b...", 
                            $changes);
            }
        }
    }
    if (strlen($form_errors) > 0)
        $todo = $form_templates[$tablename];
    else
        $todo = count($form_templates) + 2;
}

// create type options for logbook and boats edit.
include_once '../classes/efa_config.php';
$efa_config = new Efa_config($toolbox);
// Boat type categories are needed for boat record editing
$type_categories = ["BOAT" => "TypeType","COXING" => "TypeCoxing","NUMSEATS" => "TypeSeats",
        "RIGGING" => "TypeRigging"
];

// ==== continue with the definition and eventually initialization of form to fill for the next step
if (isset($form_filled) && ($todo < count($form_templates) + 2)) {
    // redo the 'done' form, if the $todo == $done, i. e. the validation failed.
    $form_to_fill = $form_filled;
} else {
    // if it is the start or all is fine, use a form for the coming step.
    $form_to_fill = new Tfyh_form($form_layout, $socket, $toolbox, $todo, $fs_id);
    if ((strcasecmp("efa2logbook", $tablename) == 0) || (strcasecmp("efa2status", $tablename) == 0)) {
        $types_type = (strcasecmp("efa2logbook", $tablename) == 0) ? "SESSION" : "STATUS";
        $types = ["0="
        ];
        foreach ($efa_config->types[$types_type] as $type)
            $types[] = $type["Type"] . "=" . $type["Value"];
        $form_to_fill->select_options = $types;
    } elseif (strcasecmp("efa2boats", $tablename) == 0) {
        $form_to_fill->select_options = [];
        foreach ($type_categories as $type_category => $field_name) {
            $options = ["NOENTRY="
            ];
            foreach ($efa_config->types[$type_category] as $type)
                $options[] = $type["Type"] . "=" . $type["Value"];
            // four boat type variants are forseen as possible. Number will be changeable in javascript
            $form_to_fill->select_options[$field_name . "1"] = $options;
            $form_to_fill->select_options[$field_name . "2"] = $options;
            $form_to_fill->select_options[$field_name . "3"] = $options;
            $form_to_fill->select_options[$field_name . "4"] = $options;
        }
    }
    if ($todo == 1) {
        // fix for boolean (checkbox) values: efa expects "true" or nothing instead of "on" or nothing
        $preset = Efa_tables::fix_boolean_text($tablename, $record);
        
        // add a set of boat variant input fields, one per variant
        if (strcasecmp("efa2boats", $tablename) == 0) {
            foreach (["TypeVariant","TypeType","TypeCoxing","TypeSeats","TypeRigging","TypeDescription"
            ] as $field_name) {
                $values = explode(";", $record[$field_name]);
                $preset["VariantCount"] = count($values);
                for ($i = 1; $i <= 4; $i ++)
                    $preset[$field_name . $i] = (isset($values[$i - 1])) ? $values[$i - 1] : null;
            }
        }
        $form_to_fill->preset_values($preset);
    }
}

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
echo i("V1VijQ| ** Change a record ** &...", "<b>" . Efa_tables::locale_names()[$tablename] . "</b>");
echo $toolbox->form_errors_to_html($form_errors);
echo $form_to_fill->get_html();

if ($todo < count($form_templates) + 2) { // step 1. No special texts for output
    echo '<br /><a href="../pages/view_record.php?table=' . $tablename . '&ecrid=' . $record["ecrid"] .
             '">Bearbeitung abbrechen</a>';
    echo $form_to_fill->get_help_html();
    
    // add variables which shall be passed for lookup support in Javascript.
    echo "<script>";
    echo "var formIsNewRecord = " . (($add_new) ? "true" : "false") . ";\n";
    echo "var formTablename = '" . $tablename . "';\n";
    echo "var formLookupsNeeded = '" . $lookups_needed[$tablename] . "';\n";
    $cfg_app = $toolbox->config->get_cfg();
    echo "var formNameFormat = '" . $cfg_app["efa_NameFormat"] . "';\n";
    echo "var formLookupsCsv = {};\n";
    include_once "../classes/tfyh_list.php";
    $list_args = ["{LastModified}" => "0"
    ];
    foreach (explode(";", $lookups_needed[$tablename]) as $lookup_definition) {
        $listname = explode(":", $lookup_definition)[0];
        if (strcasecmp($listname, "efaweb_virtual_boatVariants") == 0)
            $listname = "efaWeb_boats"; // the variant list will be created within the Javascrip Code for
                                        // efaClouzd & efaWeb
        $include_csv = new Tfyh_list("../config/lists/efaWeb", 0, $listname, $socket, $toolbox, $list_args);
        $csv_str = $include_csv->get_csv($_SESSION["User"]);
        $csv_str = str_replace("`", "\`", $csv_str);
        echo "formLookupsCsv['" . $listname . "'] = `" . $csv_str . "`;\n";
    }
    echo "</script>\n";
    echo $efa_config->pass_on_config();
} else { // the very last form for all edits
    echo i("dm895V| ** The data change is %...", (($form_errors) ? "nicht" : ""));
    echo $changes;
    echo "<br><a href='../pages/view_record.php?table=$tablename&ecrid=" . $current_record_ecrid . "'>" .
             i("JUw3H1|Show current record") . "</a>";
    echo i("gPmeyF|             </p>");
}
echo i("DiRyic|</div>");
end_script();
