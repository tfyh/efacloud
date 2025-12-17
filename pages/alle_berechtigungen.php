<?php
/**
 *
 *       the tools-for-your-hobby framework
 *       ----------------------------------
 *       https://www.tfyh.org
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
 * An overview on all accesses currently granted.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
echo "<!-- START OF content -->\n<div class='w3-container'>\n";
echo "<h3>" . i("1GPSPQ|Authorisations overview") . "</h3>";
echo "<p>" . i("3Ju6sc|An overview of the curre...") . "</p>";
echo $toolbox->users->get_all_accesses($socket);

echo "<h4>" . i("VCcc4J|Permissions per role") . "</h4>";
$menu_file_path = "../config/access/imenu";
$audit_menu = new Tfyh_menu($menu_file_path, $toolbox);
echo $audit_menu->get_allowance_profile_html($menu_file_path);

echo "<h4>" . i("VCcc4J|Permissions per role") . " (efaWeb)</h4>";
$menu_file_path = "../config/access/wmenu";
$audit_menu = new Tfyh_menu($menu_file_path, $toolbox);
echo $audit_menu->get_allowance_profile_html($menu_file_path);

echo "</div><div class='w3-container'>";
echo "<h3>" . i("NWE4ur|Information on data prot...") . "</h3>";
echo "<ul class='listWithMarker'><li>" .
         i(
                "u34D4y|This information is prov...") .
         "</li></ul>";
echo "</div>";
end_script();
