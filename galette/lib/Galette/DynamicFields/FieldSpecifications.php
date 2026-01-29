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

use JsonSerializable;

use function Safe\json_decode;

/**
 * Dynamic field specifications DTO
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
abstract class FieldSpecifications implements JsonSerializable
{
    /** @var array<string, mixed> */
    protected array $data = [];

    /**
     * Set a property
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     */
    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    /**
     * Get a property
     *
     * @param string $name Property name
     */
    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    /**
     * Check if a property exists
     *
     * @param string $name Property name
     */
    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * Serialize to JSON
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    /**
     * Load from JSON
     */
    public function fromJson(?string $json): self
    {
        if ($json !== null && $json !== '') {
            $data = json_decode($json, true);
            if (is_array($data)) {
                $this->data = $data;
            }
        }
        return $this;
    }
}
