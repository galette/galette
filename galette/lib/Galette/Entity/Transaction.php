<?php

/**
 * Copyright © 2003-2025 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
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
 */

declare(strict_types=1);

namespace Galette\Entity;

use ArrayObject;
use Doctrine\ORM\Mapping as ORM;
use Galette\Events\GaletteEvent;
use Galette\Repository\PaymentTypes;
use Throwable;
use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Galette\Repository\Contributions;
use Galette\Core\Db;
use Galette\Core\History;
use Galette\Core\Login;
use Galette\Features\Dynamics;
use Galette\Helpers\EntityHelper;

/**
 * Transaction class for galette
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property integer $id
 * @property string $date
 * @property float $amount
 * @property ?string $description
 * @property ?integer $member
 * @property ?integer $payment_type
 */
#[ORM\Entity]
#[ORM\Table(name: 'orm_transactions')]
class Transaction
{
    use Dynamics;
    use EntityHelper;

    public const TABLE = 'transactions';
    public const PK = 'trans_id';

    #[ORM\Id]
    #[ORM\Column(name: 'trans_id', type: 'integer', options: ['unsigned' => true])]
    #[ORM\GeneratedValue]
    private int $id;
    #[ORM\Column(name: 'trans_date', type: 'date')]
    private string $date;
    #[ORM\Column(name: 'trans_amount', type: 'decimal', precision: 15, scale: 2)]
    private float $amount;
    #[ORM\Column(name: 'trans_desc', type: 'string', length: 255, options: ['default' => ''])]
    private ?string $description = null;
    #[ORM\ManyToOne(targetEntity: Adherent::class)]
    #[ORM\JoinColumn(
        name: Adherent::PK,
        referencedColumnName: Adherent::PK,
        onDelete: 'restrict',
        options: [
            'unsigned' => true
        ]
    )]
    private ?int $member = null;
    #[ORM\ManyToOne(targetEntity: PaymentType::class)]
    #[ORM\JoinColumn(
        name: 'type_paiement_trans',
        referencedColumnName: PaymentType::PK,
        onDelete: 'restrict',
        options: [
            'unsigned' => true
        ]
    )]
    private ?int $payment_type = null;

    private Db $zdb;
    private Login $login;

    /** @var array<string> */
    protected array $errors;
    /** @var string[] */
    protected array $forbidden_fields = [];

    /**
     * Default constructor
     *
     * @param Db                                       $zdb   Database instance
     * @param Login                                    $login Login instance
     * @param null|int|ArrayObject<string, int|string> $args  Either a ResultSet row or its id for to load
     *                                                        a specific transaction, or null to just
     *                                                        instantiate object
     */
    public function __construct(Db $zdb, Login $login, ArrayObject|int|null $args = null)
    {
        $this->zdb = $zdb;
        $this->login = $login;
        $this->setFields();

        if ($args === null || is_int($args)) {
            $this->date = date("Y-m-d");

            if (is_int($args) && $args > 0) {
                $this->load($args);
            }
        } elseif ($args instanceof ArrayObject) {
            $this->loadFromRS($args);
        }

        $this->loadDynamicFields();
    }


    /**
     * Set fields, must populate $this->fields
     *
     * @return self
     */
    protected function setFields(): self
    {
        $this->fields = array(
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
            ),
            'type_paiement_trans' => array(
                'label'    => _T("Payment type:"),
                'propname' => 'payment_type'
            )
        );
        return $this;
    }

    /**
     * Loads a transaction from its id
     *
     * @param int $id the identifier for the transaction to load
     *
     * @return bool true if query succeed, false otherwise
     */
    public function load(int $id): bool
    {
        if (!$this->login->isLogged()) {
            Analog::log(
                'Non-logged-in users cannot load transaction id `' . $id,
                Analog::ERROR
            );
            return false;
        }

        try {
            $select = $this->zdb->select(self::TABLE, 't');
            $select->where([self::PK => $id]);
            $select->join(
                array('a' => PREFIX_DB . Adherent::TABLE),
                't.' . Adherent::PK . '=a.' . Adherent::PK,
                array()
            );

            //restrict query on current member id if he's not admin nor staff member
            if (!$this->login->isAdmin() && !$this->login->isStaff() && !$this->login->isGroupManager()) {
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
            if ($results->count() > 0) {
                /** @var ArrayObject<string, int|string> $result */
                $result = $results->current();
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
    public function remove(History $hist, bool $transaction = true): bool
    {
        global $emitter;

        try {
            if ($transaction) {
                $this->zdb->connection->beginTransaction();
            }

            //remove associated contributions if needed
            if ($this->getDispatchedAmount() > 0) {
                $c = new Contributions($this->zdb, $this->login);
                $clist = $c->getListFromTransaction($this->id);
                $cids = array();
                foreach ($clist as $cid) {
                    $cids[] = $cid->id;
                }
                $c->remove($cids, $hist, false);
            }

            //remove transaction itself
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where([self::PK => $this->id]);
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

            $emitter->dispatch(new GaletteEvent('transaction.remove', $this));
            return true;
        } catch (Throwable $e) {
            if ($transaction) {
                $this->zdb->connection->rollBack();
            }
            Analog::log(
                'An error occurred trying to remove transaction #' .
                $this->id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ArrayObject<string,int|string> $r the resultset row
     *
     * @return void
     */
    private function loadFromRS(ArrayObject $r): void
    {
        $pk = self::PK;
        $this->id = (int)$r->$pk;
        $this->date = $r->trans_date;
        $this->amount = (float)$r->trans_amount;
        $this->description = $r->trans_desc;
        $adhpk = Adherent::PK;
        if ($r->$adhpk !== null) {
            $this->member = (int)$r->$adhpk;
        }
        if ($r->type_paiement_trans != null) {
            $this->payment_type = (int)$r->type_paiement_trans;
        }

        $this->loadDynamicFields();
    }

    /**
     * Check posted values validity
     *
     * @param array<string,mixed> $values   All values to check, basically the $_POST array
     *                                      after sending the form
     * @param array<string,int>   $required Array of required fields
     * @param array<string>       $disabled Array of disabled fields
     *
     * @return true|array<string>
     */
    public function check(array $values, array $required, array $disabled): bool|array
    {
        global $preferences;
        $this->errors = array();

        $fields = array_keys($this->fields);
        foreach ($fields as $key) {
            //first, let's sanitize values
            $key = strtolower($key);
            $prop = $this->fields[$key]['propname'];

            if (isset($values[$key])) {
                $value = $values[$key];
                if (is_string($value)) {
                    $value = trim($value);
                }
            } else {
                $value = null;
            }

            // if the field is enabled, check it
            if (!isset($disabled[$key])) {
                // now, check validity
                if ($value != '') {
                    switch ($key) {
                        // dates
                        case 'trans_date':
                            $this->setDate($key, $value);
                            break;
                        case Adherent::PK:
                            $this->member = (int)$value;
                            break;
                        case 'trans_amount':
                            //FIXME: this is a hack to allow comma as decimal separator
                            $value = strtr((string)$value, ',', '.');
                            $this->amount = (double)$value;
                            if (!is_numeric($value)) {
                                $this->errors[] = _T("- The amount must be an integer!");
                            }
                            break;
                        case 'trans_desc':
                            /** TODO: retrieve field length from database and check that */
                            $this->description = strip_tags($value);
                            if (mb_strlen($value) > 150) {
                                $this->errors[] = _T("- Transaction description must be 150 characters long maximum.");
                            }
                            break;
                        case 'type_paiement_trans':
                            if ($value == 0) {
                                break;
                            }
                            $ptypes = new PaymentTypes(
                                $this->zdb,
                                $preferences,
                                $this->login
                            );
                            $ptlist = $ptypes->getList();
                            if (isset($ptlist[$value])) {
                                $this->payment_type = (int)$value;
                            } else {
                                $this->errors[] = _T("- Unknown payment type");
                            }
                            break;
                    }
                }
            }
        }

        // missing required fields?
        foreach ($required as $key => $val) {
            if ($val === 1) {
                $prop = $this->fields[$key]['propname'];
                if (!isset($disabled[$key]) && !isset($this->$prop)) {
                    $this->errors[] = str_replace(
                        '%field',
                        '<a href="#' . $key . '">' . $this->getFieldLabel($key) . '</a>',
                        _T("- Mandatory field %field empty.")
                    );
                }
            }
        }

        if (isset($this->id)) {
            $dispatched = $this->getDispatchedAmount();
            if ($dispatched > $this->amount) {
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
    public function store(History $hist): bool
    {
        global $emitter;

        try {
            $this->zdb->connection->beginTransaction();
            $values = array();
            $fields = $this->getDbFields($this->zdb);
            /** FIXME: quote? */
            foreach ($fields as $field) {
                $prop = $this->fields[$field]['propname'];
                if (isset($this->$prop)) {
                    $values[$field] = $this->$prop;
                }
            }

            if (!isset($this->id) || $this->id == '') {
                //we're inserting a new transaction
                unset($values[self::PK]);
                $insert = $this->zdb->insert(self::TABLE);
                $insert->values($values);
                $add = $this->zdb->execute($insert);
                if ($add->count() > 0) {
                    $this->id = $this->zdb->getLastGeneratedValue($this);

                    // logging
                    $hist->add(
                        _T("Transaction added"),
                        Adherent::getSName($this->zdb, $this->member)
                    );
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
                $update->set($values)->where([self::PK => $this->id]);
                $edit = $this->zdb->execute($update);
                //edit == 0 does not mean there were an error, but that there
                //were nothing to change
                if ($edit->count() > 0) {
                    $hist->add(
                        _T("Transaction updated"),
                        Adherent::getSName($this->zdb, $this->member)
                    );
                }
                $event = 'transaction.edit';
            }

            //dynamic fields
            $this->dynamicsStore(true);

            $this->zdb->connection->commit();

            //send event at the end of process, once all has been stored
            $emitter->dispatch(new GaletteEvent($event, $this));

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
        if (empty($this->id)) {
            return (double)0;
        }

        try {
            $select = $this->zdb->select(Contribution::TABLE);
            $select->columns(
                array(
                    'sum' => new Expression('SUM(montant_cotis)')
                )
            )->where([self::PK => $this->id]);

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
    public function getMissingAmount(): float
    {
        if (empty($this->id)) {
            return $this->amount ?? 0;
        }

        try {
            $select = $this->zdb->select(Contribution::TABLE);
            $select->columns(
                array(
                    'sum' => new Expression('SUM(montant_cotis)')
                )
            )->where([self::PK => $this->id]);

            $results = $this->zdb->execute($select);
            $result = $results->current();
            $dispatched_amount = $result->sum;
            return (double)$this->amount - (double)$dispatched_amount;
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
     * Get payment type label
     *
     * @return string
     */
    public function getPaymentType(): string
    {
        if ($this->payment_type === null) {
            return '-';
        }

        $ptype = new PaymentType($this->zdb, $this->payment_type);
        return $ptype->getName();
    }

    /**
     * Retrieve fields from database
     *
     * @param Db $zdb Database instance
     *
     * @return array<string>
     */
    public function getDbFields(Db $zdb): array
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
    public function getRowClass(): string
    {
        return ($this->getMissingAmount() == 0) ?
            'transaction-normal' : 'transaction-uncomplete';
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return mixed the called property
     */
    public function __get(string $name): mixed
    {
        if (!in_array($name, $this->forbidden_fields) && property_exists($this, $name)) {
            switch ($name) {
                case 'date':
                    return $this->getDate($name);
                case 'id':
                    if (isset($this->$name) && $this->$name !== null) {
                        return (int)$this->$name;
                    }
                    return null;
                case 'amount':
                    if (isset($this->$name)) {
                        return (double)$this->$name;
                    }
                    return null;
                case 'fields':
                    return $this->fields;
                default:
                    return $this->$name;
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
     * Handle files (dynamics files)
     *
     * @param array<string,mixed> $files Files sent
     *
     * @return array<string>|true
     */
    public function handleFiles(array $files): array|bool
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
        global $preferences;

        //non-logged-in members cannot show contributions
        if (!$login->isLogged()) {
            return false;
        }

        //admin and staff users can edit, as well as member itself
        if (!isset($this->id) || $login->id == $this->member || $login->isAdmin() || $login->isStaff()) {
            return true;
        }

        //group managers can see contributions of members of groups they manage - if enabled in preferences
        if ($login->isGroupManager() && $preferences->pref_bool_groupsmanagers_see_transactions) {
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
                if ($child->id === $this->member) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }
}
