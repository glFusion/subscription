<?php
/**
 * Common functions for the Subscription plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2021 Lee Garner
 * @package     subscription
 * @version     v1.0.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined('GVERSION')) {
    die('This file can not be used on its own.');
}

/** @global array $_CONF_SUBSCR */
global $_CONF_SUBSCR;

/** Import configuration-handling class */
require_once $_CONF['path_system'] . 'classes/config.class.php';
$sub_config = config::get_instance();
$_CONF_SUBSCR = $sub_config->get_config('subscription');

require_once __DIR__ . '/subscription.php';

define('SUBSCR_PI_PATH', __DIR__);
define('SUBSCR_ADMIN_URL',
    $_CONF['site_url'] . '/admin/plugins/' . $_CONF_SUBSCR['pi_name']);
define('SUBSCR_URL',
    $_CONF['site_url'] . '/' . $_CONF_SUBSCR['pi_name']);

/** Additional config items and table definitions are added here */
if (file_exists(SUBSCR_PI_PATH . '/config.php')) {
    /** Include local configuration overrides */
    include_once SUBSCR_PI_PATH . '/config.php';
}

/**
 * Language file Include
 */
$langpath = SUBSCR_PI_PATH . '/language';
$langfile = "{$_CONF['language']}.php";
if (!file_exists("$langpath/$langfile")) {
    $langfile = 'english_utf-8.php';
}
require_once "$langpath/$langfile";
if (file_exists("$langpath/custom/$langfile")) {
    include_once "$langpath/custom/$langfile";
}

/** Import services to interact with the Shop plugin */
require_once SUBSCR_PI_PATH . '/services.inc.php';

spl_autoload_register(function ($class)
{
    if (strpos($class, 'Subscription\\') === 0) {
        $class = str_replace('Subscription\\', '', $class);
        $path = __DIR__ . '/classes/' . $class . '.class.php';
        if (file_exists($path)) {
            include $path;
        }
    }
});


/**
 * Return true to work with PLG_invokeService().
 *
 * @return  boolean     True, if webservices are supported
 */
function plugin_wsEnabled_subscription()
{
    return true;
}


/**
 * Returns the items for this plugin that should appear on the main menu.
 *
 * @return  array   Associative array of menu items to be added to menu
 */
function plugin_getmenuitems_subscription()
{
    global $_CONF, $_CONF_SUBSCR, $LANG_SUBSCR;

    $menuitems = array();
    if (
        $_CONF_SUBSCR['onmenu'] == 1 &&
        SEC_hasRights('subscription.view')
    ) {
      $menuitems[$LANG_SUBSCR['block_title']] = SUBSCR_URL . '/index.php';
    }
    return $menuitems;
}


/**
 * Get additional Javascript into the site header.
 *
 * @return  array   List of paths to Javascript files
 */
function plugin_getheaderjs_subscription()
{
    global $_CONF, $_CONF_SUBSCR;

    $pi_dir = SUBSCR_PI_PATH . '/js/';
    $files = array(
        $pi_dir . 'toggleEnabled.js',
    );
    return $files;
}


/**
 * Shows the statistics for the plugin on stats.php.
 * If $showsitestats is 1 then we are to only print the overall stats in
 * the 'site statistics' box otherwise we show the detailed stats for the
 * plugin.
 *
 * @param   integer  $showsitestats   Flag to let us know which stats to get
 * @return  string  formatted HTML to insert in stats page
 */
