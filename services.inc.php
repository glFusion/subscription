<?php
/**
 * Web service functions for the Subscription plugin.
 * This file provides functions to be called by other plugins, such
 * as the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2011-2020 Lee Garner <lee@leegarner.com>
 * @package     subscription
 * @version     v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own!');
}


/**
 * Get the query element needed when collecting data for the Profile plugin.
 * The $output array contains the field names, the SELECT and JOIN queries,
 * and the search fields for the ADMIN_list function.
 *
 * @param  array   $args       Unused
 * @param  array   &$output    Pointer to output array
 * @param  array   &$svc_msg   Unused
 * @return integer             Status code
 */
function service_profilefields_subscription($args, &$output, &$svc_msg)
{
    global $LANG_SUBSCR, $_CONF_SUBSCR, $_TABLES;

    $pi = $_CONF_SUBSCR['pi_name'];
    $prod = $_TABLES['subscr_products'];
    $sub = $_TABLES['subscr_subscriptions'];

    // Does not support remote web services, must be local only.
    if ($args['gl_svc'] !== false) return PLG_RET_PERMISSION_DENIED;

    $output = array(
        'names' => array(
            $pi . '_description' => array(
                'field' => $prod . '.short_description',
                'title' => $LANG_SUBSCR['description'],
            ),
            $pi . '_expires' => array(
                'field' => $sub . '.expiration',
                'title' => $LANG_SUBSCR['expires'],
            ),
        ),

        'query' => "{$sub}.expiration as {$pi}_expires,
                    {$prod}.short_description AS {$pi}_description",

        'join' => "LEFT JOIN {$sub} ON u.uid = {$sub}.uid
                LEFT JOIN {$prod} ON {$prod}.item_id = {$sub}.item_id",

        //'where' => "({$sub}.status = " . SUBSCR_STATUS_ENABLED . ')',
        'where' => '',

        'search' => array($prod.'.short_description'),
    );

    return PLG_RET_OK;
}


/**
 * Get information about a specific item.
 *
 * @param   array   $A          Item Info (pi_name, item_type, item_id)
 * @param   array   $output     Receives the function output
 * @param   mixed   $svc_msg    Not used
 * @return  integer     Return code
 */
function service_productinfo_subscription($A, &$output, &$svc_msg)
{
    global $_TABLES, $LANG_SUBSCR;

    // Does not support remote web services, must be local only.
    if ($A['gl_svc'] !== false) {
        return PLG_RET_PERMISSION_DENIED;
    }

    // remove to prevent extra ':' in product_id.
    if (isset($A['gl_svc'])) {
        unset($A['gl_svc']);
    }

    // Verify that item id is passed in
    $item = SUBSCR_getVar($A, 'item_id', 'array');
    if (!is_array($item)) {
        return PLG_RET_ERROR;
    }

    // Create a return array with values to be populated later
    $product_id = implode(':', $item);
    $output = array(
        'product_id' => $product_id,
        'id' => $product_id,
        'name' => 'Unknown',
        'short_description' => 'Unknown Subscription Item',
        'description'       => '',
        'price' => '0.00',
        'taxable' => 0,
        'have_detail_svc' => true,  // Tell Shop to use it's detail page wrapper
        'fixed_q' => 1,         // Purchase qty fixed at 1
        'isUnique' => true,     // Only on purchase of this item allowed
        'supportsRatings' => false,
        'cancel_url' => SUBSCR_URL . '/index.php',
    );

    $item_id = $item[0];        // get base product ID
    $item_mod = SUBSCR_getVar($item, 1, 'string', 'new');
    $P = Subscription\Plan::getInstance($item_id);
    if ($P->isNew()) {
        COM_errorLog(__FUNCTION__ . " Item {$item_id} not found.");
        return PLG_RET_ERROR;
    }
    $output['short_description'] = $P->getName();
    $output['name'] = $P->getName();;
    $output['description'] = $P->getDscp();
    $output['taxable'] = $P->isTaxable();
    $output['canPurchase'] = $P->canBuy();
    if ($item_mod == 'upgrade' && $P->upg_from != '' && $P->upg_price > 0) {
        $output['price'] = $P->getUpgradePrice();
        $output['name'] .= ', ' . $LANG_SUBSCR['upgrade'];
    } else {
        $output['price'] = $P->getBasePrice();
    }
    $output['url'] = COM_buildUrl(SUBSCR_URL .
        '/index.php?view=detail&item_id=' . $P->getID());
    return PLG_RET_OK;
}


