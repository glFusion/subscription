<?php
/**
*   Configuration Defaults for the Subscription plugin for glFusion.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010 Lee Garner
*   @package    subscription
*   @version    0.0.2
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*
*/


// This file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/** Subscriptions plugin configuration defaults
*   @global array */
global $_SUBSCR_DEFAULTS;
$_SUBSCR_DEFAULTS = array(
    // Grace period after expiration when access will terminate
    'grace_days'    => 2,
    // Maximum number of days before expiration that a subscription can be renewed
    'early_renewal' => 14,
    // Days before expiration to notify subscribers.  -1 = "never"
    'notifydays'    => 14,
    // Show subscription products in the Paypal catalog?
    'show_in_pp_cat' => 1,
    // Debug the plugin?
    'debug'         => 0,
    // Which glFusion blocks to show in our pages
    'displayblocks' => 3,
    // Defaults for new products
    'show_in_block'] = 0;
    'enabled'       => 1,
    'taxable'       => 0,
    'onmenu'        => 0,    // Show on site menu?
    'return_url'    => '',   // Optional paypal return override
);

/**
*   Initialize Subscriptions plugin configuration
*
*   @param  integer $group_id   Group ID to use as the plugin's admin group
*   @return boolean             true: success; false: an error occurred
*/
function plugin_initconfig_subscription($group_id = 0)
{
    global $_CONF, $_CONF_SUBSCR, $_SUBSCR_DEFAULTS;

    // Use configured default if a valid group ID wasn't presented
    if ($group_id == 0)
        $group_id = $_SUBSCR_DEFAULTS['defgrp'];

    $c = config::get_instance();

    if (!$c->group_exists($_CONF_SUBSCR['pi_name'])) {

        $c->add('sg_main', NULL, 'subgroup', 0, 0, NULL, 0, true, 
                $_CONF_SUBSCR['pi_name']);
        $c->add('fs_main', NULL, 'fieldset', 0, 0, NULL, 0, true, 
                $_CONF_SUBSCR['pi_name']);

        $c->add('show_in_pp_cat', $_SUBSCR_DEFAULTS['show_in_pp_cat'],
                'select', 0, 0, 3, 10, true, $_CONF_SUBSCR['pi_name']);

        $c->add('grace_days', $_SUBSCR_DEFAULTS['grace_days'],
                'text', 0, 0, 0, 20, true, $_CONF_SUBSCR['pi_name']);

        $c->add('early_renewal', $_SUBSCR_DEFAULTS['early_renewal'],
                'select', 0, 0, 2, 30, true, $_CONF_SUBSCR['pi_name']);

        $c->add('notifydays', $_SUBSCR_DEFAULTS['notifydays'],
                'select', 0, 0, 2, 40, true, $_CONF_SUBSCR['pi_name']);

        $c->add('debug', $_SUBSCR_DEFAULTS['debug'],
                'select', 0, 0, 3, 50, true, $_CONF_SUBSCR['pi_name']);

        $c->add('displayblocks', $_SUBSCR_DEFAULTS['displayblocks'],
                'select', 0, 0, 13, 60, true, $_CONF_SUBSCR['pi_name']);

        $c->add('onmenu', $_SUBSCR_DEFAULTS['onmenu'],
                'select', 0, 0, 3, 70, true, $_CONF_SUBSCR['pi_name']);

        $c->add('return_url', $_SUBSCR_DEFAULTS['return_url'],
                'text', 0, 0, 0, 80, true, $_CONF_SUBSCR['pi_name']);

        // Product defaults
        $c->add('fs_defaults', NULL, 'fieldset', 0, 10, NULL, 0, true, 
                $_CONF_SUBSCR['pi_name']);
 
        $c->add('enabled', $_SUBSCR_DEFAULTS['enabled'],
                'select', 0, 10, 3, 10, true, $_CONF_SUBSCR['pi_name']);

        $c->add('show_in_block', $_SUBSCR_DEFAULTS['show_in_block'],
                'select', 0, 10, 3, 20, true, $_CONF_SUBSCR['pi_name']);

        $c->add('taxable', $_SUBSCR_DEFAULTS['taxable'],
                'select', 0, 10, 3, 30, true, $_CONF_SUBSCR['pi_name']);

     }

     return true;

}

?>
