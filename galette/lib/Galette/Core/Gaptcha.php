<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
    public const int OP_ADD = 1;
    public const int OP_SUB = 2;

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
     * @param int $gaptcha User entry
     */
    public function check(int $gaptcha): bool
    {
        return $gaptcha === $this->gaptcha;
    }
}
