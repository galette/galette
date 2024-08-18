<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

/**
 * Text utilities
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Text
{
    /**
     * Slugify a string
     *
     * @param string $string String to slugify
     * @param string $prefix Prefix to use
     *
     * @return string
     */
    public static function slugify(string $string, string $prefix = ''): string
    {
        $string = $prefix . $string;
        $string = transliterator_transliterate("Any-Latin; Latin-ASCII; [^a-zA-Z0-9\.\ -_] Remove;", $string);
        $string = str_replace(' ', '-', mb_strtolower($string, 'UTF-8'));
        $string = preg_replace('~[^0-9a-z_\.]+~i', '-', $string);
        $string = trim($string, '-');
        if ($string == '') {
            throw new \RuntimeException(
                'Cannot create a slug from the given string ' . $string
            );
        }
        return $string;
    }

    /**
     * Get a random string
     *
     * @param integer $length of the random string
     *
     * @return string
     *
     * @see https://stackoverflow.com/questions/4356289/php-random-string-generator/31107425#31107425
     */
    public static function getRandomString(int $length): string
    {
        $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $str = '';
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }
        return $str;
    }
}
