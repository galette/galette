<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * i18n handling
 *
 * PHP version 5
 *
 * Copyright © 2007-2014 The Galette Team
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
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-07-06
 */

namespace Galette\Core;

use Analog\Analog;

/**
 * i18n handling
 *
 * @category  Core
 * @name      i18n
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-07-06
 */

class I18n
{
    private $id;
    private $longid;
    private $name;
    private $abbrev;
    private $flag;
    private $filename;
    private $alternate;

    const DEFAULT_LANG = 'fr_FR';

    private $dir = 'lang/';
    private $path;
    private $file = 'languages.xml';

    /**
     * Default constructor.
     * Initialize default language and set environment variables
     *
     * @param bool $lang true if there were a language change
     *
     * @return void
     */
    public function __construct($lang = false)
    {
        $this->path = GALETTE_ROOT . $this->dir;
        $this->file = $this->path . $this->file;

        if (!$lang) {
            //try to determine user language
            $dlang = self::DEFAULT_LANG;
            if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $blang = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
                if (substr($blang, 0, 2) == 'fr') {
                    $dlang = 'fr_FR';
                } elseif (substr($blang, 0, 2) == 'en') {
                    $dlang = 'en_US';
                } else {
                    $dlang = self::DEFAULT_LANG;
                }
            }
            $this->changeLanguage($dlang);
        } else {
            $this->load($lang);
        }
    }

    /**
     * Load language parameters from the XML file
     *
     * @param string $id Identifier forv requested language
     *
     * @return void
     */
    public function changeLanguage($id)
    {
        Analog::log('Trying to set locale to ' . $id, Analog::DEBUG);

        $xml = simplexml_load_file($this->file);
        $xpath = '/translations/lang[@id=\'' . $id . '\'][not(@inactive)]';
        $current = $xml->xpath($xpath);

        //if no match, switch to default
        if (!isset($current[0])) {
            $msg = $id . ' does not exist in XML file, switching to default.';
            Analog::log($msg, Analog::WARNING);
            $id = self::DEFAULT_LANG;
            //do not forget to reload informations from the xml file
            $current = $xml->xpath('/translations/lang[@id=\'' . $id . '\']');
        }

        $sxe = $current[0];
        $this->id = $id;
        $this->longid = ( isset($sxe['long']) )?(string)$sxe['long']:$id;
        $this->name = (string)$sxe->longname;
        $this->abbrev = (string)$sxe->shortname;
        $this->flag = (string)$sxe->flag;
        $this->filename = (string)$sxe->filename;
        $this->alternate = (string)$sxe['alter'];

        $this->updateEnv();
    }

    /**
     * Update environment according to locale.
     * Mainly used at app initialization or at login
     *
     * @return void
     */
    public function updateEnv()
    {
        global $disable_gettext;

        setlocale(LC_ALL, $this->getLongID(), $this->getAlternate());

        if (putenv("LANG=" . $this->getLongID())
            or putenv("LANGUAGE=" . $this->getLongID())
            or putenv("LC_ALL=" . $this->getLongID())
        ) {
            $textdomain = realpath(GALETTE_ROOT . 'lang');
            //main translation domain
            $domain = 'galette';
            bindtextdomain($domain, $textdomain);
            //routes translation domain
            $routes_domain = 'routes';
            bindtextdomain($routes_domain, $textdomain);
            //set default translation domain and encoding
            textdomain($domain);
            bind_textdomain_codeset($domain, 'UTF-8');
            bind_textdomain_codeset($routes_domain, 'UTF-8');
        }

        if ($disable_gettext) {
            /*if (isset($GLOBALS['lang'])) {
                unset($GLOBALS['lang']);
            }*/
            $domains = ['galette', 'routes'];
            foreach ($domains as $domain) {
                include GALETTE_ROOT . 'lang/' . $domain . '_' . $this->getLongID() . '.php';
                //check if a local lang file exists and load it
                $locfile = GALETTE_ROOT . 'lang/' . $domain . '_' . $this->getLongID() . '_local.php';
                if (file_exists($locfile)) {
                    include $locfile;
                }
                $GLOBALS['lang'] = $lang;
            }
        }
    }

    /**
     * Load a language
     *
     * @param string $id identifier for the language to load
     *
     * @return void
     */
    private function load($id)
    {
        $xml = simplexml_load_file($this->file);
        $current = $xml->xpath('/translations/lang[@id=\'' . $id . '\']');
        $sxe = $current[0];
        $this->id = $id;
        $this->longid = ( isset($sxe['long']) )?(string)$sxe['long']:$id;
        $this->name = (string)$sxe->longname;
        $this->abbrev = (string)$sxe->shortname;
        $this->flag = (string)$sxe->flag;
        $this->filename = (string)$sxe->filename;
        $this->alternate = (string)$sxe['alter'];
    }

    /**
     * List languages
     *
     * @return array list of all active languages
     */
    public function getList()
    {
        $result = array();
        $xml = simplexml_load_file($this->file);
        foreach ($xml->lang as $lang) {
            if (!$lang['inactive']) {
                $result[] = new I18n((string)$lang['id']);
            }
        }

        return $result;
    }

    /**
     * List languages as simple array
     *
     * @return array
     */
    public function getArrayList()
    {
        $list = $this->getList();
        $al = array();
        foreach ($list as $l) {
            $al[$l->getID()] = ucfirst($l->getName());
        }
        return $al;
    }

    /**
     * Gets language full name from its ID
     *
     * @param string $id the language identifier
     *
     * @return string name for specified identifier
     */
    public function getNameFromId($id)
    {
        $xml = simplexml_load_file($this->file);
        $current = $xml->xpath('/translations/lang[@id=\'' . $id . '\']');
        if (count($current)) {
            $sxe = $current[0];
            return (string)$sxe->longname;
        } else {
            return str_replace(
                '%lang',
                $id,
                _T('Unknown lang (%lang)')
            );
        }
    }

    /**
     * Gets the language flag from its ID
     *
     * @param string $id the language identifier
     *
     * @return string path to flag for specified language identifier
     */
    public function getFlagFromId($id)
    {
        $xml = simplexml_load_file($this->file);
        $current = $xml->xpath('/translations/lang[@id=\'' . $id . '\']');

        $path = null;
        if (count($current)) {
            $sxe = $current[0];
            if (defined('GALETTE_THEME_DIR')) {
                $path = GALETTE_THEME_DIR . 'images/' . $sxe->flag;
            } else {
                $path = GALETTE_THEME . 'images/' . $sxe->flag;
            }
        } else {
            Analog::log(
                str_replace(
                    '%lang',
                    $id,
                    _T('Unknown lang (%lang)')
                ),
                Analog::INFO
            );
        }

        return $path;
    }

    /**
     * Get current id
     *
     * @return string current language identifier
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Get long identifier
     *
     * @return string current language long identifier
     */
    public function getLongID()
    {
        return $this->longid;
    }

    /**
     * Return alternative identifier (mostly to fix Wind/Mac bugs!
     *
     * @return string current language alternative identifier
     */
    public function getAlternate()
    {
        return $this->alternate;
    }

    /**
     * Get current name
     *
     * @return string current language name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get current abreviation
     *
     * @return string current language abreviation
     */
    public function getAbbrev()
    {
        return $this->abbrev;
    }

    /**
     * Get current flag
     *
     * @return string path to the current language flag image
     */
    public function getFlag()
    {
        if (defined('GALETTE_THEME_DIR')) {
            return GALETTE_THEME_DIR . 'images/' . $this->flag;
        } else {
            return GALETTE_THEME . 'images/' . $this->flag;
        }
    }

    /**
     * Get current filename
     *
     * @return string current filename
     */
    public function getFileName()
    {
        return $this->filename;
    }

    /**
     * Is a string seem to be UTF-8 one ?
     *
     * @param string $str string to analyze
     *
     * @return  boolean
     */
    public static function seemUtf8($str)
    {
        return mb_check_encoding($str, 'UTF-8');
    }
}
