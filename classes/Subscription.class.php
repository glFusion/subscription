<?php
/**
 * Class to manage actual subscriptions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2022 Lee Garner
 * @package     subscription
 * @version     v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Subscription;
use glFusion\Database\Database;
use glFusion\Log\Log;


/**
 * Class for subscriptions.
 * @package subscription
 */
class Subscription
{
    const STATUS_ENABLED = 0;
    const STATUS_CANCELED = 1;
    const STATUS_EXPIRED = 2;

    /** Subscription record ID.
     * @var integer */
    private $id = 0;

    /** Subscribing user ID.
     * @var integer */
    private $uid = 0;

    /** Subscription status.
     * @var integer */
    private $status = 0;

    /** Plan ID.
     * @var string */
    private $item_id = '';

    /** Purchase transaction ID.
     * @var string */
    private $txn_id = '';

    /** Purchase date.
     * @var string */
    private $purchase_date = '';

    /** Expiration date.
     * @var string */
    private $expiration = '';

    /** Flag to indicate the subscriber has been notified of impending expiration.
     * @var boolean */
    private $notified = 0;

    /** Subscription plan object.
     * @var object */
    public $Plan = NULL;

    /** Indicate whether the current user is an administrator
     * @var boolean */
    private $isAdmin = false;

    /** Holder for a general-purpose date object.
     * @var object */
    private $dt = NULL;

    /** Array of error messages.
     * @var array */
    public $Errors = array();

    /** Duration, in days, months or years.
     * Used to calculate expiration.
     * @var integer */
    private $duration = 0;

    /** Duration type.
     * Day, Week, Month, Year or FIXED.
     * @var string */
    private $duration_type = 'MONTH';

    /** Subscription price.
     * Used for logging transaction history.
     * @var float */
    private $price = -1;

    /** Flag to indicate this subscription is an upgrade.
     * @var boolean */
    private $is_upgrade = 0;

    /**
     * Constructor.
     * Reads in the specified subscription record if $id is set.  If $id is zero,
     * then a new entry is being created.
     *
     * @param   integer $id     Optional subscription record ID
     */
    public function __construct($id=0)
    {
        global $_CONF_SUBSCR, $_CONF;

        $this->dt = new \Date('now', $_CONF['timezone']);
        if (is_array($id)) {
            // Load variables from supplied array
            $this->setVars($id);
            $this->Plan = Plan::getInstance($this->item_id);
        } elseif ($id < 1) {
            $this->expiration = $this->dt->format('Y-m-d');
            $this->purchase_date = $this->dt->format('Y-m-d');
        } else {
            $this->id = $id;
            if (!$this->Read()) {
                $this->id = 0;
            }
        }
        $this->isAdmin = SEC_hasRights('subscription.admin') ? 1 : 0;
    }


    /**
     * Get the subscription ID
     *
     * @return  int     The subscription ID
     */
    public function getID()
    {
      return $this->id;
    }


    /**
     * Get the plan ID from the subsciption
     *
     * @return  string      the Plan ID
     */
    public function getitemID()
    {
      return $this->item_id;
    }


    /**
     * Get the subscriber's user ID.
     *
     * @return  integer     User ID
     */
    public function getUid() : int
    {
        return (int)$this->uid;
    }


    /**
     * Get the expiration date.
     *
     * @return  string      Expiration date
     */
    public function getExpiration()
    {
        return $this->expiration;
    }


    /**
     * Set the subscriber's user ID.
     *
     * @param   integer $uid    User ID
     * @return  object  $this
     */
    public function withUid($uid)
    {
        $this->uid = (int)$uid;
        return $this;
    }


    /**
     * Set the subscription plan ID.
     *
     * @param   string  $item_id    Plan ID
     * @return  object  $this
     */
    public function withItemId($item_id)
    {
        $this->item_id = $item_id;
        return $this;
    }


    /**
     * Set the number of days/weeks/months for the duration.
     *
     * @see     self::withDurationType()
     * @param   integer $duration   Duration number.
     * @return  object  $this
     */
    public function withDuration($duration)
    {
        $this->duration = (int)$duration;
        return $this;
    }


    /**
     * Set the duration type, day, week, etc.
     *
     * @param   string  $type   Duration type
     * @return  object  $this
     */
    public function withDurationType($type)
    {
        $type = strtoupper($type);
        switch ($type) {
        case 'DAY':
        case 'WEEK':
        case 'MONTH':
        case 'YEAR':
        case 'FIXED':
            $this->duration_type = $type;
            break;
        default:
            $this->duration_type = 'MONTH';
            break;
        }
        return $this;
    }


