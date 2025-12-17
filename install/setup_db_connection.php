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
 * An implementation of a form to define the settings of the data base access.
 */

// ===== THIS SHALL ONLY BE USED during application configuration, then access rights shall
// be changed to "no access" - even better: or the form deleted from the site.

// ===== initialize toolbox
include_once "../classes/init_i18n.php"; // not part of init for setup, api, logout and error
include_once '../classes/tfyh_toolbox.php';
$settings_path = "../config/settings";
$toolbox = new Tfyh_toolbox();

// PRELIMINARY SECURITY CHECKS
// ===== throttle to prevent from machine attacks.
$toolbox->load_throttle("inits", $toolbox->config->settings_tfyh["init"]["max_inits_per_hour"], 
        "setup_db_connection.php");

// Create PHP-wrapper socket to data base
include_once '../classes/tfyh_socket.php';
$socket = new Tfyh_socket($toolbox);

// ===== define default values for configuration
$cfg_db_default["db_host"] = "rdbms.hoster.xyz";
$cfg_db_default["db_name"] = "efacloudDB";
$cfg_db_default["db_user"] = "dbUser";
$cfg_db_default["db_up"] = "dbPassword";

// ===== define display text for field in configuration form
$cfg_db_description["db_host"] = "der Server, auf dem die Datenbank gehostet wird";
$cfg_db_description["db_name"] = "Name der Datenbank";
$cfg_db_description["db_user"] = "Technischer Datenbanknutzer";
$cfg_db_description["db_up"] = "Kennwort des technischen Datenbanknutzers";

// ===== define field format in configuration form
$cfg_db_type["db_host"] = "text";
$cfg_db_type["db_name"] = "text";
$cfg_db_type["db_user"] = "text";
$cfg_db_type["db_up"] = "password";

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo file_get_contents('../config/snippets/page_02_nav_to_body');
?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Datenbankverbindung konfigurieren</h3>
</div>
<div class="w3-container">

<?php
// first set defaults
$cfg_db_to_use = $cfg_db_default;
// now overwrite defaults with existing configuration
$cfg_db_existing = $toolbox->config->get_cfg_db();
if (is_array($cfg_db_existing))
    foreach ($cfg_db_default as $key => $value)
        if (isset($cfg_db_existing[$key]))
            $cfg_db_to_use[$key] = $cfg_db_existing[$key];

// try to connect in step "done"
if ((isset($_GET['done']) && intval($_GET["done"]) == 1)) {
    
    // read entered values into $cfg_to_use array.
    foreach ($cfg_db_default as $key => $value) {
        $new_value = $_POST[$key];
        if (! is_null($new_value) && (strlen($new_value) > 0))
            $cfg_db_to_use[$key] = $_POST[$key];
    }
    
    // test database access
    $toolbox->config->set_cfg_db($cfg_db_to_use);
    $socket = new Tfyh_socket($toolbox);
    $success_db = true;
    echo "<p>Teste Datenbankverbindung für: " . $cfg_db_to_use["db_user"] . " ... ";
    $socket = new Tfyh_socket($toolbox);
    echo " ... Tfyh_socket erstellt. Verbinde ... ";
    $connect_res = $socket->open_socket();
    if ($connect_res === true)
        echo "Erfolgreich!</p>";
    else {
        echo "Fehlgeschlagen. Fehler: '" . $connect_res . "'</p>";
        $success_db = false;
    }
    
    // store the configuration
    if ($success_db !== false) {
        // up masking
        $cfg_db_to_use["db_up"] = Tfyh_toolbox::swap_lchars($cfg_db_to_use["db_up"]);
        // add existing additional parameters
        if (is_array($cfg_db_existing))
            foreach ($cfg_db_existing as $key => $value)
                if (! isset($cfg_db_to_use[$key]))
                    $cfg_db_to_use[$key] = $cfg_db_existing[$key];
        // write to settings file
        $cfgStr = serialize($cfg_db_to_use);
        $cfgStrBase64 = base64_encode($cfgStr);
        echo "<p>" . $settings_path . '_db wird geschrieben ... ';
        $byte_cnt = file_put_contents($settings_path . "_db", $cfgStrBase64);
        echo $byte_cnt . " Bytes.</p>";
        
        ?>
		<h3>Erfolgreich abgeschlossen</h3>
	<p>Die Konfiguration des Datenbankzugangs wurde angepasst.</p>
<?php
        $table_names = $socket->get_table_names(false);
        $has_users_table = false;
        if (count($table_names) > 0) {
            foreach ($table_names as $table_name)
                $has_users_table = $has_users_table ||
                         (strcasecmp($table_name, $toolbox->users->user_table_name) == 0);
        }
        if (! $has_users_table) {
            ?>
	<p>In der Datenbank wurde keine Tabelle der Nutzer gefunden. Bitte
		Setzen Sie sie neu auf.</p>
	<p>
		<a href='../install/setup_clear_db.php' target='_blank'>Datenbank neu
			aufsetzen</a>.
	</p>
<?php
        } else {
            ?>
	<p>
		In der Datenbank wurde eine Tabelle der Nutzer gefunden. Damit ist die
		Installation nun abgeschlossen. Die Einrichtung kann nun <a
			href='../install/setup_finish.php'>hier abgeschlossen</a> werden.
		Wenn Sie die Bestandsdaten nicht weiter verwenden wollen, können Sie <a
			href='../install/setup_clear_db.php'>die Datenbank löschen und neu
			aufsetzen</a>.
	</p>
<?php
        }
    } else {
        ?>
		<h2>Das hat leider nicht geklappt.</h2>
	<p>Die Konfiguration wurde nicht angepasst. Ist die Datenbankkennung
		richtig?</p>
	<p>
		<a href="?done=0">Erneut versuchen</a>
	</p>
<?php
    }
} else {
    ?>
		<p>Bitte die Parameter hier eingeben. Wenn der Verbindungsversuch
		fehlschlägt, wird nichts verändert.</p>
	<form action="?done=1" method="post">
		<table>

    <?php
    // Display form fields depending on the installation mode.
    foreach ($cfg_db_to_use as $key => $value) {
        echo '<tr><td>' . $key . ':<br>' . $cfg_db_description[$key] .
                 '&nbsp;</td><td><input class="forminput" type="' . $cfg_db_type[$key] .
                 '" size="35" maxlength="250" name="' . $key . '" value="' . $value . '"></td></tr>';
    }
    ?>
    </table>
		<br> <input class="formbutton" type="submit" value="Übernehmen">
	</form><?php
}
?>
</div><?php