function plugin_showstats_subscription($showsitestats)
{
    global $_CONF, $_USER, $_TABLES, $LANG_SUBSCR, $_CONF_SUBSCR;

    $retval = '';

    $sql = "SELECT p.item_id, COUNT(s.id) as sub_count
            FROM {$_TABLES['subscr_products']} p
            LEFT JOIN {$_TABLES['subscr_subscriptions']} s
                ON p.item_id=s.item_id
            WHERE p.enabled = 1
            AND ( s.status = '" . Subscription\Subscription::STATUS_ENABLED . "' )
            AND ( s.expiration > '{$_CONF['_now']->toMySQL(true)}' )
            GROUP BY p.item_id";
    //echo $sql;die;
    $result = DB_query($sql);
    $nrows  = DB_numRows ($result);
    if ($nrows > 0) {
        USES_lib_admin();
        $header_arr = array(
            array('text' => $LANG_SUBSCR['product'],
                    'field' => 'item_id',
                    'header_class' => 'stats-header-title'),
            array('text' => $LANG_SUBSCR['subscriptions'],
                    'field' => 'sub_count',
                    'header_class' => 'stats-header-count',
                    'field_class' => 'stats-list-count'),
        );
        $data_arr = array();
        $text_arr = array('has_menu'     => false,
                          'title'        => $LANG_SUBSCR['subscriptions'],
        );
        while ($A = DB_fetchArray($result, false)) {
            $A['item_id'] = "<a href=\"" . COM_buildUrl(SUBSCR_URL .
                    "/index.php?mode=detail&item_id={$A['item_id']}"). '">' .
                    htmlspecialchars($A['item_id']) . '</a>';
            $A['received'] = COM_NumberFormat($A['sub_count']);
            $data_arr[] = $A;
        }
        $retval .= ADMIN_simpleList("", $header_arr, $text_arr, $data_arr);
    }

    return $retval;
}


/**
 * Get the values for the summary section of stats.php.
 *
 * @return  array   array(title, total subscriptions)
 */
function plugin_statssummary_subscription()
{
    global $LANG_SUBSCR, $_TABLES;

    $count = DB_count($_TABLES['subscr_subscriptions'],
            'status', Subscription\Subscription::STATUS_ENABLED);
    $retval = array($LANG_SUBSCR['subscriptions'], $count);
    return $retval;
}


/**
 * This will put an option for the plugin in the command and control area.
 *
 * @return  mixed   Array containing (name, admin_url, icon_url), or ''
 */
function plugin_cclabel_subscription()
{
    global $_CONF, $_CONF_SUBSCR;

    if (SUBSCR_isAdmin()) {
        return array(
            $_CONF_SUBSCR['pi_display_name'],
            SUBSCR_ADMIN_URL . '/index.php',
            SUBSCR_URL . '/images/subscription.png'
        );
    } else {
        return '';
    }
}


/**
 * Returns the administrative menu option for this plugin.
 *
 * @return  mixed   Array (pi_name, pi_admin_url, # of items) or ''
 */
function plugin_getadminoption_subscription()
{
    global $_CONF, $_TABLES, $LANG_SUBSCR, $_CONF_SUBSCR;

    if (SUBSCR_isAdmin()) {
        return array(
            $_CONF_SUBSCR['pi_display_name'],
            SUBSCR_ADMIN_URL . '/index.php',
        );
    } else {
        return '';
    }
}


/**
 * Return the current version of code.
 *
 * @return  string      Current plugin version
 */
function plugin_chkVersion_subscription()
{
    global $_CONF_SUBSCR;

    return $_CONF_SUBSCR['pi_version'];
}


/**
 * Automatically uninstall the plugin.
 *
 * @return  array   Array of plugin elements to remove
 */
function plugin_autouninstall_subscription()
{
    global $_CONF_SUBSCR;

    $out = array (
        'tables' => array(
            'subscr_products',
            'subscr_subscriptions',
        ),
        'groups' => array(
            $_CONF_SUBSCR['pi_name'] . ' Admin',
        ),
        'features' => array(
            $_CONF_SUBSCR['pi_name'] . '.admin',
            $_CONF_SUBSCR['pi_name'] . '.view',
        ),
        'php_blocks' => array(
            'phpblock_subscription_subscribe'),
        'vars' => array(
            $_CONF_SUBSCR['pi_name'] . '_gid',
        ),
    );
    \Subscription\Cache::clear();
    PLG_itemDeleted('*', $_CONF_SUBSCR['pi_name']);
    return $out;
}


/**
 * Returns the URL of the plugin's icon.
 *
 * @return  string      URL of the icon
 */
