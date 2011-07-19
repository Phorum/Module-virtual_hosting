<?php

if (!defined("PHORUM_ADMIN")) return;

// Create the form where the admin can select a vroot..
include_once "./include/admin/PhorumInputForm.php";
$frm = new PhorumInputForm ("", "post", "Select vroot");
$frm->hidden("module", "modsettings");
$frm->hidden("mod", "virtual_hosting"); 
$frm->hidden("vh_action", "edit_vroot");

// Explain to the admin what he's supposed to do here.
$frm->addbreak("Select the vroot for which you want to configure virtual hosting");
$frm->addmessage("If you want to add virtual hosting to a vroot folder or if you want to modify an existing virtual hosting setup, then use one of the vroot search arguments from the form below and click \"Select vroot\".");

// Create a simple text entry for entering the vroot id directly.
$frm->addrow("Numerical ID of the vroot", $frm->text_box('vroot1', '', 6));

// Create a select list of available vroots.
$vroot_list = phorum_get_forum_info(3, -1);
$select_list = array(0 => "");
foreach($vroot_list as $k => $v) $select_list[$k] = "$v (vroot $k)";
$frm->addrow("Name of the vroot", $frm->select_tag('vroot2', $select_list, '', 'style="width:100%"'));

// Create a select list of available host names.
$hostname_list = virtual_hosting_db_gethostnames();
$select_list = array(0 => "");
foreach($hostname_list as $k => $v) $select_list["$k:$v"] = "$k (vroot $v)";
$frm->addrow("Existing virtual host name", $frm->select_tag('vroot3', $select_list, '', 'style="width:100%"'));

$frm->show();

?>
