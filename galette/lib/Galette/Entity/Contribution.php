<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contribution class for galette
 *
 * PHP version 5
 *
 * Copyright © 2010-2013 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2010-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2010-03-11
 */

namespace Galette\Entity;

use Analog\Analog;
use Galette\IO\ExternalScript;
use Galette\IO\PdfContribution;

/**
 * Contribution class for galette
 *
 * @category  Entity
 * @name      Contribution
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2010 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2010-03-11
 */
class Contribution
{
    const TABLE = 'cotisations';
    const PK = 'id_cotis';

    const PAYMENT_OTHER = 0;
    const PAYMENT_CASH = 1;
    const PAYMENT_CREDITCARD = 2;
    const PAYMENT_CHECK = 3;
    const PAYMENT_TRANSFER = 4;
    const PAYMENT_PAYPAL = 5;

    private $_id;
    private $_date;
    private $_member;
    private $_type;
    private $_amount;
    private $_payment_type;
    private $_orig_amount;
    private $_info;
    private $_begin_date;
    private $_end_date;
    private $_transaction = null;
    private $_is_cotis;
    private $_extension;

    //fields list and their translation
    private $_fields;

    /**
    * Default constructor
    *
    * @param null|int|ResultSet $args Either a ResultSet row to load
    *                                   a specific contribution, or an type id
    *                                   to just instanciate object
    */
    public function __construct($args = null)
    {
        /*
        * Fields configuration. Each field is an array and must reflect:
        * array(
        *   (string)label,
        *   (string) propname
        * )
        *
        * I'd prefer a static private variable for this...
        * But call to the _T function does not seem to be allowed there :/
        */
        $this->_fields = array(
            'id_cotis'            => array(
                'label'    => null, //not a field in the form
                'propname' => 'id'
            ),
            Adherent::PK          => array(
                'label'    => _T("Contributor:"),
                'propname' => 'member'
            ),
            ContributionsTypes::PK => array(
                'label'    => _T("Contribution type:"),
                'propname' => 'type'
            ),
            'montant_cotis'       => array(
                'label'    => _T("Amount:"),
                'propname' => 'amount'
            ),
            'type_paiement_cotis' => array(
                'label'    => _T("Payment type:"),
                'propname' => 'payment_type'
            ),
            'info_cotis'          => array(
                'label'    => _T("Comments:"),
                'propname' => 'info'
            ),
            'date_enreg'          => array(
                'label'    => null, //not a field in the form
                'propname' => 'date'
            ),
            'date_debut_cotis'    => array(
                'label'    => _T("Date of contribution:"),
                'cotlabel' => _T("Start date of membership:"), //if contribution is a cotisation, label differs
                'propname' => 'begin_date'
            ),
            'date_fin_cotis'      => array(
                'label'    => _T("End date of membership:"),
                'propname' => 'end_date'
            ),
            Transaction::PK       => array(
                'label'    => null, //not a field in the form
                'propname' => 'transaction'
            ),
            //this one is not really a field, but is required in some cases...
            //adding it here make simplier to check required fields
            'duree_mois_cotis'    => array(
                'label'    => _T("Membership extension:"),
                'propname' => 'extension'
            )
        );
        if ( is_int($args) ) {
            $this->load($args);
        } else if ( is_array($args) ) {
            $this->_date = date("Y-m-d");
            if ( isset($args['adh']) && $args['adh'] != '' ) {
                $this->_member = (int)$args['adh'];
            }
            $this->_type = new ContributionsTypes((int)$args['type']);
            $this->_is_cotis = (bool)$this->_type->extension;
            //calculate begin date for cotisation
            $this->_begin_date = $this->_date;
            if ( $this->_is_cotis ) {
                $curend = self::getDueDate($args['adh']);
                if ($curend != '') {
                    $dend = new \DateTime($curend);
                    $now = date('Y-m-d');
                    $dnow = new \DateTime($now);
                    if ( $dend < $dnow ) {
                        // Member didn't renew on time
                        $this->_begin_date = $now;
                    } else {
                        $this->_begin_date = $curend;
                    }
                }
                if ( isset($args['ext']) ) {
                    $this->_extension = $args['ext'];
                }
                $this->_retrieveEndDate();
            }
            if ( isset($args['trans']) ) {
                $this->_transaction = new Transaction((int)$args['trans']);
                if ( !isset($this->_member) ) {
                    $this->_member = (int)$this->_transaction->member;
                }
                $this->_amount = $this->_transaction->getMissingAmount();
            }
            if ( isset($args['payment_type']) ) {
                $this->_payment_type = $args['payment_type'];
            }
        } elseif ( is_object($args) ) {
            $this->_loadFromRS($args);
        }
    }

