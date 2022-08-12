<?php
/**
 * A page to reset the complete data base.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
$do_reset = (strcmp($_GET["do_reset"], "now") == 0);

if ($do_reset) {
    // ===== create data base
    include_once '../classes/efa_tables.php';
    $efa_tables = new Efa_tables($toolbox, $socket);
    include_once '../classes/efa_tools.php';
    $efa_tools = new Efa_tools($efa_tables, $toolbox);
    $result_bootstrap = $efa_tools->init_efa_data_base(true, true);
}

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');
?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Datenbank <?php echo $socket->get_db_name(); ?> löschen und neu aufsetzen</h3>
	<?php
if ($do_reset) {
    echo "<p>Die Datenbank wurde neu aufgesetzt und " . $_SESSION["User"]["Vorname"] . " " .
             $_SESSION["User"]["Nachname"] .
             " als Administrator wie bisher neu eingerichtet, damit sie nun noch verwaltet werden kann. " .
             "Bitte melde Dich nun ab und neu an, das Passwort ist immer noch das gleiche.<br><br>" .
             "<span class='formbutton'><a href='../pages/logout.php'>Abmelden</a></span><br></p>";
    echo "<p>Folgendes Ergebnis der Aktivität wurde mitgeschrieben:<br>" . $result_bootstrap . "</p>";
} else {
    ?>
	<p>In wirklich seltenen Fällen kann es nötig sein, die Datenbank
		komplett löschen zu können. Das ist hier per Knopfdruck möglich. <br><b>Aber
		dann sind die Daten auch wirklich weg.</b><br>Eine Rekonstruktion auf Basis
		eines Fahrtenbuch-Backups ist nicht komplett möglich, weil auch die
		Historie der Daten und die Administratoren mit Ausnahme von 
		'<?php echo $_SESSION["User"]["Vorname"] . " " . $_SESSION["User"]["Nachname"];?>' gelöscht werden. Der Vorgang kann 10-20 Sekunden dauern.</p>
	<h4>Oh nein, das dann doch nicht!</h4>
	<p>
		<span class='formbutton'><a href="../pages/home.php">Abbrechen und zur Startseite</a></span>
	</p>
</div>
<div class="w3-container">
	<p>
		<b style='color: #f00'>ACHTUNG: Keine weitere Abfrage. <br>--- Datenbank: <?php echo $socket->get_db_name() . " auf " . $app_root; ?>
			---<br>wird unmittelbar und unwiederruflich gelöscht!
		</b>
	</p>
	<p>
		<span class='formbutton'><a href="?do_reset=now" style='color: #f00'>Datenbank
				jetzt löschen und neu aufsetzen</a></span>
	</p>
	<?php
}
?>
</div>
<?php
end_script();