<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Entity;

use Galette\Tests\GaletteTestCase;

/**
 * HasEvent tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class HasEvent extends GaletteTestCase
{
    protected int $seed = 20240223092214;

    /**
     * Test HasEvent capacities
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
