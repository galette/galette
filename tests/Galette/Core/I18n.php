<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\GaletteTestCase;

use function Safe\preg_match;
use function Safe\mb_convert_encoding;

/**
 * I18n tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class I18n extends GaletteTestCase
{
    private \Galette\Core\Galette $galette;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->galette = new \Galette\Core\Galette();
    }

    /**
     * Test lang autodetect
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
     */
    public function testGetList(): void
    {
        $list = $this->i18n->getList();

        $this->assertGreaterThan(3, count($list));

        foreach ($list as $elt) {
            $this->assertInstanceOf(\Galette\Core\I18n::class, $elt);
        }
    }

    /**
     * Test languages list as array
     */
    public function testGetArrayList(): void
    {
        $list = $this->i18n->getArrayList();

        $this->assertGreaterThan(3, count($list));
    }

    /**
     * Test getting language name from its ID
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
     */
    public function testChangeUnknownLanguage(): void
    {
        $this->i18n->changeLanguage('un_KN');
        $id = $this->i18n->getID();

        $this->assertSame(\Galette\Core\I18n::DEFAULT_LANG, $id);
        $this->expectLogEntry(\Analog\Analog::WARNING, "Lang un_KN does not exist, switching to default.");
    }

    /**
     * Check (non) UTF strings
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
