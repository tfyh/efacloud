<?php
/**
 * The form for upload and import of multiple data records as csv-tables. 
 * Based on the Form class, please read instructions their to better understand this PHP-code part.
 *
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once '../classes/form.php';

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = $done;
$form_errors = "";
$form_layout = "../config/layouts/efa_xml_importieren";
$tmp_upload_file = "";

// ======== start with form filled in last step: check of the entered values.
if ($done > 0) {
    
    $form_filled = new Form($form_layout, $socket, $toolbox, $toolbox->users->user_table_name, $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    $forms[$done] = $form_filled;
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This only prevents any logic to apply, if form errors
        // occured.
    } elseif ($done == 1) {
        // step 1 form was filled. Values were valid
        if (strlen($_FILES['userfile']["name"]) < 1) {
            // Special case upload error. Userfile can not be checked after
            // being entered, must be checked
            // after upload was tried.
            $form_errors .= "Keine Datei angegeben. bitte noch einmal versuchen.";
        } else {
            $filename = $_FILES['userfile']["name"];
            $fileextension = substr($filename, strrpos($filename, "."));
            $uploadpath = "../uploads/" . $filename;
            copy($_FILES['userfile']["tmp_name"], $uploadpath);
            $isZip = (strcasecmp($fileextension, ".zip") == 0);
            $todo = $done + 1;
        }
    }
}

// ==== continue with the definition and eventually initialization of form to fill in this step
if (($done == 0) || ($todo !== $form_filled->get_index())) {
    // use a new form for the very first form display or the next step.
    if ($done == 0)
        $todo = 1;
        $form_to_fill = new Form($form_layout, $socket, $toolbox, $toolbox->users->user_table_name, $todo, $fs_id);
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
	<h3>Eine eFa-XML-Tabelle oder ein eFa-Backup importieren</h3>
	<p>Hier können Sie eine Tabelle des Fahrtenbuches (XML, z. B.
		efa2persons) oder auch das Fahrtenbuch komplett (Backup-Datei, z.B.
		efaBackup_20200307_210001.zip) importieren. Der Import des kompletten
		Fahrtenbuches kann geraume Zeit dauern.</p>
<?php
if ($todo == 1) { // step 1. Texts for output
    ?>
	<p>
		Achtung, die bestehende Tabelle in der Datenbank wird gelöscht und neu
		angelegt. <br> <b>ALLE BISHERIGEN DATENSÄTZE GEHEN VERLOREN.</b><br>
		Wenn Sie ein komplettes Backup einspielen, wird das ganze Fahrtenbuch
		gelöscht und neu aufgesetzt.
	</p>
	<p>Die Art der Tabelle und das Layout richtet sich nach dem Typ der
		Datei, dazu bitte die Datei-Endung nicht modifizieren (z. B.
		'.efa2persons' oder "efa2boats'). es wird nur das aktuelle Fahrtenbuch
		(Typ '.efa2logbook') nach Meta-Datei importiert.</p>
		<?php
    echo $form_to_fill->get_html(true); // enable file upload
} elseif ($todo == 2) { // step 2. Texts for output
    echo $toolbox->form_errors_to_html($form_errors);
    ?>
	<p>
		Der Datei-Upload mit anschließender Analyse und Import beginnt jetzt.
		Da das etwas dauern kann, wird nun der Fortschritt angezeigt.<br>Uploading: 
		<?php
    echo "file upload for '" . $uploadpath . "' completed.<br>";
    if ($isZip === true) {
        include_once '../classes/efa_tables.php';
        $efa_tables = new Efa_tables($toolbox, $socket);
        $efa_tables->import_zip($_SESSION["User"], $uploadpath);
    } else {
        include_once '../classes/efa_tables.php';
        $efa_tables = new Efa_tables($toolbox, $socket);
        $efa_xml = file_get_contents($uploadpath);
        $efa_tables->import_table($_SESSION["User"], $efa_xml);
    }
    ?>
</p><?php
}
// Help texts and page footer for output.
?>
</div><?php
end_script();
