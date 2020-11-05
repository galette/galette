<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Transactions lists filters and paginator
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
 * @link      http://galette.tuxfamily.org
 * @since     june, 12th 2016
 */

namespace Galette\Filters;

use Throwable;
use Analog\Analog;
use Galette\Core\Pagination;

/**
 * Transactions lists filters and paginator
 *
 * @name      TransactionsList
 * @category  Filters
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2016 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

class TransactionsList extends Pagination
{

    public const ORDERBY_DATE = 0;
    public const ORDERBY_MEMBER = 3;
    public const ORDERBY_AMOUNT = 5;

    //filters
    private $start_date_filter;
    private $end_date_filter;
    private $filtre_cotis_adh;

    protected $list_fields = array(
        'start_date_filter',
        'end_date_filter',
        'filtre_cotis_adh'
    );

    protected $virtuals_list_fields = array(
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
     * @return string field name
     */
    protected function getDefaultOrder()
    {
        return self::ORDERBY_DATE;
    }

    /**
     * Reinit default parameters
     *
     * @return void
     */
    public function reinit()
    {
        parent::reinit();
        $this->start_date_filter = null;
        $this->end_date_filter = null;
        $this->filtre_cotis_adh = null;
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
            '[TransactionsList] Getting property `' . $name . '`',
            Analog::DEBUG
        );

        if (in_array($name, $this->pagination_fields)) {
            return parent::__get($name);
        } else {
            if (in_array($name, $this->list_fields) || in_array($name, $this->virtuals_list_fields)) {
                switch ($name) {
                    case 'start_date_filter':
                    case 'end_date_filter':
                        try {
                            if ($this->$name !== null) {
                                $d = new \DateTime($this->$name);
                                return $d->format(__("Y-m-d"));
                            }
                        } catch (Throwable $e) {
                            //oops, we've got a bad date :/
                            Analog::log(
                                'Bad date (' . $this->$name . ') | ' .
                                $e->getMessage(),
                                Analog::INFO
                            );
                            return $this->$name;
                        }
                        break;
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
                    '[TransactionsList] Unable to get proprety `' . $name . '`',
                    Analog::WARNING
                );
            }
        }
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param mixed  $value a relevant value for the property
     *
     * @return void
     */
    public function __set($name, $value)
    {
        if (in_array($name, $this->pagination_fields)) {
            parent::__set($name, $value);
        } else {
            Analog::log(
                '[TransactionsList] Setting property `' . $name . '`',
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
                            if ($y === false && $ym !== false) {
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
