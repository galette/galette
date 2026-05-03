<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Helpers;

/**
 * Entity helper trait
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property array<string, array<string, string|null>> $fields
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

    /**
     * Set fields, must populate $this->fields
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
     */
    public function __isset(string $name): bool
    {
        if (in_array($name, ($this->forbidden_fields ?? []))) { // @phpstan-ignore nullCoalesce.property
            return false;
        }

        $virtual_fields = [];
        if (isset($this->virtual_fields)) { // @phpstan-ignore isset.property
            $virtual_fields = $this->virtual_fields;
        }
        return in_array($name, $virtual_fields) || property_exists($this, $name);
    }

    /**
     * Get field label
     *
     * @param string $field Field name
     * @param string $entry Array entry to use (defaults to "label")
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
     */
    protected function getFieldPropertyName(string $field): string
    {
        return $this->fields[$field]['propname'] ?? $field;
    }
}
