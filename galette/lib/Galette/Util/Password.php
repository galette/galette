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

namespace Galette\Util;

use Galette\Core\Preferences;
use Galette\Entity\Adherent;

/**
 * Password checks
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Password
{
    /**
     * Errors
     *
     * @var array<int, string>
     */
    protected array $errors = [];
    /**
     * Strength errors
     *
     * @var array<int, string>
     */
    protected array $strength_errors = [];
    protected ?int $strength = null;
    protected bool $blacklisted = false;
    /**
     * Personal information to check against
     *
     * @var array<int, string>
     */
    protected array $personal_infos = [];

    /**
     * Default constructor
     *
     * @param Preferences $preferences Preferences instance
     */
    public function __construct(protected Preferences $preferences)
    {
    }

    /**
     * Does password suits requirements?
     *
     * @param string $password Password to test
     *
     * @return boolean
     */
    public function isValid(string $password): bool
    {
        $this->errors = []; //reset

        if ($this->isBlacklisted($password)) {
            $this->errors[] = _T('Password is blacklisted!');
            //no need here to check length/strength
            $this->strength = 0;
            return false;
        }

        if (mb_strlen($password) < $this->preferences->pref_password_length) {
            $this->errors[] = sprintf(
                _T('Too short (%1$s characters minimum, %2$s found)'),
                (string)$this->preferences->pref_password_length,
                (string)mb_strlen($password)
            );
        }

        $this->strength = $this->calculateStrength($password);
        if ($this->strength < $this->preferences->pref_password_strength) {
            $this->errors = array_merge($this->errors, $this->strength_errors);
        }

        //check also against personal information
        if ($this->preferences->pref_password_strength > Preferences::PWD_NONE && in_array(mb_strtolower($password), $this->personal_infos)) {
            $this->errors[] = _T('Do not use any of your personal information as password!');
        }

        return count($this->errors) === 0;
    }

    /**
     * Is password blacklisted?
     *
     * @param string $password Password to check
     *
     * @return boolean
     */
    public function isBlacklisted(string $password): bool
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
     * Calculate password strength
     *
     * @param string $password Password to check
     *
     * @return integer
     */
    public function calculateStrength(string $password): int
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
    public function getStrenght(): int
    {
        return $this->strength;
    }

    /**
     * Get errors
     *
     * @return array<int, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get strength errors
     *
     * @return array<int, string>
     */
    public function getStrenghtErrors(): array
    {
        return $this->strength_errors;
    }

    /**
     * Build password blacklist
     *
     * @return array<int, string>
     */
    public function getBlacklistedPasswords(): array
    {
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
     * @param array<int, string> $infos Personal information
     *
     * @return array<int, string>
     */
    public function addPersonalInformation(array $infos): array
    {
        $this->personal_infos = array_merge(
            $this->personal_infos,
            array_map(
                function (?string $info) {
                    if ($info !== null) {
                        $info = mb_strtolower($info);
                    }
                    return $info;
                },
                array_values($infos)
            )
        );
        return $this->personal_infos;
    }

    /**
     * Set member and calculate personal information to blacklist
     *
     * @param Adherent $adh Adherent instance
     *
     * @return self
     */
    public function setAdherent(Adherent $adh): self
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
        if ($adh->rbirthdate !== null) {
            $bdate = \DateTime::createFromFormat('Y-m-d', $adh->rbirthdate);
            if ($bdate !== false) {
                $infos[] = $bdate->format('Y-m-d'); //standard format
                //TRANS: see https://www.php.net/manual/datetime.format.php
                $infos[] = $bdate->format(__('Y-m-d')); //localized format
                $infos[] = $bdate->format('Ymd');
                $infos[] = $bdate->format('dmY');
                $infos[] = $bdate->format('Ydm');
            }
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
