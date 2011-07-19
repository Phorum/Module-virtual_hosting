<?php

if (!defined("PHORUM")) return;

$sqlqueries[]= "
    CREATE TABLE {$PHORUM["virtual_hosts_table"]} (
        hostname VARCHAR(255) NOT NULL DEFAULT '',
        vroot    INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY (hostname),
        KEY (vroot)
    )
";

?>
