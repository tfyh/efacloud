<?php
/**
 * A data base bootstrap script to create the server side admin tables and the first admin user.
 *
 * @author mgSoft
 */

// ===== THIS SHALL ONLY BE USED during application configuration, then access rights shall
// be changed to "no access" - even better: or the form deleted from the site.

// ===== initialize toolbox
include_once '../classes/toolbox.php';
$toolbox = new Toolbox("../config/settings");

// PRELIMINARY SECURITY CHECKS
// ===== throttle to prevent from machine attacks.
$toolbox->load_throttle("inits/", 1000);

// Create PHP-wrapper socket to data base
include_once '../classes/socket.php';
$socket = new Socket($toolbox);
$db_name = $socket->get_db_name();

// ===== define admin user default configuration
// set defaults
$cfg_default["ecadmin_vorname"] = "Alex";
$cfg_default["ecadmin_nachname"] = "Admin";
$cfg_default["ecadmin_mail"] = "alex.admin@efacloud.org";
$cfg_default["ecadmin_id"] = "1142";
$cfg_default["ecadmin_password"] = "123Test!";
$cfg_default["ecadmin_password_confirm"] = "123Test!";

// ===== Form texts for admin user configuration
$cfg_description["ecadmin_vorname"] = "Vorname des efacloud Server Admins";
$cfg_description["ecadmin_nachname"] = "Nachname des efacloud Server Admins";
$cfg_description["ecadmin_mail"] = "E-Mail Adresse des efacloud Server Admins";
$cfg_description["ecadmin_id"] = "userID des efacloud Server Admins (ganze Zahl)";
$cfg_description["ecadmin_password"] = "Passwort des efacloud Server Admins, UNBEDINGT MERKEN";
$cfg_description["ecadmin_password_confirm"] = "Passwort des efacloud Server Admins wiederholen";

// ===== define field format in configuration form
$cfg_type["ecadmin_vorname"] = "text";
$cfg_type["ecadmin_nachname"] = "text";
$cfg_type["ecadmin_mail"] = "email";
$cfg_type["ecadmin_id"] = "text";
$cfg_type["ecadmin_password"] = "password";
$cfg_type["ecadmin_password_confirm"] = "password";

// ===== data base bootstrap SQL statmenets
$sql_bootstrap[] = "ALTER DATABASE `#db_name#` CHARACTER SET utf8 COLLATE utf8_german2_ci;";
$tables_to_drop = [
        "efa2autoincrement",
        "efa2boatdamages",
        "efa2boatreservations",
        "efa2boats",
        "efa2boatstatus",
        "efa2crews",
        "efa2destinations",
        "efa2fahrtenabzeichen",
        "efa2groups",
        "efa2logbook",
        "efa2messages",
        "efa2persons",
        "efa2sessiongroups",
        "efa2statistics",
        "efa2status",
        "efa2waters",
        "efaCloudConfig",
        "efaCloudLog",
        $toolbox->users->user_table_name
];

$sql_bootstrap[] = "CREATE TABLE `efaCloudConfig` (`ID` int UNSIGNED NOT NULL, `Name` varchar(64) NOT NULL, " .
         "`Wert` varchar(4096) NOT NULL, `LastModified` bigint DEFAULT NULL);";
$sql_bootstrap[] = "ALTER TABLE `efaCloudConfig` ADD PRIMARY KEY (`ID`);";
$sql_bootstrap[] = "ALTER TABLE `efaCloudConfig` MODIFY `ID` int UNSIGNED NOT NULL AUTO_INCREMENT;";
$sql_bootstrap[] = "INSERT INTO `efaCloudConfig` (`ID`, `Name`, `Wert`) VALUES (1, 'lastUserID', '1142');";

$sql_bootstrap[] = "CREATE TABLE `efaCloudLog` (`ID` int UNSIGNED NOT NULL, `Author` int NOT NULL, " .
         "`Time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, `ChangedTable` varchar(64) NOT NULL DEFAULT 'no_table_set', " .
         "`ChangedID` varchar(256) DEFAULT NULL, `Modification` varchar(4096) DEFAULT NULL, `LastModified` bigint DEFAULT NULL);";
$sql_bootstrap[] = "ALTER TABLE `efaCloudLog` ADD PRIMARY KEY (`ID`);";
$sql_bootstrap[] = "ALTER TABLE `efaCloudLog` MODIFY `ID` int UNSIGNED NOT NULL AUTO_INCREMENT;";

$sql_bootstrap[] = "CREATE TABLE `efaCloudUsers` (`ID` int UNSIGNED NOT NULL, `EMail` varchar(64) NOT NULL DEFAULT 'nn@efacloud.org'," .
         " `efaCloudUserID` int DEFAULT NULL, `Vorname` varchar(64) NOT NULL, `Nachname` varchar(64) NOT NULL, `Rolle` varchar(64) DEFAULT 'anonymous', " .
         "`Passwort_Hash` varchar(256) NOT NULL DEFAULT '-', `LastModified` bigint DEFAULT NULL);";
