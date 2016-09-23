<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contributions lists filters and paginator
 *
 * PHP version 5
 *
 * Copyright © 2016 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 * @copyright 2016 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     june, 12th 2016
 */

namespace Galette\Filters;

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
 * @copyright 2016 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

class ContributionsList extends Pagination
{

    const ORDERBY_DATE = 0;
    const ORDERBY_BEGIN_DATE = 1;
    const ORDERBY_END_DATE = 2;
    const ORDERBY_MEMBER = 3;
    const ORDERBY_TYPE = 4;
    const ORDERBY_AMOUNT = 5;
    const ORDERBY_DURATION = 6;
    const ORDERBY_PAYMENT_TYPE = 7;

    const DATE_BEGIN = 0;
    const DATE_END = 1;
    const DATE_RECORD = 2;

    //filters
    private $date_field = null;
    private $start_date_filter = null;
    private $end_date_filter = null;
    private $payment_type_filter = null;
    private $filtre_cotis_adh = null;
    private $filtre_transactions = null;

    private $from_transaction = false;
    private $max_amount = null;

    protected $list_fields = array(
        'start_date_filter',
        'end_date_filter',
        'filtre_cotis_adh',
        'date_field',
        'payment_type_filter',
        'filtre_transactions',
        'from_transaction',
        'max_amount'
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
     * @return string field name
     */
    protected function getDefaultOrder()
    {
        return self::ORDERBY_BEGIN_DATE;
    }

    /**
     * Reinit default parameters
     *
     * @return void
     */
    public function reinit()
    {
        parent::reinit();
        $this->date_field = self::DATE_BEGIN;
        $this->start_date_filter = null;
        $this->end_date_filter = null;
        $this->payment_type_filter = null;
        $this->filtre_transactions = null;
        $this->_count = null;
        $this->filtre_cotis_adh = null;
        $this->from_transaction = false;
        $this->max_amount = null;
        $this->_sum = null;
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrive
     *
     * @return object the called property
     */
    public function __get($name)
    {
        Analog::log(
            '[ContributionsList] Getting property `' . $name . '`',
            Analog::DEBUG
        );

        if (in_array($name, $this->pagination_fields)) {
            return parent::__get($name);
        } else {
            if (in_array($name, $this->list_fields)) {
                return $this->$name;
            } else {
                Analog::log(
                    '[ContributionsList] Unable to get proprety `' .$name . '`',
                    Analog::WARNING
                );
            }
        }
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param object $value a relevant value for the property
     *
     * @return void
     */
    public function __set($name, $value)
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
                                    $y->format('Y'),
                                    $month,
                                    $day
                                );
                                $this->$name = $y->format('Y-m-d');
                            }

                            $ym = \DateTime::createFromFormat(__("Y-m"), $value);
                            if ($y === false && $ym  !== false) {
                                $day = 1;
                                if ($name === 'end_date_filter') {
                                    $day = $ym->format('t');
                                }
                                $ym->setDate(
                                    $ym->format('Y'),
                                    $ym->format('m'),
                                    $day
                                );
                                $this->$name = $ym->format('Y-m-d');
                            }

                            $d = \DateTime::createFromFormat(__("Y-m-d"), $value);
                            if ($y === false && $ym  === false && $d !== false) {
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
                                    str_replace(
                                        array('%field', '%format'),
                                        array(
                                            $field,
                                            implode(', ', $formats)
                                        ),
                                        _T("Unknown date format for %field.<br/>Know formats are: %formats")
                                    )
                                );
                            }
                        } else {
                            $this->$name = null;
                        }
                    } catch (\Exception $e) {
                        Analog::log(
                            'Wrong date format. field: ' . $key .
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

    /**
     * Add SQL limit
     *
     * @param Select $select Original select
     *
     * @return <type>
     */
    public function setLimit($select)
    {
        return $this->setLimits($select);
    }

    /**
     * Set counter
     *
     * @param int $c Count
     *
     * @return void
     */
    public function setCounter($c)
    {
        $this->counter = (int)$c;
        $this->countPages();
    }
}
