<?php
/**
 * The form for upload and import of multiple data records as csv-tables. Based on the Tfyh_form class, please
 * read instructions their to better understand this PHP-code part.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once '../classes/tfyh_form.php';

// === APPLICATION LOGIC ==============================================================
$cdir = (isset($_SESSION["getps"][$fs_id]["cdir"])) ? $_SESSION["getps"][$fs_id]["cdir"] : "";
if (strlen($cdir) == 0)
    $cdir = "../uploads";
$tmp_upload_file = "";
if (isset($_GET["top"]) && (intval($_GET["top"]) == 1))
    $_SESSION["fileupload_level_of_top"] = count(explode("/", $cdir)) - 1;

// if validation fails, the same form will be displayed anew with error messgaes
$todo = ($done == 0) ? 1 : $done;
$form_errors = "";
$form_layout = "../config/layouts/dateiablage";

// ======== Start with form filled in last step: check of the entered values.
if ($done > 0) {
    $form_filled = new Tfyh_form($form_layout, $socket, $toolbox, $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This avoids any change, if form errors occured.
    } elseif ($done == 1) {
        // step 1 form was filled. Values were valid
        if (isset($entered_data["VerzeichnisNeu"]) && (strlen($entered_data["VerzeichnisNeu"]) > 0)) {
            $res_mkdir = mkdir($cdir . "/" . $entered_data["VerzeichnisNeu"]);
            $uploadResult = ($res_mkdir === false) ? i("Yel8jV|Unknown error when creat...", 
                    $cdir . "/" . $entered_data["VerzeichnisNeu"]) : i("PeQfHu|The directory was create...");
        } elseif (strlen($_FILES['userfile']["name"]) < 1) {
            // Special case upload error. Userfile can not be checked after
            // being entered, must be checked after upload was tried.
            $form_errors .= i("R8XI15|No file specified. Pleas...");
        } else {
            $tmp_upload_file = file_get_contents($_FILES['userfile']["tmp_name"]);
            if (! $tmp_upload_file)
                $form_errors .= i("a5koMa|Unknown error during upl...");
            else {
                $_SESSION["getps"][$fs_id]["io_file"] = $_FILES['userfile']["name"];
                $result = file_put_contents($cdir . "/" . $_SESSION["getps"][$fs_id]["io_file"], 
                        $tmp_upload_file);
                $uploadResult = ($result === false) ? i("w2qoFy|Unknown error while uplo...", 
                        $cdir . "/" . $_SESSION["getps"][$fs_id]["io_file"]) : i(
                        "Tu6j0f|%1 Bytes were uploaded.", $result);
                $todo = $done + 1;
            }
        }
    }
} elseif (isset($_SESSION["getps"][$fs_id]["dfile"])) {
    $toolbox->return_file_to_user($_SESSION["getps"][$fs_id]["dfile"], "application/x-binary");
} elseif (isset($_SESSION["getps"][$fs_id]["xfile"])) {
    $xfile = $_SESSION["getps"][$fs_id]["xfile"];
    $unlinkres = unlink($xfile);
    if ($unlinkres)
        $uploadResult = i("ETWO97|%1 was deleted.", $xfile);
    else
        $uploadResult = i("RTVMQw|%1 could not be deleted.", $xfile);
    $todo = 2;
} elseif (isset($_SESSION["getps"][$fs_id]["xdir"])) {
    $xdir = $_SESSION["getps"][$fs_id]["xdir"];
    $unlinkres = rmdir($xdir);
    if ($unlinkres)
        $uploadResult = i("aHoVZc|%1 was deleted.", $xdir);
    else
        $uploadResult = i("HlAWGl|%1 could not be deleted.", $xdir);
    $todo = 2;
}

// ==== continue with the definition and eventually initialization of form to fill for the next step
if (isset($form_filled) && ($todo == $form_filled->get_index())) {
    // redo the 'done' form, if the $todo == $done, i. e. the validation failed.
    $form_to_fill = $form_filled;
} else {
    // if it is the start or all is fine, use a form for the coming step.
    $form_to_fill = new Tfyh_form($form_layout, $socket, $toolbox, $todo, $fs_id);
}

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
echo i("ZAjY7v| ** File storage ** Alte...");

$fileupload_level_of_top = isset($_SESSION["fileupload_level_of_top"]) ? $_SESSION["fileupload_level_of_top"] : 1;
echo $toolbox->get_dir_contents($cdir, $fileupload_level_of_top);
echo $toolbox->form_errors_to_html($form_errors);

echo i("Y6Vy5J|</div><div class=°w3-...");
if ($todo == 1) {
    echo $form_to_fill->get_html(true); // enable file upload
    echo $form_to_fill->get_help_html();
} elseif ($todo == 2) { // step 2. Texts for output
    echo "<p>" . $uploadResult . "</p>";
    echo i("a8iDlL| ** Here ** go to the ne...", $cdir);
}

// Help texts and page footer for output.
echo i("AVZqHm|<!-- END OF form --></..."); ?>
end_script();

