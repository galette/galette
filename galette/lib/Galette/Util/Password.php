<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Password checks
 *
 * PHP version 5
 *
 * Copyright © 2020-2023 The Galette Team
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
 * @category  Util
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4
 */

namespace Galette\Util;

use Analog\Analog;
use Galette\Core\Preferences;
use Galette\Entity\Adherent;

/**
 * Password checks
 *
 * @category  Util
 * @name      Password
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @see       https://github.com/rollerworks/PasswordStrengthValidator
 * @since     Available since 0.9.4
 */
class Password
{
    protected $preferences;
    protected $errors = [];
    protected $strength_errors = [];
    protected $strength = null;
    protected $blacklisted = false;
    protected $personal_infos = [];

    /**
     * Default constructor
     *
     * @param Preferences $prefs Preferences instance
     */
    public function __construct(Preferences $prefs)
    {
        $this->preferences = $prefs;
    }

    /**
     * Does password suits requirements?
     *
     * @param string $password Password to test
     *
     * @return boolean
     */
    public function isValid($password)
    {
        $this->errors = []; //reset

        if ($this->isBlacklisted($password)) {
            $this->errors[] = _T('Password is blacklisted!');
            //no need here to check lenght/strength
            $this->strength = 0;
            return false;
        }

        if (mb_strlen($password) < $this->preferences->pref_password_length) {
            $this->errors[] = str_replace(
                ['%lenght', '%count'],
                [$this->preferences->pref_password_length, mb_strlen($password)],
                _T('Too short (%lenght characters minimum, %count found)')
            );
        }

        $this->strength = $this->calculateStrength($password);
        if ($this->strength < $this->preferences->pref_password_strength) {
            $this->errors = array_merge($this->errors, $this->strength_errors);
        }

        if ($this->preferences->pref_password_strength > Preferences::PWD_NONE) {
            //check also against personal information
            if (in_array(mb_strtolower($password), $this->personal_infos)) {
                $this->errors[] = _T('Do not use any of your personal information as password!');
            }
        }

        return (count($this->errors) === 0);
    }

    /**
     * Is password blacklisted?
     *
     * @param string $password Password to check
     *
     * @return boolean
     */
    public function isBlacklisted($password)
    {
        if (!$this->preferences->pref_password_blacklist) {
            return false;
        }

        return in_array(
            mb_strtolower($password),
            $this->getBlacklistedPasswords()
        );
    }

    /**
     * Calculate pasword strength
     *
     * @param string $password Password to check
     *
     * @return integer
     */
    public function calculateStrength($password)
    {
        $strength = 0;

        if (preg_match('/\p{L}/u', $password)) {
            ++$strength;

            if (!preg_match('/\p{Ll}/u', $password)) {
                $this->strength_errors[] = _T('Does not contains lowercase letters');
            } elseif (preg_match('/\p{Lu}/u', $password)) {
                ++$strength;
            } else {
                $this->strength_errors[] = _T('Does not contains uppercase letters');
            }
        } else {
            $this->strength_errors[] = _T('Does not contains letters');
        }

        if (preg_match('/\p{N}/u', $password)) {
            ++$strength;
        } else {
            $this->strength_errors[] = _T('Does not contains numbers');
        }

        if (preg_match('/[^\p{L}\p{N}]/u', $password)) {
            ++$strength;
        } else {
            $this->strength_errors[] = _T('Does not contains special characters');
        }

        return $strength;
    }

    /**
     * Get current strength
     *
     * @return integer
     */
    public function getStrenght()
    {
        return $this->strength;
    }

    /**
     * Get errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get strength errors
     *
     * @return array
     */
    public function getStrenghtErrors()
    {
        return $this->strength_errors;
    }

    /**
     * Build password blacklist
     *
     * @return array
     */
    public function getBlacklistedPasswords()
    {
        $blacklist = [];
        $file = GALETTE_DATA_PATH . '/blacklist.txt';

        if (!file_exists($file)) {
            //copy default provided list
            $worst500 = explode(PHP_EOL, file_get_contents(GALETTE_ROOT . 'includes/fields_defs/pass_blacklist'));
            file_put_contents($file, implode(PHP_EOL, $worst500));
        }

        $blacklist = explode(PHP_EOL, file_get_contents($file));
        $blacklist[] = 'galette'; //that one should always be blacklisted... :)

        return $blacklist;
    }

    /**
     * Add personal information to check against
     *
     * @param array $infos Personal information
     *
     * @return array
     */
    public function addPersonalInformation(array $infos)
    {
        $this->personal_infos = array_merge(
            $this->personal_infos,
            array_map('mb_strtolower', array_values($infos))
        );
        return $this->personal_infos;
    }

    /**
     * Set member and calculate personal information to blacklist
     *
     * @param Adherent $adh Adherent instance
     *
     * @return Password
     */
    public function setAdherent(Adherent $adh)
    {
        $infos = [
            $adh->name,
            $adh->surname,
            $adh->birthdate ?? '', //locale formatted
            $adh->rbirthdate, //raw
            $adh->nickname,
            $adh->town,
            $adh->login,
            $adh->email
        ];

        //handle date formats
        $bdate = \DateTime::createFromFormat('Y-m-d', $adh->rbirthdate);
        if ($bdate !== false) {
            $infos[] = $bdate->format('Y-m-d'); //standard format
            //TRANS: see https://www.php.net/manual/datetime.format.php
            $infos[] = $bdate->format(__('Y-m-d')); //localized format
            $infos[] = $bdate->format('Ymd');
            $infos[] = $bdate->format('dmY');
            $infos[] = $bdate->format('Ydm');
        }

        //some possible combinations
        foreach ([$adh->surname, $adh->nickname, $adh->login] as $surname) {
            if ($surname === null) {
                continue;
            }
            $infos[] = mb_substr($surname, 0, 1) . $adh->name;
            $infos[] = $adh->name . mb_substr($surname, 0, 1);
            $infos[] = $surname . $adh->name;
            $infos[] = $adh->name . $surname;

            //compound surnames
            $parts = preg_split('/[- _]/', $surname);
            if (count($parts) > 1) {
                $letters = '';
                foreach ($parts as $part) {
                    $letters .= mb_substr($part, 0, 1);
                }
                $infos[] = $letters . $adh->name;
                $infos[] = $adh->name . $letters;
            }
        }

        $this->addPersonalInformation($infos);

        return $this;
    }
}
