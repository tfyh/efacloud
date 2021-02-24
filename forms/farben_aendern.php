<?php
/**
 * The form for user profile self service. Based on the Form class, please read instructions their to better
 * understand this PHP-code part.
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
    $key = explode("=", $color)[0];
    $value = explode("=", $color)[1];
    $colors[$key] = $value;
    $color_keys[] = $key;
}

// if applicable, read data entered in last step
$changecolors = (isset($_GET["changecolors"])) ? intval($_GET["changecolors"]) : 0;
if ($changecolors != 0) {
    
    // backup previous style
    // $infix = date("Ymd_His");
    // rename("../resources/app-style.css", "../resources/app-style." . $infix . ".css");
    // rename("../resources/app-colors.txt", "../resources/app-colors." . $infix . ".txt");
    
    foreach ($colors as $key => $value) {
        // The enclosing apostrophes are not in $_POST, but just the inner key.
        $postkey = substr($key, 1, strlen($key) - 2);
        if (isset($_POST[$postkey])) {
            $colors[$key] = $_POST[$postkey];
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
    file_put_contents("../resources/app-colors.txt", $app_colors_new);
    file_put_contents("../resources/app-style.css", $app_style_new);
    
    // wait a little to let the file writing complete and
    // restart anew.
    sleep(2);
    header("Location: farben_aendern.php");
    
}

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Farbschema und Font ändern</h3>
	<p>Hier kann das Farbschema und der Font der Applikation angepasst
		werden.</p>
	<h1>Beispiel Überschrift 1</h1>
	<h4>Beispiel Überschrift 4</h4>
	<p><a href='#'>Beispiel Link</a></p>
	<label class="cb-container">Radiobutton checked<input type="radio"
		name="radioexample1" value="" checked /><span class="cb-radio"></span></label><br>
	<label class="cb-container">Radiobutton unchecked<input type="radio"
		name="radioexample2" value="" /><span class="cb-radio"></span></label><br>
	<label class="cb-container">Checkbox checked<input type="checkbox"
		name="checkboxexample1" value="" checked /><span class="cb-checkmark"></span></label><br>
	<label class="cb-container">Checkbox unchecked<input type="checkbox"
		name="checkboxexample2" value="" /><span class="cb-checkmark"></span></label><br>
	<select class="formselector" name="selectorexample"
		style="width: 15em">
		<option value="option 1">option1</option>
		<option selected value="option 2">dropdown options</option>
		<option value="option 3">option3</option>
	</select>
	<p>&nbsp;</p>
	<form method=POST action="?changecolors=1">
		<table style='width: 70%'>
			<thead>
				<tr>
					<th>Farbanwendung</th>
					<th>Farbwert</th>
				</tr>
			</thead>
			<tbody>
			  <?php
    foreach ($color_keys as $color_key) {
        if (strlen($color_key) > 0) {
            if (substr($color_key, 0, 1) == '#')
                $row = "<tr><td><h5>" . substr($color_key, 1) . "</h5></td><td>&nbsp;</td></tr>";
            else
                $row = "<tr><td>" . $color_key . "</td><td>" . "<input class='forminput' name=" . $color_key . " value='" .
                         $colors[$color_key] . "' type=text /></td></tr>";
            echo $row;
        }
    }
    ?>
			</tbody>
		</table>
		<p>
			<input name='submit' value='Ausprobieren' type='submit'
				class='formbutton' />
		</p>
	</form>
</div>

<?php
end_script();
