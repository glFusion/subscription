<?php
/**
 * Configuration Defaults for the Subscription plugin for glFusion.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2018 Lee Garner
 * @package     subscription
 * @version     v0.2.2
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */


// This file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/** Subscriptions plugin configuration defaults.
 * @global array */
global $subscrConfigData;
$subscrConfigData = array(
    array(
        'name' => 'sg_main',
        'default_value' => NULL,
        'type' => 'subgroup',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'subscription',
    ),
    array(
        'name' => 'fs_main',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'subscription',
    ),
    array(
        'name' => 'show_in_pp_cat',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 10,
        'set' => true,
        'group' => 'subscription',
    ),
    array(
        'name' => 'grace_days',
        'default_value' => '2',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'subscription',
    ),
    array(
        'name' => 'early_renewal',
        'default_value' => '14',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'subscription',
    ),
    array(
        'name' => 'notifydays',
        'default_value' => '14',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 40,
        'set' => true,
        'group' => 'subscription',
    ),
    array(
        'name' => 'debug',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 50,
        'set' => true,
        'group' => 'subscription',
    ),
    array(
        'name' => 'displayblocks',
        'default_value' => '3',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 13,
        'sort' => 60,
        'set' => true,
        'group' => 'subscription',
    ),
    array(
        'name' => 'onmenu',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 70,
        'set' => true,
        'group' => 'subscription',
    ),
    array(
        'name' => 'return_url',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 80,
        'set' => true,
        'group' => 'subscription',
    ),
    array(
        'name' => 'ena_ratings',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 90,
        'set' => true,
        'group' => 'subscription',
    ),

    // New Product Defaults
    array(
        'name' => 'fs_defaults',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'subscription',
    ),
    array(
        'name' => 'enabled',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => 3,
        'sort' => 10,
        'set' => true,
        'group' => 'subscription',
    ),
    array(
        'name' => 'show_in_block',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => 3,
        'sort' => 20,
        'set' => true,
        'group' => 'subscription',
    ),
    array(
        'name' => 'taxable',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => 3,
        'sort' => 30,
        'set' => true,
        'group' => 'subscription',
    ),
);


/**
 * Initialize Subscriptions plugin configuration.
 *
 * @param   integer $group_id   Group ID to use as the plugin's admin group
 * @return  boolean             true: success; false: an error occurred
 */
function plugin_initconfig_subscription($group_id = 0)
{
    global $subscrConfigData;

    $c = config::get_instance();
    if (!$c->group_exists('subscription')) {
        foreach ($subscrConfigData AS $cfgItem) {
            $c->add(
                $cfgItem['name'],
                $cfgItem['default_value'],
                $cfgItem['type'],
                $cfgItem['subgroup'],
                $cfgItem['fieldset'],
                $cfgItem['selection_array'],
                $cfgItem['sort'],
                $cfgItem['set'],
                $cfgItem['group']
            );
        }
    }
    return true;
}


/**
 * Sync the configuration in the DB to the above configs.
 */
function plugin_updateconfig_subscription()
{
    global $subscrConfigData;

    USES_lib_install();
    _update_config('subscription', $subscrConfigData);
}

?>
