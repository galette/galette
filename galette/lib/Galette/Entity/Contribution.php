<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

namespace Galette\Entity;

use ArrayObject;
use DateTime;
use Galette\Events\GaletteEvent;
use Throwable;
use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\IO\ExternalScript;
use Galette\IO\PdfContribution;
use Galette\Repository\PaymentTypes;
use Galette\Features\Dynamics;

/**
 * Contribution class for galette
 * Manage membership fees and donations.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property integer $id
 * @property string $date
 * @property DateTime $raw_date
 * @property integer $member
 * @property ContributionsTypes $type
 * @property double $amount
 * @property integer $payment_type
 * @property double $orig_amount
 * @property string $info
 * @property string $begin_date
 * @property DateTime $raw_begin_date
 * @property string $end_date
 * @property DateTime $raw_end_date
 * @property Transaction|null $transaction
 * @property integer $extension
 * @property integer $duration
 * @property integer $model
 * @property array<string, array<string, string>> $fields
 */
class Contribution
{
    use Dynamics;

    public const TABLE = 'cotisations';
    public const PK = 'id_cotis';

    public const TYPE_FEE = 'fee';
    public const TYPE_DONATION = 'donation';

    public const STATUS_NEVER = -1;
    public const STATUS_UNKNOWN = 0;
    public const STATUS_UPTODATE = 1;
    public const STATUS_DUEFREE = 2;
    public const STATUS_IMPENDING = 3;
    public const STATUS_LATE = 4;
    public const STATUS_OLD = 5;

    private int $_id;
    private ?string $_date = null;
    private ?int $_member = null;
    private ?ContributionsTypes $_type = null;
    private ?float $_amount = null;
    private ?int $_payment_type;
    private ?float $_orig_amount = null;
    private ?string $_info = null;
    private ?string $_begin_date = null;
    private ?string $_end_date = null;
    private ?Transaction $_transaction = null;
    private bool $_is_cotis;
    private ?int $_extension = null;

    //fields list and their translation
    /** @var array<string, array<string, string>>  */
    private array $_fields;

    /** @var Db */
    private Db $zdb;
    /** @var Login */
    private Login $login;
    /** @var array<string> */
    private array $errors = [];

    private bool $sendmail = false;

    /**
     * Default constructor
     *
     * @param Db                                                          $zdb   Database
     * @param Login                                                       $login Login instance
     * @param null|int|array<string,mixed>|ArrayObject<string,int|string> $args  Either a ResultSet row to load
     *                                                                           a specific contribution, or a type id
     *                                                                           to just instantiate object
     */
    public function __construct(Db $zdb, Login $login, int|array|ArrayObject $args = null)
    {
        $this->zdb = $zdb;
        $this->login = $login;

        global $preferences;
        $this->_payment_type = $preferences->pref_default_paymenttype;

        /*
         * Fields configuration. Each field is an array and must reflect:
         * array(
         *   (string)label,
         *   (string) property name
         * )
         *
         * I'd prefer a static private variable for this...
         * But call to the _T function does not seem to be allowed there :/
         */
        $this->_fields = array(
            'id_cotis'            => array(
                'label'    => _T('Contribution id'), //not a field in the form
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
                'label'    => _T('Date'), //not a field in the form
                'propname' => 'date'
            ),
            'date_debut_cotis'    => array(
                'label'    => _T("Date of contribution:"),
                'cotlabel' => _T("Start date of membership:"), //if contribution is a membership fee, label differs
                'propname' => 'begin_date'
            ),
            'date_fin_cotis'      => array(
                'label'    => _T("End date of membership:"),
                'propname' => 'end_date'
            ),
            Transaction::PK       => array(
                'label'    => _T('Transaction ID'), //not a field in the form
                'propname' => 'transaction'
            ),
            //this one is not really a field, but is required in some cases...
            //adding it here make more simple to check required fields
            'duree_mois_cotis'    => array(
                'label'    => _T("Membership extension:"),
                'propname' => 'extension'
            )
        );
        if (is_int($args)) {
            $this->load($args);
        } elseif (is_array($args)) {
            $this->_date = date("Y-m-d");
            if (isset($args['adh']) && $args['adh'] != '') {
                $this->_member = (int)$args['adh'];
            }
            if (isset($args['trans'])) {
                $this->_transaction = new Transaction($this->zdb, $this->login, (int)$args['trans']);
                if (!isset($this->_member)) {
                    $this->_member = $this->_transaction->member;
                }
                $this->_amount = $this->_transaction->getMissingAmount();
            }
            $this->setContributionType((int)$args['type']);
            //calculate begin date for membership fee
            $this->_begin_date = $this->_date;
            if ($this->_is_cotis) {
                $due_date = self::getDueDate($this->zdb, $this->_member);
                if ($due_date != '') {
                    $now = new \DateTime();
                    $due_date = new \DateTime($due_date);
                    if ($due_date < $now) {
                        // Member didn't renew on time
                        $this->_begin_date = $now->format('Y-m-d');
                    } else {
                        // Caution : the next_begin_date is the day after the due_date.
                        $next_begin_date = clone $due_date;
                        $next_begin_date->add(new \DateInterval('P1D'));
                        $this->_begin_date = $next_begin_date->format('Y-m-d');
                    }
                }
                $this->retrieveEndDate();
            }
            if (isset($args['payment_type'])) {
                $this->_payment_type = $args['payment_type'];
            }
        } elseif (is_object($args)) {
            $this->loadFromRS($args);
        }

        $this->loadDynamicFields();
    }

