<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Twig;

use Slim\Csrf\Guard;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Twig CSRF extension
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */


class CsrfExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * Constructor
     * @param Guard $csrf CSRF instance
     */
    public function __construct(protected Guard $csrf)
    {
    }

    /**
     * Get globals
     *
     * @return array<string,null|string>
     */
    public function getGlobals(): array
    {
        // CSRF token name and value
        $nameKey = $this->csrf->getTokenNameKey();
        $valueKey = $this->csrf->getTokenValueKey();
        $name = $this->csrf->getTokenName();
        $value = $this->csrf->getTokenValue();

        return [
            'csrf_name_key' => $nameKey,
            'csrf_value_key' => $valueKey,
            'csrf_name' => $name,
            'csrf_value' => $value
        ];
    }
}
