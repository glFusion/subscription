<?php
/**
 * Upgrade routines for the Subscription plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2022 Lee Garner <lee@leegarner.com>
 * @package     subscription
 * @version     v1.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */

// Required to get the ADVT_DEFAULTS config values
global $_CONF, $_CONF_SUBSCR, $SUBSCR_UPGRADE;

/** Include the table creation strings */
require_once __DIR__ . "/sql/mysql_install.php";
use glFusion\Database\Database;
use glFusion\Log\Log;


/**
 * Perform the upgrade starting at the current version.
 *
 * @since   v0.1.0
 * @param   boolean $dvlp   True to ignore errors during a development update
 * @return  boolean     True on success, False on failure
 */
function SUBSCR_do_upgrade($dvlp=false)
{
    global $_TABLES, $_CONF, $_CONF_SUBSCR, $_PLUGIN_INFO;

    if (isset($_PLUGIN_INFO[$_CONF_SUBSCR['pi_name']])) {
        if (is_array($_PLUGIN_INFO[$_CONF_SUBSCR['pi_name']])) {
            // glFusion > 1.6.5
            $current_ver = $_PLUGIN_INFO[$_CONF_SUBSCR['pi_name']]['pi_version'];
        } else {
            // legacy
            $current_ver = $_PLUGIN_INFO[$_CONF_SUBSCR['pi_name']];
        }
    } else {
        return false;
    }
    $installed_ver = plugin_chkVersion_subscription();

    if (!COM_checkVersion($current_ver, '0.1.0')) {
        $current_ver = '0.1.0';
        if (!SUBSCR_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.1.1')) {
        $current_ver = '0.1.1';
        if (!SUBSCR_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!SUBSCR_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.1.2')) {
        $current_ver = '0.1.2';
        if (!SUBSCR_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!SUBSCR_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.1.3')) {
        $current_ver = '0.1.3';
        if (!SUBSCR_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!SUBSCR_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.1.4')) {
        $current_ver = '0.1.4';
        if (!SUBSCR_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!SUBSCR_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.2.0')) {
        $current_ver = '0.2.0';
        if (!SUBSCR_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!SUBSCR_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.2.1')) {
        $current_ver = '0.2.1';
        if (!SUBSCR_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!SUBSCR_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.2.2')) {
        $current_ver = '0.2.2';
        if (!SUBSCR_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!SUBSCR_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '1.0.0')) {
        $current_ver = '1.0.0';
        if (!SUBSCR_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!SUBSCR_do_set_version($current_ver)) return false;
    }
    
    if (!COM_checkVersion($current_ver, '1.1.0')) {
        $current_ver = '1.1.0';
        if (!SUBSCR_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!SUBSCR_do_set_version($current_ver)) return false;
    }

    // Update the plugin configuration
    USES_lib_install();
    require_once __DIR__ . '/install_defaults.php';
    _update_config('subscription', $subscrConfigData);

    // Final version update to catch updates that don't go through
    // any of the update functions, e.g. code-only updates
    if (!COM_checkVersion($current_ver, $installed_ver)) {
        if (!SUBSCR_do_set_version($installed_ver)) {
            return false;
        }
    }

    return true;
}


/**
 * Actually perform any sql updates.
 * Gets the sql statements from the $SUBSCR_UPGRADE array defined (maybe)
 * in the SQL installation file.
 *
 * @since   v0.1.0
 * @param   string  $version    Version being upgraded TO
 * @param   boolean $ignore_errors  True to ignore SQL errors
 * @return  boolean         True on success, False on failure
 */
function SUBSCR_do_upgrade_sql($version='', $ignore_errors=false)
{
    global $_TABLES, $_CONF_SUBSCR, $SUBSCR_UPGRADE;

    // If no sql statements passed in, return success
    if (!isset($SUBSCR_UPGRADE[$version]) || 
        !is_array($SUBSCR_UPGRADE[$version])) {   
            return true;
    }

    $db = Database::getInstance();

    // Execute SQL now to perform the upgrade
    Log::write('system', Log::INFO, "-- Updating Subscription to version $version");
    foreach($SUBSCR_UPGRADE[$version] as $sql) {
        Log::write('system', Log::INFO, "--- Subscription Plugin $version update: Executing SQL");
        try {
            $db->conn->executeStatement($sql);
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
            if ($ignore_errors) return false;
        }
    }
    Log::write('system', Log::INFO, "--- Subscription plugin SQL update to version $version done");
    return true;
}


/**
 * Update the plugin version number in the database.
 * Called at each version upgrade to keep up to date with
 * successful upgrades.
 *
 * @param   string  $ver    New version to set
 * @return  boolean         True on success, False on failure
 */
function SUBSCR_do_set_version($ver)
{
    global $_TABLES, $_CONF_SUBSCR;

    // now update the current version number.
    $db = Database::getInstance();
    try {
        $db->conn->update(
            $_TABLES['plugins'],
            array(
                'pi_version' => $_CONF_SUBSCR['pi_version'],
                'pi_gl_version' => $_CONF_SUBSCR['gl_version'],
                'pi_homepage' => $_CONF_SUBSCR['pi_url'],
            ),
            array('pi_name' => $_CONF_SUBSCR['pi_name']),
            array(Database::STRING, Database::STRING, Database::STRING, Database::STRING)
        );
    } catch (\Exception $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        return false;
    } else {
        Log::write('system', Log::INFO, "--- Updated the {$_CONF_SUBSCR['pi_display_name']} plugin version to $ver");
        return true;
    }
}

