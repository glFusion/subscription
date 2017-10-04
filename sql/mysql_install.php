<?php
/**
*   Database creation and update statements for the Subscription plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010 Lee Garner
*   @package    subscription
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

global $_TABLES;

$_SQL['subscr_products'] = 
"CREATE TABLE {$_TABLES['subscr_products']} (
  `item_id` varchar(128) NOT NULL,
  `short_description` varchar(255) NOT NULL,
  `description` text,
  `price` decimal(5,2) unsigned DEFAULT NULL,
  `duration` int(5) DEFAULT NULL,
  `duration_type` varchar(10) NOT NULL DEFAULT 'month',
  `expiration` date DEFAULT NULL,
  `grace_days` int(5) NOT NULL DEFAULT '0',
  `early_renewal` int(5) unsigned DEFAULT '0',
  `enabled` tinyint(1) DEFAULT '1',
  `show_in_block` tinyint(1) unsigned DEFAULT '0',
  `taxable` tinyint(1) unsigned DEFAULT '0',
  `dt_add` int(11) unsigned DEFAULT NULL,
  `views` int(4) unsigned DEFAULT '0',
  `buttons` text,
  `addgroup` int(5) DEFAULT NULL,
  `at_registration` tinyint(1) NOT NULL DEFAULT '0',
  `is_upgrade` tinyint(1) unsigned DEFAULT '0',
  `upg_from` varchar(128) DEFAULT NULL,
  `upg_price` decimal(5,2) DEFAULT '0.00',
  `upg_extend_exp` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `trial_days` int(3) unsigned NOT NULL DEFAULT '0',
  `prf_update` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `prf_type` varchar(40) DEFAULT NULL,
  `grp_access` mediumint(8) NOT NULL DEFAULT '13',
  PRIMARY KEY (`item_id`)
) ENGINE=MyISAM";

$_SQL['subscr_subscriptions'] = 
"CREATE TABLE {$_TABLES['subscr_subscriptions']} (
  `id` int(11) NOT NULL auto_increment,
  `item_id` varchar(128) NOT NULL,
  `uid` int(11) unsigned NOT NULL default '0',
  `expiration` date default NULL,
  `notified` tinyint(1) unsigned NOT NULL default '0',
  `status` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `idx_uid_itemid` (`uid`,`item_id`),
  KEY `subscr_itemid` (`item_id`),
  KEY `subscr_userid` (`uid`),
  KEY `subscr_expiration` (`expiration`)
) ENGINE=MyISAM";

$_SQL['subscr_history'] = 
"CREATE TABLE {$_TABLES['subscr_history']} (
  `id` int(11) NOT NULL auto_increment,
  `item_id` varchar(128) NOT NULL,
  `uid` int(11) unsigned NOT NULL default '0',
  `txn_id` varchar(255) default '',
  `purchase_date` datetime default NULL,
  `expiration` datetime default NULL,
  `price` float(10,2) NOT NULL default '0.00',
  `notes` text,
  PRIMARY KEY  (`id`),
  KEY `subscr_itemid` (`item_id`),
  KEY `subscr_userid` (`uid`)
) ENGINE=MyISAM";


$SUBSCR_UPGRADE = array(
'0.1.1' => array(
    "ALTER TABLE {$_TABLES['subscr_products']}
        ADD expiration date AFTER duration_type,
        ADD at_registration tinyint(1) NOT NULL DEFAULT '0',
        ADD is_upgrade tinyint(1) unsigned DEFAULT '0',
        ADD upg_from varchar(40) DEFAULT NULL",
    ),
'0.1.2' => array(
    "ALTER TABLE {$_TABLES['subscr_products']}
        ADD pp_account varchar(255)",
    ),
'0.1.3' => array(
    "ALTER TABLE {$_TABLES['subscr_products']}
        ADD upg_price decimal(5,2) DEFAULT '0',
        ADD upg_extend_exp tinyint(1) unsigned NOT NULL DEFAULT '0',
        ADD trial_days tinyint(3) unsigned NOT NULL DEFAULT '0',
        ADD prf_update tinyint(1) unsigned NOT NULL DEFAULT'0',
        ADD prf_type varchar(40),
        ADD owner_id mediumint(8) unsigned NOT NULL DEFAULT 2,
        ADD group_id mediumint(8) unsigned NOT NULL DEFAULT 2,
        ADD perm_owner tinyint(1) unsigned NOT NULL DEFAULT 3,
        ADD perm_group tinyint(1) unsigned NOT NULL DEFAULT 2,
        ADD perm_members tinyint(1) unsigned NOT NULL DEFAULT 2,
        ADD perm_anon tinyint(1) unsigned NOT NULL DEFAULT 0,
        DROP pp_account,
        DROP is_upgrade",
    "CREATE TABLE {$_TABLES['subscr_history']} (
      `id` int(11) NOT NULL auto_increment,
      `item_id` varchar(40) NOT NULL default '',
      `uid` int(11) unsigned NOT NULL default '0',
      `txn_id` varchar(255) default '',
      `purchase_date` datetime default NULL,
      `expiration` datetime default NULL,
      `price` float(10,2) NOT NULL default '0.00',
      `notes` text,
      PRIMARY KEY  (`id`),
      KEY `subscr_itemid` (`item_id`),
      KEY `subscr_userid` (`uid`)
    ) ENGINE=MyISAM",
),
'0.1.6' => array(
    "ALTER TABLE {$_TABLES['subscr_subscriptions']}
        DROP KEY subscr_userid,
        ADD KEY subscr_userid(uid, item_id)",
    ),
'0.2.0' => array(
    "ALTER TABLE {$_TABLES['subscr_products']}
        DROP `name`,
        CHANGE `item_id` `item_id` varchar(128) NOT NULL,
        CHANGE `upg_from` `upg_from` varchar(128) NOT NULL",
    "ALTER TABLE {$_TABLES['subscr_subscriptions']}
        CHANGE `item_id` `item_id` varchar(128) NOT NULL",
    "ALTER TABLE {$_TABLES['subscr_history']}
        CHANGE `item_id` `item_id` varchar(128) NOT NULL",
    ),
'0.2.1' => array(
    "ALTER TABLE {$_TABLES['subscr_products']}
        CHANGE group_id grp_access mediumint(8) unsigned NOT NULL DEFAULT 13,
        DROP owner_id, DROP perm_owner, DROP perm_group, DROP perm_members, DROP perm_anon",
    // add subscr_history table creation if it doesn't exist
    "CREATE TABLE IF NOT EXIST {$_TABLES['subscr_history']} (
        `id` int(11) NOT NULL auto_increment,
        `item_id` varchar(128) NOT NULL,
        `uid` int(11) unsigned NOT NULL default '0',
        `txn_id` varchar(255) default '',
        `purchase_date` datetime default NULL,
        `expiration` datetime default NULL,
        `price` float(10,2) NOT NULL default '0.00',
        `notes` text,
        PRIMARY KEY  (`id`),
        KEY `subscr_itemid` (`item_id`),
        KEY `subscr_userid` (`uid`)
        ) ENGINE=MyISAM",
    ),
);

?>
