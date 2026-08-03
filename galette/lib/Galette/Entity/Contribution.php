<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Entity;

use ArrayObject;
use DateInterval;
use Galette\Entity\Attributes\Column;
use Safe\DateTime;
use Galette\Events\GaletteEvent;
use Galette\Features\HasEvent;
use Galette\Interfaces\AccessManagementInterface;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;
use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\IO\ExternalScript;
use Galette\IO\PdfContribution;
use Galette\Repository\PaymentTypes;
use Galette\Features\Dynamics;
use Galette\Helpers\EntityHelper;

use function Safe\mkdir;

/**
 * Contribution class for galette
 * Manage membership fees and donations.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property int                                  $id
 * @property ?string                              $date
 * @property ?DateTime                            $raw_date
 * @property ?int                                 $member
 * @property ?ContributionsTypes                  $type
 * @property ?float                               $amount
 * @property ?int                                 $payment_type
 * @property ?float                               $orig_amount
 * @property ?string                              $info
 * @property ?string                              $begin_date
 * @property ?DateTime                            $raw_begin_date
 * @property ?string                              $end_date
 * @property ?DateTime                            $raw_end_date
 * @property ?Transaction                         $transaction
 * @property ?int                                 $extension
 * @property int                                  $duration
 * @property ?int                                 $model
 * @property array<string, array<string, string>> $fields
 */
class Contribution implements AccessManagementInterface
{
    use Dynamics;
    use HasEvent;
    use EntityHelper {
        getFieldLabel as protected trait_getFieldLabel;
        __isset as protected trait___isset;
    }

    public const string TABLE = 'cotisations';
    public const string PK = 'id_cotis';

    public const string TYPE_FEE = 'fee';
    public const string TYPE_DONATION = 'donation';

    public const int STATUS_NEVER = -1;
    public const int STATUS_UNKNOWN = 0;
    public const int STATUS_UPTODATE = 1;
    public const int STATUS_DUEFREE = 2;
    public const int STATUS_IMPENDING = 3;
    public const int STATUS_LATE = 4;
    public const int STATUS_OLD = 5;

    #[Column(name: 'id_cotis', insertable: false, updatable: false)]
    private int $id;
    #[Column(name: 'date_enreg')]
    private ?string $date = null;
    #[Column(name: 'id_adh')]
    private ?int $member = null;
    #[Column(name: 'id_type_cotis')]
    private ?ContributionsTypes $type = null;
    #[Column(name: 'montant_cotis')]
    private ?float $amount = null;
    #[Column(name: 'type_paiement_cotis')]
    private ?int $payment_type;
    private ?float $orig_amount = null;
    private ?string $info = null;
    private ?string $begin_date = null;
    private ?string $end_date = null;
    private ?Transaction $transaction = null;
    private bool $is_cotis;
    private ?int $extension = null;
    /** @var array<int, PaymentType> */
    private array $ptypes_list;

    /** @var array<string> */
    protected array $errors = [];

    private bool $sendmail = false;
    private bool $checklogin = true;

    /** @var string[] */
    protected array $forbidden_fields = ['is_cotis'];

    /** @var string[] */
    protected array $virtual_fields = [
        'duration',
        'model',
        'raw_date',
        'raw_begin_date',
        'raw_end_date',
    ];

    /**
     * Default constructor
     *
     * @param Db                                                          $zdb   Database
     * @param Login                                                       $login Login instance
     * @param null|int|array<string,mixed>|ArrayObject<string,int|string> $args  Either a ResultSet row to load
     *                                                                           a specific contribution, or a type id
     *                                                                           to just instantiate object
     */
    public function __construct(
        private Db $zdb,
        private Login $login,
        int|array|ArrayObject|null $args = null
    ) {
        global $preferences;
        $this->payment_type = $preferences->pref_default_paymenttype;

        $this
            ->setFields()
            ->withAddEvent()
            ->withEditEvent()
            ->withoutDeleteEvent()
            ->activateEvents();


        if (is_int($args)) {
            $this->load($args);
        } elseif (is_array($args)) {
            $this->loadFromArray($args);
        } elseif (is_object($args)) {
            $this->loadFromRS($args);
        }

        $this->loadDynamicFields();
    }

