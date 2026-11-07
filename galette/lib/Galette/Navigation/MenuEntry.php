<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Navigation;

/**
 * Navigation entry: menu item, dashboard card or member action
 *
 * An entry leads to a route, or groups children entries.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final readonly class MenuEntry
{
    /**
     * Constructor
     *
     * @param string           $label      Label
     * @param RouteTarget|null $route      Route the entry leads to; null when it has children
     * @param string|null      $title      Longer description
     * @param string|null      $icon       Icon name
     * @param int|null         $badge      Count displayed on the entry
     * @param list<MenuEntry>  $children   Children entries
     * @param string|null      $extraClass Additional CSS class
     */
    public function __construct(
        public string $label,
        public ?RouteTarget $route = null,
        public ?string $title = null,
        public ?string $icon = null,
        public ?int $badge = null,
        public array $children = [],
        public ?string $extraClass = null
    ) {
        if ($route === null && $children === []) {
            throw new \InvalidArgumentException(sprintf('Navigation entry "%s" leads nowhere', $label));
        }
    }

    /**
     * Build from an array, as plugins provide them
     *
     * @param array<string, mixed> $data Entry, with the same keys as the constructor
     *                                   parameters; "extra_class" for $extraClass
     *
     * @throws \InvalidArgumentException when entry is invalid
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['label']) || !is_string($data['label'])) {
            throw new \InvalidArgumentException('A navigation entry must have a label');
        }

        $children = [];
        foreach (is_array($data['children'] ?? null) ? $data['children'] : [] as $child) {
            if (is_array($child)) {
                $children[] = self::fromArray($child);
            }
        }

        return new self(
            label: $data['label'],
            route: is_array($data['route'] ?? null) ? RouteTarget::fromArray($data['route']) : null,
            title: self::stringOrNull($data['title'] ?? null),
            icon: self::stringOrNull($data['icon'] ?? null),
            badge: is_int($data['badge'] ?? null) ? $data['badge'] : null,
            children: $children,
            extraClass: self::stringOrNull($data['extra_class'] ?? null)
        );
    }

    /**
     * Build a list of entries from arrays, as plugins provide them
     *
     * @param array<mixed> $entries Entries
     *
     * @return list<self>
     */
    public static function listFromArray(array $entries): array
    {
        $list = [];
        foreach ($entries as $entry) {
            if (is_array($entry)) {
                $list[] = self::fromArray($entry);
            }
        }
        return $list;
    }

    /**
     * Get the names of the routes for which the entry, or one of its children,
     * is the current one
     *
     * @return list<string>
     */
    public function getRouteNames(): array
    {
        $names = $this->route?->getNames() ?? [];
        foreach ($this->children as $child) {
            array_push($names, ...$child->getRouteNames());
        }
        return $names;
    }

    /**
     * Keep a string value
     *
     * @param mixed $value Value
     */
    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
