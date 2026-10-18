<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Features;

use Galette\Core\L10n;

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
     */
    protected function addTranslation(string $text_orig): bool
    {
        /** @var L10n $l10n */
        global $l10n;

        $result = $l10n->addDynamicTranslation($text_orig);
        if ($result === false) {
            $this->warnings[] = sprintf(
                //TRANS: paramter is a field name
                _T('Unable to add dynamic translation for %1$s :('),
                $text_orig
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
     */
    protected function updateTranslation(string $text_orig, string $text_locale, string $text_trans): bool
    {
        /** @var L10n $l10n */
        global $l10n;

        $result = $l10n->updateDynamicTranslation($text_orig, $text_locale, $text_trans);
        if ($result === false) {
            $this->warnings[] = sprintf(
                //TRANS: paramter is a field name
                _T('Unable to update dynamic translation for %1$s :('),
                $text_orig
            );
        };

        return $result;
    }

    /**
     * Change text of a translation stored in the database
     *
     * @param string $text_orig         Old text
     * @param string $text_new          New text
     * @param bool   $keep_translations Keep existing translations for other languages
     */
    protected function renameTranslation(string $text_orig, string $text_new, bool $keep_translations): bool
    {
        /** @var L10n $l10n */
        global $l10n;

        if (!$keep_translations) {
            $deleted = $this->deleteTranslation($text_orig);
            return $this->addTranslation($text_new) && $deleted;
        }

        $result = $l10n->renameDynamicTranslation($text_orig, $text_new);
        if ($result === false) {
            $this->warnings[] = sprintf(
                //TRANS: parameter is a field name
                _T('Unable to update dynamic translation for %1$s :('),
                $text_new
            );
        }

        return $result;
    }

    /**
     * Delete a translation stored in the database
     *
     * @param string $text_orig Text to translate
     */
    protected function deleteTranslation(string $text_orig): bool
    {
        /** @var L10n $l10n */
        global $l10n;

        $result = $l10n->deleteDynamicTranslation($text_orig);
        if ($result === false) {
            $this->warnings[] = sprintf(
                //TRANS: paramter is a field name
                _T('Unable to remove old dynamic translation for %1$s :('),
                $text_orig
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
