<?php
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
    if (strcmp($language_code, "de") == 0) {
        $dfmt_d = "d.m.Y";
        $dfmt_dt = "d.m.Y H:i:s";
    }
}

function i (String ...$args)
{
    global $lr;
    $i18n_resource = $args[0];
    if ((strlen($i18n_resource) < 7) || (substr($i18n_resource, 6, 1) != "|"))
        return $i18n_resource;
    $token = substr($i18n_resource, 0, 6);
    if (! isset($lr[$token]))
        return $i18n_resource;
    $text = $lr[$token];
    for ($i = 0; $i < count($args); $i ++)
        $text = str_replace("%" . $i, $args[$i], $text);
    return $text;
}

// ===== formatting support
