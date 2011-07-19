<?php

if(!defined("PHORUM")) return;

// The database schema version, which is used to handle
// installation and upgrades directly from the module.
define("VIRTUAL_HOSTING_DB_VERSION", 3);

// The table name for storing virtual hosts.
$PHORUM["virtual_hosts_table"] = 
    "{$PHORUM["DBCONFIG"]["table_prefix"]}_virtual_hosts";

// Load database layer functions.
include("./mods/virtual_hosting/db/{$PHORUM["DBCONFIG"]["type"]}/layer.php");

/**
 * This function will check if an upgrade of the database scheme is needed.
 * It is generic for all database layers.
 */
function virtual_hosting_db_install()
{
    $PHORUM = $GLOBALS["PHORUM"];

    $version = isset($PHORUM["mod_virtual_hosting_installed"]) 
        ? $PHORUM["mod_virtual_hosting_installed"] : 0;

    while ($version < VIRTUAL_HOSTING_DB_VERSION)
    {
        // Initialize the settings array that we will be saving.
        $version++;
        $settings = array( "mod_virtual_hosting_installed" => $version );

        $sqlfile = "./mods/virtual_hosting/db/" .
                   $PHORUM["DBCONFIG"]["type"] . "/$version.php";
                   
        if (! file_exists($sqlfile)) {
            print "<b>Unexpected situation on installing " .
                  "the Virtual Hosting module</b>: " .
                  "unable to find the database schema setup script " . 
                  htmlspecialchars($sqlfile);
            return false;
        }

        $sqlqueries = array();
        include($sqlfile);
        
        if (count($sqlqueries) == 0) {
            print "<b>Unexpected situation on installing " .
                  "the Virtual Hosting module</b>: could not read any SQL " .
                  "queries from file " . htmlspecialchars($sqlfile);
            return false;                    
        }
        $err = phorum_db_run_queries($sqlqueries);
        if ($err) {
            print "<b>Unexpected situation on installing " .
                  "the Virtual Hosting module</b>: running the " .
                  "install queries from file " . htmlspecialchars($sqlfile) .
                  " failed";
            return false;                    
        }

        // Save our settings.
        if (!phorum_db_update_settings($settings)) {
            print "<b>Unexpected situation on installing " .
                  "the Virtual Hosting module</b>: updating the " .
                  "mod_virtual_hosting_installed setting failed";
            return false;
        }
    }

    return true;
}

?>
