<?php
/**
*   Web service functions for the Subscription plugin.
*   This file provides functions to be called by other plugins, such
*   as the PayPal plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2011-2018 Lee Garner <lee@leegarner.com>
*   @package    subscription
*   @version    0.2.2
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own!');
}


/**
*   Get the query element needed when collecting data for the Profile plugin.
*   The $output array contains the field names, the SELECT and JOIN queries,
*   and the search fields for the ADMIN_list function.
*
*   @param  array   $args       Unused
*   @param  array   &$output    Pointer to output array
*   @param  array   &$svc_msg   Unused
*   @return integer             Status code
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
            $pi . '_membertype' => array(
                'field' => $prod . '.prf_type',
                'title' => $LANG_SUBSCR['member_type'],
            ),
        ),

        'query' => "{$sub}.expiration as {$pi}_expires,
                    {$prod}.prf_type as {$pi}_membertype,
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
*   Get information about a specific item.
*
*   @param  array   $A          Item Info (pi_name, item_type, item_id)
*   @param  array   $custom     Custom parameters
*   @return array       Complete product ID, description, price
*/
function service_productinfo_subscription($A, &$output, &$svc_msg)
{
    global $_TABLES, $LANG_SUBSCR;

    // Does not support remote web services, must be local only.
    if ($A['gl_svc'] !== false) return PLG_RET_PERMISSION_DENIED;
    // remove to prevent extra ':' in product_id.
    if (isset($A['gl_svc'])) unset($A['gl_svc']);

    // Verify that item id is passed in
    $item = SUBSCR_getVar($A, 'item_id', 'array');
    if (!is_array($item)) return PLG_RET_ERROR;

    // Create a return array with values to be populated later
    $output = array(
            'product_id' => implode(':', $item),
            'name' => 'Unknown',
            'short_description' => 'Unknown Subscription Item',
            'description'       => '',
            'price' => '0.00',
            'taxable' => 0,
            'have_detail_svc' => true,  // Tell Paypal to use it's detail page wrapper
            'buynow_qty' => 1,
    );

    $item_id = $item[0];        // get base product ID
    $item_mod = SUBSCR_getVar($item, 1, 'string', 'new');
    $P = Subscription\Product::getInstance($item_id);
    if ($P->isNew) {
        COM_errorLog(__FUNCTION__ . " Item {$item_id} not found.");
        return PLG_RET_ERROR;
    }
    $output['short_description'] = $P->short_description;
    $output['name'] = $P->short_description;
    $output['description'] = $P->description;
    if ($item_mod == 'upgrade' && $P->upg_from != '' && $P->upg_price > 0) {
        $output['price'] = $P->upg_price;
        $output['name'] .= ', ' . $LANG_SUBSCR['upgrade'];
    } else {
        $output['price'] = $P->price;
    }
    $output['url'] = COM_buildUrl(SUBSCR_URL .
                    '/index.php?view=detail&item_id=' . $P->item_id);
    return PLG_RET_OK;
}


/**
*   Handle the purchase of a product via IPN message.
*
*   @param  array   $id     Array of (pi_name, category, item_id)
*   @param  array   $item   Array of item info for this purchase
*   @param  array   $ipn_data    All Paypal data from IPN
*   @return array           Array of item info, for notification
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
    if (empty($item_id)) return PLG_RET_ERROR;
    if (!isset($args['ipn_data']) || empty($args['ipn_data'])) return PLG_RET_ERROR;
    $ipn_data = $args['ipn_data'];

    // Get rid of paypal-supplied options, not used here
    list($item_id) = explode('|', $item_id);
    $id_parts = explode(':', $item_id);
    if (!isset($id_parts[1])) {
        return PLG_RET_ERROR;
    }

    $product_id = $id_parts[1];
    $P = Subscription\Product::getInstance($product_id);
    if ($P->isNew) {
        return PLG_RET_ERROR;
    }
    $upgrade = isset($id_parts[2]) && $id_parts[2] == 'upgrade' ? true : false;
    $amount = (float)$ipn_data['pmt_gross'];

    // Initialize the return array
    $output = array(
            'product_id' => $item_id,
            'name' => $P->name,
            'short_description' => $P->name,
            'description' => $P->description,
            'price' =>  $amount,
            'expiration' => NULL,
            'download' => 0,
            'file' => '',
    );

    // User ID is returned in the 'custom' field, so make sure it's numeric.
    if (is_numeric($ipn_data['custom']['uid']))
        $uid = (int)$ipn_data['custom']['uid'];
    else
        $uid = DB_getItem($_TABLES['users'], 'email', $ipn_data['payer_email']);

    /*if (!empty($ipn_data['memo'])) {
        $memo = DB_escapeString($ipn_data['memo']);
    } else {
        $memo = '';
    }*/

    COM_errorLog("Processing subscription for user $uid to item {$product_id}");
    $txn_id = SUBSCR_getVar($ipn_data, 'txn_id', 'string', 'undefined');
    $S = Subscription\Subscription::getInstance($uid, $product_id);
    $status = $S->Add($uid, $product_id, 0, '', NULL, $upgrade, $txn_id);
    return $status == true ? PLG_RET_OK : PLG_RET_ERROR;
}