    /**
     * Set fields, must populate $this->fields
     */
    protected function setFields(): self
    {
        $this->fields = [
            'id_cotis'            => [
                'label'    => _T('Contribution id'), //not a field in the form
                'propname' => 'id'
            ],
            Adherent::PK          => [
                'label'    => _T("Contributor:"),
                'propname' => 'member'
            ],
            ContributionsTypes::PK => [
                'label'    => _T("Contribution type:"),
                'propname' => 'type'
            ],
            'montant_cotis'       => [
                'label'    => _T("Amount:"),
                'propname' => 'amount'
            ],
            'type_paiement_cotis' => [
                'label'    => _T("Payment type:"),
                'propname' => 'payment_type'
            ],
            'info_cotis'          => [
                'label'    => _T("Comments:"),
                'propname' => 'info'
            ],
            'date_enreg'          => [
                'label'    => _T('Date'), //not a field in the form
                'propname' => 'date'
            ],
            'date_debut_cotis'    => [
                'label'    => _T("Date of contribution:"),
                'cotlabel' => _T("Start date of membership:"), //if contribution is a membership fee, label differs
                'propname' => 'begin_date'
            ],
            'date_fin_cotis'      => [
                'label'    => _T("End date of membership:"),
                'propname' => 'end_date'
            ],
            Transaction::PK       => [
                'label'    => _T('Transaction ID'), //not a field in the form
                'propname' => 'transaction'
            ],
            //this one is not really a field, but is required in some cases...
            //adding it here make more simple to check required fields
            'duree_mois_cotis'    => [
                'label'    => _T("Membership extension:"),
                'propname' => 'extension'
            ]
        ];

        return $this;
    }

