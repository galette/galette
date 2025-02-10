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

namespace Galette\Core\test\units;

use PHPUnit\Framework\TestCase;

/**
 * I18n tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class I18n extends TestCase
{
    private \Galette\Core\Db $zdb;
    private ?\Galette\Core\I18n $i18n = null;
    private \Galette\Core\Galette $galette;

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
        $this->galette = new \Galette\Core\Galette();
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
    }

    /**
     * Test lang autodetect
     *
     * @return void
     */
    public function testAutoLang(): void
    {
        $this->i18n = new \Galette\Core\I18n();

        $this->assertSame(\Galette\Core\I18n::DEFAULT_LANG, $this->i18n->getID());

        //simulate fr from browser
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr_BE';
        $this->i18n = new \Galette\Core\I18n();

        $this->assertSame('fr_FR', $this->i18n->getID());

        //simulate en from browser
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en_GB';
        $this->i18n = new \Galette\Core\I18n();

        $this->assertSame('en_US', $this->i18n->getID());

        //simulate unknown lang from browser
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'un_KN';
        $this->i18n = new \Galette\Core\I18n();

        $this->assertSame(\Galette\Core\I18n::DEFAULT_LANG, $this->i18n->getID());
    }

    /**
     * Test languages list
     *
     * @return void
     */
    public function testGetList(): void
    {
        $list = $this->i18n->getList();

        $this->assertGreaterThan(3, count($list));

        foreach ($list as $elt) {
            $this->assertInstanceOf('\Galette\Core\I18n', $elt);
        }
    }

    /**
     * Test languages list as array
     *
     * @return void
     */
    public function testGetArrayList(): void
    {
        $list = $this->i18n->getArrayList();

        $this->assertGreaterThan(3, count($list));
    }

    /**
     * Test getting language name from its ID
     *
     * @return void
     */
    public function testGetNameFromid(): void
    {
        $lang = $this->i18n->getNameFromId('en_US');
        $this->assertSame('English', $lang);

        $lang = $this->i18n->getNameFromId('fr_FR');
        $this->assertSame('Français', $lang);
    }

    /**
     * Test retrieving language information
     *
     * @return void
     */
    public function testGetLangInfos(): void
    {
        $id = $this->i18n->getID();
        $longid = $this->i18n->getLongID();
        $name = $this->i18n->getName();
        $abbrev = $this->i18n->getAbbrev();

        $this->assertSame('en_US', $id);
        $this->assertSame('en_US', $longid);
        $this->assertSame('English', $name);
        $this->assertSame('en', $abbrev);

        $this->i18n->changeLanguage('fr_FR');
        $id = $this->i18n->getID();
        $longid = $this->i18n->getLongID();
        $name = $this->i18n->getName();
        $abbrev = $this->i18n->getAbbrev();

        $this->assertSame('fr_FR', $id);
        $this->assertSame('fr_FR.utf8', $longid);
        $this->assertSame('Français', $name);
        $this->assertSame('fr', $abbrev);
    }

    /**
     * Change to an unknown language
     *
     * @return void
     */
    public function testChangeUnknownLanguage(): void
    {
        $this->i18n->changeLanguage('un_KN');
        $id = $this->i18n->getID();

        $this->assertSame(\Galette\Core\I18n::DEFAULT_LANG, $id);
    }

    /**
     * Check (non) UTF strings
     *
     * @return void
     */
    public function testSeemUtf8(): void
    {
        $is_utf = $this->i18n->seemUtf8('HéhéHÉHÉâ-ôß¬- ©»«<ëßßä€êþÿûîœô');
        $is_iso = $this->i18n->seemUtf8(mb_convert_encoding('Héhé', 'ISO-8859-1'));

        $this->assertTrue($is_utf);
        $this->assertFalse($is_iso);
    }

    /**
     * Test getting online documentation base URL
     *
     * @return void
     */
    public function testGetDocumentationBaseUrl(): void
    {
        $docbaseurl = $this->i18n->getDocumentationBaseUrl();
        $branch = (preg_match('(-git)', $this->galette->gitVersion()) ? 'develop' : 'master') . '/';

        $this->assertSame('https://doc.galette.eu/en/' . $branch, $docbaseurl);

        $this->i18n->changeLanguage('fr_FR');
        $docbaseurl = $this->i18n->getDocumentationBaseUrl();

        $this->assertSame('https://doc.galette.eu/fr/' . $branch, $docbaseurl);

        $this->i18n->changeLanguage('si');
        $docbaseurl = $this->i18n->getDocumentationBaseUrl();

        $this->assertSame('https://doc.galette.eu/en/' . $branch, $docbaseurl);

        $this->i18n->changeLanguage('nb_NO');
        $docbaseurl = $this->i18n->getDocumentationBaseUrl();

        $this->assertSame('https://doc.galette.eu/no/' . $branch, $docbaseurl);
    }
}
