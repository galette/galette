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

namespace Galette\Filters;

use Throwable;
use Analog\Analog;
use Galette\Core\Pagination;
use Galette\Core\MailingHistory;

/**
 * Mailings history lists filters and paginator
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property ?string $start_date_filter
 * @property string $raw_start_date_filter
 * @property ?string $end_date_filter
 * @property string $raw_end_date_filter
 * @property int $sender_filter
 * @property int $sent_filter
 * @property ?string $subject_filter
 */

class MailingsList extends HistoryList
{
    public const ORDERBY_DATE = 0;
    public const ORDERBY_SENDER = 1;
    public const ORDERBY_SUBJECT = 2;
    public const ORDERBY_SENT = 3;

    //filters
    private ?string $start_date_filter = null;
    private ?string $end_date_filter = null;
    private int $sender_filter = 0;
    private int $sent_filter = MailingHistory::FILTER_DC_SENT;
    private ?string $subject_filter = null;

    /** @var array<string>  */
    protected array $list_fields = array(
        'start_date_filter',
        'raw_start_date_filter',
        'end_date_filter',
        'raw_end_date_filter',
        'sender_filter',
        'sent_filter',
        'subject_filter'
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
        return self::ORDERBY_DATE;
    }

    /**
     * Reinit default parameters
     *
     * @return void
     */
    public function reinit(): void
    {
        parent::reinit();
        $this->start_date_filter = null;
        $this->end_date_filter = null;
        $this->sender_filter = 0;
        $this->sent_filter = MailingHistory::FILTER_DC_SENT;
        $this->subject_filter = null;
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
            '[MailingsList] Getting property `' . $name . '`',
            Analog::DEBUG
        );

        if (in_array($name, $this->pagination_fields)) {
            return parent::__get($name);
        } else {
            if (in_array($name, $this->list_fields)) {
                switch ($name) {
                    case 'raw_start_date_filter':
                        return $this->start_date_filter;
                    case 'raw_end_date_filter':
                        return $this->end_date_filter;
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
                    default:
                        return $this->$name;
                }
            } else {
                Analog::log(
                    '[MailingsList] Unable to get property `' . $name . '`',
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
        } elseif (in_array($name, $this->list_fields)) {
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
                '[MailingsList] Setting property `' . $name . '`',
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
