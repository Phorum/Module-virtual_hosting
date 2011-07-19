<?php

if (!defined("PHORUM_ADMIN")) return;

// Fetch all available vroots.
$vroot_list = phorum_get_forum_info(3, -1);

$vroot = NULL;
if (count($_REQUEST) &&
    isset($_REQUEST["vh_vroot"]) && !empty($_REQUEST["vh_vroot"])) {
    $vroot = (int) $_REQUEST["vh_vroot"];
}
if ($vroot !== NULL) {
    if (! isset($vroot_list[$vroot])) {
        phorum_admin_error("vroot forum_id for saving virtual hosting config does not point to a vroot folder");
        return;
    }
} else {
    phorum_admin_error("vroot forum_id for saving virtual hosting config missing from the request");
    return;
}

// Handle processing of virtual hosts ------------------------------------

$all_virtual_hosts = virtual_hosting_db_gethostnames();
$ok_hosts = array();
$form_hosts = array();
$errors = 0;
$errorhosts = array(); // For highlighting the error hosts in the form
foreach ($_POST["vh_hostnames"] as $id => $hostname)
{
    // All hosts are stored in lower case.
    $hostname = strtolower(trim($hostname));

    // Don't process the hostname if it's empty.
    if ($hostname == '') continue;

    // Keep track of what was entered in the form, so we  can present
    // the same data in case of errors.
    $form_hosts[$hostname] = $hostname;

    // Check if the hostname format is correct.
    if (!preg_match('/^[\w-\.]+$/', $hostname)) {
        phorum_admin_error(htmlspecialchars($hostname) . ": " .
                           "Invalid host name format");
        $errorhosts[$hostname] = $hostname;
        $errors ++;
        continue;
    }

    // Check if the hostname is not already in use for another vroot.
    if (isset($all_virtual_hosts[$hostname]) &&
        $all_virtual_hosts[$hostname] != $vroot) {
        phorum_admin_error("$hostname is already in use by " .
                           "<a href=\"{$_SERVER["PHP_SELF"]}?module=editfolder&forum_id=" .
                           $all_virtual_hosts[$hostname] . "\">vroot " .
                           $all_virtual_hosts[$hostname] .
                           "</a>");
        $errorhosts[$hostname] = $hostname;
        $errors ++;
        continue;
    }

    // This hostname is valid.
    $ok_hosts[$hostname] = $hostname;
}

if (! $errors) {
    // See what hostnames we have to add and delete for the current vroot.
    $my_virtual_hosts = virtual_hosting_db_gethostnames($vroot);
    $add = array();
    $del = $my_virtual_hosts;
    foreach ($ok_hosts as $hostname) {
        // Already linked.
        if (isset($my_virtual_hosts[$hostname])) {
            unset($del[$hostname]);
            continue;
        // Not yet linked.
        } else {
            $add[$hostname] = $vroot;
        }
    }

    // Update the database.
    $error = NULL;
    foreach ($add as $hostname => $vroot) {
        $error = virtual_hosting_db_linkhostname($vroot, $hostname);
        if ($error) {
            phorum_admin_error($error);
            $errorhosts[$hostname] = $hostname;
            $errors ++;
            break;
        }
    }
    if (!$errors) {
        foreach ($del as $hostname => $vroot) {
            $error = virtual_hosting_db_unlinkhostname($vroot, $hostname);
            if ($error) {
                phorum_admin_error($error);
                $errorhosts[$hostname] = $hostname;
                $errors ++;
                break;
            }
        }
    }
}

// Handle processing of settings overrides -------------------------------

$overrides = array(
    "system_email_from_name"    => NULL,
    "system_email_from_address" => NULL,
);

foreach ($overrides as $k => $v) {
    $formk = "vh_$k";
    if (isset($_POST[$formk])) {
        $v = trim($_POST[$formk]);
        if ($v == '') $v = NULL;
        $overrides[$k] = $v;
    }
}

$error = virtual_hosting_db_setvrootconfig($vroot, $overrides);
if ($error !== NULL) {
    phorum_admin_error($error);
    $errors ++;
}

// Finish up -------------------------------------------------------------

if ($errors) {
    // There was an error, so we have to show the form in the current
    // editing state. This is done by passing the field data through
    // $PHORUM["MOD_VIRTUAL_HOSTING"]["FORM"] to the edit form code.
    $GLOBALS["PHORUM"]["MOD_VIRTUAL_HOSTING"]["FORM"] = array(
        "hostnames"    => $form_hosts,
        "errorhosts"   => $errorhosts,
        "overrides"    => $overrides,
    );
    return;
}

// All went well!
phorum_admin_okmsg("Virtual hosting settings saved successfully");
return true;

?>
