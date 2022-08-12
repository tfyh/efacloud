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
$translations = explode("\n", file_get_contents("../config/db_layout/names_translated_de"));
$en2de = [];
$de2en = [];
foreach ($translations as $translation) {
    $parts = explode("=", $translation, 2);
    $de = (strlen($parts[1]) > 0) ? $parts[1] : $parts[0];
    $en2de[$parts[0]] = $de;
    $de2en[$de] = $parts[0];
}

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
        $record = $socket->find_record_matched("Parameter", ["Name" => $efa2table
        ], true);
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
        $records = $socket->find_records_sorted_matched($_SESSION["efa2table"], 
                [$efa2tablefield => $searchvalue
                ], 50, "LIKE", $searchkey, true, false);
        $todo = $done + 1;
    }
    
    // if data sets were selected, create list output. Resolve UUIDs.
    if ($todo == 3) {
        $i = 0;
        $v = 0;
        include_once "../classes/efa_dataedit.php";
        $efa_dataedit = new Efa_dataedit($toolbox, $socket);
        $results_to_show_html = "";
        $date = new DateTime();
        $nowSeconds = $date->getTimestamp();
        $_SESSION["search_result"] = [];
        $efa_tables = new Efa_tables($toolbox, $socket);
        $is_versionized = in_array($_SESSION["efa2table"], $efa_tables->is_versionized);
        $short_info_fields = Efa_tables::$short_info_fields[$_SESSION["efa2table"]];
        if (is_array($records))
            foreach ($records as $record) {
                // PHP version may not be 64 bit, then the max int is 2 billion. Makes the validity
                // check a bit complex.
                $invalid = $is_versionized && (! isset($record["InvalidFrom"]) || ((strlen(
                        $record["InvalidFrom"]) < 15) &&
                         (intval(substr($record["InvalidFrom"], 0, 10)) < $nowSeconds)));
                if ($invalid)
                    $results_to_show_html .= "<span style='color:#aaa'>";
                foreach ($record as $key => $value) {
                    if (in_array($key, $short_info_fields) || (strcasecmp($key, $_SESSION["efa2tablefield"]) == 0)) {
                        if (in_array($key, $efa_tables->timestampFields) && (strlen(strval($value)) > 0))
                            $value = $efa_tables->get_readable_date_time($value);
                        if ((strlen(strval($value)) > 0) && (strcasecmp($key, "ecrhis") !== 0)) {
                            if ((strcasecmp($key, $_SESSION["efa2tablefield"]) == 0) ||
                                     ((strcasecmp($key, "InvalidFrom") == 0) && $invalid)) {
                                $results_to_show_html .= "<b>" . $en2de[$key] . ": '" . $value . "'</b>, ";
                            } else {
                                $results_to_show_html .= $en2de[$key] . ": '" . $value . "', ";
                            }
                        }
                    }
                }
                
                if ($invalid)
                    $results_to_show_html .= "</span>";
                else {
                    $v ++;
                    $_SESSION["search_result"][$v] = $record;
                    $results_to_show_html .= " - <a href='../pages/view_record.php?searchresultindex=" . $v .
                             "'>Details anzeigen</a>";
                }
                $results_to_show_html .= "<br />\n";
                $i ++;
            }
        if ($i === 0) {
            $results_to_show_html = "<b>Hinweis:</b><br>Kein passender Datensatz gefunden.";
        }
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
        $select_options_list = [];
        $table_record_count_list = "";
        $total_record_count = 0;
        $total_table_count = 0;
        foreach ($table_names as $tn) {
            $record_count = $socket->count_records($tn);
            $total_record_count += $record_count;
            $total_table_count ++;
            $select_options_list[] = $tn . "=" . $en2de[$tn] . " [" . $record_count . "]";
            $table_record_count_list .= $en2de[$tn] . " [" . $record_count . "], ";
        }
        $table_record_count_list .= "in Summe [" . $total_record_count . "] Datensätze in " .
                 $total_table_count . " Tabellen.";
    } elseif ($done == 1) {
        $column_names = $socket->get_column_names($efa2table);
        $record_count = $socket->count_records($efa2table);
        $select_options_list = [];
        foreach ($column_names as $cn)
            $select_options_list[] = $cn . "=" . $en2de[$cn];
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
?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Einen Datensatz finden</h3>
	<p>Hier kannst Du einen Datensatz unter Angabe der Tabelle und der zu
		überprüfenden Spalte finden.</p>
</div>

<div class="w3-container">
<?php

echo $toolbox->form_errors_to_html($form_errors);
if ($todo < 3) {
    if ($todo == 1)
        echo "<p>Datensätze pro Tabelle:<br>" . $table_record_count_list . "</p>";
    elseif ($todo == 2)
        echo "<p>Gewählte Tabelle:<br><b>" . $_SESSION["efa2table"] . "</b> mit " . $record_count .
                 " Datensätzen</p>";
    echo $form_to_fill->get_html($fs_id);
    echo '<h5><br />Ausfüllhilfen</h5><ul>';
    echo $form_to_fill->get_help_html();
    echo "</ul>";
} else {
    echo "<p>Tabelle: <b>" . $_SESSION["efa2table"] . "</b><br>";
    echo "Filter: <b>" . $_SESSION["efa2tablefield"] . " = '" . $searchvalue . "'</b></p>";
    echo $results_to_show_html;
}
?></div><?php
end_script();

    