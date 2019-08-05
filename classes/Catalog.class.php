<?php
/**
 * Plugin-specific functions for the Subscription plugin for glFusion.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner
 * @package     subscription
 * @version     v1.0.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Subscription;


/**
 * Class to display the product catalog.
 * @package subscription
 */
class Catalog
{
    /**
     * Diaplay the product catalog items.
     *
     * @param   integer $cat_id     Optional category ID to limit display
     * @return  string      HTML for product catalog.
     */
    public static function Render($cat_id = 0)
    {
        global $_CONF, $_CONF_SUBSCR, $LANG_SUBSCR, $_USER;

        if (!SUBSCR_shop_enabled()) {
            return "Shop plugin is required";
        }

        $T = new \Template(SUBSCR_PI_PATH . '/templates');
        $T->set_file(array(
            'prodlist'  => 'plan_list.thtml',
        ));
        $T->set_var(array(
            'pi_url'        => SUBSCR_URL,
            'user_id'       => $_USER['uid'],
        ) );

        $mySubs = Subscription::getSubscriptions($_USER['uid']);
        $Plans = Plan::getPlans();

        if (count($Plans) < 1) {
            $T->parse('output', 'prodlist');
            $retval = $T->finish($T->get_var('output', 'prodlist'));
            $retval .= '<p />' . $LANG_SUBSCR['no_products_avail'];
            return $retval;
        }

        /*$status = LGLIB_invokeService('shop', 'getCurrency', array(),
            $currency, $svc_msg);*/
        $currency = PLG_callFunctionForOnePlugin('plugin_getCurrency_shop');
        if (empty($currency)) $currency = 'USD';

        $T->set_block('prodlist', 'PlanBlock', 'PBlock');
        foreach ($Plans as $P) {
            // Skip the rare case of a fixed expiration that has passed.
            if ($P->duration_type == 'fixed' &&
                $P->expiration < $_CONF_SUBSCR['_dt']->format('Y-m-d', true)) {
                continue;
            }
            $description = $P->description;
            $price = (float)$P->price;
            $lang_price = $LANG_SUBSCR['price'];

            $ok_to_buy = true;
            $exp_msg = '';
            if (isset($mySubs[$P->item_id])) {
                $d = new \Date($mySubs[$P->item_id]->expiration);
                $exp_ts = $d->toUnix();
                $exp_format = $d->format($_CONF['shortdate']);
                $exp_msg = sprintf($LANG_SUBSCR['your_sub_expires'], $exp_format);
                if ($P->early_renewal > 0) {
                    $renew_ts = $exp_ts - ($P->early_renewal * 86400);
                    if ($renew_ts > $_CONF_SUBSCR['_dt']->toUnix()) {
                        $ok_to_buy = false;
                    }
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
                'exp_msg'   => $exp_msg,
            ) );
            $T->parse('PBlock', 'PlanBlock', true);
        }
        $T->parse('output', 'prodlist');
        return $T->finish($T->get_var('output', 'prodlist'));
    }

}

?>