/**
*   Handle a product refund
*
*   @param  array   $args       Array of item and IPN data
*   @param  array   &$output    Return array
*   @param  string  &$svc_msg   Unused
*   @return integer     Return value
*/
function service_handleRefund_subscription($args, &$output, &$svc_msg)
{
    global $_TABLES;

    $item = $args['item_id'];      // array of item number info
    $paypal_data = $args['ipn_data'];

    // Must have an item ID following the plugin name
    if (!is_array($item) || !isset($item[1]))
        return PLG_RET_ERROR;

    // User ID is provided in the 'custom' field, so make sure it's numeric.
    if (isset($paypal_data['custom'])) {
        $uid = SUBSCR_getVar($paypal_data['custom'], 'uid', 'int', 1);
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
*   Get the products under a given category (categroy not used)
*
*   @deprecated - Paypal no longer includes products, only categories
*   @param  string  $cat    Name of category (unused)
*   @return array           Array of product info, empty string if none
*/
function service_getproducts_subscription($args, &$output, &$svc_msg)
{
    global $_CONF_SUBSCR, $_CONF, $LANG_SUBSCR;

    // Initialize the return value as empty.
    $output = array();

    // If we're not configured to show campaigns in the Paypal catalog,
    // just return
    if ($_CONF_SUBSCR['show_in_pp_cat'] != 1) {
        return PLG_RET_ERROR;
    }

    $Subs = Subscription\Subscription::getSubscriptions();
    $Products = Subscription\Product::getProducts();
    if (!$Products) return PLG_RET_ERROR;

    foreach ($Products as $P) {
        $description = $P->description;
        $short_description = $P->short_description;

        // Check the expiration and early renewal period for any current
        // subscriptions to see if the current user can purchase this item.
        $ok_to_buy = true;
        if (isset($Subs[$P->item_id]) && $Subs[$P->item_id]->expiration > '0000') {
            $exp_ts = strtotime($Subs[$P->item_id]->expiration);
            $exp_format = strftime($_CONF['shortdate'], $exp_ts);
            $description .=
                "<br /><i>{$LANG_SUBSCR['your_sub_expires']} $exp_format</i>";
            if ($P->early_renewal > 0) {
                $renew_ts = $exp_ts - ($P->early_renewal * 86400);
                if ($renew_ts > date('U')) $ok_to_buy = false;
            }
        }
        if (array_key_exists($P->upg_from, $Subs) && $P->upg_price != '') {
            $price = (float)$P->upg_price;
            $item_option = ':upgrade';
        } else {
            $price = (float)$P->price;
            $item_option = ':new';
        }

        if ($ok_to_buy) {
            $output[] = array(
                'id'    => 'subscription:' . $P->item_id . $item_option,
                'item_id' => $P->item_id,
                'name' => $P->name,
                'short_description' => $short_description,
                'description' => $description,
                'price' => $price,
                'buttons' => array('buy_now' => $P->MakeButton()),
                'url' => COM_buildUrl(SUBSCR_URL .
                    '/index.php?view=detail&item_id=' . $P->item_id),
                'have_detail_svc' => true,  // Tell Paypal to use it's detail page wrapper
            );
        }
    }
    return PLG_RET_OK;
}


/**
*   Get the product detail page for a specific item.
*   Takes the item ID as a full paypal-compatible ID (subscription:id:opts)
*   and creates the detail page for inclusion in the paypal catalog.
*
*   @param  array   $args   Array containing item_id=>subscription:id:opts
*   @param  mixed   $output Output holder variable
*   @param  string  $svc_msg    Service message (not used)
*   @return integer         Status value
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
    $P = Subscription\Product::getInstance($item_info[1]);
    if ($P->isNew) return PLG_RET_ERROR;
    $output = $P->Detail();
    return PLG_RET_OK;
}

?>
