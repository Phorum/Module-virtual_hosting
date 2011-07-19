<?php
if (!defined("PHORUM_ADMIN")) return;

global $PHORUM;

// Find the vroot which is being handled ------------------------------------

// Fetch all available vroots.
$vroot_list = phorum_get_forum_info(3, -1);

// Check if a search argument for a vroot was entered. If no valid vroot
// can be found, then go to the vroot selection screen. The vroot
// REQUEST parameter "vh_vroot" is a generic vroot argument,
// which is used internally from the admin code. The vroot1/2/3 POST
// parameters are used to process the search arguments from the
// select_vroot.php settings page.
$vroot = NULL;
// Set if included from an admin page.
if (count($_REQUEST) && 
    isset($_REQUEST["vh_vroot"]) && !empty($_REQUEST["vh_vroot"])) {
    $vroot = (int) $_REQUEST["vh_vroot"];
}
// Set from search arguments from select_vroot.php.
if ($vroot === NULL && count($_POST)) {
    if (isset($_POST["vroot1"]) && $_POST["vroot1"] != '') {
        $vroot = (int) $_POST["vroot1"];
    } elseif (isset($_POST["vroot2"]) && !empty($_POST["vroot2"])) {
        $vroot = (int) $_POST["vroot2"];
    } elseif (isset($_POST["vroot3"]) && !empty($_POST["vroot3"])) {
        list ($host,$vroot) = explode(":", $_POST["vroot3"]);
        $vroot = (int) $vroot;
    }
}
if ($vroot !== NULL) {
    if (! isset($vroot_list[$vroot])) {
        phorum_admin_error("No vroot found for the current selection");
        include("./mods/virtual_hosting/settings/select_vroot.php");
        return;
    }
} else {
    phorum_admin_error("No vroot search arguments entered");
    include("./mods/virtual_hosting/settings/select_vroot.php");
    return;
}

// Page header, including some simple menu options for navigation -----------

if ($_REQUEST["module"] == "modsettings")
{ 
    print "<h1>Edit virtual hosting for vroot:<br/>" .
          "\"{$vroot_list[$vroot]}\"</h1>";
    print "<a href=\"{$_SERVER["PHP_SELF"]}?module=editfolder&forum_id={$vroot}\">Open the folder settings for this vroot</a>";
    print " | ";
    print "<a href=\"{$_SERVER["PHP_SELF"]}?module=modsettings&mod=virtual_hosting\">Select a different vroot</a>";
    print "<br/><br/>";
}

// Handle a posted form -----------------------------------------------------

if (isset($_POST["vh_action"]) && $_POST["vh_action"] == "edit_vroot" &&
    isset($_POST["vh_do_save"])) {
    include("./mods/virtual_hosting/settings/edit_vroot_save.php");
}

// Create the form ----------------------------------------------------------

// Create the form where the admin can edit settings for a vroot if 
// we're running in stand alone mode from the module settings screen.
// If we're not on the modsettings form, then we hike along with the
// already existing $frm variable.
if ($_REQUEST["module"] == "modsettings")
{ 
    include_once "./include/admin/PhorumInputForm.php";
    $frm = new PhorumInputForm ("", "post", "Save virtual hosting settings");
    $frm->hidden("module", "modsettings");
    $frm->hidden("mod", "virtual_hosting"); 
}

$frm->hidden("vh_vroot", $vroot);
$frm->hidden("vh_action", "edit_vroot");
$frm->hidden("vh_do_save", "1");

// Linked virtual hosts -----------------------------------------------------

// Find all virtual hosts that are currently linked to the vroot.
// Followup request.
if (isset($PHORUM["MOD_VIRTUAL_HOSTING"]["FORM"]) &&
    isset($PHORUM["MOD_VIRTUAL_HOSTING"]["FORM"]["hostnames"])) {
    $hostname_list = $PHORUM["MOD_VIRTUAL_HOSTING"]["FORM"]["hostnames"];
// Initial request.
} else {
    $hostname_list = virtual_hosting_db_gethostnames($vroot);
}

