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
 * @property integer $date_field
 * @property ?integer $payment_type_filter
 * @property integer|false $from_contribution
 * @property string $rstart_date_filter
 * @property string $rend_date_filter
 * @property array $selected
 */

class ScheduledPaymentsList extends Pagination
{
    use DatesHelper;

    public const ORDERBY_DATE = 0;
    public const ORDERBY_MEMBER = 1;
    public const ORDERBY_SCHEDULED_DATE = 2;
    public const ORDERBY_CONTRIBUTION = 3;
    public const ORDERBY_AMOUNT = 5;
    public const ORDERBY_PAYMENT_TYPE = 7;
    public const ORDERBY_ID = 8;

    public const DATE_RECORD = 0;

    public const DATE_SCHEDULED = 1;

    //filters
    private ?int $date_field = null;
    private ?string $start_date_filter = null;
    private ?string $end_date_filter = null;
    private ?int $payment_type_filter = null;
    private int|false $from_contribution = false;


    /** @var array<int> */
    private array $selected = [];

    /** @var array<string> */
    protected array $list_fields = array(
        'start_date_filter',
        'end_date_filter',
        'date_field',
        'payment_type_filter',
        'from_contribution',
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
        return self::ORDERBY_SCHEDULED_DATE;
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
        $this->date_field = self::DATE_SCHEDULED;
        $this->start_date_filter = null;
        $this->end_date_filter = null;
        $this->payment_type_filter = null;
        $this->selected = [];

        if ($ajax === false) {
            $this->from_contribution = false;
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
            switch ($name) {
                case 'start_date_filter':
                case 'end_date_filter':
                    $this->setFilterDate($name, $value, $name === 'start_date_filter');
                    break;
                default:
                    $this->$name = $value;
                    break;
            }
        }
    }
}
