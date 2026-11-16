<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Twig;

use Galette\Core\Csp;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig Content Security Policy extension for Galette
 *
 * Every script tag must carry the request nonce:
 * <script nonce="{{ csp_nonce() }}">...</script>
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CspExtension extends AbstractExtension
{
    /**
     * @param Csp $csp Content Security Policy of the request
     */
    public function __construct(private readonly Csp $csp)
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
            new TwigFunction('csp_nonce', $this->csp->getNonce(...)),
        ];
    }
}
