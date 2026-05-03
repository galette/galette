<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Laminas\I18n\Translator\Translator as ZTranslator;

/**
 * Zend translator override
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class Translator extends ZTranslator //@phpstan-ignore class.extendsFinalByPhpDoc
{
    /**
     * Does a translation exist for string
     *
     * @param string  $message    String to check for
     * @param string  $textDomain Translation domain, defaults to "default"
     * @param ?string $locale     Locale, defaults to null
     */
    public function translationExists(string $message, string $textDomain = 'default', ?string $locale = null): bool
    {
        $locale = ($locale ?: $this->getLocale());

        if (!isset($this->messages[$textDomain][$locale])) {
            $this->loadMessages($textDomain, $locale);
        }

        return isset($this->messages[$textDomain][$locale][$message]);
    }
}
