<?php
/**
*   Installation routines for the Subscription plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010 Lee Garner
*   @package    subscription
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/** Import core glFusion functions */
require_once '../../../lib-common.php';
use glFusion\Log\Log;

// Only let Root users access this page
if (!SEC_inGroup('Root')) {
    // Someone is trying to illegally access this page
    Log::write('system', Log::ERROR,
        "Someone has tried to illegally access the subscription installation page.  User id: {$_USER['uid']}, Username: {$_USER['username']}, IP: $REMOTE_ADDR"
    );
    $display = COM_siteHeader();
    $display .= COM_startBlock($LANG_DQ['access_denied']);
    $display .= $LANG_DQ['access_denied_msg'];
    $display .= COM_endBlock();
    $display .= COM_siteFooter(true);
    echo $display;
    exit;
}

$base_path = "{$_CONF['path']}plugins/subscription";

/** Import plugin functions.  Not included since plugin isn't installed yet */
require_once $base_path . '/functions.inc';
/** Import auto-installation routines */
require_once $base_path . '/autoinstall.php';

USES_lib_install();


/* 
*   MAIN
*/
if (SEC_checkToken()) {
    if ($_GET['action'] == 'install') {
        if (plugin_install_subscription()) {
            echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php?msg=44');
            exit;
        } else {
            echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php?msg=72');
            exit;
        }
    } else if ($_GET['action'] == "uninstall") {
        USES_lib_plugin();
        if (PLG_uninstall('subscription')) {
            echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php?msg=45');
            exit;
        } else {
            echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php?msg=73');
            exit;
        }
    }
}

echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php');

?>
