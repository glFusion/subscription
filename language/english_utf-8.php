<?php
/**
 * Default English Language file for the Subscription plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2020 Lee Garner
 * @package     subscription
 * @version     v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */

/**
 * The plugin's lang array
 * @global array $LANG_SUBSCR
 */
$LANG_SUBSCR = array(
'product_id'    => 'Plan ID',
'name'          => 'Name',
'enabled'       => 'Enabled',
'show_in_block' => 'Show in Block',
'taxable'       => 'Taxable',
'at_registration' => 'Subscribe at Registration',
'optional'      => 'Optional',
'automatic'     => 'Automatic',
'none'          => 'None',
'trial_period'  => 'Trial',
'days'          => 'Days',
'for'           => 'for',
'short_description'   => 'Short Description',
'description'   => 'Description',
'duration'      => 'Duration',
'duration_type' => 'Duration Period',
'price'         => 'Price',
'disc_price'    => 'Discounted Price',
'before_exp'    => 'before expiration',
'after_start'   => 'after start',
'renewal'       => 'Renewal',
'both'          => 'Both',
'submit'        => 'Submit',
'clearform'     => 'Reset Form',
'delete'        => 'Delete',
'cancel'        => 'Cancel',
'edit'          => 'Edit',
'admin_hdr'     => 'Subscription Administration',
'admin_txt'     => 'Click on a subscription name to view subscribers.',
'admin_txt_subscriptions'    => 'Add or edit subscriptions.',
'admin_txt_editsubscrip'    => 'Edit the subscription below.  Click on one of the above links to return to the admin menus.',
'admin_txt_editproduct' => '',
'block_title'   => 'Subscriptions',
'date'          => 'Date',
'expires'       => 'Expires',
'expired'       => 'Expired',
'canceled'      => 'Canceled',
'active'        => 'Active',
'status'        => 'Status',
'subscribe'     => 'Subscribe',
'subscriber'    => 'Subscriber',
'amount'        => 'Amount Paid',
'reset_buttons' => 'Reset Shop Buttons',
'txn_id'        => 'Transaction ID',
'uid'           => 'User ID',
'q_del_item'    => 'Are you sure that you want to delete this item?',
'invalid_id_req' => 'Invalid subscription ID requested.',
'disabled'      => 'Disabled',
'closed'        => 'Closed',
'comments'          => 'Comments',
'ratings_enabled'   => 'Allow Ratings',
'subscription_info' => 'Subscription Item Info',
'day'          => 'Day',
'week'         => 'Week',
'month'        => 'Month',
'year'         => 'Year',
'fixed'         => 'Fixed',
'subscriptions' => 'Subscriptions',
'plan'          => 'Plan',
'addgroup'      => 'Subscription Group',
'your_sub_expires' => 'Your subscription expires: %s',
'renew_now'     => 'Renew Now!',
'renew'         => 'Renew',
'renew_all'     => 'Renew all selected items',
'grace_days'    => 'Grace Period (days)',
'early_renewal' => 'Early Renewal (days)',
'trial_days'    => 'Trial Period (days)',
'products'      => 'Plans',
'product'       => 'Plan',
'new_product'   => 'New Plan',
'new_subscription' => 'New Subscription',
'select'        => 'Select',
'date_selector' => 'Select Date',
'expiration'    => 'Expiration',
'fixed_exp'     => 'Fixed Expiration',
'user_notified' => 'Notification Sent?',
'subscription_info' => 'Subscription Information',
'no_products_avail' => 'No subscription products match your request.',
'err_missing_uid' => 'A valid user ID is required',
'err_missing_item' => 'A valid product selection is required',
'err_noaccess'  => 'An invalid product was requested, or you may not have access to the item.  Perhaps you need to log into the site first.',
'err_no_sub_found'  => 'No subscription of type %s found for user %d.',
'exp_notice'    => 'Subscription Expiration Notice',
'confirm_renew' => 'Are you sure you want to renew the selected subscriptions?',
'system_task'   => 'System Task',
'pp_account'    => 'Shop Account',
'permissions_msg' => 'The permissions will determine who may view and purchase the subscription product.  The "Write" permission is not used.',
'invalid_product_id' => 'Invalid product ID requested',
'upg_from'      => 'Upgrade From',
'upg_price'     => 'Upgrade Price',
'upg_extend_exp' => 'Upgrade extends expiration',
'upgrade'       => 'Upgrade',
'quantity'      => 'Quantity',
'prf_update'    => 'Updates Profile',
'prf_upd_acct'  => 'Buyer Account',
'prf_upd_child' => 'Buyer and Children',
'prf_upd_all'   => 'All Related Accounts',
'none'          => 'None',
'prf_type'      => 'Profile Membertype',
'profile_integration' => 'Profile Plugin Integration',
'frm_invalid'   => 'Invalid Entry',
'member_type'   => 'Member Type',
'purch_access'  => 'Purchase allowed by',
'your_current_subs' => 'Your current subscriptions',
'all_plans'     => 'All Plans',
'show_exp'      => 'Show Expired',
'msg_toggle'    => 'Item has been %s',
'msg_unchanged' => 'Item was not changed',
'tt_view_subscribers' => 'Click to view subscribers',
'date_selector' => 'Date Selector',
'required'      => 'Required',
'hlp_admin_del' => 'Only plans which have no associated subscriptions may be deleted.',
'msg_purch_email' => 'If you are logged into the site, you may need to log out and back in again for your subscription to take effect.',
'reset_ratings' => 'Reset Ratings',
'rating'        => 'Rating',
);

// Messages for the plugin upgrade
$PLG_subscription_MESSAGE06 = 'Plugin upgrade not supported.';

// Localization of the Admin Configuration UI
$LANG_configsections['subscription'] = array(
    'label' => 'Subscriptions',
    'title' => 'Subscription Configuration',
);

$LANG_confignames['subscription'] = array(
    'grace_days'    => 'Grace Period (days)',
    'early_renewal' => 'Early Renewal (days)',
    'notifydays'    => 'Days before expiration to send notifications',
    'debug'         => 'Debugging?',
    'show_in_pp_cat' => 'Show in Shop product catalog?',
    'show_in_block' => 'Show product in block?',
    'enabled'       => 'Plan is enabled?',
    'taxable'       => 'Plan is taxable?',
    'displayblocks' => 'Display glFusion Blocks',
    'onmenu'        => 'Show on default glFusion menu?',
    'return_url'    => 'Optional after-payment URL',
    'ena_ratings'   => 'Enable Ratings?',
);

$LANG_configsubgroups['subscription'] = array(
    'sg_main' => 'Main Settings',
);

$LANG_fs['subscription'] = array(
    'fs_main' => 'Main Settings',
    'fs_defaults' => 'Plan Defaults',
);

// Note: entries 0, 1, and 12 are the same as in $LANG_configselects['Core']
$LANG_configselects['subscription'] = array(
    3 => array('Yes' => 1, 'No' => 0),
    13 => array('None' => 0, 'Left' => 1, 'Right' => 2, 'Both' => 3),
);

?>
