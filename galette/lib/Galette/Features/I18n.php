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

namespace Galette\Features;

/**
 * Files
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

trait I18n
{
    /** @var array<string> */
    protected array $warnings = [];

    /**
     * Add a translation stored in the database
     *
     * @param string $text_orig Text to translate
     *
     * @return boolean
     */
    protected function addTranslation(string $text_orig): bool
    {
        /** @var \Galette\Core\L10n $l10n */
        global $l10n;

        $result = $l10n->addDynamicTranslation($text_orig);
        if ($result === false) {
            $this->warnings[] = str_replace(
                '%field',
                $text_orig,
                _T('Unable to add dynamic translation for %field :(')
            );
        };

        return $result;
    }

    /**
     * Update a translation stored in the database
     *
     * @param string $text_orig   Text to translate
     * @param string $text_locale The locale
     * @param string $text_trans  Translated text
     *
     * @return boolean
     */
    protected function updateTranslation(string $text_orig, string $text_locale, string $text_trans): bool
    {
        /** @var \Galette\Core\L10n $l10n */
        global $l10n;

        $result = $l10n->updateDynamicTranslation($text_orig, $text_locale, $text_trans);
        if ($result === false) {
            $this->warnings[] = str_replace(
                '%field',
                $text_orig,
                _T('Unable to update dynamic translation for %field :(')
            );
        };

        return $result;
    }

    /**
     * Delete a translation stored in the database
     *
     * @param string $text_orig Text to translate
     *
     * @return boolean
     */
    protected function deleteTranslation(string $text_orig): bool
    {
        /** @var \Galette\Core\L10n $l10n */
        global $l10n;

        $result = $l10n->deleteDynamicTranslation($text_orig);
        if ($result === false) {
            $this->warnings[] = str_replace(
                '%field',
                $text_orig,
                _T('Unable to remove old dynamic translation for %field :(')
            );
        }

        return $result;
    }

    /**
     * Get warnings
     *
     * @return array<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
