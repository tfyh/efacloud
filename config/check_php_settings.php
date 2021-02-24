<?php
$ref_config = [
        "apache2handler",
        "bz2",
        "calendar",
        "Core",
        "ctype",
        "curl",
        "date",
        "dom",
        "exif",
        "FFI",
        "fileinfo",
        "filter",
        "ftp",
        "gd",
        "gettext",
        "hash",
        "iconv",
        "json",
        "libxml",
        "mbstring",
        "mysqli",
        "mysqlnd",
        "openssl",
        "pcre",
        "pdo_mysql",
        "PDO",
        "Phar",
        "posix",
        "readline",
        "Reflection",
        "session",
        "shmop",
        "SimpleXML",
        "sockets",
        "sodium",
        "SPL",
        "standard",
        "sysvmsg",
        "sysvsem",
        "sysvshm",
        "tokenizer",
        "xml",
        "xmlreader",
        "xmlwriter",
        "xsl",
        "Zend OPcache",
        "zip",
        "zlib"
];

$this_config = get_loaded_extensions();
$missing = [];
foreach($ref_config as $rcfg) {
    $contained = false;
    foreach ($this_config as $tcfg) {
        $contained = $contained || (strcmp($tcfg, $rcfg) == 0);
    }
    if (!$contained) $missing[] = $rcfg;
}