function plugin_geticon_subscription()
{
    return SUBSCR_URL . '/images/subscription.png';
}


/**
 * Implements the [subscription:] autotag.
 *
 * @param   string  $op         Requested operation
 * @param   string  $content    Page content
 * @param   string  $autotag    Autotag content
 * @return  string              $content with the autotag substitutions
 */
function plugin_autotags_subscription($op, $content = '', $autotag = '')
{
    global $_CONF, $_TABLES, $_CONF_SUBSCR;

    switch ($op) {
    case 'tagname':
        $retval = 'subscription';
        break;
    case 'parse':
        $HTML = '';
        $option = COM_applyFilter($autotag['parm1']);
        switch ($option) {
        case 'buynow':
        case 'buy_now':
        case 'cart':
        case 'add_cart':
            // tag: [subscription:buynow product_id]
            $P = new \Subscription\Plan($autotag['parm2']);
            if ($P->canBuy()) {
                $HTML = $P->MakeButton($option);
            }
            break;
        }
        $retval = str_replace($autotag['tagstr'], $HTML, $content);
        break;

    default:
        $retval = $content;
        break;

    }   // switch ($op)

    return $retval;
}


/**
 * Enable or disable this plugin's blocks when the plugin is changed.
 *
 * @param   boolean $enabled    Plugin's "enabled" state
 */