    /**
     * Sets end contribution date
     *
     * @return void
     */
    private function retrieveEndDate(): void
    {
        global $preferences;

        $now = new \DateTime();
        $begin_date = new \DateTime($this->_begin_date);
        if ($preferences->pref_beg_membership != '') {
            //case beginning of membership
            if($this->_type->extension < 1){ // 0 = donate; -1= else duration (months)

                list($j, $m) = explode('/', $preferences->pref_beg_membership);
                $next_begin_date = new \DateTime($begin_date->format('Y') . '-' . $m . '-' . $j);
                while ($next_begin_date <= $begin_date) {
                    $next_begin_date->add(new \DateInterval('P1Y'));
                }

                if ($preferences->pref_membership_offermonths > 0) {
                    //count days until next membership begin date
                    $diff1 = (int)$now->diff($next_begin_date)->format('%a');

                    //count days between next membership begin date and offered months
                    $tdate = clone $next_begin_date;
                    $tdate->sub(new \DateInterval('P' . $preferences->pref_membership_offermonths . 'M'));
                    $diff2 = (int)$next_begin_date->diff($tdate)->format('%a');

                    //when number of days until next membership begin date is less than or equal to the offered months, it's free :)
                    if ($diff1 <= $diff2) {
                        $next_begin_date->add(new \DateInterval('P1Y'));
                    }
                }

                // Caution : the end_date to retrieve is the day before the next_begin_date.
                $end_date = clone $next_begin_date;
                $end_date->sub(new \DateInterval('P1D'));
            }
            else
            {   
                $dext = new \DateInterval('P' . $this->_type->extension . 'M');
                $end_date = $begin_date->add($dext);
                $end_date->sub(new \DateInterval('P1D')); //-1 day
            }
            $this->_end_date = $end_date->format('Y-m-d');
        } elseif ($preferences->pref_membership_ext != '') {
            //case membership extension
            if ($this->_extension == null) {
                $this->_extension = $preferences->pref_membership_ext;
            }
            $dext = new \DateInterval('P' . $this->_extension . 'M');
            // Caution : the end_date to retrieve is the day before the next_begin_date.
            $next_begin_date = $begin_date->add($dext);
            $end_date = clone $next_begin_date;
            $end_date->sub(new \DateInterval('P1D'));
            $this->_end_date = $end_date->format('Y-m-d');
        } else {
            throw new \RuntimeException(
                'Unable to define end date; none of pref_beg_membership nor pref_membership_ext are defined!'
            );
        }
    }

