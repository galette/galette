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

namespace Galette\Helpers;

use DateTime;
use Throwable;
use Analog\Analog;

/**
 * Entity helper trait
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

trait DatesHelper
{
    /**
     * Set a Date
     *
     * @param string $field Field to store date
     * @param string $value Date to store
     *
     * @return self
     */
    protected function setDate(string $field, string $value): self
    {
        try {
            //first, try with localized date
            $date = DateTime::createFromFormat(__("Y-m-d"), $value);
            if ($date === false) {
                //try with non localized date
                $date = DateTime::createFromFormat("Y-m-d", $value);
                if ($date === false) {
                    throw new \Exception('Incorrect format');
                }
            }

            /** @phpstan-ignore-next-line */
            if (method_exists($this, 'getFieldPropertyName')) {
                $fieldPropertyName = $this->getFieldPropertyName($field);
            } else {
                $fieldPropertyName = $field;
            }
            $this->$fieldPropertyName = $date->format('Y-m-d');
        } catch (Throwable $e) {
            Analog::log(
                'Wrong date format. field: ' . $field .
                ', value: ' . $field . ', expected fmt: ' .
                __("Y-m-d") . ' | ' . $e->getMessage(),
                Analog::INFO
            );

            /** @phpstan-ignore-next-line */
            if (method_exists($this, 'getFieldLabel')) {
                $fieldLabel = $this->getFieldLabel($field);
            } else {
                $fieldLabel = $field;
            }

            $this->errors[] = sprintf(
                //TRANS: %1$s is the date format, %2$s is the field name
                _T('- Wrong date format (%1$s) for %2$s!'),
                __("Y-m-d"),
                $fieldLabel
            );
        }

        return $this;
    }

    /**
     * Set a Date for filtering
     *
     * @param string $field Field to store date
     * @param string $value Date to store
     * @param bool   $start Is a start date (or is a end date))
     *
     * @return self
     */
    protected function setFilterDate(string $field, string $value, bool $start): self
    {
        $formats = array(
            __("Y"),
            __("Y-m"),
            __("Y-m-d"),
        );

        try {
            if ($value !== '') {
                $y = \DateTime::createFromFormat(__("Y"), $value);
                if ($y !== false) {
                    $month = 1;
                    $day = 1;
                    if ($start === false) {
                        $month = 12;
                        $day = 31;
                    }
                    $y->setDate(
                        (int)$y->format('Y'),
                        $month,
                        $day
                    );
                    $this->$field = $y->format('Y-m-d');
                    return $this;
                }

                $ym = \DateTime::createFromFormat(__("Y-m"), $value);
                if ($ym !== false) {
                    $day = 1;
                    if ($start === false) {
                        $day = (int)$ym->format('t');
                    }
                    $ym->setDate(
                        (int)$ym->format('Y'),
                        (int)$ym->format('m'),
                        $day
                    );
                    $this->$field = $ym->format('Y-m-d');
                    return $this;
                }

                $d = \DateTime::createFromFormat(__("Y-m-d"), $value);
                if ($d !== false) {
                    $this->$field = $d->format('Y-m-d');
                    return $this;
                }

                $field = null;
                if ($start === true) {
                    $field = _T("start date filter");
                } else {
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
            } else {
                $this->$field = null;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Wrong date format. field: ' . $field .
                ', value: ' . $value . ', known formats: ' .
                implode(', ', $formats) . ' | ' . $e->getMessage(),
                Analog::INFO
            );

            /** @phpstan-ignore-next-line */
            if (method_exists($this, 'getFieldLabel')) {
                $fieldLabel = $this->getFieldLabel($field);
            } else {
                $fieldLabel = $field;
            }

            $this->errors[] = sprintf(
                //TRANS: %1$s is the date format, %2$s is the field name
                _T('- Wrong date format (%1$s) for %2$s!'),
                implode(', ', $formats),
                $fieldLabel
            );

            throw $e;
        }

        return $this;
    }

    /**
     * Get a date
     *
     * @param string $field      Field name to retrieve
     * @param bool   $formatted  Get formatted date, or DateTime object
     * @param bool   $translated Get translated or db value
     *
     * @return string|DateTime|null
     */
    public function getDate(string $field, bool $formatted = true, bool $translated = true): string|DateTime|null
    {
        /** @phpstan-ignore-next-line */
        if (method_exists($this, 'getFieldPropertyName')) {
            $fieldPropertyName = $this->getFieldPropertyName($field);
        } else {
            $fieldPropertyName = $field;
        }

        if ($this->$fieldPropertyName !== null && $this->$fieldPropertyName != '') {
            try {
                $date = new DateTime($this->$fieldPropertyName);
                if ($formatted === false) {
                    return $date;
                }
                if ($translated === false) {
                    return $date->format('Y-m-d');
                }
                return $date->format(__('Y-m-d'));
            } catch (Throwable $e) {
                //oops, we've got a bad date :/
                Analog::log(
                    'Bad date (' . $this->$fieldPropertyName . ') | ' .
                    $e->getMessage(),
                    Analog::INFO
                );
                return $this->$fieldPropertyName;
            }
        }

        return null;
    }
}
