<?php

if (!defined("PHORUM")) return;

$sqlqueries[]= "
    ALTER TABLE {$PHORUM["user_table"]}
    ADD COLUMN vroot INTEGER NOT NULL DEFAULT 0
";

?>
