<?php
/**
 * Class to cache DB and web lookup results.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018 Lee Garner <lee@leegarner.com>
 * @package     subscription
 * @version     v0.2.2
 * @since       v0.2.2
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Subscription;

/**
 * Cache lookups for the Subscription plugin
 * @package subscription
 */
class Cache
{
    const TAG = 'subscription';     // tag to include in every record
    const MIN_GVERSION = '2.0.0';   // minimum glFusion to support caching

    /**
     * Update the cache.
     * Adds an array of tags including the plugin name
     *
     * @param   string  $key    Item key
     * @param   mixed   $data   Data, typically an array
     * @param   mixed   $tag    Tag, or array of tags.
     * @param   integer $cache_mins Cache minutes
     * @return  boolean     True on success, False on error
     */
    public static function set($key, $data, $tag='', $cache_mins=1440)
    {
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            return;     // caching not suppored in this glFusion version
        }

        // Always make sure the base tag is included
        $tags = array(self::TAG);
        if (!empty($tag)) {
            if (!is_array($tag)) $tag = array($tag);
            $tags = array_merge($tags, $tag);
        }
        $key = self::makeKey($key);
        return \glFusion\Cache\Cache::getInstance()
            ->set($key, $data, $tags, (int)$cache_mins * 60);
    }


    /**
     * Delete a single item from the cache by key
     *
     * @param   string  $key    Base key, e.g. item ID
     * @return  boolean     True on success, False on error
     */
    public static function delete($key)
    {
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            return;     // caching not suppored in this glFusion version
        }
        $key = self::makeKey($key);
        return \glFusion\Cache\Cache::getInstance()->delete($key);
    }


    /**
     * Completely clear the cache.
     * Called after upgrade.
     *
     * @param   array   $tag    Optional array of tags, base tag used if undefined
     * @return  boolean     True on success, False on error
     */
    public static function clear($tag = array())
    {
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            return;     // caching not suppored in this glFusion version
        }
        $tags = array(self::TAG);
        if (!empty($tag)) {
            if (!is_array($tag)) $tag = array($tag);
            $tags = array_merge($tags, $tag);
        }
        return \glFusion\Cache\Cache::getInstance()->deleteItemsByTagsAll($tags);
    }


    /**
     * Delete group cache after adding or removing memberships.
     * This uses the core glFusion tags that are cleared when memberships
     * are changed via the admin interface.
     *
     * @param   integer $grp_id     ID of affected group
     * @param   integer $uid        ID of affected user
     * @return  boolean     True on success, False on error
     */
    public static function clearGroup($grp_id, $uid)
    {
        $tags = array('menu', 'groups', 'group_' . $grp_id, 'user_' . $uid);
        return self::clearAnyTags($tags);
    }


    /**
     * Delete cache items that match any of the supplied tags.
     * Does not include the default "subscriptions" tag.
     *
     * @param   array   $tags   Single or Array of tags
     * @return  boolean     True on success, False on error
     */
    public static function clearAnyTags($tags)
    {
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            return;     // caching not suppored in this glFusion version
        }
        if (!is_array($tags)) $tags = array($tags);
        return \glFusion\Cache\Cache::getInstance()->deleteItemsByTags($tags);
    }


    /**
     * Create a unique cache key.
     * Intended for internal use, but public in case it is needed.
     *
     * @param   string  $key    Base key, e.g. Item ID
     * @return  string          Encoded key string to use as a cache ID
     */
    public static function makeKey($key)
    {
        return \glFusion\Cache\Cache::getInstance()->createKey(self::TAG . '_' . $key);
    }


    /**
     * Get an item from cache.
     *
     * @param   string  $key    Key to retrieve
     * @return  mixed       Value of key, or NULL if not found
     */
    public static function get($key)
    {
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            return NULL;    // caching not suppored in this glFusion version
        }
        $key = self::makeKey($key);
        if (\glFusion\Cache\Cache::getInstance()->has($key)) {
            return \glFusion\Cache\Cache::getInstance()->get($key);
        } else {
            return NULL;
        }
    }

}   // class Subscription\Cache

?>
