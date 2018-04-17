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

/**
*   Display the subscription products available.
*
*   @return string      HTML for product catalog.
*/
function SUBSCR_ProductList()
{
    global $_CONF, $_CONF_SUBSCR, $LANG_SUBSCR, $_USER;

    if (!SUBSCR_paypal_enabled()) {
        return "PayPal is required";
    }

    $T = new \Template(SUBSCR_PI_PATH . '/templates');
    $T->set_file(array(
        'prodlist'  => 'product_list.thtml',
    ));
    $T->set_var(array(
            'pi_url'        => SUBSCR_URL,
            'user_id'       => $_USER['uid'],
    ) );

    $mySubs = Subscription\Subscription::getSubscriptions($_USER['uid']);
    $Products = Subscription\Product::getProducts();

    if (count($Products) < 1) {
        $T->parse('output', 'prodlist');
        $retval = $T->finish($T->get_var('output', 'prodlist'));
        $retval .= '<p />' . $LANG_SUBSCR['no_products_avail'];
        return $retval;
    }

    $status = LGLIB_invokeService('paypal', 'getCurrency', array(),
        $currency, $svc_msg);
    if (empty($currency)) $currency = 'USD';

    $T->set_block('prodlist', 'ProductBlock', 'PBlock');
    foreach ($Products as $P) {
        $description = $P->description;
        $price = (float)$P->price;
        $lang_price = $LANG_SUBSCR['price'];

        $ok_to_buy = true;
        if (isset($mySubs[$P->item_id])) {
            $d = new \Date($mySubs[$P->item_id]->expiration);
            $exp_ts = $d->toUnix();
            $exp_format = $d->format($_CONF['shortdate']);
            $description .=
                "<br /><i>{$LANG_SUBSCR['your_sub_expires']} $exp_format</i>";
            if ($P->early_renewal > 0) {
                $renew_ts = $exp_ts - ($P->early_renewal * 86400);
                if ($renew_ts > $_CONF_SUBSCR['_dt']->toUnix())
                    $ok_to_buy = false;
            }
        }

        if ($ok_to_buy) {
            $buttons = $P->MakeButton();
        } else {
            $buttons = '';
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
        $T->parse('PBlock', 'ProductBlock', true);
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
