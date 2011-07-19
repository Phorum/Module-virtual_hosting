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

    $sql = "
        SELECT vroot 
        FROM {$PHORUM["virtual_hosts_table"]}
        WHERE hostname='".addslashes($hostname)."'
    ";

    $conn = phorum_db_mysqli_connect();
    $res = mysqli_query($conn, $sql);
    if ($err = mysqli_error()) phorum_db_mysqli_error("$err: $sql");
    $rec = mysqli_fetch_array($res);
    return $rec ? $rec[0] : NULL;
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

    $conn = phorum_db_mysqli_connect();
    $res = mysqli_query($conn, $sql);
    if ($err = mysqli_error()) phorum_db_mysqli_error("$err: $sql");
    $return = array();
    if (mysqli_num_rows($res) > 0) {
        while ($rec = mysqli_fetch_array($res)) {
            $return[$rec[0]] = $rec[1];
        }
    }

    return $return;
}

/**
 * This function links a $hostname to a $vroot.
 *
 * @param $vroot - The vroot to link the hostname to.
 * @param $hostname - The hostname to link to the vroot.
 * @return $error - Error message if an error occurred or NULL if all went ok.
 */
function virtual_hosting_db_linkhostname($vroot, $hostname)
{
    $PHORUM = $GLOBALS["PHORUM"];

    settype($vroot, "int");
    $hostname = strtolower(trim($hostname));

    $sql = "
        INSERT INTO {$PHORUM["virtual_hosts_table"]} (vroot, hostname)
        VALUES ($vroot, '".addslashes($hostname)."')
    ";
    
    $conn = phorum_db_mysqli_connect();
    $res = mysqli_query($conn, $sql);
    if ($err = mysqli_error()) phorum_db_mysqli_error("$err: $sql");

    return $err 
        ? "Linking ".htmlspecialchars($hostname) ." to vroot $vroot failed"
        : NULL;
}

/**
 * This function unlinks a $hostname from a $vroot.
 *
 * @param $vroot - The vroot to unlink the hostname from.
 * @param $hostname - The hostname to unlink to the vroot.
 * @return $error - Error message if an error occurred or NULL if all went ok.
 */
function virtual_hosting_db_unlinkhostname($vroot, $hostname)
{
    $PHORUM = $GLOBALS["PHORUM"];

    settype($vroot, "int");
    $hostname = strtolower(trim($hostname));

    $sql = "
        DELETE FROM {$PHORUM["virtual_hosts_table"]} 
        WHERE hostname = '".addslashes($hostname)."' 
              AND
              vroot = $vroot
    ";
    
    $conn = phorum_db_mysqli_connect();
    $res = mysqli_query($conn, $sql);
    if ($err = mysqli_error()) phorum_db_mysqli_error("$err: $sql");

    return $err 
        ? "Unlinking ".htmlspecialchars($hostname) ." from vroot $vroot failed"
        : NULL;
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
    $sql =
        "SELECT v.vroot,
                hostname
         FROM   {$PHORUM["virtual_hosts_table"]} AS v
                LEFT JOIN {$PHORUM["forums_table"]}
                ON v.vroot = forum_id
         WHERE  forum_id IS NULL";

    $conn = phorum_db_mysqli_connect();
    $res = mysqli_query($conn, $sql);
    if ($err = mysqli_error()) phorum_db_mysqli_error("$err: $sql");
    if (mysqli_num_rows($res) > 0) {
        while ($rec = mysqli_fetch_array($res)) {
          virtual_hosting_db_unlinkhostname($rec[0], $rec[1]);
        }
    }
}

/**
 * This function is used to store override settings for a vroot.
 *
 * @param $vroot - The vroot to store the settings for.
 * @param $settings - The settings array to store.
 * @return $error - Error message if an error occurred or NULL if all went ok.
 */
function virtual_hosting_db_setvrootconfig($vroot, $settings)
{
    $PHORUM = $GLOBALS["PHORUM"];

    settype($vroot, "int");

    if (!is_array($settings)) {
        return "virtual_hosting_db_setvrootconfig(): " .
               "settings argument must be an array";
    }

    $sql = "
        UPDATE {$PHORUM["forums_table"]}
        SET virtualhost_config = '".addslashes(serialize($settings))."'
        WHERE forum_id = $vroot
    ";

    $conn = phorum_db_mysqli_connect();
    $res = mysqli_query($conn, $sql);
    if ($err = mysqli_error()) {
        phorum_db_mysqli_error("$err: $sql");
        return "Updating the virtual hosting settings override for " .
               "vroot $vroot failed.";
    }

    return NULL;
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

    $sql = "
        SELECT virtualhost_config
        FROM {$PHORUM["forums_table"]}
        WHERE forum_id = $vroot
    ";

    $conn = phorum_db_mysqli_connect();
    $res = mysqli_query($conn, $sql);
    if ($err = mysqli_error()) {
        phorum_db_mysqli_error("$err: $sql");
    } else {
        $rec = mysqli_fetch_array($res);
        if ($rec) {
            $return = @unserialize($rec[0]);
            if (is_array($return)) return $return;
        }
    }

    return NULL;
}


?>
