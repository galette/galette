<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * i18n tests
 *
 * PHP version 5
 *
 * Copyright © 2013-2023 The Galette Team
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
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2013-01-13
 */

namespace Galette\Core\test\units;

use PHPUnit\Framework\TestCase;

/**
 * I18n tests class
 *
 * @category  Core
 * @name      i18n
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2013-01-13
 */
class I18n extends TestCase
{
    private \Galette\Core\Db $zdb;
    private ?\Galette\Core\I18n $i18n = null;

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
    public function testAutoLang()
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
    public function testGetList()
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
    public function testGetArrayList()
    {
        $list = $this->i18n->getArrayList();

        $this->assertGreaterThan(3, count($list));
    }

    /**
     * Test getting language name from its ID
     *
     * @return void
     */
    public function testGetNameFromid()
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
    public function testGetLangInfos()
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
    public function testChangeUnknownLanguage()
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
    public function testSeemUtf8()
    {
        $is_utf = $this->i18n->seemUtf8('HéhéHÉHÉâ-ôß¬- ©»«<ëßßä€êþÿûîœô');
        $is_iso = $this->i18n->seemUtf8(mb_convert_encoding('Héhé', 'ISO-8859-1'));

        $this->assertTrue($is_utf);
        $this->assertFalse($is_iso);
    }
}
