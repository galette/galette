<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * i18n handling
 *
 * PHP version 5
 *
 * Copyright © 2007-2018 The Galette Team
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
 * @copyright 2007-2018 The Galette Team
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

    const DEFAULT_LANG = 'fr_FR';

    private $dir = 'lang/';
    private $path;

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
        $this->guessLangs();

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
     * Load language parameters
     *
     * @param string $id Identifier for requested language
     *
     * @return void
     */
    public function changeLanguage($id)
    {
        Analog::log('Trying to set locale to ' . $id, Analog::DEBUG);
        $this->load($id);
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

        setlocale(LC_ALL, $this->getLongID());

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
        if (!isset($this->langs[$id])) {
            $msg = 'Lang ' . $id . ' does not exist, switching to default.';
            Analog::log($msg, Analog::WARNING);
            $id = self::DEFAULT_LANG;
        }
        $lang = $this->langs[$id];
        $this->id       = $id;
        $this->longid   = $lang['long'];
        $this->name     = $lang['longname'];
        $this->abbrev   = $lang['shortname'];
        $this->flag     = $lang['flag'];
    }

    /**
     * List languages
     *
     * @return array list of all active languages
     */
    public function getList()
    {
        $result = array();
        foreach (array_keys($this->langs) as $id) {
            $result[] = new I18n((string)$id);
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
            //FIXME: shoudl use mb with sthing like:
            //$strlen = mb_strlen($string, $encoding);
            //$firstChar = mb_substr($string, 0, 1, $encoding);
            //$then = mb_substr($string, 1, $strlen - 1, $encoding);
            //return mb_strtoupper($firstChar, $encoding) . $then;
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
        if (isset($this->langs[$id])) {
            return $this->langs[$id]['longname'];
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
        $path = null;
        if (!isset($this->langs[$id])) {
            Analog::log(
                str_replace(
                    '%lang',
                    $id,
                    _T('Unknown lang (%lang)')
                ),
                Analog::INFO
            );
        } else {
            if (defined('GALETTE_THEME_DIR')) {
                $path = GALETTE_THEME_DIR . 'images/flags/' . $this->langs['id']['flag'];
            } else {
                $path = GALETTE_THEME . 'images/flags/' . $this->langs[$id]['flag'];
            }
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
            return GALETTE_THEME_DIR . 'images/flags/' . $this->flag;
        } else {
            return GALETTE_THEME . 'images/flags/' . $this->flag;
        }
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

    /**
     * Guess available languages from directories
     * that are present in the lang directory.
     *
     * Will store foud langs in class langs variable and return it.
     *
     * @return array
     */
    public function guessLangs()
    {
        $dir = new \DirectoryIterator($this->path);
        $flags_dir = GALETTE_ROOT . 'webroot/themes/default/images/flags/';
        $langs = [];
        foreach ($dir as $fileinfo) {
            if ($fileinfo->isDir() && !$fileinfo->isDot()) {
                $lang = $fileinfo->getFilename();
                $real_lang = str_replace('.utf8', '', $lang);
                $parsed_lang = \Locale::parseLocale($lang);
                $flag = (file_exists($flags_dir . $real_lang . '.svg') ?
                    $real_lang . '.svg' :
                    'default.svg');

                $langs[$real_lang] = [
                    'long'      => $lang,
                    'shortname' => $parsed_lang['language'],
                    'longname'  => \Locale::getDisplayLanguage(
                        $lang,
                        $lang
                    ),
                    'flag'      => $flag
                ];
            }
        }
        ksort($langs);
        $this->langs = $langs;
        return $this->langs;
    }
}
