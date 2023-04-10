<?php
echo "<h3>Test of internationalization functions</h3>";
$i18n_test = file_get_contents("../i18n/i18n_test.txt");
echo "<p>Using: " . $i18n_test . "</p><p>";
for ($i = 0; $i < mb_strlen($i18n_test); $i++)
    echo "Zeichen #" . $i. ": '" . mb_substr($i18n_test, $i, 1) . "', code: " . ord($i18n_test[$i]) . "<br>";
$index_of_new_line = mb_strpos($i18n_test, "\n");
echo "<br>The first line break is at character #" . $index_of_new_line;
echo "<br>Done.</p>";
    