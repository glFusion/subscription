<?php
/**
*   Class to manage actual subscriptions
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010-2017 Lee Garner
*   @package    subscription
*   @version    0.2.2
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Subscription;

/**
*   Class for subscription
*   @package subscription
*/
class Subscription
{
    /** Property fields.  Accessed via __set() and __get()
    *   @var array */
    private $properties;

    /** Subscription plan object.
    *   @var object */
    public $Plan;

    /** Indicate whether the current user is an administrator
    *   @var boolean */
    private $isAdmin;

    private $isNew;
    private $dt;        // Place to keep a date value

    /** Array of error messages
     *  @var array */
    public $Errors = array();


    /**
    *   Constructor.
    *   Reads in the specified subscription record if $id is set.  If $id is zero,
    *   then a new entry is being created.
    *
    *   @param  integer $id     Optional subscription record ID
    */
    public function __construct($id=0)
    {
        global $_CONF_SUBSCR, $_CONF;

        $this->properties = array();
        $this->isNew = true;
        $this->status = 0;
        $this->dt = new \Date('now', $_CONF['timezone']);
        $this->Plan = NULL;
        $id = (int)$id;
        if (is_array($id)) {
            // Load variables from supplied array
            $this->setVars($id);
        } elseif ($id < 1) {
            $this->id = 0;
            $this->item_id = '';
            $this->uid = 0;
            $this->price = 0;
            $this->expiration = $this->dt->format('Y-m-d');
            $this->txn_id = '';
            $this->purchase_date = $this->dt->format('Y-m-d');
        } else {
            $this->id  = $id;
            if (!$this->Read()) {
                $this->id = 0;
            }
        }
        $this->isAdmin = SEC_hasRights('subscription.admin') ? 1 : 0;
    }


    /**
    *   Set a property's value.
    *
    *   @param  string  $var    Name of property to set.
    *   @param  mixed   $value  New value for property.
    */
    public function __set($var, $value='')
    {
        switch ($var) {
        case 'id':
        case 'uid':
        case 'status':
            // Integer values
            $this->properties[$var] = (int)$value;
            break;

        case 'item_id':
            $this->properties[$var] = COM_sanitizeID($value, false);
            break;

        case 'txn_id':
        case 'purchase_date':
        case 'exp_day':
        case 'exp_month':
        case 'exp_year':
            // String values
            $this->properties[$var] = trim($value);
            break;

        case 'expiration':
            $value = trim($value);
            $this->properties[$var] = $value;
            list($this->exp_year, $this->exp_month, $this->exp_day) = explode('-', $value);
            break;

        case 'notified':
            $this->properties[$var] = $value == 1 ? 1 : 0;
            break;

        default:
            // Undefined values (do nothing)
            break;
        }
    }


    /**
    *   Get the value of a property.
    *   Emulates the behaviour of __get() function in PHP 5.
    *
    *   @param  string  $var    Name of property to retrieve.
    *   @return mixed           Value of property, NULL if undefined.
    */
    public function __get($var)
    {
        if (array_key_exists($var, $this->properties)) {
            return $this->properties[$var];
        } else {
            return NULL;
        }
    }


    /**
     *  Sets all variables to the matching values from $rows.
     *
     *  @param  array   $row        Array of values, from DB or $_POST
     *  @param  boolean $fromDB     True if read from DB, false if from $_POST
     */
    public function setVars($row, $fromDB=false)
    {
        if (!is_array($row)) return;
        $this->id = $row['id'];
        $this->item_id = $row['item_id'];
        $this->uid = $row['uid'];
        $this->expiration = $row['expiration'];
        $this->notified = isset($row['notified']) ? $row['notified'] : 0;
        $this->status = $row['status'];
    }


