<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * i18n tests
 *
 * PHP version 5
 *
 * Copyright © 2013 The Galette Team
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
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2013-01-13
 */

namespace Galette\Core\test\units;

use \atoum;

/**
 * I18n tests class
 *
 * @category  Core
 * @name      i18n
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2013-01-13
 */
class I18n extends atoum
{
    private $_i18n = null;

    /**
     * Set up tests
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
    {
        $this->_i18n = new \Galette\Core\I18n(
            \Galette\Core\I18n::DEFAULT_LANG
        );
    }

    /**
     * Test lang autodetect
     *
     * @return void
     */
    public function testAutoLang()
    {
        $this->_i18n = new \Galette\Core\I18n();

        $this->variable($this->_i18n->getID())
            ->isIdenticalTo('fr_FR');

        //simulate fr from browser
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr_BE';
        $this->_i18n = new \Galette\Core\I18n();

        $this->variable($this->_i18n->getID())
            ->isIdenticalTo('fr_FR');

        //simulate en from browser
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en_GB';
        $this->_i18n = new \Galette\Core\I18n();

        $this->variable($this->_i18n->getID())
            ->isIdenticalTo('en_US');

        //simulate unknown lang from browser
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'es_ES';
        $this->_i18n = new \Galette\Core\I18n();

        $this->variable($this->_i18n->getID())
            ->isIdenticalTo('fr_FR');

    }

    /**
     * Test languages list
     *
     * @return void
     */
    public function testGetList()
    {
        $list = $this->_i18n->getList();

        $this->array($list)
            ->hasSize(2);

        foreach ( $list as $elt ) {
            $this->object($elt)
                ->isInstanceOf('\Galette\Core\I18n');
        }
    }

    /**
     * Test getting language name from its ID
     *
     * @return void
     */
    public function testGetNameFromid()
    {
        $lang = $this->_i18n->getNameFromId('en_US');

        $this->variable($lang)
            ->isIdenticalTo('english');
    }

    /**
     * Test getting flag from its ID
     *
     * @return void
     */
    public function testGetFlagFromid()
    {
        $flag = $this->_i18n->getFlagFromId('en_US');

        $this->variable($flag)
            ->isIdenticalTo(
                GALETTE_BASE_PATH . GALETTE_TPL_SUBDIR . 'images/english.gif'
            );
    }

    /**
     * Test retrieving language informations
     *
     * @return void
     */
    public function testGetLangInfos()
    {
        $id = $this->_i18n->getID();
        $longid = $this->_i18n->getLongID();
        $alt = $this->_i18n->getAlternate();
        $name = $this->_i18n->getName();
        $abbrev = $this->_i18n->getAbbrev();
        $flag = $this->_i18n->getFlag();
        $file = $this->_i18n->getFileName();

        $this->variable($id)
            ->isIdenticalTo('fr_FR');
        $this->variable($longid)
            ->isIdenticalTo('fr_FR.utf8');
        $this->variable($alt)
            ->isIdenticalTo('fra');
        $this->variable($name)
            ->isIdenticalTo('français');
        $this->variable($abbrev)
            ->isIdenticalTo('fr');
        $this->variable($flag)
            ->isIdenticalTo(
                GALETTE_BASE_PATH . GALETTE_TPL_SUBDIR . 'images/french.gif'
            );
        $this->variable($file)
            ->isIdenticalTo('french');
    }

    /**
     * Change to an unknown language
     *
     * @return void
     */
    public function testChangeUnknownLanguage()
    {
        $this->_i18n->changeLanguage('de_DE');
        $id = $this->_i18n->getID();

        $this->variable($id)
            ->isIdenticalTo('fr_FR');
    }

    /**
     * Check (non) UTF strings
     *
     * @return void
     */
    public function testSeemUtf8()
    {
        $is_utf = $this->_i18n->seemUtf8('HéhéHÉHÉâ-ôß¬- ©»«<ëßßä€êþÿûîœô');
        $is_iso = $this->_i18n->seemUtf8(utf8_decode('Héhé'));

        $this->boolean($is_utf)->isTrue();
        $this->boolean($is_iso)->isFalse();
    }
}
