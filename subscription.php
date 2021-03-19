<?php
/**
 * Global configuration items for the Subscriptions plugin.
 * These are either static items, such as the plugin name and table
 * definitions, or are items that don't lend themselves well to the
 * glFusion configuration system, such as allowed file types.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2020 Lee Garner
 * @package     subscription
 * @version     v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

global $_DB_table_prefix, $_TABLES;
global $_CONF_SUBSCR;

$_CONF_SUBSCR['pi_name']            = 'subscription';
$_CONF_SUBSCR['pi_display_name']    = 'Subscriptions';
$_CONF_SUBSCR['pi_version']         = '1.1.0';
$_CONF_SUBSCR['gl_version']         = '1.7.0';
$_CONF_SUBSCR['pi_url']             = 'http://www.leegarner.com';

$_SUBSCR_table_prefix = $_DB_table_prefix . 'subscr_';

$_TABLES['subscr_products']      = $_SUBSCR_table_prefix . 'products';
$_TABLES['subscr_subscriptions'] = $_SUBSCR_table_prefix . 'subscriptions';
$_TABLES['subscr_history']       = $_SUBSCR_table_prefix . 'history';
$_TABLES['subscr_referrals']     = $_SUBSCR_table_prefix . 'referrals';

$_CONF_SUBSCR['logfile'] = 'subscription.log';

?>
