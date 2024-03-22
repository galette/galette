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

namespace Galette\Core\test\units;

use Galette\GaletteTestCase;

/**
 * Password tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Links extends GaletteTestCase
{
    //private $pass = null;
    protected int $seed = 95842355;
    private \Galette\Core\Links $links;
    private array $ids = [];
    protected array $excluded_after_methods = ['testDuplicateLinkTarget'];

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->links = new \Galette\Core\Links($this->zdb, false);
        $this->contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);

        $this->adh = new \Galette\Entity\Adherent($this->zdb);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
    }

    /**
     * Cleanup after testeach test method
     *
     * @return void
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where(['info_cotis' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Core\Links::TABLE);
        $this->zdb->execute($delete);

        parent::tearDown();
    }

    /**
     * Test new Link generation
     *
     * @return void
     */
    public function testGenerateNewLink()
    {
        $links = $this->links;
        $this->getMemberTwo();
        $id = $this->adh->id;

        $res = $links->generateNewLink(
            \Galette\Core\Links::TARGET_MEMBERCARD,
            $id
        );

        $this->assertNotEmpty($res);

        $select = $this->zdb->select(\Galette\Core\Links::TABLE);
        $results = $this->zdb->execute($select);
        $this->assertSame(1, $results->count());

        $this->assertSame(
            $links->isHashValid($res, $this->adh->getEmail()),
            [
                \Galette\Core\Links::TARGET_MEMBERCARD,
                $id
            ]
        );

        $this->assertFalse($links->isHashValid($res, 'any@mail.com'));
        $this->assertFalse($links->isHashValid(base64_encode('sthingthatisnotahash'), $this->adh->getEmail()));

        $this->createContribution();
        $cid = $this->contrib->id;
        $res = $links->generateNewLink(
            \Galette\Core\Links::TARGET_INVOICE,
            $cid
        );

        $this->assertNotEmpty($res);
        $this->assertSame(
            $links->isHashValid($res, $this->adh->getEmail()),
            [
                \Galette\Core\Links::TARGET_INVOICE,
                $cid
            ]
        );
    }

    /**
     * Test expired is invalid
     *
     * @return void
     */
    public function testExpiredValidate()
    {
        $links = $this->links;
        $this->getMemberTwo();
        $id = $this->adh->id;

        $res = $links->generateNewLink(
            \Galette\Core\Links::TARGET_MEMBERCARD,
            $id
        );

        $this->assertNotEmpty($res);

        $this->assertSame(
            $links->isHashValid($res, $this->adh->getEmail()),
            [
                \Galette\Core\Links::TARGET_MEMBERCARD,
                $id
            ]
        );

        $select = $this->zdb->select(\Galette\Core\Links::TABLE);
        $results = $this->zdb->execute($select);
        $this->assertSame(1, $results->count());

        $update = $this->zdb->update(\Galette\Core\Links::TABLE);
        $old_date = new \DateTime();
        $old_date->sub(new \DateInterval('P2W'));
        $update
            ->set(['creation_date' => $old_date->format('Y-m-d')])
            ->where(['hash' => base64_decode($res)]);
        $this->zdb->execute($update);

        $this->assertFalse($links->isHashValid($res, $this->adh->getEmail()));
    }

    /**
     * Test cleanExpired
     *
     * @return void
     */
    public function testCleanExpired()
    {
        $date = new \DateTime();
        $date->sub(new \DateInterval('PT48H'));

        $insert = $this->zdb->insert(\Galette\Core\Links::TABLE);
        $insert->values(
            [
                'hash'          => 'Not expired link',
                'creation_date' => $date->format('Y-m-d'),
                'target'        => \Galette\Core\Links::TARGET_MEMBERCARD,
                'id'            => 1
            ]
        );
        $this->zdb->execute($insert);

        $date->sub(new \DateInterval('P1W'));
        $insert = $this->zdb->insert(\Galette\Core\Links::TABLE);
        $insert->values(
            [
                'hash'          => 'Expired link',
                'creation_date' => $date->format('Y-m-d'),
                'target'        => \Galette\Core\Links::TARGET_MEMBERCARD,
                'id'            => 2
            ]
        );
        $this->zdb->execute($insert);

        $select = $this->zdb->select(\Galette\Core\Links::TABLE);
        $results = $this->zdb->execute($select);
        $this->assertSame(2, $results->count());

        $links = new \Galette\Core\Links($this->zdb, true);

        $results = $this->zdb->execute($select);
        $result = $results->current();
        $this->assertSame(1, $results->count());
        $this->assertSame('Not expired link', $result['hash']);
    }

    /**
     * Test duplicate target
     *
     * @return void
     */
    public function testDuplicateLinkTarget()
    {
        $date = new \DateTime();
        $date->sub(new \DateInterval('PT48H'));

        $insert = $this->zdb->insert(\Galette\Core\Links::TABLE);
        $insert->values(
            [
                'hash'          => 'Unique link',
                'creation_date' => $date->format('Y-m-d'),
                'target'        => \Galette\Core\Links::TARGET_MEMBERCARD,
                'id'            => 1
            ]
        );
        $this->zdb->execute($insert);

        $date->sub(new \DateInterval('PT1H'));

        $insert = $this->zdb->insert(\Galette\Core\Links::TABLE);
        $insert->values(
            [
                'hash'          => 'Unique link (but we did not know before)',
                'creation_date' => $date->format('Y-m-d'),
                'target'        => \Galette\Core\Links::TARGET_MEMBERCARD,
                'id'            => 1
            ]
        );

        $this->expectExceptionMessage('Duplicate entry');
        $this->zdb->execute($insert);
    }

    /**
     * Create test contribution in database
     *
     * @return void
     */
    protected function createContribution()
    {
        $now = new \DateTime(); // 2020-11-07
        $begin_date = clone $now;
        $begin_date->sub(new \DateInterval('P1Y')); // 2019-11-07
        $begin_date->sub(new \DateInterval('P6M')); // 2019-05-07
        $begin_date->add(new \DateInterval('P13D')); // 2019-05-20

        $due_date = clone $begin_date;
        $due_date->sub(new \DateInterval('P1D'));
        $due_date->add(new \DateInterval('P1Y'));

        $data = [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 3,
            'montant_cotis' => 111,
            'type_paiement_cotis' => 6,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $begin_date->format('Y-m-d'),
            'date_debut_cotis' => $begin_date->format('Y-m-d'),
            'date_fin_cotis' => $due_date->format('Y-m-d'),
        ];
        $this->createContrib($data);
        $this->checkContribExpected();
    }

    /**
     * Check contributions expecteds
     *
     * @param Contribution $contrib       Contribution instance, if any
     * @param array        $new_expecteds Changes on expected values
     *
     * @return void
     */
    protected function checkContribExpected($contrib = null, $new_expecteds = [])
    {
        if ($contrib === null) {
            $contrib = $this->contrib;
        }

        $begin_date = $contrib->raw_begin_date;

        $due_date = clone $begin_date;
        $due_date->sub(new \DateInterval('P1D'));
        $due_date->add(new \DateInterval('P1Y'));

        $this->assertInstanceOf('DateTime', $contrib->raw_date);
        $this->assertInstanceOf('DateTime', $contrib->raw_begin_date);
        $this->assertInstanceOf('DateTime', $contrib->raw_end_date);

        $expecteds = [
            'id_adh' => "{$this->adh->id}",
            'id_type_cotis' => 3,
            'montant_cotis' => '111',
            'type_paiement_cotis' => '6',
            'info_cotis' => 'FAKER' . $this->seed,
            'date_fin_cotis' => $due_date->format('Y-m-d')
        ];
        $expecteds = array_merge($expecteds, $new_expecteds);

        $this->assertSame($expecteds['date_fin_cotis'], $contrib->raw_end_date->format('Y-m-d'));

        foreach ($expecteds as $key => $value) {
            $property = $this->contrib->fields[$key]['propname'];
            switch ($key) {
                case \Galette\Entity\ContributionsTypes::PK:
                    $ct = $this->contrib->type;
                    if ($ct instanceof \Galette\Entity\ContributionsTypes) {
                        $this->assertSame($value, (int)$ct->id);
                    } else {
                        $this->assertSame($value, $ct);
                    }
                    break;
                default:
                    $this->assertEquals($contrib->$property, $value, $property);
                    break;
            }
        }

        //load member from db
        $this->adh = new \Galette\Entity\Adherent($this->zdb, $this->adh->id);
        //member is now up-to-date
        $this->assertSame('active-account cotis-late', $this->adh->getRowClass());
        $this->assertSame($this->contrib->end_date, $this->adh->due_date);
        $this->assertFalse($this->adh->isUp2Date());
    }
}
