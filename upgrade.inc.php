<?php
/**
*   Upgrade routines for the Subscription plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2017 Lee Garner <lee@leegarner.com>
*   @package    subscription
*   @version    0.2.2
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

// Required to get the ADVT_DEFAULTS config values
global $_CONF, $_CONF_SUBSCR, $_SUBSCR_DEFAULTS, $_DB_dbms, $SUBSCR_UPGRADE;

/** Include the default configuration values */
require_once SUBSCR_PI_PATH . '/install_defaults.php';

/** Include the table creation strings */
require_once SUBSCR_PI_PATH . "/sql/{$_DB_dbms}_install.php";


/**
*   Perform the upgrade starting at the current version.
*
*   @since  version 0.1.0
*   @return boolean     True on success, False on failure
*/
function SUBSCR_do_upgrade()
{
    global $_TABLES, $_CONF, $_CONF_SUBSCR, $_SUBSCR_DEFAULTS, $_PLUGIN_INFO;

    if (isset($_PLUGIN_INFO[$_CONF_SUBSCR['pi_name']])) {
        $current_ver = $_PLUGIN_INFO[$_CONF_SUBSCR['pi_name']];
    } else {
        return false;
    }

    $c = config::get_instance();
    $have_config = ($c->group_exists($_CONF_SUBSCR['pi_name'])) ? 1 : 0;

    if (!COM_checkVersion($current_ver, '0.1.0')) {
        $current_ver = '0.1.0';
        if ($have_config) {
            $c->add('displayblocks', $_SUBSCR_DEFAULTS['displayblocks'],
                'select', 0, 0, 13, 210, true, $_CONF_SUBSCR['pi_name']);
        }
        if (!SUBSCR_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.1.1')) {
        $current_ver = '0.1.1';
        if (!SUBSCR_do_upgrade_sql($current_ver)) return false;
        if (!SUBSCR_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.1.2')) {
        $current_ver = '0.1.2';
        if (!SUBSCR_do_upgrade_sql($current_ver)) return false;
        if (!SUBSCR_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.1.3')) {
        $current_ver = '0.1.3';
        if (!SUBSCR_do_upgrade_sql($current_ver)) return false;
        if (!SUBSCR_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.1.4')) {
        $current_ver = '0.1.4';
        if ($have_config) {
            $c->add('onmenu', $_SUBSCR_DEFAULTS['onmenu'],
                'select', 0, 0, 3, 70, true, $_CONF_SUBSCR['pi_name']);
        }
        if (!SUBSCR_do_upgrade_sql($current_ver)) return false;
        if (!SUBSCR_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.2.0')) {
        $current_ver = '0.2.0';
        if (!SUBSCR_do_upgrade_sql($current_ver)) return false;
        if (!SUBSCR_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.2.1')) {
        $current_ver = '0.2.1';
        if (!SUBSCR_do_upgrade_sql($current_ver)) return false;
        if (!SUBSCR_do_set_version($current_ver)) return false;
    }

    return true;
}


/**
*   Actually perform any sql updates.
*   Gets the sql statements from the $SUBSCR_UPGRADE array defined (maybe)
*   in the SQL installation file.
*
*   @since  version 0.1.0
*   @param  string  $version    Version being upgraded TO
*   @param  array   $sql        Array of SQL statement(s) to execute
*   @return boolean         True on success, False on failure
*/
function SUBSCR_do_upgrade_sql($version='')
{
    global $_TABLES, $_CONF_SUBSCR, $SUBSCR_UPGRADE;

    // If no sql statements passed in, return success
    if (!is_array($SUBSCR_UPGRADE[$version])) {
        return 0;
    }

    // Execute SQL now to perform the upgrade
    COM_errorLog("--Updating Subscription to version $version");
    foreach($SUBSCR_UPGRADE[$version] as $sql) {
        COM_errorLog("Subscription Plugin $version update: Executing SQL => $sql");
        DB_query($sql, 1);
        if (DB_error()) {
            COM_errorLog("SQL Error during Subscription Plugin update", 1);
            return false;
        }
    }
    COM_errorLog("--- Subscription plugin SQL update to version $version done", 1);
    return true;
}


/**
*   Update the plugin version number in the database.
*   Called at each version upgrade to keep up to date with
*   successful upgrades.
*
*   @param  string  $ver    New version to set
*   @return boolean         True on success, False on failure
*/
function SUBSCR_do_set_version($ver)
{
    global $_TABLES, $_CONF_SUBSCR;

    // now update the current version number.
    $sql = "UPDATE {$_TABLES['plugins']} SET
            pi_version = '{$_CONF_SUBSCR['pi_version']}',
            pi_gl_version = '{$_CONF_SUBSCR['gl_version']}',
            pi_homepage = '{$_CONF_SUBSCR['pi_url']}'
        WHERE pi_name = '{$_CONF_SUBSCR['pi_name']}'";

    $res = DB_query($sql, 1);
    if (DB_error()) {
        COM_errorLog("Error updating the {$_CONF_SUBSCR['pi_display_name']} Plugin version",1);
        return false;
    } else {
        return true;
    }
}

?>
