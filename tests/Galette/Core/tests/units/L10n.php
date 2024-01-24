<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

namespace Galette\Core\test\units;

use PHPUnit\Framework\TestCase;

/**
 * L10n tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class L10n extends TestCase
{
    private \Galette\Core\Db $zdb;
    private \Galette\Core\I18n $i18n;
    private \Galette\Core\L10n $l10n;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->zdb = new \Galette\Core\Db();
        $this->i18n = new \Galette\Core\I18n(
            \Galette\Core\I18n::DEFAULT_LANG
        );
        $this->l10n = new \Galette\Core\L10n(
            $this->zdb,
            $this->i18n
        );
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        if (TYPE_DB === 'mysql') {
            $this->assertSame([], $this->zdb->getWarnings());
        }
        //cleanup dynamic translations
        $delete = $this->zdb->delete(\Galette\Core\L10n::TABLE);
        $delete
            ->where(['text_orig' => ['A text for test', 'Un texte de test']]);
        $this->zdb->execute($delete);
    }

    /**
     * Test add dynamic translation
     *
     * @return void
     */
    public function testAddDynamicTranslation()
    {
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

        $langs = array_keys($this->i18n->getArrayList());

        $results = $this->zdb->execute($select);
        $this->assertSame(count($langs), $results->count());

        foreach ($results as $result) {
            $this->assertTrue(in_array(str_replace('.utf8', '', $result['text_locale']), $langs));
            $this->assertSame(1, (int)$result['text_nref']);
            $this->assertSame(($result['text_locale'] == 'en_US' ? 'A text for test' : ''), $result['text_trans']);
        }

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

        $langs = array_keys($this->i18n->getArrayList());

        $results = $this->zdb->execute($select);
        $this->assertSame(count($langs), $results->count());

        foreach ($results as $result) {
            $this->assertTrue(in_array(str_replace('.utf8', '', $result['text_locale']), $langs));
            $this->assertSame(1, (int)$result['text_nref']);
            $this->assertSame(($result['text_locale'] == 'fr_FR.utf8' ? 'Un texte de test' : ''), $result['text_trans']);
        }
    }
}
