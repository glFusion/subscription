<?php
/**
 * Class to provide admin and user-facing menus.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2022 Lee Garner <lee@leegarner.com>
 * @package     subscription
 * @version     v1.0.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Subscription;

USES_lib_admin();

/**
 * Class to provide admin and user-facing menus.
 * @package subscription
 */
class Menu
{
    /**
     * Create the administrator menu.
     *
     * @param   string  $view   View being shown, so set the help text
     * @return  string      Administrator menu
     */
    public static function Admin($view='')
    {
        global $_CONF, $LANG_ADMIN, $LANG_SUBSCR;
        $menu_arr = array (
            array(
                'url' => SUBSCR_ADMIN_URL . '/index.php?products=x',
                'text' => $LANG_SUBSCR['products'],
                'active' => $view == 'products' ? true : false,
            ),
            array(
                'url' => SUBSCR_ADMIN_URL . '/index.php?subscriptions=0',
                'text' => $LANG_SUBSCR['subscriptions'],
                'active' => $view == 'subscriptions' ? true : false,
            ),
            array(
                'url' => $_CONF['site_admin_url'],
                'text' => $LANG_ADMIN['admin_home'],
            ),
        );
        if (isset($LANG_SUBSCR['admin_txt_' . $view])) {
            $hdr_txt = $LANG_SUBSCR['admin_txt_' . $view];
        } else {
            $hdr_txt = $LANG_SUBSCR['admin_txt'];
        }

        $retval = ADMIN_createMenu(
            $menu_arr,
            $hdr_txt,
            plugin_geticon_subscription()
        );
        return $retval;
    }


    /**
     * Display the site header, with or without blocks according to configuration.
     *
     * @param   string  $title  Title to put in header
     * @param   string  $meta   Optional header code
     * @return  string          HTML for site header, from COM_siteHeader()
     */
    public static function siteHeader($title='', $meta='')
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
     * Display the site footer, with or without blocks as configured.
     *
     * @return  string      HTML for site footer, from COM_siteFooter()
     */
    public static function siteFooter()
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


    /**
     * Display an error message in an alert-style box.
     * The incoming $msg parameter should be a string of list items
     * enclosed in &lt;li&gt; tags.  This will be enclosed in &lt;ul&gt; tags
     * to create a list of errors.
     *
     * @param   string  $msg    Message to be displayed.
     * @return  string          Formatted message ready for display.
     */
    public static function errorMessage($msg)
    {
        $retval = '';
        if (!empty($msg)) {
            $retval .= COM_showMessageText($msg, '', true, 'error');
        }
        return $retval;
    }

}

