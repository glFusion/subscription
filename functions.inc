<?php
/**
 * Common functions for the Subscription plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2022 Lee Garner
 * @package     subscription
 * @version     v1.1.0
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
use glFusion\Database\Database;
use glFusion\Log\Log;

Log::config('subscription',
    array(
      'type'  => 'file',
      'path'  => $_CONF['path_log'],
      'file'  => 'subscription.log',
      'level' => $_CONF['log_level'],
    )
);

// Leverage the system log for debugging based on the plugin debug setting.
Log::config('subscr_debug',
    array(
      'type'  => 'file',
      'path'  => $_CONF['path_log'],
      'file'  => 'system.log',
      'level' => $_CONF_SUBSCR['debug'] ? Log::DEBUG : 999,
    )
);


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

    $db = Database::getInstance();
    $qb = $db->conn->createQueryBuilder();
    try {
        $data = $qb->select('p.item_id', 'COUNT(s.id) AS sub_count')
                   ->from($_TABLES['subscr_products'], 'p')
                   ->leftJoin('p', $_TABLES['subscr_subscriptions'], 's', 's.item_id=p.item_id')
                   ->where('p.enabled = 1')
                   ->andWhere('s.status = :status' . Subscription\Subscription::STATUS_ENABLED)
                   ->andWhere('s.expiration = :expiration')
                   ->groupBy('p.item_id ASC')
                   ->execute()
                   ->fetchAllAssociative();
    } catch (\Exception $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        $data = false;
    }
    if (!empty($data)) {
        USES_lib_admin();
        $header_arr = array(
            array(
                'text' => $LANG_SUBSCR['product'],
                'field' => 'item_id',
                'header_class' => 'stats-header-title',
            ),
            array(
                'text' => $LANG_SUBSCR['subscriptions'],
                'field' => 'sub_count',
                'header_class' => 'stats-header-count',
                'field_class' => 'stats-list-count',
            ),
        );
        $data_arr = array();
        $text_arr = array(
            'has_menu'     => false,
            'title'        => $LANG_SUBSCR['subscriptions'],
        );
        foreach ($data as $A) {
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

    $db = Database::getInstance();
    return array(
        $LANG_SUBSCR['subscriptions'],
        $db->getCount(
            $_TABLES['subscr_subscriptions'],
            'status',
            Subscription\Subscription::STATUS_ENABLED
        )
    );
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
            'subscr_history',
            'subscr_referrals',
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
    $db = Database::getInstance();

    // If trying to enable the plugin but lgLib is not available,
    // take no action and return
    if ($enabled && !in_array('lglib', $_PLUGINS)) {
        // Re-disable the plugin
        try {
            $db->conn->update(
                $_TABLES['plugins'],
                array('pi_enabled' => 0),
                array('pi_name' => 'subscription'),
                array(Database::INTEGER, Database::STRING)
            );
            $enabled = 0;
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        }
    }


    if ($enabled == 0) {    // Never automatically enable blocks
        $blockfns = array('phpblock_subscription_');
        foreach ($blockfns as $blockfn) {
            try {
                $db->conn->update(
                    "UPDATE {$_TABLES['blocks']}
                    SET is_enabled = ?
                    WHERE phpblockfn LIKE '{$blockfn}%'",
                    array($enabled, $blockfn),
                    array(Database::INTEGER, Database::STRING)
                );
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
            }
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
        $db = Database::getInstance();
        try {
            $db->conn->update(
                $_TABLES['plugins'],
                array('pi_enabled' => 0),
                array('pi_name' => 'subscription'),
                array(Database::INTEGER, Databse::STRING)
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        }
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

    $db = Database::getInstance();
    $qb = $db->conn->createQueryBuilder();
    try {
        $stmt = $qb->select('p.item_id')
                   ->from($_TABLES['subscr_products'], 'p')
                   ->leftJoin('p', $_TABLES['subscr_subscriptions'], 's', 'p.item_id=s.item_id')
                   ->where('p.enabled = 1')
                   ->andWhere(('s.item_id IS NULL OR s.expiration <= :now  + INTERVAL p.early_renewal DAY'))
                   ->andWhere('p.show_in_block = 1')
                   ->setParameter('now', $_CONF['_now']->toMySQL(true), Database::STRING)
                   ->execute();
        $nrows = $stmt->rowCount();
                   //->fetchAllAssociative();
    } catch (\Exception $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        $data = false;
    }

    // class if nothing needs to be shown.
    if ($nrows < 1) {
        return '';
    }

    $T = new Template(__DIR__ . '/templates');
    $i = 0;
    while ($row = $stmt->fetchAssociative()) {
        $P = Subscription\Plan::getInstance($row['item_id']);
        if ($P->getID() == '') continue;     // no access to this product

        $currency = PLG_callFunctionForOnePlugin('plugin_getCurrency_shop');
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

    $db = Database::getInstance();
    $qb = $db->conn->createQueryBuilder();
    try {
        $data = $qb->select('p.item_id', 's.expiration')
           ->from($_TABLES['subscr_subscriptions'], 's')
           ->leftJoin('s', $_TABLES['subscr_products'], 'p', 'p.item_id=s.item_id')
           ->where('s.uid = :uid')
           ->setParameter('uid', $uid, Database::INTEGER)
           ->execute()
           ->fetchAllAssociative();
    } catch (\Exception $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        $data = false;
    }
    if (!empty($data)) {
        $LT = new Template(SUBSCR_PI_PATH . '/templates/');
        $LT->set_file(array(
            'block' => 'profileblock.thtml',
        ));
        $LT->set_block('block', 'subscription_row', 'srow');

        foreach ($data as $A) {
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
 * @uses    Subscription::Expire()
 * @uses    SUBSCR_notifyExpiration()
 */