/**
 * Handle the purchase of a product via IPN message.
 *
 * @param   array   $args       Array of (pi_name, category, item_id)
 * @param   array   $output     Array of item info for this purchase
 * @param   mixed   $svc_msg    Not used
 * @return  integer     Return code
 */
function service_handlePurchase_subscription($args, &$output, &$svc_msg)
{
    global $_CONF, $_CONF_SUBSCR, $_TABLES;

    $item_id = NULL;
    if (isset($args['item']) && is_array($args['item'])) {
        if (isset($args['item']['item_id']))
            $item_id = $args['item']['item_id'];
    }
    // Must have an item ID and IPN data
    if (
        empty($item_id) ||
        !isset($args['ipn_data']) ||
        empty($args['ipn_data'])
    ) {
        return PLG_RET_ERROR;
    }
    $ipn_data = $args['ipn_data'];

    // Get rid of shop-supplied options, not used here
    list($item_id) = explode('|', $item_id);
    $id_parts = explode(':', $item_id);
    if (!isset($id_parts[1])) {
        return PLG_RET_ERROR;
    }

    $product_id = $id_parts[1];
    $P = Subscription\Plan::getInstance($product_id);
    if ($P->isNew()) {
        return PLG_RET_ERROR;
    }
    $upgrade = false;
    $ref_code = '';
    if (isset($id_parts[2])) {
        if ($id_parts[2] == 'upgrade') {
            $upgrade = true;
        } else {
            $ref_code = $id_parts[2];
        }
    }
    $upgrade = isset($id_parts[2]) && $id_parts[2] == 'upgrade' ? true : false;
    $amount = (float)$ipn_data['pmt_gross'];

    // Initialize the return array
    $output = array(
        'product_id' => $item_id,
        'name' => $P->getName(),
        'short_description' => $P->getName(),
        'description' => $P->getDscp(),
        'price' =>  $amount,
        'expiration' => NULL,
        'download' => 0,
        'file' => '',
    );

    // User ID is returned in the 'custom' field, so make sure it's numeric.
    if (!empty($ipn_data['Order'])) {
        $uid = $ipn_data['Order']->uid;
     } elseif (is_numeric($ipn_data['custom']['uid'])) {
        $uid = (int)$ipn_data['custom']['uid'];
    } else {
        $uid = DB_getItem(
            $_TABLES['users'],
            'uid',
            "email = '" . DB_escapeString($ipn_data['payer_email']) . "'"
        );
    }

    COM_errorLog("Processing subscription for user $uid to item {$product_id}");
    $txn_id = SUBSCR_getVar($ipn_data, 'txn_id', 'string', 'undefined');
    $S = Subscription\Subscription::getInstance($uid, $product_id);
    $status = $S->withUid($uid)
                ->withItemId($product_id)
                ->withUpgrade($upgrade)
                ->withTxnID($txn_id)
                ->withPrice($amount)
                ->Add();
    if ($status) {
        // Handle referrals
        if (isset($args['referrer']) && is_array($args['referrer'])) {
            $ref_uid = LGLIB_getVar($args['referrer'], 'ref_uid', 'integer');
            $ref_token = LGLIB_getVar($args['referrer'], 'ref_token');
        }
        if ($ref_uid > 0) {
            // update the referrer's subscription or other action
            $R = Subscription\Subscription::getInstance($ref_uid);
            if (date('Y-m-d') <= $S->getExpiration()) {    // is subscription still valid
                COM_errorLog("Processing affiliate bonus for user {$ref_uid} for purchase by user {$uid} of item {$product_id}");
                $status = $R->AddBonus($S);
            }
        }
    }      
    return $status == true ? PLG_RET_OK : PLG_RET_ERROR;
}


/**
 * Handle a product refund.
 *
 * @param   array   $args       Array of item and IPN data
 * @param   array   &$output    Return array
 * @param   string  &$svc_msg   Unused
 * @return  integer     Return value
 */