    /**
     * Sets end contribution date
     */
    private function retrieveEndDate(): void
    {
        global $preferences;

        $now = new DateTime();
        $begin_date = new DateTime($this->begin_date);
        $original_begin_date = clone $begin_date;

        if ($this->type->extension > ContributionsTypes::DONATION_TYPE && $preferences->pref_beg_membership == '') {
            $dext = new DateInterval('P' . $this->type->extension . 'M');
            $end_date = $begin_date->add($dext);
        } elseif ($preferences->pref_beg_membership != '') {
            //case beginning of membership
            [$j, $m] = explode('/', (string)$preferences->pref_beg_membership);
            $next_begin_date = new DateTime($begin_date->format('Y') . '-' . $m . '-' . $j);
            while ($next_begin_date <= $begin_date) {
                $next_begin_date->add(new DateInterval('P1Y'));
            }

            if ($preferences->pref_membership_offermonths > 0) {
                //count days until next membership begin date
                $diff1 = (int)$now->diff($next_begin_date)->format('%a');

                //count days between next membership begin date and offered months
                $tdate = clone $next_begin_date;
                $tdate->sub(new DateInterval('P' . $preferences->pref_membership_offermonths . 'M'));
                $diff2 = (int)$next_begin_date->diff($tdate)->format('%a');

                //when number of days until next membership begin date is less than or equal to the offered months, it's free :)
                if ($diff1 <= $diff2) {
                    $next_begin_date->add(new DateInterval('P1Y'));
                }
            }

            $end_date = clone $next_begin_date;
        } elseif ($preferences->pref_membership_ext != '' && $preferences->pref_membership_ext != 0) {
            //case membership extension
            if ($this->extension == null) {
                $this->extension = $preferences->pref_membership_ext;
            }
            $dext = new DateInterval('P' . $this->extension . 'M');
            // Caution : the end_date to retrieve is the day before the next_begin_date.
            $next_begin_date = $begin_date->add($dext);
            $end_date = clone $next_begin_date;
        } else {
            throw new \RuntimeException(
                'Unable to define end date; none of pref_beg_membership nor pref_membership_ext are defined!'
            );
        }

        // Caution : the end_date to retrieve is the day before the next_begin_date.
        $end_date->sub(new DateInterval('P1D'));
        // Edge case: when begin_date is exactly 1 day before pref_beg_membership,
        // end_date would equal begin_date (a zero-duration membership). Advance by one year.
        if ($end_date <= $original_begin_date) {
            $end_date->add(new DateInterval('P1Y'));
        }
        $this->end_date = $end_date->format('Y-m-d');
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
        global $preferences;

        if (!$this->login->isLogged() && $this->login->id == '') {
            return false;
        }

        try {
            $select = $this->zdb->select(self::TABLE, 'c');
            $select->join(
                ['a' => PREFIX_DB . Adherent::TABLE],
                'c.' . Adherent::PK . '=a.' . Adherent::PK,
                []
            );
            //restrict query on current member id if he's not admin nor staff member
            if (!$this->login->isAdmin() && !$this->login->isStaff()) {
                if ($this->login->isGroupManager() && ($preferences->pref_bool_groupsmanagers_create_transactions || $preferences->pref_bool_groupsmanagers_see_transactions)) {
                    //limit to managed members from managed groups
                    $mgroups = $this->login->getManagedGroups();
                    $select->join(
                        ['users_groups' => PREFIX_DB . Group::GROUPSUSERS_TABLE],
                        'c.' . Adherent::PK . '=users_groups.' . Adherent::PK,
                        [],
                        $select::JOIN_LEFT
                    );
                    $select->where
                        ->nest()
                            ->in('users_groups.' . Group::PK, array_values($mgroups))
                            ->or
                            ->equalTo('a.' . Adherent::PK, $this->login->id)
                            ->or
                            ->equalTo('a.parent_id', $this->login->id)
                        ->unnest()
                        ->and
                        ->equalTo('c.' . self::PK, $id);
                } else {
                    $select->where
                        ->nest()
                            ->equalTo('a.' . Adherent::PK, $this->login->id)
                            ->or
                            ->equalTo('a.parent_id', $this->login->id)
                        ->unnest()
                        ->and
                        ->equalTo('c.' . self::PK, $id)
                    ;
                }
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
                'An error occurred attempting to load contribution #' . $id
                . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ArrayObject<string, int|string> $r the resultset row
     */
    private function loadFromRS(ArrayObject $r): void
    {
        $pk = self::PK;
        $this->id = (int)$r->$pk;
        $this->date = $r->date_enreg;
        $this->amount = (float)$r->montant_cotis;
        //save original amount, we need it for transactions parts calculations
        $this->orig_amount = (float)$r->montant_cotis;
        $this->payment_type = (int)$r->type_paiement_cotis;
        $this->info = $r->info_cotis;
        $this->begin_date = $r->date_debut_cotis;
        $end_date = $r->date_fin_cotis;
        //do not work with knows bad dates...
        //the one with BC comes from 0.63/pgsql demo... Why the hell a so
        //strange date? don't know :(
        if (
            $end_date !== '0000-00-00'
            && $end_date !== '1901-01-01'
            && $end_date !== '0001-01-01 BC'
        ) {
            $this->end_date = $r->date_fin_cotis;
        }
        $adhpk = Adherent::PK;
        $this->member = (int)$r->$adhpk;

        $transpk = Transaction::PK;
        if ($r->$transpk != '') {
            $this->transaction = new Transaction($this->zdb, $this->login, (int)$r->$transpk);
        } else {
            $this->transaction = null;
        }

        $this->setContributionType((int)$r->id_type_cotis);
        $this->loadDynamicFields();
    }

    /**
     * Populate object from an array
     *
     * @param array<string,mixed> $args Instanciation arguments
     */
    private function loadFromArray(array $args): void
    {
        global $preferences;

        $this->date = date("Y-m-d");
        if (isset($args['adh']) && $args['adh'] != '') {
            $this->member = (int)$args['adh'];
        }
        if (isset($args['amount'])) {
            $this->amount = $args['amount'];
        }
        if (isset($args['trans'])) {
            $this->transaction = new Transaction($this->zdb, $this->login, (int)$args['trans']);
            if (!isset($this->member)) {
                $this->member = $this->transaction->member;
            }
            $this->amount = $this->transaction->getMissingAmount();
            $this->payment_type = $this->transaction->payment_type;
        }
        $this->setContributionType((int)$args['type']);
        if (!$this->isFee()) {
            //for donations, begin date is current date
            $this->begin_date = $this->date;
        } else {
            if ($preferences->pref_membership_ext != '') {
                //calculate begin date for membership fee with membership extension
                $this->loadWithMembershipExt();
            } else {
                //calculate begin date for membership fee with beginning of membership date
                $this->loadWithEndofMembershipDate();
            }
            $this->retrieveEndDate();
        }
        if (isset($args['payment_type'])) {
            $this->setPaymentType((int)$args['payment_type']);
        }
    }

    /**
     * Contribution created with a membership extension
     */
    private function loadWithMembershipExt(): void
    {
        $this->begin_date = $this->date;
        $due_date = self::getDueDate($this->zdb, $this->member);
        if ($due_date != '') {
            $now = new DateTime();
            $due_date = new DateTime($due_date);
            if ($due_date < $now) {
                // Member didn't renew on time
                $this->begin_date = $now->format('Y-m-d');
            } else {
                // Caution : the next_begin_date is the day after the due_date.
                $next_begin_date = clone $due_date;
                $next_begin_date->add(new DateInterval('P1D'));
                $this->begin_date = $next_begin_date->format('Y-m-d');
            }
        }
    }

    /**
     * Contribution created with an en date of membership
     */
    private function loadWithEndofMembershipDate(): void
    {
        global $preferences;

        //compare dates only: due_date is stored without time, so $now must be
        //reset to midnight - otherwise, on the very last valid day of membership
        //(due_date === today), the member would be wrongly considered expired and
        //the next contribution would start one year in the past.
        $now = new DateTime();
        $now->setTime(0, 0);
        $due_date = self::getDueDate($this->zdb, $this->member);

        if ($due_date != '' && new DateTime($due_date) >= $now) {
            //member is still up to date: next contribution starts the day after
            //the current end of membership (continuous renewal).
            $next_begin_date = new DateTime($due_date);
            $next_begin_date->add(new DateInterval('P1D'));
        } else {
            //no current membership (new member or membership expired):
            //align begin date to the start of the current membership period,
            //that is the most recent membership begin date on or before now.
            //Note: we step back from the current year candidate instead of
            //cloning now and subtracting a year, which would overflow on
            //February 29th (2024-02-29 minus one year is not a valid date).
            [$j, $m] = explode('/', (string)$preferences->pref_beg_membership);
            $next_begin_date = new DateTime($now->format('Y') . '-' . $m . '-' . $j);
            while ($next_begin_date > $now) {
                $next_begin_date->sub(new DateInterval('P1Y'));
            }
        }
        $this->begin_date = $next_begin_date->format('Y-m-d');
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
        $this->errors = [];

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
                // fill up the contribution structure

                // now, check validity
                switch ($key) {
                    // dates
                    case 'date_enreg':
                    case 'date_debut_cotis':
                    case 'date_fin_cotis':
                        if ($value != '') {
                            $this->setDate($key, $value);
                        }
                        break;
                    case Adherent::PK:
                        if ($value != '') {
                            $member = new Adherent($this->zdb, (int)$value, false);
                            if (
                                $this->checklogin
                                && !$this->login->isStaff()
                                && !$this->login->isAdmin()
                                && !$this->login->isGroupManager(array_keys($member->getGroups()))
                            ) {
                                $this->errors[] = _T("- Please select a member from a group you manage.");
                                unset($this->member);
                            } else {
                                $this->member = (int)$value;
                            }
                        }
                        break;
                    case ContributionsTypes::PK:
                        if ($value != '') {
                            $this->setContributionType((int)$value);
                        }
                        break;
                    case 'montant_cotis':
                        //FIXME: this is a hack to allow comma as decimal separator
                        $value = strtr((string)$value, ',', '.');
                        if (!empty($value) || $value === '0') {
                            $this->amount = (float)$value;
                        }
                        if (!is_numeric($value) && $value !== '') {
                            $this->errors[] = _T("- The amount must be an integer!");
                        }
                        break;
                    case 'type_paiement_cotis':
                        if ($value != '') {
                            $this->setPaymentType((int)$value);
                        }
                        break;
                    case 'info_cotis':
                        $this->info = $value;
                        break;
                    case Transaction::PK:
                        if ($value != '') {
                            $this->transaction = new Transaction($this->zdb, $this->login, (int)$value);
                            if (!isset($values['type_paiement_cotis']) && isset($this->transaction->payment_type)) {
                                $this->payment_type = $this->transaction->payment_type;
                            }
                        }
                        break;
                    case 'duree_mois_cotis':
                        if ($preferences->pref_membership_ext != '' && $value != '') {
                            if (!is_numeric($value) || $value <= 0) {
                                $this->errors[] = _T("- The duration must be a positive integer!");
                            } else {
                                $this->$prop = (int)$value;
                                $this->retrieveEndDate();
                            }
                        }
                        break;
                }
            }
        }

        //check end date
        if (
            $preferences->pref_membership_ext == ''
            && $this->isFee()
            && new DateTime($this->end_date) <= new DateTime($this->begin_date)
        ) {
            $this->errors[] = _T("- The end date must be after the start date!");
        }


        // missing required fields?
        foreach ($required as $key => $val) {
            if ($val == 0) {
                continue;
            }
            $prop = $this->fields[$key]['propname'];

            if (!isset($disabled[$key]) && (!isset($this->$prop) || $this->$prop == '')) {
                $this->errors[] = sprintf(
                    //TRANS: parameter is an hTML link to the field with its name
                    _T('- Mandatory field %1$s empty.'),
                    '<a href="#' . $key . '">' . $this->getFieldLabel($key) . '</a>',
                );
            }
        }

        if ($this->transaction != null && $this->amount != null) {
            $missing = $this->transaction->getMissingAmount();
            //calculate new missing amount
            $missing = $missing + $this->orig_amount - $this->amount;
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
                'Some errors has been threw attempting to edit/store a contribution'
                . print_r($this->errors, true),
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
     * @return bool|string True if all is ok, false if error,
     * error message if overlap
     */
    public function checkOverlap(): bool|string
    {
        try {
            $select = $this->zdb->select(self::TABLE, 'c');
            //@phpstan-ignore property.notFound ("Access to an undefined property Laminas\Db\Sql\Where::$where" which exists)
            $select->columns(
                ['date_debut_cotis', 'date_fin_cotis']
            )->join(
                ['ct' => PREFIX_DB . ContributionsTypes::TABLE],
                'c.' . ContributionsTypes::PK . '=ct.' . ContributionsTypes::PK,
                []
            )->where([Adherent::PK => $this->member])
                ->where->notEqualTo('cotis_extension', ContributionsTypes::DONATION_TYPE)
                ->where->nest->nest
                ->greaterThanOrEqualTo('date_debut_cotis', $this->begin_date)
                ->lessThanOrEqualTo('date_debut_cotis', $this->end_date)
                ->unnest
                ->or->nest
                ->greaterThanOrEqualTo('date_fin_cotis', $this->begin_date)
                ->lessThanOrEqualTo('date_fin_cotis', $this->end_date);

            if (isset($this->id)) {
                $select->where->notEqualTo(self::PK, $this->id);
            }

            $results = $this->zdb->execute($select);
            if ($results->count() > 0) {
                $result = $results->current();

                $d_begin = new DateTime($result->date_debut_cotis);
                $d_end = new DateTime($result->date_fin_cotis);

                if ($d_begin->format('m-d') == $d_end->format('m-d') && $result->date_fin_cotis == $this->begin_date) {
                    //see https://bugs.galette.eu/issues/1762
                    return true;
                }

                return _T("- Membership period overlaps period starting at ")
                    . $d_begin->format(__("Y-m-d"));
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
     */
    public function store(): bool
    {
        global $hist, $emitter;

        if (count($this->errors) > 0) {
            throw new \RuntimeException(
                'Existing errors prevents storing contribution: '
                . print_r($this->errors, true)
            );
        }

        try {
            $this->zdb->beginTransaction();
            $values = [];
            $fields = self::getDbFields($this->zdb);
            foreach ($fields as $field) {
                $prop = $this->fields[$field]['propname'];
                if (!isset($this->$prop)) {
                    continue;
                }
                $values[$field] = match ($field) {
                    ContributionsTypes::PK, Transaction::PK => $this->$prop->id,
                    default => $this->$prop,
                };
            }

            //no end date for donation
            if (!$this->isFee()) {
                $values['date_fin_cotis'] = new Expression('NULL');
            }

            if (!isset($this->id)) {
                //we're inserting a new contribution
                unset($values[self::PK]);

                $insert = $this->zdb->insert(self::TABLE);
                $insert->values($values);
                $add = $this->zdb->execute($insert);

                if ($add->count() > 0) {
                    $this->id = $this->zdb->getLastGeneratedValue($this);

                    // logging
                    $hist->add(
                        _T("Contribution added"),
                        Adherent::getSName($this->zdb, $this->member)
                    );
                    $event = $this->getAddEventName();
                } else {
                    $hist->add(_T("Fail to add new contribution."));
                    throw new \Exception(
                        'An error occurred inserting new contribution!'
                    );
                }
            } else {
                //we're editing an existing contribution
                $update = $this->zdb->update(self::TABLE);
                $update->set($values)->where([self::PK => $this->id]);
                $edit = $this->zdb->execute($update);

                //edit == 0 does not mean there were an error, but that there
                //were nothing to change
                if ($edit->count() > 0) {
                    $hist->add(
                        _T("Contribution updated"),
                        Adherent::getSName($this->zdb, $this->member)
                    );
                }

                $event = $this->getEditEventName();
            }
            //update deadline
            if ($this->isFee()) {
                $this->updateDeadline();
            }

            //dynamic fields
            $this->dynamicsStore(true);

            $this->zdb->commit();
            $this->orig_amount = $this->amount;

            //send event at the end of process, once all has been stored
            if ($this->areEventsEnabled()) {
                $emitter->dispatch(new GaletteEvent($event, $this));
            }

            return true;
        } catch (Throwable $e) {
            if ($this->zdb->inTransaction()) {
                $this->zdb->rollback();
            }
            throw $e;
        }
    }

    /**
     * Update member deadline
     */
    private function updateDeadline(): bool
    {
        try {
            $due_date = self::getDueDate($this->zdb, $this->member);

            $due_date_update = $due_date != '' ? $due_date : new Expression('NULL');

            $update = $this->zdb->update(Adherent::TABLE);
            $update->set(
                ['date_echeance' => $due_date_update]
            )->where(
                [Adherent::PK => $this->member]
            );
            $this->zdb->execute($update);
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred updating member ' . $this->member
                . '\'s deadline |'
                . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Remove contribution from database
     *
     * @param bool $transaction Activate transaction mode (defaults to true)
     */
    public function remove(bool $transaction = true): bool
    {
        global $emitter;

        try {
            if ($transaction) {
                $this->zdb->beginTransaction();
            }

            $delete = $this->zdb->delete(self::TABLE);
            $delete->where([self::PK => $this->id]);
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
                $this->zdb->commit();
            }
            $emitter->dispatch(new GaletteEvent('contribution.remove', $this));
            return true;
        } catch (Throwable $e) {
            if ($transaction) {
                $this->zdb->rollback();
            }
            Analog::log(
                'An error occurred trying to remove contribution #'
                . $this->id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Get field label
     *
     * @param string $field Field name
     * @param string $entry Array entry to use (defaults to "label")
     */
    public function getFieldLabel(string $field, string $entry = 'label'): string
    {
        if ($field == 'date_debut_cotis' && !empty($this->is_cotis) && $this->isFee()) {
            $entry = 'cotlabel';
        }
        return $this->trait_getFieldLabel($field, $entry);
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
        $fields = [];
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
        return ($this->end_date != $this->begin_date && $this->is_cotis)
            ? 'cotis-normal' : 'cotis-give';
    }

    /**
     * Retrieve member due date
     *
     * @param Db   $zdb       Database instance
     * @param ?int $member_id Member identifier
     */
    public static function getDueDate(Db $zdb, ?int $member_id): ?string
    {
        if (!$member_id) {
            return '';
        }
        try {
            $select = $zdb->select(self::TABLE, 'c');
            $select->columns(
                [
                    'max_date' => new Expression('MAX(date_fin_cotis)')
                ]
            )->join(
                ['ct' => PREFIX_DB . ContributionsTypes::TABLE],
                'c.' . ContributionsTypes::PK . '=ct.' . ContributionsTypes::PK,
                []
            )->where(
                [Adherent::PK => $member_id]
            )
            ->where->notEqualTo('cotis_extension', ContributionsTypes::DONATION_TYPE);

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
     * @param int $trans_id Transaction identifier
     */
    public function unsetTransactionPart(int $trans_id): bool
    {
        try {
            //first, we check if contribution is part of transaction
            $c = new Contribution($this->zdb, $this->login, $this->id);
            if ($c->isTransactionPartOf($trans_id)) {
                $update = $this->zdb->update(self::TABLE);
                $update->set(
                    [Transaction::PK => null]
                )->where(
                    [self::PK => $this->id]
                );
                $this->zdb->execute($update);
                return true;
            } else {
                Analog::log(
                    'Contribution #' . $this->id
                    . ' is not actually part of transaction #' . $trans_id,
                    Analog::WARNING
                );
                return false;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Unable to detach contribution #' . $this->id
                . ' to transaction #' . $trans_id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Set a contribution as a transaction part
     *
     * @param int $trans_id Transaction identifier
     */
    public function setTransactionPart(int $trans_id): bool
    {
        try {
            $update = $this->zdb->update(self::TABLE);
            $update->set(
                [Transaction::PK => $trans_id]
            )->where([self::PK => $this->id]);

            $this->zdb->execute($update);
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to attach contribution #' . $this->id
                . ' to transaction #' . $trans_id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Is current contribution a membership fee
     */
    public function isFee(): bool
    {
        return $this->is_cotis ?? false;
    }

    /**
     * Is current contribution part of specified transaction
     *
     * @param int $id Transaction identifier
     */
    public function isTransactionPartOf(int $id): bool
    {
        if ($this->isTransactionPart()) {
            return $id == $this->transaction->id;
        } else {
            return false;
        }
    }

    /**
     * Is current contribution part of transaction
     */
    public function isTransactionPart(): bool
    {
        return $this->transaction != null;
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
        ?array $extra = null,
        ?array $pextra = null
    ): string|bool {
        global $preferences;

        $payment = [
            'id'    => $this->getPaymentTypeId(),
            'type'  => $this->getPaymentType()
        ];

        if ($pextra !== null) {
            $payment = array_merge($payment, $pextra);
        }

        if (!file_exists(GALETTE_CACHE_DIR . '/pdf_contribs')) {
            @mkdir(GALETTE_CACHE_DIR . '/pdf_contribs');
        }

        $voucher_path = null;
        if (isset($this->id)) {
            $voucher = new PdfContribution($this, $this->zdb, $preferences);
            $voucher->store(GALETTE_CACHE_DIR . '/pdf_contribs');
            $voucher_path = $voucher->getPath();
        }

        $contrib = [
            'id'        => $this->id,
            'date'      => $this->date,
            'type'      => $this->getRawType(),
            'amount'    => $this->amount,
            'voucher'   => $voucher_path,
            'category'  => [
                'id'    => $this->type->id,
                'label' => $this->type->libelle
            ],
            'payment'   => $payment
        ];

        if ($this->member !== null) {
            $m = new Adherent($this->zdb, (int)$this->member);
            $member = [
                'id'            => (int)$this->member,
                'name'          => $m->sfullname,
                'email'         => $m->email,
                'organization'  => ($m->isCompany() ? 1 : 0),
                'status'        => [
                    'id'    => $m->status,
                    'label' => $m->sstatus
                ],
                'country'       => $m->country
            ];

            if ($m->isCompany()) {
                $member['organization_name'] = $m->company_name;
            }

            $contrib['member'] = $member;
        }

        $contrib['auth_token'] = defined('SCRIPT_AUTH_TOKEN') ? SCRIPT_AUTH_TOKEN : '';

        if ($extra !== null) {
            $contrib = array_merge($contrib, $extra);
        }

        $res = $es->send($contrib);

        if ($res !== true) {
            Analog::log(
                'An error occurred calling post contribution '
                . "script:\n" . $es->getOutput(),
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
     * Get payment type id
     */
    public function getPaymentTypeId(): int
    {
        if ($this->payment_type === null) {
            return 0;
        }

        $ptype = new PaymentType($this->zdb, $this->payment_type);
        return $ptype->id;
    }

    /**
     * Get payment type label
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
     * Global getter method
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return mixed the called property
     */
    public function __get(string $name): mixed
    {
        if (in_array($name, $this->forbidden_fields)) {
            Analog::log(
                "Call to __get for '$name' is forbidden!",
                Analog::WARNING
            );

            return match ($name) {
                'is_cotis' => $this->isFee(),
                default => throw new \RuntimeException("Call to __get for '$name' is forbidden!"),
            };
        } elseif (
            property_exists($this, $name)
            || in_array($name, $this->virtual_fields)
        ) {
            switch ($name) {
                case 'raw_date':
                case 'raw_begin_date':
                case 'raw_end_date':
                    return $this->getDate(substr($name, 4), false);
                case 'date':
                case 'begin_date':
                case 'end_date':
                    return $this->getDate($name);
                case 'duration':
                    if (isset($this->is_cotis)) {
                        // Caution : the end_date stored is actually the due date.
                        // Adding a day to compute the next_begin_date is required
                        // to return the right number of months.
                        $next_begin_date = new DateTime($this->end_date ?? $this->begin_date);
                        $next_begin_date->add(new DateInterval('P1D'));
                        $begin_date = new DateTime($this->begin_date);
                        $diff = $next_begin_date->diff($begin_date);
                        return (int)$diff->format('%y') * 12 + (int)$diff->format('%m');
                    } else {
                        return '';
                    }
                    // no break
                case 'model':
                    if (!isset($this->is_cotis)) {
                        return null;
                    }
                    return ($this->isFee())
                        ? PdfModel::INVOICE_MODEL : PdfModel::RECEIPT_MODEL;
                case 'fields':
                    return $this->fields;
                default:
                    if (property_exists($this, $name)) {
                        if (isset($this->$name)) {
                            return $this->$name ?? null;
                        }
                    } else {
                        throw new \LogicException("Property '" . static::class . "::$name' does not exist!");
                    }
            }
        } else {
            Analog::log(
                "Unknown property '$name'",
                Analog::WARNING
            );
        }

        return null;
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param mixed  $value a relevant value for the property
     */
    public function __set(string $name, mixed $value): void
    {
        $forbidden = ['fields', 'is_cotis', 'end_date'];

        if (!in_array($name, $forbidden)) {
            switch ($name) {
                case 'transaction':
                    if (is_int($value)) {
                        $this->$name = new Transaction($this->zdb, $this->login, $value);
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
                    $this->setDate($name, $value);
                    break;
                case 'amount':
                    if (is_numeric($value) && $value > 0) {
                        $this->$name = (float)$value;
                    } else {
                        Analog::log(
                            'Trying to set an amount with a non numeric value, '
                            . 'or with a zero value',
                            Analog::WARNING
                        );
                    }
                    break;
                case 'member':
                    if (is_int($value)) {
                        //set type
                        $this->$name = $value;
                    }
                    break;
                case 'payment_type':
                    $this->setPaymentType((int)$value);
                    break;
                default:
                    Analog::log(
                        '[' . static::class . ']: Trying to set an unknown property ('
                        . $name . ')',
                        Analog::WARNING
                    );
                    break;
            }
        }
    }

    /**
     * Flag creation mail sending
     *
     * @param bool $send True (default) to send creation email
     */
    public function setSendmail(bool $send = true): self
    {
        $this->sendmail = $send;
        return $this;
    }

    /**
     * Should we send administrative emails to member?
     */
    public function sendEMail(): bool
    {
        return $this->sendmail;
    }

    /**
     * Handle files (dynamics files)
     *
     * @param array<UploadedFileInterface> $files Files sent
     *
     * @return array<string>|true
     */
    public function handleFiles(array $files): bool|array
    {
        $this->errors = [];

        $this->dynamicsFiles($files);

        if (count($this->errors) > 0) {
            Analog::log(
                'Some errors has been threw attempting to edit/store a contribution files' . "\n"
                . print_r($this->errors, true),
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
     * Can current logged-in user create a contribution?
     *
     * @param Login $login Login instance
     */
    public function canCreate(Login $login): bool
    {
        global $preferences;

        if (!$login->isLogged()) {
            return false;
        }

        if ($login->isAdmin() || $login->isStaff()) {
            return true;
        }
        return $preferences->pref_bool_groupsmanagers_create_contributions && $login->isGroupManager();
    }

    /**
     * Can current logged-in user display contribution?
     *
     * @param Login $login Login instance
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

        //groups managers can see contributions of their group members - if preferences is enabled
        if ($preferences->pref_bool_groupsmanagers_see_contributions && $login->isGroupManager()) {
            $member = new Adherent($this->zdb, (int)$this->member, false);
            return $login->isGroupManager(array_keys($member->getGroups()));
        }

        //parent can see their children contributions
        $parent = new Adherent($this->zdb);
        $parent
            ->disableAllDeps()
            ->enableDep('children')
            ->load($login->id);
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

    /**
     * Can current logged-in user edit a contribution?
     *
     * @param Login $login Login instance
     */
    public function canEdit(Login $login): bool
    {
        if (!$login->isLogged()) {
            return false;
        }
        return $login->isAdmin() || $login->isStaff();
    }

    /**
     * Can current logged-in user delete a contribution?
     *
     * @param Login $login Login instance
     */
    public function canDelete(Login $login): bool
    {
        return $this->canEdit($login);
    }

    /**
     * Set contribution type and determine if it is a contribution or a donation
     *
     * @param int $type Type
     */
    public function setContributionType(int $type): self
    {
        //set type
        $this->type = new ContributionsTypes($this->zdb, $type);
        //set is_cotis according to type
        $this->is_cotis = $this->type->extension != ContributionsTypes::DONATION_TYPE;

        return $this;
    }

    /**
     * Get prefix for events
     */
    protected function getEventsPrefix(): string
    {
        return 'contribution';
    }

    /**
     * Does contribution have attached scheduled payment?
     *
     * @throws Throwable
     */
    public function hasSchedule(): bool
    {
        $schedule = new ScheduledPayment($this->zdb);
        return $schedule->isContributionHandled($this->id ?? 0);
    }

    /**
     * Is schedule fully allocated
     *
     * @throws Throwable
     */
    public function isScheduleFullyAllocated(): bool
    {
        $schedule = new ScheduledPayment($this->zdb);
        return $schedule->isFullyAllocated($this);
    }

    /**
     * Set (and check) payment type
     *
     * @param int $value Payment type to set
     *
     * @throws Throwable
     */
    public function setPaymentType(int $value): void
    {
        global $preferences;

        if (!isset($this->ptypes_list)) {
            $ptypes = new PaymentTypes(
                $this->zdb,
                $preferences,
                $this->login
            );
            $this->ptypes_list = $ptypes->getList();
        }
        if (isset($this->ptypes_list[$value])) {
            if (isset($this->id) && $this->payment_type != $value && $this->hasSchedule()) {
                $this->errors[] = _T("Cannot change payment type if there is an attached scheduled payment");
            } else {
                $this->payment_type = $value;
            }
        } else {
            Analog::log(
                'Unknown payment type ' . $value,
                Analog::WARNING
            );
            $this->errors[] = _T("- Unknown payment type");
        }
    }

    /**
     * Disable login on checks
     */
    public function setNoCheckLogin(): self
    {
        $this->checklogin = false;
        return $this;
    }
}