    /**
     *  Read a specific record and populate the local values.
     *
     *  @param  integer $id Optional ID.  Current ID is used if zero.
     *  @return boolean     True if a record was read, False on failure
     */
    public function Read($id = 0)
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id == 0) $id = $this->id;
        if ($id == 0) {
            $this->Errors[] = 'Invalid ID in Read()';
            return false;
        }

        $cache_key = 'subscr_' . $id;
        $row = Cache::get($cache_key);
        if ($row === NULL) {
            $result = DB_query("SELECT *
                    FROM {$_TABLES['subscr_subscriptions']}
                    WHERE id='$id'");
            if (!$result || DB_numRows($result) != 1) {
                return false;
            } else {
                $row = DB_fetchArray($result, false);
            }
        }
        if (!empty($row)) {
            $this->setVars($row, true);
            $this->isNew = false;
            $this->Plan = Product::getInstance($row['item_id']);
            Cache::set($cache_key, $row, 'subscriptions');
            return true;
        } else {
            return false;
        }
    }


    /**
    *   Get a subscription object by user and item ID
    *
    *   @param  integer $uid        User ID
    *   @param  string  $item_id    Product ID
    *   @return object      Subscription object
    */
    public static function getInstance($uid, $item_id)
    {
        global $_TABLES;

        $cache_key = "subscr_{$uid}_{$item_id}";
        $Obj = Cache::get($cache_key);
        if ($Obj === NULL) {
            $uid = (int)$uid;
            $item_id= DB_escapeString($item_id);
            $sql = "SELECT * FROM {$_TABLES['subscr_subscriptions']}
                    WHERE uid='{$uid}' AND item_id = '{$item_id}'";
            $res = DB_query($sql);
            if ($res && DB_numRows($res) == 1) {
                $A = DB_fetchArray($res, false);
                $Obj = new self($A);
                Cache::set($cache_key, 'subscriptions');
            } else {
                $Obj = new self();
            }
        }
        return $Obj;
    }


    /**
     *  Save the current values to the database.
     *  Appends error messages to the $Errors property.
     *
     *  @param  array   $A      Optional array of values from $_POST
     *  @return boolean         True if no errors, False otherwise
     */
    public function Save($A = '')
    {
        global $_TABLES;

        if (is_array($A)) {
            $this->setVars($A);
        }

        // If cancelling an existing subscription, just call self::Cancel()
        if ($this->status == SUBSCR_STATUS_CANCELED && $this->id > 0) {
            return self::Cancel($this->uid);
        }

        if (!$this->isValidRecord()) {
            return false;
        }

        $db_expiration = DB_escapeString($this->expiration);
        $sql = "INSERT INTO {$_TABLES['subscr_subscriptions']} SET
                    item_id = '{$this->item_id}',
                    uid = '" . (int)$this->uid . "',
                    expiration = '$db_expiration',
                    notified = '{$this->notified}',
                    status = '{$this->status}'
                ON DUPLICATE KEY UPDATE
                    expiration = '$db_expiration',
                    notified = '{$this->notified}',
                    status = '{$this->status}'";
        //echo $sql;die;
        DB_query($sql, 1);

        if (!DB_error()) {
            /*if ($this->id == 0) {
                $this->id = DB_insertID();
            }*/
            $status = true;
            $P = new Product($this->item_id);
            $this->AddtoGroup($P->addgroup, $this->uid);
            //$P->updateProfile($this->expiration, $this->uid);
            $this->Read();
            $this->AddHistory();
            Cache::clear('subscriptions');
        } else {
            $status = false;
            $this->Errors[] = 'Database error, possible duplicate key.';
            COM_errorLog(__METHOD__ . '() SQL error: ' . $sql);
        }
        /*$logmsg .= ' ' . $this->id . ' for ' .
                COM_getDisplayName($A['uid']) . ' (' . $A['uid'] . ') ' .
                $this->ProductName() . ", exp {$this->expiration}";*/
        SUBSCR_debug('Status of last update: ' . print_r($status,true));
        Cache::clear('subscriptions');
        return $status;
    }


    /**
    *   Delete the current subscription record from the database
    *
    *   @return boolean True on success, False on invalid ID
    */
    public function Delete()
    {
        global $_TABLES, $_CONF_SUBSCR;

        if ($this->id < 1)      // Invalid or new record
            return false;

        DB_delete($_TABLES['subscr_subscriptions'], 'id', $this->id);
        Cache::clear('subscriptions');
        $this->id = 0;
        return true;
    }


    /**
    *   Add a new subscription record, or extend an existing one.
    *   This handles purchased subscriptions and calculates an expiration date.
    *
    *   @uses   AddtoGroup()
    *   @uses   AddHistory()
    *   @param  integer $uid        User ID
    *   @param  string  $item_id    Product item ID
    *   @param  integer $duration   Optionsl Duration (# of duration_type's)
    *   @param  integer $duration_type  Optional Duration interval (week, month, etc.)
    *   @param  string  $expiration Optional fixed expiration
    *   @param  boolean $upgrade    True if this is an upgrade, default False
    *   @param  string  $txn_id     Optional Payment transaction ID
    *   @param  float   $price      Optional price, default to product price
    *   @return boolean     True on successful update, False on error
    */
    public function Add($uid, $item_id, $duration=0, $duration_type='',
                $expiration=NULL, $upgrade = false, $txn_id = '', $price = -1)
    {
        global $_TABLES;

        $this->uid = $uid;
        $this->item_id = $item_id;
        $today = $this->dt->format('Y-m-d');
        $this->status = SUBSCR_STATUS_ENABLED;
        $this->notified = 0;

        // Get the product information for this subscription
        $P = Product::getInstance($item_id);
        if ($price == -1) {
            $price = $upgrade ? $P->upg_price : $P->price;
        }
        if ($P->isNew) {
            return false;
        }
        $P->checkPerms = false; // don't check permissions, may be IPN

        if (empty($duration_type)) $duration_type = $P->duration_type;
        $duration_type = strtoupper($duration_type);
        if ($duration == 0) $duration = $P->duration;
        $duration = (int)$duration;

        if ($this->isNew) {
            $this->expiration = $today;
        } else {
            if ($this->expiration < $today) {
                $this->expiration = $today;
            }
            // If this is an upgrade, verify that it is allowed.
            // Check that the current product is an upgrade item, and that it
            // isn't being upgraded against itself, and that there's a current
            // active subscription for it.
            if ($upgrade) {
                if ($this->status > 0 ||
                    $P->upg_from == '' ||
                    $P->upg_from != $this->item_id) {
                    return false;
                }
            }
        }

        // Set the new expiration to either the additional time, or the
        // fixed expiration date.
        if (!$upgrade || $P->upg_extend_exp == 1) {
            if ($duration_type != 'FIXED') {
                $expiration = "'{$this->expiration}' + INTERVAL $duration $duration_type";
            } else {
                $expiration = "'" . DB_escapeString($P->expiration) . "'";
            }
        } else {
            $expiration = "'{$this->expiration}'";
        }

        if ($this->id == 0) {
            // Create a new subscription record
            $sql1 = "INSERT INTO {$_TABLES['subscr_subscriptions']} SET
                    uid = '{$this->uid}', ";
            $sql3 = " ON DUPLICATE KEY UPDATE
                    expiration = $expiration,
                    notified = 0,
                    status = " . SUBSCR_STATUS_ENABLED;
        } else {
            // Update an existing subscription.  Also resets the notify flag
            $sql1 = "UPDATE {$_TABLES['subscr_subscriptions']} SET ";
            $sql3 = " WHERE id = '{$this->id}'";
        }

        $sql2 = "item_id = '{$this->item_id}',
                expiration = $expiration,
                notified = 0,
                status = '" . SUBSCR_STATUS_ENABLED . "'";
        $sql = $sql1 . $sql2 . $sql3;
        //COM_errorLog($sql);
        DB_query($sql, 1);     // Execute event record update
        if (DB_error()) {
            COM_errorLog(__METHOD__ . "() SQL error: $sql");
            $status = false;
        } else {
            if ($this->id == 0) {
                $this->id = DB_insertID();
            }
            $status = true;
            $this->AddtoGroup($P->addgroup, $this->uid);
            $this->Read();
            $this->AddHistory($txn_id, $price);
            // Now have the product update the member profile
            //$P->updateProfile($this->expiration, $this->uid);
            Cache::clear('subscriptions');
        }
        return $status;
    }


    /**
    *   Add a history record.
    *
    *   @param  string  $txn_id     Transaction ID
    *   @param  float   $price      Price paid
    */
    public function AddHistory($txn_id = '', $price = 0)
    {
        global $_TABLES;

        $price = number_format($price, 2, '.', '');
        $sql = "INSERT INTO {$_TABLES['subscr_history']} SET
            item_id = '{$this->item_id}',
            uid = '{$this->uid}',
            txn_id = '" . DB_escapeString($txn_id) . "',
            purchase_date = '" . $this->dt->toMySQL() . "',
            expiration = '{$this->expiration}',
            price = '$price'";
        DB_query($sql, 1);
    }

           
    /**
    *   Adds a user to a glFusion group.
    *
    *   @param  integer $groupid    Group the user is added to
    *   @param  integer $uid        User ID being added
    */
    public function AddtoGroup($groupid, $uid)
    {
        global $_TABLES;

        $groupid = (int)$groupid;
        $uid = (int)$uid;

        SUBSCR_debug("Adding user $uid to group $groupid");
        if ($groupid > 0 && $uid > 2) {
            $groups = SEC_getUserGroups($uid);
            if (!in_array($groupid, $groups)) {

                // Set the main user value clause
                $values_arr = array("('$groupid', '$uid')");

                // If there are child accounts related to this subscriber,
                // then try to add them to the same group
                $status = LGLIB_invokeService('profile', 'getChildAccounts',
                        array('uid' => $uid), $output, $svc_msg);
                if ($status == PLG_RET_OK) {
                    foreach ($output as $child_uid) {
                        $values_arr[] = "('$groupid', $child_uid)";
                        /*DB_query("INSERT INTO {$_TABLES['group_assignments']}
                                (ug_main_grp_id, ug_uid)
                            VALUES
                                ('$groupid', $child_uid)");*/
                    }
                }

                $value_str = implode(',', $values_arr);
                DB_query("INSERT INTO {$_TABLES['group_assignments']}
                        (ug_main_grp_id, ug_uid)
                    VALUES
                        $value_str");
                        //('$groupid', $uid)");
            } else {
                SUBSCR_debug("User $uid is already in group $groupid");
            }
        } else {
            SUBSCR_debug("Invalid user $uid or group $groupid");
        }
    }


    /**
    *   Determines if the current record is valid.
    *
    *   @return boolean     True if ok, False when first test fails.
    */
    public function isValidRecord()
    {
        global $LANG_SUBSCR;

        // Check that basic required fields are filled in
        if ($this->item_id == '') {
            $this->Errors[] = $LANG_SUBSCR['err_missing_item'];
        }
        if ((int)$this->uid < 2) {
            $this->Errors[] = $LANG_SUBSCR['err_missing_uid'];
        }

        if (!empty($this->Errors)) {
            SUBSCR_debug('Errors encountered: ' . print_r($this->Errors,true));
            return false;
        } else {
            SUBSCR_debug('isValidRecord(): No errors');
            return true;
        }
    }


    /**
    *   Creates the edit form.
    *
    *   @param  integer $id     Optional ID, current record used if zero.
    *   @return string          HTML for edit form
    */
    public function Edit($id = 0)
    {
        global $_TABLES, $_CONF, $_CONF_SUBSCR, $LANG_SUBSCR,
                $LANG24, $LANG_postmodes, $_SYSTEM;

        $id = (int)$id;
        if ($id > 0) {
            // If an id is passed in, then read that record
            if (!$this->Read($id)) {
                return SUBSCR_errorMessage($LANG_SUBSCR['invalid_subscr_id'], 'info');
            }
            $sel_opts = '';
        } else {
            $sel_opts = '<option value="0" selected="selected">--' .
                        $LANG_SUBSCR['select'] . "--</option>\n";
        }

        $T = new \Template(SUBSCR_PI_PATH . '/templates');
        switch ($_SYSTEM['framework']) {
        case 'uikit':
            $T->set_file(array('product' => 'subscription_form.uikit.thtml'));
            break;
        default:
            $T->set_file(array('product' => 'subscription_form.thtml'));
            break;
        }
        if ($id > 0) {
            $retval = COM_startBlock($LANG_SUBSCR['edit'] . ': ' .
                    $this->name);
        } else {
            $retval = COM_startBlock($LANG_SUBSCR['new_subscription']);
        }

        $T->set_var(array(
            'id'            => $this->id,
            'expiration'    => $this->expiration,
            'notified_chk'  => $this->notified == 1 ?
                                    ' checked="checked"' : '',
            'pi_admin_url'  => SUBSCR_ADMIN_URL,
            'pi_url'        => SUBSCR_URL,
            'doc_url'       => SUBSCR_getDocURL('subscription_form.html',
                                            $_CONF['language']),
            'status_' . $this->status => 'checked="checked"',
            'subscriber_select' => $sel_opts .
                        COM_optionList($_TABLES['users'],
                        'uid,username', $this->uid, 1),
            'product_select' => $sel_opts .
                        COM_optionList($_TABLES['subscr_products'],
                        'item_id,item_id', $this->item_id, 1),
            'iconset'       => $_CONF_SUBSCR['_iconset'],
        ) );

        $retval .= $T->parse('output', 'product');
        $retval .= COM_endBlock();
        return $retval;
    }   // function Edit()


    /**
    *   Display the detail page for the product.
    *
    *   @return string      HTML for the product page.
    */
    public function Detail()
    {
        global $_CONF, $_CONF_SUBSCR, $_TABLES, $LANG_SUBSCR, $_USER;

        $id = $this->id;
        if ($item_id < 1) {
            return SUBSCR_errorMessage($LANG_SUBSCR['invalid_subscr_id'], 'info');
        }

        $retval = COM_startBlock();

        $T = new \Template(SUBSCR_PI_PATH . '/templates');
        $T->set_file(array('subscription' => 'subscr_detail.thtml',
            ));

        $A = DB_fetchArray(DB_query("SELECT item_id, description
                FROM {$_TABLES['subscr_products']}
                WHERE item_id='" . $this->item_id . "'"), false);

        $T->set_var(array(
            'user_id'           => $this->uid,
            'id'                => $id,
            'item_id'           => $this->item_id,
            'name'              => $A['item_id'],
            'description'       => $A['description'],
            'expiration'        => $this->expiration,
            'purchase_date'     => $this->purchase_date,
        ) );

        $retval .= $T->parse('output', 'subscription');
        $retval .= COM_endBlock();
        return $retval;
    }


    public function XX_Find($uid, $item_id)
    {
        global $_TABLES;

        $uid = (int)$uid;
        $item_id = COM_sanitizeID($item_id, false);

        $sub_id = DB_getItem($_TABLES['subscr_subscriptions'],
                'id', "uid=$uid AND item_id='$item_id'");
        if (!empty($sub_id)) {
            return $this->Read(sub_id);
        } else {
            return false;
        }
    }


    /**
    *   Cancel a subscription.
    *   If $system is true, then a user's name won't be logged with the message
    *   to avoid confusion.
    *
    *   @param  integer $sub_id     Database ID of the subscription to cancel
    *   @param  boolean $system     True if this is a system action.
    */
    public function Cancel($sub_id, $system=false)
    {
        global $_TABLES;

        $Sub = new self($sub_id);
        if ($Sub->id < 1) return;

        // Remove the subscriber from the subscription group
        USER_delGroup($Sub->Plan->groupid, $Sub->uid);

        // Delete the subscription and log the activity
        DB_delete($_TABLES['subscr_subscriptions'], 'id', $Sub->id);
        SUBSCR_auditLog("Cancelled subscription $Sub->id ({$Sub->Plan->item_id}) " .
                "for user {$Sub->uid} (" .COM_getDisplayName($Sub->uid) . '), expiring ' .
                $Sub->expiration, $system);
    }


    /**
    *   Get the product name associated with this subscription
    *
    *   @return string      Product name
    */
    public function ProductName()
    {
        global $_TABLES;
        return DB_getItem($_TABLES['subscr_products'], 'item_id',
            "item_id='" . $this->item_id . "'");
    }


    /**
    *   Create a formatted display-ready version of the error messages.
    *
    *   @return string      Formatted error messages.
    */
    public function PrintErrors()
    {
        $retval = '';
        foreach($this->Errors as $key=>$msg) {
            $retval .= "<li>$msg</li>\n";
        }
        return $retval;
    }


    /*function updateProfile($newtype, $newdate, $uid = 0)
    {
        if (function_exists('PROFILE_updateExpiration')) {
            if (!PROFILE_updateExpiration($newdate, $uid))
                return false;
        }
        if (function_exists('PROFILE_updateMembertype')) {
            if (!PROFILE_updateMembertype($newtype, $uid))
                return false;
        }
        return true;
    }*/


    /**
    *   Get all current subscriptions for a user
    *
    *   @param  integer $uid    User ID to check, current user by default
    *   @param  integer $status Subscription status, Active by default
    *   @return array   Array of subscription objects
    */
    public static function getSubscriptions($uid = 0, $status = SUBSCR_STATUS_ENABLED)
    {
        global $_USER, $_TABLES;

        $retval = array();
        if ($uid == 0) {
            $uid = $_USER['uid'];
        }
        $uid = (int)$uid;
        $sql = "SELECT * FROM {$_TABLES['subscr_subscriptions']}
                WHERE uid = $uid";
        if (is_array($status)) {
            $status = array_map('intval', $status);
            $sql .= ' AND status IN (' . implode(',', $status) . ')';
        } elseif ($status > -1) {
            $status = (int)$status;
            $sql .= " AND status = $status";
        }
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[$A['item_id']] = new Subscription();
            $retval[$A['item_id']]->setVars($A);
        }
        return $retval;
    }

}   // class Subscription

?>
