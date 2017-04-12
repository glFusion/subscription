<?php
/**
*   Web service functions for the Subscription plugin.
*   This file provides functions to be called by other plugins, such
*   as the PayPal plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2011 Lee Garner <lee@leegarner.com>
*   @package    subscription
*   @version    0.1.3
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

    // Create a return array with values to be populated later
    $output = array('product_id' => implode(':', $A),
            'name' => 'Unknown',
            'short_description' => 'Unknown Subscription Item',
            'description'       => '',
            'price' => '0.00',
    );

    if (isset($A[1]) && !empty($A[1])) {
        $A[1] = COM_sanitizeID($A[1]);
        $sql = "SELECT item_id, short_description, description, price, upg_from, upg_price
                FROM {$_TABLES['subscr_products']} 
                WHERE item_id='{$A[1]}'";
        // Suppress sql errors to avoid breaking the IPN process, but log
        // them for review
        $res = DB_query($sql, 1);
        if ($res) {
            $info = DB_fetchArray($res, false);
        } else {
            COM_errorLog("service_productinfo_subscription() SQL error: $sql", 1);
            $info = array();
        }
        if (!empty($info)) {
            $output['short_description'] = $info['short_description'];
            $output['name'] = $info['short_description'];
            $output['description'] = $info['description'];
            //if (isset($custom['sub_type']) && 
            //        $custom['sub_type'] == 'upgrade' &&
            if (isset($A[2]) && $A[2] == 'upgrade' &&
                    !empty($info['upg_from']) &&
                    $info['upg_price'] > 0) {
                $output['price'] = $info['upg_price'];
                $output['name'] .= ', ' . $LANG_SUBSCR['upgrade'];
            } else {
                $output['price'] = $info['price'];
            }
        }
    }

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
    global $_CONF, $_CONF_SUBSCR, $_TABLES, $LANG_DON;

    // Must have an item ID following the plugin name
    $item = $args['item'];
    $ipn_data = $args['ipn_data'];

    $id = explode(':', $item['item_id']);
    if (isset($id[1])) {
        $subscr_id = COM_sanitizeID($id[1], false);
        $sql = "SELECT * FROM {$_TABLES['subscr_products']}
                WHERE item_id='{$id[1]}'";
        $res = DB_query($sql, 1);
        $A = $res ? DB_fetchArray($res, false) : array();
    } else {
        return PLG_RET_ERROR;
    }

    $amount = (float)$ipn_data['pmt_gross'];

    // Initialize the return array
    $output = array('product_id' => implode(':', $id),
            'name' => $A['name'],
            'short_description' => $A['name'],
            'description' => $A['description'],
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

    if (!empty($ipn_data['memo'])) {
        $memo = DB_escapeString($ipn_data['memo']);
    } else {
        $memo = '';
    }

    COM_errorLog("Processing subscription for user $uid to item {$id[1]}");
    USES_subscription_class_subscription();

    $S = new \Subscription\Subscription();
    //$S->Add($uid, $id[1], $A['duration'], $A['duration_type'], $A['expiration']);
    $upgrade = isset($id[2]) && $id[2] == 'upgrade' ? true : false;
    $status = $S->Add($uid, $id[1], 0, '', NULL, $upgrade, $ipn_data['txn_id'], $amount);

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

    $item = $args['item'];      // array of item number info
    $paypal_data = $args['ipn_data'];

    // Must have an item ID following the plugin name
    if (!is_array($item) || !isset($item[1]))
        return PLG_RET_ERROR;

    // User ID is provided in the 'custom' field, so make sure it's numeric.
    if (is_numeric($paypal_data['custom']['uid']))
        $uid = (int)$paypal_data['custom']['uid'];
    else
        $uid = 1;

    // Get the current subscription for this product and user and cancel it
    $sub_id = DB_getItem($_TABLES['subscr_subscriptions'],
            array('item_id', 'uid'),
            array($item[1], $uid));
    if (!empty($sub_id)) {
        USES_subscription_class_subscription();
        Subscription::Cancel($sub_id, true);
    }
    return PLG_RET_OK;
}


/**
*   Get the products under a given category (categroy not used)
*
*   @param  string  $cat    Name of category (unused)
*   @return array           Array of product info, empty string if none
*/
function service_getproducts_subscription($args, &$output, &$svc_msg)
{
    global $_TABLES, $_CONF_SUBSCR;

    // Initialize the return value as empty.
    $output = array();

    // If we're not configured to show campaigns in the Paypal catalog,
    // just return
    if ($_CONF_SUBSCR['show_in_pp_cat'] != 1) {
        return $output;
    }

    // Determine if the current user is subscribed already.
    $sql = "SELECT item_id, expiration
            FROM {$_TABLES['subscr_subscriptions']}
            WHERE uid = '" . (int)$_USER['uid'] . "'
            AND status = '" . SUBSCR_STATUS_ENABLED . "'";
    $res = DB_query($sql, 1);
    $mySub = $res ? DB_fetchArray($res, false) : array();
    if (empty($mySub)) {
        $mySub = array('item_id' => '', 'expiration' => '');
    }

    // Select products where the user either isn't subscribed, or is
    // subscribed and the expiration is within early_renewal days from now.
    // FIXME: this doesn't pick up non-subscribed users
    $sql = "SELECT p.item_id
            FROM {$_TABLES['subscr_products']} p
            WHERE p.enabled = 1 ";
    if (!SUBSCR_isAdmin()) {
        $sql .= SEC_buildAccessSql();
    }
    $result = DB_query($sql);
    if (!$result)
        return PLG_RET_ERROR;

    USES_subscription_class_product();
    $P = new \Subscription\SubscriptionProduct();

    while ($A = DB_fetchArray($result)) {
        $P->Read($A['item_id']);

        $description = $P->description;
        $short_description = $P->short_description;

        $ok_to_buy = true;
        if (!empty($mySub['expiration']) && $mySub['item_id'] == $P->item_id) {
            $exp_ts = strtotime($mySub['expiration']);
            $exp_format = strftime($_CONF['shortdate'], $exp_ts);
            $description .=
                "<br /><i>{$LANG_SUBSCR['your_sub_expires']} $exp_format</i>";
            if ($P->early_renewal > 0) {
                $renew_ts = $exp_ts - ($P->early_renewal * 86400);
                if ($renew_ts > date('U')) $ok_to_buy = false;
            }
        }
        if ($P->upg_from == $mySub['item_id'] && $P->upg_price != '') {
            $price = (float)$P->upg_price;
            $item_option = ':upgrade';
        } else {
            $price = (float)$P->price;
            $item_option = ':new';
        }

        if ($ok_to_buy) {
            $output[] = array(
                'id'    => 'subscription:' . $P->item_id . $item_option,
                'name' => $P->name,
                'short_description' => $short_description,
                'description' => $description,
                'price' => $price,
                'buttons' => array('buy_now' => $P->MakeButton()),
                'url' => COM_buildUrl(SUBSCR_URL .
                    '/index.php?view=detail&item_id=' . $P->item_id),
            );
        }
    }

    return PLG_RET_OK;
}

?>
