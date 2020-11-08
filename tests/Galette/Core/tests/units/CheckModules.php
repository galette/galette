<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * CheckModules tests
 *
 * PHP version 5
 *
 * Copyright © 2016 The Galette Team
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
 * @category  Core
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2016 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2016-11-09
 */

namespace Galette\Core\test\units;

use atoum;

/**
 * CheckModules tests class
 *
 * @category  Core
 * @name      CheckModules
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2016 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2016-11-09
 */
class CheckModules extends atoum
{
    /**
     * Test modules, all should be ok
     *
     * @return void
     */
    public function testAllOK()
    {
        $checks = new \Galette\Core\CheckModules();
        $this->boolean($checks->isValid())->isTrue();
        $this->integer(count($checks->getGoods()))
            ->isLessThanOrEqualTo(10)
            ->isGreaterThanOrEqualTo(6);
        $this->array($checks->getMissings())
            ->isEmpty();
        $this->array($checks->getShoulds())
            ->isEmpty(2);
        $this->boolean($checks->isGood('mbstring'))
            ->isTrue();
    }

    /**
     * Test all extensions missing
     *
     * @return void
     */
    public function testAllKO()
    {
        $this->assert('All PHP extensions missing')
            ->given($checks = new \Galette\Core\CheckModules(false))
            ->if($this->function->extension_loaded = false)
            ->then
                ->if($checks->doCheck())
                    ->then
                        ->array($checks->getGoods())
                            ->hasSize(0)
                        ->array($checks->getShoulds())
                            ->hasSize(4)
                        ->array($checks->getMissings())
                            ->hasSize(6)
                        ->string($checks->toHtml())
                            ->notContains('icon-valid.png')
                            ->hasLength(1141);
    }

    /**
     * Test HTMl output
     *
     * @return void
     */
    public function testToHtml()
    {
        $checks = new \Galette\Core\CheckModules();
        $checks->doCheck();
        $html = $checks->toHtml();
        $this->string($html)
            ->notContains('icon-invalid.png')
            ->length->isGreaterThanOrEqualTo(908);
    }
}
