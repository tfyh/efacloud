<?php
/**
 * The form for upload and import of multiple data records as csv-tables. Based on the Form class, please read
 * instructions their to better understand this PHP-code part.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once '../classes/form.php';

// === APPLICATION LOGIC ==============================================================
$cdir = (isset($_GET["cdir"])) ? $_GET["cdir"] : "";
if (strlen($cdir) > 0)
    $_SESSION["cdir"] = $cdir;
elseif (strlen($_SESSION["cdir"]) > 0)
    $cdir = $_SESSION["cdir"];
else
    $cdir = "../uploads";

// if validation fails, the same form will be displayed anew with error messgaes
$todo = $done;
$form_errors = "";
$form_layout = "../config/layouts/dateiablage";
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
        if (isset($entered_data["VerzeichnisNeu"]) && (strlen($entered_data["VerzeichnisNeu"]) > 0)) {
            $res_mkdir = mkdir($cdir . "/" . $entered_data["VerzeichnisNeu"]);
            $uploadResult = ($res_mkdir === false) ? "Unbekannter Fehler beim Erstellen des Verzeichnisses: '" .
                     $cdir . "/" . $entered_data["VerzeichnisNeu"] . "'" : "Das Verzeichnis wurde erstellt.";
        } elseif (strlen($_FILES['userfile']["name"]) < 1) {
            // Special case upload error. Userfile can not be checked after
            // being entered, must be checked after upload was tried.
            $form_errors .= "Keine Datei angegeben. bitte noch einmal versuchen.";
        } else {
            $tmp_upload_file = file_get_contents($_FILES['userfile']["tmp_name"]);
            if (! $tmp_upload_file)
                $form_errors .= "Unbekannter Fehler beim Hochladen. bitte noch einmal versuchen.";
            else {
                $_SESSION["io_file"] = $_FILES['userfile']["name"];
                $result = file_put_contents($cdir . "/" . $_SESSION["io_file"], $tmp_upload_file);
                $uploadResult = ($result === false) ? "Unbekannter Fehler beim Upload." : $result .
                         " Bytes wurden hochgeladen.";
                $todo = $done + 1;
            }
        }
    }
} elseif (isset($_GET["dfile"])) {
    $toolbox->return_file_to_user($_GET["dfile"], "application/x-binary");
} elseif (isset($_GET["xfile"])) {
    $unlinkres = unlink($_GET["xfile"]);
    if ($unlinkres)
        $uploadResult = $_GET["xfile"] . " wurde gelöscht.";
    else
        $uploadResult = $_GET["xfile"] . " konnte nicht gelöscht werden.";
    $todo = 2;
} elseif (isset($_GET["xdir"])) {
    $unlinkres = rmdir($_GET["xdir"]);
    if ($unlinkres)
        $uploadResult = $_GET["xdir"] . " wurde gelöscht.";
    else
        $uploadResult = $_GET["xdir"] . " konnte nicht gelöscht werden.";
    $todo = 2;
}

// ==== continue with the definition and eventually initialization of form to fill in this step
if (($done == 0) || ($todo !== $form_filled->get_index())) {
    // use a new form for the very first form display or the next step.
    if (($done == 0) && ($todo < 2))
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
	<h3>Dateiablage</h3>
	<p>Alternativ kann auch ein Verzeichnis erstellt werden.</p>
	<?php
echo $toolbox->get_dir_contents($cdir);
echo $toolbox->form_errors_to_html($form_errors);

?>
</div>

<div class="w3-container">
<?php
if ($todo == 1) {
    echo $form_to_fill->get_html(true); // enable file upload
    echo '<h5><br />Ausfüllhilfen</h5><ul>';
    echo $form_to_fill->get_help_html();
    echo "</ul>";
} elseif ($todo == 2) { // step 2. Texts for output
    echo "<p>" . $uploadResult . "</p>";
    ?>
	<p>
		<?php echo "<a href='?cdir=" . $cdir . "'>Hier</a>"; ?> geht es zum nächsten Upload.
	</p>
<?php
}

// Help texts and page footer for output.
?>
	<!-- END OF form -->
</div><?php
end_script();

