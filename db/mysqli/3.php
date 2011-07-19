<?php

if (!defined("PHORUM")) return;

$sqlqueries[]= "
    CREATE TABLE {$PHORUM["virtual_hosts_table"]} (
        hostname VARCHAR(255) NOT NULL DEFAULT '',
        vroot    INT(10) UNSIGNED NOT NULL DEFAULT '0',
        PRIMARY KEY (hostname),
        KEY (vroot)
    )
";

?>
