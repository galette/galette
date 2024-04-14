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

namespace Galette\Entity\test\units;

use Galette\GaletteTestCase;

/**
 * Status tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Social extends GaletteTestCase
{
    protected int $seed = 25568744158;

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $this->deleteSocials();

        //drop dynamic translations
        $delete = $this->zdb->delete(\Galette\Core\L10n::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);
    }

    /**
     * Delete socials
     *
     * @return void
     */
    private function deleteSocials(): void
    {
        $delete = $this->zdb->delete(\Galette\Entity\Social::TABLE);
        $this->zdb->execute($delete);
    }

    /**
     * Test social object
     *
     * @return void
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
     *
     * @return void
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
     *
     * @return void
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
