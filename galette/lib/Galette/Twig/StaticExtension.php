<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
