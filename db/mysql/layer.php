<?php

/**
 * This function can be used to map a hostname to a virtual host.
 *
 * @param $hostname - A hostname to lookup.
 * @param $vroot - A vroot id if this hostname is linked to a vroot or
 *                 NULL if is is not linked.
 */
function virtual_hosting_db_getvrootbyname($hostname)
{
    $PHORUM = $GLOBALS["PHORUM"];

    $hostname = strtolower(trim($hostname));

    $row = phorum_db_interact(
        DB_RETURN_ROW,
       "SELECT vroot
        FROM {$PHORUM["virtual_hosts_table"]}
        WHERE hostname='".phorum_db_interact(DB_RETURN_QUOTED, $hostname)."'"
    );

    return $row[0];
}

/**
 * This function will retrieve host names that are configured for virtual
 * hosting. The function can either return all host names or only the host
 * names that are assigned to a certain vroot.
 *
 * @param $vroot - The vroot to search the host names for or NULL if all
 *                 host names should be returned.
 * @return $hostnames - An array of host names. The values are the vroots
 *                 to which the host names are linked.
 */
function virtual_hosting_db_gethostnames($vroot = NULL)
{
    $PHORUM = $GLOBALS["PHORUM"];

    $sql = "
        SELECT hostname,vroot
        FROM {$PHORUM["virtual_hosts_table"]}
    ";
    if ($vroot !== NULL) {
        settype($vroot, "int");
        $sql .= " WHERE vroot = $vroot";
    }

    $return = phorum_db_interact(DB_RETURN_ROWS, $sql, 0);
    foreach ($return as $id => $row) $return[$id] = $row[1];

    return $return;
}

/**
 * This function links a $hostname to a $vroot.
 *
 * @param $vroot - The vroot to link the hostname to.
 * @param $hostname - The hostname to link to the vroot.
 */
function virtual_hosting_db_linkhostname($vroot, $hostname)
{
    $PHORUM = $GLOBALS["PHORUM"];

    settype($vroot, "int");
    $hostname = strtolower(trim($hostname));

    phorum_db_interact(
        DB_RETURN_RES,
       "INSERT INTO {$PHORUM["virtual_hosts_table"]} (vroot, hostname)
        VALUES ($vroot, '".phorum_db_interact(DB_RETURN_QUOTED,$hostname)."')"
    );
}

/**
 * This function unlinks a $hostname from a $vroot.
 *
 * @param $vroot - The vroot to unlink the hostname from.
 * @param $hostname - The hostname to unlink to the vroot.
 */
function virtual_hosting_db_unlinkhostname($vroot, $hostname)
{
    $PHORUM = $GLOBALS["PHORUM"];

    settype($vroot, "int");
    $hostname = strtolower(trim($hostname));

    phorum_db_interact(
        DB_RETURN_RES,
       "DELETE FROM {$PHORUM["virtual_hosts_table"]}
        WHERE hostname = '".addslashes($hostname)."'
              AND
              vroot = $vroot"
    );
}

/**
 * This function cleans up stale vroot assignments (vroots that are linked
 * to no longer existing vroot folders).
 *
 * This was the easiest way to make sure that hostnames that were linked
 * to vroots that are deleted get cleaned up fully.
 */
function virtual_hosting_db_fixintegrity()
{
    $PHORUM = $GLOBALS["PHORUM"];

    $stale = phorum_db_interact(
        DB_RETURN_ROWS,
        "SELECT hostname,v.vroot
         FROM   {$PHORUM["virtual_hosts_table"]} AS v
                LEFT JOIN {$PHORUM["forums_table"]}
                ON v.vroot = forum_id
         WHERE  forum_id IS NULL"
    );

    foreach ($stale as $rec) {
        virtual_hosting_db_unlinkhostname($rec[0], $rec[1]);
    }
}

/**
 * This function is used to store override settings for a vroot.
 *
 * @param $vroot - The vroot to store the settings for.
 * @param $settings - The settings array to store.
 */
function virtual_hosting_db_setvrootconfig($vroot, $settings)
{
    $PHORUM = $GLOBALS["PHORUM"];

    settype($vroot, "int");

    if (!is_array($settings)) {
        return "virtual_hosting_db_setvrootconfig(): " .
               "settings argument must be an array";
    }

    phorum_db_interact(
        DB_RETURN_RES,
        "UPDATE {$PHORUM["forums_table"]}
         SET virtualhost_config = '".addslashes(serialize($settings))."'
         WHERE forum_id = $vroot"
    );
}

/**
 * This function is used to retrieve override settings for a vroot.
 *
 * @param $vroot - The vroot to retrieve the settings for.
 * @return $settings - The override settings array for the vroot or NULL
 *                 if no settings can be retrieved.
 */
function virtual_hosting_db_getvrootconfig($vroot)
{
    $PHORUM = $GLOBALS["PHORUM"];

    settype($vroot, "int");

    $rec = phorum_db_interact(
        DB_RETURN_ROW,
        "SELECT virtualhost_config
         FROM {$PHORUM["forums_table"]}
         WHERE forum_id = $vroot"
    );

    if ($rec) {
        $return = @unserialize($rec[0]);
        if (is_array($return)) return $return;
    }

    return NULL;
}

?>