    /**
     * Sets end contribution date
     *
     * @return void
     */
    private function _retrieveEndDate()
    {
        global $preferences;

        $bdate = new \DateTime($this->_begin_date);
        if ( $preferences->pref_beg_membership != '' ) {
            //case beginning of membership
            list($j, $m) = explode('/', $preferences->pref_beg_membership);
            $edate = new \DateTime($bdate->format('Y') . '-' . $m . '-' . $j);
            while ( $edate <= $bdate ) {
                $edate->modify('+1 year');
            }
            $this->_end_date = $edate->format('Y-m-d');
        } else if ( $preferences->pref_membership_ext != '' ) {
            //case membership extension
            $dext = new \DateInterval('P' . $this->_extension . 'M');
            $edate = $bdate->add($dext);
            $this->_end_date = $edate->format('Y-m-d');
        }
    }

    /**
    * Loads a contribution from its id
    *
    * @param int $id the identifiant for the contribution to load
    *
    * @return bool true if query succeed, false otherwise
    */
    public function load($id)
    {
        global $zdb, $login;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . self::TABLE)
                ->where(self::PK . ' = ?', $id);
            //restrict query on current member id if he's not admin nor staff member
            if ( !$login->isAdmin() && !$login->isStaff() ) {
                $select->where(Adherent::PK . ' = ?', $login->id);
            }
            $row = $select->query()->fetch();
            if ( $row !== false ) {
                $this->_loadFromRS($row);
                return true;
            } else {
                throw new \Exception(
                    'No contribution #' . $id . ' (user ' .$login->id . ')'
                );
            }
        } catch (\Exception $e) {
            /** FIXME */
            Analog::log(
                'An error occured attempting to load contribution #' . $id .
                $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
    * Populate object from a resultset row
    *
    * @param ResultSet $r the resultset row
    *
    * @return void
    */
    private function _loadFromRS($r)
    {
        $pk = self::PK;
        $this->_id = (int)$r->$pk;
        $this->_date = $r->date_enreg;
        $this->_amount = $r->montant_cotis;
        //save original amount, we need it for transactions parts calulations
        $this->_orig_amount = $r->montant_cotis;
        $this->_payment_type = $r->type_paiement_cotis;
        $this->_info = $r->info_cotis;
        $this->_begin_date = $r->date_debut_cotis;
        $enddate = $r->date_fin_cotis;
        //do not work with knows bad dates...
        //the one with BC comes from 0.63/pgsl demo... Why the hell a so
        //strange date? dont know :(
        if ( $enddate !== '0000-00-00'
            && $enddate !== '1901-01-01'
            && $enddate !== '0001-01-01 BC'
        ) {
            $this->_end_date = $r->date_fin_cotis;
        }
        $adhpk = Adherent::PK;
        $this->_member = (int)$r->$adhpk;

        $transpk = Transaction::PK;
        if ( $r->$transpk != '' ) {
            $this->_transaction = new Transaction((int)$r->$transpk);
        }

        $this->_type = new ContributionsTypes((int)$r->id_type_cotis);
        if ( $this->_type->extension == 1 ) {
            $this->_is_cotis = true;
        } else {
            $this->_is_cotis = false;
        }
    }

    /**
     * Check posted values validity
     *
     * @param array $values   All values to check, basically the $_POST array
     *                        after sending the form
     * @param array $required Array of required fields
     * @param array $disabled Array of disabled fields
     *
     * @return true|array
     */
    public function check($values, $required, $disabled)
    {
        global $zdb;
        $errors = array();

        $fields = array_keys($this->_fields);
        foreach ( $fields as $key ) {
            //first of all, let's sanitize values
            $key = strtolower($key);
            $prop = '_' . $this->_fields[$key]['propname'];

            if ( isset($values[$key]) ) {
                $value = trim($values[$key]);
            } else {
                $value = '';
            }

            // if the field is enabled, check it
            if ( !isset($disabled[$key]) ) {
                // fill up the adherent structure
                //$this->$prop = stripslashes($value); //not relevant here!

                // now, check validity
                switch ( $key ) {
                // dates
                case 'date_enreg':
                case 'date_debut_cotis':
                case 'date_fin_cotis':
                    if ( $value != '' ) {
                        try {
                            $d = \DateTime::createFromFormat(_T("Y-m-d"), $value);
                            if ( $d === false ) {
                                throw new \Exception('Incorrect format');
                            }
                            $this->$prop = $d->format('Y-m-d');
                        } catch (\Exception $e) {
                            Analog::log(
                                'Wrong date format. field: ' . $key .
                                ', value: ' . $value . ', expected fmt: ' .
                                _T("Y-m-d") . ' | ' . $e->getMessage(),
                                Analog::INFO
                            );
                            $errors[] = str_replace(
                                array(
                                    '%date_format',
                                    '%field'
                                ),
                                array(
                                    _T("Y-m-d"),
                                    $this->_fields[$key]['label']
                                ),
                                _T("- Wrong date format (%date_format) for %field!")
                            );
                        }
                    }
                    break;
                case Adherent::PK:
                    if ( $value != '' ) {
                        $this->_member = $value;
                    }
                    break;
                case ContributionsTypes::PK:
                    if ( $value != '' ) {
                        $this->_type = new ContributionsTypes((int)$value);
                    }
                    break;
                case 'montant_cotis':
                    $this->_amount = $value;
                    $us_value = strtr($value, ',', '.');
                    if ( !is_numeric($value) ) {
                        $errors[] = _T("- The amount must be an integer!");
                    }
                    break;
                case 'type_paiement_cotis':
                    if ( $value == self::PAYMENT_OTHER
                        || $value == self::PAYMENT_CASH
                        || $value == self::PAYMENT_CREDITCARD
                        || $value == self::PAYMENT_CHECK
                        || $value == self::PAYMENT_TRANSFER
                        || $value == self::PAYMENT_PAYPAL
                    ) {
                        $this->_payment_type = $value;
                    } else {
                        $errors[] = _T("- Unknown payment type");
                    }
                    break;
                case 'info_cotis':
                    $this->_info = $value;
                    break;
                case Transaction::PK:
                    if ( $value != '' ) {
                        $this->_transaction = new Transaction((int)$value);
                    }
                    break;
                case 'duree_mois_cotis':
                    if ( $value != '' ) {
                        if ( !is_numeric($value) || $value<=0 ) {
                            $errors[] = _T("- The duration must be a positive integer!");
                        }
                        $this->$prop = $value;
                        $this->_retrieveEndDate();
                    }
                    break;
                }
            }
        }

        // missing required fields?
        while ( list($key, $val) = each($required) ) {
            if ( $val === 1) {
                $prop = '_' . $this->_fields[$key]['propname'];
                if ( !isset($disabled[$key])
                    && (!isset($this->$prop)
                    || (!is_object($this->$prop) && trim($this->$prop) == '')
                    || (is_object($this->$prop) && trim($this->$prop->id) == ''))
                ) {
                    $errors[] = _T("- Mandatory field empty: ") .
                    ' <a href="#' . $key . '">' . $this->getFieldName($key) .'</a>';
                }
            }
        }

        if ( $this->_transaction != null && $this->_amount != null) {
            $missing = $this->_transaction->getMissingAmount();
            //calculate new missing amount
            $missing = $missing + $this->_orig_amount - $this->_amount;
            if ( $missing < 0 ) {
                $errors[] = _T("- Sum of all contributions exceed corresponding transaction amount.");
            }
        }

        if ( count($errors) > 0 ) {
            Analog::log(
                'Some errors has been throwed attempting to edit/store a contribution' .
                print_r($errors, true),
                Analog::DEBUG
            );
            return $errors;
        } else {
            Analog::log(
                'Contribution checked successfully.',
                Analog::DEBUG
            );
            return true;
        }
    }

    /**
     * Check that membership fees does not overlap
     *
     * @return boolean|string True if all is ok, false if error,
     * error message if overlap
     */
    public function checkOverlap()
    {
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(
                array('c' => PREFIX_DB . self::TABLE),
                array('date_debut_cotis', 'date_fin_cotis')
            )->join(
                array('ct' => PREFIX_DB . ContributionsTypes::TABLE),
                'c.' . ContributionsTypes::PK . '=ct.' . ContributionsTypes::PK,
                array()
            )->where(Adherent::PK . ' = ?', $this->_member)
                ->where('cotis_extension = ?', (string)1)
                ->where(
                    '((' . $zdb->db->quoteInto('date_debut_cotis >= ?', $this->_begin_date) .
                    ' AND '. $zdb->db->quoteInto('date_debut_cotis < ?', $this->_end_date) .
                    ') OR (' . $zdb->db->quoteInto('date_fin_cotis > ?', $this->_begin_date) .
                    ' AND ' . $zdb->db->quoteInto('date_fin_cotis <= ?', $this->_end_date) . '))'
                );

            if ( $this->id != '' ) {
                $select->where(self::PK . ' != ?', $this->id);
            }

            $result = $select->query()->fetch();
            if ( $result !== false ) {
                $d = new \DateTime($result->date_debut_cotis);

                return _T("- Membership period overlaps period starting at ") .
                    $d->format(_T("Y-m-d"));
            }
            return true;
        } catch (\Exception $e) {
            /** FIXME */
            Analog::log(
                'An error occured checking overlaping fee. ' . $e->getMessage(),
                Analog::ERROR
            );
            Analog::log(
                'Query was: ' . $select->__toString(),
                Analog::DEBUG
            );
            return false;
        }
    }

    /**
     * Store the contribution
     *
     * @return boolean
     */
    public function store()
    {
        global $zdb, $hist;

        try {
            $zdb->db->beginTransaction();
            $values = array();
            $fields = self::getDbFields();
            /** FIXME: quote? */
            foreach ( $fields as $field ) {
                $prop = '_' . $this->_fields[$field]['propname'];
                switch ( $field ) {
                case ContributionsTypes::PK:
                case Transaction::PK:
                    if ( isset($this->$prop) ) {
                        $values[$field] = $this->$prop->id;
                    }
                    break;
                default:
                    $values[$field] = $this->$prop;
                    break;
                }
            }

            //no end date,, let's take database defaults
            if ( !$this->isCotis() && !$this->_end_date ) {
                unset($values['date_fin_cotis']);
            }

            if ( !isset($this->_id) || $this->_id == '') {
                //we're inserting a new contribution
                unset($values[self::PK]);
                $add = $zdb->db->insert(PREFIX_DB . self::TABLE, $values);
                if ( $add > 0) {
                    $this->_id = (int)$zdb->db->lastInsertId(
                        PREFIX_DB . self::TABLE,
                        'id'
                    );
                    // logging
                    $hist->add(
                        _T("Contribution added"),
                        Adherent::getSName($this->_member)
                    );
                } else {
                    $hist->add(_T("Fail to add new contribution."));
                    throw new \Exception(
                        'An error occured inserting new contribution!'
                    );
                }
            } else {
                //we're editing an existing contribution
                $edit = $zdb->db->update(
                    PREFIX_DB . self::TABLE,
                    $values,
                    self::PK . '=' . $this->_id
                );
                //edit == 0 does not mean there were an error, but that there
                //were nothing to change
                if ( $edit > 0 ) {
                    $hist->add(
                        _T("Contribution updated"),
                        Adherent::getSName($this->_member)
                    );
                } else if ($edit === false) {
                    throw new \Exception(
                        'An error occured updating contribution # ' . $this->_id . '!'
                    );
                }
            }
            //update deadline
            if ( $this->isCotis() ) {
                $deadline = $this->_updateDeadline();
                if ( $deadline !== true ) {
                    //if something went wrong, we rollback transaction
                    throw new \Exception('An error occured updating member\'s deadline');
                }
            }
            $zdb->db->commit();
            $this->_orig_amount = $this->_amount;
            return true;
        } catch (\Exception $e) {
            /** FIXME */
            $zdb->db->rollBack();
            Analog::log(
                'Something went wrong :\'( | ' . $e->getMessage() . "\n" .
                $e->getTraceAsString(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Update member dead line
     *
     * @return boolean
     */
    private function _updateDeadline()
    {
        global $zdb;

        try {
            $due_date = self::getDueDate($this->_member);

            if ( $due_date != '' ) {
                $date_fin_update = $due_date;
            } else {
                $date_fin_update = new \Zend_Db_Expr('NULL');
            }

            $edit = $zdb->db->update(
                PREFIX_DB . Adherent::TABLE,
                array('date_echeance' => $date_fin_update),
                Adherent::PK . '=' . $this->_member
            );
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'An error occured updating member ' . $this->_member .
                '\'s deadline |' .
                $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Remove contribution from database
     *
     * @param boolean $transaction Activate transaction mode (defaults to true)
     *
     * @return boolean
     */
    public function remove($transaction = true)
    {
        global $zdb;

        try {
            if ( $transaction ) {
                $zdb->db->beginTransaction();
            }
            $del = $zdb->db->delete(
                PREFIX_DB . self::TABLE,
                self::PK . ' = ' . $this->_id
            );
            if ( $del > 0 ) {
                $this->_updateDeadline();
            }
            if ( $transaction ) {
                $zdb->db->commit();
            }
            return true;
        } catch (\Exception $e) {
            /** FIXME */
            if ( $transaction ) {
                $zdb->db->rollBack();
            }
            Analog::log(
                'An error occured trying to remove contribution #' .
                $this->_id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Get field label
     *
     * @param string $field Field name
     *
     * @return string
     */
    public function getFieldName($field)
    {
        $label = $this->_fields[$field]['label'];
        if ( $this->isCotis() && $field == 'date_debut_cotis') {
            $label = $this->_fields[$field]['cotlabel'];
        }
        //remove trailing ':' and then nbsp (for french at least)
        $label = trim(trim($label, ':'), '&nbsp;');
        return $label;
    }

    /**
     * Retrieve fields from database
     *
     * @return array
     */
    public static function getDbFields()
    {
        global $zdb;
        return array_keys($zdb->db->describeTable(PREFIX_DB . self::TABLE));
    }

    /**
    * Get the relevant CSS class for current contribution
    *
    * @return string current contribution row class
    */
    public function getRowClass()
    {
        return ( $this->_end_date != $this->_begin_date && $this->_is_cotis) ?
            'cotis-normal' :
            'cotis-give';
    }

    /**
     * Retrieve member due date
     *
     * @param integer $member_id Member identifier
     *
     * @return date
     */
    public static function getDueDate($member_id)
    {
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(
                PREFIX_DB . self::TABLE,
                'MAX(date_fin_cotis)'
            )->where(Adherent::PK . ' = ?', $member_id);
            $due_date = $select->query()->fetchColumn();
            //avoid bad dates in postgres
            if ( $due_date == '0001-01-01 BC' ) {
                $due_date = '';
            }
            return $due_date;
        } catch (\Exception $e) {
            /** FIXME */
            Analog::log(
                'An error occured trying to retrieve member\'s due date',
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Detach a contribution from a transaction
     *
     * @param int $trans_id   Transaction identifier
     * @param int $contrib_id Contribution identifier
     *
     * @return boolean
     */
    public static function unsetTransactionPart($trans_id, $contrib_id)
    {
        global $zdb;

        try {
            //first, we check if contribution is part of transaction
            $c = new Contribution((int)$contrib_id);
            if ( $c->isTransactionPartOf($trans_id)) {
                $zdb->db->update(
                    PREFIX_DB . self::TABLE,
                    array(Transaction::PK => null),
                    self::PK . ' = ' . $contrib_id
                );
                return true;
            } else {
                Analog::log(
                    'Contribution #' . $contrib_id .
                    ' is not actually part of transaction #' . $trans_id,
                    Analog::WARNING
                );
                return false;
            }
        } catch (\Exception $e) {
            Analog::log(
                'Unable to detach contribution #' . $contrib_id .
                ' to transaction #' . $trans_id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Set a contribution as a transaction part
     *
     * @param int $trans_id   Transaction identifier
     * @param int $contrib_id Contribution identifier
     *
     * @return boolean
     */
    public static function setTransactionPart($trans_id, $contrib_id)
    {
        global $zdb;

        try {
            $zdb->db->update(
                PREFIX_DB . self::TABLE,
                array(Transaction::PK => $trans_id),
                self::PK . ' = ' . $contrib_id
            );
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'Unable to attach contribution #' . $contrib_id .
                ' to transaction #' . $trans_id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Is current contribution a cotisation
     *
     * @return boolean
     */
    public function isCotis()
    {
        return $this->_is_cotis;
    }

    /**
     * Is current contribution part of specified transaction
     *
     * @param int $id Transaction identifier
     *
     * @return boolean
     */
    public function isTransactionPartOf($id)
    {
        if ( $this->isTransactionPart() ) {
            return $id == $this->_transaction->id;
        } else {
            return false;
        }
    }

    /**
     * Is current contribution part of transaction
     *
     * @return boolean
     */
    public function isTransactionPart()
    {
        return $this->_transaction != null;
    }

    /**
     * Execute post contribution script
     *
     * @param ExternalScript $es     External script to execute
     * @param array          $extra  Extra informations on contribution
     *                                  Defaults to null
     * @param array          $pextra Extra information on payment
     *                                  Defaults to null
     *
     * @return mixed Script return value on success, values and script output on fail
     */
    public function executePostScript(ExternalScript $es,
        $extra = null, $pextra = null
    ) {
        global $zdb, $preferences;

        $payment = array(
            'type'  => $this->getPaymentType()
        );

        if ( $pextra !== null && is_array($pextra) ) {
            $payment = array_merge($payment, $pextra);
        }

        if ( !file_exists(GALETTE_CACHE_DIR . '/pdf_contribs') ) {
            @mkdir(GALETTE_CACHE_DIR . '/pdf_contribs');
        }

        $voucher_path = null;
        if ( $this->_id !== null ) {
            $voucher = new PdfContribution($this, $zdb, $preferences);
            $voucher->store(GALETTE_CACHE_DIR . '/pdf_contribs');
            $voucher_path = $voucher->getPath();
        }

        $contrib = array(
            'date'      => $this->_date,
            'type'      => $this->getRawType(),
            'amount'    => $this->amount,
            'voucher'   => $voucher_path,
            'category'  => array(
                'id'    => $this->type->id,
                'label' => $this->type->libelle
            ),
            'payment'   => $payment
        );

        if ( $this->_member !== null ) {
            $m = new Adherent((int)$this->_member);
            $member = array(
                'name'          => $m->sfullname,
                'email'         => $m->email,
                'organization'  => ($m->isCompany() ? 1 : 0),
                'status'        => array(
                    'id'    => $m->status,
                    'label' => $m->sstatus
                ),
                'country'       => $m->country
            );

            if ( $m->isCompany() ) {
                $member['organization_name'] = $m->company_name;
            }

            $contrib['member'] = $member;
        }

        if ( $extra !== null && is_array($extra) ) {
            $contrib = array_merge($contrib, $extra);
        }

        $res = $es->send($contrib);

        if ( $res !== true ) {
            Analog::log(
                'An error occured calling post contribution ' .
                "script:\n" . $es->getOutput(),
                Analog::ERROR
            );
            $res = _T("Contribution informations") . "\n";
            $res .= print_r($contrib, true);
            $res .= "\n\n" . _T("Script output") . "\n";
            $res .= $es->getOutput();
        }

        return $res;
    }
    /**
     * Get raw contribution type
     *
     * @return string
     */
    public function getRawType()
    {
        if ( $this->isCotis() ) {
            return 'membership';
        } else {
            return 'donation';
        }
    }

    /**
     * Get contribution type label
     *
     * @return string
     */
    public function getTypeLabel()
    {
        if ( $this->isCotis() ) {
            return _T("Membership");
        } else {
            return _T("Donation");
        }
    }

    /**
     * Get payent type label
     *
     * @return string
     */
    public function getPaymentType()
    {
        switch ( $this->payment_type ) {
        case Contribution::PAYMENT_CASH:
            return 'cash';
            break;
        case Contribution::PAYMENT_CREDITCARD:
            return 'credit_card';
            break;
        case Contribution::PAYMENT_CHECK:
            return 'check';
            break;
        case Contribution::PAYMENT_TRANSFER:
            return 'transfer';
            break;
        case Contribution::PAYMENT_PAYPAL:
            return 'paypal';
            break;
        case Contribution::PAYMENT_OTHER:
            return 'other';
            break;
        default:
            Analog::log(
                __METHOD__ . ' Unknonw payment type ' . $this->payment_type,
                Analog::ERROR
            );
            throw new \RuntimeException(
                'Unknonw payment type ' . $this->payment_type
            );
        }

    }

    /**
    * Global getter method
    *
    * @param string $name name of the property we want to retrive
    *
    * @return false|object the called property
    */
    public function __get($name)
    {

        $forbidden = array('is_cotis');
        $virtuals = array('duration', 'spayment_type', 'model', 'raw_date',
            'raw_begin_date', 'raw_end_date'
        );

        $rname = '_' . $name;
        if ( !in_array($name, $forbidden)
            && isset($this->$rname)
            || in_array($name, $virtuals)
        ) {
            switch($name) {
            case 'raw_date':
            case 'raw_begin_date':
            case 'raw_end_date':
                $rname = '_' . substr($name, 4);
                if ( $this->$rname != '' ) {
                    try {
                        $d = new \DateTime($this->$rname);
                        return $d;
                    } catch (\Exception $e) {
                        //oops, we've got a bad date :/
                        Analog::log(
                            'Bad date (' . $his->$rname . ') | ' .
                            $e->getMessage(),
                            Analog::INFO
                        );
                        throw $e;
                    }
                }
            case 'date':
            case 'begin_date':
            case 'end_date':
                if ( $this->$rname != '' ) {
                    try {
                        $d = new \DateTime($this->$rname);
                        return $d->format(_T("Y-m-d"));
                    } catch (\Exception $e) {
                        //oops, we've got a bad date :/
                        Analog::log(
                            'Bad date (' . $his->$rname . ') | ' .
                            $e->getMessage(),
                            Analog::INFO
                        );
                        return $this->$rname;
                    }
                }
                break;
            case 'duration':
                if ( $this->_is_cotis ) {
                    $date_end = new \DateTime($this->_end_date);
                    $date_start = new \DateTime($this->_begin_date);
                    $diff = $date_end->diff($date_start);
                    return $diff->format('%y') * 12 + $diff->format('%m');
                } else {
                    return '';
                }
                break;
            case 'spayment_type':
                switch ( $this->_payment_type ) {
                case self::PAYMENT_OTHER:
                    return _T("Other");
                    break;
                case self::PAYMENT_CASH:
                    return _T("Cash");
                    break;
                case self::PAYMENT_CREDITCARD:
                    return _T("Credit card");
                    break;
                case self::PAYMENT_CHECK:
                    return _T("Check");
                    break;
                case self::PAYMENT_TRANSFER:
                    return _T("Transfer");
                    break;
                case self::PAYMENT_PAYPAL:
                    return _T("Paypal");
                    break;
                default:
                    Analog::log(
                        'Unknown payment type ' . $this->_payment_type,
                        Analog::WARNING
                    );
                    return '-';
                    break;
                }
            case 'model':
                return ($this->isCotis()) ?
                    PdfModel::INVOICE_MODEL :
                    PdfModel::RECEIPT_MODEL;
                break;
            default:
                return $this->$rname;
                break;
            }
        } else {
            return false;
        }
    }

    /**
    * Global setter method
    *
    * @param string $name  name of the property we want to assign a value to
    * @param object $value a relevant value for the property
    *
    * @return void
    */
    public function __set($name, $value)
    {
        $forbidden = array('fields', 'is_cotis', 'end_date');

        if ( !in_array($name, $forbidden) ) {
            $rname = '_' . $name;
            switch($name) {
            case 'transaction':
                if ( is_int($value) ) {
                    $this->$rname = new Transaction($value);
                } else {
                    Analog::log(
                        'Trying to set a transaction from an id that is not an integer.',
                        Analog::WARNING
                    );
                }
                break;
            case 'type':
                if ( is_int($value) ) {
                    //set type
                    $this->$rname = new ContributionsTypes($value);
                    //set is_cotis according to type
                    if ( $this->$rname->extension == 1 ) {
                        $this->_is_cotis = true;
                    } else {
                        $this->_is_cotis = false;
                    }
                } else {
                    Analog::log(
                        'Trying to set a type from an id that is not an integer.',
                        Analog::WARNING
                    );
                }
                break;
            case 'begin_date':
                try {
                    $d = \DateTime::createFromFormat(_T("Y-m-d"), $value);
                    if ( $d === false ) {
                        throw new \Exception('Incorrect format');
                    }
                    $this->_begin_date = $d->format('Y-m-d');
                } catch (\Exception $e) {
                    Analog::log(
                        'Wrong date format. field: ' . $key .
                        ', value: ' . $value . ', expected fmt: ' .
                        _T("Y-m-d") . ' | ' . $e->getMessage(),
                        Analog::INFO
                    );
                    $errors[] = str_replace(
                        array(
                            '%date_format',
                            '%field'
                        ),
                        array(
                            _T("Y-m-d"),
                            $this->_fields[$key]['label']
                        ),
                        _T("- Wrong date format (%date_format) for %field!")
                    );
                }
                break;
            case 'amount':
                if (is_numeric($value) && $value > 0 ) {
                    $this->$rname = $value;
                } else {
                    Analog::log(
                        'Trying to set an amount with a non numeric value, ' .
                        'or with a zero value',
                        Analog::WARNING
                    );
                }
                break;
            default:
                Analog::log(
                    '[' . __CLASS__ . ']: Trying to set an unknown property (' .
                    $name . ')',
                    Analog::WARNING
                );
                break;
            }
        }

    }
}
