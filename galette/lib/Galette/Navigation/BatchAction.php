<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Navigation;

/**
 * Action on the members selected in a list
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final readonly class BatchAction
{
    /**
     * Constructor
     *
     * @param string      $name  Action name, sent with the selection
     * @param string      $label Label
     * @param string      $icon  Icon name
     * @param string|null $title Longer description
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $icon,
        public ?string $title = null
    ) {
    }

    /**
     * Build a list of actions from arrays, as plugins provide them
     *
     * @param array<mixed> $actions Actions, with the same keys as the constructor parameters
     *
     * @return list<self>
     *
     * @throws \InvalidArgumentException when an action is invalid
     */
    public static function listFromArray(array $actions): array
    {
        $list = [];
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            foreach (['name', 'label', 'icon'] as $key) {
                if (!isset($action[$key]) || !is_string($action[$key])) {
                    throw new \InvalidArgumentException('A batch action must have a name, a label and an icon');
                }
            }
            $list[] = new self(
                name: $action['name'],
                label: $action['label'],
                icon: $action['icon'],
                title: is_string($action['title'] ?? null) ? $action['title'] : null
            );
        }
        return $list;
    }
}
