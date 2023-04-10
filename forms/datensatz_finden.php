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

$users_to_show_html = "";

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = ($done == 0) ? 1 : $done;
$form_errors = "";
$form_layout = "../config/layouts/datensatz_finden";
$i18n2locale = Efa_tables::locale_names($toolbox->config->language_code);

// ======== Start with form filled in last step: check of the entered values.
if ($done > 0) {
    $form_filled = new Tfyh_form($form_layout, $socket, $toolbox, $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    $forms[$done] = $form_filled;
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This avoids any change, if form errors occured.
    } elseif ($done == 1) {
        $efa2table = $entered_data["Table"];
        // keep information on selected table in session variable
        $_SESSION["efa2table"] = $efa2table;
        $todo = $done + 1;
    } elseif ($done == 2) {
        $efa2tablefield = $entered_data["Field"];
        $searchkey = $efa2tablefield;
        if (strcasecmp($efa2tablefield, "EntryId") == 0)
            $searchkey = "#" . $efa2tablefield;
        $_SESSION["efa2tablefield"] = $efa2tablefield;
        $searchvalue = "%" . $entered_data["Value"] . "%";
        $sort_key = $searchkey;
        if (in_array($_SESSION["efa2table"], Efa_tables::$versionized_table_names))
            $sort_key = (strcasecmp($_SESSION["efa2table"], "efa2persons") == 0) ? "FirstLastName,InvalidFrom" : "Name,InvalidFrom";
        $records = $socket->find_records_sorted_matched($_SESSION["efa2table"], 
                [$efa2tablefield => $searchvalue
                ], 50, "LIKE", $sort_key, true);
        $todo = $done + 1;
    }
    
    // if data sets were selected, create list output. Resolve UUIDs.
    if ($todo == 3) {
        $i = 0;
        $results_to_show_html = "<ul>";
        $date = new DateTime();
        $nowSeconds = time();
        $is_versionized_table = in_array($_SESSION["efa2table"], Efa_tables::$versionized_table_names);
        $short_info_fields = Efa_tables::$short_info_fields[$_SESSION["efa2table"]];
        if (is_array($records)) {
            $r = 0;
            foreach ($records as $record) {
                // filter in case of versionized tables the most recent record.
                $r ++;
                $other_id = ! isset($records[$r]) || ! isset($records[$r]["Id"]) ||
                         (strcmp($record["Id"], $records[$r]["Id"]) != 0);
                if (! $is_versionized_table || ($r == count($records)) || $other_id) {
                    // PHP version may not be 64 bit, then the max int is 2 billion. Makes the validity
                    // check a bit complex.
                    $invalid_from32 = (isset($record["InvalidFrom"])) ? Efa_tables::value_validity32(
                            $record["InvalidFrom"]) : 0;
                    $invalid = $is_versionized_table && ($invalid_from32 < $nowSeconds);
                    $invalid = $invalid || (strcmp($record["LastModification"], "delete") == 0);
                    $results_to_show_html .= "<li>\n";
                    if ($invalid)
                        $results_to_show_html .= "<span style='color:#aaa'>";
                    foreach ($record as $key => $value) {
                        $is_efa2tablefield = (strcasecmp($key, $_SESSION["efa2tablefield"]) == 0);
                        if (in_array($key, $short_info_fields) || $is_efa2tablefield) {
                            if (in_array($key, Efa_tables::$timestamp_field_names) &&
                                     (strlen(strval($value)) > 0))
                                $value = Efa_tables::get_readable_date_time($value);
                            if (in_array($key, Efa_tables::$date_fields[$_SESSION["efa2table"]]) &&
                                     (strlen(strval($value)) > 0))
                                $value = date($dfmt_d, strtotime($value));
                            if ((strlen(strval($value)) > 0) && (strcasecmp($key, "ecrhis") !== 0)) {
                                if ((strcasecmp($key, $_SESSION["efa2tablefield"]) == 0) ||
                                         ((strcasecmp($key, "InvalidFrom") == 0) && $invalid)) {
                                    $results_to_show_html .= "<b>" . $i18n2locale[$key] . ": '" . $value . "'</b>, ";
                                } else {
                                    $results_to_show_html .= $i18n2locale[$key] . ": '" . $value . "', ";
                                }
                            }
                        }
                    }
                    $results_to_show_html .= $i18n2locale["LastModification"] . ": '" . $record["LastModification"] .
                             "', ";
                    if ($invalid) {
                        $results_to_show_html .= "</span>";
                        if (strcmp($record["LastModification"], "delete") == 0)
                            $results_to_show_html .= i("ZgWLaf|Record deleted on") . " " .
                                     date($dfmt_d, Efa_tables::value_validity32($record["LastModified"]));
                        else
                            $results_to_show_html .= "<a href='../forms/datensatz_aendern.php?table=" .
                                     $_SESSION["efa2table"] . "&ecrid=" . $record["ecrid"] . "'>" .
                                     i("V5XtIe|Reopen record") . "</a>";
                    } else {
                        $results_to_show_html .= " - <a href='../pages/view_record.php?table=" .
                                 $_SESSION["efa2table"] . "&ecrid=" . $record["ecrid"] . "'>" .
                                 i("sRQXxQ|Show details") . "</a>";
                        $results_to_show_html .= " - <a href='../forms/datensatz_aendern.php?table=" .
                                 $_SESSION["efa2table"] . "&ecrid=" . $record["ecrid"] . "'>" .
                                 i("nc58pc|Change record") . "</a>";
                    }
                    $results_to_show_html .= "</li>\n";
                    $i ++;
                }
            }
            if ($i === 0)
                $results_to_show_html = "<li><b>" . i("CpLUaw|Hints:") . "</b><br>" .
                         i("bYRqTv|No matching record found...") . "</li>";
        }
        $results_to_show_html .= "</ul>";
    }
}

