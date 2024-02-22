<?php

/**
 * HasEvents tests
 *
 * PHP version 5
 *
 * Copyright © 2024 The Galette Team
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
 * @category  Features
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2024 The Galette Team
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 */

namespace Galette\Entity\test\units;

use Galette\GaletteTestCase;

/**
 * HasEvent tests class
 *
 * @category  Features
 * @name      HasEvents
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2024 The Galette Team
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 */
class HasEvent extends GaletteTestCase
{
    protected int $seed = 20240223092214;

    /**
     * Test HasEvent capacities
     *
     * @return void
     */
    public function testCapacities(): void
    {
        $this->adh = new \Galette\Entity\Adherent($this->zdb);

        //per default, add and edit events are active on contributions
        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);
        $this->assertTrue($contrib->areEventsEnabled());
        $this->assertTrue($contrib->hasAddEvent());
        $this->assertTrue($contrib->hasEditEvent());
        $this->assertFalse($contrib->hasDeleteEvent());
        $this->assertEquals('contribution.add', $contrib->getAddEventName());
        $this->assertEquals('contribution.edit', $contrib->getEditEventName());
        $this->assertNull($contrib->getDeleteEventName());

        //per default, add and edit events are active on members
        $this->assertTrue($this->adh->areEventsEnabled());
        $this->assertTrue($this->adh->hasAddEvent());
        $this->assertTrue($this->adh->hasEditEvent());
        $this->assertFalse($this->adh->hasDeleteEvent());
        $this->assertEquals('member.add', $this->adh->getAddEventName());
        $this->assertEquals('member.edit', $this->adh->getEditEventName());
        $this->assertNull($this->adh->getDeleteEventName());

        //disable add event
        $this->adh->withoutAddEvent();
        $this->assertFalse($this->adh->hasAddEvent());
        $this->assertNull($this->adh->getAddEventName());
        $this->assertTrue($this->adh->hasEditEvent());
        //enable add event
        $this->adh->withAddEvent();
        $this->assertTrue($this->adh->hasAddEvent());

        //disable edit event
        $this->adh->withoutEditEvent();
        $this->assertTrue($this->adh->hasAddEvent());
        $this->assertFalse($this->adh->hasEditEvent());
        $this->assertNull($this->adh->getEditEventName());
        //enable edit event
        $this->adh->withEditEvent();
        $this->assertTrue($this->adh->hasEditEvent());

        //enable delete event
        $this->adh->withDeleteEvent();
        $this->assertTrue($this->adh->hasDeleteEvent());
        $this->assertEquals('member.delete', $this->adh->getDeleteEventName());
        //disable delete event
        $this->adh->withoutDeleteEvent();
        $this->assertFalse($this->adh->hasDeleteEvent());

        // disable all events
        $this->adh->disableEvents();
        $this->assertFalse($this->adh->areEventsEnabled());
        $this->assertFalse($this->adh->hasAddEvent());
        $this->assertFalse($this->adh->hasEditEvent());
        $this->assertFalse($this->adh->hasDeleteEvent());
        $this->assertNull($this->adh->getAddEventName());
        $this->assertNull($this->adh->getEditEventName());
        $this->assertNull($this->adh->getDeleteEventName());

        //reactivate events
        $this->adh->activateEvents();
        $this->assertTrue($this->adh->areEventsEnabled());
        $this->assertTrue($this->adh->hasAddEvent());
        $this->assertTrue($this->adh->hasEditEvent());
        $this->assertFalse($this->adh->hasDeleteEvent());
    }
}
