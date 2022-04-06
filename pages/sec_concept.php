<?php
/**
 * A page to audit the complete data base.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

$sec_concept_html = false;
if (isset($_GET["create"]) && (intval($_GET["create"]) >= 1)) {
    include_once "../classes/sec_concept.php";
    $sec_concept = new Sec_concept($toolbox, $socket);
    if ((intval($_GET["create"]) == 1)) {
        $sec_concept_html = $sec_concept->create_HTML();
    }
    elseif ((intval($_GET["create"]) == 2)) {
        $sec_concept_html = $sec_concept->create_HTML();
        $toolbox->return_string_as_zip($sec_concept_html, "efaCloud_SecurityConcept.html");
    }
    elseif ((intval($_GET["create"]) == 3)) {
        $saved_at = $sec_concept->create_PDF();
        copy($saved_at, $saved_at . ".previous");
        $toolbox->return_file_to_user($saved_at, "application/binary");
    }
}

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

if ($sec_concept_html) {
    ?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Sicherheitskonzept für die Anwendung</h3>
	<?php
    echo $sec_concept_html;
    ?>
</div>
<?php
} else {
    ?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Sicherheitskonzept für die Anwendung erzeugen</h3>
	<p>Aus der Anwendung heraus wird ein aktuelles Sicherheitskonzept
		erzeugt, welches für die Dokumentation gemäß DSGVO verwendet werden
		kann. Bitte beachte, dass das Sicherheitskonzept nur die Anwendung
		betrifft. Die Sicherheit des Web-Servers muss an anderer Stelle
		dokumentiert werden oder durch eine entsprechende AVV abgesichert
		sein.</p>
	<p>
		<a class='formbutton' href="?create=1">Jetzt als Webseite anzeigen</a>&nbsp; &nbsp; 
		<a class='formbutton' href="?create=2">html download</a>&nbsp; &nbsp; 
		<a class='formbutton' href="?create=3">PDF download</a>
	</p>
	<?php
}
?>
</div>
<?php
end_script();