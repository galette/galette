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

namespace Galette\Twig;

use Galette\Core\Db;
use Galette\Entity\Adherent;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig static calls extension for Galette
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */


class StaticExtension extends AbstractExtension
{
    /**
     * Constructor
     *
     * @param Db $zdb Database instance
     */
    public function __construct(private readonly Db $zdb)
    {
    }

    /**
     * Get functions
     *
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_class', get_class(...)),
            new TwigFunction('file_exists', file_exists(...)),
            new TwigFunction('memberName', $this->memberName(...)),
            new TwigFunction('callstatic', $this->callStatic(...))
        ];
    }

    /**
     * Get member's formatted name
     *
     * @param int|array{id: int} $id Member ID or array with 'id' key
     */
    public function memberName(int|array $id): string
    {
        return Adherent::getSName(
            zdb: $this->zdb,
            id: $id['id'] ?? $id
        );
    }

    /**
     * Call a static method
     *
     * @param string $class   Class name
     * @param string $method  Method name
     * @param mixed  ...$args Arguments to pass to method
     */
    public function callStatic(string $class, string $method, mixed ...$args): mixed
    {
        if (!class_exists($class)) {
            throw new \Exception("Cannot call static method $method on Class $class: Invalid Class");
        }

        if (!method_exists($class, $method)) {
            throw new \Exception("Cannot call static method $method on Class $class: Invalid method");
        }

        return forward_static_call_array([$class, $method], $args);
    }
}
