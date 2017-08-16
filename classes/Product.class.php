<?php
/**
*   Class to manage subscription items
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
*   Class for subscription product items
*
*   @package subscription
*/
class Product
{
    /** Property fields.  Accessed via __set() and __get()
    *   @var array */
    private $properties = array();

    /** Indicate whether the current user is an administrator
    *   @var boolean */
    private $isAdmin = false;

    /** Should permissions be checked?
    *   Normally yes, but if the product is instantiated by an IPN message,
    *   then checking permissions would break the process.
    *   Public to be called from the Subscription class
    *   @var boolean */
    public $checkPerms = true;

    /** Indicator that this is a new product vs. editing an existing one.
    *   @var boolean */
    private $isNew;

    /** Array of error messages
     *  @var array */
    public $Errors = array();

    /**
    *   Array form of pricing options
    */
    public $pricing = array();

    /**
    *   Constructor.
    *   Reads in the specified class, if $id is set.  If $id is zero,
    *   then a new entry is being created.
    *
    *   @param integer  $id     Optional product ID
    */
    public function __construct($id = '')
    {
        global $_CONF_SUBSCR, $LANG_SUBSCR;

        $this->isNew = true;
        $this->isAdmin = SEC_hasRights('subscription.admin') ? 1 : 0;
        $this->item_id = $id;

        if ($this->item_id != '') {
            if (!$this->Read($this->item_id)) {
                $this->item_id = '';
            }
        } else {
            $this->short_description = '';
            $this->description = '';
            $this->price = 0;
            $this->upg_price = 0;
            $this->upg_extend_exp = 0;
            $this->duration = 0;
            $this->duration_type = 'month';
            $this->expiration =  NULL;
            $this->enabled = $_CONF_SUBSCR['enabled'];
            $this->show_in_block = $_CONF_SUBSCR['show_in_block'];
            $this->taxable = $_CONF_SUBSCR['taxable'];
            $this->at_registration = SUBSCR_REGISTER_NONE;
            $this->dt_add = time();
            $this->views = 0;
            $this->grace_days = $_CONF_SUBSCR['grace_days'];
            $this->early_renewal = $_CONF_SUBSCR['early_renewal'];
            $this->grp_access = 13;       // logged-in users
            $this->pricing = array();
        }

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
        case 'item_id':
        case 'upg_from':
            $this->properties[$var] = COM_sanitizeID($value, false);
            break;

        case 'views':
        case 'dt_add':
        case 'duration':
        case 'addgroup':
        case 'grace_days':
        case 'early_renewal':
        case 'at_registration':
        case 'trial_days':
        case 'grp_access':
        case 'prf_update':
            // Integer values
            $this->properties[$var] = (int)$value;
            break;

        case 'price':
        case 'upg_price':
            // Float values
            $this->properties[$var] = (float)$value;
            break;

        case 'buttons':
        case 'short_description':
        case 'description':
        case 'duration_type':
        case 'prf_type':
            // String values
            $this->properties[$var] = trim($value);
            break;

        case 'enabled':
        case 'show_in_block':
        case 'taxable':
        case 'upg_extend_exp':
            // Boolean values
            $this->properties[$var] = $value == 1 ? 1 : 0;
            break;

        /*case 'buttons':
            if (is_array($value)) {
                $this->properties['buttons'] = $value;
            } else {
                $value = unserialize($value);
                if (is_array($value)) {
                    $this->properties['buttons'] = $value;
                } else {
                    $this->properties['buttons'] = $this->btn_types;
                }
            }
            break;*/

        case 'expiration':          // Fixed expiration date, or NULL
            //if ($value != NULL) {
            if (!empty($value)) {
                $value = trim($value);
            }
            $this->properties['expiration'] = $value;
            break;

        default:
            // Undefined values (do nothing)
            break;
        }
    }


    /**
    *   Get the value of a property.
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
    *   Sets all variables to the matching values from $rows.
    *
    *   @param  array   $row        Array of values, from DB or $_POST
    *   @param  boolean $fromDB     True if read from DB, false if from $_POST
    */
    public function SetVars($row, $fromDB=false)
    {
        if (!is_array($row)) return;

        $this->item_id = $row['item_id'];
        $this->short_description = $row['short_description'];
        $this->description = $row['description'];
        $this->enabled = $row['enabled'];
        $this->show_in_block = $row['show_in_block'];
        $this->taxable = $row['taxable'];
        $this->at_registration = $row['at_registration'];
        $this->trial_days = $row['trial_days'];
        $this->price = $row['price'];
        $this->upg_price = $row['upg_price'];
        $this->upg_extend_exp = $row['upg_extend_exp'];
        $this->upg_from = $row['upg_from'];
        $this->duration = $row['duration'];
        $this->duration_type = $row['duration_type'];
        if ($this->duration_type == 'fixed') {
            $this->expiration = empty($row['expiration']) ?
                            NULL : $row['expiration'];
        } else {
            $this->expiration = NULL;
        }
        $this->prf_update = $row['prf_update'];
        $this->prf_type = $row['prf_type'];
        $this->dt_add = $row['dt_add'];
        $this->views = $row['views'];
        $this->addgroup = $row['addgroup'];
        $this->grace_days = $row['grace_days'];
        $this->early_renewal = $row['early_renewal'];
        $this->grp_access = $row['grp_access'];
        if ($fromDB) {
            $this->buttons = $row['buttons'];
            $this->pricing = @unserialize($row['pricing']);
        } else {
            $this->buttons = $this->btn_types;
            $this->pricing['base'] = (float)$row['price'];
            $disc_price = (float)$row['disc_price'];
            if ($disc_price > 0) {
                if (is_array($row['disc_price'])
                    && is_array($row['disc_from'])
                    && is_array($row['disc_to'])
                    ) {
                }
            }
        }
    }


    /**
    *   Read a specific record and populate the local values.
    *   Also sets the private $isNew value to false if the product is read
    *   successfully.
    *
    *   @param  integer $id Optional ID.  Current ID is used if zero.
    *   @return boolean     True if a record was read, False on failure
    */
    public function Read($id = '')
    {
        global $_TABLES;

        $id = COM_sanitizeID($id, false);
        if ($id == '') $id = $this->item_id;
        if ($id == '') {
            $this->error = 'Invalid ID in Read()';
            return false;
        }

        $sql = "SELECT * FROM {$_TABLES['subscr_products']}
               WHERE item_id='$id' ";
        //echo $sql;die;
        //COM_errorLog($sql);
        $result = DB_query($sql);
        if (!$result || DB_numRows($result) != 1) {
            return false;
        } else {
            $row = DB_fetchArray($result, false);
            $this->SetVars($row, true);
            $this->isNew = false;
            return true;
        }
    }


    /**
    *   Save the current values to the database.
    *   Appends error messages to the $Errors property.
    *
    *   @param  array   $A      Optional array of values from $_POST
    *   @return boolean         True if no errors, False otherwise
    */
    public function Save($A = '')
    {
        global $_TABLES;

        if (!$this->isNew) {
            // Save the original item ID by which this record was loaded
            // before any updates are done to it.
            $orig_item_id = $this->item_id;
        }

        if (is_array($A)) {
            // Should always be an array, e.g. $_POST
            $this->SetVars($A);
        }

        if ($this->item_id == '') {
            // Make sure there's a valid item_id
            $this->item_id = COM_makeSid();
        }

        $this->buttons = $this->btn_types;

        // Make sure the record has all necessary fields.
        if (!$this->isValidRecord()) {
            return false;
        }

        // Don't escape a NULL expiration or it ends up as '0000-00-00'
        $expiration = $this->expiration;
        if ($expiration !== NULL) {
            $expiration = "'" . DB_escapeString($expiration) . "'";
        } else {
            $expiration = 'NULL';
        }

        // Ensure prices are formatted for MySQL regardless of locale
        $price = number_format($this->price, 2, '.', '');
        $upg_price = number_format($this->upg_price, 2, '.', '');

        // Insert or update the record, as appropriate
        if ($this->isNew) {
            SUBSCR_debug('Preparing to save a new product.');
            $count_should_be = 0;   // item_id should not be in the DB
            $sql1 = "INSERT INTO {$_TABLES['subscr_products']} SET
                    item_id='{$this->item_id}', ";
            $sql3 = '';
        } else {
            SUBSCR_debug('Preparing to update product id ' . $this->item_id);
            $sql1 = "UPDATE {$_TABLES['subscr_products']} SET ";
            $count_should_be = 1;   // should be one existing record
            if ($this->item_id != $orig_item_id) {
                SUBSCR_debug("Updating from {$orig_item_id} to {$this->item_id}");
                $count_should_be = 0;   // When updating item_id should be absent
                $sql1 .= "item_id = '{$this->item_id}',";
            }
            $sql3 = " WHERE item_id='{$orig_item_id}'";
        }

        // Check that the item_id does not already exist, or only exists once
        // if updating the same ID
        $c = DB_count($_TABLES['subscr_products'], 'item_id', $this->item_id);
        if ($c > $count_should_be) {
            SUBSCR_debug("Item {$this->item_id} already exists, cannot add");
            $this->Errors[] = "Item {$this->item_id} already exists, cannot create";
            return false;
        }

        // If the item_id has changed, update all the current subscription
        // records with the new value. Do this first, before updating the
        // product, to avoid getting out of sync.
        if ($this->item_id != $orig_item_id) {
            $sql = "UPDATE {$_TABLES['subscr_subscriptions']}
                    SET item_id = '{$this->item_id}'
                    WHERE item_id = '$orig_item_id'";
            DB_query($sql);
            if (DB_error()) {
                $this->Errors[] = "Failed updating subscriptions to {$this->item_id}";
                return false;
            }
        }

        $sql2 = "short_description = '" .
                        DB_escapeString($this->short_description) . "',
                description = '" . DB_escapeString($this->description) . "',
                price = '$price',
                duration = '{$this->duration}',
                duration_type = '" . DB_escapeString($this->duration_type). "',
                expiration = $expiration,
                enabled = '{$this->enabled}',
                show_in_block = '{$this->show_in_block}',
                taxable = '{$this->taxable}',
                at_registration = '{$this->at_registration}',
                trial_days = '{$this->trial_days}',
                views = '{$this->views}',
                grace_days = '{$this->grace_days}',
                early_renewal = '{$this->early_renewal}',
                addgroup = '{$this->addgroup}',
                upg_from = '{$this->upg_from}',
                upg_price = '$upg_price',
                upg_extend_exp = '{$this->upg_extend_exp}',
                prf_update = '{$this->prf_update}',
                prf_type = '" . DB_escapeString($this->prf_type) . "',
                grp_access = '{$this->grp_access}',
                buttons = '{$this->buttons}'";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        SUBSCR_debug($sql);
        DB_query($sql);
        if (DB_error()) {
            $status = false;
        } else {
            $status = true;
        }

        SUBSCR_debug('Status of last update: ' . print_r($status,true));
        if (!$this->hasErrors()) {
            SUBSCR_debug('Update of product ' . $this->item_id .
                    ' succeeded.');
            return true;
        } else {
            SUBSCR_debug('Update of product ' . $this->item_id .
                    ' failed.');
            return false;
        }

    }


    /**
    *  Delete the current product record from the database.
    *
    *   @return boolean     True on success, False if item not valid
    */
    public function Delete()
    {
        global $_TABLES, $_CONF_SUBSCR;

        if ($this->item_id == '')
            return false;

        DB_delete($_TABLES['subscr_products'], 'item_id', $this->item_id);
        $this->item_id = '';
        return true;
    }


    /**
    *   Determines if the current record is valid.
    *   Error messages are added to the Errors array.  The array isn't cleared
    *   first, so existing errors will cause this function to return False.
    *
    *   @return boolean     True if ok, False if any test fails.
    */
    private function isValidRecord()
    {
        global $LANG_SUBSCR;

        // Check that basic required fields are filled in
        if ($this->short_description == '') {
            $this->Errors[] = $LANG_SUBSCR['frm_invalid'] . ': ' .$LANG_SUBSCR['short_description'];
        }
        if ($this->duration_type == 'fixed') {
            $exp = $this->expiration;
            if (empty($exp)) {
                $this->Errors[] = $LANG_SUBSCR['frm_invalid'] . ': ' . $LANG_SUBSCR['expiration'];
            }
        } elseif ($this->duration < 1) {
            $this->Errors[] = $LANG_SUBSCR['frm_invalid'] . ': ' . $LANG_SUBSCR['expiration'];
        }

        if ($this->hasErrors()) {
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
    public function Edit($id = '')
    {
        global $_TABLES, $_CONF, $_CONF_SUBSCR, $LANG_SUBSCR,
                $LANG24, $LANG_postmodes, $_SYSTEM;

        $id = COM_sanitizeID($id, false);
        if ($id != '') {
            // If an id is passed in, then read that record
            if (!$this->Read($id)) {
                return SUBSCR_errorMessage($LANG_SUBSCR['invalid_product_id'], 'info');
            }
        }
        $id = $this->item_id;
        $action_url = SUBSCR_ADMIN_URL . '/index.php';

        $T = new \Template(SUBSCR_PI_PATH . '/templates');
        switch ($_SYSTEM['framework']) {
        case 'uikit':
            $T->set_file(array('product' => "product_form.uikit.thtml"));
            break;
        default:
            $T->set_file(array('product' => "product_form.thtml"));
            break;
        }

        // Set up the wysiwyg editor, if available
        switch (PLG_getEditorType()) {
        case 'ckeditor':
            $T->set_var('show_htmleditor', true);
            PLG_requestEditor('subscription','subscr_entry','ckeditor_subscription.thtml');
            PLG_templateSetVars('subscr_entry', $T);
            break;
        case 'tinymce' :
            $T->set_var('show_htmleditor',true);
            PLG_requestEditor('paypal','subscr_entry','tinymce_subscription.thtml');
            PLG_templateSetVars('subscr_entry', $T);
            break;
        default :
            // don't support others right now
            $T->set_var('show_htmleditor', false);
            break;
        }

        if ($id != '') {
            $retval = COM_startBlock($LANG_SUBSCR['edit'] . ': ' . $this->item_id);

        } else {
            $retval = COM_startBlock($LANG_SUBSCR['new_product']);

        }

        /*if (function_exists('USES_profile_functions')) {
            $T->set_var('profile_enabled', 'true');
        }*/
        $this->prf_update = false;

        $T->set_var(array(
            'item_id'   => $id,
            'mootools'  => $_SYSTEM['disable_mootools'] ? '' : 'true',
            'short_description'   =>
                            htmlspecialchars($this->short_description),
            'description'   => htmlspecialchars($this->description),
            'price'         => sprintf('%.2f', $this->price),
            'duration'      => $this->duration,
            'grace_days'    => $this->grace_days,
            'early_renewal' => $this->early_renewal,
            'pi_admin_url'  => SUBSCR_ADMIN_URL,
            'pi_url'        => SUBSCR_URL,
            'doc_url'       => SUBSCR_getDocURL('product_form.html',
                                            $_CONF['language']),
            'ena_chk'       => $this->enabled == 1 ?
                                    ' checked="checked"' : '',
            'block_chk'     => $this->show_in_block == 1 ?
                                    ' checked="checked"' : '',
            'taxable_chk'   => $this->taxable == 1 ?
                                    ' checked="checked"' : '',
            'sel_' . $this->duration_type => ' selected="selected"',
            'expiration'    => $this->expiration,
            'addgroup_sel'  => COM_optionList($_TABLES['groups'],
                            'grp_id,grp_name', $this->addgroup, 1, 'grp_id <> 1'),
            'dur_type'      => $this->duration_type,
            'upg_no_selection' => $this->upg_from == '' ?
                        'selected="selected"' : '',
            'upg_from_sel'  => COM_optionList($_TABLES['subscr_products'],
                        'item_id,item_id', $this->upg_from, 1,
                        "item_id <> '{$this->item_id}'" ),
            'upg_ext_chk' => $this->upg_extend_exp == 1 ?
                        'checked="checked"' : '',
            'upg_from' => $this->upg_from,
            'upg_price' => sprintf('%.2f', $this->upg_price),
            'prf_upd_chk' . $this->prf_update  => 'checked="checked"',
            'prf_type' => $this->prf_type,
            'group_options' => COM_optionList($_TABLES['groups'],
                                'grp_id,grp_name', $this->grp_access, 1, 'grp_id <> 1'),
            'iconset' => $_CONF_SUBSCR['_iconset'],
        ) );

        $trial_days = '';
        switch ($this->at_registration) {
        case SUBSCR_REGISTER_AUTO:
            $reg_radio = 'register_auto_sel';
            break;
        case SUBSCR_REGISTER_OPTIONAL:
            $$reg_radio = 'register_opt_sel';
            break;
        case SUBSCR_REGISTER_TRIAL:
            $reg_radio = 'register_trial_sel';
            $trial_days = $this->trial_days;
            break;
        case SUBSCR_REGISTER_NONE:
        default:
            $reg_radio = 'register_none_sel';
            break;
        }
        $T->set_var(array(
            $reg_radio      => 'checked="checked"',
            'trial_days'    => $trial_days,
        ) );

        if (!$this->isUsed()) {
            $T->set_var('candelete', 'true');
        }

        $retval .= $T->parse('output', 'product');
        $retval .= COM_endBlock();
        return $retval;

    }   // function Edit()


    /**
    *   Set a boolean field to the specified value.
    *
    *   @param  integer $id ID number of element to modify
    *   @param  integer $value New value to set
    *   @return         New value, or old value upon failure
    */
    private static function _toggle($oldvalue, $varname, $id)
    {
        global $_TABLES;

        // Determing the new value (opposite the old)
        $newvalue = $oldvalue == 1 ? 0 : 1;

        $sql = "UPDATE {$_TABLES['subscr_products']}
                SET $varname=$newvalue
                WHERE item_id='" . DB_escapeString($id) . "'";
        //echo $sql;die;
        DB_query($sql);

        return DB_error() ? $oldvalue : $newvalue;
    }


    /**
    *   Display the product detail page.
    *
    *   @return string      HTML for product detail
    */
    public function Detail()
    {
        global $_TABLES, $_CONF, $_USER, $_CONF_SUBSCR, $LANG_SUBSCR;

        $status = LGLIB_invokeService('paypal', 'getCurrency', array(),
            $output, $svc_msg);
        if ($status == PLG_RET_OK) {
            $currency = $output;
        } else {
            $currency = 'USD';
        }

        $buttons = '';

        // Create product template
        $T = new \Template(SUBSCR_PI_PATH . '/templates');
        $T->set_file(array(
            'detail'  => 'product_detail.thtml',
        ));

        // Check the expiration for the current user
        if (!COM_isAnonUser()) {
            $sql = "SELECT expiration,
                UNIX_TIMESTAMP(expiration) as exp_date,
                UNIX_TIMESTAMP(expiration - INTERVAL
                        {$_CONF_SUBSCR['early_renewal']} DAY) AS early_renewal,
                UNIX_TIMESTAMP(expiration + INTERVAL
                        {$_CONF_SUBSCR['grace_days']} DAY) AS late_renewal
                FROM {$_TABLES['subscr_subscriptions']}
                WHERE item_id='" . $this->item_id . "'
                AND uid='{$_USER['uid']}'";
            $A = DB_fetchArray(DB_query($sql), false);
            if (!empty($A)) {
                $dt = new \Date($A['exp_date'], $_CONF['timezone']);
                $T->set_var('exp_date', $dt->Format($_CONF['shortdate']));
                $tm = time();
                if ($A['early_renewal'] < $tm ||
                    ($A['late_renewal'] > $tm && $A['exp_date'] <= $tm)) {
                    $T->set_var('renew_now', 'true');
                }
            }
            if (!$this->expiration || $this->expiration != $A['expiration']) {
                $buttons = $this->MakeButton();
            }
        }

        $T->set_var(array(
                'pi_url'        => SUBSCR_URL,
                'user_id'       => $_USER['uid'],
                'item_id'       => $this->item_id,
                'short_description'   => PLG_replacetags($this->short_description),
                'description'   => PLG_replacetags($this->description),
                'price'         => COM_numberFormat($this->price, 2),
                'encrypted'     => '',
                'currency'      => $currency,
                'purchase_btn'  => $buttons,
                'duration'      => $this->duration,
                'duration_type' => $LANG_SUBSCR[$this->duration_type],
                'expiration'    => $this->expiration,
        ) );

        $T->parse('output', 'detail');
        return $T->finish($T->get_var('output', 'detail'));
    }


    /**
    *   Sets the "enabled" field to the specified value.
    *
    *   @uses   _toggle()
    *   @param  integer $id ID number of element to modify
    *   @param  integer $value New value to set
    *   @return         New value, or old value upon failure
    */
    public static function toggleEnabled($oldvalue, $id)
    {
        $oldvalue = $oldvalue == 1 ? 1 : 0;
        $id = COM_sanitizeID($id);
        return self::_toggle($oldvalue, 'enabled', $id);
    }


    /**
    *   Determine if this product is mentioned in any purchase records.
    *   Typically used to prevent deletion of product records that have
    *   dependencies.
    *
    *   @return boolean     True if used, False if not
    */
    public function isUsed($id = '')
    {
        global $_TABLES;

        if ($id == '') {
            if (is_object($this)) {
                $id = $this->item_id;
            } else {
                return;
            }
        } else {
            $id = COM_sanitizeID($id, false);
        }

        if (DB_count($_TABLES['subscr_subscriptions'], 'item_id', $id) > 0) {
            return true;
        } else {
            return false;
        }
    }


    /**
    *   Create a purchase-now button.
    *
    *   This plugin only uses one type of button, so that's all that we return.
    *
    *   @return string      Button code
    */
    public function MakeButton($btn_type = '')
    {
        global $_CONF, $_CONF_DON, $_USER;

        $retval = '';

        if (!$this->canBuy()) {
            return $retval;
        }

        switch ($btn_type) {
        case 'cart':
        case 'add_cart':
        case 'addcart':
            $add_cart = true;
            break;
        case 'pay_now':
        case 'paynow':
            $btn_type = 'pay_now';
            $add_cart = false;
        case 'buy_now':
        case 'buynow':
            $btn_type = 'buy_now';
            $add_cart = false;
            break;
        default:
            $btn_type = 'pay_now';
            $add_cart = true;
        }

        if (SUBSCR_PAYPAL_ENABLED) {
            $vars = array(
                'item_number' => 'subscription:' . $this->item_id,
                'item_name' => $this->item_id,
                'short_description' => $this->short_description,
                'amount' => sprintf("%5.2f", (float)$this->price),
                'no_shipping' => 1,
                'taxable' => $this->taxable,
                'btn_type' => $btn_type,
                'quantity' => 1,
                'add_cart' => $add_cart,
                'unique' => true,
            );
            $status = LGLIB_invokeService('paypal', 'genButton', $vars,
                    $output, $svc_msg);
            if ($status == PLG_RET_OK && is_array($output)) {
                foreach ($output as $button) {
                    $retval .= $button . '<br />';
                }
            }
        }
        return $retval;
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


    /**
    *   Check if this item has any error messages
    *
    *   @return boolean     True if Errors[] is not empty, false if it is.
    */
    public function hasErrors()
    {
        return (!empty($this->Errors));
    }


    /**
    *   Determine if the current user can purchase this item.
    *   Also ensures that the current object is a valid item.
    *
    *   @return boolean     True if allowed, False if not
    */
    public function canBuy()
    {
        global $_GROUPS, $_USER, $_CONF;

        $retval = false;
        if ($this->item_id != '') {
            if (in_array($this->grp_access, $_GROUPS)) {
                $retval = true;
            }
            USES_subscription_class_subscription();
            $mySubs = Subscription::getSubscriptions($_USER['uid']);
            if (isset($mySubs[$this->item_id])) {
                $d = new \Date($mySubs[$this->item_id]->expiration);
                $exp_ts = $d->toUnix();
                $exp_format = $d->format($_CONF['shortdate']);
                 if ($this->early_renewal > 0) {
                    $renew_ts = $exp_ts - ($this->early_renewal * 86400);
                    if ($renew_ts > date('U'))
                        $retval = false;
                }
            }
        }
        return $retval;
    }


    /**
    *   Update the Profile plugin data with the membership type and expiration.
    *
    *   @deprecated 0.2.0
    *   @param  string  $newdate    Subscription expiration date
    *   @param  integer $uid        User ID
    *   @return integer             Result from LGLIB_invokeService()
    */
    public function DEPRECATED_updateProfile($newdate, $uid)
    {
        $args = array(
            'sys_expires'       => $newdate,
            'sys_membertype'    => $this->prf_type,
            // not used, may be implemented later in the Profile plugin...
            'children'          => $this->prf_update > 1 ? true : false,
        );

        // Based on the value of prf_update, update the profiles for related
        // accounts.  Each case falls through to the next; e.g. a value of 3
        // also implements 2 and 1.
        switch ($this->prf_update) {
        case 3:         // Find siblings (children of our parent)
            $status = LGLIB_invokeService('profile', 'getParentAccount',
                array('uid' => $uid), $output, $svc_msg);
            if ($status == PLG_RET_OK && $output > 0) {
                $args['uid'] = $output;
                $status = LGLIB_invokeService('profile', 'setSysValues',
                            $args, $output, $svc_msg);
                $status = LGLIB_invokeService('profile', 'getChildAccounts',
                    array('uid' => $output), $output, $svc_msg);
                if ($status == PLG_RET_OK && is_array($output)) {
                    foreach ($output as $user_id) {
                        $args['uid'] = $user_id;
                        $status = LGLIB_invokeService('profile', 'setSysValues',
                            $args, $output, $svc_msg);
                        if ($status != PLG_RET_OK) {
                            COM_errorLog("Error updating profile for $user_id");
                        }
                    }
                }
            }

        case 2:         // Update children of this account
            // Update all the children
            $status = LGLIB_invokeService('profile', 'getChildAccounts',
                array('uid' => $uid), $output, $svc_msg);
            if ($status == PLG_RET_OK) {
                foreach ($output as $user_id) {
                    $args['uid'] = $user_id;
                    $status = LGLIB_invokeService('profile', 'setSysValues',
                        $args, $output, $svc_msg);
                    if ($status != PLG_RET_OK) {
                        COM_errorLog("Error updating profile for user $user_id");
                        break;
                    }
                }
            }

        case 1:
            // Finally, update this account
            $args['uid'] = $uid;
            return LGLIB_invokeService('profile', 'setSysValues', $args,
                        $output, $svc_msg);
        }
    }

}   // class Product

?>
