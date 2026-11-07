<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Navigation;

/**
 * Menu: a title and its entries
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final readonly class Menu
{
    /**
     * Constructor
     *
     * @param string          $title Title
     * @param string          $icon  Icon name
     * @param list<MenuEntry> $items Entries
     */
    public function __construct(
        public string $title,
        public string $icon,
        public array $items = []
    ) {
    }

    /**
     * Get a copy of the menu, with more entries
     *
     * @param MenuEntry ...$items Entries to append
     */
    public function withItems(MenuEntry ...$items): self
    {
        return new self($this->title, $this->icon, [...$this->items, ...array_values($items)]);
    }

    /**
     * Get the names of the routes for which one of the entries is the current one
     *
     * @return list<string>
     */
    public function getRouteNames(): array
    {
        $names = [];
        foreach ($this->items as $item) {
            array_push($names, ...$item->getRouteNames());
        }
        return $names;
    }
}