$sql_bootstrap[] = "ALTER TABLE `efaCloudUsers` ADD PRIMARY KEY (`ID`);";
$sql_bootstrap[] = "ALTER TABLE `efaCloudUsers` MODIFY `ID` int UNSIGNED NOT NULL AUTO_INCREMENT;";
$sql_bootstrap[] = "INSERT INTO `efaCloudUsers` (`ID`, `EMail`, `efaCloudUserID`, `Vorname`, `Nachname`, `Rolle`, `Passwort_Hash`, `LastModified`) " .
         "VALUES (1, '#ecadmin_mail#', #ecadmin_id#, '#ecadmin_vorname#', '#ecadmin_nachname#', 'admin', '#ecadmin_password_hash#', '1582228940000');";

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo file_get_contents('../config/snippets/page_02_nav_to_body');
?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Datenbank <?php echo $db_name; ?> löschen und neu aufsetzen</h3>
</div>
<div class="w3-container">

<?php
if ((isset($_GET['done']) && intval($_GET["done"]) == 1)) {
    
    foreach ($cfg_default as $key => $value)
        $cfg_to_use[$key] = $cfg_default[$key];
    
    // read entered values into $cfg_to_use array.
    foreach ($cfg_default as $key => $value) {
        $new_value = $_POST[$key];
        if (! is_null($new_value) && (strlen($new_value) > 0))
            $cfg_to_use[$key] = $_POST[$key];
    }
    // check password
    if (strcmp($cfg_to_use["ecadmin_password"], $cfg_to_use["ecadmin_password_confirm"]) != 0) {
        ?>
	<h3>Die Kennwörter stimmen nicht überein. Bitte korrigieren!</h3>
	<p>
		<a href='../install/setup_clear_db.php'>Neuer Versuch</a>
	</p>
<?php
        echo "</div>";
        end_script();
        exit();
    }
    if (strlen($toolbox->check_password($cfg_to_use["ecadmin_password"])) > 0) {
        ?>
	<h3>Das Kennwort genügt icht den Sicherheitsregeln</h3>
	<p>
		<a href='../install/setup_clear_db.php'>Neuer Versuch</a>
	</p>
<?php
        echo "</div>";
        end_script();
        exit();
    }
    
    // hash password
    $cfg_to_use["ecadmin_password_hash"] = password_hash($cfg_to_use["ecadmin_password"], PASSWORD_DEFAULT);
    unset($cfg_to_use["ecadmin_password"]);
    
    $result_bootstrap = "<p><b>Löschen der Datenbanktabellen:</b><br>";
    $existing_tables = $socket->get_table_names(true);
    foreach ($tables_to_drop as $table_to_drop) {
        $result_bootstrap .= "Lösche Tabelle: " . $table_to_drop . " ... ";
        $sql_cmd = "DROP TABLE IF EXISTS `" . $table_to_drop . "`";
        $res_sql = $socket->query($sql_cmd, true);
        $result_bootstrap .= (intval($res_sql) == 1) ? "ok." : $res_sql;
        $result_bootstrap .= "<br>";
    }
    $result_bootstrap .= "</p><p><b>Neu-Aufsetzen der Verwaltungstabellen:</b><br>";
    foreach ($sql_bootstrap as $sql_cmd_template) {
        $sql_cmd = $sql_cmd_template;
        foreach ($cfg_to_use as $cfg_key => $cfg_value)
            $sql_cmd = str_replace('#' . $cfg_key . '#', $cfg_value, $sql_cmd);
        $sql_cmd = str_replace('#db_name#', $db_name, $sql_cmd);
        $table_name_start = strpos($sql_cmd, "`") + 1;
        $table_name_end = strpos($sql_cmd, "`", $table_name_start);
        $table_name = substr($sql_cmd, $table_name_start, $table_name_end - $table_name_start);
        if (strpos($sql_cmd, "CREATE TABLE") !== false)
            $result_bootstrap .= "Erzeuge Tabelle " . $table_name . " ... ";
        elseif (strpos($sql_cmd, "INSERT INTO") !== false)
            $result_bootstrap .= "Füge Datensatz hinzu in " . $table_name . " ... ";
        else
            $result_bootstrap .= "Konfiguriere in mehreren Schritten " . $table_name . " ... ";
        $res_sql = $socket->query($sql_cmd, true);
        $result_bootstrap .= (intval($res_sql) == 1) ? "ok." : $res_sql;
        $result_bootstrap .= "<br>";
    }
    echo $result_bootstrap;
    echo "</p>";
    // Display result and next steps
    ?>
	<h3>Fertig</h3>
	<p>
		Die Datenbank ist gelöscht und neu aufgesetzt. Die Einrichtung muss
		nun <a href='../install/setup_finish.php'>hier abgeschlossen</a> werden.
	</p>
<?php
} else {
    ?>
	<p>
		Hier bitte den Administrator der neu aufgesetzten Datenbank angeben.
		Das ist dann der einzige Nutzer der neuen Datenbank. Dieser Nutzer
		kann dann alle weiteren Verwaltungsvorgänge in der Anwendung
		durchführen.<br>
	</p>
	<form action="?done=1" method="post">
		<table>

    <?php
    // Display form fields depending on the installation mode.
    foreach ($cfg_default as $key => $value)
        echo '<tr><td>' . $key . ':<br>' . $cfg_description[$key] . '&nbsp;</td><td><input class="forminput" type="' .
                 $cfg_type[$key] . '" size="35" maxlength="250" name="' . $key . '" value="' . $value . '"></td></tr>';
    ?>
    </table>
		<br> <input class="formbutton" type="submit"
			value="Datenbank neu aufsetzen">
	</form>
	<h2>Achtung: Dadurch werden alle bestehenden Daten in der Datenbank "<?php echo $db_name; ?>" gelöscht!</h2>
</div><?php
}
end_script();