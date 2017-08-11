<?php
/**
*   Plugin-specific functions for the Subscription plugin for glFusion.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010-2016 Lee Garner
*   @package    subscription
*   @version    0.2.2
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

namespace subscription;

/**
*   Display the subscription products available.
*
*   @return string      HTML for product catalog.
*/
function SUBSCR_ProductList()
{
    global $_TABLES, $_CONF, $_CONF_SUBSCR, $LANG_SUBSCR, $_USER, $_PLUGINS, 
            $_IMAGE_TYPE;

    if (!SUBSCR_PAYPAL_ENABLED) {
        return "PayPal is required";
    }
    USES_subscription_class_product();
    USES_subscription_class_subscription();

    $T = new \Template(SUBSCR_PI_PATH . '/templates');
    $T->set_file(array(
        'prodlist'  => 'product_list.thtml',
    ));
    $T->set_var(array(
            'pi_url'        => SUBSCR_URL,
            'user_id'       => $_USER['uid'],
    ) );

    $mySubs = Subscription::getSubscriptions($_USER['uid']);
    /*if (!empty($mySubs)) {
        // Let current members know when they expire
        $str = '<ul>';
        foreach ($mySubs as $SubObj) {
            $dt = new \Date($SubObj->expiration, $_CONF['timezone']);
            $str .= '<li>' . $SubObj->Plan->item_id . '&nbsp;&nbsp;' .
                $LANG_SUBSCR['expires'] . ':&nbsp;' . $dt->format($_CONF['shortdate']) . '</li>';
        }
        $str .= '</ul>';
        $T->set_var('current_subs', $str);
    }*/

    $options = array();

    // Create product template
    // Select products where the user either isn't subscribed, or is
    // subscribed and the expiration is within early_renewal days from now.
    // FIXME: this doesn't pick up non-subscribed users
    $sql = "SELECT p.item_id
            FROM {$_TABLES['subscr_products']} p
            WHERE p.enabled = 1 ";
    if (!SUBSCR_isAdmin()) {
        $sql .= SEC_buildAccessSql();
    }
    //COM_errorLog($sql);
    $result = DB_query($sql);
    if (!$result || DB_numRows($result) < 1) {
        $T->parse('output', 'prodlist');
        $retval = $T->finish($T->get_var('output', 'prodlist'));
        $retval .= '<p />' . $LANG_SUBSCR['no_products_avail'];
        return $retval;
    }

    $status = LGLIB_invokeService('paypal', 'getCurrency', array(),
        $currency, $svc_msg);

    if (empty($currency)) $currency = 'USD';
    $T->set_block('prodlist', 'ProductBlock', 'PBlock');

    while ($A = DB_fetchArray($result)) {
        $P = new SubscriptionProduct($A['item_id']);
        $description = $P->description;
        $price = (float)$P->price;
        $lang_price = $LANG_SUBSCR['price'];

        $ok_to_buy = true;
        if (isset($mySubs[$P->item_id])) {
        //if (!empty($mySub['expiration']) && $mySub['item_id'] == $P->item_id) {
            $d = new \Date($mySubs[$P->item_id]->expiration);
            $exp_ts = $d->toUnix();
            $exp_format = $d->format($_CONF['shortdate']);
            $description .=
                "<br /><i>{$LANG_SUBSCR['your_sub_expires']} $exp_format</i>";
            if ($P->early_renewal > 0) {
                $renew_ts = $exp_ts - ($P->early_renewal * 86400);
                if ($renew_ts > date('U')) $ok_to_buy = false;
            }
        }
        /*if ($P->upg_from == $mySub['item_id'] && $P->upg_price != '') {
            $price = (float)$P->upg_price;
            $lang_price = $LANG_SUBSCR['upg_price'];
            $options['sub_type'] = 'upgrade';
            $item_option = ':upgrade';
        } else {
            $options['sub_type'] = 'new';
            $item_option = ':new';
        }*/

        // Create variable array for purchase buttons
        $vars = array(
            'item_number'   => 'subscription:' . $P->item_id . $item_option,
            'item_name'     => $P->short_description,
            'short_description' => $P->short_description,
            'amount'        => $price,
            'quantity'      => 1,
            'taxable'       => $P->taxable,
            //'return' => SUBSCR_URL . '/index.php?action=ppthanks',
            'options'       => $options,
            'btn_type'      => 'pay_now',
            'add_cart'      => 'true',
            'unique'        => true,
        );

        $buttons = '';
        if ($ok_to_buy) {
            $status = LGLIB_invokeService('paypal', 'genButton', $vars,
                    $output, $svc_msg);
            if ($status == PLG_RET_OK) {
                $buttons = implode('<br />', $output);
            }
        }

        $T->set_var(array(
            'item_id'   => $P->item_id,
            'description' => PLG_replacetags($description),
            'price'     => COM_numberFormat($price, 2),
            'encrypted' => '',
            'currency'  => $currency,
            'purchase_btn' => $buttons,
            'lang_price' => $lang_price,
        ) );

        $display .= $T->parse('PBlock', 'ProductBlock', true);
    }

    $T->parse('output', 'prodlist');
    return $T->finish($T->get_var('output', 'prodlist'));
}


