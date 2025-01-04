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

namespace Galette\Filters;

use Galette\Helpers\DatesHelper;
use Throwable;
use Analog\Analog;
use Galette\Core\Pagination;

/**
 * Contributions lists filters and paginator
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property ?string $start_date_filter
 * @property ?string $end_date_filter
 * @property ?integer $filtre_cotis_adh
 * @property integer|false $filtre_cotis_children
 * @property integer $date_field
 * @property ?integer $payment_type_filter
 * @property ?integer $contrib_type_filter
 * @property boolean $filtre_transactions
 * @property integer|false $from_transaction
 * @property ?integer $max_amount
 * @property string $rstart_date_filter
 * @property string $rend_date_filter
 * @property integer[] $selected
 */

class ContributionsList extends Pagination
{
    use DatesHelper;

    public const ORDERBY_DATE = 0;
    public const ORDERBY_BEGIN_DATE = 1;
    public const ORDERBY_END_DATE = 2;
    public const ORDERBY_MEMBER = 3;
    public const ORDERBY_TYPE = 4;
    public const ORDERBY_AMOUNT = 5;
    public const ORDERBY_PAYMENT_TYPE = 6;
    public const ORDERBY_ID = 7;

    public const DATE_BEGIN = 0;
    public const DATE_END = 1;
    public const DATE_RECORD = 2;

    //filters
    private ?int $date_field = null;
    private ?string $start_date_filter = null; //@phpstan-ignore-line
    private ?string $end_date_filter = null; //@phpstan-ignore-line
    private ?int $payment_type_filter = null; //@phpstan-ignore-line
    private ?int $contrib_type_filter = null; //@phpstan-ignore-line

    private ?int $filtre_cotis_adh = null;
    private int|false $filtre_cotis_children = false; //@phpstan-ignore-line
    private bool $filtre_transactions = false;
    private int|false $from_transaction = false; //@phpstan-ignore-line
    private ?int $max_amount = null; //@phpstan-ignore-line

    /** @var array<int> */
    private array $selected = [];

    /** @var array<string> */
    protected array $list_fields = array(
        'start_date_filter',
        'end_date_filter',
        'filtre_cotis_adh',
        'filtre_cotis_children',
        'date_field',
        'payment_type_filter',
        'contrib_type_filter',
        'filtre_transactions',
        'from_transaction',
        'max_amount',
        'selected'
    );

    /** @var array<string>  */
    protected array $virtuals_list_fields = array(
        'rstart_date_filter',
        'rend_date_filter'
    );

    /**
     * Default constructor
     */
    public function __construct()
    {
        $this->reinit();
    }

    /**
     * Returns the field we want to default set order to
     *
     * @return int|string
     */
    protected function getDefaultOrder(): int|string
    {
        return self::ORDERBY_BEGIN_DATE;
    }

    /**
     * Reinit default parameters
     *
     * @param boolean $ajax Called form an ajax query
     *
     * @return void
     */
    public function reinit(bool $ajax = false): void
    {
        parent::reinit();
        $this->date_field = self::DATE_BEGIN;
        $this->start_date_filter = null;
        $this->end_date_filter = null;
        $this->payment_type_filter = null;
        $this->contrib_type_filter = null;
        $this->filtre_cotis_adh = null;
        $this->filtre_cotis_children = false;
        $this->from_transaction = false;
        $this->selected = [];

        if ($ajax === false) {
            $this->max_amount = null;
            $this->filtre_transactions = false;
        }
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
        if (in_array($name, $this->pagination_fields)) {
            return parent::__get($name);
        } else {
            if (in_array($name, $this->list_fields) || in_array($name, $this->virtuals_list_fields)) {
                switch ($name) {
                    case 'start_date_filter':
                    case 'end_date_filter':
                        return $this->getDate($name);
                    case 'rstart_date_filter':
                    case 'rend_date_filter':
                        //same as above, but raw format
                        $rname = substr($name, 1);
                        return $this->getDate($rname, true, false);
                    default:
                        return $this->$name;
                }
            }
        }

        throw new \RuntimeException(
            sprintf(
                'Unable to get property "%s::%s"!',
                __CLASS__,
                $name
            )
        );
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
        if (in_array($name, $this->pagination_fields)) {
            return true;
        } elseif (in_array($name, $this->list_fields) || in_array($name, $this->virtuals_list_fields)) {
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
    public function __set(string $name, mixed $value): void
    {
        if (in_array($name, $this->pagination_fields)) {
            parent::__set($name, $value);
        } else {
            Analog::log(
                '[ContributionsList] Setting property `' . $name . '`',
                Analog::DEBUG
            );

            switch ($name) {
                case 'start_date_filter':
                case 'end_date_filter':
                    $this->setFilterDate($name, $value, $name === 'start_date_filter');
                    break;
                case 'filtre_cotis_adh':
                case 'date_field':
                    $this->$name = (int)$value;
                    break;
                default:
                    $this->$name = $value;
                    break;
            }
        }
    }
}
