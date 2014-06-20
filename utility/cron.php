<?php
/**
*   Allows scheduled tasks to be run outside of glFusion.
*
*   Run this program from a cron job, like so:
*       php -q cron.php
*
*   You'll need to adjust the path to lib-common.php shown below so this
*   script can find it.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010 Lee Garner
*   @package    subscription
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/**
*   Import core glFusion libraries.  This path to lib-common.php 
*   will need to be changed, depending upon where you put this file.
*/
require_once('../lib-common.php');

plugin_runScheduledTask_subscription(true);

?>