    /**
     * Set the upgrade flag if this is an upgrade from another plan.
     *
     * @param   boolean $flag   True if this is an upgrade, False if not
     * @return  object  $this
     */
    public function withUpgrade($flag)
    {
        $this->is_upgrade = $flag ? true : false;
        return $this;
    }


    /**
     * Set the payment transaction ID for tracking.
     *
     * @param   string  $txn_id Transaction ID
     * @return  object  $this
     */
    public function withTxnId($txn_id)
    {
        $this->txn_id = $txn_id;
        return $this;
    }


    /**
     * Set the price for this subscription.
     *
     * @param   float   $price  Subscription price
     * @return  object  $this
     */
    public function withPrice($price)
    {
        $this->price = (float)$price;
        return $this;
    }


    public function isNew() : bool
    {
        return $this->id == 0;
    }


    /**
     * Sets all variables to the matching values from $rows.
     *
     * @param   array   $row        Array of values, from DB or $_POST
     * @param   boolean $fromDB     True if read from DB, false if from $_POST
     */
    public function setVars($row, $fromDB=false)
    {
        if (!is_array($row)) return;
        $this->id = (int)$row['id'];
        $this->item_id = $row['item_id'];
        $this->uid = (int)$row['uid'];
        $this->expiration = $row['expiration'];
        $this->notified = isset($row['notified']) ? (int)$row['notified'] : 0;
        $this->status = (int)$row['status'];
    }


