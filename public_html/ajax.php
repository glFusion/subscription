<?php
/**
 *  Common AJAX functions.
 *
 *  @author     Lee Garner <lee@leegarner.com>
 *  @copyright  Copyright (c) 2010-2022 Lee Garner <lee@leegarner.com>
 *  @package    subscription
 *  @version    1.2.0
 *  @license    http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 *  @filesource
 */

/**
 *  Include required glFusion common functions
 */
require_once '../lib-common.php';
use glFusion\Database\Database;
use glFusion\Log\Log;

switch ($_GET['action']) {
case 'getExpDate':
    $retval = date('Y-m-d');    // Default return value

    // Get the expiration date for a subscription.
    // today + term
    $prod_id = COM_sanitizeID($_REQUEST['prod_id']);
    if (!empty($prod_id)) {
        $db = Database::getInstance();
        try {
            $A = $db->conn->executeQuery(
                "SELECT duration, duration_type
                FROM {$_TABLES['subscr_products']}
                WHERE item_id = ?";
                array($prod_id),
                array(Database::STRING)
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __FILE__ . '::' . __LINE__ . ': ' $e->getMessage());
            $A = false;
        }
        if (!empty($A)) {
            // Just using SQL to get the expiration date
            try {
                $A = $db->conn->executeQuery(
                    "SELECT DATE_ADD(CURDATE(), INTERVAL $num $period) AS expdate",
                    array($A['duration'], $A['duration_type']),
                    array(Database::INTEGER, Database::STRING)
                )->fetchAssociative();
                $retval = $A['expdate'];
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __FILE__ . '::' . __LINE__ . ': ' $e->getMessage());
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

