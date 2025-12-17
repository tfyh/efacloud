<?php
/**
 *
 *       efaCloud
 *       --------
 *       https://www.efacloud.org
 *
 * Copyright  2018-2024  Martin Glade
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


/**
 * A data base bootstrap script to create the server side admin tables and the first admin user.
 */

// ===== THIS SHALL ONLY BE USED during application configuration, then access rights shall
// be changed to "no access" - even better: or the form deleted from the site.

// ===== initialize toolbox
include_once "../classes/init_i18n.php"; // not part of init for setup, api, logout and error
include_once '../classes/tfyh_toolbox.php';
$toolbox = new Tfyh_toolbox();
// init parameters definition
$cfg = $toolbox->config->get_cfg();

// PRELIMINARY SECURITY CHECKS
// ===== throttle to prevent from machine attacks.
$toolbox->load_throttle("inits", $toolbox->config->settings_tfyh["init"]["max_inits_per_hour"], "setup_clear_db.php");

// Create PHP-wrapper socket to data base
include_once '../classes/tfyh_socket.php';
$socket = new Tfyh_socket($toolbox);
$connected = $socket->open_socket();
if ($connected !== true)
    $toolbox->display_error("Datenbankverbindung fehlgeschlagen", $connected, "../install/setup_clear_db.pbp", 
            __FILE__);

$db_name = $socket->get_db_name();

// ===== define admin user default configuration
// set defaults
$cfg_db_default["ecadmin_vorname"] = "Alex";
$cfg_db_default["ecadmin_nachname"] = "Admin";
$cfg_db_default["ecadmin_mail"] = "alex.admin@efacloud.org";
$cfg_db_default["ecadmin_id"] = "1142";
$cfg_db_default["ecadmin_Name"] = "alexa";
$cfg_db_default["ecadmin_password"] = "123Test!";
$cfg_db_default["ecadmin_password_confirm"] = $cfg_db_default["ecadmin_password"];

// ===== Form texts for admin user configuration
$cfg_db_description["ecadmin_vorname"] = "Vorname des efacloud Server Admins";
$cfg_db_description["ecadmin_nachname"] = "Nachname des efacloud Server Admins";
$cfg_db_description["ecadmin_mail"] = "E-Mail Adresse des efacloud Server Admins";
$cfg_db_description["ecadmin_id"] = "userID des efacloud Server Admins (ganze Zahl)";
$cfg_db_description["ecadmin_Name"] = "admin Name des efacloud Server Admins (z.B. 'martin', 'admin' ist nicht zulässig!)";
$cfg_db_description["ecadmin_password"] = "Passwort des efacloud Server Admins, UNBEDINGT MERKEN";
$cfg_db_description["ecadmin_password_confirm"] = "Passwort des efacloud Server Admins wiederholen";

// ===== define field format in configuration form
$cfg_db_type["ecadmin_vorname"] = "text";
$cfg_db_type["ecadmin_nachname"] = "text";
$cfg_db_type["ecadmin_mail"] = "email";
$cfg_db_type["ecadmin_id"] = "text";
$cfg_db_type["ecadmin_Name"] = "text";
$cfg_db_type["ecadmin_password"] = "password";
$cfg_db_type["ecadmin_password_confirm"] = "password";

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
    
    foreach ($cfg_db_default as $key => $value)
        $cfg_db_to_use[$key] = $cfg_db_default[$key];
    
    // read entered values into $cfg_to_use array.
    foreach ($cfg_db_default as $key => $value) {
        $new_value = $_POST[$key];
        if (! is_null($new_value) && (strlen($new_value) > 0))
            $cfg_db_to_use[$key] = $_POST[$key];
    }
    // check password
    if (strcmp($cfg_db_to_use["ecadmin_password"], $cfg_db_to_use["ecadmin_password_confirm"]) != 0) {
        ?>
	<h4>Die Kennwörter stimmen nicht überein. Bitte korrigieren!</h4>
	<p>
		<a href='../install/setup_clear_db.php'>Neuer Versuch</a>
	</p>
<?php
        echo "</div>";
        exit();
    }
    if (strlen($toolbox->check_password($cfg_db_to_use["ecadmin_password"])) > 0) {
        ?>
	<h4>Das Kennwort genügt nicht den Sicherheitsregeln</h4>
	<p>
		<a href='../install/setup_clear_db.php'>Neuer Versuch</a>
	</p>
<?php
        echo "</div>";
        exit();
    }
    if (strcasecmp($cfg_db_to_use["ecadmin_Name"], "admin") == 0) {
        ?>
	<h4>Der admin-Name 'admin' ist unzulässig. Bitte verwende einen anderen admin Namen.</h4>
	<p>
		<a href='../install/setup_clear_db.php'>Neuer Versuch</a>
	</p>
<?php
        echo "</div>";
        exit();
    }
    
    // set session user to selected admin, in order to be able to manipulate the data base.
    $session_user = [];
    $session_user["Vorname"] = $cfg_db_to_use["ecadmin_vorname"];
    $session_user["Nachname"] = $cfg_db_to_use["ecadmin_nachname"];
    $session_user["EMail"] = $cfg_db_to_use["ecadmin_mail"];
    $session_user["efaCloudUserID"] = $cfg_db_to_use["ecadmin_id"];
    $session_user["efaAdminName"] = $cfg_db_to_use["ecadmin_Name"];
    $session_user["Passwort_Hash"] = password_hash($cfg_db_to_use["ecadmin_password"], PASSWORD_DEFAULT);
    $session_user["Rolle"] = "admin";
    $toolbox->users->set_session_user($session_user);
    
    // ===== create data base
    include_once '../classes/efa_tools.php';
    $efa_tools = new Efa_tools($toolbox, $socket);
    $result_bootstrap = $efa_tools->init_efa_data_base(true, true, true);
    
    echo "<p>" . $result_bootstrap . "</p>";
    // Display result and next steps
    ?>
	<h3>Fertig</h3>
	<p>
		Die Datenbank ist gelöscht und neu aufgesetzt. Die Einrichtung muss
		nun <a href='../install/setup_finish.php'>hier abgeschlossen</a>
		werden.
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
    foreach ($cfg_db_default as $key => $value)
        echo '<tr><td>' . $key . ':<br>' . $cfg_db_description[$key] .
                 '&nbsp;</td><td><input class="forminput" type="' . $cfg_db_type[$key] .
                 '" size="35" maxlength="250" name="' . $key . '" value="' . $value . '"></td></tr>';
    ?>
    </table>
		<br> <input class="formbutton" type="submit"
			value="Datenbank neu aufsetzen">
	</form>
	<h2>Achtung: Dadurch werden alle bestehenden Daten in der Datenbank "<?php echo $db_name; ?>" gelöscht!</h2>
</div><?php
}