<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contributions lists filters and paginator
 *
 * PHP version 5
 *
 * Copyright © 2016-2024 The Galette Team
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
 *
 * @category  Filters
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2016-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     june, 12th 2016
 */

namespace Galette\Filters;

use Throwable;
use Analog\Analog;
use Galette\Core\Pagination;

/**
 * Contributions lists filters and paginator
 *
 * @name      ContributionsList
 * @category  Filters
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2016-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 *
 * @property ?string $start_date_filter
 * @property ?string $end_date_filter
 * @property ?integer $filtre_cotis_adh
 * @property integer|false $filtre_cotis_children
 * @property integer $date_field
 * @property ?integer $payment_type_filter
 * @property boolean $filtre_transactions
 * @property integer|false $from_transaction
 * @property ?integer $max_amount
 * @property string $rstart_date_filter
 * @property string $rend_date_filter
 * @property array $selected
 */

class ContributionsList extends Pagination
{
    public const ORDERBY_DATE = 0;
    public const ORDERBY_BEGIN_DATE = 1;
    public const ORDERBY_END_DATE = 2;
    public const ORDERBY_MEMBER = 3;
    public const ORDERBY_TYPE = 4;
    public const ORDERBY_AMOUNT = 5;
    public const ORDERBY_DURATION = 6;
    public const ORDERBY_PAYMENT_TYPE = 7;
    public const ORDERBY_ID = 8;

    public const DATE_BEGIN = 0;
    public const DATE_END = 1;
    public const DATE_RECORD = 2;

    //filters
    private ?int $date_field = null;
    private ?string $start_date_filter = null;
    private ?string $end_date_filter = null;
    private ?int $payment_type_filter = null;
    private ?int $filtre_cotis_adh = null;
    private int|false $filtre_cotis_children = false;
    private bool $filtre_transactions = false;

    private int|false $from_transaction = false;
    private ?int $max_amount = null;

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
    public function __get(string $name)
    {
        Analog::log(
            '[ContributionsList] Getting property `' . $name . '`',
            Analog::DEBUG
        );

        if (in_array($name, $this->pagination_fields)) {
            return parent::__get($name);
        } else {
            if (in_array($name, $this->list_fields) || in_array($name, $this->virtuals_list_fields)) {
                switch ($name) {
                    case 'start_date_filter':
                    case 'end_date_filter':
                        if ($this->$name === null) {
                            return $this->$name;
                        }
                        try {
                            $d = \DateTime::createFromFormat(__("Y-m-d"), $this->$name);
                            if ($d === false) {
                                //try with non localized date
                                $d = \DateTime::createFromFormat("Y-m-d", $this->$name);
                                if ($d === false) {
                                    throw new \Exception('Incorrect format');
                                }
                            }
                            return $d->format(__("Y-m-d"));
                        } catch (Throwable $e) {
                            //oops, we've got a bad date :/
                            Analog::log(
                                'Bad date (' . $this->$name . ') | ' .
                                $e->getMessage(),
                                Analog::INFO
                            );
                            return $this->$name;
                        }
                    case 'rstart_date_filter':
                    case 'rend_date_filter':
                        //same as above, but raw format
                        $rname = substr($name, 1);
                        return $this->$rname;
                    default:
                        return $this->$name;
                }
            } else {
                Analog::log(
                    '[ContributionsList] Unable to get property `' . $name . '`',
                    Analog::WARNING
                );
            }
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
    public function __set(string $name, $value): void
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
                    try {
                        if ($value !== '') {
                            $y = \DateTime::createFromFormat(__("Y"), $value);
                            if ($y !== false) {
                                $month = 1;
                                $day = 1;
                                if ($name === 'end_date_filter') {
                                    $month = 12;
                                    $day = 31;
                                }
                                $y->setDate(
                                    (int)$y->format('Y'),
                                    $month,
                                    $day
                                );
                                $this->$name = $y->format('Y-m-d');
                            }

                            $ym = \DateTime::createFromFormat(__("Y-m"), $value);
                            if ($y === false && $ym !== false) {
                                $day = 1;
                                if ($name === 'end_date_filter') {
                                    $day = (int)$ym->format('t');
                                }
                                $ym->setDate(
                                    (int)$ym->format('Y'),
                                    (int)$ym->format('m'),
                                    $day
                                );
                                $this->$name = $ym->format('Y-m-d');
                            }

                            $d = \DateTime::createFromFormat(__("Y-m-d"), $value);
                            if ($y === false && $ym === false && $d !== false) {
                                $this->$name = $d->format('Y-m-d');
                            }

                            if ($y === false && $ym === false && $d === false) {
                                $formats = array(
                                    __("Y"),
                                    __("Y-m"),
                                    __("Y-m-d"),
                                );

                                $field = null;
                                if ($name === 'start_date_filter') {
                                    $field = _T("start date filter");
                                }
                                if ($name === 'end_date_filter') {
                                    $field = _T("end date filter");
                                }

                                throw new \Exception(
                                    sprintf(
                                        //TRANS: %1$s is field name, %2$s is list of known date formats
                                        _T('Unknown date format for %1$s.<br/>Know formats are: %2$s'),
                                        $field,
                                        implode(', ', $formats)
                                    )
                                );
                            }
                        } else {
                            $this->$name = null;
                        }
                    } catch (Throwable $e) {
                        Analog::log(
                            'Wrong date format. field: ' . $name .
                            ', value: ' . $value . ', expected fmt: ' .
                            __("Y-m-d") . ' | ' . $e->getMessage(),
                            Analog::INFO
                        );
                        throw $e;
                    }
                    break;
                default:
                    $this->$name = $value;
                    break;
            }
        }
    }
}
