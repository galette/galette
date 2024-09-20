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
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Galette\Helpers\EntityHelper;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\IsNull;
use Laminas\Db\Sql\Predicate\Operator;
use Laminas\Db\Sql\Predicate\PredicateSet;
use Throwable;
use Galette\Core\Db;
use Analog\Analog;

/**
 * Scheduled payment
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[ORM\Entity]
#[ORM\Table(name: 'orm_payments_schedules')]
class ScheduledPayment
{
    use EntityHelper;

    public const TABLE = 'payments_schedules';
    public const PK = 'id_schedule';
    private Db $zdb;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_schedule', type: 'integer')]
    //FIXME: does not works :/
    //#[ORM\SequenceGenerator(sequenceName: 'galette_payments_schedules_id_seq', initialValue: 1)]
    private int $id;
    #[ORM\ManyToOne(targetEntity: Contribution::class)]
    #[ORM\JoinColumn(
        name: Contribution::PK,
        referencedColumnName: Contribution::PK,
        nullable: false,
        onDelete: 'cascade',
        options: [
            'unsigned' => true
        ]
    )]
    private int $id_contribution;
    private Contribution $contribution;
    #[ORM\ManyToOne(targetEntity: PaymentType::class)]
    #[ORM\JoinColumn(
        name: 'id_paymenttype',
        referencedColumnName: PaymentType::PK,
        nullable: false,
        onDelete: 'restrict',
        options: [
            'unsigned' => true
        ]
    )]
    private int $id_paymenttype;
    private PaymentType $payment_type;
    #[ORM\Column(name: 'creation_date', type: 'date')]
    private string $creation_date;
    #[ORM\Column(name: 'scheduled_date', type: 'date')]
    private string $scheduled_date;
    #[ORM\Column(name: 'amount', type: 'decimal', precision: 15, scale: 2)]
    private float $amount;
    #[ORM\Column(name: 'paid', type: 'boolean', options: ['default' => false])]
    private bool $is_paid = false;
    #[ORM\Column(name: 'comment', type: 'text', nullable: true)]
    private ?string $comment = null;
    /** @var string[] */
    private array $errors = [];

    /**
     * Main constructor
     *
     * @param Db                                      $zdb  Database instance
     * @param ArrayObject<string,int|string>|int|null $args Arguments
     */
    public function __construct(Db $zdb, ArrayObject|int|null $args = null)
    {
        $this->zdb = $zdb;
        $now = new DateTime();
        $this->creation_date = $now->format('Y-m-d');
        $this->scheduled_date = $now->format('Y-m-d');

        $this->setFields();

        if (is_int($args)) {
            $this->load($args);
        } elseif ($args instanceof ArrayObject) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Load a scheduled payment from its identifier
     *
     * @param integer $id Identifier
     *
     * @return bool
     */
    public function load(int $id): bool
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->limit(1)->where([self::PK => $id]);

            $results = $this->zdb->execute($select);
            $rs = $results->current();

            if (!$rs) {
                return false;
            }
            $this->loadFromRS($rs);
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred loading scheduled payment #' . $id . "Message:\n" .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Load scheduled payment from a db ResultSet
     *
     * @param ArrayObject<string, int|string> $rs ResultSet
     *
     * @return void
     */
    private function loadFromRS(ArrayObject $rs): void
    {
        global $login;

        $pk = self::PK;
        $this->id = (int)$rs->$pk;
        $this->id_contribution = (int)$rs->{Contribution::PK};
        $this->contribution = new Contribution($this->zdb, $login, $this->id_contribution);
        $this->id_paymenttype = (int)$rs->id_paymenttype;
        $this->payment_type = new PaymentType($this->zdb, $this->id_paymenttype);
        $this->creation_date = $rs->creation_date;
        $this->scheduled_date = $rs->scheduled_date;
        $this->amount = (float)$rs->amount;
        $this->is_paid = (bool)$rs->paid;
        $this->comment = $rs->comment;
    }

    /**
     * Check data
     *
     * @param array<string,mixed> $data Data
     *
     * @return boolean
     */
    public function check(array $data): bool
    {
        global $login;

        $this->errors = [];
        $this->contribution = new Contribution($this->zdb, $login);

        if (!isset($data[Contribution::PK]) || !is_numeric($data[Contribution::PK])) {
            $this->errors[] = _T('Contribution is required');
        } else {
            if (!$this->contribution->load((int)$data[Contribution::PK])) {
                $this->errors[] = _T('Unable to load contribution');
            } else {
                if (isset($data['amount'])) {
                    //Amount is not required (will defaults to contribution amount)
                    if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
                        $this->errors[] = _T('Amount must be a positive number');
                    } else {
                        $not_allocated = $this->contribution->amount - $this->getAllocation($this->contribution->id);
                        if (isset($this->id)) {
                            $not_allocated += $this->amount;
                        }
                        if ($data['amount'] > $not_allocated) {
                            $this->errors[] = _T('Amount cannot be greater than non allocated amount');
                        }
                    }
                }
                if ($this->contribution->payment_type !== PaymentType::SCHEDULED) {
                    $this->errors[] = _T('Payment type for contribution must be set to scheduled');
                }
            }
        }

        if (!isset($data['id_paymenttype']) || !is_numeric($data['id_paymenttype'])) {
            $this->errors[] = _T('Payment type is required');
        } else {
            //no schedule inception allowed!
            if ((int)$data['id_paymenttype'] === PaymentType::SCHEDULED) {
                $this->errors[] = _T('Cannot schedule a scheduled payment!');
            } else {
                $this->payment_type = new PaymentType($this->zdb, (int)$data['id_paymenttype']);
            }
        }

        if (!isset($data['scheduled_date'])) {
            $this->errors[] = _T('Scheduled date is required');
        }

        if (count($this->errors) > 0) {
            return false;
        }

        $this
            ->setContribution((int)$data[Contribution::PK])
            ->setPaymentType((int)$data['id_paymenttype'])
            ->setCreationDate($data['creation_date'] ?? date('Y-m-d'))
            ->setScheduledDate($data['scheduled_date'])
            ->setAmount(isset($data['amount']) ? (float)$data['amount'] : $this->contribution->amount)
            ->setPaid(isset($data['paid']) ? (bool)$data['paid'] : false)
            ->setComment($data['comment'] ?? null);

        return true;
    }

    /**
     * Store scheduled payment in database
     *
     * @return boolean
     */
    public function store(): bool
    {
        $data = array(
            Contribution::PK => $this->contribution->id,
            'id_paymenttype' => $this->payment_type->id,
            'scheduled_date' => $this->scheduled_date,
            'amount' => $this->amount,
            'paid' => ($this->is_paid ? true : ($this->zdb->isPostgres() ? 'false' : 0)),
            'comment' => $this->comment
        );
        try {
            if (isset($this->id) && $this->id > 0) {
                $update = $this->zdb->update(self::TABLE);
                $update->set($data)->where([self::PK => $this->id]);
                $this->zdb->execute($update);
            } else {
                $data['creation_date'] = $this->creation_date;
                $insert = $this->zdb->insert(self::TABLE);
                $insert->values($data);
                $add = $this->zdb->execute($insert);
                if (!$add->count() > 0) {
                    Analog::log('Not stored!', Analog::ERROR);
                    return false;
                }

                $this->id = $this->zdb->getLastGeneratedValue($this);
            }
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred storing shceduled payment: ' . $e->getMessage() .
                "\n" . print_r($data, true),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Remove current
     *
     * @return boolean
     */
    public function remove(): bool
    {
        $id = $this->id;

        try {
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where([self::PK => $id]);
            $this->zdb->execute($delete);
            Analog::log(
                'Scheduled Payment #' . $id .  ' deleted successfully.',
                Analog::INFO
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to delete scheduled payment ' . $id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Get identifier
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Get contribution
     *
     * @return Contribution
     */
    public function getContribution(): Contribution
    {
        return $this->contribution;
    }

    /**
     * Set contribution
     *
     * @param int|Contribution $contribution Contribution instance or id
     *
     * @return self
     */
    public function setContribution(int|Contribution $contribution): self
    {
        if (is_int($contribution)) {
            global $login;
            try {
                $contrib = new Contribution($this->zdb, $login);
                if ($contrib->load($contribution)) {
                    $this->contribution = $contrib;
                } else {
                    throw new \RuntimeException('Cannot load contribution #' . $contribution);
                }
            } catch (Throwable $e) {
                Analog::log(
                    'Unable to load contribution #' . $contribution . ' | ' . $e->getMessage(),
                    Analog::ERROR
                );
                $this->errors[] = _T('Unable to load contribution');
            }
        } else {
            $this->contribution = $contribution;
        }
        $this->id_contribution = $this->contribution->id;
        return $this;
    }

    /**
     * Get payment type
     *
     * @return PaymentType
     */
    public function getPaymentType(): PaymentType
    {
        global $preferences;

        return $this->payment_type ?? new PaymentType($this->zdb, $preferences->pref_default_paymenttype);
    }

    /**
     * Set payment type
     *
     * @param int|PaymentType $payment_type Payment type instance or id
     *
     * @return self
     */
    public function setPaymentType(int|PaymentType $payment_type): self
    {
        if (is_int($payment_type)) {
            try {
                $ptype = new PaymentType($this->zdb);
                if ($ptype->load($payment_type)) {
                    $this->payment_type = $ptype;
                } else {
                    throw new \RuntimeException('Cannot load payment type #' . $payment_type);
                }
            } catch (Throwable $e) {
                Analog::log(
                    'Unable to load payment type #' . $payment_type . ' | ' . $e->getMessage(),
                    Analog::ERROR
                );
                $this->errors[] = _T('Unable to load payment type');
            }
        } else {
            $this->payment_type = $payment_type;
        }
        $this->id_paymenttype = $this->payment_type->id;
        return $this;
    }

    /**
     * Get creation date
     *
     * @param bool $formatted Get formatted date, or DateTime object
     *
     * @return string|DateTime|null
     */
    public function getCreationDate(bool $formatted = true): string|DateTime|null
    {
        return $this->getDate('creation_date', $formatted);
    }

    /**
     * Set creation date
     *
     * @param string $creation_date Creation date
     *
     * @return self
     */
    public function setCreationDate(string $creation_date): self
    {
        $this->setDate('creation_date', $creation_date);
        return $this;
    }

    /**
     * Get scheduled date
     *
     * @param bool $formatted Get formatted date, or DateTime object
     *
     * @return string|DateTime|null
     */
    public function getScheduledDate(bool $formatted = true): string|DateTime|null
    {
        return $this->getDate('scheduled_date', $formatted);
    }

    /**
     * Set scheduled date
     *
     * @param string $scheduled_date Scheduled date
     *
     * @return self
     */
    public function setScheduledDate(string $scheduled_date): self
    {
        $this->setDate('scheduled_date', $scheduled_date);
        return $this;
    }

    /**
     * Get amount
     *
     * @return float
     */
    public function getAmount(): ?float
    {
        return $this->amount ?? null;
    }

    /**
     * Set amount
     *
     * @param float $amount Amount
     *
     * @return self
     */
    public function setAmount(float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * Is payment done?
     *
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->is_paid;
    }

    /**
     * Set paid
     *
     * @param bool $is_paid Paid status
     *
     * @return self
     */
    public function setPaid(bool $is_paid = true): self
    {
        $this->is_paid = $is_paid;
        return $this;
    }

    /**
     * Get comment
     *
     * @return ?string
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * Set comment
     *
     * @param ?string $comment Comment
     *
     * @return self
     */
    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * Is a contribution handled from a scheduled payment?
     *
     * @param int $id_cotis Contribution identifier
     *
     * @return bool
     * @throws Throwable
     */
    public function isContributionHandled(int $id_cotis): bool
    {
        $select = $this->zdb->select(self::TABLE);
        $select->limit(1)->where([Contribution::PK => $id_cotis]);

        $results = $this->zdb->execute($select);
        return ($results->count() > 0);
    }

    /**
     * Get allocated amount
     *
     * @param int $id_cotis Contribution identifier
     *
     * @return float
     * @throws Throwable
     */
    public function getAllocation(int $id_cotis): float
    {
        $select = $this->zdb->select(self::TABLE);
        $select->columns(['allocation' => new Expression('SUM(amount)')]);
        $select->where([Contribution::PK => $id_cotis]);

        $results = $this->zdb->execute($select);
        $result = $results->current();
        return (float)($result->allocation ?? 0);
    }

    /**
     * Get allocated amount for current contribution
     *
     * @return float
     * @throws Throwable
     */
    public function getAllocated(): float
    {
        return $this->getAllocation($this->contribution->id);
    }

    /**
     * Get missing amount
     *
     * @return float
     */
    public function getMissingAmount(): float
    {
        return $this->contribution->amount - $this->getAllocated();
    }

    /**
     * Is scheduled payment fully allocated?
     *
     * @param Contribution $contrib Contribution
     *
     * @return bool
     */
    public function isFullyAllocated(Contribution $contrib): bool
    {
        return !($this->getAllocation($contrib->id) < $contrib->amount);
    }

    /**
     * Get not fully allocated scheduled payments
     *
     * @return Contribution[]
     */
    public function getNotFullyAllocated(): array
    {
        $select = $this->zdb->select(Contribution::TABLE, 'c');
        $select->columns([Contribution::PK, 'montant_cotis']);
        $select->quantifier('DISTINCT');

        $select->join(
            array('s' => PREFIX_DB . self::TABLE),
            //$on,
            'c.' . Contribution::PK . '=s.' . Contribution::PK,
            array('allocated' => new Expression('SUM(s.amount)')),
            $select::JOIN_LEFT
        );

        $select->group('c.' . Contribution::PK);
        $select->where(['c.type_paiement_cotis' => PaymentType::SCHEDULED]);
        $select->having([
            new PredicateSet(
                array(
                    new Operator(
                        /** @phpstan-ignore-next-line  */
                        new \Laminas\Db\Sql\Predicate\Expression('SUM(s.amount)'),
                        '<',
                        new \Laminas\Db\Sql\Predicate\Expression('c.montant_cotis')
                    ),
                    /** @phpstan-ignore-next-line  */
                    new IsNull(new \Laminas\Db\Sql\Predicate\Expression('SUM(s.amount)'))
                ),
                PredicateSet::OP_OR
            )
        ]);

        $results = $this->zdb->execute($select);

        return $results->toArray();
    }

    /**
     * Get errors
     *
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
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
                'label'    => _T('Scheduled payment ID'), //not a field in the form
                'propname' => 'id'
            ),
            Contribution::PK       => array(
                'label'    => _T('Contribution ID'), //not a field in the form
                'propname' => 'contribution'
            ),
            'id_paymenttype'   => array(
                'label'    => _T('Payment type:'),
                'propname' => 'payment_type'
            ),
            'creation_date'    => array(
                'label'    => _T('Record date:'),
                'propname' => 'creation_date'
            ),
            'scheduled_date'   => array(
                'label'    => _T('Scheduled date:'),
                'propname' => 'scheduled_date'
            ),
            'amount'           => array(
                'label'    => _T('Amount:'),
                'propname' => 'amount'
            ),
            'paid'          => array(
                'label'    => _T('Paid'),
                'propname' => 'is_paid'
            ),
            'comment'          => array(
                'label'    => _T('Comments:'),
                'propname' => 'comment'
            )
        );

        return $this;
    }
}
