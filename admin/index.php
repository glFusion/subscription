<?php
/**
 * Administrative entry point for the Subscription plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2020 Lee Garner
 * @package     subscription
 * @version     1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion functions */
require_once('../../../lib-common.php');
use glFusion\Log\Log;

if (!in_array('subscription', $_PLUGINS)) {
    COM_404();
}

// Only let admin users access this page
if (!SEC_hasRights('subscription.admin')) {
    Log::write('system', Log::ERROR,
        "Attempted unauthorized access the Subscription Admin page." .
        " User id: {$_USER['uid']}, Username: {$_USER['username']}, " .
        " IP: $REMOTE_ADDR"
    );
    $display = COM_siteHeader();
    $display .= COM_startBlock($LANG_SUBSCR['access_denied']);
    $display .= $LANG_SUBSCR['access_denied_msg'];
    $display .= COM_endBlock();
    $display .= COM_siteFooter(true);
    echo $display;
    exit;
}

$action = '';
$actionval = '';
$expected = array(
    'edit', 'cancel',
    'saveproduct', 'deleteproduct',
    'savesubscription', 'deletesubscription',
    'renewbutton_x', 'cancelbutton_x',
    'resetratings', 'test',
    // Views
    'editproduct', 'products', 'subscriptions',
    'editsubscrip',
    'mode'
);
foreach($expected as $provided) {
    if (isset($_POST[$provided])) {
        $action = $provided;
        $actionval = $_POST[$provided];
        break;
    } elseif (isset($_GET[$provided])) {
        $action = $provided;
        $actionval = $_GET[$provided];
        break;
    }
}
if ($action == 'mode') {
    // Catch the old "mode=xxx" format
    $action = $actionval;
}


// Get the mode and page name, if any
//$view = isset($_REQUEST['view']) ?
//                COM_applyFilter($_REQUEST['view']) : $action;

// Get the product and subscription IDs, if any
// item_id could be item_id_orig if the product is being updated
if (isset($_REQUEST['item_id_orig'])) {
    $item_id = $_REQUEST['item_id_orig'];
} elseif (isset($_REQUEST['item_id'])) {
    $item_id = $_REQUEST['item_id'];
} else {
    $item_id = '';
}
$item_id = COM_sanitizeId($item_id, false);
$sub_id = isset($_REQUEST['sub_id']) ? (int)$_REQUEST['sub_id'] : 0;

$content = '';      // initialize variable for page content

switch ($action) {
case 'resetratings':
    foreach (SHOP_getVar($_POST, 'plan_bulk', 'array', array()) as $plan_id) {
        RATING_resetRating($_CONF_SUBSCR['pi_name'], $plan_id);
    }
    echo COM_refresh(SUBSCR_ADMIN_URL.'/index.php?products');
    break;

case 'saveproduct':
    $S = new Subscription\Plan($item_id);
    $status = $S->Save($_POST);
    if ($status) {
        $view = 'products';
    } else {
        $content .= Subscription\Menu::errorMessage($S->PrintErrors());
        $view = 'editproduct';
        // Force the submitted item ID to be the original
        $_POST['item_id'] = $_POST['item_id_orig'];
    }
    break;

case 'deleteproduct':
    $P = new Subscription\Plan($item_id);
    $P->Delete();
    echo COM_refresh(SUBSCR_ADMIN_URL . '/index.php?products');
    break;

case 'savesubscription':
    $uid = isset($_POST['uid']) ? $_POST['uid'] : 0;
    $item_id = isset($_POST['item_id']) ? $_POST['item_id'] : '';
    $S = Subscription\Subscription::getInstance($uid, $item_id);
    if ($S->Save($_POST)) {
        $actionval = $S->getItemID();
        echo COM_refresh(SUBSCR_ADMIN_URL.'/index.php?subscriptions=' . $item_id);
    } else {
        $content .= Subscription\Menu::errorMessage($S->PrintErrors());
        $view = 'editsubscrip';
    }
    break;

case 'deletesubscription':
    $S = new Subscription\Subscription($_POST['id']);
    $S->Delete();
    echo COM_refresh(SUBSCR_ADMIN_URL . '/index.php?subscriptions');
    break;

case 'cancelbutton_x':
//case 'delMultiSub':
    if (isset($_POST['delitem']) && is_array($_POST['delitem'])) {
        foreach ($_POST['delitem'] as $item) {
            Subscription\Subscription::CancelByID($item);
        }
    }
    echo COM_refresh(SUBSCR_ADMIN_URL.'/index.php?subscriptions=0');
    break;

case 'renewbutton_x':
    if (isset($_POST['delitem']) && is_array($_POST['delitem']) &&
            !empty($_POST['delitem'])) {
        $S = new Subscription\Subscription();
        foreach ($_POST['delitem'] as $item) {
            $S->Read($item);
            $S->Add($S->uid, $S->item_id);
        }
    }
    echo COM_refresh(SUBSCR_ADMIN_URL .
            '/index.php?subscriptions=' . $S->item_id);
    break;

default:
    $view = $action;
    break;
}

// Display the correct page content
switch ($view) {
case 'editproduct':
    $P = Subscription\Plan::getInstance($item_id);
    if (isset($_POST['short_description'])) {
        // Pick a field.  If it exists, then this is probably a rejected save
        $P->SetVars($_POST);
    }
    $content .= Subscription\Menu::Admin($view);
    $content .= $P->Edit();
    break;

case 'subscriptions':
    $content .= Subscription\Subscription::adminList($actionval);
    break;

case 'editsubscrip':
    $sub_id = isset($_GET['sub_id']) ? $_GET['sub_id'] : '';
    $S = new Subscription\Subscription($sub_id);
    $content .= Subscription\Menu::Admin($view);
    if ($actionval == 0 && isset($_POST['uid'])) {
        // Pick a field.  If it exists, then this is probably a rejected save
        $S->SetVars($_POST);
    }
    $content .= $S->Edit();
    break;

case 'test':
    $ipn_data =  array(
        'sql_date' => '2021-03-12',                 // SQL-formatted date string, site timezone
        'uid' => 5,                                 // user ID to receive credit
        'pmt_gross' => 99.99,                       // gross amount paid
        'txn_id' => '',                             // transaction ID
        'gw_name' => '',                            // payment gateway short name, e.g. "paypal"
        'memo' => '',                               // misc. comment
        'first_name' => 'Test',                     // payer's first name
        'last_name' => 'User01',                    // payer's last name
        'payer_name' => 'Test User01',              // payer's full name
        'payer_email' => 'testuser01@example.com',  // payer's email address
        'custom' => array(                          // backward compatibility for plugins
        'uid' => 5,
    ),
    'data' => array(),    // actual complete notification payload
    );
    $args = array(
        'item'  => array(
            'item_id' => 'subscription:test',   // actual item ID (plugin:item_id)
            'quantity' => 1,                    // quantity sold
            'name' => 'test',                   // short description or SKU,
            'price' => 99.99,                   // unit price
            'paid' => 99.99,                    // amount paid
            'order_id' => 1,                    // Shop order number,
        ),
        'ipn_data' => $ipn_data,
        'referrer' => array(
            'ref_uid' => 3,             // the referring user's glFusion ID
            'ref_token' => 'tokenhere', // the referral token (affiliate ID),
        )
    );
    service_handlePurchase_subscription( $args, $output, $svc_msg );
    $content .= Subscription\Subscription::adminList( 0 );
    break;
    
case 'products':
default:
    $content .= Subscription\Plan::adminList();
    break;
}
$display = COM_siteHeader();
$display .= $content;
$display .= COM_siteFooter();
echo $display;

?>