function plugin_enablestatechange_subscription($enabled)
{
    global $_TABLES, $_PLUGINS;

    $enabled = $enabled == true ? 1 : 0;

    // If trying to enable the plugin but lgLib is not available,
    // take no action and return
    if ($enabled && !in_array('lglib', $_PLUGINS)) {
        // Re-disable the plugin
        DB_change($_TABLES['plugins'], 'pi_enabled', 0, 'pi_name', 'subscription');
        $enabled = 0;
    }


    if ($enabled == 0) {    // Never automatically enable blocks
        $blockfns = array('phpblock_subscription_');
        foreach ($blockfns as $blockfn) {
            DB_query("UPDATE {$_TABLES['blocks']}
                SET is_enabled=$enabled
                WHERE phpblockfn LIKE '{$blockfn}%'");
        }
    }
}


/**
 * Receives notification of anothe plugin's deletion.
 *
 * @param   mixed   $id     Item ID
 * @param   string  $type   Plugin Name
 */
function plugin_itemdeleted_subscription($id, $type)
{
    global $_TABLES;

    // If the lgLib plugin is disabled or removed, disable thie plugin
    if ($type == 'lglib' && $id == '*') {
        plugin_enablestatechange_subscription(false);
        DB_change($_TABLES['plugins'], 'pi_enabled', 0, 'pi_name', 'subscription');
    }
}


/**
 * Get the URL to the configuration help file.
 *
 * @uses    SUBSCR_getDocURL()
 * @param   string  $option     Help option to display
 * @param   string  $doclang    Document language
 * @return  string              URL to document
 */
function plugin_getconfigelementhelp_subscription($option, $doclang='english')
{
    if (version_compare(GVERSION, '1.6.0', '<')) {
        $file = 'config.legacy.html';
    } else {
        $file = 'config.html';
    }
    $doc_url = SUBSCR_getDocURL($file, $doclang);
    if (empty($doc_url))
        return false;

    if (!empty($option)) {
        $doc_url .= '#desc_' . $option;
    }
    return array($doc_url, 0);
}


/**
 * Get the link to a documentation file.
 * Returns the URL to the file, or to the directory if no file requested.
 *
 * @param   string  $file       HTML file
 * @param   string  $doclang    Documentation language
 * @return  string              URL to document directory or file
 */
function SUBSCR_getDocURL($file='', $doclang='english')
{
    $doc_path = SUBSCR_PI_PATH . "/docs/$doclang/";
    $doc_url = '';

    if ($file != '') {
        if (!is_file($doc_path . $file)) {
            $doclang = 'english';
        }
        $doc_url = SUBSCR_URL . "/docs/$doclang/$file";
    } else {
        if (!is_dir($doc_path)) {
            $doclang = 'english';
        }
        $doc_url = SUBSCR_URL . "/docs/$doclang/";
    }
    return $doc_url;
}


/**
 * Upgrade the plugin to the currently-installed version.
 *
 * @return  mixed       true if successful, false or error number otherwise
 */
function plugin_upgrade_subscription()
{
    require_once __DIR__ . '/upgrade.inc.php';
    \Subscription\Cache::clear();
    return SUBSCR_do_upgrade();
}


/**
 * Create the quick-subscribe block for products with show_in_block set.
 *
 * @return  string      HTML for the block
 */
function phpblock_subscription_subscribe()
{
    global $_TABLES, $_CONF_SUBSCR, $_USER, $_CONF;

    $retval = '';

    // Shop is required to create the purchase link
    if (
        !SUBSCR_shop_enabled() ||
        COM_isAnonUser()
    ) {
        return $retval;
    }

    $sql = "SELECT p.item_id
            FROM {$_TABLES['subscr_products']} p
            LEFT JOIN {$_TABLES['subscr_subscriptions']} s
            ON s.uid = '{$_USER['uid']}' AND p.item_id = s.item_id
            WHERE p.enabled = 1
            AND (s.item_id IS NULL OR
                 s.expiration <= '{$_CONF['_now']}' + INTERVAL p.early_renewal DAY)
            AND p.show_in_block = 1";
    //echo $sql;die;
    $r = DB_query($sql);

    // DB_numRows is checked just to save having to load the subscription
    // class if nothing needs to be shown.
    $n = DB_numRows($r);
    if ($n < 1) {
        return '';
    }

    $T = new Template(__DIR__ . '/templates');
    $i = 0;
    while ($row = DB_fetchArray($r, false)) {
        $P = Subscription\Plan::getInstance($row['item_id']);
        if ($P->getID() == '') continue;     // no access to this product

        $status = LGLIB_invokeService(
            'shop', 'getCurrency',
            array(),
            $currency,
            $svc_msg
        );
        if (empty($currency)) {
            $currency = 'USD';
        }

        $T->set_file('block', 'blk_subscribe.thtml');

        $T->set_var(array(
            'title'     => $P->getID(),
            'dscp'      => $P->getDscp(),
            'short_dscp' => $P->getName(),
            'item_id'   => $P->getID(),
            'pi_url'    => SUBSCR_URL,
            'price'     => COM_numberFormat($P->getBasePrice(), 2),
            'currency'  => $currency,
            'purchase_btn' => $P->MakeButton(),
            'counter'   => $i,
        ) );

        /*if ($i > 0)
            $T->set_var('hr', '<hr />');*/
        $i++;

        $T->parse('output','block');
        $retval .= $T->finish($T->get_var('output'));
    }
    return $retval;
}


/**
 * Displays subscriptions in the user's profile.
 *
 * @param   integer  $uid   User ID whose profile is being displayed
 * @return  string          HTML to include in profile page
 */
function plugin_profileblocksdisplay_subscription($uid)
{
    global $_CONF_SUBSCR, $LANG_SUBSCR, $_TABLES, $_CONF, $_USER;

    $retval = '';

    // non-admins can only see their own subscription.
    if (!SUBSCR_isAdmin() && $uid != $_USER['uid']) {
        return $retval;
    }

    $sql = "SELECT p.item_id, s.expiration
            FROM {$_TABLES['subscr_subscriptions']} s
            LEFT JOIN {$_TABLES['subscr_products']} p
            ON s.item_id = p.item_id
            WHERE s.uid = $uid";
    $res = DB_query($sql);
    if ($res && DB_numRows($res) > 0) {
        $LT = new Template(SUBSCR_PI_PATH . '/templates/');
        $LT->set_file(array(
            'block' => 'profileblock.thtml',
        ));
        $LT->set_block('block', 'subscription_row', 'srow');

        while ($A = DB_fetchArray($res, false)) {
            $LT->set_var(array(
                'item_id' => $A['item_id'],
                'expires' => $A['expiration'],
            ) );
            $LT->parse('srow', 'subscription_row', true);
        }

        $LT->parse('output', 'block');
        $retval = $LT->finish($LT->get_var('output'));
    }
    return $retval;
}


/**
 * Get additional CSS into the site header.
 * Allows for custom CSS to be placed by the site admin.
 *
 * @return  array   List of paths to CSS files
 */
function plugin_getheadercss_subscription()
{
    global $_CONF, $_CONF_SUBSCR;

    $styles = array();
    $files = array('style.css');

/*    if (!defined('HAVE_CALENDAR_CSS')) {
        // Include the popup calendar CSS if not included by another plugin
        $files[] = 'calendar-blue.css';
        define('HAVE_CALENDAR_CSS', 1);
    }
*/
    $defpath = SUBSCR_PI_PATH . '/css';
    $layoutpath = $_CONF['path_layout'] . 'plugins/' . $_CONF_SUBSCR['pi_name'];
    foreach ($files as $file) {
        if (file_exists($layoutpath . '/' . $file)) {
            $styles[] = $layoutpath . '/' . $file;
        } else {
            $styles[] = $defpath . '/' . $file;
        }
    }

    // Now get any customized versions
    foreach ($files as $file) {
        if (is_file($defpath . '/custom/' . $file)) {
            $styles[] = $defpath . '/custom/' . $file;
        }
    }

    return $styles;
}


/**
 * Perform scheduled functions, such as notifications and cancellations.
 *
 * @uses    Subscription::Cancel()
 * @uses    SUBSCR_notifyExpiration()
 */
function plugin_runScheduledTask_subscription()
{
    global $_TABLES, $_CONF_SUBSCR, $_CONF;

    // Cancel subscriptions not renewed within the grace period
    $sql = "SELECT s.uid, s.item_id
            FROM {$_TABLES['subscr_subscriptions']} s
            LEFT JOIN {$_TABLES['subscr_products']} p
                ON p.item_id = s.item_id
            WHERE '{$_CONF['_now']->toMySQL(true)}' > (s.expiration + interval p.grace_days DAY)
                AND status = " . Subscription\Subscription::STATUS_ENABLED;
    //COM_errorLog($sql);
    $r = DB_query($sql);
    if ($r) {
        while ($row = DB_fetchArray($r, false)) {
            \Subscription\Subscription::Cancel($row['uid'], $row['item_id'], true);
        }
    }

    // For the subscriptions that are left, notify the subscribers
    // if their expiration is impending
    SUBSCR_notifyExpiration();
}


/**
 * Log activity to a plugin-specific log file.
 * Message includes the user who initiated the action, unless it's a
 * system-generated event such as a scheduled task.
 *
 * @param   string  $logentry   Text to log
 * @param   boolean $system     True if this is a system-generated entry
 */
function SUBSCR_auditLog($logentry = '', $system=false)
{
    global $_CONF, $_USER, $LANG01, $LANG_SUBSCR;

    if ($logentry == '')
        return '';

    // A little sanitizing
    $logentry = str_replace(
            array('<?', '?>'),
            array('(@', '@)'),
            $logentry);

    $timestamp = strftime('%c');
    $logfile = $_CONF['path_log'] . 'subscription.log';

    // Can't open the log file?  Return an error
    if (!$file = fopen($logfile, 'a+')) {
        return $LANG01[33] . $logfile . ' (' . $timestamp . ')<br />' . LB;
    }

    if ($system == false) {
        // Get the user name if it's not anonymous
        if (isset($_USER['uid'])) {
            $byuser = $_USER['uid'] . '-'.
                    COM_getDisplayName($_USER['uid'],
                        $_USER['username'], $_USER['fullname']);
        } else {
            $byuser = 'anon';
        }
        $byuser .= '@' . $_SERVER['REMOTE_ADDR'];
    } else {
        $byuser = $LANG_SUBSCR['system_task'];
    }

    // Write the log entry to the file
    fputs($file, "$timestamp ($byuser) - $logentry\n");

}


/**
 * Send debug info to the error log.
 *
 * @param   string  $msg    Text message to display, may be blank.
 */
function SUBSCR_debug($msg)
{
    global $_CONF_SUBSCR;
    if (isset($_CONF_SUBSCR['debug']) && $_CONF_SUBSCR['debug']) {
        COM_errorLog('SUBSCR DEBUG: ' . $msg);
    }
}


/**
 * Notify users that have subscriptions soon to expire.
 * This is in functions.inc so it can be called from runscheduledTask.
 */
function SUBSCR_notifyExpiration()
{
    global $_TABLES, $_CONF, $_CONF_SUBSCR, $LANG_SUBSCR;

    // Return if we're not configured to notify users.
    if ($_CONF_SUBSCR['notifydays'] <= 0) {
        return;
    }

    $sql = "SELECT s.id, s.uid, s.notified,
                p.item_id, p.short_description
            FROM {$_TABLES['subscr_subscriptions']} s
            LEFT JOIN {$_TABLES['subscr_products']} p
                ON p.item_id = s.item_id
            LEFT JOIN {$_TABLES['users']} u
                ON u.uid = s.uid
            WHERE notified = 0
            AND s.expiration < '{$_CONF['_now']->toMySQL(true)}' + INTERVAL {$_CONF_SUBSCR['notifydays']} DAY";
    //COM_errorLog($sql);
    $r = DB_query($sql);
    if (!$r)
        return;

    $template_base = SUBSCR_PI_PATH . '/templates/notify';

    while ($row = DB_fetchArray($r, false)) {
        $username = COM_getDisplayName($row['uid']);
        $email = DB_getItem($_TABLES['users'], 'email', "uid={$row['uid']}");
        //$language = DB_getItem($_TABLES['users'], 'language', "uid={$row['uid']}");

        // Include the owner's language, if possible.
        // Fallback to site language.
        //$LANG = plugin_loadlanguage_classifieds(array($language, $_CONF['language']));

        /*if (file_exists("$template_base/$language/expiration.thtml")) {
            $template_dir = "$template_base/$language";
        } else {
            $template_dir = "$template_base/english";
        }*/

        $T = new Template($template_base);
        $T->set_file('message', 'expiration.thtml');
        $T->set_var(array(
            'site_name'     => $_CONF['site_name'],
            'username'      => $username,
            'pi_name'       => $_CONF_SUBSCR['pi_name'],
            'product'       => $row['item_id'],
            'item_id'       => $row['item_id'],
            'short_description' => $row['short_description'],
            'detail_url'    => COM_buildURL(SUBSCR_URL .
                    '/index.php?view=detail&item_id=' .
                    urlencode($row['item_id'])),
        ) );

        $T->parse('output','message');
        $message = $T->finish($T->get_var('output'));

        COM_mail(
            array($email, $username),
            $LANG_SUBSCR['exp_notice'],
            $message,
            "{$_CONF['site_name']} <{$_CONF['site_mail']}>",
            true
        );

        // Mark that the expiration notification has been sent.
        DB_query("UPDATE {$_TABLES['subscr_subscriptions']}
                SET notified=1
                WHERE id='{$row['id']}'");
    }

}


/**
 * Delete subscriptions for users that are deleted.
 *
 * @param   integer $uid    User id of deleted user
 */
function plugin_user_delete_subscription($uid)
{
    global $_TABLES;

    DB_delete($_TABLES['subscr_subscriptions'], 'uid', (int)$uid);
}


/**
 * Handle a new user registration.
 * Called by PLG_createUser (glFusion 1.1.5) to handle a new user signup.
 * Creates an initial trial subscription for any products so configured.
 * If the product doesn't have a value set for trial_days, then a full
 * subscription is created.
 *
 * @param   integer $uid    UID of new user.  Already set by glFusion
 */
function plugin_user_create_subscription($uid = 0)
{
    global $_TABLES, $_CONF_SUBSCR;

    // $uid must already be set to the new user's ID
    if ($uid == 0)
        return;

    $S = new \Subscription\Subscription();
    $Plans = \Subscription\Plan::getPlans();
    foreach ($Plans as $P) {
        if ($P->getAtRegistration()) {
            // If auto-signup is enabled for this product, use the trial_days
            // if defined, or create a full subscription
            if ($P->getTrialDays() > 0) {
                $S->withUid($uid)
                    ->withItemId($P->getID())
                    ->withDuration($P->getTrialDays())
                    ->withDurationType('day')
                    ->Add();
            } else {
                $S->withUid($uid)
                  ->withItemId($P->getID())
                  ->Add();
            }
        }
    }
}


/**
 * Support merging local accounts into remote accounts.
 *
 * @param   integer $origUID    Original (remote) user ID
 * @param   integer $destUID    Merged (local) user ID
 */
function plugin_user_move_subscription($origUID, $destUID)
{
    global $_TABLES;

    $origUID = (int)$origUID;
    $destUID = (int)$destUID;

    $sqls = array(
        "UPDATE {$_TABLES['subscr_subscriptions']}
            SET uid = $destUID WHERE uid = $origUID",
        "UPDATE {$_TABLES['subscr_history']}
            SET uid = $destUID WHERE uid = $origUID",
    );
    foreach($sqls as $sql) {
        DB_query($sql, 1);
        if (DB_error()) {
            COM_errorLog("plugin_user_move_subscription SQL error: $sql",1);
        }
    }
}


/**
 * Return categories for the Shop plugin.
 *
 * @return  array   Array of category information
 */
function plugin_shop_getcategories_subscription()
{
    global $_CONF_SUBSCR;

    if ($_CONF_SUBSCR['show_in_pp_cat']) {
        return array(
            array(
                'name' => 'Subscriptions',
                'count' => 0,
                'url' => SUBSCR_URL,
            ),
        );
    } else {
        return array();
    }
}


/**
 * Provides the getItemInfo function for subscription products.
 *
 * @param   string  $id         Plan ID
 * @param   string  $what       Comma-separated list of fields to return
 * @param   integer $uid        User ID
 * @param   array   $options    Not used
 * @return  array           Array of product information
 */
function plugin_getiteminfo_subscription($id, $what, $uid=0, $options=array())
{
    global $_TABLES, $_CONF, $LANG_SUBSCR;

    $properties = explode(',', $what);
    $fields = array();
    $retval = array();
    $buildingSearchIndex = false;
    foreach ($properties as $p) {
        switch ($p) {
        case 'search_index':
            $buildingSearchIndex = true;
            break;
        case 'id':
            $fields[] = 'item_id';
            break;
        case 'title':
            $fields[] = 'short_description';
            break;
        case 'content':
        case 'description':
            $fields[] = 'description';
            break;
        case 'searchidx':
            $fields[] = 'short_description';
            $fields[] = 'description';
            break;
        case 'price':
            $fields[] = 'price';
            break;
        case 'date':
            $fields[] = 'dt_add';
            break;
        }
    }
    if (count($fields) == 0) {
        return $retval;
    }
    $fields = array_unique($fields);
    $fields = implode(',', $fields);
    $retval = array();
    $sql = "SELECT $fields FROM {$_TABLES['subscr_products']} ";
    if ($id != '*') {
        $sql .= " WHERE (item_id = '" . DB_escapeString($id) . "')";
    }
    //echo $sql;die;
    $res = DB_query($sql);
    while ($A = DB_fetchArray($res, false)) {
        foreach ($properties as $p) {
            switch ($p) {
            case 'id':
                $props[$p] = $A['item_id'];
                break;
            case 'excerpt':
            case 'description':
            case 'content':
                $props[$p] = $A['description'];
                break;
            case 'title':
                $props[$p] = $A['short_description'];
                break;
            case 'author':
                $props[$p] = 2;     // no author, just return Admin
                break;
            case 'date':
                $props[$p] = $A['dt_add'];
                break;
            case 'searchidx':
                $props[$p] = $A['short_description'] . ' ' . $A['description'];
                break;
            case 'url':
                $props[$p] = COM_buildUrl(SUBSCR_URL);  // no detail page yet
                break;
            default:
                $props[$p] = isset($A[$p]) ? $A[$p] : NULL;
                break;
            }
        }
        $mapped = array();
        foreach ($props as $key => $value) {
            if ($id == '*') {
                if ($value != '') {
                    $mapped[$key] = $value;
                }
            } else {
                $mapped[$key] = $value;
            }
        }

        if ($id == '*') {
            $retval[] = $mapped;
        } else {
            $retval = $mapped;
            break;
        }
    }

    if (($id != '*') && (count($retval) == 1)) {
        $tRet = array_values($retval);
        $retval = $tRet[0];
    }
    if ($retval === '' || (is_array($retval) && count($retval) == 0)) {
        return NULL;
    } else {
        return $retval;
    }
}


/**
 * Get the search types for the site search.
 *
 * @return  mixed   Array of typeID=>typeName, or empty string for none.
 */
function plugin_searchtypes_subscription()
{
    global $_CONF, $_CONF_SUBSCR;

    if (COM_isAnonUser() && $_CONF['loginrequired'] == 1) {
        return '';
    }
    return array($_CONF_SUBSCR['pi_name'] => $_CONF_SUBSCR['pi_display_name']);
}


/**
 * Determine if the current user is allowed to rate an item.
 * Curretly, all logged-in users can rate (even admin).
 * Requires glFusion 1.1.7 or higher.
 *
 * @param   mixed   $item_id    ID of product to be rate (not used)d
 * @param   integer $uid        User ID of the one doing the rating
 * @return  boolean             True if user is allowed, false if not
 */
function plugin_canuserrate_subscription($item_id, $uid)
{
    return !COM_isAnonUser();
}


/**
 * Updates the product table with the new rating and vote count.
 *
 * @param   mixed   $id     Plan ID
 * @param   float   $rating Rating Value
 * @param   integer $votes  Number of votes cast
 */
function Xplugin_itemrated_subscription($id, $rating, $votes)
{
//    return Subscription\Plan::updateRating($id, $rating, $votes);
}


/**
 * Check if the current user is allowed to administer the plugin.
 *
 * @return  boolean     True is user is an admin, False if not
 */
function SUBSCR_isAdmin()
{
    global $_CONF_SUBSCR;

    static $isAdmin = NULL;       // static since this can be called many times
    if ($isAdmin === NULL) {
        $isAdmin = SEC_hasRights($_CONF_SUBSCR['pi_name'] . '.admin') ? true : false;
    }
    return $isAdmin;
}


/**
 * Determine if the Shop plugin is installed and integration is enabled.
 *
 * @return  boolean     True if Shop is available, False if not.
 */
function SUBSCR_shop_enabled()
{
    static $enabled = NULL;
    if ($enabled === NULL) {
        $enabled = function_exists('plugin_chkVersion_shop') ||
            function_exists('plugin_chkVersion_shop');
    }
    return $enabled;
}


/**
 * Get an array variable, first checking whether it's set.
 *
 * @param  array   $A          Array where the variable should be
 * @param  string  $key        Name of array element
 * @param  string  $type       Type of return value
 * @param  mixed   $default    Default value, if key is not set
 * @return     Array element cast to $type, or NULL if not set
 */
function SUBSCR_getVar($A, $key, $type = 'string', $default = NULL)
{
    // If the key is not set, return the default
    if (!is_array($A) || !isset($A[$key])) {
        return $default;
    } else {
        $val = $A[$key];
    }

    // Cast the value according to the specified type
    switch ($type) {
    case 'int':
    case 'integer':
        $val = (int)$val;
        break;
    case 'bool':
        $val = (bool)$val;
        break;
    case 'float':
        $val = (float)$val;
        break;
    case 'array':
        // Cast value as an array only if it isn't already one
        if (!is_array($val)) {
            $val = array($val);
        }
        break;
    case 'string':
    default:
        $val = (string)$val;
        break;
    }
    return $val;
}
