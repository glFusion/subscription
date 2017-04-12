<?php
/**
*   Public entry point for the Subscription plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010 Lee Garner
*   @package    subscription
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

namespace subscription;

/** Import core glFusion libraries */
require_once '../lib-common.php';

// If plugin is installed but not enabled, display an error and exit gracefully
if (!in_array('subscription', $_PLUGINS)) {
    COM_404();
}

USES_subscription_functions();

if (COM_isAnonUser()) {
    echo SUBSCR_siteHeader();
    echo SEC_loginRequiredForm();
    echo SUBSCR_siteFooter();
    exit;
}

// Retrieve and sanitize input variables.  Typically _GET, but may be _POSTed.
COM_setArgNames(array('view', 'item_id'));

// Get any message ID
if (isset($_REQUEST['msg'])) {
    $msg = COM_applyFilter($_REQUEST['msg']);
} else {
    $msg = '';
}

if (isset($_REQUEST['view'])) {
    $view = COM_applyFilter($_REQUEST['view']);
} else {
    $view = COM_getArgument('view');
}
if (isset($_REQUEST['item_id'])) {
    $id = COM_sanitizeID($_REQUEST['item_id']);
} else {
    $id = COM_applyFilter(COM_getArgument('item_id'));
}

if (empty($view)) $view = 'list';

$pageTitle = $LANG_SUBSCR['subscriptions'];  // Set basic page title
$display = SUBSCR_siteHeader($pageTitle);

if ($msg != '')
    $display .= COM_showMessage($msg, $_CONF_SUBSCR['pi_name']);

switch ($view) {
case 'detail':
    if (!empty($id)) {
        USES_subscription_class_product();
        $P = new SubscriptionProduct($id);
        if ($P->hasErrors()) {
            $display .= COM_showMessageText($P->PrintErrors(), '', true);
        } else {
            $display .= $P->Detail();
        }
    } else {
        $display .= SUBSCR_ProductList();
    }
    break;

case 'list':
default:
    $display .= SUBSCR_ProductList();
    break;
}

$display .= SUBSCR_siteFooter();
echo $display;

?>
