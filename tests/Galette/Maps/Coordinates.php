<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Maps;

use Galette\Tests\GaletteTestCase;

/**
 * Members positions tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Coordinates extends GaletteTestCase
{
    protected int $seed = 20240517214956;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(\Galette\Maps\Coordinates::TABLE);
        $this->zdb->execute($delete);
        parent::tearDown();
    }

    /**
     * Test coordinates
     */
    public function testCoordinates(): void
    {
        $member = $this->getMemberOne();
        $coords = new \Galette\Maps\Coordinates($this->zdb, $this->login);
        $this->assertNull($coords->get($member->id));
        $this->assertSame([], $coords->listVisible());

        $this->logSuperAdmin();
        $this->assertNull($coords->get($member->id));
        $this->assertSame([], $coords->listVisible());

        //set coordinates for member one
        $coords->set($member->id, 50.362038, 3.472998);
        $this->assertSame(
            [
                'latitude' => '50.362038',
                'longitude' => '3.472998'
            ],
            $coords->get($member->id)
        );
        $this->assertEquals(
            [
                [
                    'id_adh' => $member->id,
                    'lat' => '50.362038',
                    'lng' => '3.472998',
                    'approximate' => false,
                    'name' => 'DURAND René',
                    'nickname' => 'ubertrand'
                ]
            ],
            $coords->listVisible()
        );

        //update coordinates for member one
        $coords->set($member->id, 51.362038, 3.572998);

        //remove coordinates for member one
        $coords->remove($member->id);
        $this->assertNull($coords->get($member->id));
    }

    /**
     * Storing the same position again is not a failure
     */
    public function testSetSamePosition(): void
    {
        $member = $this->getMemberOne();
        $coords = new \Galette\Maps\Coordinates($this->zdb, $this->login);
        $coords->set($member->id, 50.362038, 3.472998);
        $coords->set($member->id, 50.362038, 3.472998);
    }

    /**
     * Set member visibility fields
     *
     * @param int  $id_adh   Member ID
     * @param bool $active   Is member active
     * @param bool $public   Does member display its information
     * @param bool $uptodate Is member up to date
     */
    private function setVisibility(int $id_adh, bool $active, bool $public, bool $uptodate): void
    {
        $bool = fn(bool $value): \Laminas\Db\Sql\Expression => new \Laminas\Db\Sql\Expression($value ? 'true' : 'false');
        $update = $this->zdb->update(\Galette\Entity\Adherent::TABLE);
        $update->set([
            'activite_adh' => $bool($active),
            'bool_display_info' => $bool($public),
            'bool_exempt_adh' => $bool(false),
            'date_echeance' => $uptodate ? date('Y-m-d', strtotime('+1 month')) : date('Y-m-d', strtotime('-1 month')),
        ])->where([\Galette\Entity\Adherent::PK => $id_adh]);
        $this->zdb->execute($update);
    }

    /**
     * Get IDs of listed members
     *
     * @return array<int>
     */
    private function listedIds(): array
    {
        $ids = array_column((new \Galette\Maps\Coordinates($this->zdb, $this->login))->listVisible(), 'id_adh');
        sort($ids);
        return $ids;
    }

    /**
     * Visitors see public up-to-date profiles, members also see their own one, if active
     */
    public function testListVisibility(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $coords = new \Galette\Maps\Coordinates($this->zdb, $this->login);
        $coords->set($member_one->id, 50.36, 3.47);
        $coords->set($member_two->id, 48.85, 2.35);

        $this->setVisibility($member_one->id, active: true, public: false, uptodate: false);
        $this->setVisibility($member_two->id, active: true, public: true, uptodate: true);
        $this->assertSame([$member_two->id], $this->listedIds());

        $this->assertTrue($this->login->login($this->dataAdherentOne()['login_adh'], $this->dataAdherentOne()['mdp_adh']));
        $expected = [$member_one->id, $member_two->id];
        sort($expected);
        $this->assertSame($expected, $this->listedIds());

        //an inactive member is never listed, not even to itself
        $this->setVisibility($member_one->id, active: false, public: false, uptodate: false);
        $this->assertSame([$member_two->id], $this->listedIds());

        $this->setVisibility($member_two->id, active: false, public: true, uptodate: true);
        $this->assertSame([], $this->listedIds());
        $this->login->logout();
    }

    /**
     * Storing coordinates of a member that does not exist raises
     */
    public function testSetMissingMember(): void
    {
        $coords = new \Galette\Maps\Coordinates($this->zdb, $this->login);
        //a failing query aborts the whole transaction on PostgreSQL
        $this->zdb->db->query('SAVEPOINT maps_set', \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        try {
            $coords->set(999999, 50.36, 3.47);
            $this->fail('An exception was expected');
        } catch (\Throwable $e) {
            $this->assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class, $e);
        } finally {
            $this->zdb->db->query('ROLLBACK TO SAVEPOINT maps_set', \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        }
        $this->assertNull($coords->get(999999));
        //logged by Db
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Query error: INSERT INTO');
    }

    /**
     * Sort positions by member
     *
     * @param array<int, array{string, string, bool}> $positions Positions
     *
     * @return array<int, array{string, string, bool}>
     */
    private function sorted(array $positions): array
    {
        ksort($positions);
        return $positions;
    }

    /**
     * Get listed positions, by member
     *
     * @param ?float $step Grid step
     *
     * @return array<int, array{string, string, bool}>
     */
    private function listedPositions(?float $step): array
    {
        $positions = [];
        foreach ((new \Galette\Maps\Coordinates($this->zdb, $this->login))->listVisible($step) as $row) {
            $positions[$row['id_adh']] = [$row['lat'], $row['lng'], $row['approximate']];
        }
        //list has no order
        ksort($positions);
        return $positions;
    }

    /**
     * Positions are snapped for everyone but staff, administrators and the member itself
     */
    public function testListPrecision(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $coords = new \Galette\Maps\Coordinates($this->zdb, $this->login);
        $coords->set($member_one->id, 50.362038, 3.472998);
        $coords->set($member_two->id, 48.856614, 2.352222);
        $this->setVisibility($member_one->id, active: true, public: true, uptodate: true);
        $this->setVisibility($member_two->id, active: true, public: true, uptodate: true);

        $exact_one = ['50.362038', '3.472998', false];
        $exact_two = ['48.856614', '2.352222', false];
        $snapped_one = ['50.36', '3.47', true];
        $snapped_two = ['48.86', '2.35', true];

        //visitor
        $this->assertSame($this->sorted([$member_one->id => $snapped_one, $member_two->id => $snapped_two]), $this->listedPositions(0.01));
        //exact positions required
        $this->assertSame($this->sorted([$member_one->id => $exact_one, $member_two->id => $exact_two]), $this->listedPositions(null));

        //member sees its own position as is
        $this->assertTrue($this->login->login($this->dataAdherentOne()['login_adh'], $this->dataAdherentOne()['mdp_adh']));
        $this->assertSame($this->sorted([$member_one->id => $exact_one, $member_two->id => $snapped_two]), $this->listedPositions(0.01));
        $this->login->logout();

        //staff members see exact positions
        $this->getStaffMember($member_one);
        $this->assertTrue($this->login->login($this->dataAdherentOne()['login_adh'], $this->dataAdherentOne()['mdp_adh']));
        $this->assertTrue($this->login->isStaff());
        $this->assertSame($this->sorted([$member_one->id => $exact_one, $member_two->id => $exact_two]), $this->listedPositions(0.01));
        $this->login->logout();

        $this->logSuperAdmin();
        $this->assertSame($this->sorted([$member_one->id => $exact_one, $member_two->id => $exact_two]), $this->listedPositions(0.01));
        $this->login->logout();
    }
}
