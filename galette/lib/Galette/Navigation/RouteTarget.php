<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Navigation;

/**
 * Route a navigation entry leads to
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final readonly class RouteTarget
{
    /**
     * Constructor
     *
     * @param string               $name    Route name
     * @param array<string, mixed> $args    Route arguments
     * @param list<string>         $aliases Other routes for which the entry is the current one
     */
    public function __construct(
        public string $name,
        public array $args = [],
        public array $aliases = []
    ) {
    }

    /**
     * Build from an array, as plugins provide them
     *
     * @param array<string, mixed> $data Route name, and optional args and aliases
     *
     * @throws \InvalidArgumentException when route name is missing
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['name']) || !is_string($data['name'])) {
            throw new \InvalidArgumentException('A navigation route must have a name');
        }

        $args = [];
        //route arguments are named
        foreach (is_array($data['args'] ?? null) ? $data['args'] : [] as $key => $value) {
            if (is_string($key)) {
                $args[$key] = $value;
            }
        }
        $aliases = array_values(array_filter(
            is_array($data['aliases'] ?? null) ? $data['aliases'] : [],
            is_string(...)
        ));

        return new self($data['name'], $args, $aliases);
    }

    /**
     * Get the names of the routes for which the entry is the current one
     *
     * @return list<string>
     */
    public function getNames(): array
    {
        return [$this->name, ...$this->aliases];
    }
}