function plugin_runScheduledTask_subscription()
{
    global $_TABLES, $_CONF_SUBSCR, $_CONF;

    $db = Database::getInstance();
    $qb = $db->conn->createQueryBuilder();

    // Cancel subscriptions not renewed within the grace period
    try {
        $data = $qb->select('s.uid', 's.item_id')
           ->from($_TABLES['subscr_subscriptions'], 's')
           ->leftJoin('s', $_TABLES['subscr_products'], 'p', 's.item_id = p.item_id')
           ->where(':cutoff > (s.expiration + interval p.grace_days DAY)')
           ->andWhere('status = :status')
           ->setParameter('cutoff', $_CONF['_now']->toMySQL(true), Database::STRING)
           ->setParameter('status', Subscription\Subscription::STATUS_ENABLED, Database::INTEGER)
           ->execute()
           ->fetchAllAssociative();
    } catch (\Exception $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        $data = false;
    }
    if (is_array($data)) {
        foreach ($data as $row) {
            \Subscription\Subscription::Expire($row['uid'], $row['item_id'], true);
        }
    }

    // For the subscriptions that are left, notify the subscribers
    // if their expiration is impending
    SUBSCR_notifyExpiration();
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

    $db = Database::getInstance();
    $qb = $db->conn->createQueryBuilder();
    try {
        $stmt = $qb->select(
            's.*', 'p.item_id', 'p.short_description', 'p.grace_days',
            'p.description', 'p.price', 'u.email, u.language'
        )
        ->from($_TABLES['subscr_subscriptions'], 's')
        ->leftJoin('s', $_TABLES['subscr_products'], 'p', 'p.item_id=s.item_id')
        ->leftJoin('s', $_TABLES['users'], 'u', 'u.uid=s.uid')
        ->where('notified = 0')
        ->andWhere('s.expiration < :now + INTERVAL :days DAY')
        ->setParameter('now', $_CONF['_now']->toMySQL(true), Database::STRING)
        ->setParameter('days', $_CONF_SUBSCR['notifydays'], Database::INTEGER)
        ->execute();
    } catch (\Exception $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        $stmt = false;
    }
    if (!$stmt) {
        return;
    }

    $template_base = SUBSCR_PI_PATH . '/templates/notify';

    while ($row = $stmt->fetchAssociative()) {
        $username = COM_getDisplayName($row['uid']);
        $email = $row['email'];
        //$language = $row['language'];

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
            'description'   => $row['description'],
            'expiration'    => $row['expiration'],
            'price'         => number_format($row['price'], 2, '.', ''),
            'uid'           => $row['uid'],
            'detail_url'    => COM_buildURL(SUBSCR_URL .
                    '/index.php?view=detail&item_id=' .
                    urlencode($row['item_id'])),
        ) );
        $T->parse('output','message');
        $message = $T->finish($T->get_var('output'));

        COM_mail(
            //array('email' => $email, 'name' => $username),
            $email,
            $LANG_SUBSCR['exp_notice'],
            $message,
            "{$_CONF['site_name']} <{$_CONF['site_mail']}>",
            true
        );

        // Mark that the expiration notification has been sent.
        try {
            $db->conn->update(
                $_TABLES['subscr_subscriptions'],
                array('notified' => 1),
                array('id' => $row['id']),
                array(Database::INTEGER)
                );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        }
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

    $db = Database::getInstance();
    try {
        $db->conn->delete(
            $_TABLES['subscr_subscriptions'],
            array('uid' => $uid),
            array(Database::INTEGER)
        );
    } catch (\Exception $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
    }
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
function plugin_user_move_subscription(int $origUID, int $destUID) : void
{
    global $_TABLES;

    $db = Database::getInstance();
    $tables = array('subscr_subscriptions', 'subscr_history');
    foreach ($tables as $table) {
        try {
            $db->conn->update(
                $_TABLES[$table],
                array('uid' => $destUID),
                array('uid' => $origUID),
                array(Database::INTEGER)
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
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
    $retval = array();
    $buildingSearchIndex = false;

    foreach ($properties as $p) {
        switch ($p) {
        case 'search_index':
            $buildingSearchIndex = true;
            break;
        }
    }

    $retval = array();
    $db = Database::getInstance();
    $sql = "SELECT * FROM {$_TABLES['subscr_products']} ";
    $values = array();
    $types = array();
    if ($id != '*') {
        $sql .= " WHERE (item_id = ?)";
        $values[] = $id;
        $types[] = Database::STRING;
    }

    try {
        $stmt = $db->conn->executeQuery($sql, $values, $types);
    } catch (\Exception $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        return $retval;
    }
    $props = array();
    $item_mod = 'new';
    while ($A = $stmt->fetchAssociative()) {
        $P = new Subscription\Plan($A);
        if ($item_mod == 'upgrade') {
            // check if upgrade is possible.
            if ($P->getUpgradeFrom() == '' || $P->getUpgradePrice == 0) {
                $item_mod = '';
            }
        }
        foreach ($properties as $p) {
            switch ($p) {
            case 'id':
                $props[$p] = $P->getID();
                break;
            case 'excerpt':
            case 'description':
            case 'content':
                $props[$p] = $P->getDscp();
                break;
            case 'title':
            case 'short_description':
            case 'name':
                $props[$p] = $P->getName();
                if ($item_mod == 'upgrade') {
                    $props['name'] .= ', ' . $LANG_SUBSCR['upgrade'];
                }
                break;
            case 'author':
                $props[$p] = 2;     // no author, just return Admin
                break;
            case 'date':
                $props[$p] = $A['dt_add'];
                break;
            case 'searchidx':
                $props[$p] = $P->getName() . ' ' . $P->getDscp();
                break;
            case 'url':
            case 'cancel_url':
                $props[$p] = COM_buildUrl(SUBSCR_URL);  // no detail page yet
                break;
            case 'isUnique':
            case 'have_detail_svc':
            case 'override_price':
            case 'fixed_q':
                $props[$p] = true;
                break;
            case 'supportsRatings':
                $props[$p] = false;
                break;
            case 'price':
                if ($item_mod == 'upgrade') {
                    $props[$p] = $P->getUpgradePrice();
                } else {
                    $props[$p] = $P->getBasePrice();
                }
                break;
            case 'supportsRatings':
                $props[$p] = false;
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
        return false;
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


function plugin_getsubscribers_subscription(string $plan_id) : array
{
    return Subscription\Subscription::getByPlan($plan_id);
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
