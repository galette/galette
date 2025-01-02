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

/**
 * Required modules checking
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CheckModules
{
    /** @var array<string,string> */
    private array $good = array();
    /** @var array<int,string> */
    private array $should = array();
    /** @var array<int,string> */
    private array $missing = array();

    /** @var array<string,bool> */
    private array $modules = [
        //name      => required
        'SimpleXML' => true,
        'gd'        => true,
        'pdo'       => true,
        'curl'      => false,
        'gettext'   => false,
        'mbstring'  => true,
        'openssl'   => false,
        'intl'      => true,
        'session'   => true
    ];


    /**
     * Constructor
     *
     * @param boolean $do Whether to do checks, defaults to true
     */
    public function __construct(bool $do = true)
    {
        if ($do === true) {
            $this->doCheck();
        }
    }

    /**
     * Check various modules and dispatch them between:
     * - good: module that are present,
     * - may: modules that may be present but are not,
     * - should: modules that should be present but are not,
     * - missing: required modules that are missing
     *
     * @param boolean $translated Use translations (default to true)
     *
     * @return void
     */
    public function doCheck(bool $translated = true): void
    {
        $string = ($translated ? _T("'%s' module") : "'%s' module");
        foreach ($this->modules as $name => $required) {
            if ($name == 'pdo') {
                //one of mysql or pgsql driver must be present
                $mstring = "either 'mysql' or 'pgsql' PDO driver";
                if ($translated) {
                    $mstring = _T("either 'mysql' or 'pgsql' PDO driver");
                }
                if (
                    !$this->isExtensionLoaded('pdo_mysql')
                    && !$this->isExtensionLoaded('pdo_pgsql')
                ) {
                    $this->missing[] = $mstring;
                } else {
                    $this->good[$name] = $mstring;
                }
            } else {
                $mstring = str_replace('%s', $name, $string);
                if (!$this->isExtensionLoaded($name)) {
                    if ($required) {
                        $this->missing[] = $mstring;
                    } else {
                        $this->should[] = $mstring;
                    }
                } else {
                    $this->good[$name] = str_replace('%s', $name, $string);
                }
            }
        }
    }

    /**
     * HTML formatted results for checks
     *
     * @param boolean $translated Use translations (default to true)
     *
     * @return string
     */
    public function toHtml(bool $translated = true): string
    {
        $html = null;

        if (count($this->missing) > 0) {
            $ko = ($translated ? _T('Ko') : 'Ko');
            foreach ($this->missing as $m) {
                $html .= '<li><span>' . $m . '</span><span><i class="ui red times icon" aria-hidden="true"></i><span class="visually-hidden">' .
                    $ko . '</span></span></li>';
            }
        }

        if (count($this->good) > 0) {
            $ok = ($translated ? _T('Ok') : 'Ok');
            foreach ($this->good as $m) {
                $html .= '<li><span>' . $m . '</span><span><i class="ui green check icon" aria-hidden="true"></i><span class="visually-hidden">' .
                    $ok . '</span></span></li>';
            }
        }

        if (count($this->should) > 0) {
            foreach ($this->should as $m) {
                $html .= '<li><span>' . $m . '</span><span><i class="ui yellow exclamation circle icon" aria-hidden="true"></i></span></li>';
            }
        }

        return $html;
    }

    /**
     * Check if it is ok to use Galette with current modules
     *
     * @return boolean
     */
    public function isValid(): bool
    {
        return count($this->missing) === 0;
    }

    /**
     * Check if a specific module is OK for that instance
     *
     * @param string $module Module name to check
     *
     * @return boolean
     */
    public function isGood(string $module): bool
    {
        return isset($this->good[$module]);
    }

    /**
     * Retrieve good modules
     *
     * @return array<string,string>
     */
    public function getGoods(): array
    {
        return $this->good;
    }

    /**
     * Retrieve modules that should be present
     *
     * @return array<int,string>
     */
    public function getShoulds(): array
    {
        return $this->should;
    }

    /**
     * Retrieve missing modules
     *
     * @return array<int,string>
     */
    public function getMissings(): array
    {
        return $this->missing;
    }

    /**
     * Check if a module is loaded
     *
     * @param string $ext Module name
     *
     * @return bool
     */
    protected function isExtensionLoaded(string $ext): bool
    {
        return extension_loaded($ext);
    }
}
