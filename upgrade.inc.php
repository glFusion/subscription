<?php
/**
*   Upgrade routines for the Subscription plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @package    subscription
*   @version    0.1.1
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
*   @param  string  $current_ver    Current version to be upgraded
*   @return integer                 Error code, 0 for success
*/
function SUBSCR_do_upgrade($current_ver)
{
    global $_TABLES, $_CONF, $_CONF_SUBSCR, $_SUBSCR_DEFAULTS;

    $error = 0;

    $c = config::get_instance();
    $have_config = ($c->group_exists($_CONF_SUBSCR['pi_name'])) ? 1 : 0;
    
    if ($current_ver < '0.1.0') {
        if ($have_config) {
            $c->add('displayblocks', $_SUBSCR_DEFAULTS['displayblocks'],
                'select', 0, 0, 13, 210, true, $_CONF_SUBSCR['pi_name']);
        }
    }

    if ($current_ver < '0.1.1') {
        $error = SUBSCR_do_upgrade_sql('0.1.1');
        if ($error > 1) return $error;
    }

    if ($current_ver < '0.1.2') {
        $error = SUBSCR_do_upgrade_sql('0.1.2');
        if ($error > 1) return $error;
    }

    if ($current_ver < '0.1.3') {
        $error = SUBSCR_do_upgrade_sql('0.1.3');
        if ($error > 1) return $error;
    }

    if ($current_ver < '0.1.4') {
        if ($have_config) {
            $c->add('onmenu', $_SUBSCR_DEFAULTS['onmenu'],
                'select', 0, 0, 3, 70, true, $_CONF_SUBSCR['pi_name']);
        }
        $error = SUBSCR_do_upgrade_sql('0.1.3');
        if ($error > 1) return $error;
    }

    return $error;

}


/**
*   Actually perform any sql updates.
*   Gets the sql statements from the $SUBSCR_UPGRADE array defined (maybe)
*   in the SQL installation file.
*
*   @since  version 0.1.0
*   @param  string  $version    Version being upgraded TO
*   @param  array   $sql        Array of SQL statement(s) to execute
*/
function SUBSCR_do_upgrade_sql($version='')
{
    global $_TABLES, $_CONF_SUBSCR, $SUBSCR_UPGRADE;

    // If no sql statements passed in, return success
    if (!is_array($SUBSCR_UPGRADE[$version])) {
        return 0;
    }

    // Execute SQL now to perform the upgrade
    COM_errorLOG("--Updating Subscription to version $version");
    foreach($SUBSCR_UPGRADE[$version] as $sql) {
        COM_errorLOG("Subscription Plugin $version update: Executing SQL => $sql");
        DB_query($sql, '1');
        if (DB_error()) {
            COM_errorLog("SQL Error during Subscription Plugin update",1);
            return 1;
            break;
        }
    }

    return 0;

}


?>