    /**
     * Loads a contribution from its id
     *
     * @param int $id the identifier for the contribution to load
     *
     * @return bool true if query succeed, false otherwise
     */
    public function load(int $id): bool
    {
        if (!$this->login->isLogged() && $this->login->id == '') {
            return false;
        }

        try {
            $select = $this->zdb->select(self::TABLE, 'c');
            $select->join(
                array('a' => PREFIX_DB . Adherent::TABLE),
                'c.' . Adherent::PK . '=a.' . Adherent::PK,
                array()
            );
            //restrict query on current member id if he's not admin nor staff member
            if (!$this->login->isAdmin() && !$this->login->isStaff()) {
                $select->where
                    ->nest()
                        ->equalTo('a.' . Adherent::PK, $this->login->id)
                        ->or
                        ->equalTo('a.parent_id', $this->login->id)
                    ->unnest()
                    ->and
                    ->equalTo('c.' . self::PK, $id)
                ;
            } else {
                $select->where->equalTo(self::PK, $id);
            }

            $results = $this->zdb->execute($select);
            if ($results->count() > 0) {
                $row = $results->current();
                $this->loadFromRS($row);
                return true;
            } else {
                Analog::log(
                    'No contribution #' . $id . ' (user ' . $this->login->id . ')',
                    Analog::ERROR
                );
                return false;
            }
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred attempting to load contribution #' . $id .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ArrayObject<string, int|string> $r the resultset row
     *
     * @return void
     */
    private function loadFromRS(ArrayObject $r): void
    {
        $pk = self::PK;
        $this->_id = (int)$r->$pk;
        $this->_date = $r->date_enreg;
        $this->_amount = (double)$r->montant_cotis;
        //save original amount, we need it for transactions parts calculations
        $this->_orig_amount = (double)$r->montant_cotis;
        $this->_payment_type = $r->type_paiement_cotis;
        $this->_info = $r->info_cotis;
        $this->_begin_date = $r->date_debut_cotis;
        $end_date = $r->date_fin_cotis;
        //do not work with knows bad dates...
        //the one with BC comes from 0.63/pgsql demo... Why the hell a so
        //strange date? don't know :(
        if (
            $end_date !== '0000-00-00'
            && $end_date !== '1901-01-01'
            && $end_date !== '0001-01-01 BC'
        ) {
            $this->_end_date = $r->date_fin_cotis;
        }
        $adhpk = Adherent::PK;
        $this->_member = (int)$r->$adhpk;

        $transpk = Transaction::PK;
        if ($r->$transpk != '') {
            $this->_transaction = new Transaction($this->zdb, $this->login, (int)$r->$transpk);
        }

        $this->setContributionType((int)$r->id_type_cotis);
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
                // fill up the adherent structure
                //$this->$prop = stripslashes($value); //not relevant here!

                // now, check validity
                switch ($key) {
                    // dates
                    case 'date_enreg':
                    case 'date_debut_cotis':
                    case 'date_fin_cotis':
                        if ($value != '') {
                            try {
                                $d = \DateTime::createFromFormat(__("Y-m-d"), $value);
                                if ($d === false) {
                                    //try with non localized date
                                    $d = \DateTime::createFromFormat("Y-m-d", $value);
                                    if ($d === false) {
                                        throw new \Exception('Incorrect format');
                                    }
                                }
                                $this->$prop = $d->format('Y-m-d');
                            } catch (Throwable $e) {
                                Analog::log(
                                    'Wrong date format. field: ' . $key .
                                    ', value: ' . $value . ', expected fmt: ' .
                                    __("Y-m-d") . ' | ' . $e->getMessage(),
                                    Analog::INFO
                                );
                                $this->errors[] = sprintf(
                                    //TRANS: %1$s is the date format, %2$s is the field name
                                    _T('- Wrong date format (%1$s) for %2$s!'),
                                    __("Y-m-d"),
                                    $this->_fields[$key]['label']
                                );
                            }
                        }
                        break;
                    case Adherent::PK:
                        if ($value != '') {
                            $this->_member = (int)$value;
                        }
                        break;
                    case ContributionsTypes::PK:
                        if ($value != '') {
                            $this->setContributionType((int)$value);
                        }
                        break;
                    case 'montant_cotis':
                        $value = strtr($value, ',', '.');
                        if (!empty($value) || $value === '0') {
                            $this->_amount = (double)$value;
                        }
                        if (!is_numeric($value) && $value !== '') {
                            $this->errors[] = _T("- The amount must be an integer!");
                        }
                        break;
                    case 'type_paiement_cotis':
                        $ptypes = new PaymentTypes(
                            $this->zdb,
                            $preferences,
                            $this->login
                        );
                        $ptlist = $ptypes->getList();
                        if (isset($ptlist[$value])) {
                            $this->_payment_type = (int)$value;
                        } else {
                            $this->errors[] = _T("- Unknown payment type");
                        }
                        break;
                    case 'info_cotis':
                        $this->_info = $value;
                        break;
                    case Transaction::PK:
                        if ($value != '') {
                            $this->_transaction = new Transaction($this->zdb, $this->login, (int)$value);
                        }
                        break;
                    case 'duree_mois_cotis':
                        if ($value != '') {
                            if (!is_numeric($value) || $value <= 0) {
                                $this->errors[] = _T("- The duration must be a positive integer!");
                            }
                            $this->$prop = $value;
                            $this->retrieveEndDate();
                        }
                        break;
                }
            }
        }

        // missing required fields?
        foreach ($required as $key => $val) {
            if ($val === 1) {
                $prop = '_' . $this->_fields[$key]['propname'];
                if (
                    !isset($disabled[$key])
                    && (!isset($this->$prop)
                    || (!is_object($this->$prop) && trim($this->$prop) == '')
                    || (is_object($this->$prop) && trim($this->$prop->id) == ''))
                ) {
                    $this->errors[] = str_replace(
                        '%field',
                        '<a href="#' . $key . '">' . $this->getFieldLabel($key) . '</a>',
                        _T("- Mandatory field %field empty.")
                    );
                }
            }
        }

        if ($this->_transaction != null && $this->_amount != null) {
            $missing = $this->_transaction->getMissingAmount();
            //calculate new missing amount
            $missing = $missing + $this->_orig_amount - $this->_amount;
            if ($missing < 0) {
                $this->errors[] = _T("- Sum of all contributions exceed corresponding transaction amount.");
            }
        }

        if ($this->isFee() && count($this->errors) == 0) {
            $overlap = $this->checkOverlap();
            if ($overlap !== true) {
                //method directly return error message
                $this->errors[] = $overlap;
            }
        }

        $this->dynamicsCheck($values, $required, $disabled);

        if (count($this->errors) > 0) {
            Analog::log(
                'Some errors has been threw attempting to edit/store a contribution' .
                print_r($this->errors, true),
                Analog::ERROR
            );
            return $this->errors;
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
    public function checkOverlap(): bool|string
    {
        try {
            $select = $this->zdb->select(self::TABLE, 'c');
            //@phpstan-ignore-next-line
            $select->columns(
                array('date_debut_cotis', 'date_fin_cotis')
            )->join(
                array('ct' => PREFIX_DB . ContributionsTypes::TABLE),
                'c.' . ContributionsTypes::PK . '=ct.' . ContributionsTypes::PK,
                array()
            )->where([Adherent::PK => $this->_member])
                ->where->notEqualTo('cotis_extension', 0)
                ->where->nest->nest
                ->greaterThanOrEqualTo('date_debut_cotis', $this->_begin_date)
                ->lessThanOrEqualTo('date_debut_cotis', $this->_end_date)
                ->unnest
                ->or->nest
                ->greaterThanOrEqualTo('date_fin_cotis', $this->_begin_date)
                ->lessThanOrEqualTo('date_fin_cotis', $this->_end_date);

            if ($this->id != '') {
                $select->where->notEqualTo(self::PK, $this->id);
            }

            $results = $this->zdb->execute($select);
            if ($results->count() > 0) {
                $result = $results->current();

                $d_begin = new \DateTime($result->date_debut_cotis);
                $d_end = new \DateTime($result->date_fin_cotis);

                if ($d_begin->format('m-d') == $d_end->format('m-d') && $result->date_fin_cotis == $this->_begin_date) {
                    //see https://bugs.galette.eu/issues/1762
                    return true;
                }

                return _T("- Membership period overlaps period starting at ") .
                    $d_begin->format(__("Y-m-d"));
            }
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred checking overlapping fee. ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Store the contribution
     *
     * @return boolean
     */
    public function store(): bool
    {
        global $hist, $emitter;

        $event = null;

        if (count($this->errors) > 0) {
            throw new \RuntimeException(
                'Existing errors prevents storing contribution: ' .
                print_r($this->errors, true)
            );
        }

        try {
            $this->zdb->connection->beginTransaction();
            $values = array();
            $fields = self::getDbFields($this->zdb);
            foreach ($fields as $field) {
                $prop = '_' . $this->_fields[$field]['propname'];
                if (!isset($this->$prop)) {
                    continue;
                }
                switch ($field) {
                    case ContributionsTypes::PK:
                    case Transaction::PK:
                        $values[$field] = $this->$prop->id;
                        break;
                    default:
                        $values[$field] = $this->$prop;
                        break;
                }
            }

            //no end date, let's take database defaults
            if (!$this->isFee() && !$this->_end_date) {
                unset($values['date_fin_cotis']);
            }

            if (!isset($this->_id) || $this->_id == '') {
                //we're inserting a new contribution
                unset($values[self::PK]);

                $insert = $this->zdb->insert(self::TABLE);
                $insert->values($values);
                $add = $this->zdb->execute($insert);

                if ($add->count() > 0) {
                    $this->_id = $this->zdb->getLastGeneratedValue($this);

                    // logging
                    $hist->add(
                        _T("Contribution added"),
                        Adherent::getSName($this->zdb, $this->_member)
                    );
                    $event = 'contribution.add';
                } else {
                    $hist->add(_T("Fail to add new contribution."));
                    throw new \Exception(
                        'An error occurred inserting new contribution!'
                    );
                }
            } else {
                //we're editing an existing contribution
                $update = $this->zdb->update(self::TABLE);
                $update->set($values)->where([self::PK => $this->_id]);
                $edit = $this->zdb->execute($update);

                //edit == 0 does not mean there were an error, but that there
                //were nothing to change
                if ($edit->count() > 0) {
                    $hist->add(
                        _T("Contribution updated"),
                        Adherent::getSName($this->zdb, $this->_member)
                    );
                }

                $event = 'contribution.edit';
            }
            //update deadline
            if ($this->isFee()) {
                $this->updateDeadline();
            }

            //dynamic fields
            $this->dynamicsStore(true);

            $this->zdb->connection->commit();
            $this->_orig_amount = $this->_amount;

            //send event at the end of process, once all has been stored
            if ($event !== null) {
                $emitter->dispatch(new GaletteEvent($event, $this));
            }

            return true;
        } catch (Throwable $e) {
            if ($this->zdb->connection->inTransaction()) {
                $this->zdb->connection->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Update member deadline
     *
     * @return boolean
     */
    private function updateDeadline(): bool
    {
        try {
            $due_date = self::getDueDate($this->zdb, $this->_member);

            if ($due_date != '') {
                $due_date_update = $due_date;
            } else {
                $due_date_update = new Expression('NULL');
            }

            $update = $this->zdb->update(Adherent::TABLE);
            $update->set(
                array('date_echeance' => $due_date_update)
            )->where(
                [Adherent::PK => $this->_member]
            );
            $this->zdb->execute($update);
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred updating member ' . $this->_member .
                '\'s deadline |' .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Remove contribution from database
     *
     * @param boolean $transaction Activate transaction mode (defaults to true)
     *
     * @return boolean
     */
    public function remove(bool $transaction = true): bool
    {
        global $emitter;

        try {
            if ($transaction) {
                $this->zdb->connection->beginTransaction();
            }

            $delete = $this->zdb->delete(self::TABLE);
            $delete->where([self::PK => $this->_id]);
            $del = $this->zdb->execute($delete);
            if ($del->count() > 0) {
                $this->updateDeadline();
                $this->dynamicsRemove(true);
            } else {
                Analog::log(
                    'Contribution has not been removed!',
                    Analog::WARNING
                );
                return false;
            }
            if ($transaction) {
                $this->zdb->connection->commit();
            }
            $emitter->dispatch(new GaletteEvent('contribution.remove', $this));
            return true;
        } catch (Throwable $e) {
            if ($transaction) {
                $this->zdb->connection->rollBack();
            }
            Analog::log(
                'An error occurred trying to remove contribution #' .
                $this->_id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Get field label
     *
     * @param string $field Field name
     *
     * @return string
     */
    public function getFieldLabel(string $field): string
    {
        $label = $this->_fields[$field]['label'];
        if ($field == 'date_debut_cotis' && !empty($this->_is_cotis) && $this->isFee()) {
            $label = $this->_fields[$field]['cotlabel'];
        }
        //replace "&nbsp;"
        $label = str_replace('&nbsp;', ' ', $label);
        //remove trailing ':' and then trim
        $label = trim(trim($label, ':'));
        return $label;
    }

    /**
     * Retrieve fields from database
     *
     * @param Db $zdb Database instance
     *
     * @return array<string>
     */
    public static function getDbFields(Db $zdb): array
    {
        $columns = $zdb->getColumns(self::TABLE);
        $fields = array();
        foreach ($columns as $col) {
            $fields[] = $col->getName();
        }
        return $fields;
    }

    /**
     * Get the relevant CSS class for current contribution
     *
     * @return string current contribution row class
     */
    public function getRowClass(): string
    {
        return ($this->_end_date != $this->_begin_date && $this->_is_cotis) ?
            'cotis-normal' : 'cotis-give';
    }

    /**
     * Retrieve member due date
     *
     * @param Db       $zdb       Database instance
     * @param ?integer $member_id Member identifier
     *
     * @return string|null
     */
    public static function getDueDate(Db $zdb, ?int $member_id): ?string
    {
        if (!$member_id) {
            return '';
        }
        try {
            $select = $zdb->select(self::TABLE, 'c');
            $select->columns(
                array(
                    'max_date' => new Expression('MAX(date_fin_cotis)')
                )
            )->join(
                array('ct' => PREFIX_DB . ContributionsTypes::TABLE),
                'c.' . ContributionsTypes::PK . '=ct.' . ContributionsTypes::PK,
                array()
            )->where(
                [Adherent::PK => $member_id]
            )
            ->where->notEqualTo('cotis_extension', 0);

            $results = $zdb->execute($select);
            $result = $results->current();
            $due_date = $result->max_date;

            //avoid bad dates in postgres and bad mysql return from zenddb
            if ($due_date == '0001-01-01 BC' || $due_date == '1901-01-01') {
                $due_date = '';
            }
            return $due_date;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred trying to retrieve member\'s due date',
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Detach a contribution from a transaction
     *
     * @param Db    $zdb        Database instance
     * @param Login $login      Login instance
     * @param int   $trans_id   Transaction identifier
     * @param int   $contrib_id Contribution identifier
     *
     * @return boolean
     */
    public static function unsetTransactionPart(Db $zdb, Login $login, int $trans_id, int $contrib_id): bool
    {
        try {
            //first, we check if contribution is part of transaction
            $c = new Contribution($zdb, $login, (int)$contrib_id);
            if ($c->isTransactionPartOf($trans_id)) {
                $update = $zdb->update(self::TABLE);
                $update->set(
                    array(Transaction::PK => null)
                )->where(
                    [self::PK => $contrib_id]
                );
                $zdb->execute($update);
                return true;
            } else {
                Analog::log(
                    'Contribution #' . $contrib_id .
                    ' is not actually part of transaction #' . $trans_id,
                    Analog::WARNING
                );
                return false;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Unable to detach contribution #' . $contrib_id .
                ' to transaction #' . $trans_id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Set a contribution as a transaction part
     *
     * @param Db  $zdb        Database instance
     * @param int $trans_id   Transaction identifier
     * @param int $contrib_id Contribution identifier
     *
     * @return boolean
     */
    public static function setTransactionPart(Db $zdb, int $trans_id, int $contrib_id): bool
    {
        try {
            $update = $zdb->update(self::TABLE);
            $update->set(
                array(Transaction::PK => $trans_id)
            )->where([self::PK => $contrib_id]);

            $zdb->execute($update);
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to attach contribution #' . $contrib_id .
                ' to transaction #' . $trans_id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Is current contribution a membership fee
     *
     * @return boolean
     */
    public function isFee(): bool
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
    public function isTransactionPartOf(int $id): bool
    {
        if ($this->isTransactionPart()) {
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
    public function isTransactionPart(): bool
    {
        return $this->_transaction != null;
    }

    /**
     * Execute post contribution script
     *
     * @param ExternalScript       $es     External script to execute
     * @param ?array<string,mixed> $extra  Extra information on contribution
     *                                     Defaults to null
     * @param ?array<string,mixed> $pextra Extra information on payment
     *                                     Defaults to null
     *
     * @return string|bool Script return value on success, values and script output on fail
     */
    public function executePostScript(
        ExternalScript $es,
        array $extra = null,
        array $pextra = null
    ): string|bool {
        global $preferences;

        $payment = array(
            'type'  => $this->getPaymentType()
        );

        if ($pextra !== null) {
            $payment = array_merge($payment, $pextra);
        }

        if (!file_exists(GALETTE_CACHE_DIR . '/pdf_contribs')) {
            @mkdir(GALETTE_CACHE_DIR . '/pdf_contribs');
        }

        $voucher_path = null;
        if (isset($this->_id)) {
            $voucher = new PdfContribution($this, $this->zdb, $preferences);
            $voucher->store(GALETTE_CACHE_DIR . '/pdf_contribs');
            $voucher_path = $voucher->getPath();
        }

        $contrib = array(
            'id'        => $this->_id,
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

        if ($this->_member !== null) {
            $m = new Adherent($this->zdb, (int)$this->_member);
            $member = array(
                'id'            => (int)$this->_member,
                'name'          => $m->sfullname,
                'email'         => $m->email,
                'organization'  => ($m->isCompany() ? 1 : 0),
                'status'        => array(
                    'id'    => $m->status,
                    'label' => $m->sstatus
                ),
                'country'       => $m->country
            );

            if ($m->isCompany()) {
                $member['organization_name'] = $m->company_name;
            }

            $contrib['member'] = $member;
        }

        if ($extra !== null) {
            $contrib = array_merge($contrib, $extra);
        }

        $res = $es->send($contrib);

        if ($res !== true) {
            Analog::log(
                'An error occurred calling post contribution ' .
                "script:\n" . $es->getOutput(),
                Analog::ERROR
            );
            $res = _T("Contribution information") . "\n";
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
    public function getRawType(): string
    {
        if ($this->isFee()) {
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
    public function getTypeLabel(): string
    {
        if ($this->isFee()) {
            return _T("Membership");
        } else {
            return _T("Donation");
        }
    }

    /**
     * Get payment type label
     *
     * @return string
     */
    public function getPaymentType(): string
    {
        if ($this->_payment_type === null) {
            return '-';
        }

        $ptype = new PaymentType($this->zdb, $this->payment_type);
        return $ptype->getName();
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return mixed the called property
     */
    public function __get(string $name)
    {

        $forbidden = array('is_cotis');
        $virtuals = array('duration', 'model', 'raw_date',
            'raw_begin_date', 'raw_end_date'
        );

        $rname = '_' . $name;

        if (in_array($name, $forbidden)) {
            Analog::log(
                "Call to __get for '$name' is forbidden!",
                Analog::WARNING
            );

            switch ($name) {
                case 'is_cotis':
                    return $this->isFee();
                default:
                    throw new \RuntimeException("Call to __get for '$name' is forbidden!");
            }
        } elseif (
            property_exists($this, $rname)
            || in_array($name, $virtuals)
        ) {
            switch ($name) {
                case 'raw_date':
                case 'raw_begin_date':
                case 'raw_end_date':
                    $rname = '_' . substr($name, 4);
                    if ($this->$rname !== null && $this->$rname != '') {
                        try {
                            $d = new \DateTime($this->$rname);
                            return $d;
                        } catch (Throwable $e) {
                            //oops, we've got a bad date :/
                            Analog::log(
                                'Bad date (' . $this->$rname . ') | ' .
                                $e->getMessage(),
                                Analog::INFO
                            );
                            throw $e;
                        }
                    }
                    break;
                case 'date':
                case 'begin_date':
                case 'end_date':
                    if ($this->$rname !== null && $this->$rname != '') {
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
                case 'duration':
                    if (isset($this->_is_cotis)) {
                        // Caution : the end_date stored is actually the due date.
                        // Adding a day to compute the next_begin_date is required
                        // to return the right number of months.
                        $next_begin_date = new \DateTime($this->_end_date ?? $this->_begin_date);
                        $next_begin_date->add(new \DateInterval('P1D'));
                        $begin_date = new \DateTime($this->_begin_date);
                        $diff = $next_begin_date->diff($begin_date);
                        return (int)$diff->format('%y') * 12 + (int)$diff->format('%m');
                    } else {
                        return '';
                    }
                case 'model':
                    if (!isset($this->_is_cotis)) {
                        return null;
                    }
                    return ($this->isFee()) ?
                        PdfModel::INVOICE_MODEL : PdfModel::RECEIPT_MODEL;
                default:
                    if (property_exists($this, $rname)) {
                        if (isset($this->$rname)) {
                            return $this->$rname;
                        }
                    } else {
                        throw new \LogicException("Property '" . __CLASS__ . "::$rname' does not exist!");
                    }
            }
        } else {
            Analog::log(
                "Unknown property '$rname'",
                Analog::WARNING
            );
            return null;
        }
    }

    /**
     * Global isset method
     * Required for twig to access properties via __get
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        $forbidden = array('is_cotis');
        $virtuals = array('duration', 'model', 'raw_date',
            'raw_begin_date', 'raw_end_date'
        );

        $rname = '_' . $name;

        if (in_array($name, $forbidden)) {
            switch ($name) {
                case 'is_cotis':
                    return true;
            }
        } elseif (
            property_exists($this, $rname)
            || in_array($name, $virtuals)
        ) {
            return true;
        }

        return false;
    }


    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param mixed  $value a relevant value for the property
     *
     * @return void
     */
    public function __set(string $name, $value): void
    {
        global $preferences;

        $forbidden = array('fields', 'is_cotis', 'end_date');

        if (!in_array($name, $forbidden)) {
            $rname = '_' . $name;
            switch ($name) {
                case 'transaction':
                    if (is_int($value)) {
                        $this->$rname = new Transaction($this->zdb, $this->login, $value);
                    } else {
                        Analog::log(
                            'Trying to set a transaction from an id that is not an integer.',
                            Analog::WARNING
                        );
                    }
                    break;
                case 'type':
                    $this->setContributionType($value);
                    break;
                case 'begin_date':
                    try {
                        $d = \DateTime::createFromFormat(__("Y-m-d"), $value);
                        if ($d === false) {
                            throw new \Exception('Incorrect format');
                        }
                        $this->_begin_date = $d->format('Y-m-d');
                    } catch (Throwable $e) {
                        Analog::log(
                            'Wrong date format. field: ' . $name .
                            ', value: ' . $value . ', expected fmt: ' .
                            __("Y-m-d") . ' | ' . $e->getMessage(),
                            Analog::INFO
                        );
                        $this->errors[] = sprintf(
                            //TRANS: %1$s is the date format, %2$s is the field name
                            _T('- Wrong date format (%1$s) for %2$s!'),
                            __("Y-m-d"),
                            $this->_fields['date_debut_cotis']['label']
                        );
                    }
                    break;
                case 'amount':
                    if (is_numeric($value) && $value > 0) {
                        $this->$rname = $value;
                    } else {
                        Analog::log(
                            'Trying to set an amount with a non numeric value, ' .
                            'or with a zero value',
                            Analog::WARNING
                        );
                    }
                    break;
                case 'member':
                    if (is_int($value)) {
                        //set type
                        $this->$rname = $value;
                    }
                    break;
                case 'payment_type':
                    $ptypes = new PaymentTypes(
                        $this->zdb,
                        $preferences,
                        $this->login
                    );
                    $list = $ptypes->getList();
                    if (isset($list[$value])) {
                        $this->_payment_type = $value;
                    } else {
                        Analog::log(
                            'Unknown payment type ' . $value,
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

    /**
     * Flag creation mail sending
     *
     * @param boolean $send True (default) to send creation email
     *
     * @return self
     */
    public function setSendmail(bool $send = true): self
    {
        $this->sendmail = $send;
        return $this;
    }

    /**
     * Should we send administrative emails to member?
     *
     * @return boolean
     */
    public function sendEMail(): bool
    {
        return $this->sendmail;
    }

    /**
     * Handle files (dynamics files)
     *
     * @param array<string, mixed> $files Files sent
     *
     * @return array<string>|true
     */
    public function handleFiles(array $files): bool|array
    {
        $this->errors = [];

        $this->dynamicsFiles($files);

        if (count($this->errors) > 0) {
            Analog::log(
                'Some errors has been threw attempting to edit/store a contribution files' . "\n" .
                print_r($this->errors, true),
                Analog::ERROR
            );
            return $this->errors;
        } else {
            return true;
        }
    }

    /**
     * Get required fields list
     *
     * @return array<string, int>
     */
    public function getRequired(): array
    {
        return [
            'id_type_cotis'     => 1,
            'id_adh'            => 1,
            'date_enreg'        => 1,
            'date_debut_cotis'  => 1,
            'date_fin_cotis'    => $this->isFee() ? 1 : 0,
            'montant_cotis'     => $this->isFee() ? 1 : 0
        ];
    }

    /**
     * Can current logged-in user display contribution
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
        if (!$this->id || $login->id == $this->_member || $login->isAdmin() || $login->isStaff()) {
            return true;
        }

        //parent can see their children contributions
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

    /**
     * Set contribution type and determine if it is a contribution or a donation
     *
     * @param int|null $type Type
     *
     * @return self
     */
    public function setContributionType(?int $type): self
    {
        if (is_int($type)) {
            //set type
            $this->_type = new ContributionsTypes($this->zdb, $type);
            //set is_cotis according to type
            if ($this->_type->extension == 0) {
                $this->_is_cotis = false;
            } else {
                $this->_is_cotis = true;
            }
        } else {
            Analog::log(
                'Trying to set a type from an id that is not an integer.',
                Analog::WARNING
            );
        }

        return $this;
    }
}
