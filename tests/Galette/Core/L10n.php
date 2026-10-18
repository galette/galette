<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * L10n tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class L10n extends GaletteTestCase
{
    private \Galette\Core\L10n $l10n;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->l10n = new \Galette\Core\L10n(
            $this->zdb,
            $this->i18n,
            $this->container->get(\Galette\Core\Translator::class)
        );
    }

    /**
     * Test add dynamic translation
     */
    public function testAddDynamicTranslation(): void
    {
        $langs = array_keys($this->i18n->getArrayList());

        //add a translation with english as original language
        $this->i18n->changeLanguage('en_US');

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->columns([
            'text_locale',
            'text_nref',
            'text_trans'
        ]);
        $select->where(['text_orig' => 'A text for test']);
        $results = $this->zdb->execute($select);
        $this->assertSame(0, $results->count());

        $this->assertTrue($this->l10n->addDynamicTranslation('A text for test'));

        //check from database
        $results = $this->zdb->execute($select);
        $this->assertSame(count($langs), $results->count());
        foreach ($results as $result) {
            $this->assertTrue(in_array(str_replace('.utf8', '', $result['text_locale']), $langs));
            $this->assertSame(1, (int)$result['text_nref']);
            $this->assertSame(($result['text_locale'] == 'en_US' ? 'A text for test' : ''), $result['text_trans']);
        }

        //check from class method
        $results = $this->l10n->getDynamicTranslations(md5('A text for test'));
        $this->assertCount(count($langs), $results);
        foreach ($results as $result) {
            $this->assertSame(($result['key'] == 'en_US' ? 'A text for test' : ''), $result['text']);
        }

        //add a translation with french as original language
        $this->i18n->changeLanguage('fr_FR');

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->columns([
            'text_locale',
            'text_nref',
            'text_trans'
        ]);
        $select->where(['text_orig' => 'Un texte de test']);
        $results = $this->zdb->execute($select);
        $this->assertSame(0, $results->count());

        $this->assertTrue($this->l10n->addDynamicTranslation('Un texte de test'));

        //check from database
        $results = $this->zdb->execute($select);
        $this->assertSame(count($langs), $results->count());

        foreach ($results as $result) {
            $this->assertTrue(in_array(str_replace('.utf8', '', $result['text_locale']), $langs));
            $this->assertSame(1, (int)$result['text_nref']);
            $this->assertSame(($result['text_locale'] == 'fr_FR.utf8' ? 'Un texte de test' : ''), $result['text_trans']);
        }

        //check from class method
        $results = $this->l10n->getDynamicTranslations(md5('Un texte de test'));
        $this->assertCount(count($langs), $results);
        foreach ($results as $result) {
            $this->assertSame(($result['key'] == 'fr_FR.utf8' ? 'Un texte de test' : ''), $result['text']);
        }

        $to_translate = $this->l10n->getStringsToTranslate();
        $this->assertCount(2, $to_translate);
        $this->assertArrayHasKey(md5('A text for test'), $to_translate);
        $this->assertArrayHasKey(md5('Un texte de test'), $to_translate);
    }

    /**
     * Test core strings detection and translations
     */
    public function testCoreStrings(): void
    {
        $this->assertTrue($this->l10n->isCoreString('annual fee'));
        $this->assertTrue($this->l10n->isCoreString('President'));
        $this->assertFalse($this->l10n->isCoreString('A text for core test'));
        $this->assertFalse($this->l10n->isCoreString(''));

        $translations = $this->l10n->getCoreTranslations('annual fee');
        $this->assertCount(count($this->i18n->getList()), $translations);
        $by_lang = array_column($translations, 'text', 'key');
        $this->assertSame('annual fee', $by_lang['en_US']);
        $this->assertSame('cotisation annuelle', $by_lang['fr_FR.utf8']);
        $translations = $this->l10n->getCoreTranslations('A text for core test');
        $this->assertSame([''], array_unique(array_column($translations, 'text')));

        //no dynamic translation is stored for core strings
        $this->assertTrue($this->l10n->addDynamicTranslation('annual fee'));
        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(['text_orig_sum' => md5('annual fee')]);
        $this->assertSame(0, $this->zdb->execute($select)->count());
    }

    /**
     * Test dynamic translation flow (add/update/get/delete)
     */
    public function testDynamicTranslationFlow(): void
    {
        $langs = array_keys($this->i18n->getArrayList());
        $this->i18n->changeLanguage('en_US');

        //we first need to add a dynamic translation
        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->columns([
            'text_locale',
            'text_nref',
            'text_trans'
        ]);
        $select->where(['text_orig' => 'A text for flow test']);
        $results = $this->zdb->execute($select);
        $this->assertSame(0, $results->count());

        $this->assertTrue($this->l10n->addDynamicTranslation('A text for flow test'));
        $results = $this->zdb->execute($select);
        $this->assertSame(count($langs), $results->count());

        foreach ($results as $result) {
            $this->assertTrue(in_array(str_replace('.utf8', '', $result['text_locale']), $langs));
            $this->assertSame(1, (int)$result['text_nref']);
            $this->assertSame(($result['text_locale'] == 'en_US' ? 'A text for flow test' : ''), $result['text_trans']);
        }

        //do translation
        $this->assertTrue(
            $this->l10n->updateDynamicTranslation(
                'A text for flow test',
                'en_US',
                'A text for flow test translated'
            )
        );

        $this->assertTrue(
            $this->l10n->updateDynamicTranslation(
                'A text for flow test',
                'fr_FR.utf8',
                'Un texte pour le test flow'
            )
        );

        $to_translate = $this->l10n->getStringsToTranslate();
        $this->assertCount(1, $to_translate);

        $results = $this->l10n->getDynamicTranslations(md5('A text for flow test'));
        $this->assertCount(count($langs), $results);
        foreach ($results as $result) {
            match ($result['key']) {
                'en_US' => $this->assertSame('A text for flow test translated', $result['text']),
                'fr_FR.utf8' => $this->assertSame('Un texte pour le test flow', $result['text']),
                default => $this->assertSame('', $result['text']),
            };
        }

        //remove translation
        $this->assertTrue($this->l10n->deleteDynamicTranslation('A text for flow test'));
        $results = $this->zdb->execute($select);
        $this->assertSame(0, $results->count());

        //getDynamicTranslations will still return an array for each known language
        $results = $this->l10n->getDynamicTranslations(md5('A text for flow test'));
        $this->assertCount(count($langs), $results);
        foreach ($results as $result) {
            $this->assertSame('', $result['text'], $result['key']);
        }

        //update method will create text if it does not exist
        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->columns([
            'text_locale',
            'text_nref',
            'text_trans'
        ]);
        $select->where(['text_orig' => 'A text created on update']);
        $results = $this->zdb->execute($select);
        $this->assertSame(0, $results->count());

        $this->assertTrue($this->l10n->updateDynamicTranslation('A text created on update', 'en_US', 'A text created on update translated'));
        $this->assertTrue($this->l10n->updateDynamicTranslation('A text created on update', 'fr_FR.utf8', 'Un texte créé à la mise à jour'));
        $results = $this->zdb->execute($select);
        $this->assertSame(2, $results->count());

        foreach ($results as $result) {
            match ($result['text_locale']) {
                'en_US' => $this->assertSame('A text created on update translated', $result['text_trans']),
                'fr_FR.utf8' => $this->assertSame('Un texte créé à la mise à jour', $result['text_trans']),
                default => $this->assertSame('', $result['text_trans']),
            };
        }
    }

    /**
     * Test a translation shared by several objects
     */
    public function testSharedDynamicTranslation(): void
    {
        $this->i18n->changeLanguage('en_US');
        $text = 'A shared text for test';

        $this->assertTrue($this->l10n->addDynamicTranslation($text));
        $this->assertTrue($this->l10n->updateDynamicTranslation($text, 'fr_FR.utf8', 'Un texte partagé'));
        //second object using the same text
        $this->assertTrue($this->l10n->addDynamicTranslation($text));
        //language with no row yet: created with a lower count
        $this->zdb->execute(
            $this->zdb->delete(\Galette\Core\L10n::TABLE)->where(['text_orig_sum' => md5($text), 'text_locale' => 'de_DE.utf8'])
        );
        $this->assertTrue($this->l10n->updateDynamicTranslation($text, 'de_DE.utf8', 'Ein geteilter Text'));

        //first object released: translations are kept for the second one
        $this->assertTrue($this->l10n->deleteDynamicTranslation($text));
        $this->assertSame('Un texte partagé', $this->l10n->getDynamicTranslation($text, 'fr_FR.utf8'));
        $this->assertSame('Ein geteilter Text', $this->l10n->getDynamicTranslation($text, 'de_DE.utf8'));
        $this->expectLogEntry(\Analog\Analog::INFO, 'Dynamic translation for `' . $text . '` is still in use, kept.');

        //last object released: translations are removed
        $this->assertTrue($this->l10n->deleteDynamicTranslation($text));
        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(['text_orig_sum' => md5($text)]);
        $this->assertSame(0, $this->zdb->execute($select)->count());
    }

    /**
     * Test renaming a translation, keeping existing translations
     */
    public function testRenameDynamicTranslation(): void
    {
        $this->i18n->changeLanguage('en_US');
        $old = 'A text to rename';
        $new = 'A renamed text';

        $this->assertTrue($this->l10n->addDynamicTranslation($old));
        $this->assertTrue($this->l10n->updateDynamicTranslation($old, 'fr_FR.utf8', 'Un texte à renommer'));
        $this->assertTrue($this->l10n->updateDynamicTranslation($old, 'de_DE.utf8', 'Ein umzubenennender Text'));

        $this->assertTrue($this->l10n->renameDynamicTranslation($old, $new));

        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(['text_orig_sum' => md5($old)]);
        $this->assertSame(0, $this->zdb->execute($select)->count());

        //new text is in current language, other translations are kept
        $this->assertSame($new, $this->l10n->getDynamicTranslation($new, 'en_US'));
        $this->assertSame('Un texte à renommer', $this->l10n->getDynamicTranslation($new, 'fr_FR.utf8'));
        $this->assertSame('Ein umzubenennender Text', $this->l10n->getDynamicTranslation($new, 'de_DE.utf8'));
        $this->assertSame('', $this->l10n->getDynamicTranslation($new, 'es.utf8'));

        //existing translations of the new text are not overwritten
        $other = 'Another existing text';
        $this->assertTrue($this->l10n->addDynamicTranslation($other));
        $this->assertTrue($this->l10n->updateDynamicTranslation($other, 'fr_FR.utf8', 'Un autre texte existant'));
        $this->assertTrue($this->l10n->renameDynamicTranslation($new, $other));
        $this->assertSame('Un autre texte existant', $this->l10n->getDynamicTranslation($other, 'fr_FR.utf8'));
        $this->assertSame('Ein umzubenennender Text', $this->l10n->getDynamicTranslation($other, 'de_DE.utf8'));

        //renaming to a Galette label stores nothing
        $this->assertTrue($this->l10n->renameDynamicTranslation($other, 'annual fee'));
        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->where(['text_orig_sum' => md5('annual fee')]);
        $this->assertSame(0, $this->zdb->execute($select)->count());
        $this->expectLogEntry(\Analog\Analog::INFO, 'Dynamic translation for `' . $other . '` is still in use, kept.');
    }

    /**
     * Test add dynamic translation with exception
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testAddWException(): void
    {
        $zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(['execute'])
            ->getMock();

        $zdb->method('execute')
            ->willReturnCallback(
                function (): void {
                    throw new \LogicException('Error executing query!', 123);
                }
            );

        $l10n = new \Galette\Core\L10n(
            $zdb,
            $this->i18n,
            $this->container->get(\Galette\Core\Translator::class)
        );
        $this->assertFalse($l10n->addDynamicTranslation('A text that will not be added'));
        $this->expectLogEntry(\Analog\Analog::ERROR, 'An error occurred adding dynamic translation for `A text that will not be added` | Error executing query!');
    }

    /**
     * Test update dynamic translation with exception
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testUpdateWException(): void
    {
        $zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(['execute'])
            ->getMock();

        $zdb->method('execute')
            ->willReturnCallback(
                function (): void {
                    throw new \LogicException('Error executing query!', 123);
                }
            );

        $l10n = new \Galette\Core\L10n(
            $zdb,
            $this->i18n,
            $this->container->get(\Galette\Core\Translator::class)
        );
        $this->assertFalse(
            $l10n->updateDynamicTranslation(
                'A text that will not be updated',
                'en_US',
                'A text that will not be updated'
            )
        );
        $this->expectLogEntry(\Analog\Analog::ERROR, 'An error occurred updating dynamic translation for `A text that will not be updated` | Error executing query!');
    }

    /**
     * Test delete dynamic translation with exception
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testDeleteWException(): void
    {
        $zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(['execute'])
            ->getMock();

        $zdb->method('execute')
            ->willReturnCallback(
                function (): void {
                    throw new \LogicException('Error executing query!', 123);
                }
            );

        $l10n = new \Galette\Core\L10n(
            $zdb,
            $this->i18n,
            $this->container->get(\Galette\Core\Translator::class)
        );
        $this->assertFalse($l10n->deleteDynamicTranslation('A text that will not be deleted'));
        $this->expectLogEntry(\Analog\Analog::ERROR, 'An error occurred deleting dynamic translation for `A text that will not be deleted` | Error executing query!');
    }

    /**
     * Test get dynamic translation with exception
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetWException(): void
    {
        $zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(['execute'])
            ->getMock();

        $zdb->method('execute')
            ->willReturnCallback(
                function (): void {
                    throw new \LogicException('Error executing query!', 123);
                }
            );

        $l10n = new \Galette\Core\L10n(
            $zdb,
            $this->i18n,
            $this->container->get(\Galette\Core\Translator::class)
        );

        $exception_thrown = false;
        try {
            $l10n->getDynamicTranslation('A text that will not be get', 'en_US');
        } catch (\LogicException $e) {
            $exception_thrown = true;
            $this->assertSame('Error executing query!', $e->getMessage());
        }
        $this->assertTrue($exception_thrown, 'No exception has been thrown');
        $this->expectLogEntry(\Analog\Analog::WARNING, 'An error occurred retrieving l10n entry. text_orig=A text that will not be get, text_locale=en_US | Error executing query!');
    }

    /**
     * Test get dynamic translations with exception
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetsWException(): void
    {
        $zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(['execute'])
            ->getMock();

        $zdb->method('execute')
            ->willReturnCallback(
                function (): void {
                    throw new \LogicException('Error executing query!', 123);
                }
            );

        $l10n = new \Galette\Core\L10n(
            $zdb,
            $this->i18n,
            $this->container->get(\Galette\Core\Translator::class)
        );

        $exception_thrown = false;
        try {
            $l10n->getDynamicTranslations(md5('A text that will not be get'));
        } catch (\LogicException $e) {
            $exception_thrown = true;
            $this->assertSame('Error executing query!', $e->getMessage());
        }
        $this->assertTrue($exception_thrown, 'No exception has been thrown');
        $this->expectLogEntry(\Analog\Analog::WARNING, 'An error occurred retrieving l10n entries. text_orig_sum=' . md5('A text that will not be get') . ' | Error executing query!');
    }
}
