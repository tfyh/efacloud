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
$toolbox = new Tfyh_toolbox();

// PRELIMINARY SECURITY CHECKS
// ===== throttle to prevent from machine attacks.
$toolbox->load_throttle("inits", $toolbox->config->settings_tfyh["init"]["max_inits_per_hour"], "setup_finish.php");

// remove install file from root folder
unlink("../install.php");
// block access to install folder
file_put_contents("../install/.htaccess", "deny for all");
chmod("../install", 0700);

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
	<h3>Die Installation wurde abgeschlossen</h3>
	<p>Die Datei 'install.php' wurde aus dem Wurzelverzeichnis gelöscht und
		der Ordner 'install' für den Zugang gesperrt.</p>
	<p>Vielen Dank, dass Sie efa und efacloud nutzen!</p>
	<h4>
		<a href='../forms/login.php' target='_blank'>Zum Login hier lang</a>
		oder <a href='../public/index.php'>zur Startseite</a>.
	</h4>
</div><?php
