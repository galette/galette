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

namespace Galette\Core;

use NumberFormatter;
use Analog\Analog;
use Galette\Entity\Adherent;

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

    /** @var I18n */
    private I18n $i18n;
    /** @var integer */
    private int $current_left;
    /** @var integer */
    private int $current_right;
    /** @var integer */
    private int $current_op;
    /** @var integer */
    private int $gaptcha;

    /**
     * Default constructor
     *
     * @param I18n $i18n I18n instance
     */
    public function __construct(I18n $i18n)
    {
        $this->i18n = $i18n;
        $this->current_left = rand($this->min, $this->max);
        $this->current_right = rand($this->min, $this->max);
        $this->current_op = rand(1, 2);
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
     * Get questions phrase
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
        $question = $questions[rand(0, (count($questions) - 1))];
        return $question;
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
