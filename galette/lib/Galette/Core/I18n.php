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
 * @copyright 2007-2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
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
 * @copyright 2007-2020 The Galette Team
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

    public const DEFAULT_LANG = 'fr_FR';

    private $dir = 'lang/';
    private $path;

    private $rtl_langs = [
        'ar',
        'az',
        'fa',
        'he',
        'ur'
    ];

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
                $preferred_locales = array_reduce(
                    explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']),
                    function ($res, $el) {
                        list($l, $q) = array_merge(explode(';q=', $el), [1]);
                        $res[$l] = (float) $q;
                        return $res;
                    },
                    []
                );
                arsort($preferred_locales);

                foreach (array_keys($preferred_locales) as $preferred_locale) {
                    $short_locale = explode('_', $preferred_locale)[0];
                    foreach (array_keys($this->langs) as $lang) {
                        $short_key = explode('_', $lang)[0];
                        if ($short_key == $short_locale) {
                            $dlang = $lang;
                            break 2;
                        }
                    }
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
        global $translator;

        setlocale(LC_ALL, $this->getLongID());

        if (
            putenv("LANG=" . $this->getLongID())
            or putenv("LANGUAGE=" . $this->getLongID())
            or putenv("LC_ALL=" . $this->getLongID())
        ) {
            $textdomain = realpath(GALETTE_ROOT . 'lang');
            //main translation domain
            $domain = 'galette';
            bindtextdomain($domain, $textdomain);
            //set default translation domain and encoding
            textdomain($domain);
            bind_textdomain_codeset($domain, 'UTF-8');
        }
        if ($translator) {
            $translator->setLocale($this->getLongID());
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
            //FIXME: should use mb with something like:
            //$strlen = mb_strlen($string, $encoding);
            //$firstChar = mb_substr($string, 0, 1, $encoding);
            //$then = mb_substr($string, 1, $strlen - 1, $encoding);
            //return mb_strtoupper($firstChar, $encoding) . $then;
            $al[$l->getID()] = $l->getName();
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
        $langs = [];
        foreach ($dir as $fileinfo) {
            if ($fileinfo->isDir() && !$fileinfo->isDot()) {
                $lang = $fileinfo->getFilename();
                $real_lang = str_replace('.utf8', '', $lang);
                $parsed_lang = \Locale::parseLocale($lang);

                $langs[$real_lang] = [
                    'long'      => $lang,
                    'shortname' => $parsed_lang['language'] ?? '',
                    'longname'  => mb_convert_case(
                        \Locale::getDisplayLanguage(
                            $lang,
                            $real_lang
                        ),
                        MB_CASE_TITLE,
                        'UTF-8'
                    )
                ];
            }
        }
        ksort($langs);
        $this->langs = $langs;
        return $this->langs;
    }

    /**
     * Is current language RTL?
     *
     * @return boolean
     */
    public function isRTL()
    {
        return in_array(
            $this->getAbbrev(),
            $this->rtl_langs
        );
    }
}