// ==== continue with the definition and eventually initialization of form to fill for the next step
if (isset($form_filled) && ($todo == $form_filled->get_index())) {
    // redo the 'done' form, if the $todo == $done, i. e. the validation failed.
    $form_to_fill = $form_filled;
} else {
    // create the select lists depending on the table names or table fields.
    $select_options_list = false;
    if ($done == 0) {
        $todo = 1;
        $table_names = $socket->get_table_names();
        $select_options_array = [];
        $table_record_count_array = [];
        $total_record_count = 0;
        $total_table_count = 0;
        foreach ($table_names as $tn) {
            if (Efa_tables::is_efa_table($tn)) {
                $record_count = $socket->count_records($tn);
                $total_record_count += $record_count;
                $total_table_count ++;
                $select_options_array[$i18n2locale[$tn] . " [" . $record_count . "]"] = $tn;
                $table_record_count_array[$tn] = $record_count;
            }
        }
        ksort($select_options_array);
        $select_options_list = [];
        $table_record_count_list = "";
        foreach ($select_options_array as $display => $tn) {
            $select_options_list[] = $tn . "=" . $display;
            $table_record_count_list .= $display . ", ";
        }
        $table_record_count_list .= i("0nSbjg|In sum [%1] records in %...", $total_record_count, 
                $total_table_count);
    } elseif ($done == 1) {
        $column_names = $socket->get_column_names($efa2table);
        $record_count = $socket->count_records($efa2table);
        $select_options_array = [];
        foreach ($column_names as $cn) {
            $localized = strtoupper(mb_substr($i18n2locale[$cn], 0, 1)) . mb_substr($i18n2locale[$cn], 1);
            $select_options_array[$localized] = $cn;
        }
        ksort($select_options_array);
        foreach ($select_options_array as $display => $cn)
            $select_options_list[] = $cn . "=" . $display;
    }
    // if it is the start or all is fine, use a form for the coming step.
    $form_to_fill = new Tfyh_form($form_layout, $socket, $toolbox, $todo, $fs_id);
    if ($select_options_list)
        $form_to_fill->select_options = $select_options_list;
}

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
echo i("fr1H7j| ** Find an efa record ..."); 
echo $toolbox->form_errors_to_html($form_errors);
if ($todo < 3) {
    if ($todo == 2)
        echo "<p>" . i("1vgJ3q|Selected table:") . "<br><b>" . $_SESSION["efa2table"] . "</b> " .
                 i("G3BLoW|with %1 records", $record_count) . "</p>";
    echo $form_to_fill->get_html();
    echo $form_to_fill->get_help_html();
    if ($todo == 1)
        echo "<p>" . i("ltxpNk|Records per table:") . "<br>" . $table_record_count_list . "</p>";
} else {
    echo "<p>" . i("AFDcif|Table:") . " <b>" . $_SESSION["efa2table"] . "</b><br>";
    echo i("tzF0EL|Filter:") . " <b>" . $_SESSION["efa2tablefield"] . " = '" . $searchvalue . "'</b>";
    if ($is_versionized_table)
        echo "<br>" . i("hFKtKU|The list is sorted by na...");
    else
        echo "<br>" . i("cTaWzE|The list is sorted by th...", $sort_key);
    
    echo "</p>";
    echo $results_to_show_html;
}
echo i("OkHOab|</div>");
end_script();

    
