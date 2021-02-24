<?php
/**
 * The form for user profile self service.
 * Based on the Form class, please read instructions their to better understand this PHP-code part.
 *
 * @author mgSoft
 */
// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
// ===== page does not need an active session
include_once "../classes/init.php";
include_once '../classes/form.php';

// === APPLICATION LOGIC ==============================================================
$action = (isset($_GET["action"])) ? intval($_GET["action"]) : 0;
$id = (isset($_GET["id"])) ? intval($_GET["id"]) : 0;
// if validation fails, the same form will be displayed anew with error messgaes
$todo = $done;
$form_errors = "";
$form_layout = "../config/layouts/datensatz_finden";
$users_to_show_html = "";

// ======== start with form filled in last step: check of the entered values.
if ($done > 0) {
    
    $form_filled = new Form($form_layout, $socket, $toolbox, "Parameter", $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    $forms[$done] = $form_filled;
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This only prevents any logic to apply, if form errors
        // occured.
    } elseif ($done == 1) {
        $efa2table = $entered_data["Table"];
        $record = $socket->find_record_matched("Parameter", ["Name" => $efa2table
        ], true);
        // keep information on selected table in session variable
        $_SESSION["efa2table"] = $efa2table;
        $todo = $done + 1;
    } elseif ($done == 2) {
        $efa2tablefield = $entered_data["Field"];
        $_SESSION["efa2tablefield"] = $efa2tablefield;
        $searchvalue = "%" . $entered_data["Value"] . "%";
        $records = $socket->find_records_sorted_matched($_SESSION["efa2table"], 
                [$efa2tablefield => $searchvalue
                ], 20, "LIKE", $efa2tablefield, true, false);
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
        if (is_array($records))
            foreach ($records as $record) {
                // PHP version may not be 64 bit, then the max int is 2 billion. Makes the validity
                // check a bit complex.
                $invalid = ($record["InvalidFrom"] && (strlen($record["InvalidFrom"]) < 15) &&
                         (intval(substr($record["InvalidFrom"], 0, 10)) < $nowSeconds));
                if ($invalid)
                    $results_to_show_html .= "<span style='color:#aaa'>";
                foreach ($record as $key => $value) {
                    if (strlen(strval($value)) > 0) {
                        if ($efa_dataedit->isUUID($value))
                            $value = $efa_dataedit->resolve_UUID($value)[1];
                        if ((strcasecmp($key, $_SESSION["efa2tablefield"]) == 0) ||
                                 ((strcasecmp($key, "InvalidFrom") == 0) && $invalid))
                            $results_to_show_html .= "<b>" . $key . ": '" . $value . "'</b>, ";
                        else
                            $results_to_show_html .= $key . ": '" . $value . "', ";
                    }
                }
                
                if ($invalid)
                    $results_to_show_html .= "</span>";
                else {
                    $v ++;
                    $_SESSION["search_result"][$v] = $record;
                    $results_to_show_html .= "<b><a href='../forms/datensatz_aendern.php?searchresult=" . $v .
                             "'>ändern</a></b>";
                }
                $results_to_show_html .= "<br />\n";
                $i ++;
            }
        if ($i === 0) {
            $results_to_show_html = "<b>Hinweis:</b><br>Kein passender Datensatz gefunden.";
        }
    }
}

// ==== continue with the definition and eventually initialization of form to fill in this step
if (($done == 0) || ($todo !== $form_filled->get_index())) {
    // use a new form for the very first form display or the next step.
    $select_options_list = false;
    if ($done == 0) {
        $todo = 1;
        $table_names = $socket->get_table_names(false);
        $select_options_list = [];
        $table_record_count_list = "";
        $total_record_count = 0;
        $total_table_count = 0;
        foreach ($table_names as $tn) {
            $record_count = $socket->count_records($tn, true);
            $total_record_count += $record_count;
            $total_table_count ++;
            $select_options_list[] = $tn . "=" . $tn . " [" . $record_count . "]";
            $table_record_count_list .= $tn . " [" . $record_count . "], ";
        }
        $table_record_count_list .= "in Summe [" . $total_record_count . "] Datensätze in " . $total_table_count .
                 " Tabellen.";
    } elseif ($done == 1) {
        $column_names = $socket->get_column_names($efa2table, true);
        $record_count = $socket->count_records($efa2table, true);
        $select_options_list = [];
        foreach ($column_names as $cn)
            $select_options_list[] = $cn . "=" . $cn;
    }
    $form_to_fill = new Form($form_layout, $socket, $toolbox, $toolbox->users->user_table_name, $todo, $fs_id);
    if ($select_options_list)
        $form_to_fill->select_options = $select_options_list;
} else {
    // or reuse the 'done' form, if validation failed.
    $form_to_fill = $form_filled;
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
        echo "<p>Gewählte Tabelle:<br><b>" . $_SESSION["efa2table"] . "</b> mit " . $record_count . " Datensätzen</p>";
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

    