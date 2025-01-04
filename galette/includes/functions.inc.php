<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

use Analog\Analog;
use Galette\Core\Galette;

const NOT_TRANSLATED = ' (not translated)';

/**
 * Check URL validity
 *
 * @param string $url The URL to check
 *
 * @return boolean
 */
function isValidWebUrl(string $url): bool
{
    return (preg_match(
        '#^http[s]?\\:\\/\\/[a-z0-9\-]+\.([a-z0-9\-]+\.)?[a-z]+#i',
        $url
    ) === 1);
}

/**
 * Translate a string, or return original one
 *
 * @param string  $string The string to translate
 * @param string  $domain Translation domain. Default to galette
 * @param boolean $nt     Indicate not translated strings; defaults to true
 *
 * @return string
 */
function _T(string $string, string $domain = 'galette', bool $nt = true): string
{
    global $language, $installer, $translator, $l10n;

    if (
        empty($string) //cannot translate an empty string
        || str_contains($domain, 'route') //routes are no longer translated
    ) {
        return $string;
    }

    if ($translator->translationExists($string, $domain)) {
        return $translator->translate($string, $domain);
    }

    $trans = false;
    if (!isset($installer) || $installer !== true) {
        $trans = $l10n->getDynamicTranslation(
            $string,
            $language
        );
    }

    if (!$trans) {
        $trans = $string;

        if (Galette::isDebugEnabled() && $nt === true) {
            $trans .= NOT_TRANSLATED;
        }
    }
    return $trans;
}

/**
 * Pluralized translation
 *
 * @param string  $singular Singular form of the string to translate
 * @param string  $plural   Plural form of the string to translate
 * @param integer $count    Number for count
 * @param string  $domain   Translation domain. Default to galette
 * @param boolean $nt       Indicate not translated strings; defaults to true
 *
 * @return string
 */
function _Tn(string $singular, string $plural, int $count, string $domain = 'galette', bool $nt = true): string
{
    global $language, $installer, $translator, $l10n;

    if (empty($singular) || empty($plural)) {
        Analog::log(
            'Cannot translate empty strings..',
            Analog::INFO
        );
        return $count > 1 ? $plural : $singular;
    }

    if (
        $translator->translationExists($singular, $domain)
        && $translator->translationExists($plural, $domain)
    ) {
        return $translator->translatePlural(
            $singular,
            $plural,
            $count,
            $domain
        );
    }

    if (!isset($installer) || $installer !== true) {
        $trans = $l10n->getDynamicTranslation(
            ($count > 1 ? $plural : $singular),
            $language
        );
    }

    if (!$trans) {
        $trans = ($count > 1 ? $plural : $singular);

        if (Galette::isDebugEnabled() && $nt === true) {
            $trans .= NOT_TRANSLATED;
        }
    }
    return $trans;
}

/**
 * Contextualized translation
 *
 * @param string  $context Context
 * @param string  $string  The string to translate
 * @param string  $domain  Translation domain (defaults to galette)
 * @param boolean $nt      Indicate not translated strings; defaults to true
 *
 * @return string
 */
function _Tx(string $context, string $string, string $domain = 'galette', bool $nt = true): string
{
    global $language, $installer, $translator, $l10n;

    $cstring = contextualizedString($string, $context);
    $ret = _T($cstring, $domain);
    if ($ret == $cstring) {
        $ret = $string;
    }

    $trans = false;
    if (!isset($installer) || $installer !== true) {
        $trans = $l10n->getDynamicTranslation(
            $cstring,
            $language
        );
    }

    if (!$trans) {
        $trans = $ret;

        if (Galette::isDebugEnabled() && $nt === true) {
            $trans .= NOT_TRANSLATED;
        }
    }
    return $trans;
}

/**
 * Pluralized and contextualized translation
 *
 * @param string  $context  Context
 * @param string  $singular Singular form of the string to translate
 * @param string  $plural   Plural form of the string to translate
 * @param integer $count    Number for count
 * @param string  $domain   Translation domain. Default to galette
 * @param boolean $nt       Indicate not translated strings; defaults to true
 *
 * @return string
 */
function _Tnx(string $context, string $singular, string $plural, int $count, string $domain = 'galette', bool $nt = true): string
{
    global $language, $installer, $translator, $l10n;

    $csingular = contextualizedString($singular, $context);
    $cplural = contextualizedString($plural, $context);
    $ret = _Tn(
        $csingular,
        $cplural,
        $count,
        $domain
    );

    if ($ret == $csingular) {
        // No translation
        $ret = $singular;
    }

    if ($ret == $cplural) {
        // No translation
        $ret = $plural;
    }

    $trans = false;
    if (!isset($installer) || $installer !== true) {
        $trans = $l10n->getDynamicTranslation(
            ($count > 1 ? $cplural : $csingular),
            $language
        );
    }

    if (!$trans) {
        $trans = $ret;

        if (Galette::isDebugEnabled() && $nt === true) {
            $trans .= NOT_TRANSLATED;
        }
    }

    return $trans;
}

/**
 * Get contextualized string (simulates pgettext)
 *
 * @param string $string  The string to translate
 * @param string $context The context
 *
 * @return string
 */
function contextualizedString(string $string, string $context): string
{
    return "{$string}\004{$context}";
}

/**
 * Translate a string, without displaying not translated
 *
 * @param string $string The string to translate
 * @param string $domain Translation domain. Default to false (will take default domain)
 *
 * @return string
 */
function __(string $string, string $domain = 'galette'): string
{
    return _T($string, $domain, false);
}
