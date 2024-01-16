<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contributions repository tests
 *
 * PHP version 5
 *
 * Copyright © 2023 The Galette Team
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
 *
 * @category  Repository
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     2023-03-27
 */

namespace Galette\Repository\test\units;

use Galette\GaletteTestCase;

/**
 * Contributions repository tests
 *
 * @category  Repository
 * @name      Contributions
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     2023-03-27
 */
class Contributions extends GaletteTestCase
{
    protected int $seed = 20230327215258;

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $this->zdb = new \Galette\Core\Db();
        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where(['info_cotis' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $delete->where('parent_id IS NOT NULL');
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);
    }

    /**
     * Test getList
     *
     * @return void
     */
    public function testGetList()
    {
        $this->logSuperAdmin();
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login);

        $list = $contributions->getList(true, null, true);
        $this->assertIsArray($list);
        $this->assertCount(0, $list);
        $this->assertSame(0, $contributions->getCount());
        $this->assertSame(0.0, $contributions->getSum());
        $member2 = $this->getMemberTwo();
        $this->getMemberOne();
        $this->createContribution();

        $list = $contributions->getList(true);
        $this->assertIsArray($list);
        $this->assertCount(1, $list);
        $this->assertSame(92.0, $contributions->getSum());

        //filters
        $filters = new \Galette\Filters\ContributionsList();
        $filters->filtre_cotis_adh = $member2->id;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(0, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->filtre_cotis_adh = $this->adh->id;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->max_amount = 90;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(0, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->max_amount = 95;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->start_date_filter = $this->contrib->begin_date;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->start_date_filter = $this->contrib->end_date;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(0, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->date_field = \Galette\Filters\ContributionsList::DATE_END;
        $filters->end_date_filter = $this->contrib->end_date;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->date_field = \Galette\Filters\ContributionsList::DATE_RECORD;
        $filters->start_date_filter = $this->contrib->date;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->date_field = \Galette\Filters\ContributionsList::DATE_RECORD;
        $filters->start_date_filter = $this->contrib->end_date;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(0, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->payment_type_filter = $this->contrib->payment_type;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        //member with a contribution
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(false);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);
        $login->setId($this->adh->id);
        $contributions = new \Galette\Repository\Contributions($this->zdb, $login);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        $filters = new \Galette\Filters\ContributionsList();
        $filters->date_field = \Galette\Filters\ContributionsList::DATE_END;
        $filters->filtre_cotis_children = true;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        //member does not have any contribution
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(false);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);
        $login->setId($member2->id);
        $contributions = new \Galette\Repository\Contributions($this->zdb, $login);
        $list = $contributions->getList(true);
        $this->assertCount(0, $list);

        //cannot load another simple member's contribution
        $filters = new \Galette\Filters\ContributionsList();
        $filters->filtre_cotis_adh = $this->adh->id;
        $contributions = new \Galette\Repository\Contributions($this->zdb, $login, $filters);
        $list = $contributions->getList(true);
        $this->assertCount(0, $list);
    }

    /**
     * Test getArrayList
     *
     * @return void
     */
    public function testGetArrayList()
    {
        $this->logSuperAdmin();
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login);

        $this->getMemberOne();
        $this->createContribution();

        $list = $contributions->getArrayList([$this->contrib->id], true);

        $this->assertIsArray($list);
        $this->assertCount(1, $list);
        $contrib = array_pop($list);
        $this->assertTrue($contrib instanceof \Galette\Entity\Contribution);

        $list = $contributions->getArrayList([$this->contrib->id], false);
        $this->assertIsArray($list);
        $this->assertCount(1, $list);
        $contrib = array_pop($list);
        $this->assertFalse($contrib instanceof \Galette\Entity\Contribution);
    }

    /**
     * Test remove
     *
     * @return void
     */
    public function testRemove()
    {
        $this->logSuperAdmin();
        $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login);

        $this->getMemberOne();
        $this->createContribution();

        $list = $contributions->getList(true);
        $this->assertCount(1, $list);

        $this->assertTrue($contributions->remove($this->contrib->id, $this->history));

        $list = $contributions->getList(true);
        $this->assertCount(0, $list);
    }
}
