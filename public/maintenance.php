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

// ===== start page output
include_once "../classes/init_i18n.php"; // usually this is included with init.php
echo file_get_contents('../config/snippets/page_01_start');
echo file_get_contents('../config/snippets/page_02_nav_to_body');
echo "<div class='w3-container'><h3><br><br><br><br><br>" . i("u7mzai|Maintenance") . "</h3>";
echo "<p>" . i("O31g1E|Die Anwendiung ist aktuell in Wartung bis etwa");
echo "<br><b>" . $_GET["until"];
echo "</b><br>" . i("FwRbeh|Tut uns leid, wir bitten um Geduld") . "<br><br><br><br>&nbsp;</p></div>";
echo file_get_contents('../config/snippets/page_03_footer');
