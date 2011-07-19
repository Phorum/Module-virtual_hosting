<?php

// Check if we are loaded from the Phorum code.
// Direct access to this file is not allowed.
if (! defined("PHORUM")) return;

/**
 * In this hook, we will check if the current host should be subjected
 * to virtual hosting rules. If this is the case, then we will make sure
 * that the requested forum_id lies within the virtual host's vroot.
 */
function phorum_mod_virtual_hosting_common_pre()
{
    global $PHORUM;

    // Register the "vroot" field as a valid field for the user API code.
    $GLOBALS['PHORUM']['API']['user_fields']['vroot'] = 'int';

    require_once("./mods/virtual_hosting/db.php");

    // Check and handle automatic installation and upgrading
    // of the database structure. Do not continue running the
    // Virtual Hosting module in case the installation fails.
    if (! virtual_hosting_db_install()) return;

    // We do not want to run the code below when we are in the
    // admin interface.
    if (defined("PHORUM_ADMIN")) return;

    // Any webserver should support HTTP_HOST and any client should
    // send a Host: header (part of HTTP 1.1), but to be sure, we'll
    // fall back to the configured http path in case the client or
    // server does not do the HTTP 1.1 host header stuff.
    $host = isset($_SERVER["HTTP_HOST"])
          ? $_SERVER["HTTP_HOST"]
          : NULL;
    if ($host === NULL) {
        if (preg_match('!^\w+://([^/]+)!', $PHORUM["http_path"], $m)) {
            $host = $m[1];
        } else {
            // This code should never be reached on a correctly
            // configured system. But hey, we're paranoid...
            // Fall back to the SERVER_NAME.
            $host = $_SERVER["SERVER_NAME"];
        }
    }
    $host = strtolower($host);

    // Construct a valid http_path setting for the current virtual host.
    // We have to override the http path setting from the general settings
    // in the Phorum admin to construct correct URLs.
    $http_path =
      (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on"?"https":"http") .
      "://" . $host .
      (isset($_SERVER["SERVER_PORT"]) &&
       $_SERVER["SERVER_PORT"] != 80 && $_SERVER["SERVER_PORT"] != 443
          ? ":" . $_SERVER["SERVER_PORT"] : "") .
      dirname($_SERVER["PHP_SELF"]);
    $http_path = preg_replace('!/$!', '', $http_path);

    // Store the constructed http path in the settings.
    $PHORUM["http_path"] = $http_path;

    // Check if the current host name is linked to a vroot. If this is
    // not the case, then the virtual hosting mod is out of business.
    $vroot = virtual_hosting_db_getvrootbyname($host);
    if ($vroot === NULL) return;

    // Retrieve override settings for the vroot.
    $vconf = virtual_hosting_db_getvrootconfig($vroot);
    if ($vconf !== NULL) {
        foreach ($vconf as $setting => $value) {
            $PHORUM[$setting] = $value;
        }
    }

    // Yes, virtual root found. Keep reference of the found data for
    // later use (this variable is also used by other hooks to see
    // if virtual hosting is active).
    $PHORUM["MOD_VIRTUAL_HOST"] = array(
        "vroot" => $vroot,
        "vconf" => $vconf
    );

    // Force the visitor to the vroot for the current virtual host.
    // Only forum_ids that are within the vroot are allowed. If a
    // forum_id outside the vroot is requested, then the visitor is
    // sent to the vroot index instead.
    $PHORUM["vroot"] = $vroot;
    $forums = phorum_db_get_forums(0, -1, $vroot);
    if (!isset($forums[$PHORUM["forum_id"]])) {
        $PHORUM["forum_id"] = $vroot;
    }
}

/**
 * This hook takes care of setting the user's vroot field, when a new
 * user registers for an account.
 */
function phorum_mod_virtual_hosting_after_register($userdata)
{
    // Only run this if we detected a virtual hosting environment.
    if (! isset($GLOBALS["PHORUM"]["MOD_VIRTUAL_HOST"])) return;

    phorum_api_user_save_raw(array(
        "user_id" => $userdata["user_id"],
        "vroot"   => $GLOBALS["PHORUM"]["vroot"]
    ));

    return $userdata;
}

/**
 * This hook extends the user management form in the admin interface,
 * to include a field for showing the vroot to which the user is linked.
 * The field can also be used to change the linked vroot.
 */
function phorum_mod_virtual_hosting_admin_users_form($frm, $user)
{
    $vroot_list=phorum_get_forum_info(3, -1);
    $select_list = array(0 => "No vroot, top level folder");
    foreach($vroot_list as $k => $v) {
        $select_list[$k] = $v;
    }

    $vroot_select = $frm->select_tag("vroot", $select_list, $user["vroot"]);
    $row = $frm->addrow("User vroot", $vroot_select);

    $frm->addhelp($row, "User vroot", "This field is part of the Virtual Hosting module. It shows the vroot to which this user is tied. This normally is the vroot through which the user signed up for his account. The vroot can be changed from this screen, by selecting a different one from the pulldown menu.");

    return array($frm, $user);
}

/**
 * This hook will store the linked vroot for a user, in case the
 * administrator changed it on the user management form in the
 * admin interface.
 */
function phorum_mod_virtual_hosting_admin_users_form_save($userdata)
{
    if (!isset($_POST["vroot"])) return $userdata; // should not happen

    $vroot_list = phorum_get_forum_info(3, -1);
    if ($_POST["vroot"] == 0 || isset($vroot_list[$_POST["vroot"]])) {
        phorum_api_user_save_raw(array(
            "user_id" => $userdata["user_id"],
            "vroot"   => $_POST["vroot"]
        ));
    }

    return $userdata;
}

/**
 * This hook adds the virtual host editing form to the bottom of the
 * "editfolder" admin module page, in case the settings for a vroot
 * folder are handled.
 */
function phorum_mod_virtual_hosting_admin_editfolder_form($frm, $forum_settings)
{
    if ($forum_settings["forum_id"] && $forum_settings["forum_id"] &&
        $forum_settings["vroot"] == $forum_settings["forum_id"]) {
        $_REQUEST["vh_vroot"] = $forum_settings["forum_id"];
        include("./mods/virtual_hosting/settings/edit_vroot.php");
    }
    return array($frm, $forum_settings);
}

/**
 * This hook stores the virtual root configuration that was saved through
 * the "editfolder" admin module page.
 */
function phorum_mod_virtual_hosting_admin_editfolder_form_save($folder_settings)
{
    if (isset($_POST["vh_action"]) && $_POST["vh_action"] == "edit_vroot")
    {
        // In case the vroot checkbox was switched off, then make sure that
        // the list of vroot hosts is cleaned up.
        if (!isset($_POST["vroot"])) {
            $_POST["vh_hostnames"] = array();
        }

        $ret = include("./mods/virtual_hosting/settings/edit_vroot_save.php");
        if (! $ret) {
            $folder_settings["error"] = "Saving virtual host settings failed";
        }
    }

    // Clean up the data that was added for this module. Since the
    // admin page uses the $_POST array directly for storing
    // settings, it will trip if we don't clean up this data.
    foreach ($folder_settings as $k => $v) {
        if (substr($k, 0, 3) == "vh_") {
            unset($folder_settings[$k]);
        }
    }

    return $folder_settings;
}

?>
