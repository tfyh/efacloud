<?php
/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2022 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo file_get_contents('../config/snippets/page_02_nav_to_body');
?>
<?= i("Zb6TUk| ** Maintenance ** The..."); ?>
<?php echo $_GET["until"]; ?>
<?= i("2jun3e| ** We apologise for any..."); ?>
<?php
echo file_get_contents('../config/snippets/page_03_footer');
