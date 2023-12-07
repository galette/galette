<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * i18n handling
 *
 * PHP version 5
 *
 * Copyright © 2007-2018 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2018 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

namespace Galette\Core;

use Analog\Analog;
use Laminas\I18n\Translator\Translator as ZTranslator;

/**
 * Zend translator override
 *
 * @category  Core
 * @name      Translator
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2018 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

class Translator extends ZTranslator
{
    /**
     * Do a translation exist for string
     *
     * @param string $message    String to check for
     * @param string $textDomain Translation domain, defaults to "default"
     * @param string $locale     Locale, defaults to null
     *
     * @return boolean
     */
    public function translationExists($message, $textDomain = 'default', $locale = null)
    {
        $locale = ($locale ?: $this->getLocale());

        if (!isset($this->messages[$textDomain][$locale])) {
            $this->loadMessages($textDomain, $locale);
        }

        return isset($this->messages[$textDomain][$locale][$message]);
    }
}
