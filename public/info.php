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
 * A page providing information for direct client display (not via the api).
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
$info_type = (isset($_GET["type"])) ? $_GET["type"] : ""; // identify the type of information requested
$info_mode = (isset($_GET["mode"])) ? intval($_GET["mode"]) : - 1; // identify the mode of formatting
                                                                   // requested
include_once "../classes/efa_info.php";
$efa_info = new Efa_info($toolbox, $socket);

if ($efa_info->is_allowed_info($toolbox->users->session_user, "public_" . $info_type)) {
    if (strcasecmp("onthewater", $info_type) == 0)
        $info = $efa_info->get_on_the_water($info_mode);
    elseif (strcasecmp("notavailable", $info_type) == 0)
        $info = $efa_info->get_not_available($info_mode);
    elseif (strcasecmp("notusable", $info_type) == 0)
        $info = $efa_info->get_not_usable($info_mode);
    elseif (strcasecmp("reserved", $info_type) == 0)
        $info = $efa_info->get_reserved($info_mode);
    else
        $info = i('BVliju|Error 502: no valid info...');
} else {
    $info = i('Apulhe|Access to this informati...');
}
echo file_get_contents("../config/snippets/page_iframe_start");
echo $info;
echo file_get_contents("../config/snippets/page_iframe_end");
end_script(false);
