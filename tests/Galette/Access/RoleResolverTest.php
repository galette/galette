<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Access\test\units;

use Galette\Access\AccessControl;
use Galette\Access\Permissions;
use Galette\Access\RoleResolver;
use Galette\Access\Roles;
use Galette\Tests\GaletteTestCase;

/**
 * RoleResolver tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RoleResolverTest extends GaletteTestCase
{
    protected int $seed = 20261005;
    private Roles $roles;
    private RoleResolver $resolver;
    private AccessControl $access;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();

        //system roles with defaults from preferences all off
        $saved = [];
        foreach (array_keys($this->preferences->getDefaults()) as $preference) {
            if (str_starts_with($preference, 'pref_bool_groupsmanagers_') || $preference === 'pref_bool_create_member') {
                $saved[$preference] = $this->preferences->$preference;
                $this->preferences->$preference = false;
            }
        }
        $this->roles = new Roles($this->zdb, new Permissions());
        $this->roles->installInit($this->preferences);
        foreach ($saved as $preference => $value) {
            $this->preferences->$preference = $value;
        }

        $this->resolver = new RoleResolver($this->zdb);
        $this->access = new AccessControl(new Permissions(), $this->resolver, $this->login);
    }

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        $this->cleanContributions();
        $this->cleanMembers();
        parent::tearDown();
    }

    /**
     * Create a role
     *
     * @param string        $name        Role name
     * @param array<string> $permissions Role permissions
     * @param ?int          $parent      Parent role identifier
     */
    private function createRole(string $name, array $permissions, ?int $parent = null): int
    {
        $insert = $this->zdb->insert(Roles::TABLE);
        $insert->values(['name' => $name, 'id_parent' => $parent]);
        $this->zdb->execute($insert);
        $id = $this->zdb->getLastGeneratedValue($this->roles);

        foreach ($permissions as $permission) {
            $insert = $this->zdb->insert(Roles::PERMISSIONS_TABLE);
            $insert->values([Roles::PK => $id, 'permission' => $permission]);
            $this->zdb->execute($insert);
        }
        $this->resolver->reset();
        return $id;
    }

    /**
     * Give a role to a member
     *
     * @param int  $member Member identifier
     * @param int  $role   Role identifier
     * @param ?int $group  Group identifier
     */
    private function giveRole(int $member, int $role, ?int $group = null): void
    {
        $insert = $this->zdb->insert(Roles::MEMBERS_TABLE);
        $insert->values(['id_adh' => $member, Roles::PK => $role, 'id_group' => $group]);
        $this->zdb->execute($insert);
        $this->resolver->reset();
    }

    /**
     * Log member one in
     */
    private function logMemberOne(): void
    {
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
    }

    /**
     * Test anonymous and super administrator
     */
    public function testAnonymousAndSuperAdmin(): void
    {
        $this->assertFalse($this->access->can('member:read'));
        $this->assertFalse($this->access->can('contribution:edit'));

        $this->logSuperAdmin();
        $this->assertTrue($this->access->can('member:read'));
        $this->assertTrue($this->access->can('contribution:edit'));
    }

    /**
     * Test a member gets owner permissions only
     */
    public function testMember(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $this->logMemberOne();

        $this->assertTrue($this->access->can('member:read', $member_one));
        $this->assertTrue($this->access->can('member:edit', $member_one));
        $this->assertFalse($this->access->can('member:read', $member_two));
        $this->assertFalse($this->access->can('member:read'));
        $this->assertFalse($this->access->can('member:create'));
        $this->assertFalse($this->access->can('contribution:create'));
    }

    /**
     * Test staff and administrators get staff permissions
     */
    public function testStaffAndAdmin(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();

        $staff = $this->getStaffMember($member_one);
        $this->logMemberOne();
        $this->assertTrue($this->login->isStaff());
        $this->assertTrue($this->access->can('member:delete', $member_two));
        $this->assertTrue($this->access->can('contribution:edit'));
        $this->login->logOut();
        $this->resetStaffStatus($staff, $member_two);

        $this->getAdminMember($member_one);
        $this->logMemberOne();
        $this->assertTrue($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());
        //inherited from staff
        $this->assertTrue($this->access->can('transaction:delete'));
    }

    /**
     * Test groups managers are granted on their groups only
     */
    public function testGroupManager(): void
    {
        $group = $this->logGroupManager();
        $this->login->logOut();
        $member_two = $this->getMemberTwo();
        $member_one = $this->getMemberOne();

        $this->logSuperAdmin();
        $other = new \Galette\Entity\Group();
        $other->setName('Not managed');
        $this->assertTrue($other->store());
        $this->login->logOut();

        $this->logMemberOne();
        $this->assertTrue($this->login->isGroupManager());

        //member:read is granted to groups managers by default
        $this->assertTrue($this->access->can('member:read'));
        $this->assertFalse($this->access->can('member:read', $member_two));
        $this->login->logOut();

        $this->logSuperAdmin();
        $this->assertTrue($group->setMembers([$member_two]));
        $this->login->logOut();
        $member_two = new \Galette\Entity\Adherent($this->zdb, (int)$member_two->id);

        $this->logMemberOne();
        $this->assertTrue($this->access->can('member:read', $member_two));
        $this->assertTrue($this->access->can('member:read', $group));
        $this->assertFalse($this->access->can('member:read', $other));
        $this->assertFalse($this->access->can('member:edit', $member_two));
        $this->assertFalse($this->access->can('group:edit', $group));

        //granting group:edit to Group manager role
        $insert = $this->zdb->insert(Roles::PERMISSIONS_TABLE);
        $insert->values([Roles::PK => 3, 'permission' => 'group:edit']);
        $this->zdb->execute($insert);
        $this->resolver->reset();
        $this->assertTrue($this->access->can('group:edit', $group));
        $this->assertFalse($this->access->can('group:edit', $other));

        $this->assertSame($member_one->id, $this->login->id);
    }

    /**
     * Test roles given explicitly, globally or for a group, and inheritance
     */
    public function testGivenRoles(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();

        $this->logSuperAdmin();
        $group = new \Galette\Entity\Group();
        $group->setName('Treasurers group');
        $this->assertTrue($group->store());
        $this->login->logOut();

        $treasurer = $this->createRole('Treasurer', ['contribution:create', 'contribution:edit']);
        $this->giveRole((int)$member_one->id, $treasurer);

        $this->logMemberOne();
        $this->assertTrue($this->access->can('contribution:create'));
        $this->assertTrue($this->access->can('contribution:edit'));
        $this->assertFalse($this->access->can('contribution:delete'));

        //inherits from Staff member role
        $deputy = $this->createRole('Deputy', [], 4);
        $this->giveRole((int)$member_one->id, $deputy);
        $this->assertTrue($this->access->can('contribution:delete'));

        //for a group only
        $editor = $this->createRole('Editor', ['member:edit']);
        $this->giveRole((int)$member_two->id, $editor, $group->getId());
        $this->login->logOut();

        $mdata = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->access->can('member:edit', $member_one));
        $this->assertTrue($this->access->can('member:edit'));
        $this->login->logOut();

        $this->logSuperAdmin();
        $this->assertTrue($group->setMembers([$member_one]));
        $this->login->logOut();
        $member_one = new \Galette\Entity\Adherent($this->zdb, (int)$member_one->id);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertTrue($this->access->can('member:edit', $member_one));
        $this->assertFalse($this->access->can('member:delete', $member_one));
    }

    /**
     * Test exports permissions are granted separately
     */
    public function testSplitExports(): void
    {
        $member_one = $this->getMemberOne();
        $printer = $this->createRole('Printer', ['member:print']);
        $this->giveRole((int)$member_one->id, $printer);

        $this->logMemberOne();
        $this->assertTrue($this->access->can('member:print'));
        $this->assertFalse($this->access->can('member:export'));
        $this->assertFalse($this->access->can('group:export'));
        $this->assertFalse($this->access->can('mailing:send'));
    }

    /**
     * Test groups scope
     */
    public function testGroupScope(): void
    {
        $member_one = $this->getMemberOne();

        $this->assertSame([], $this->access->getGroupScope('member:read'));

        $this->logSuperAdmin();
        $this->assertNull($this->access->getGroupScope('member:read'));
        $group = new \Galette\Entity\Group();
        $group->setName('Scoped group');
        $this->assertTrue($group->store());
        $this->login->logOut();

        $this->logMemberOne();
        $this->assertSame([], $this->access->getGroupScope('member:read'));

        $reader = $this->createRole('Reader', ['member:read']);
        $this->giveRole((int)$member_one->id, $reader, $group->getId());
        $this->assertSame([$group->getId()], $this->access->getGroupScope('member:read'));
        $this->assertSame([], $this->access->getGroupScope('member:edit'));

        $this->giveRole((int)$member_one->id, $reader);
        $this->assertNull($this->access->getGroupScope('member:read'));
    }

    /**
     * Test members lists follow the groups a role is given for
     */
    public function testScopedMembersLists(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();

        $this->logSuperAdmin();
        $group = new \Galette\Entity\Group();
        $group->setName('Readers group');
        $this->assertTrue($group->store());
        $this->assertTrue($group->setMembers([$member_two]));
        $this->login->logOut();

        $reader = $this->createRole('Reader', ['member:read']);
        $this->giveRole((int)$member_one->id, $reader, $group->getId());

        $previous = $this->container->get(AccessControl::class);
        $this->container->set(AccessControl::class, $this->access);
        try {
            $this->logMemberOne();
            $members = new \Galette\Repository\Members();

            $visible = $members->getVisibleMembersList(fields: ['id_adh', 'nom_adh', 'prenom_adh']);
            $ids = [];
            foreach ($visible as $row) {
                $ids[] = (int)$row->id_adh;
            }
            $this->assertSame([(int)$member_two->id], $ids);

            //self is always accessible, other members only when in the group
            $list = $members->getArrayList([(int)$member_one->id, (int)$member_two->id], as_members: false);
            $this->assertIsArray($list);
            $this->assertCount(2, $list);
            $this->assertTrue($group->setMembers([]));
            $this->assertCount(1, $members->getArrayList([(int)$member_two->id, (int)$member_one->id], as_members: false));
            $this->expectLogEntry(\Analog\Analog::WARNING, 'requested 2 member(s), only 1 are accessible or exist.');
        } finally {
            $this->container->set(AccessControl::class, $previous);
        }
    }

    /**
     * Test contributions are restricted to the groups a role is given on
     */
    public function testScopedContributions(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();

        $this->logSuperAdmin();
        $group = new \Galette\Entity\Group();
        $group->setName('Treasurers group');
        $this->assertTrue($group->store());
        $this->assertTrue($group->setMembers([$member_two]));
        $this->adh = $member_two;
        $contribution = $this->createContrib($this->getContribData());
        $this->login->logOut();

        $treasurer = $this->createRole('Treasurer', ['contribution:read']);
        $this->giveRole((int)$member_one->id, $treasurer, $group->getId());

        $previous = $this->container->get(AccessControl::class);
        $this->container->set(AccessControl::class, $this->access);
        try {
            $this->logMemberOne();

            $filters = new \Galette\Filters\ContributionsList();
            $contributions = new \Galette\Repository\Contributions($this->zdb, $this->login, $filters);
            $this->assertContains((int)$contribution->id, $this->getContributionsIds($contributions));
            $this->assertTrue((new \Galette\Entity\Contribution($this->zdb, $this->login))->load((int)$contribution->id));

            $filters->filtre_cotis_adh = (int)$member_two->id;
            $this->assertContains((int)$contribution->id, $this->getContributionsIds($contributions));

            //out of the group, contributions are no longer visible
            $this->assertTrue($group->setMembers([]));
            $this->assertNotContains((int)$contribution->id, $this->getContributionsIds($contributions));
            $this->expectLogEntry(
                \Analog\Analog::WARNING,
                'Trying to display contributions for member #' . $member_two->id . ' without appropriate ACLs'
            );
            $filters->filtre_cotis_adh = null;
            $this->assertNotContains((int)$contribution->id, $this->getContributionsIds($contributions));
            $this->assertFalse((new \Galette\Entity\Contribution($this->zdb, $this->login))->load((int)$contribution->id));
            $this->expectLogEntry(
                \Analog\Analog::ERROR,
                'No contribution #' . $contribution->id . ' (user ' . $member_one->id . ')'
            );
        } finally {
            $this->container->set(AccessControl::class, $previous);
        }
    }

    /**
     * Get contributions identifiers from a list
     *
     * @param \Galette\Repository\Contributions $contributions Contributions repository
     *
     * @return array<int>
     */
    private function getContributionsIds(\Galette\Repository\Contributions $contributions): array
    {
        $ids = [];
        foreach ($contributions->getList(false) as $row) {
            $ids[] = (int)$row->id_cotis;
        }
        return $ids;
    }
}
