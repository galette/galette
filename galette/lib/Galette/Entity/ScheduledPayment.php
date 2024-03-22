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
use Laminas\Db\Sql\Expression;
use Throwable;
use Galette\Core\Db;
use Analog\Analog;
use Galette\Features\I18n;
use Galette\Features\Translatable;

/**
 * Scheduled payment
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class ScheduledPayment
{
    public const TABLE = 'payments_schedules';
    public const PK = 'id_schedule';

    private Db $zdb;
    private int $id;
    private Contribution $contribution;
    private PaymentType $payment_type;
    private DateTime $creation_date;
    private DateTime $scheduled_date;
    private float $amount;
    private bool $is_paid;
    private string $comment;

    /**
     * Main constructor
     *
     * @param Db                                      $zdb  Database instance
     * @param ArrayObject<string,int|string>|int|null $args Arguments
     */
    public function __construct(Db $zdb, ArrayObject|int $args = null)
    {
        $this->zdb = $zdb;
        if (is_int($args)) {
            $this->load($args);
        } elseif ($args instanceof ArrayObject) {
            $this->loadFromRs($args);
        }
    }

    /**
     * Load a scheduled payment from its identifier
     *
     * @param integer $id Identifier
     *
     * @return void
     */
    private function load(int $id): void
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->limit(1)->where([self::PK => $id]);

            $results = $this->zdb->execute($select);
            $rs = $results->current();

            $this->loadFromRs($rs);
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
    private function loadFromRs(ArrayObject $rs): void
    {
        $pk = self::PK;
        $this->id = $rs->$pk;
        //$this->name = $rs->type_name;
    }

    /**
     * Store scheduled payment in database
     *
     * @return boolean
     */
    public function store(): bool
    {
        return false;
        /*$data = array(
            'type_name' => $this->name
        );
        try {
            if (isset($this->id) && $this->id > 0) {
                if ($this->old_name !== null) {
                    $this->deleteTranslation($this->old_name);
                    $this->addTranslation($this->name);
                }

                $update = $this->zdb->update(self::TABLE);
                $update->set($data)->where([self::PK => $this->id]);
                $this->zdb->execute($update);
            } else {
                $insert = $this->zdb->insert(self::TABLE);
                $insert->values($data);
                $add = $this->zdb->execute($insert);
                if (!$add->count() > 0) {
                    Analog::log('Not stored!', Analog::ERROR);
                    return false;
                }

                $this->id = $this->zdb->getLastGeneratedValue($this);

                $this->addTranslation($this->name);
            }
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred storing payment type: ' . $e->getMessage() .
                "\n" . print_r($data, true),
                Analog::ERROR
            );
            throw $e;
        }*/
    }

    /**
     * Remove current
     *
     * @return boolean
     */
    public function remove(): bool
    {
        return false;
        /*$id = $this->id;
        if ($this->isSystemType()) {
            throw new \RuntimeException(_T("You cannot delete system payment types!"));
        }

        try {
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where([self::PK => $id]);
            $this->zdb->execute($delete);
            $this->deleteTranslation($this->name);
            Analog::log(
                'Payment type #' . $id . ' (' . $this->name
                . ') deleted successfully.',
                Analog::INFO
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to delete payment type ' . $id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }*/
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
            $contribution = new Contribution($this->zdb, $login, $contribution);
        }
        $this->contribution = $contribution;
        return $this;
    }

    /**
     * Get payment type
     *
     * @return PaymentType
     */
    public function getPaymentType(): PaymentType
    {
        return $this->payment_type;
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
            $payment_type = new PaymentType($this->zdb, $payment_type);
        }
        $this->payment_type = $payment_type;
        return $this;
    }

    /**
     * Get creation date
     *
     * @return DateTime
     */
    public function getCreationDate(): DateTime
    {
        return $this->creation_date;
    }

    /**
     * Set creation date
     *
     * @param string|DateTime $creation_date Creation date
     *
     * @return self
     */
    public function setCreationDate(string|DateTime $creation_date): self
    {
        if (is_string($creation_date)) {
            $creation_date = new DateTime($creation_date);
        }
        $this->creation_date = $creation_date;
        return $this;
    }

    /**
     * Get scheduled date
     *
     * @return DateTime
     */
    public function getScheduledDate(): DateTime
    {
        return $this->scheduled_date;
    }

    /**
     * Set scheduled date
     *
     * @param string|DateTime $scheduled_date Scheduled date
     *
     * @return self
     */
    public function setScheduledDate(string|DateTime $scheduled_date): self
    {
        if (is_string($scheduled_date)) {
            $scheduled_date = new DateTime($scheduled_date);
        }
        $this->scheduled_date = $scheduled_date;
        return $this;
    }

    /**
     * Get amount
     *
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
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
     * @return string
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * Set comment
     *
     * @param string $comment Comment
     *
     * @return self
     */
    public function setComment(string $comment): self
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
        return $result->allocation;
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
        return [];
    }
}
