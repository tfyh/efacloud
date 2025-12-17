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


// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once "../classes/efa_info.php";
$efa_info = new Efa_info($toolbox, $socket);

$info = [];
$info_mode = 7;
$info_types = ["onthewater","notavailable","notusable","reserved"
];
foreach ($info_types as $info_type) {
    if ($efa_info->is_allowed_info($toolbox->users->session_user, "public_" . $info_type)) {
        if (strcasecmp("onthewater", $info_type) == 0)
            $info[$info_type] = $efa_info->get_on_the_water($info_mode);
        elseif (strcasecmp("notavailable", $info_type) == 0)
            $info[$info_type] = $efa_info->get_not_available($info_mode);
        elseif (strcasecmp("notusable", $info_type) == 0)
            $info[$info_type] = $efa_info->get_not_usable($info_mode);
        elseif (strcasecmp("reserved", $info_type) == 0)
            $info[$info_type] = $efa_info->get_reserved($info_mode);
    }
}

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

echo "<h3>" . i("EJRxHO|Publicly available logbo...") . "</h3>";

$cfg = $toolbox->config->get_cfg();
$onthewater = (strcasecmp($cfg["public_onthewater"], "on") == 0);
$reserved = (strcasecmp($cfg["public_reserved"], "on") == 0);
$notusable = (strcasecmp($cfg["public_notusable"], "on") == 0);
$notavailable = (strcasecmp($cfg["public_notavailable"], "on") == 0);

if ($onthewater)
    echo "<h4>" . i("HX2QQd|trips on the water") . "</h4>" . $info["onthewater"];
if ($reserved)
    echo "<h4>" . i("BR9BZ3|boats reservations") . "</h4>" . $info["reserved"];
if ($notusable)
    echo "<h4>" . i("OgP0Hp|boats not usable") . "</h4>" . $info["notusable"];
if ($notavailable)
    echo "<h4>" . i("R827cX|boats not available") . "</h4>" . $info["notavailable"];
if (! $onthewater && ! $reserved && ! $notusable && ! $notavailable)
    echo "<h4>" . i("2e6zhz|In this logbook no infor...") . "</h4>";

end_script();
