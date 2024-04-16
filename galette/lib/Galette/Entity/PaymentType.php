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
use Throwable;
use Galette\Core\Db;
use Analog\Analog;
use Galette\Features\I18n;
use Galette\Features\Translatable;
use Galette\Entity\Base\EntityFromDb;

/**
 * Payment type
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property integer $id
 * @property string $name
 */

class PaymentType extends EntityFromDb
{
    //use Translatable;
    use I18n;

    public const TABLE = 'paymenttypes';
    public const PK = 'type_id';

    public const SCHEDULED = 7;
    public const OTHER = 6;
    public const CASH = 1;
    public const CREDITCARD = 2;
    public const CHECK = 3;
    public const TRANSFER = 4;
    public const PAYPAL = 5;


    public function __construct(Db $zdb, ArrayObject|int $args = null)
    {
        parent::__construct(
            $zdb,
            [
                'table' => self::TABLE,
                'id' => self::PK,
                'name' => 'type_name',
            ],
            [
                'toString' => 'name',
                'name:warningnoempty' => true,
                'i18n' => ['name']
            ],
            $args
        );
    }

    // Compatibilité ancienne classe
    public function getName($translated = true): string
    {
        return $this->getValue('name', $translated);
    }


    /**
     * Remove current title
     *
     * @return boolean
     */
    public function remove(): bool
    {
        $id = (int) $this->id;
        if ($this->isSystemType()) {
            throw new \RuntimeException(_T("You cannot delete system payment types!"));
        }
        $ok = parent::remove();
        return $ok;
    }



    /**
     * Get system payment types
     *
     * @param boolean $translated Return translated types (default) or not
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
                self::SCHEDULED     => "Payment schedule"
            ];
        }
        return $systypes;
    }

    /**
     * Is current payment a system one
     *
     * @return boolean
     *
     */
    public function isSystemType(): bool
    {
        return isset($this->getSystemTypes()[$this->id]);
    }

}
