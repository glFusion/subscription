<?php
/**
 *  Common AJAX functions.
 *
 *  @author     Lee Garner <lee@leegarner.com>
 *  @copyright  Copyright (c) 2010 Lee Garner
 *  @package    subscription
 *  @version    0.0.1
 *  @license    http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 *  @filesource
 */

/** Include required glFusion common functions */
require_once '../../../lib-common.php';

// This is for administrators only
if (!SEC_hasRights('subscription.admin')) {
    COM_accessLog("User {$_USER['username']} tried to illegally access the Subscription AJAX functions.");
    exit;
}

$base_url = $_CONF['site_url'];

switch ($_GET['action']) {
case 'toggleEnabled':
    switch ($_GET['type']) {
    case 'subscription':
        USES_subscription_class_product();
        $newval = SubscriptionProduct::toggleEnabled($_REQUEST['oldval'], $_REQUEST['id']);
        break;

     default:
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    // A date in the past to disable caching
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    $values = array(
        'newval' => $newval,
        'id'    => $_REQUEST['id'],
        'type'  => $_REQUEST['type'],
    );
    echo json_encode($values);
    break;
}

?>