function service_handleRefund_subscription($args, &$output, &$svc_msg)
{
    global $_TABLES;

    $item = $args['item_id'];      // array of item number info
    $shop_data = $args['ipn_data'];

    // Must have an item ID following the plugin name
    if (!is_array($item) || !isset($item[1]))
        return PLG_RET_ERROR;

    // User ID is provided in the 'custom' field, so make sure it's numeric.
    if (isset($shop_data['custom'])) {
        $uid = SUBSCR_getVar($shop_data['custom'], 'uid', 'int', 1);
    } else {
        $uid = 1;
    }
    if ($uid == 1) return PLG_RET_OK;   // Nothing to do for anonymous

    // Get the current subscription for this product and user and cancel it
    if (Subscription\Subscription::Cancel($uid, $item[1], true)) {
        return PLG_RET_OK;
    } else {
        return PLG_RET_ERROR;
    }
}


/**
 * Get the products under a given category (categroy not used)
 *
 * @param   array   $args       Argument array (not used)
 * @param   mixed   $output     Output holder variable
 * @param   string  $svc_msg    Service message (not used)
 * @return  integer         Status value
 */
function service_getproducts_subscription($args, &$output, &$svc_msg)
{
    global $_CONF_SUBSCR, $_CONF, $LANG_SUBSCR;

    // Initialize the return value as empty.
    $output = array();

    // If we're not configured to show campaigns in the Shop catalog,
    // just return
    if ($_CONF_SUBSCR['show_in_pp_cat'] != 1) {
        return PLG_RET_ERROR;
    }

    //$Subs = Subscription\Subscription::getSubscriptions();
    $Plans = Subscription\Plan::getPlans();
    if (!$Plans) return PLG_RET_ERROR;

    foreach ($Plans as $P) {
        $description = $P->getDscp();
        $short_description = $P->getName();

        // Check the expiration and early renewal period for any current
        // subscriptions to see if the current user can purchase this item.
        $ok_to_buy = $P->canBuy();
        $price = (float)$P->getBasePrice();
        $item_option = ':new';

        $output[] = array(
            'id'    => 'subscription:' . $P->getID(). $item_option,
            'item_id' => $P->getID(),
            'name' => $P->getName(),
            'short_description' => $short_description,
            'description' => $description,
            'price' => $price,
            'buttons' => array('buy_now' => $P->MakeButton()),
            'url' => COM_buildUrl(SUBSCR_URL .
                    '/index.php?view=detail&item_id=' . $P->getID()),
            'have_detail_svc' => true,  // Tell Shop to use it's detail page wrapper
            'img_url' => '',
            'canPurchase' => $ok_to_buy,
            'canDisplay' => true,
        );
    }
    return PLG_RET_OK;
}


/**
 * Get the product detail page for a specific item.
 * Takes the item ID as a full shop-compatible ID (subscription:id:opts)
 * and creates the detail page for inclusion in the shop catalog.
 *
 * @param   array   $args   Array containing item_id=>subscription:id:opts
 * @param   mixed   $output Output holder variable
 * @param   string  $svc_msg    Service message (not used)
 * @return  integer         Status value
 */
function service_getDetailPage_subscription($args, &$output, &$svc_msg)
{
    $output = '';
    if (!is_array($args) || !isset($args['item_id'])) {
        return PLG_RET_ERROR;
    }
    $item_info = explode(':', $args['item_id']);
    if (!isset($item_info[1]) || empty($item_info[1])) {    // missing item ID
        return PLG_RET_ERROR;
    }
    $P = Subscription\Plan::getInstance($item_info[1]);
    if ($P->isNew()) return PLG_RET_ERROR;
    $output = $P->Detail();
    return PLG_RET_OK;
}


/**
 * Set text information to be included with the purchase notification.
 * Expected args: ```array(
 *      'item_id' => array(
 *          0 => item_id,
 *          1 => new vs renewal,
 *      ),
 *      'mods' => array(
 *          'uid' => user_id
 *      )
 *  )```
 *
 * @param   array   $args       Array of item information
 * @param   array   $output     Return array
 * @param   string  $svc_msg    Unused
 * @return  integer     Return value
 */
function service_emailReceiptInfo_subscription($args, &$output, &$svc_msg)
{
    global $LANG_SUBSCR;

    if (isset($args['item_id'][0])) {
        $output = $LANG_SUBSCR['msg_purch_email'];
    }
    return PLG_RET_OK;      // don't error
}
