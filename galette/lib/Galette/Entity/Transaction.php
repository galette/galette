<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Transaction class for galette
 *
 * PHP version 5
 *
 * Copyright © 2011-2021 The Galette Team
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
 * @copyright 2011-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-07-31
 */

namespace Galette\Entity;

use Throwable;
use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Galette\Repository\Contributions;
use Galette\Core\Db;
use Galette\Core\History;
use Galette\Core\Login;
use Galette\Features\Dynamics;

/**
 * Transaction class for galette
 *
 * @category  Entity
 * @name      Transaction
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2010-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2010-03-11
 *
 * @property integer $id
 * @property date $date
 * @property integer $amount
 * @property string $description
 * @property integer $member
 */
class Transaction
{
    use Dynamics;

    public const TABLE = 'transactions';
    public const PK = 'trans_id';

    private $_id;
    private $_date;
    private $_amount;
    private $_description;
    private $_member;

    //fields list and their translation
    private $_fields;

    private $zdb;
    private $login;

    private $errors;

    /**
     * Default constructor
     *
     * @param Db                 $zdb   Database instance
     * @param Login              $login Login instance
     * @param null|int|ResultSet $args  Either a ResultSet row or its id for to load
     *                                  a specific transaction, or null to just
     *                                  instantiate object
     */
    public function __construct(Db $zdb, Login $login, $args = null)
    {
        $this->zdb = $zdb;
        $this->login = $login;

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
        if ($args == null || is_int($args)) {
            $this->_date = date("Y-m-d");

            if (is_int($args) && $args > 0) {
                $this->load($args);
            }
        } elseif (is_object($args)) {
            $this->loadFromRS($args);
        }

        $this->loadDynamicFields();
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
        try {
            $select = $this->zdb->select(self::TABLE, 't');
            $select->where(self::PK . ' = ' . $id);
            $select->join(
                array('a' => PREFIX_DB . Adherent::TABLE),
                't.' . Adherent::PK . '=a.' . Adherent::PK,
                array()
            );

            //restrict query on current member id if he's not admin nor staff member
            if (!$this->login->isAdmin() && !$this->login->isStaff() && !$this->login->isGroupManager()) {
                if (!$this->login->isLogged()) {
                    Analog::log(
                        'Non-logged-in users cannot load transaction id `' . $id,
                        Analog::ERROR
                    );
                    return false;
                }
                $select->where
                    ->nest()
                        ->equalTo('a.' . Adherent::PK, $this->login->id)
                        ->or
                        ->equalTo('a.parent_id', $this->login->id)
                    ->unnest()
                    ->and
                    ->equalTo('t.' . self::PK, $id)
                ;
            } else {
                $select->where->equalTo(self::PK, $id);
            }

            $results = $this->zdb->execute($select);
            $result = $results->current();
            if ($result) {
                $this->loadFromRS($result);
                return true;
            } else {
                Analog::log(
                    'Transaction id `' . $id . '` does not exists',
                    Analog::WARNING
                );
                return false;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Cannot load transaction form id `' . $id . '` | ' .
                $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Remove transaction (and all associated contributions) from database
     *
     * @param History $hist        History
     * @param boolean $transaction Activate transaction mode (defaults to true)
     *
     * @return boolean
     */
    public function remove(History $hist, $transaction = true)
    {
        global $emitter;

        try {
            if ($transaction) {
                $this->zdb->connection->beginTransaction();
            }

            //remove associated contributions if needeed
            if ($this->getDispatchedAmount() > 0) {
                $c = new Contributions($this->zdb, $this->login);
                $clist = $c->getListFromTransaction($this->_id);
                $cids = array();
                foreach ($clist as $cid) {
                    $cids[] = $cid->id;
                }
                $rem = $c->remove($cids, $hist, false);
            }

            //remove transaction itself
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where(
                self::PK . ' = ' . $this->_id
            );
            $del = $this->zdb->execute($delete);
            if ($del->count() > 0) {
                $this->dynamicsRemove(true);
            } else {
                Analog::log(
                    'Transaction has not been removed!',
                    Analog::WARNING
                );
                return false;
            }

            if ($transaction) {
                $this->zdb->connection->commit();
            }

            $emitter->emit('transaction.remove', $this);
            return true;
        } catch (Throwable $e) {
            if ($transaction) {
                $this->zdb->connection->rollBack();
            }
            Analog::log(
                'An error occurred trying to remove transaction #' .
                $this->_id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ResultSet $r the resultset row
     *
     * @return void
     */
    private function loadFromRS($r)
    {
        $pk = self::PK;
        $this->_id = $r->$pk;
        $this->_date = $r->trans_date;
        $this->_amount = $r->trans_amount;
        $this->_description = $r->trans_desc;
        $adhpk = Adherent::PK;
        $this->_member = (int)$r->$adhpk;

        $this->loadDynamicFields();
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
        $this->errors = array();

        $fields = array_keys($this->_fields);
        foreach ($fields as $key) {
            //first, let's sanitize values
            $key = strtolower($key);
            $prop = '_' . $this->_fields[$key]['propname'];

            if (isset($values[$key])) {
                $value = trim($values[$key]);
            } else {
                $value = '';
            }

            // if the field is enabled, check it
            if (!isset($disabled[$key])) {
                // now, check validity
                if ($value != '') {
                    switch ($key) {
                        // dates
                        case 'trans_date':
                            try {
                                $d = \DateTime::createFromFormat(__("Y-m-d"), $value);
                                if ($d === false) {
                                    throw new \Exception('Incorrect format');
                                }
                                $this->$prop = $d->format('Y-m-d');
                            } catch (Throwable $e) {
                                Analog::log(
                                    'Wrong date format. field: ' . $key .
                                    ', value: ' . $value . ', expected fmt: ' .
                                    __("Y-m-d") . ' | ' . $e->getMessage(),
                                    Analog::INFO
                                );
                                $this->errors[] = str_replace(
                                    array(
                                        '%date_format',
                                        '%field'
                                    ),
                                    array(
                                        __("Y-m-d"),
                                        $this->getFieldLabel($key)
                                    ),
                                    _T("- Wrong date format (%date_format) for %field!")
                                );
                            }
                            break;
                        case Adherent::PK:
                            $this->_member = (int)$value;
                            break;
                        case 'trans_amount':
                            $this->_amount = $value;
                            $value = strtr($value, ',', '.');
                            if (!is_numeric($value)) {
                                $this->errors[] = _T("- The amount must be an integer!");
                            }
                            break;
                        case 'trans_desc':
                            /** TODO: retrieve field length from database and check that */
                            $this->_description = $value;
                            if (mb_strlen($value) > 150) {
                                $this->errors[] = _T("- Transaction description must be 150 characters long maximum.");
                            }
                            break;
                    }
                }
            }
        }

        // missing required fields?
        foreach ($required as $key => $val) {
            if ($val === 1) {
                $prop = '_' . $this->_fields[$key]['propname'];
                if (!isset($disabled[$key]) && !isset($this->$prop)) {
                    $this->errors[] = str_replace(
                        '%field',
                        '<a href="#' . $key . '">' . $this->getFieldLabel($key) . '</a>',
                        _T("- Mandatory field %field empty.")
                    );
                }
            }
        }

        if ($this->_id != '') {
            $dispatched = $this->getDispatchedAmount();
            if ($dispatched > $this->_amount) {
                $this->errors[] = _T("- Sum of all contributions exceed corresponding transaction amount.");
            }
        }

        $this->dynamicsCheck($values, $required, $disabled);

        if (count($this->errors) > 0) {
            Analog::log(
                'Some errors has been thew attempting to edit/store a transaction' .
                print_r($this->errors, true),
                Analog::DEBUG
            );
            return $this->errors;
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
     * @param History $hist History
     *
     * @return boolean
     */
    public function store(History $hist)
    {
        global $emitter;

        $event = null;

        try {
            $this->zdb->connection->beginTransaction();
            $values = array();
            $fields = $this->getDbFields($this->zdb);
            /** FIXME: quote? */
            foreach ($fields as $field) {
                $prop = '_' . $this->_fields[$field]['propname'];
                $values[$field] = $this->$prop;
            }

            $success = false;
            if (!isset($this->_id) || $this->_id == '') {
                //we're inserting a new transaction
                unset($values[self::PK]);
                $insert = $this->zdb->insert(self::TABLE);
                $insert->values($values);
                $add = $this->zdb->execute($insert);
                if ($add->count() > 0) {
                    $this->_id = $this->zdb->getLastGeneratedValue($this);

                    // logging
                    $hist->add(
                        _T("Transaction added"),
                        Adherent::getSName($this->zdb, $this->_member)
                    );
                    $success = true;
                    $event = 'transaction.add';
                } else {
                    $hist->add(_T("Fail to add new transaction."));
                    throw new \RuntimeException(
                        'An error occurred inserting new transaction!'
                    );
                }
            } else {
                //we're editing an existing transaction
                $update = $this->zdb->update(self::TABLE);
                $update->set($values)->where(
                    self::PK . '=' . $this->_id
                );
                $edit = $this->zdb->execute($update);
                //edit == 0 does not mean there were an error, but that there
                //were nothing to change
                if ($edit->count() > 0) {
                    $hist->add(
                        _T("Transaction updated"),
                        Adherent::getSName($this->zdb, $this->_member)
                    );
                }
                $success = true;
                $event = 'transaction.edit';
            }

            //dynamic fields
            if ($success) {
                $success = $this->dynamicsStore(true);
            }

            $this->zdb->connection->commit();

            //send event at the end of process, once all has been stored
            if ($event !== null) {
                $emitter->emit($event, $this);
            }

            return true;
        } catch (Throwable $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                'Something went wrong :\'( | ' . $e->getMessage() . "\n" .
                $e->getTraceAsString(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Retrieve amount that has already been dispatched into contributions
     *
     * @return double
     */
    public function getDispatchedAmount(): float
    {
        if (empty($this->_id)) {
            return (double)0;
        }

        try {
            $select = $this->zdb->select(Contribution::TABLE);
            $select->columns(
                array(
                    'sum' => new Expression('SUM(montant_cotis)')
                )
            )->where(self::PK . ' = ' . $this->_id);

            $results = $this->zdb->execute($select);
            $result = $results->current();
            $dispatched_amount = $result->sum;
            return (double)$dispatched_amount;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred retrieving dispatched amounts | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Retrieve amount that has not yet been dispatched into contributions
     *
     * @return double
     */
    public function getMissingAmount()
    {
        if (empty($this->_id)) {
            return (double)$this->amount;
        }

        try {
            $select = $this->zdb->select(Contribution::TABLE);
            $select->columns(
                array(
                    'sum' => new Expression('SUM(montant_cotis)')
                )
            )->where(self::PK . ' = ' . $this->_id);

            $results = $this->zdb->execute($select);
            $result = $results->current();
            $dispatched_amount = $result->sum;
            return (double)$this->_amount - (double)$dispatched_amount;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred retrieving missing amounts | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Retrieve fields from database
     *
     * @param Db $zdb Database instance
     *
     * @return array
     */
    public function getDbFields(Db $zdb)
    {
        $columns = $zdb->getColumns(self::TABLE);
        $fields = array();
        foreach ($columns as $col) {
            $fields[] = $col->getName();
        }
        return $fields;
    }

    /**
     * Get the relevant CSS class for current transaction
     *
     * @return string current transaction row class
     */
    public function getRowClass()
    {
        return ($this->getMissingAmount() == 0) ?
            'transaction-normal' : 'transaction-uncomplete';
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
        if (!in_array($name, $forbidden) && property_exists($this, $rname)) {
            switch ($name) {
                case 'date':
                    if ($this->$rname != '') {
                        try {
                            $d = new \DateTime($this->$rname);
                            return $d->format(__("Y-m-d"));
                        } catch (Throwable $e) {
                            //oops, we've got a bad date :/
                            Analog::log(
                                'Bad date (' . $this->$rname . ') | ' .
                                $e->getMessage(),
                                Analog::INFO
                            );
                            return $this->$rname;
                        }
                    }
                    break;
                case 'id':
                    if ($this->$rname !== null) {
                        return (int)$this->$rname;
                    }
                    return null;
                case 'amount':
                    if ($this->$rname !== null) {
                        return (double)$this->$rname;
                    }
                    return null;
                default:
                    return $this->$rname;
            }
        } else {
            Analog::log(
                sprintf(
                    'Property %1$s does not exists for transaction',
                    $name
                ),
                Analog::WARNING
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
    public function getFieldLabel($field)
    {
        $label = $this->_fields[$field]['label'];
        //replace "&nbsp;"
        $label = str_replace('&nbsp;', ' ', $label);
        //remove trailing ':' and then trim
        $label = trim(trim($label, ':'));
        return $label;
    }

    /**
     * Handle files (dynamics files)
     *
     * @param array $files Files sent
     *
     * @return array|true
     */
    public function handleFiles($files)
    {
        $this->errors = [];

        $this->dynamicsFiles($files);

        if (count($this->errors) > 0) {
            Analog::log(
                'Some errors has been thew attempting to edit/store a transaction files' . "\n" .
                print_r($this->errors, true),
                Analog::ERROR
            );
            return $this->errors;
        } else {
            return true;
        }
    }

    /**
     * Can current logged-in user display transaction
     *
     * @param Login $login Login instance
     *
     * @return boolean
     */
    public function canShow(Login $login): bool
    {
        //non-logged-in members cannot show contributions
        if (!$login->isLogged()) {
            return false;
        }

        //admin and staff users can edit, as well as member itself
        if (!$this->id || $this->id && $login->id == $this->_member || $login->isAdmin() || $login->isStaff()) {
            return true;
        }

        //parent can see their children transactions
        $parent = new Adherent($this->zdb);
        $parent
            ->disableAllDeps()
            ->enableDep('children')
            ->load($this->login->id);
        if ($parent->hasChildren()) {
            foreach ($parent->children as $child) {
                if ($child->id === $this->_member) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }
}
