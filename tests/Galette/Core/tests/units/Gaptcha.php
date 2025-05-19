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

namespace Galette\Core\test\units;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Galette captcha tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Gaptcha extends TestCase
{
    /**
     * Test getRawData
     *
     * @return void
     */
    public function testCheck(): void
    {
        $gaptcha = new \Galette\Core\Gaptcha(new \Galette\Core\I18n());
        $rgaptcha = new ReflectionClass($gaptcha);

        $op = $rgaptcha->getProperty('current_op');
        $op->setAccessible(true);
        $op->setValue($gaptcha, \Galette\Core\Gaptcha::OP_ADD);

        $left = $rgaptcha->getProperty('current_left');
        $left->setAccessible(true);
        $left->setValue($gaptcha, 3);

        $right = $rgaptcha->getProperty('current_right');
        $right->setAccessible(true);
        $right->setValue($gaptcha, 5);

        $current = $rgaptcha->getProperty('gaptcha');
        $current->setAccessible(true);
        $current->setValue($gaptcha, 8);

        $question = $gaptcha->generateQuestion();
        $this->assertStringContainsString('three', $question);
        $this->assertStringContainsString('five', $question);

        $this->assertTrue($gaptcha->check(8));

        //localized
        $gaptcha = new \Galette\Core\Gaptcha(new \Galette\Core\I18n('fr_FR'));
        $rgaptcha = new ReflectionClass($gaptcha);

        $op = $rgaptcha->getProperty('current_op');
        $op->setAccessible(true);
        $op->setValue($gaptcha, \Galette\Core\Gaptcha::OP_ADD);

        $left = $rgaptcha->getProperty('current_left');
        $left->setAccessible(true);
        $left->setValue($gaptcha, 3);

        $right = $rgaptcha->getProperty('current_right');
        $right->setAccessible(true);
        $right->setValue($gaptcha, 5);

        $current = $rgaptcha->getProperty('gaptcha');
        $current->setAccessible(true);
        $current->setValue($gaptcha, 8);

        $question = $gaptcha->generateQuestion();
        $this->assertStringContainsString('trois', $question);
        $this->assertStringContainsString('cinq', $question);

        $this->assertTrue($gaptcha->check(8));

        //sub
        $gaptcha = new \Galette\Core\Gaptcha(new \Galette\Core\I18n());
        $rgaptcha = new ReflectionClass($gaptcha);

        $op = $rgaptcha->getProperty('current_op');
        $op->setAccessible(true);
        $op->setValue($gaptcha, \Galette\Core\Gaptcha::OP_SUB);

        $left = $rgaptcha->getProperty('current_left');
        $left->setAccessible(true);
        $left->setValue($gaptcha, 5);

        $right = $rgaptcha->getProperty('current_right');
        $right->setAccessible(true);
        $right->setValue($gaptcha, 3);

        $current = $rgaptcha->getProperty('gaptcha');
        $current->setAccessible(true);
        $current->setValue($gaptcha, 2);

        $question = $gaptcha->generateQuestion();
        $this->assertStringContainsString('three', $question);
        $this->assertStringContainsString('five', $question);

        $this->assertTrue($gaptcha->check(2));
    }
}
