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

namespace Galette\Helpers;

/**
 * Entity helper trait
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

trait EntityHelper
{
    use DatesHelper;

    /**
     * Fields configuration. Each field is an array and must reflect:
     * array(
     *   (string)label,
     *   (string)property name
     * )
     *
     * @var array<string, array<string, string|null>>
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
     * Get fields
     *
     * @return array<string, array<string, string>>
     */
    public function getFields(): array
    {
        return $this->fields;
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
        if (in_array($name, ($this->forbidden_fields ?? []))) {
            return false;
        }

        $virtual_fields = [];
        if (isset($this->virtual_fields)) {
            $virtual_fields = $this->virtual_fields;
        }
        return in_array($name, $virtual_fields) || property_exists($this, $name);
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
        return trim(trim($label, ':'));
    }

    /**
     * Get property name for given field
     *
     * @param string $field Field
     *
     * @return string
     */
    protected function getFieldPropertyName(string $field): string
    {
        return $this->fields[$field]['propname'] ?? $field;
    }
}
