<?php
/**
 * Automatic installation functions for the Subscriptions plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010 Lee Garner
 * @package     subscription
 * @version     v0.0.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

global $_DB_dbms;

$pi_path = $_CONF['path'] . 'plugins/subscription';
require_once $pi_path . '/subscription.php';
require_once $pi_path . '/sql/' . $_DB_dbms . '_install.php';
require_once $pi_path . '/install_defaults.php';

$language = $_CONF['language'];
if (!is_file($pi_path . '/language/' . $language . '.php')) {
    $language = 'english';
}
global $LANG_SUBSCR;
require_once $pi_path . '/language/' . $language . '.php';

// +--------------------------------------------------------------------------+
// | Plugin installation options                                              |
// +--------------------------------------------------------------------------+

$INSTALL_plugin['subscription'] = array(
    'installer' => array(
        'type' => 'installer',
        'version' => '1',
        'mode' => 'install',
    ),

    'plugin' => array(
        'type' => 'plugin',
        'name' => $_CONF_SUBSCR['pi_name'],
        'ver' => $_CONF_SUBSCR['pi_version'],
        'gl_ver' => $_CONF_SUBSCR['gl_version'],
        'url' => $_CONF_SUBSCR['pi_url'],
        'display' => $_CONF_SUBSCR['pi_display_name'],
    ),

    array(
        'type' => 'table',
        'table' => $_TABLES['subscr_products'],
        'sql' => $_SQL['subscr_products'],
    ),

    array(
        'type' => 'table',
        'table' => $_TABLES['subscr_subscriptions'],
        'sql' => $_SQL['subscr_subscriptions'],
    ),

    array(
        'type' => 'group',
        'group' => $_CONF_SUBSCR['pi_name'] . ' Admin',
        'desc' => 'Users in this group can administer the Subscriptions plugin',
        'variable' => 'admin_group_id',
        'admin' => true,
        'addroot' => true,
    ),

    array(
        'type' => 'feature',
        'feature' => 'subscription.admin',
        'desc' => 'Ability to administer the Subscriptions plugin',
        'variable' => 'admin_feature_id',
    ),

    array(
        'type' => 'feature',
        'feature' => 'subscription.view',
        'desc' => 'Ability to view Subscriptions entries',
        'variable' => 'view_feature_id',
    ),

    array(
        'type' => 'mapping',
        'group' => 'admin_group_id',
        'feature' => 'admin_feature_id',
        'log' => 'Adding Admin feature to the admin group',
    ),

    array(
        'type' => 'mapping',
        'findgroup' => 'Logged-in Users',
        'feature' => 'view_feature_id',
        'log' => 'Adding View feature to the Logged-in Users group',
    ),

    array(
        'type' => 'block',
        'name' => 'subscription_subscribe',
        'title' => $LANG_SUBSCR['subscribe'],
        'phpblockfn' => 'phpblock_subscription_subscribe',
        'block_type' => 'phpblock',
        'group_id' => 'admin_group_id',
    ),
);


/**
 * Puts the datastructures for this plugin into the glFusion database.
 * Note: Corresponding uninstall routine is in functions.inc.
 *
 * @return  boolean     True if successful, False otherwise
 */
function plugin_install_subscription()
{
    global $INSTALL_plugin, $_CONF_SUBSCR;

    $pi_name            = $_CONF_SUBSCR['pi_name'];
    $pi_display_name    = $_CONF_SUBSCR['pi_display_name'];

    COM_errorLog("Attempting to install the $pi_display_name plugin", 1);

    $ret = INSTALLER_install($INSTALL_plugin[$pi_name]);
    if ($ret > 0) {
        return false;
    }

    return true;
}


/**
 * Loads the configuration records for the Online Config Manager.
 *
 * @return  boolean     True = proceed, False = an error occured
 */
function plugin_load_configuration_subscription()
{
    global $_CONF, $_CONF_SUBSCR, $_TABLES;

    // Get the group ID that was saved previously.
    $group_id = (int)DB_getItem($_TABLES['groups'], 'grp_id',
            "grp_name='{$_CONF_SUBSCR['pi_name']} Admin'");

    return plugin_initconfig_subscription($group_id);
}


/**
 * Plugin-specific post-installation function.
 * Creates the subscription log file.
 */
function plugin_postinstall_subscription()
{
    global $_CONF, $_CONF_SUBSCR, $_SUBSCR_DEFAULTS;

    // Create an empty log file
    if (!file_exists($_CONF['path_log'] . $_CONF_SUBSCR['logfile'])) {
        $fp = fopen($_CONF['path_log'] . $_CONF_SUBSCR['logfile'], "w+");
        if (!$fp) {
            COM_errorLog("Failed to create logfile {$_CONF_SUBSCR['logfile']}");
        } else {
            fwrite($fp, "*** Logfile Created ***\n");
        }
    }

    if (!is_writable($_CONF['path_log'] . $_CONF_SUBSCR['logfile'])) {
        COM_errorLog("Can't write to {$_CONF_SUBSCR['logfile']}");
    }

}


/**
 * Recursively create directories.
 * Included here since the 'recursive' option wasn't added to mkdir() until PHP 5.
 *
 * @param   string  $pathname   Path to create
 * @param   integer $mode       Creation mode
 * @return  boolean             Result from latest mkdir() call
 */
function mkdir_recursive($pathname, $mode=0777)
{
    COM_errorLog("mkdir: creating $pathname");
    is_dir(dirname($pathname)) || mkdir_recursive(dirname($pathname), $mode);
    return is_dir($pathname) || @mkdir($pathname, $mode);
}


?>
