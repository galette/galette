<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
