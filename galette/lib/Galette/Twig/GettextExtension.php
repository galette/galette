<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig gettext extension for Galette
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */


class GettextExtension extends AbstractExtension
{
    /**
     * Get functions
     *
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('__', $this->__(...)),
            new TwigFunction('_T', $this->_T(...)),
            new TwigFunction('_Tn', $this->_Tn(...)),
            new TwigFunction('_Tx', $this->_Tx(...)),
            new TwigFunction('_Tnx', $this->_Tnx(...))
        ];
    }

    /**
     * Translate a string, without displaying not translated
     *
     * @param string $string The string to translate
     * @param string $domain Translation domain. Default to galette
     */
    public function __(
        string $string,
        string $domain = 'galette'
    ): string {
        return __($string, $domain);
    }

    /**
     * Translate a string, or return original one
     *
     * @param string $string The string to translate
     * @param string $domain Translation domain. Default to galette
     */
    public function _T(// @phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,PSR2.Methods.MethodDeclaration.Underscore
        string $string,
        string $domain = 'galette'
    ): string {
        return _T($string, $domain);
    }

    /**
     * Translate a singular/plural string, or return original one
     *
     * @param string $singular Singular string
     * @param string $plural   Plural string
     * @param int    $count    Count to determine singular/plural
     * @param string $domain   Translation domain. Default to galette
     */
    public function _Tn(// @phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,PSR2.Methods.MethodDeclaration.Underscore
        string $singular,
        string $plural,
        int $count,
        string $domain = 'galette'
    ): string {
        return _Tn(singular: $singular, plural: $plural, count: $count, domain: $domain);
    }

    /**
     * Get contextualized translation
     *
     * @param string $context The context
     * @param string $string  The string to translate
     * @param string $domain  Translation domain. Default to galette
     */
    public function _Tx(// @phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,PSR2.Methods.MethodDeclaration.Underscore
        string $context,
        string $string,
        string $domain = 'galette'
    ): string {
        return _Tx($context, $string, $domain);
    }

    /**
     * Get contextualized singular/plural translation
     *
     * @param string $context  The context
     * @param string $singular Singular string
     * @param string $plural   Plural string
     * @param int    $count    Count to determine singular/plural
     * @param string $domain   Translation domain. Default to galette
     */
    public function _Tnx(// @phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,PSR2.Methods.MethodDeclaration.Underscore
        string $context,
        string $singular,
        string $plural,
        int $count,
        string $domain = 'galette'
    ): string {
        return _Tnx(context: $context, singular: $singular, plural: $plural, count: $count, domain: $domain);
    }
}
