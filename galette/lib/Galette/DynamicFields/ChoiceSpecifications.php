<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

namespace Galette\DynamicFields;

/**
 * Choice dynamic field specifications
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ChoiceSpecifications extends FieldSpecifications
{
    /**
     * Set choices
     *
     * @param string[] $values Choices values. Just a string array, legacy code.
     */
    public function setChoices(array $values): self
    {
        $choices = [];
        $id = 0;
        foreach ($values as $value) {
            $choices[] = [
                'id'    => $id,
                'value' => $value
            ];
            ++$id;
        }
        $this->__set('choices', $choices);
        return $this;
    }

    /**
     * Get choices
     *
     * @return string[] Just a string array, legacy code.
     */
    public function getChoices(): array
    {
        $choices = [];
        foreach ($this->__get('choices') ?? [] as $entry) {
            $choices[(int)$entry['id']] = $entry['value'];
        }
        return $choices;
    }
}
