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
            $this->i18n
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
            $this->i18n
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
            $this->i18n
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
            $this->i18n
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
            $this->i18n
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
            $this->i18n
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
