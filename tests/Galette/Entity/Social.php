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
 * Status tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Social extends GaletteTestCase
{
    protected int $seed = 25568744158;

    /**
     * Test social object
     */
    public function testObject(): void
    {
        $social = new \Galette\Entity\Social($this->zdb);

        //setters and getters
        $this->assertInstanceOf(\Galette\Entity\Social::class, $social->setType('mytype'));
        $this->assertSame('mytype', $social->type);

        $this->assertInstanceOf(\Galette\Entity\Social::class, $social->setUrl('myurl'));
        $this->assertSame('myurl', $social->url);

        //null as member id for Galette main preferences
        $this->assertInstanceOf(\Galette\Entity\Social::class, $social->setLinkedMember(null));
        $this->assertNull($social->id_adh);
        $this->assertNull($social->member);

        $this->getMemberTwo();
        $this->assertInstanceOf(\Galette\Entity\Social::class, $social->setLinkedMember($this->adh->id));
        $this->assertSame($this->adh->id, $social->id_adh);
        $this->assertInstanceOf(\Galette\Entity\Adherent::class, $social->member);
        $this->assertSame($this->adh->name, $social->member->name);
    }

    /**
     * Test socials "system" types
     */
    public function testGetSystemTypes(): void
    {
        $social = new \Galette\Entity\Social($this->zdb);
        $this->assertCount(10, $social->getSystemTypes());
        $this->assertSame($social->getSystemTypes(true), $social->getSystemTypes());
        $this->assertCount(10, $social->getSystemTypes(false));

        $this->assertSame('Twitter', $social->getSystemType(\Galette\Entity\Social::TWITTER));
        $this->assertSame('twitter', $social->getSystemType(\Galette\Entity\Social::TWITTER, false));
    }

    /**
     * Test getListForMember
     */
    public function testGetListForMember(): void
    {
        $this->assertEmpty(\Galette\Entity\Social::getListForMember(null));

        $this->getMemberTwo();
        $this->assertEmpty(\Galette\Entity\Social::getListForMember($this->adh->id));

        $social = new \Galette\Entity\Social($this->zdb);
        $this->assertTrue(
            $social
                ->setType(\Galette\Entity\Social::MASTODON)
                ->setUrl('mastodon URL')
                ->setLinkedMember($this->adh->id)
                ->store()
        );

        $socials = \Galette\Entity\Social::getListForMember($this->adh->id);
        $this->assertCount(1, $socials);
        $social = array_pop($socials);
        $this->assertSame(\Galette\Entity\Social::MASTODON, $social->type);
        $this->assertSame($this->adh->id, $social->id_adh);
        $this->assertSame('mastodon URL', $social->url);

        //Same, from Adherent object
        $adh = new \Galette\Entity\Adherent($this->zdb, $this->adh->id, ['socials' => true]);
        $socials = $adh->socials;
        $this->assertCount(1, $socials);
        $social = array_pop($socials);
        $this->assertSame(\Galette\Entity\Social::MASTODON, $social->type);
        $this->assertSame($this->adh->id, $social->id_adh);
        $this->assertSame('mastodon URL', $social->url);

        $social = new \Galette\Entity\Social($this->zdb);
        $this->assertTrue(
            $social
                ->setType(\Galette\Entity\Social::MASTODON)
                ->setUrl('Galette mastodon URL')
                ->setLinkedMember(null)
                ->store()
        );

        $social = new \Galette\Entity\Social($this->zdb);
        $this->assertTrue(
            $social
                ->setType(\Galette\Entity\Social::JABBER)
                ->setUrl('Galette jabber')
                ->setLinkedMember(null)
                ->store()
        );

        $social = new \Galette\Entity\Social($this->zdb);
        $this->assertTrue(
            $social
                ->setType(\Galette\Entity\Social::MASTODON)
                ->setUrl('Another Galette mastodon URL')
                ->setLinkedMember(null)
                ->store()
        );

        $this->assertCount(3, \Galette\Entity\Social::getListForMember(null));
        $this->assertCount(1, \Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::JABBER));

        $this->assertTrue($social->remove());
        $this->assertCount(2, \Galette\Entity\Social::getListForMember(null));
    }
}
