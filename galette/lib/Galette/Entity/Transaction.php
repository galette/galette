<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Transaction class for galette
 *
 * PHP version 5
 *
 * Copyright © 2011-2013 The Galette Team
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
 * @copyright 2011-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-07-31
 */

namespace Galette\Entity;

use Analog\Analog as Analog;
use Galette\Repository\Contributions as Contributions;

/**
 * Transaction class for galette
 *
 * @category  Entity
 * @name      Transaction
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2010 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2010-03-11
 */
class Transaction
{
    const TABLE = 'transactions';
    const PK = 'trans_id';

    private $_id;
    private $_date;
    private $_amount;
    private $_description;
    private $_member;

    //fields list and their translation
    private $_fields;

    /**
    * Default constructor
    *
    * @param null|int|ResultSet $args Either a ResultSet row or its id for to load
    *                                   a specific transaction, or null to just
    *                                   instanciate object
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
            self::PK            => array(
                'label'    => null, //not a field in the form
                'propname' => 'id'
            ),
            'trans_date'          => array(
                'label'    => _T("Date:"), //not a field in the form
                'propname' => 'date'
            ),
            'trans_amount'       => array(
                'label'    => _T("Amount:"),
                'propname' => 'amount'
            ),
            'trans_desc'          => array(
                'label'    => _T("Description:"),
                'propname' => 'description'
            ),
            Adherent::PK          => array(
                'label'    => _T("Originator:"),
                'propname' => 'member'
            )
        );
        if ( $args == null || is_int($args) ) {
            $this->_date = date("Y-m-d");

            if ( is_int($args) && $args > 0 ) {
                $this->load($args);
            }
        } elseif ( is_object($args) ) {
            $this->_loadFromRS($args);
        }
    }

    /**
    * Loads a transaction from its id
    *
    * @param int $id the identifier for the transaction to load
    *
    * @return bool true if query succeed, false otherwise
    */
    public function load($id)
    {
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . self::TABLE)
                ->where(self::PK . ' = ?', $id);
            $result = $select->query()->fetch();
            if ( $result ) {
                $this->_loadFromRS($result);
                return true;
            } else {
                throw new \Exception;
            }
        } catch (\Exception $e) {
            /** FIXME */
            Analog::log(
                'Cannot load transaction form id `' . $id . '` | ' .
                $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Remove transaction (and all associated contributions) from database
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

            //remove associated contributions if needeed
            if ( $this->getDispatchedAmount() > 0 ) {
                $c = new Contributions();
                $clist = $c->getListFromTransaction($this->_id);
                $cids = array();
                foreach ( $clist as $cid) {
                    $cids[] = $cid->id;
                }
                $rem = $c->removeContributions($cids, false);
            }

            //remove transaction itself
            $del = $zdb->db->delete(
                PREFIX_DB . self::TABLE,
                self::PK . ' = ' . $this->_id
            );

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
                'An error occured trying to remove transaction #' .
                $this->_id . ' | ' . $e->getMessage(),
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
        $this->_id = $r->$pk;
        $this->_date = $r->trans_date;
        $this->_amount = $r->trans_amount;
        $this->_description = $r->trans_desc;
        $adhpk = Adherent::PK;
        $this->_member = (int)$r->$adhpk;
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
                // now, check validity
                if ( $value != '' ) {
                    switch ( $key ) {
                    // dates
                    case 'trans_date':
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
                        break;
                    case Adherent::PK:
                        $this->_member = $value;
                        break;
                    case 'trans_amount':
                        $this->_amount = $value;
                        $us_value = strtr($value, ',', '.');
                        if ( !is_numeric($value) ) {
                            $errors[] = _T("- The amount must be an integer!");
                        }
                        break;
                    case 'trans_desc':
                        /** TODO: retrieve field length from database and check that */
                        $this->_description = $value;
                        if ( trim($value) == '' ) {
                            $errors[] = _T("- Empty transaction description!");
                        } else if (strlen($value) > 150 ) {
                            $errors[] = _T("- Transaction description must be 150 characters long maximum.");
                        }
                        break;
                    }
                }
            }
        }

        // missing required fields?
        while ( list($key, $val) = each($required) ) {
            if ( $val === 1) {
                $prop = '_' . $this->_fields[$key]['propname'];
                if ( !isset($disabled[$key]) && !isset($this->$prop) ) {
                    $errors[] = _T("- Mandatory field empty: ") .
                    ' <a href="#' . $key . '">' . $this->getFieldName($key) .'</a>';
                }
            }
        }

        if ( $this->_id != '' ) {
            $dispatched = $this->getDispatchedAmount();
            if ( $dispatched > $this->_amount ) {
                $errors[] = _T("- Sum of all contributions exceed corresponding transaction amount.");
            }
        }

        if ( count($errors) > 0 ) {
            Analog::log(
                'Some errors has been throwed attempting to edit/store a transaction' .
                print_r($errors, true),
                Analog::DEBUG
            );
            return $errors;
        } else {
            Analog::log(
                'Transaction checked successfully.',
                Analog::DEBUG
            );
            return true;
        }
    }

    /**
     * Store the transaction
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
                $values[$field] = $this->$prop;
            }

            if ( !isset($this->_id) || $this->_id == '') {
                //we're inserting a new transaction
                unset($values[self::PK]);
                $add = $zdb->db->insert(PREFIX_DB . self::TABLE, $values);
                if ( $add > 0) {
                    $this->_id = $zdb->db->lastInsertId(
                        PREFIX_DB . self::TABLE,
                        'id'
                    );
                    // logging
                    $hist->add(
                        _T("Transaction added"),
                        Adherent::getSName($this->_member)
                    );
                } else {
                    $hist->add(_T("Fail to add new transaction."));
                    throw new \Exception(
                        'An error occured inserting new transaction!'
                    );
                }
            } else {
                //we're editing an existing transaction
                $edit = $zdb->db->update(
                    PREFIX_DB . self::TABLE,
                    $values,
                    self::PK . '=' . $this->_id
                );
                //edit == 0 does not mean there were an error, but that there
                //were nothing to change
                if ( $edit > 0 ) {
                    $hist->add(
                        _T("Transaction updated"),
                        Adherent::getSName($this->_member)
                    );
                }
            }
            $zdb->db->commit();
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
     * Retrieve amount that has already been dispatched into contributions
     *
     * @return double
     */
    public function getDispatchedAmount()
    {
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(
                PREFIX_DB . Contribution::TABLE,
                'SUM(montant_cotis)'
            )->where(self::PK . ' = ?', $this->_id);
            $dispatched_amount = $select->query()->fetchColumn();
            return (double)$dispatched_amount;
        } catch (\Exception $e) {
            Analog::log(
                'An error occured retrieving dispatched amounts | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            Analog::log(
                'Query was: ' . $select->__toString(),
                Analog::DEBUG
            );
        }
    }

    /**
     * Retrieve amount that has not yet been dispatched into contributions
     *
     * @return double
     */
    public function getMissingAmount()
    {
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(
                PREFIX_DB . Contribution::TABLE,
                'SUM(montant_cotis)'
            )->where(self::PK . ' = ?', $this->_id);
            $dispatched_amount = $select->query()->fetchColumn();
            return (double)$this->_amount - (double)$dispatched_amount;
        } catch (\Exception $e) {
            Analog::log(
                'An error occured retrieving missing amounts | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            Analog::log(
                'Query was: ' . $select->__toString(),
                Analog::DEBUG
            );
        }
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
    * Get the relevant CSS class for current transaction
    *
    * @return string current transaction row class
    */
    public function getRowClass()
    {
        return ( $this->getMissingAmount() == 0 ) ?
            'transaction-normal' :
            'transaction-uncomplete';
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
        $forbidden = array();

        $rname = '_' . $name;
        if ( !in_array($name, $forbidden) && isset($this->$rname) ) {
            switch($name) {
            case 'date':
                return date_db2text($this->$rname);
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
        /*$forbidden = array('fields');*/
        /** TODO: What to do ? :-) */
    }
}
