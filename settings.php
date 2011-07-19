<?php

// Check if we are loaded from the Phorum admin code.
// Direct access to this file is not allowed.
if (! defined("PHORUM_ADMIN")) return;

virtual_hosting_db_fixintegrity();

// This admin interface contains multiple screens. Determine which one
// we have to load.
$action = "select_vroot";
if (isset($_REQUEST["vh_action"])) {
    $action = basename($_REQUEST["vh_action"]);
}

// Load the settings screen. For security,
// we follow a strict naming scheme here.
$settings_file = "./mods/virtual_hosting/settings/{$action}.php";
if (file_exists($settings_file)) {
    include($settings_file);
} else {
    die("Illegal settings action requested.");
}

?>
