<?php
/**
 *  Common AJAX functions
 *
 *  @author     Lee Garner <lee@leegarner.com>
 *  @copyright  Copyright (c) 2010 Lee Garner <lee@leegarner.com>
 *  @package    subscription
 *  @version    0.0.1
 *  @license    http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 *  @filesource
 */

/**
 *  Include required glFusion common functions
 */
require_once '../lib-common.php';

$isAdmin = SEC_hasRights('subscription.admin') ? 1 : 0;
$base_url = SUBSCR_URL;

switch ($_GET['action']) {
case 'getExpDate':
    $retval = date('Y-m-d');

    // Get the expiration date for a subscription.
    // today + term
    $prod_id = COM_sanitizeID($_REQUEST['prod_id']);
    if (!empty($prod_id)) {
        $sql = "SELECT duration, duration_type
            FROM {$_TABLES['subscr_products']}
            WHERE item_id='$prod_id'";
        $res = DB_query($sql);
        $A = DB_fetchArray($res, false);

        if (!empty($A)) {

            $num = (int)$A['duration'];
            $period = DB_escapeString($A['duration_type']);
            $sql = "SELECT DATE_ADD(CURDATE(), INTERVAL $num $period) AS expdate";
            $res = DB_query($sql);
            $A = DB_fetchArray($sql, false);
            if (!empty($A)) {
                $retval = $A['expdate'];
            }
        }
    }
    header('Content-Type: text/xml');
    header("Cache-Control: no-cache, must-revalidate");
    //A date in the past
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

    echo '<?xml version="1.0" encoding="ISO-8859-1"?>
    <prodinfo>'. "\n";
    echo "<expdate>{$retval}</expdate>\n";
    echo "</prodinfo>\n";
    break;

}

?>
