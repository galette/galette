<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Socials tests
 *
 * PHP version 5
 *
 * Copyright © 2021-2023 The Galette Team
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
 * @category  Entity
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2021-10-26
 */

namespace Galette\Entity\test\units;

use Galette\GaletteTestCase;

/**
 * Status tests
 *
 * @category  Entity
 * @name      Social
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2021-10-26
 */
class Social extends GaletteTestCase
{
    protected int $seed = 25568744158;

    /**
     * Tear down tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function afterTestMethod($method)
    {
        parent::afterTestMethod($method);

        $this->deleteSocials();

        //drop dynamic translations
        $delete = $this->zdb->delete(\Galette\Core\L10n::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $this->cleanHistory();
    }

    /**
     * Delete socials
     *
     * @return void
     */
    private function deleteSocials()
    {
        $delete = $this->zdb->delete(\Galette\Entity\Social::TABLE);
        $this->zdb->execute($delete);
    }

    /**
     * Test social object
     *
     * @return void
     */
    public function testObject()
    {
        $social = new \Galette\Entity\Social($this->zdb);

        //setters and getters
        $this->object($social->setType('mytype'))->isInstanceOf('\Galette\Entity\Social');
        $this->string($social->type)->isIdenticalTo('mytype');

        $this->object($social->setUrl('myurl'))->isInstanceOf('\Galette\Entity\Social');
        $this->string($social->url)->isIdenticalTo('myurl');

        //null as member id for Galette main preferences
        $this->object($social->setLinkedMember(null))->isInstanceOf('\Galette\Entity\Social');
        $this->variable($social->id_adh)->isNull();
        $this->variable($social->member)->isNull();

        $this->getMemberTwo();
        $this->object($social->setLinkedMember($this->adh->id))->isInstanceOf(\Galette\Entity\Social::class);
        $this->integer($social->id_adh)->isIdenticalTo($this->adh->id);
        $this->object($social->member)->isInstanceOf(\Galette\Entity\Adherent::class);
        $this->string($social->member->name)->isIdenticalTo($this->adh->name);
    }

    /**
     * Test socials "system" types
     *
     * @return void
     */
    public function testGetSystemTypes()
    {
        $social = new \Galette\Entity\Social($this->zdb);
        $this->array($social->getSystemTypes())->hasSize(9);
        $this->array($social->getSystemTypes())->isIdenticalTo($social->getSystemTypes(true));
        $this->array($social->getSystemTypes(false))->hasSize(9);

        $this->string($social->getSystemType(\Galette\Entity\Social::TWITTER))->isIdenticalTo('Twitter');
        $this->string($social->getSystemType(\Galette\Entity\Social::TWITTER, false))->isIdenticalTo('twitter');
    }

    /**
     * Test getListForMember
     *
     * @return void
     */
    public function testGetListForMember(): void
    {
        $this->array(\Galette\Entity\Social::getListForMember(null))->isEmpty();

        $this->getMemberTwo();
        $this->array(\Galette\Entity\Social::getListForMember($this->adh->id))->isEmpty();

        $social = new \Galette\Entity\Social($this->zdb);
        $this->boolean(
            $social
                ->setType(\Galette\Entity\Social::MASTODON)
                ->setUrl('mastodon URL')
                ->setLinkedMember($this->adh->id)
                ->store()
        )->isTrue();

        $socials = \Galette\Entity\Social::getListForMember($this->adh->id);
        $this->array($socials)->HasSize(1);
        $social = array_pop($socials);
        $this->string($social->type)->isIdenticalTo(\Galette\Entity\Social::MASTODON);
        $this->integer($social->id_adh)->isIdenticalTo($this->adh->id);
        $this->string($social->url)->isIdenticalTo('mastodon URL');

        $social = new \Galette\Entity\Social($this->zdb);
        $this->boolean(
            $social
                ->setType(\Galette\Entity\Social::MASTODON)
                ->setUrl('Galette mastodon URL')
                ->setLinkedMember(null)
                ->store()
        )->isTrue();

        $social = new \Galette\Entity\Social($this->zdb);
        $this->boolean(
            $social
                ->setType(\Galette\Entity\Social::JABBER)
                ->setUrl('Galette jabber')
                ->setLinkedMember(null)
                ->store()
        )->isTrue();

        $social = new \Galette\Entity\Social($this->zdb);
        $this->boolean(
            $social
                ->setType(\Galette\Entity\Social::MASTODON)
                ->setUrl('Another Galette mastodon URL')
                ->setLinkedMember(null)
                ->store()
        )->isTrue();

        $this->array(\Galette\Entity\Social::getListForMember(null))->hasSize(3);
        $this->array(\Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::JABBER))->hasSize(1);

        $this->boolean($social->remove())->isTrue();
        $this->array(\Galette\Entity\Social::getListForMember(null))->hasSize(2);
    }
}