// If we are running within the editfolder module, then we add an event
// to the form to get a confirmation from the user in case a vroot is
// switched off. In that case, the vroot settings would get lost, so we
// want to make sure that this is what the user wants.
if ($_REQUEST["module"] == "editfolder" && count($hostname_list)) {
    $frm->add_formevent("submit", 
        'if (document.forms[1] && document.forms[1].vroot && ' .
        '!document.forms[1].vroot.checked) ' .
        'if (!confirm("You have disabled the vroot option for this folder. ' .
        'The host names for virtual hosting will be unlinked from this vroot ' .
        'if you submit this form. Do you want to continue?")) return false; ');
}

$row = $frm->addbreak("Virtual host names for this vroot");
$frm->addhelp($row, "Virtual host names for this vroot", "By linking a host name to a vroot, the Virtual Hosting module will force Phorum to only show forums from this vroot in case the URL that is used for accessing Phorum contains the configured host name. For example, if you link the host name \"noobforum.yourdomain.com\" to a vroot, then accessing \"http://noobforum.yourdomain.com/phorum/\" in the browser will only show that vroot. This way, you can make one Phorum installation look like separate Phorum installations at different host names.");

// Add text boxes for existing virtual hosts.
$idx = 0;
foreach($hostname_list as $hostname => $dummy) {
    // Highlight for hostnames that resulted in an error.
    $style = '';
    if (isset($PHORUM["MOD_VIRTUAL_HOSTING"]["FORM"]["errorhosts"][$hostname]))
        $style = ';font-weight:bold; color:red';

    $row = $frm->addrow("<span style=\"$style\">Virtual host name " . ($idx+1) . "</span>", $frm->text_box("vh_hostnames[$idx]", htmlspecialchars($hostname), 50, 253, false, 'style="width:100%"'));
    $frm->addhelp($row, "Existing virtual host", "This host name is currently linked to vroot $vroot. If you want to unlink the host name, then empty this field and save the settings.");
    $idx++;
}

// Add text box for a new virtual host.
$newvirtualhost = isset($hostname_list[$idx]) ? $hostname_list[$idx] : "";
$row = $frm->addrow("Add a new virtual host", $frm->text_box("vh_hostnames[$idx]", $newvirtualhost, 50, 253, false, 'style="width:100%"'));
$frm->addhelp($row, "Add a new virtual host", "In the text field, you can enter a host name that you want to link to this vroot. If you want to link more than one host name, then enter and submit them one by one.");

// Override settings --------------------------------------------------------

// Followup request.
if (isset($PHORUM["MOD_VIRTUAL_HOSTING"]["FORM"]) &&
    isset($PHORUM["MOD_VIRTUAL_HOSTING"]["FORM"]["overrides"])) {
    $overrides = $PHORUM["MOD_VIRTUAL_HOSTING"]["FORM"]["overrides"];
// Initial request.
} else {
    $overrides = virtual_hosting_db_getvrootconfig($vroot);
}

$row = $frm->addbreak("General Settings overrides for this vroot");
$frm->addhelp($row, "General Settings overrides", "In this section, you can override some of the settings from the General Settings admin page for this vroot. If you want to use the default value from the General Settings, then empty the field.");

$frm->addrow("System Emails From Name", $frm->text_box("vh_system_email_from_name", $overrides["system_email_from_name"], 40, NULL, false, 'style="width:100%"'));

$frm->addrow("System Emails From Address", $frm->text_box("vh_system_email_from_address", $overrides["system_email_from_address"], 40, NULL, false, 'style="width:100%"'));

// Display the form ---------------------------------------------------------

// Display the form if we're running in stand alone mode from
// the module settings screen.
if ($_REQUEST["module"] == "modsettings") {
    $frm->show();
}

?>