/**
 *  Display a popup text message
 *
 *  @param string $msg Text to display 
 */
function SUBSCR_popupMsg($msg)
{
    global $_CONF;

    $msg = htmlspecialchars($msg);
    $popup = COM_showMessageText($msg);
    return $popup;

}


/**
*   Display an error message in an alert-style box.
*   The incoming $msg parameter should be a string of list items
*   enclosed in &lt;li&gt; tags.  This will be enclosed in &lt;ul&gt; tags
*   to create a list of errors.
*
*   @param  string  $msg    Message to be displayed.
*   @return string          Formatted message ready for display.
*/
function SUBSCR_errorMessage($msg)
{
    $retval = '';
    if (!empty($msg)) {
        $retval .= '<span class="alert">' . "\n";
        $retval .= "<ul>$msg</ul>\n";
        $retval .= "</span>\n";
    }
    return $retval;
}


/**
*   Callback function to create text for option list items.
*
*   @deprecated
*   @param  array   $A      Complete category record
*   @param  integer $sel    Selectd item (optional)
*   @param  integer $parent_id  Parent ID from which we've started searching
*   @param  string  $txt    Different text to use for category name.
*   @return string          Option list element for a category
*/
function XSUBSCR_callbackCatOptionList($A, $sel=0, $parent_id=0, $txt='')
{
    if ($sel > 0 && $A['cat_id'] == $sel) {
        $selected = 'selected="selected"';
    } else {
        $selected = '';
    }

    if ($A['parent_id'] == 0) {
        $style = 'style="background-color:lightblue"';
    } else {
        $style = '';
    }

    if ($txt == '')
        $txt = $A['cat_name'];

    /*if (SEC_hasAccess($row['owner_id'], $row['group_id'],
                $row['perm_owner'], $row['perm_group'], 
                $row['perm_members'], $row['perm_anon']) < 3) {
            $disabled = 'disabled="true"';
    } else {
        $disabled = '';
    }*/

    $str = "<option value=\"{$A['cat_id']}\" $style $selected $disabled>";
    $str .= $txt;
    $str .= "</option>\n";
    return $str;

}


/**
*   Display the site header, with or without blocks according to configuration.
*
*   @param  string  $title  Title to put in header
*   @param  string  $meta   Optional header code
*   @return string          HTML for site header, from COM_siteHeader()
*/
function SUBSCR_siteHeader($title='', $meta='')
{
    global $_CONF_SUBSCR;

    $retval = '';

    switch($_CONF_SUBSCR['displayblocks']) {
    case 0:     // none
    case 2:     // right only
        $retval .= COM_siteHeader('none', $title, $meta);
        break;

    case 1:     // left only
    case 3:     // both
    default :
        $retval .= COM_siteHeader('menu', $title, $meta);
        break;
    }

    return $retval;

}


/**
*   Display the site footer, with or without blocks as configured.
*
*   @return string      HTML for site footer, from COM_siteFooter()
*/
function SUBSCR_siteFooter()
{
    global $_CONF_SUBSCR;

    $retval = '';

    switch($_CONF_SUBSCR['displayblocks']) {
    case 0:     // none
    case 1:     // left only
    default :
        $retval .= COM_siteFooter();
        break;

    case 2:     // right only
    case 3:     // left and right
        $retval .= COM_siteFooter(true);
        break;
    }

    return $retval;

}

?>
