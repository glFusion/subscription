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
    $newval = $_REQUEST['newval'] == 1 ? 1 : 0;

    switch ($_GET['type']) {
    case 'subscription':
        USES_subscription_class_product();
        SubscriptionProduct::toggleEnabled($newval, $_REQUEST['id']);
        break;

     default:
        exit;
    }

    $img_url = $base_url . "/" . $_CONF_SUBSCR['pi_name'] . "/images/";
    $img_url .= $newval == 1 ? 'on.png' : 'off.png';

    header('Content-Type: text/xml');
    header("Cache-Control: no-cache, must-revalidate");
    //A date in the past
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

    echo '<?xml version="1.0" encoding="ISO-8859-1"?>
    <info>'. "\n";
    echo "<newval>$newval</newval>\n";
    echo "<id>{$_REQUEST['id']}</id>\n";
    echo "<type>{$_REQUEST['type']}</type>\n";
    echo "<imgurl>$img_url</imgurl>\n";
    echo "<baseurl>{$base_url}</baseurl>\n";
    echo "</info>\n";
    break;

}

?>
