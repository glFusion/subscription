<?php
/**
*   Paypal integration functions for the Subscription plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010 Lee Garner
*   @package    subscription
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/


/**
*   Get information about a specific item.
*
*   @param  array   $A          Item Info (pi_name, item_type, item_id)
*   @param  array   $custom     Custom parameters
*   @return array       Complete product ID, description, price
*/
function plugin_paypal_productinfo_subscription($A, $custom=array())
{
    global $_TABLES, $LANG_SUBSCR;
COM_errorLog("getproductinfo: " . print_r($A, true));

    // Create a return array with values to be populated later
    $retval = array('product_id' => implode(':', $A),
            'name' => 'Unknown',
            'short_description' => 'Unknown Subscription Item',
            'description'       => '',
            'price' => '0.00',
    );

    if (isset($A[1]) && !empty($A[1])) {
        $A[1] = COM_sanitizeID($A[1]);
        $info = DB_fetchArray(DB_query(
                "SELECT item_id, description, price, upg_from, upg_price
                FROM {$_TABLES['subscr_products']} 
                WHERE item_id='{$A[1]}'", 1), false);
        if (!empty($info)) {
            $retval['short_description'] = $info['description'];
            $retval['name'] = $info['name']; 
            $retval['description'] = $info['description'];
            //if (isset($custom['sub_type']) && 
            //        $custom['sub_type'] == 'upgrade' &&
            if (isset($A[2]) && $A[2] == 'upgrade' &&
                    !empty($info['upg_from']) &&
                    $info['upg_price'] > 0) {
                $retval['price'] = $info['upg_price'];
            } else {
                $retval['price'] = $info['price'];
            }
        }
    }

    return $retval;
}


/**
*   Get the products under a given category.
*
*   @deprecated
*   @param  string  $cat    Name of category (unused)
*   @return array           Array of product info, empty string if none
*/
function Xplugin_paypal_getproducts_subscription($cat='')
{
    global $_TABLES, $_USER, $_CONF_SUBSCR, $LANG_SUBSCR, $_CONF;

    // Initialize the return value as empty.
    $products = array();

    // Anonymous users can't subscribe
    if (COM_isAnonUser()) return $products;

    // If we're not configured to show campaigns in the Paypal catalog,
    // just return
    /*if ($_CONF_SUBSCR['show_in_pp_cat'] != 1) {
        return $products;
    }*/

    // Determine if the current user is subscribed already.
    $sql = "SELECT item_id, expiration
            FROM {$_TABLES['subscr_subscriptions']}
            WHERE uid = '" . (int)$_USER['uid'] . "'";
    $res = DB_query($sql, 1);
    $A = DB_fetchArray($res, false);
    if (empty($A)) {
        $A = array('item_id' => '', 'expiration' => '');
    }

    // Select products where the user either isn't subscribed, or is
    // subscribed and the expiration is within early_renewal days from now.
    // FIXME: this doesn't pick up non-subscribed users
    $sql = "SELECT p.item_id
            FROM {$_TABLES['subscr_products']} p
            WHERE p.enabled = 1";
    //COM_errorLog($sql);
    $result = DB_query($sql);
    if (!$result)
        return $products;

    $products = array();
    USES_subscription_class_product();
    $P = new SubscriptionProduct();

    while ($A = DB_fetchArray($result)) {
        $P->Read($A['item_id']);
        $description = $P->description;
        $price = (float)$P->price;

        if (!empty($A['expiration'])) {
            $exp_ts = strtotime($expiration);
            $exp_format = strftime($_CONF['shortdate'], $exp_ts);
            $description .= 
                "<br /><i>{$LANG_SUBSCR['your_sub_expires']} $exp_format</i>";
            if ($P->upg_from == $A['item_id'] && $P->upg_price != '') {
                $price = (float)$P->upg_price;
            }
        }

        $vars = array(
            'item_number'   => 'subscription:' . $P->item_id,
            'item_name'     => $P->name,
            'short_description' => $P->description,
            'amount'        => $price,
            'quantity'      => 1,
        );

        $status = LGLIB_invokeService('paypal', 'genButton', $vars,
            &$output, &$svc_msg);
        if ($status == PLG_RET_OK) {
            $buttons = $output;
        }
        /*$buttons = array(
            $P->buttons => PAYPAL_genButton($P->buttons, $vars),
            'add_cart' => PAYPAL_genButton('add_cart', $vars),
        );*/

        $products[] = array(
            'id' => 'subscriptions:' . $P->item_id,
            'name' => $P->name,
            'short_description' => $description,
            'price' => $price,
            'buttons' => $buttons,
            'url' => COM_buildURL(SUBSCR_URL . 
                    '/index.php?view=detail&item_id=' . 
                    urlencode($A['item_id'])),
            'orig_id' => $P->item_id,
        );

    }

    return $products;
}


/**
*   Handle the purchase of a product via IPN message.
*
*   @param  array   $id     Array of (pi_name, category, item_id)
*   @param  array   $item   Array of item info for this purchase
*   @param  array   $paypal_data    All Paypal data from IPN
*   @return array           Array of item info, for notification
*/
function plugin_paypal_handlePurchase_subscription($id, $item, $paypal_data)
{
    global $_CONF, $_CONF_SUBSCR, $_TABLES, $LANG_DON;

    // Must have an item ID following the plugin name
    if (isset($id[1])) {
        $id[1] = COM_sanitizeID($id[1], false);
        $sql = "SELECT * FROM {$_TABLES['subscr_products']}
                WHERE item_id='{$id[1]}'";
        $res = DB_query($sql);
        $A = DB_fetchArray($res, false);
    } else {
        $id[1] = '';
    }

    if (empty($A)) {
        $A = array(
            'item_id'   => '',
            'name'      => 'Miscellaneous',
            'description' => '',
            'price'     => 0,
        );
    }

    // Initialize the return array
    $retval = array('product_id' => implode(':', $id),
            'name' => $A['name'],
            'short_description' => $A['name'],
            'description' => $A['description'],
            'price' =>  (float)$item['mc_gross'],
            'expiration' => NULL,
            'download' => 0,
            'file' => '',
    );

    // User ID is returned in the 'custom' field, so make sure it's numeric.
    if (is_numeric($paypal_data['custom']['uid']))
        $uid = (int)$paypal_data['custom']['uid'];
    else
        $uid = DB_getItem($_TABLES['users'], 'email', $paypal_data['payer_email']);

    if (!empty($paypal_data['memo'])) {
        $memo = DB_escapeString($paypal_data['memo']);
    } else {
        $memo = '';
    }

    COM_errorLog("Processing subscription for user $uid to item {$id[1]}");
    USES_subscription_class_subscription();
    $amount = (float)$retval['price'];

    $S = new Subscription();
    //$S->Add($uid, $id[1], $A['duration'], $A['duration_type'], $A['expiration']);
    $upgrade = isset($id[2]) && $id[2] == 'upgrade' ? true : false;
    $S->Add($uid, $id[1], 0, '', NULL, $upgrade);

    //$groupid = (int)$A['addgroup'];
    //COM_errorLog("Adding user $uid to group $groupid");
    /*if ($groupid > 0) {
        USER_addGroup($groupid, $uid);
    }
    */

    //COM_errorLog(print_r($retval,true));
    return $retval;

}


?>
