<?php

if (!defined("PHORUM")) return;

$sqlqueries[]= "
    ALTER TABLE {$PHORUM["user_table"]} 
    ADD vroot INT(10) UNSIGNED NOT NULL DEFAULT '0'
";

?>
