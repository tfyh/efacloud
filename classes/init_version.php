<?php
/**
 * snippet to execute after upgrade to this version.
 */

// ===== Reflect upgrade result
echo "<p><b>Vielen Dank für die Aktualisierung!<br>Die Version " . file_get_contents("../public/version") . " ist nun betriebsbereit.</b>";
echo "<br>Diese Seite nicht neu laden, sondern als nächstes:<br><br>";
echo "<a href='../pages/home.php'><input type='submit' class='formbutton' value='Weiter mit der Startseite.'></a></p>";
