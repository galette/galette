<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Throwable;
use Analog\Analog;
use Laminas\Db\Sql\Expression;

/**
 * l10n handling
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class L10n
{
    public const string TABLE = 'l10n';

    /**
     * Default constructor.
     *
     * @param Db         $zdb        Database instance
     * @param I18n       $i18n       I18n instance
     * @param Translator $translator Translator instance
     */
    public function __construct(
        private readonly Db $zdb,
        private readonly I18n $i18n,
        private readonly Translator $translator
    ) {
    }

    /**
     * Is text translated by Galette itself (gettext)?
     *
     * Such a text will never use dynamic translations.
     *
     * @param string $text Text to check
     */
    public function isCoreString(string $text): bool
    {
        if (trim($text) === '') {
            return false;
        }

        foreach ($this->i18n->getList() as $lang) {
            if ($this->translator->translationExists($text, 'galette', $lang->getLongID())) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get translations provided by Galette itself (gettext) for a text
     * Text is empty for languages without translation.
     *
     * @param string $text Text to translate
     *
     * @return array<int, array{key: string, lang: string, rtl: bool, name: string, text: string}>
     */
    public function getCoreTranslations(string $text): array
    {
        $results = [];
        foreach ($this->i18n->getList() as $l) {
            $results[] = [
                'key'  => $l->getLongID(),
                'lang' => $l->getWebID(),
                'rtl' => $l->isRTL(),
                'name' => ucwords($l->getName()),
                'text' => $this->translator->translationExists($text, 'galette', $l->getLongID())
                    ? $this->translator->translate($text, 'galette', $l->getLongID())
                    : ''
            ];
        }
        return $results;
    }

    /**
     * Add a translation stored in the database
     *
     * @param string $text_orig Text to translate
     */
    public function addDynamicTranslation(string $text_orig): bool
    {
        if ($this->isCoreString($text_orig)) {
            Analog::log(
                '`' . $text_orig . '` is translated by Galette, no dynamic translation added.',
                Analog::INFO
            );
            return true;
        }

        try {
            foreach ($this->i18n->getList() as $lang) {
                //check if translation already exists
                $select = $this->zdb->select(self::TABLE);
                $select->columns(['text_nref'])
                    ->where(
                        [
                            'text_orig_sum' => md5($text_orig),
                            'text_locale'   => $lang->getLongID()
                        ]
                    );

                $results = $this->zdb->execute($select);
                $result = $results->current();
                $nref = 0;
                if ($result) {
                    $nref = $result->text_nref;
                }

                if (is_numeric($nref) && $nref > 0) {
                    //already existing, update
                    $values = [
                        'text_nref' => new Expression('text_nref+1')
                    ];
                    Analog::log(
                        'Entry for `' . $text_orig
                        . '` dynamic translation already exists.',
                        Analog::INFO
                    );

                    $owhere = $select->where;

                    $update = $this->zdb->update(self::TABLE);
                    $update->set($values)->where($owhere);
                    $this->zdb->execute($update);
                } else {
                    //add new entry
                    // User is supposed to use current language as original text.
                    $text_trans = $text_orig;
                    if ($lang->getLongID() != $this->i18n->getLongID()) {
                        $text_trans = '';
                    }
                    $values = [
                        'text_orig' => $text_orig,
                        'text_orig_sum' => md5($text_orig),
                        'text_locale' => $lang->getLongID(),
                        'text_trans' => $text_trans
                    ];

                    $insert = $this->zdb->insert(self::TABLE);
                    $insert->values($values);
                    $this->zdb->execute($insert);
                }
            }
            return true;
        } catch (Throwable $e) {
            Logs::exception($e, 'An error occurred adding dynamic translation for `' . $text_orig . '`', Analog::ERROR);
            return false;
        }
    }

    /**
     * Rename a translation stored in the database, keeping existing translations
     *
     * New text is supposed to be written in current language; translations of
     * other languages are copied, unless new text already has its own.
     *
     * @param string $text_orig Old text
     * @param string $text_new  New text
     */
    public function renameDynamicTranslation(string $text_orig, string $text_new): bool
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->columns(['text_locale', 'text_trans'])
                ->where(['text_orig_sum' => md5($text_orig)]);
            $old_translations = [];
            foreach ($this->zdb->execute($select) as $row) {
                $old_translations[$row->text_locale] = $row->text_trans;
            }
        } catch (Throwable $e) {
            Logs::exception(
                $e,
                'An error occurred renaming dynamic translation for `' . $text_orig . '`',
                Analog::ERROR
            );
            return false;
        }

        if (!$this->deleteDynamicTranslation($text_orig) || !$this->addDynamicTranslation($text_new)) {
            return false;
        }

        if ($this->isCoreString($text_new)) {
            //Galette translations will be used
            return true;
        }

        $result = true;
        foreach ($old_translations as $locale => $translation) {
            if (
                $locale === $this->i18n->getLongID()
                || $translation === ''
                || $this->getDynamicTranslation($text_new, $locale) !== ''
            ) {
                continue;
            }
            $result = $this->updateDynamicTranslation($text_new, $locale, $translation) && $result;
        }
        return $result;
    }

    /**
     * Release a translation stored in the database
     *
     * Same text may be used by several objects: translations are only removed
     * once the last of them has been released.
     *
     * @param string $text_orig Text to translate
     */
    public function deleteDynamicTranslation(string $text_orig): bool
    {
        try {
            $update = $this->zdb->update(self::TABLE);
            $update->set(['text_nref' => new Expression('text_nref-1')])
                ->where(['text_orig_sum' => md5($text_orig)]);
            $this->zdb->execute($update);

            //rows may have been added later for new languages, with a lower count
            $select = $this->zdb->select(self::TABLE);
            $select->columns(['text_nref']);
            $select->where->equalTo('text_orig_sum', md5($text_orig))
                ->greaterThan('text_nref', 0);
            if ($this->zdb->execute($select)->count() > 0) {
                Analog::log(
                    'Dynamic translation for `' . $text_orig . '` is still in use, kept.',
                    Analog::INFO
                );
                return true;
            }

            $delete = $this->zdb->delete(self::TABLE);
            $delete->where(
                [
                    'text_orig_sum' => md5($text_orig),
                ]
            );
            $this->zdb->execute($delete);
            return true;
        } catch (Throwable $e) {
            Logs::exception(
                $e,
                'An error occurred deleting dynamic translation for `' . $text_orig . '`',
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Update a translation stored in the database
     *
     * @param string $text_orig   Text to translate
     * @param string $text_locale The locale
     * @param string $text_trans  Translated text
     */
    public function updateDynamicTranslation(string $text_orig, string $text_locale, string $text_trans): bool
    {
        try {
            //check if translation already exists
            $select = $this->zdb->select(self::TABLE);
            $select->columns(['text_nref'])->where(
                [
                    'text_orig_sum' => md5($text_orig),
                    'text_locale'   => $text_locale
                ]
            );

            $results = $this->zdb->execute($select);
            $result = $results->current();

            $exists = false;
            if ($result) {
                $nref = $result->text_nref;
                $exists = (is_numeric($nref) && $nref > 0);
            }

            $values = [
                'text_trans' => $text_trans
            ];

            if ($exists) {
                $owhere = $select->where;

                $update = $this->zdb->update(self::TABLE);
                $update->set($values)->where($owhere);
                $this->zdb->execute($update);
            } else {
                $values['text_orig'] = $text_orig;
                $values['text_orig_sum'] = md5($text_orig);
                $values['text_locale'] = $text_locale;

                $insert = $this->zdb->insert(self::TABLE);
                $insert->values($values);
                $this->zdb->execute($insert);
            }
            return true;
        } catch (Throwable $e) {
            Logs::exception(
                $e,
                'An error occurred updating dynamic translation for `' . $text_orig . '`',
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Get a translation stored in the database
     *
     * @param string $text_orig   Text to translate
     * @param string $text_locale The locale
     */
    public function getDynamicTranslation(string $text_orig, string $text_locale): string
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->limit(1)->columns(
                ['text_trans']
            )->where(
                [
                    'text_orig_sum' => md5($text_orig),
                    'text_locale'   => $text_locale
                ]
            );
            $results = $this->zdb->execute($select);
            if ($results->count() > 0) {
                $res = $results->current();
                return $res->text_trans;
            } else {
                return '';
            }
        } catch (Throwable $e) {
            Logs::exception(
                $e,
                'An error occurred retrieving l10n entry. text_orig=' . $text_orig . ', text_locale=' . $text_locale,
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Get a translation stored in the database
     *
     * @param string $text_orig_sum Original text hash
     *
     * @return array<int, array{key: string, lang: string, rtl: bool, name: string, text: string}>
     */
    public function getDynamicTranslations(string $text_orig_sum): array
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->where(
                [
                    'text_orig_sum' => $text_orig_sum
                ]
            );

            $results = $this->zdb->execute($select);
            $existing_translations = [];
            foreach ($results as $row) {
                $existing_translations[$row->text_locale] = $row->text_trans;
            }

            $results = [];
            foreach ($this->i18n->getList() as $l) {
                $results[] = [
                    'key'  => $l->getLongID(),
                    'lang' => $l->getWebID(),
                    'rtl' => $l->isRTL(),
                    'name' => ucwords($l->getName()),
                    'text' => $existing_translations[$l->getLongID()] ?? ''
                ];
            }
            return $results;
        } catch (Throwable $e) {
            Logs::exception(
                $e,
                'An error occurred retrieving l10n entries. text_orig_sum=' . $text_orig_sum,
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Get existing translations from database
     *
     * @return array<string, string>
     *
     * @throws Throwable
     */
    public function getStringsToTranslate(): array
    {
        $select = $this->zdb->select(self::TABLE);
        $select->quantifier('DISTINCT')->columns(
            ['text_orig', 'text_orig_sum']
        )->order('text_orig');

        $all_texts = $this->zdb->execute($select);

        $translations = [];
        foreach ($all_texts as $row) {
            $translations[$row->text_orig_sum] = $row->text_orig;
        }
        return $translations;
    }
}
