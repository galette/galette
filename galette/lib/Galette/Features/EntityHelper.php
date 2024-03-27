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

namespace Galette\Features;

use DateTime;
use Galette\Entity\Adherent;
use Galette\Repository\DynamicFieldsSet;
use Throwable;
use Analog\Analog;
use Galette\DynamicFields\File;
use Galette\DynamicFields\Date;
use Galette\DynamicFields\Boolean;
use Galette\Entity\DynamicFieldsHandle;

/**
 * Dynamics fields trait
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

trait EntityHelper
{
    /**
     * Fields configuration. Each field is an array and must reflect:
     * array(
     *   (string)label,
     *   (string)propname
     * )
     *
     * @var array<string, array<string, string>>
     */
    protected array $fields;

    /** @var string[] */
    //protected array $forbidden_fields = [];

    /** @var string[] */
    //protected array $virtual_fields = [];

    /**
     * Set fields, must populate $this->fields
     *
     * @return self
     */
    abstract protected function setFields(): self;

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
        if (in_array($name, ($this->forbidden_fields ?? []))) {
            return false;
        }

        $virtual_fields = [];
        if (isset($this->virtual_fields)) {
            $virtual_fields = $this->virtual_fields;
        }
        return (in_array($name, $virtual_fields) || property_exists($this, $name));
    }

    /**
     * Get field label
     *
     * @param string $field Field name
     * @param string $entry Array entry to use (defaults to "label")
     *
     * @return string
     */
    public function getFieldLabel(string $field, string $entry = 'label'): string
    {
        $label = $this->fields[$field][$entry] ?? $field;
        //replace "&nbsp;"
        $label = str_replace('&nbsp;', ' ', $label);
        //remove trailing ':' and then trim
        $label = trim(trim($label, ':'));
        return $label;
    }

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
            $date = \DateTime::createFromFormat(__("Y-m-d"), $value);
            if ($date === false) {
                //try with non localized date
                $date = \DateTime::createFromFormat("Y-m-d", $value);
                if ($date === false) {
                    throw new \Exception('Incorrect format');
                }
            }
            if (isset($this->fields[$field]['propname'])) {
                $propname = $this->fields[$field]['propname'];
            } else {
                $propname = $field;
            }
            $this->$propname = $date->format('Y-m-d');
        } catch (Throwable $e) {
            Analog::log(
                'Wrong date format. field: ' . $field .
                ', value: ' . $field . ', expected fmt: ' .
                __("Y-m-d") . ' | ' . $e->getMessage(),
                Analog::INFO
            );
            $this->errors[] = sprintf(
                //TRANS: %1$s is the date format, %2$s is the field name
                _T('- Wrong date format (%1$s) for %2$s!'),
                __("Y-m-d"),
                $this->getFieldLabel($field)
            );
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
        if ($this->$field !== null && $this->$field != '') {
            try {
                $date = new \DateTime($this->$field);
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
                    'Bad date (' . $this->$field . ') | ' .
                    $e->getMessage(),
                    Analog::INFO
                );
                return $this->$field;
            }
        }
        return null;
    }
}