    /**
     * Read all the subscribers for a specific plan ID.
     *
     * @param   string  $plan_id    Plan ID
     * @return  array       Array of Subscriber objects
     */
    public static function getByPlan(string $plan_id) : array
    {
        global $_TABLES;

        $retval = array();
        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['subscr_subscriptions']}
                WHERE item_id = ?",
                array($plan_id),
                array(Database::STRING)
            )->fetchAllAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (!empty($data)) {
            foreach ($data as $A) {
                $retval[$A['uid']] = new self;
                $retval[$A['uid']]->setVars($A);
            }
        }
        return $retval;
    }


    /**
     * Read a specific record and populate the local values.
     *
     * @param   integer $id     Optional ID.  Current ID is used if zero.
     * @return  boolean     True if a record was read, False on failure
     */
    public function Read(int $id = 0) : bool
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id == 0) $id = $this->id;
        if ($id == 0) {
            $this->Errors[] = 'Invalid ID in Read()';
            return false;
        }

        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['subscr_subscriptions']}
                WHERE id = ?",
                array($id),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (!empty($data)) {
            $this->setVars($data, true);
            $this->Plan = Plan::getInstance($data['item_id']);
            return true;
        } else {
            return false;
        }
    }


    /**
     * Get a subscription object by user and item ID.
     *
     * @param   integer $uid        User ID
     * @param   string  $item_id    Plan ID
     * @return  object      Subscription object
     */
    public static function getInstance(int $uid, string $item_id='') : self
    {
        global $_TABLES;

        $uid = (int)$uid;
        $db = Database::getInstance();
        $where = 'uid = ?';
        $values = array($uid);
        $types = array(Database::INTEGER);
        if (!empty($item_id)) {
            $where .= ' AND item_id = ?';
            $values[] = $item_id;
            $types[] = Database::STRING;
        }
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['subscr_subscriptions']}
                WHERE $where
                ORDER BY expiration DESC LIMIT 1",
                $values,
                $types
            )->fetchAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (!empty($data)) {
            $Obj = new self($data);
        } else {
            $Obj = new self();
        }
        return $Obj;
    }


    /**
     * Save the current values to the database.
     * Appends error messages to the $Errors property.
     *
     * @param   array   $A      Optional array of values from $_POST
     * @return  boolean         True if no errors, False otherwise
     */
    public function Save(?array $A = NULL) : bool
    {
        global $_TABLES;

        if (is_array($A)) {
            $this->setVars($A);
        }

        // If cancelling an existing subscription, just call self::_doCancel()
        if ($this->status == self::STATUS_CANCELED) {
            if ($this->id != 0) {
                return self::_doCancel();
            } else {
                return true;    // Return success but do nothing
            }
        }

        if (!$this->isValidRecord()) {
            return false;
        }

        $db = Database::getInstance();
        try {
            $db->conn->insert(
                $_TABLES['subscr_subscriptions'],
                array(
                    'item_id' => $this->item_id,
                    'uid' => $this->uid,
                    'expiration' => $this->expiration,
                    'notified' => $this->notified,
                    'status' => $this->status,
                ),
                array(
                    Database::INTEGER,
                    Database::INTEGER,
                    Database::STRING,
                    Database::INTEGER,
                    Database::INTEGER,
                )
            );
            $this->id = $db->conn->lastInsertId();
            $status = true;
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $k) {
            try {
                $db->conn->update(
                    $_TABLES['subscr_subscriptions'],
                    array(
                        'expiration' => $this->expiration,
                        'notified' => $this->notified,
                        'status' => $this->status,
                    ),
                    array(
                        Database::STRING,
                        Database::INTEGER,
                        Database::INTEGER,
                    )
                );
                $status = true;
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $status = false;
            }
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $status = false;
        }

        if ($status) {
            $status = true;
            $this->AddtoGroup();
            $this->addHistory();
            Cache::clear('subscriptions');
        } else {
            $this->Errors[] = 'Database error, possible duplicate key.';
        }
        Log::write('subscr_debug', Log::DEBUG, 'Status of last update: ' . print_r($status,true));
        return $status;
    }


    /**
     * Delete the current subscription record from the database.
     *
     * @return  boolean True on success, False on invalid ID
     */
    public function Delete() : bool
    {
        global $_TABLES, $_CONF_SUBSCR;

        if ($this->id < 1) {    // Invalid or new record
            return false;
        }

        $db = Database::getInstance();
        try {
            $db->conn->delete(
                $_TABLES['subscr_subscriptions'],
                array('id' => $this->id),
                array(Database::INTEGER)
            );
            Cache::clear('subscriptions');
            $this->id = 0;
            return true;
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Add a new subscription record, or extend an existing one.
     * This handles purchased subscriptions and calculates an expiration date.
     *
     * @uses    AddtoGroup()
     * @uses    addHistory()
     * @return  boolean     True on successful update, False on error
     */
    public function Add() : bool
    {
        global $_TABLES, $_CONF;;

        $today = $this->dt->format('Y-m-d');
        $this->status = self::STATUS_ENABLED;

        // Get the product information for this subscription
        $P = Plan::getInstance($this->item_id);
        if ($this->price == -1) {
            $this->price = $this->is_upgrade ? $P->getUpgradePrice(): $P->getBasePrice();
        }
        if ($P->isNew()) {
            return false;
        }
        $P->setCheckPerms(false); // don't check permissions, may be IPN

        if (empty($this->duration_type)) {
            $this->duration_type = $P->getDurationType();
        }
        if ($this->duration == 0) {
            $this->duration = $P->getDuration();
        }

        if ($this->isNew()) {
            $this->expiration = $today;
        } else {
            if ($this->expiration < $today) {
                $this->expiration = $today;
            }
            // If this is an upgrade, verify that it is allowed.
            // Check that the current product is an upgrade item, and that it
            // isn't being upgraded against itself, and that there's a current
            // active subscription for it.
            if ($this->is_upgrade) {
                if ($this->status > 0 ||
                    $P->getUpgradeFrom() == '' ||
                    $P->getUpgradeFrom() != $this->item_id) {
                    return false;
                }
            }
        }

        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();
        if ($this->id == 0) {
            // Create a new subscription record
            $qb->insert($_TABLES['subscr_subscriptions'])
               ->setValue('uid', ':uid')
               ->setValue('item_id', ':item_id')
               ->setValue('notified', ':notified')
               ->setValue('expiration', ':expiration')
               ->setValue('status', ':status');
        } else {
            // Update an existing subscription.  Also resets the notify flag
            $qb->update($_TABLES['subscr_subscriptions'])
                ->set('item_id', ':item_id')
                ->set('expiration', ':expiration')
                ->set('notified', ':notified')
                ->set('status', ':status')
                ->where('id = :id')
                ->setParameter('id', $this->id, Database::INTEGER);
        }
        // Set the new expiration to either the additional time, or the
        // fixed expiration date.
        if (!$this->is_upgrade || $P->upgradeExtendsExp() == 1) {
            if ($this->duration_type != 'FIXED') {
                $dt = clone $_CONF['_now'];
                $interval = \DateInterval::createFromDateString($this->duration . ' ' . $this->duration_type);
                $dt->add($interval);
                $expiration = $dt->toMySQL(true);
            } else {
                $expiration = $P->getExpiration();
                echo $expiration;die;
            }
        } else {
            $expiration = $this->expiration;
        }
        $qb->setParameter('item_id', $this->item_id, Database::STRING)
           ->setParameter('uid', $this->uid, Database::INTEGER)
           ->setParameter('status', self::STATUS_ENABLED, Database::INTEGER)
           ->setParameter('notified', 0, Database::INTEGER)
           ->setParameter('expiration', $expiration, Database::STRING);
        try {
            $qb->execute();
            if ($this->id == 0) {
                $this->id = $db->conn->lastInsertId();
            }
            $status = true;
            $this->AddtoGroup();
            $this->Read();
            $this->addHistory($this->txn_id, $this->price);
            Cache::clear('subscriptions');
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $status = false;
        }
        return $status;
    }


    /**
     * Add a history record.
     *
     * @param   string  $txn_id     Transaction ID
     * @param   float   $price      Price paid
     */
    public function addHistory(string $txn_id, float $price = 0) : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->insert(
                $_TABLES['subscr_history'],
                array(
                    'item_id' => $this->item_id,
                    'uid' => $this->uid,
                    'txn_id' => $txn_id,
                    'purchase_date' => $this->dt->toMySQL(),
                    'expiration' => $this->expiration,
                    'price' => $price,
                ),
                array(
                    Database::STRING,
                    Database::INTEGER,
                    Database::STRING,
                    Database::STRING,
                    Database::STRING,
                    Database::STRING,
                )
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Add referrer bonus based on provided plan id.
     *
     * @uses    AddReferral()
     * @param   object  $S          The subscription that was purchased
     * @param   boolean $notify     True to notify the referrer
     * @return  boolean     True on successful update, False on error
     */
    public function AddBonus($S, $notify=true)
    {
        global $_TABLES, $LANG_SUBSCR;

        // Get the product information for this subscription
        $P = Plan::getInstance($S->getItemID());

        if ($P->getBonusDuration() == 0) {  // if no duration, no bonus
            return true;
        }

        $expiration = "";
        $db = Database::getInstance();
        try {
            $db->conn->executeStatement(
                "UPDATE {$_TABLES['subscr_subscriptions']}
                SET expiration = DATE_ADD('{$this->expiration}', INTERVAL {$P->getBonusDuration()} {$P->getBonusDurationType()})
                WHERE id = ?",
                array($this->id),
                array(Database::INTEGER)
            );
            $status = true;
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $status = false;
        }

        if ($status) {
            $this->AddReferral($S->getID());
            if ($notify) {
                $msg = sprintf(
                    $LANG_SUBSCR['msg_referral'],
                    $P->getName(),
                    $P->getBonusDuration(),
                    ucfirst($P->getBonusDurationType())
                );
                LGLIB_storeMessage(array(
                    'message' => $msg,
                    'title' => '',
                    'persist' => true,
                    'pi_code' => 'subscription',
                    'uid' => $this->uid,
                ) );
            }
            Cache::clear('subscriptions');
        }
        return $status;
    }


    /**
     * Add a referral record.
     *
     * @param   integer $sub_id        The id of the subscription generating referral
     */
    public function AddReferral(int $sub_id) : void
    {
        global $_TABLES, $_CONF;

        $db = Database::getInstance();
        try {
            $db->conn->insert(
                $_TABLES['subscr_referrals'],
                array(
                    'referrer' => $this->uid,
                    'subscription_id' => $sub_id,
                    'purchase_date' => $_CONF['_now']->toMySQL(false),
                ),
                array(
                    Database::INTEGER,
                    Database::INTEGER,
                    Database::STRING,
                )
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Adds a user to a glFusion group.
     *
     * @param   integer $groupid    Group the user is added to
     * @param   integer $uid        User ID being added
     */
    private function AddtoGroup()
    {
        if (!$this->Plan) {
            $this->Plan = Plan::getInstance($this->item_id);
        }
        if (!$this->Plan->isNew()) {
            Log::write('subscr_debug', Log::DEBUG, "Adding user {$this->uid} to group {$this->Plan->getSubGroup()}");
            Cache::clearGroup($this->Plan->getSubGroup(), $this->uid);
            USES_lib_user();
            USER_addGroup($this->Plan->getSubGroup(), $this->uid);
        } else {
            Log::write('system', Log::ERROR, "Error finding group for plan {$this->item_id}");
        }
    }


    /**
     * Determines if the current record is valid.
     *
     * @return  boolean     True if ok, False when first test fails.
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
            Log::write('subscr_debug', Log::DEBUG, __METHOD__ . ': Errors encountered: ' . print_r($this->Errors,true));
            return false;
        } else {
            Log::write('subscr_debug', Log::DEBUG, __METHOD__ . ':  No errors');
            return true;
        }
    }


    /**
     * Creates the edit form.
     *
     * @param   integer $id     Optional ID, current record used if zero.
     * @return  string          HTML for edit form
     */
    public function Edit($id = 0)
    {
        global $_TABLES, $_CONF, $_CONF_SUBSCR, $LANG_SUBSCR,
                $LANG24, $LANG_postmodes, $_SYSTEM;

        $id = (int)$id;
        if ($id > 0) {
            // If an id is passed in, then read that record
            if (!$this->Read($id)) {
                return menu::errorMessage($LANG_SUBSCR['invalid_subscr_id'], 'info');
            }
            $sel_opts = '';
        } else {
            $sel_opts = '<option value="0" selected="selected">--' .
                        $LANG_SUBSCR['select'] . "--</option>\n";
        }

        $T = new \Template(SUBSCR_PI_PATH . '/templates');
        $T->set_file(array(
            'subscription' => 'subscription_form.thtml',
            'tooltipster' => 'tooltipster.thtml',
        ) );
        if ($id > 0) {
            $retval = COM_startBlock($LANG_SUBSCR['edit'] . ': ' . $this->name);
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
        ) );

        $T->parse('tooltipster_js', 'tooltipster');
        $retval .= $T->parse('output', 'subscription');
        $retval .= COM_endBlock();
        return $retval;
    }   // function Edit()


    /**
     * Display the detail page for the product.
     *
     * @return  string      HTML for the product page.
     */
    public function Detail()
    {
        global $_CONF, $_CONF_SUBSCR, $_TABLES, $LANG_SUBSCR, $_USER;

        $id = $this->id;
        if ($item_id < 1) {
            return Menu::errorMessage($LANG_SUBSCR['invalid_subscr_id'], 'info');
        }

        $retval = COM_startBlock();

        $T = new \Template(SUBSCR_PI_PATH . '/templates');
        $T->set_file(array(
            'subscription' => 'subscr_detail.thtml',
        ) );

        $db = Database::getInstance();
        try {
            $A = $db->conn->executeQuery(
                "SELECT item_id, description
                FROM {$_TABLES['subscr_products']}
                WHERE item_id='" . $this->item_id . "'",
                array($this->item_id),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $A = false;
        }
        if (empty($A)) {
            return $retval;
        }

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


    /**
     * Cancel a subscription by user and plan ID.
     * If $system is true, then a user's name won't be logged with the message
     * to avoid confusion.
     *
     * @uses    self:_doCancel()
     * @param   integer $uid        User ID to cancel
     * @param   string  $item_id    Plan ID to cancel for the user
     * @param   boolean $system     True if this is a system action.
     * @return  boolean             True on success, False on failure
     */
    public static function Cancel(int $uid, string $item_id, ?bool $system=NULL)
    {
        $Sub = self::getInstance($uid, $item_id);
        return $Sub->_doCancel($system);
    }


    /**
     * Cancel a subscription by subscription ID.
     * If $system is true, then a user's name won't be logged with the message
     * to avoid confusion.
     *
     * @uses    selff:_doCancel()
     * @param   integer $sub_id     Database ID of the subscription to cancel
     * @param   boolean $system     True if this is a system action.
     * @return  boolean             True on success, False on failure
     */
    public static function CancelByID($sub_id, $system=false)
    {
        $Sub = new self($sub_id);
        return $Sub->_doCancel($system);
    }


    /**
     * Actually perform the functions to cancel a subscription.
     *
     * @see     self::Cancel()
     * @see     self::CancelByID()
     * @param   boolean $system     True if this is a system cancellation
     * @return  boolean             True on success, False on failure
     */
    public function _doCancel($system = false)
    {
        global $_TABLES;

        if ($this->isNew()) return false;

        // Remove the subscriber from the subscription group
        USES_lib_user();
        Log::write('subscr_debug', Log::DEBUG, "Removing user {$this->uid} from {$this->Plan->getSubGroup()}");
        Cache::clearGroup($this->Plan->getSubGroup(), $this->uid);
        USER_delGroup($this->Plan->getSubGroup(), $this->uid);

        // Mark the subscription as canceled and log the activity
        $db = Database::getInstance();
        try {
            $db->conn->update(
                $_TABLES['subscr_subscriptions'],
                array('status' => self::STATUS_CANCELED),
                array('id' => $this->id),
                array(Database::INTEGER)
            );
            Log::write(
                'subscr_audit',
                Log::INFO,
                "Canceled subscription $this->id ({$this->Plan->getID()}) " .
                "for user {$this->uid} (" .COM_getDisplayName($this->uid) . ')'
            );
            $status = true;
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $status = false;
        }
        return $status;
    }


    /**
     * Mark a subscription as expired by user and plan ID.
     * If $system is true, then a user's name won't be logged with the message
     * to avoid confusion.
     *
     * @uses    self:_doExpire()
     * @param   integer $uid        User ID to expire
     * @param   string  $item_id    Plan ID to expire for the user
     * @param   boolean $system     True if this is a system action.
     * @return  boolean             True on success, False on failure
     */
    public static function Expire($uid, $item_id, $system=false)
    {
        $Sub = self::getInstance($uid, $item_id);
        return $Sub->_doExpire($system);
    }


    /**
     * Mark a subscription as expired by subscription ID.
     * If $system is true, then a user's name won't be logged with the message
     * to avoid confusion.
     *
     * @uses    selff:_doExpire()
     * @param   integer $sub_id     Database ID of the subscription to mark as expired
     * @param   boolean $system     True if this is a system action.
     * @return  boolean             True on success, False on failure
     */
    public static function ExpireByID($sub_id, $system=false)
    {
        $Sub = new self($sub_id);
        return $Sub->_doExpire($system);
    }


    /**
     * Actually perform the functions to mark a subscription as expired.
     *
     * @see     self::Expire()
     * @see     self::ExpireByID()
     * @param   boolean $system     True if this is a system cancellation
     * @return  boolean             True on success, False on failure
     */
    private function _doExpire($system = false)
    {
        global $_TABLES;

        if ($this->isNew()) return false;

        // Remove the subscriber from the subscription group
        USES_lib_user();
        Log::write('subscr_debug', Log::DEBUG, "Removing user {$this->uid} from {$this->Plan->getSubGroup()}");
        Cache::clearGroup($this->Plan->getSubGroup(), $this->uid);
        USER_delGroup($this->Plan->getSubGroup(), $this->uid);

        // Mark the subscription as expired and log the activity
        $db = Database::getInstance();
        try {
            $db->conn->update(
                $_TABLES['subscr_subscriptions'],
                array('status' => self::STATUS_EXPIRED),
                array('id' => $this->id),
                array(Database::STRING)
            );
            Log::write(
                'subscr_audit',
                Log::INFO,
                "Marked subscription $this->id ({$this->Plan->getID()}) as expired " .
                "for user {$this->uid} (" .COM_getDisplayName($this->uid) . '), expiring ' .
                $this->expiration
            );
            return true;
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Create a formatted display-ready version of the error messages.
     *
     * @return  string      Formatted error messages.
     */
    public function PrintErrors()
    {
        $retval = '';
        foreach($this->Errors as $key=>$msg) {
            $retval .= "<li>$msg</li>\n";
        }
        return $retval;
    }


    /**
     * Get all current subscriptions for a user.
     *
     * @param   integer $uid    User ID to check, current user by default
     * @param   integer $status Subscription status, Active by default
     * @return  array       Array of subscription objects
     */
    public static function getSubscriptions($uid = 0, $status = self::STATUS_ENABLED)
    {
        global $_USER, $_TABLES;

        $retval = array();
        if ($uid == 0) {
            $uid = $_USER['uid'];
        }
        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();
        $qb->select('*')
           ->from($_TABLES['subscr_subscriptions'])
           ->where('uid = :uid')
           ->setParameter('uid', $uid, Database::INTEGER);
        if (is_array($status)) {
            $status = array_map('intval', $status);
            $qb->andWhere('status IN (:statuses)')
               ->setParameter('statuses', $status, Database::PARAM_INT_ARRAY);
        } elseif ($status > -1) {
            $status = (int)$status;
            $qb->andWhere('status = :status')
               ->setParameter('status', $status, Database::INTEGER);
        }
         try {
            $data = $qb->execute()->fetchAllAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }

        if (!empty($data)) {
            foreach ($data as $A) {
                $retval[$A['item_id']] = new self($A);
            }
        }
        return $retval;
    }


    /**
     * Create an admin list of subscriptions for a product,
     *
     * @param   string  $item_id    Plan ID to limit list.
     * @return  string      HTML for list
     */
    public static function adminList($item_id)
    {
        global $_CONF, $_TABLES, $LANG_ADMIN, $LANG_ACCESS;
        global $_CONF_SUBSCR, $LANG_SUBSCR, $_IMAGE_TYPE, $LANG01;

        USES_lib_admin();

        $retval = '';

        $header_arr = array(      # display 'text' and use table field 'field'
            array(
                'field' => 'edit',
                'text' => $LANG_ADMIN['edit'],
                'sort' => false,
                'align' => 'center'
            ),
            array(
                'field' => 'subscriber',
                'text' => $LANG_SUBSCR['subscriber'],
                'sort' => false,
            ),
            array(
                'field' => 'plan',
                'text' => $LANG_SUBSCR['plan'],
                'sort' => true,
            ),
            array(
                'field' => 'expiration',
                'text' => $LANG_SUBSCR['expires'],
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'field' => 'status',
                'text' => $LANG_SUBSCR['status'],
                'sort' => true,
                'align' => 'center',
            ),
        );

        $defsort_arr = array('field' => 'expiration', 'direction' => 'desc');
        $title = $LANG_SUBSCR['admin_hdr'];
        if (!empty($item_id)) {
            $title .= " :: $item_id";
            $item_query = " s.item_id = '".DB_escapeString($item_id)."' ";
        } else {
            $title  .= " :: All";
            $item_query = ' 1=1 ';
        }

        $retval .= COM_startBlock(
            $title, '',
            COM_getBlockTemplate('_admin_block', 'header')
        );
        $retval .= Menu::Admin('subscriptions');
        $retval .= COM_createLink(
            $LANG_SUBSCR['new_subscription'],
            SUBSCR_ADMIN_URL . '/index.php?editsubscrip=x',
            array(
                'class' => 'uk-button uk-button-success',
                'style' => 'float:left',
            )
        );

        $text_arr = array(
            'has_extras' => true,
            'form_url' => SUBSCR_ADMIN_URL .
                '/index.php?subscriptions='.$item_id,
        );

        $options = array('chkdelete' => 'true', 'chkfield' => 'id',
            'chkactions' => '<input name="deletebutton" type="image" src="'
                . $_CONF['layout_url'] . '/images/admin/delete.' . $_IMAGE_TYPE
                . '" style="vertical-align:text-bottom;" title="' . $LANG01[124]
                . '" class="tooltip"'
                . ' data-uk-tooltip="{pos:\'top-left\'}"'
                . ' onclick="return confirm(\'' . $LANG01[125] . '\');"'
                . '/>&nbsp;' . $LANG_ADMIN['delete'] . '&nbsp;&nbsp;' .

                '<input name="renewbutton" type="image" src="'
                . SUBSCR_URL . '/images/renew.png'
                . '" style="vertical-align:text-bottom;" title="' . $LANG_SUBSCR['renew_all']
                . '" class="tooltip"'
                . ' data-uk-tooltip="{pos:\'top-left\'}"'
                . ' onclick="return confirm(\'' . $LANG_SUBSCR['confirm_renew']
                . '\');"'
                . '/>&nbsp;' . $LANG_SUBSCR['renew'],
        );

        $exp_query = ' AND s.status IN (' . self::STATUS_ENABLED;
        if (isset($_POST['showexp'])) {
          $frmchk = 'checked="checked"';
          $exp_query .= ','.  self::STATUS_EXPIRED;;
        } else {
            $frmchk = '';
        }

        if (isset($_POST['showcan'])) {
            $canfrmchk = 'checked="checked"';
            $exp_query .= ',' . self::STATUS_CANCELED;;
        } else {
            $canfrmchk = '';
        }
        $exp_query .= ')';

        $query_arr = array('table' => 'subscr_subscriptions',
            'sql' => "SELECT s.*, p.item_id as plan, u.username, u.fullname
                FROM {$_TABLES['subscr_subscriptions']} s
                LEFT JOIN {$_TABLES['subscr_products']} p
                    ON s.item_id = p.item_id
                LEFT JOIN {$_TABLES['users']} u
                    ON s.uid = u.uid
                WHERE $item_query
                $exp_query",
            'query_fields' => array('username', 'fullname',),
            'default_filter' => '',
        );
        //echo $query_arr['sql'];die;

        $plans = $LANG_SUBSCR['plan'] .
            ': <select name="item_id" onchange=\'window.location.href="' .
                SUBSCR_ADMIN_URL . '/index.php?subscriptions="+this.value\'><option value="0">' . $LANG_SUBSCR['all_plans'] .
            '</option>' .
            COM_optionList($_TABLES['subscr_products'], "item_id,item_id", $item_id) .
            '</select>';
        $filter = $plans .
            '&nbsp;&nbsp;<input type="checkbox" name="showexp" ' . $frmchk .
            ' onclick="javascript:submit();"> ' . $LANG_SUBSCR['show_exp'] . '?'.
            '&nbsp;&nbsp;<input type="checkbox" name="showcan" ' . $canfrmchk .
            ' onclick="javascript:submit();"> ' . $LANG_SUBSCR['show_can'] . '? <br />';
        $form_arr = array(
        //    'top' => '<input type="checkbox" name="showexp"> Show expired?'
        );
        $retval .= ADMIN_list(
            'subscription',
            array(__CLASS__, 'getAdminListField'),
            $header_arr,
            $text_arr, $query_arr, $defsort_arr, $filter, '',
            $options, $form_arr
        );
        $retval .= COM_endBlock();
        return $retval;
    }


    /**
     * Get a single field for the Subscription admin list.
     *
     * @param   string  $fieldname  Name of field
     * @param   mixed   $fieldvalue Value of field
     * @param   array   $A          Array of all fields
     * @param   array   $icon_arr   Array of system icons
     * @return  string              HTML content for field display
     */
    public static function getAdminListField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $LANG_ACCESS, $LANG_SUBSCR, $_CONF_SUBSCR;

        $retval = '';

        switch($fieldname) {
        case 'edit':
            $retval .= COM_createLink(
                '<i class="uk-icon uk-icon-edit"></i>',
                SUBSCR_ADMIN_URL . '/index.php?editsubscrip=x&amp;sub_id=' . $A['id'],
                array(
                    'class' => 'tooltip',
                    'title' => $LANG_SUBSCR['edit'],
                )
            );
            break;

        case 'uid':
            $retval = COM_createLink(
                $fieldvalue,
                $_CONF['site_url'] . '/users.php?mode=profile&uid=' . $fieldvalue
            );
            break;

        case 'subscriber':
            $retval = COM_createLink(
                COM_getDisplayName($A['uid']),
                $_CONF['site_url'] . '/users.php?mode=profile&uid=' .$A['uid']
            );
            break;

        case 'expiration':
            if ($A['status'] == self::STATUS_EXPIRED) {
                $retval = '<span class="expired">' . $fieldvalue . '</span>';
            } elseif ($A['status'] == self::STATUS_CANCELED) {
                $retval = '<span class="canceled">' . $fieldvalue . '</span>';
            } elseif ($fieldvalue < date('Y-m-d')) {
                $retval .= '<span class="ingrace">' . $fieldvalue . '</span>';
            } else {
                $retval = $fieldvalue;
            }
            break;

        case 'status':
            $retval = $LANG_SUBSCR['status_txt'][$A['status']];
            break;

        default:
            $retval = $fieldvalue;
            break;
        }

        return $retval;
    }


    /**
     * Loads the requested language array to send email in the recipient's language.
     * If $requested is an array, the first valid language file is loaded.
     * If not, the $requested language file is loaded.
     * If $requested doesn't refer to a vailid language, then $_CONF['language']
     * is assumed.
     *
     * After loading the base language file, the same filename is loaded from
     * language/custom, if available. The admin can override language strings
     * by creating a language file in that directory.
     *
     * @param   mixed   $requested  A single or array of language strings
     * @return  array       $LANG_SUBSCR, the global language array for the plugin
     */
    public static function loadLanguage($requested)
    {
        global $_CONF;

        // Add the requested language, which may be an array or
        // a single item.
        if (is_array($requested)) {
            $languages = $requested;
        } else {
            // If no language requested, load the site/user default
            $languages = array($requested);
        }

        // Add the site language as a failsafe
        $languages[] = $_CONF['language'];

        // Final failsafe, include "english.php" which is known to exist
        $languages[] = 'english_utf-8';

        // Search the array for desired language files, in order.
        $langpath = __DIR__ . '/../language';
        foreach ($languages as $language) {
            if (file_exists("$langpath/$language.php")) {
                include "$langpath/$language.php";
                // Include admin-supplied overrides, if any.
                if (file_exists("$langpath/custom/$language.php")) {
                    include "$langpath/custom/$language.php";
                }
                break;
            }
        }
        return $LANG_SUBSCR;
    }

}
