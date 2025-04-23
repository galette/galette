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

namespace Galette\Core;

use Analog\Analog;

/**
 * i18n handling
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class I18n
{
    private string $id;
    private string $longid;
    private string $name;
    private string $abbrev;

    public const DEFAULT_LANG = 'en_US';

    private string $dir = 'lang/';
    private string $path;

    /** @var array<string,array<string,string>> */
    private array $langs = [];
    /** @var array<int,string> */
    private array $rtl_langs = [
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
     * @param string|false $lang true if there were a language change
     *
     * @return void
     */
    public function __construct(string|false $lang = false)
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
                        [$l, $q] = array_merge(explode(';q=', $el), [1]);
                        $res[$l] = (float)$q;
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
    public function changeLanguage(string $id): void
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
    public function updateEnv(): void
    {
        global $translator;

        setlocale(LC_ALL, $this->getLongID());

        $textdomain = realpath(GALETTE_ROOT . 'lang');
        //main translation domain
        $domain = 'galette';
        bindtextdomain($domain, $textdomain);
        //set default translation domain and encoding
        textdomain($domain);
        bind_textdomain_codeset($domain, 'UTF-8');

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
    private function load(string $id): void
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
     * @return array<int, I18n> list of all active languages
     */
    public function getList(): array
    {
        $result = [];
        foreach (array_keys($this->langs) as $id) {
            $result[] = new I18n((string)$id);
        }

        return $result;
    }

    /**
     * List languages as simple array
     *
     * @return array<string,string>
     */
    public function getArrayList(): array
    {
        $list = $this->getList();
        $al = [];
        foreach ($list as $l) {
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
    public function getNameFromId(string $id): string
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
    public function getID(): string
    {
        return $this->id;
    }

    /**
     * Get long identifier
     *
     * @return string current language long identifier
     */
    public function getLongID(): string
    {
        return $this->longid;
    }

    /**
     * Get current name
     *
     * @return string current language name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get current abbreviation
     *
     * @return string current language abbreviation
     */
    public function getAbbrev(): string
    {
        return $this->abbrev;
    }

    /**
     * Does string seem to be encoded as UTF-8?
     *
     * @param string $str string to analyze
     *
     * @return  boolean
     */
    public static function seemUtf8(string $str): bool
    {
        return mb_check_encoding($str, 'UTF-8');
    }

    /**
     * Guess available languages from directories
     * that are present in the lang directory.
     *
     * Will store found langs in class langs variable and return it.
     *
     * @return array<string,array<string,string>>
     */
    public function guessLangs(): array
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
    public function isRTL(): bool
    {
        return in_array(
            $this->getAbbrev(),
            $this->rtl_langs
        );
    }

    /**
     * Get current online documentation base URL
     *
     * @return string current online documentation base URL (language and branch included)
     */
    public function getDocumentationBaseUrl(): string
    {
        $url = 'https://doc.galette.eu/';
        $lang = $this->abbrev . '/';
        $branch = (preg_match('(-git)', Galette::gitVersion()) ? 'develop' : 'master') . '/';
        $not_translated = [
            'ota',
            'si'
        ];
        if (in_array($this->abbrev, $not_translated)) {
            $lang = 'en/';
        } elseif ($this->abbrev == 'nb') {
            $lang = 'no/';
        }
        return $url . $lang . $branch;
    }
}
