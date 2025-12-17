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
 * The form for user profile self service. Based on the Tfyh_form class, please read instructions their to
 * better understand this PHP-code part.
 * 
 * @author mgSoft
 */
// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
// ===== page does not need an active session
include_once "../classes/init.php";
// The color desinger needs no layout, but holds it itself

// === APPLICATION LOGIC ==============================================================

// read the template and the default colors.
$app_colors = file_get_contents("../resources/app-colors.txt");
$app_style = file_get_contents("../resources/app-style-no_colors.css");
$colors = [];
$color_keys = [];
foreach (explode("\n", $app_colors) as $color) {
    if (count(explode("=", $color)) > 1) {
        $key = explode("=", $color)[0];
        $value = explode("=", $color)[1];
        $colors[$key] = $value;
        $color_keys[] = $key;
    }
}

// if applicable, read data entered in last step
$changecolors = (isset($_GET["changecolors"])) ? intval($_GET["changecolors"]) : 0;
if ($changecolors > 0) {
    
    if ($changecolors == 1) {
        foreach ($colors as $key => $value) {
            // The enclosing apostrophes are not in $_POST, but just the inner key.
            $postkey = substr($key, 1, strlen($key) - 2);
            if (isset($_POST[$postkey])) {
                $colors[$key] = $_POST[$postkey];
            }
        }
    } elseif (($changecolors == 2) && file_exists("../resources/app-colors-previous.txt")) {
        // the new colours are the previous ones
        $prev_colors = file_get_contents("../resources/app-colors-previous.txt");
        foreach (explode("\n", $prev_colors) as $color) {
            $key = explode("=", $color)[0];
            $value = explode("=", $color)[1];
            $colors[$key] = $value;
        }
    } elseif (($changecolors == 3) && file_exists("../resources/app-colors-default.txt")) {
        // the new colours are the default ones
        $prev_colors = file_get_contents("../resources/app-colors-default.txt");
        foreach (explode("\n", $prev_colors) as $color) {
            $key = explode("=", $color)[0];
            $value = explode("=", $color)[1];
            $colors[$key] = $value;
        }
    }
    
    // Create new style sheet and save color set
    $app_colors_new = "";
    $app_style_new = $app_style;
    foreach ($colors as $key => $value) {
        if (isset($key) && (strlen($key) > 0)) {
            $app_colors_new .= $key . "=" . $value . "\n";
            $app_style_new = str_replace($key, $value, $app_style_new);
        }
    }
    file_put_contents("../resources/app-colors-previous.txt", $app_colors);
    file_put_contents("../resources/app-colors.txt", $app_colors_new);
    file_put_contents("../resources/app-style.css", $app_style_new);
    
    // wait a little to let the file writing complete and
    // restart anew.
    sleep(1);
    header("Location: farben_aendern.php");
}

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
echo "<!-- START OF content -->\n<div class='w3-container'>\n";
echo "<h3>" . i("KDNuD3|Change colours and font") . "</h3>";

// example elements
echo "<h1>" . i("iX6ImJ|Example Headline 1") . "</h1>";
echo "<h4>" . i("DybBcu|Example Headline 4") . "</h4>";
echo "<p><a href='#'>" . i("HsHWSA|Example Link") . "</a></p>";
echo "<label class='cb-container'>" . i("rTgJH2|Radio button checked") .
         "<input type='radio' name='radioexample1' value='' checked /><span class='cb-radio'></span></label><br>";
echo "<label class='cb-container'>" . i("SYuuyO|Radio button unchecked") .
         "<input type='radio' name='radioexample2' value='' /><span class='cb-radio'></span></label><br>";
echo "<label class='cb-container'>" . i("3MS3H9|Checkbox checked") .
         "<input type='checkbox' name='checkboxexample1' value='' checked /><span class='cb-checkmark'></span></label><br>";
echo "<label class='cb-container'>" . i("nMSzXi|Checkbox unchecked") .
         "<input type='checkbox' name='checkboxexample1' value='' /><span class='cb-checkmark'></span></label><br>";
echo "<select class='formselector' name='selectorexample' style='width: 15em'>";
echo "<option value='option1'>" . i("5qx7Bd|option") . " #1</option>";
echo "<option selected value='option2'>" . i("ZiS5as|option") . " #2</option>";
echo "<option value='option3'>" . i("Rqkqic|option") . " #3</option>";
echo "</select>\n<p>&nbsp;</p>\n<form method=POST action='?changecolors=1'>";

// colour table
echo "<table style='width: 70%'>\n<thead>\n<tr><th>" . i("CS3ieI|colour application") . "</th><th>" .
         i("xmC7bW|colour value") . "</th></tr></thead><tbody>";
foreach ($color_keys as $color_key) {
    if (strlen($color_key) > 0) {
        if (substr($color_key, 0, 1) == '#')
            $row = "<tr><td><h5>" . substr($color_key, 1) . "</h5></td><td>&nbsp;</td></tr>";
        else
            $row = "<tr><td>" . $color_key . "</td><td>" . "<input class='forminput' name=" . $color_key .
                     " value='" . $colors[$color_key] . "' type=text /></td></tr>";
        echo $row;
    }
}
echo "    </tbody>\n   </table>\n   <p>\n" .
         "    <input name='submit' value='Test' type='submit' class='formbutton' />\n" .
         "   </p>\n  </form>\n  <p>";
if (file_exists("../resources/app-colors-previous.txt")) {
    echo "<a href='?changecolors=2' class='formbutton'>" . i("OdDC93|Back to previous setting") . "</a>";
}
if (file_exists("../resources/app-colors-default.txt")) {
    echo "&nbsp;&nbsp;&nbsp;<a href='?changecolors=3' class='formbutton'>" .
             i("j5CpiO|Back to standard colours") . "</a>";
}
echo "</p>\n<p>";
echo i(
        "Z1rWgP|Note: Usually the browse...");
echo "</p>\n</div>";
end_script();
