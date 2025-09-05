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

use NumberFormatter;

/**
 * Password image (captcha) for galette.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Gaptcha
{
    public const OP_ADD = 1;
    public const OP_SUB = 2;

    private int $max = 12;
    private int $min = 0;
    private readonly int $current_left;
    private readonly int $current_right;
    private readonly int $current_op;
    private int $gaptcha;

    /**
     * Default constructor
     *
     * @param I18n $i18n I18n instance
     */
    public function __construct(private readonly I18n $i18n)
    {
        $this->current_left = random_int($this->min, $this->max);
        $this->current_right = random_int($this->min, $this->max);
        $this->current_op = random_int(1, 2);
        switch ($this->current_op) {
            case self::OP_ADD:
                $this->gaptcha = $this->current_left + $this->current_right;
                break;
            case self::OP_SUB:
                $this->gaptcha = $this->current_left - $this->current_right;
                break;
        }
    }

    /**
     * Get question phrase
     *
     * @return string
     */
    public function getQuestion(): string
    {
        $add_questions = [
            _T('How much is %1$s plus %2$s?'),
            _T('How much is %1$s added to %2$s?'),
            _T('I have %1$s Galettes, a friend give me %2$s more. How many Galettes do I have?')
        ];
        $sub_questions = [
            _T('How much is %1$s minus %2$s?'),
            _T('How much is %1$s on which we retire %2$s?'),
            _T('How much is %2$s retired to %1$s?'),
            _T('I have %1$s Galettes, I give %2$s of them. How many Galettes do I have?')
        ];

        $questions = ($this->current_op === self::OP_ADD) ? $add_questions : $sub_questions;
        return $questions[random_int(0, (count($questions) - 1))];
    }


    /**
     * Generate captcha question to display
     *
     * @return string
     */
    public function generateQuestion(): string
    {
        $formatter = new NumberFormatter($this->i18n->getID(), NumberFormatter::SPELLOUT);
        return sprintf(
            $this->getQuestion(),
            $formatter->format($this->current_left),
            $formatter->format($this->current_right)
        );
    }

    /**
     * Checks captcha validity
     *
     * @param integer $gaptcha User entry
     *
     * @return boolean
     */
    public function check(int $gaptcha): bool
    {
        return $gaptcha === $this->gaptcha;
    }
}
