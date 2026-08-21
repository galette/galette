<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Safe\DateTime;
use Galette\Tests\GaletteTestCase;

use function Safe\base64_decode;

/**
 * Password tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Links extends GaletteTestCase
{
    protected int $seed = 95842355;
    private \Galette\Core\Links $links;

    /**
     * Set up tests
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
     * Test new Link generation
     */
    public function testGenerateNewLink(): void
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
     */
    public function testExpiredValidate(): void
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
        $old_date = new DateTime();
        $old_date->sub(new \DateInterval('P2W'));
        $update
            ->set(['creation_date' => $old_date->format('Y-m-d')])
            ->where(['hash' => base64_decode($res)]);
        $this->zdb->execute($update);

        $this->assertFalse($links->isHashValid($res, $this->adh->getEmail()));
    }

    /**
     * Test cleanExpired
     */
    public function testCleanExpired(): void
    {
        $date = new DateTime();
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

        new \Galette\Core\Links($this->zdb, true);

        $results = $this->zdb->execute($select);
        $result = $results->current();
        $this->assertSame(1, $results->count());
        $this->assertSame('Not expired link', $result['hash']);
    }

    /**
     * Test duplicate target
     */
    public function testDuplicateLinkTarget(): void
    {
        $date = new DateTime();
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

        $exception_trhown = false;
        try {
            $this->zdb->execute($insert);
        } catch (\Exception $e) {
            $exception_trhown = true;
            $this->expectLogEntry(
                \Analog\Analog::ERROR,
                //match the constraint name only: message is localised by the server
                $this->zdb->isPostgres()
                    ? 'galette_tmplinks_pkey'
                    : "Duplicate entry '1-1' for key"
            );
            $this->assertSame(
                'Duplicate entry',
                $e->getMessage()
            );
        }
        $this->assertTrue($exception_trhown, 'No exception has been thrown');
        $warning = new \ArrayObject([
            'Level' => 'Error',
            'Code'  => '1062',
            'Message' => "Duplicate entry '1-1' for key 'PRIMARY'"
        ]);
        $this->expected_mysql_warnings[] = $warning;
    }

    /**
     * Create test contribution in database
     */
    protected function createContribution(): void
    {
        $now = new DateTime(); // 2020-11-07
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
        $this->logSuperAdmin();
        $this->createContrib($data);
        $this->checkContribExpected();
        $this->login->logout();
    }

    /**
     * Check contributions expecteds
     *
     * @param ?\Galette\Entity\Contribution $contrib       Contribution instance, if any
     * @param array<string,mixed>           $new_expecteds Changes on expected values
     */
    protected function checkContribExpected(?\Galette\Entity\Contribution $contrib = null, array $new_expecteds = []): void
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
