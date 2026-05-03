<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Entity;

use ArrayObject;
use Throwable;
use Galette\Core\Db;
use Analog\Analog;
use Galette\Features\I18n;
use Galette\Features\Translatable;

/**
 * Payment type
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property int    $id
 * @property string $name
 */

class PaymentType implements \Stringable
{
    use Translatable;
    use I18n;

    public const string TABLE = 'paymenttypes';
    public const string PK = 'type_id';

    private int $id;

    public const int SCHEDULED = 7;
    public const int OTHER = 6;
    public const int CASH = 1;
    public const int CREDITCARD = 2;
    public const int CHECK = 3;
    public const int TRANSFER = 4;
    public const int PAYPAL = 5;
    public const int STRIPE = 8;
    public const int HELLOASSO = 9;

    /**
     * Main constructor
     *
     * @param Db                                      $zdb  Database instance
     * @param ArrayObject<string,int|string>|int|null $args Arguments
     */
    public function __construct(
        private Db $zdb,
        ArrayObject|int|null $args = null
    ) {
        if (is_int($args)) {
            $this->load($args);
        } elseif ($args instanceof ArrayObject) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Load a payment type from its identifier
     *
     * @param int $id Identifier
     */
    public function load(int $id): bool
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->limit(1)->where([self::PK => $id]);

            $results = $this->zdb->execute($select);
            $res = $results->current();

            $this->id = $id;
            $this->name = $res->type_name;
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred loading payment type #' . $id . "Message:\n"
                . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Load payment type from a db ResultSet
     *
     * @param ArrayObject<string, int|string> $rs ResultSet
     */
    private function loadFromRS(ArrayObject $rs): void
    {
        $pk = self::PK;
        $this->id = (int)$rs->$pk;
        $this->name = $rs->type_name;
    }

    /**
     * Store payment type in database
     */
    public function store(): bool
    {
        $data = [
            'type_name' => $this->name
        ];
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
                'An error occurred storing payment type: ' . $e->getMessage()
                . "\n" . print_r($data, true),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Remove current title
     */
    public function remove(): bool
    {
        $id = (int)$this->id;
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
        }
    }

    /**
     * Getter
     *
     * @param string $name Property name
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'id', 'name' => $this->$name,
            default => throw new \RuntimeException(
                sprintf(
                    'Unable to get property "%s::%s"!',
                    static::class,
                    $name
                )
            ),
        };
    }

    /**
     * Isset
     * Required for twig to access properties via __get
     *
     * @param string $name Property name
     */
    public function __isset(string $name): bool
    {
        return match ($name) {
            'id', 'name' => true,
            default => false,
        };
    }

    /**
     * Setter
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     */
    public function __set(string $name, mixed $value): void
    {
        switch ($name) {
            case 'name':
                if (trim((string)$value) === '') {
                    Analog::log(
                        'Name cannot be empty',
                        Analog::WARNING
                    );
                } else {
                    $this->old_name = $this->name;
                    $this->name     = $value;
                }
                break;
            default:
                Analog::log(
                    'Unable to set property ' . $name,
                    Analog::WARNING
                );
                break;
        }
    }

    /**
     * Get system payment types
     *
     * @param bool $translated Return translated types (default) or not
     *
     * @return array<int,string>
     */
    public function getSystemTypes(bool $translated = true): array
    {
        if ($translated) {
            $systypes = [
                self::OTHER         => _T("Other"),
                self::CASH          => _T("Cash"),
                self::CREDITCARD    => _T("Credit card"),
                self::CHECK         => _T("Check"),
                self::TRANSFER      => _T("Transfer"),
                self::PAYPAL        => _T("Paypal"),
                self::STRIPE        => _T("Stripe"),
                self::HELLOASSO     => _T("HelloAsso"),
                self::SCHEDULED     => _T("Payment schedule")
            ];
        } else {
            $systypes = [
                self::OTHER         => "Other",
                self::CASH          => "Cash",
                self::CREDITCARD    => "Credit card",
                self::CHECK         => "Check",
                self::TRANSFER      => "Transfer",
                self::PAYPAL        => "Paypal",
                self::STRIPE        => "Stripe",
                self::HELLOASSO     => "HelloAsso",
                self::SCHEDULED     => "Payment schedule"
            ];
        }
        return $systypes;
    }

    /**
     * Is current payment a system one
     */
    public function isSystemType(): bool
    {
        return isset($this->getSystemTypes()[$this->id]);
    }

    /**
     * Simple text representation
     */
    public function __toString(): string
    {
        return $this->getName();
    }
}
