<?php

if (!defined("PHORUM")) return;

$sqlqueries[]= "
    ALTER TABLE {$PHORUM["forums_table"]} 
    ADD virtualhost_config TEXT NOT NULL DEFAULT ''
";

?>
