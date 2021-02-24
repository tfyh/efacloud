<?php
/**
 * The form for upload and import of multiple data records as csv-tables. Based on the Form class, please read
 * instructions their to better understand this PHP-code part.
 * 
 * @author mgSoft
 */

// ===== special efacloud field idname conventions
$idnames = ["efa2autoincrement" => "Sequence","efa2boatstatus" => "BoatId","efa2clubwork" => "Id",
        "efa2crews" => "Id","efa2fahrtenabzeichen" => "PersonId","efa2messages" => "MessageId",
        "efa2sessiongroups" => "Id","efa2statistics" => "Id","efa2status" => "Id","efa2waters" => "Id"
];

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once '../classes/form.php';

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = $done;
$form_errors = "";
$form_layout = "../config/layouts/tabelle_importieren";
$tmp_upload_file = "";

// ======== start with form filled in last step: check of the entered values.
if ($done > 0) {
    
    $form_filled = new Form($form_layout, $socket, $toolbox, $toolbox->users->user_table_name, $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    $forms[$done] = $form_filled;
    $idname = (isset($entered_data["Tabelle"]) && isset($idnames[$entered_data["Tabelle"]])) ? $idnames[$entered_data["Tabelle"]] : "ID";
    
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
            $tmp_upload_file = file_get_contents($_FILES['userfile']["tmp_name"]);
            if (! $tmp_upload_file)
                $form_errors .= "Unbekannter Fehler beim Hochladen. bitte noch einmal versuchen.";
            else {
                $_SESSION["io_file"] = $_FILES['userfile']["name"];
                $_SESSION["io_table"] = $entered_data["Tabelle"];
                file_put_contents("../log/io/" . $_SESSION["io_file"], $tmp_upload_file);
                // do import verification
                $import_result = $socket->import_table_from_csv(
                        $_SESSION["User"][$toolbox->users->user_id_field_name], $_SESSION["io_table"], 
                        "../log/io/" . $_SESSION["io_file"], true, $idname);
                // only move on, if import did not return an error.
                if (strcmp(substr($import_result, 0, 1), "#") != 0)
                    $todo = $done + 1;
                else
                    $form_errors .= $import_result;
            }
        }
    } elseif ($done == 2) {
        // step 2 form was filled. Values were valid. Now execute import.
        $import_result = $socket->import_table_from_csv(
                $_SESSION["User"][$toolbox->users->user_id_field_name], $_SESSION["io_table"], 
                "../log/io/" . $_SESSION["io_file"], false, $idname);
        $todo = $done + 1;
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
	<h3>Tabelle importieren</h3>
	<p>Hier kann eine Tabelle der Datenbank importiert werden.</p>
<?php
if ($todo == 1) { // step 1. Texts for output
    ?>
	<p>Beim Import muss in jedem Datensatz die ID angegeben sein.
		Datensätze, die eine bestehende ID haben, werden überschrieben. Alle
		Felder der Tabelle, die einem Feld in der Datenbanktabelle
		entsprechen, werden überschrieben, also auch ggf. gelöscht.
		Datensätze, die eine neue ID haben, werden neu angelegt.</p>
	<p>Zu importierenden Tabellen müssen in der ersten Zeile die Feldnamen
		der Datenbanktabelle ausweisen, Groß-Klein-Schreibung ist relevant.
		Werden ungültige Feldnamen verwendet, kann der Import nicht
		stattfinden. Zu importierenden Tabellen, die aus genau einer Spalte
		bestehen, in der die ID steht, führen zum Löschen der kompletten
		Datensätze mit der jeweiligen ID.</p>
	<p>Im ersten Schritt wird die Tabelle hochgeladen und geprüft. Im
		zweiten Schritt findet der Import statt. Dieser muss explizit
		bestätigt werden.</p>
		<?php
    echo $toolbox->form_errors_to_html($form_errors);
    echo $form_to_fill->get_html(true); // enable file upload
} elseif ($todo == 2) { // step 2. Texts for output
    ?>
	<p>Der Datei-Upload war erfolgreich. Im Folgenden ist dargestellt, was
		importiert wird. Bitte achte auf den Hinweis auf Importfehler, denn
		das ist in der Regel ein Zeichen dafür, dass mit der Upload-Datei
		irgendetwas nicht stimmt.</p>
	<p>Im nächsten Schritt wird die Tabelle hochgeladen und so, wie
		dargestellt, importiert. Bitte bestätige, dass der Import durchgeführt
		werden soll.</p>
		<?php
    // no form errors possible at this step. just a button clicked.
    echo $import_result;
    echo $form_to_fill->get_html(false);
} elseif ($todo == 3) { // step 3. Texts for output
    echo $import_result;
    ?>
	<p>
		Der Datei-Import wurde durchgeführt. <br /> <a
			href="../pages/mein_profil.php">Hier</a> geht es zurück zur
		persönlichen Startseite.
	</p>
<?php
}

// Help texts and page footer for output.
?>
</div><?php
end_script();