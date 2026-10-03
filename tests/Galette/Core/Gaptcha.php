<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\BaseGaletteTestCase;
use ReflectionClass;

/**
 * Galette captcha tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Gaptcha extends BaseGaletteTestCase
{
    /**
     * Test getRawData
     */
    public function testCheck(): void
    {
        $gaptcha = new \Galette\Core\Gaptcha(new \Galette\Core\I18n());
        $rgaptcha = new ReflectionClass($gaptcha);
        //do not call constructor so readonly properties are not set
        $gaptcha = $rgaptcha->newInstanceWithoutConstructor();
        $i18n = $rgaptcha->getProperty('i18n');
        $i18n->setValue($gaptcha, new \Galette\Core\I18n());

        $op = $rgaptcha->getProperty('current_op');
        $op->setValue($gaptcha, \Galette\Core\Gaptcha::OP_ADD);

        $left = $rgaptcha->getProperty('current_left');
        $left->setValue($gaptcha, 3);

        $right = $rgaptcha->getProperty('current_right');
        $right->setValue($gaptcha, 5);

        $current = $rgaptcha->getProperty('gaptcha');
        $current->setValue($gaptcha, 8);

        $question = $gaptcha->generateQuestion();
        $this->assertStringContainsString('three', $question);
        $this->assertStringContainsString('five', $question);

        $this->assertTrue($gaptcha->check(8));

        //localized
        $gaptcha = new \Galette\Core\Gaptcha(new \Galette\Core\I18n('fr_FR'));
        $rgaptcha = new ReflectionClass($gaptcha);
        //do not call constructor so readonly properties are not set
        $gaptcha = $rgaptcha->newInstanceWithoutConstructor();
        $i18n = $rgaptcha->getProperty('i18n');
        $i18n->setValue($gaptcha, new \Galette\Core\I18n('fr_FR'));

        $op = $rgaptcha->getProperty('current_op');
        $op->setValue($gaptcha, \Galette\Core\Gaptcha::OP_ADD);

        $left = $rgaptcha->getProperty('current_left');
        $left->setValue($gaptcha, 3);

        $right = $rgaptcha->getProperty('current_right');
        $right->setValue($gaptcha, 5);

        $current = $rgaptcha->getProperty('gaptcha');
        $current->setValue($gaptcha, 8);

        $question = $gaptcha->generateQuestion();
        $this->assertStringContainsString('trois', $question);
        $this->assertStringContainsString('cinq', $question);

        $this->assertTrue($gaptcha->check(8));

        //sub
        $gaptcha = new \Galette\Core\Gaptcha(new \Galette\Core\I18n());
        $rgaptcha = new ReflectionClass($gaptcha);
        //do not call constructor so readonly properties are not set
        $gaptcha = $rgaptcha->newInstanceWithoutConstructor();
        $i18n = $rgaptcha->getProperty('i18n');
        $i18n->setValue($gaptcha, new \Galette\Core\I18n());

        $op = $rgaptcha->getProperty('current_op');
        $op->setValue($gaptcha, \Galette\Core\Gaptcha::OP_SUB);

        $left = $rgaptcha->getProperty('current_left');
        $left->setValue($gaptcha, 5);

        $right = $rgaptcha->getProperty('current_right');
        $right->setValue($gaptcha, 3);

        $current = $rgaptcha->getProperty('gaptcha');
        $current->setValue($gaptcha, 2);

        $question = $gaptcha->generateQuestion();
        $this->assertStringContainsString('three', $question);
        $this->assertStringContainsString('five', $question);

        $this->assertTrue($gaptcha->check(2));
    }
}
