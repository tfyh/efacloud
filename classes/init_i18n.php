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

// ===== internationalization support
// i18n. lr = language resource; xx.lrf is a language resource file which always escapes line breaks.
$lr = [];
$dfmt_d = "Y-m-d";
$dfmt_dt = "Y-m-d H:i:s";

function load_i18n_resource (String $language_code)
{
    global $lr;
    global $dfmt_d, $dfmt_dt;
    $lr_file = file_get_contents("../i18n/" . $language_code . ".lrf");
    if ($lr_file == false)
        return;
    $lr_lines = explode("\n", file_get_contents("../i18n/" . $language_code . ".lrf"));
    $text = "";
    $token = "-";
    foreach ($lr_lines as $lr_line) {
        $pipe_at = strpos($lr_line, "|");
        if ($pipe_at !== false) {
            if ($pipe_at == 6) { // new language resource. Store current.
                if (strlen($token) == 6)
                    $lr[$token] = $text;
                $token = substr($lr_line, 0, 6);
                $text = substr($lr_line, 7);
            } elseif ($pipe_at == 0) { // continued language resource text
                $text .= substr($lr_line, 1) . "\n";
            }
        }
    }
    // add last entry
    $lr[$token] = $text;
    if (strcmp($language_code, "de") == 0) {
        $dfmt_d = "d.m.Y";
        $dfmt_dt = "d.m.Y H:i:s";
    }
}

function i (?String ...$args)
{
    global $lr;
    $i18n_resource = $args[0];
    if ((strlen($i18n_resource) < 7) || (substr($i18n_resource, 6, 1) != "|"))
        $text = $i18n_resource;
    else {
        $token = substr($i18n_resource, 0, 6);
        if (isset($lr[$token]))
            $text = $lr[$token];
        else
            $text = substr($i18n_resource, 7);
    }
    for ($i = 1; $i < count($args); $i ++) {
        if (is_null($args[$i]))
            $args[$i] = "NULL";
        $text = str_replace("%" . $i, $args[$i], $text);
    }
    return $text;
}

// ===== formatting support
