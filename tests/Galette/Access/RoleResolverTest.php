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
use Galette\Interfaces\AccessSubjectInterface;
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

        //system roles with former rights settings all off
        $this->roles = new Roles($this->zdb, new Permissions());
        $this->roles->installInit(array_fill_keys(array_keys(Roles::LEGACY_RIGHTS), value: false));

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
     * Get a plugin object
     *
     * @param ?int        $owner  Owner identifier
     * @param ?array<int> $groups Groups identifiers, those of the owner if null
     */
    private function getSubject(?int $owner, ?array $groups = null): AccessSubjectInterface
    {
        return new readonly class ($owner, $groups) implements AccessSubjectInterface {
            /**
             * Constructor
             *
             * @param ?int        $owner  Owner identifier
             * @param ?array<int> $groups Groups identifiers
             */
            public function __construct(private ?int $owner, private ?array $groups)
            {
            }

            /**
             * Get owner identifier
             */
            public function getOwnerId(): ?int
            {
                return $this->owner;
            }

            /**
             * Get groups identifiers
             *
             * @return ?array<int>
             */
            public function getGroupsIds(): ?array
            {
                return $this->groups;
            }
        };
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
     * Test new objects, not attached to a member, are checked as no object when creating them only
     */
    public function testNewObjects(): void
    {
        $this->logGroupManager();
        $this->login->logOut();
        $member_two = $this->getMemberTwo();

        foreach (['contribution:create', 'member:edit'] as $permission) {
            $insert = $this->zdb->insert(Roles::PERMISSIONS_TABLE);
            $insert->values([Roles::PK => 3, 'permission' => $permission]);
            $this->zdb->execute($insert);
        }
        $this->resolver->reset();

        $this->logMemberOne();
        $this->assertTrue($this->access->can('contribution:create', new \Galette\Entity\Contribution($this->zdb, $this->login)));
        //member two is not in the managed group
        $this->assertFalse($this->access->can(
            'contribution:create',
            new \Galette\Entity\Contribution($this->zdb, $this->login, ['type' => 1, 'adh' => (int)$member_two->id])
        ));
        //a member that could not be loaded is not a new one
        $this->assertFalse($this->access->can('member:edit', new \Galette\Entity\Adherent($this->zdb)));
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
     * Test permissions that do not apply to members are not granted for a group
     */
    public function testNoGroupScope(): void
    {
        $member_one = $this->getMemberOne();

        $this->logSuperAdmin();
        $group = new \Galette\Entity\Group();
        $group->setName('Secretaries group');
        $this->assertTrue($group->store());
        $this->assertTrue($group->setManagers([$member_one]));
        //member one is part of the group: a group grant would apply on itself
        $this->assertTrue($group->setMembers([$member_one]));
        $this->login->logOut();
        $insert = $this->zdb->insert(Roles::PERMISSIONS_TABLE);
        $insert->values([Roles::PK => 3, 'permission' => 'role:manage']);
        $this->zdb->execute($insert);

        $secretary = $this->createRole(
            'Secretary',
            ['member:read', 'config:preferences', 'history:manage', 'member:grant-admin', 'contribution:remind']
        );
        $this->giveRole((int)$member_one->id, $secretary, $group->getId());

        $this->logMemberOne();
        $this->assertTrue($this->login->isGroupManager());
        $this->assertTrue($this->access->can('member:read'));
        $this->assertFalse($this->access->can('config:preferences'));
        $this->assertFalse($this->access->can('history:manage'));
        $this->assertSame([], $this->access->getGroupScope('history:manage'));
        //administrators and reminders apply to every member
        $this->assertFalse($this->access->can('member:grant-admin'));
        $this->assertFalse($this->access->can('member:grant-admin', $member_one));
        $this->assertFalse($this->access->can('contribution:remind'));
        $this->assertSame([], $this->access->getGroupScope('contribution:remind'));

        //Group manager role is no different
        $this->assertTrue($this->access->can('group:read', $group));
        $this->assertFalse($this->access->can('role:manage'));

        //given for all groups, they are
        $this->giveRole((int)$member_one->id, $secretary);
        $this->assertTrue($this->access->can('config:preferences'));
        $this->assertNull($this->access->getGroupScope('history:manage'));
        $this->assertTrue($this->access->can('member:grant-admin'));
        $this->assertTrue($this->access->can('contribution:remind'));
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
        //member two belongs to both groups, it must be listed once
        $other_group = new \Galette\Entity\Group();
        $other_group->setName('Other readers group');
        $this->assertTrue($other_group->store());
        $this->assertTrue($other_group->setMembers([$member_two]));
        //child of member one is in no group
        $child = $this->createMember([
            'nom_adh'       => 'Doe',
            'prenom_adh'    => 'Johny',
            'parent_id'     => $member_one->id,
            'attach'        => true,
            'fingerprint'   => 'FAKER' . $this->seed
        ]);
        $this->login->logOut();

        $reader = $this->createRole('Reader', ['member:read']);
        $this->giveRole((int)$member_one->id, $reader, $group->getId());
        $this->giveRole((int)$member_one->id, $reader, $other_group->getId());

        $previous = $this->container->get(AccessControl::class);
        $this->container->set(AccessControl::class, $this->access);
        try {
            $this->logMemberOne();
            $members = new \Galette\Repository\Members();

            //as on their cards: themselves, their children, and members of the groups
            $visible = $members->getVisibleMembersList(fields: ['id_adh', 'nom_adh', 'prenom_adh']);
            $ids = [];
            foreach ($visible as $row) {
                $ids[] = (int)$row->id_adh;
            }
            sort($ids);
            $expected = [(int)$member_one->id, (int)$member_two->id, (int)$child->id];
            sort($expected);
            $this->assertSame($expected, $ids);
            $this->assertSame(3, $members->getCount());

            //self is always accessible, other members only when in the group
            $list = $members->getArrayList([(int)$member_one->id, (int)$member_two->id], as_members: false);
            $this->assertIsArray($list);
            $this->assertCount(2, $list);
            $this->assertTrue($group->setMembers([]));
            $this->assertTrue($other_group->setMembers([]));
            $this->assertCount(1, $members->getArrayList([(int)$member_two->id, (int)$member_one->id], as_members: false));
            $this->expectLogEntry(\Analog\Analog::WARNING, 'requested 2 member(s), only 1 are accessible or exist.');
        } finally {
            $this->container->set(AccessControl::class, $previous);
        }
    }

    /**
     * Test a contribution stays part of a transaction current user cannot load
     */
    public function testContributionOfHiddenTransaction(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $payer = $this->createMember([
            'nom_adh'       => 'Payer',
            'prenom_adh'    => 'Paul',
            'fingerprint'   => 'FAKER' . $this->seed
        ]);

        $this->logSuperAdmin();
        $group = new \Galette\Entity\Group();
        $group->setName('Treasurers group');
        $this->assertTrue($group->store());
        $this->assertTrue($group->setMembers([$member_two]));
        $transaction = new \Galette\Entity\Transaction($this->zdb, $this->login);
        $this->assertTrue($transaction->check(
            [
                'id_adh' => $payer->id,
                'trans_date' => date('Y-m-d'),
                'trans_amount' => 200,
                'trans_desc' => 'FAKER' . $this->seed,
                'type_paiement_trans' => 6,
            ],
            [],
            []
        ));
        $this->assertTrue($transaction->store($this->history));
        $group_transaction = new \Galette\Entity\Transaction($this->zdb, $this->login);
        $this->assertTrue($group_transaction->check(
            [
                'id_adh' => $member_two->id,
                'trans_date' => date('Y-m-d'),
                'trans_amount' => 200,
                'trans_desc' => 'FAKER' . $this->seed,
                'type_paiement_trans' => 6,
            ],
            [],
            []
        ));
        $this->assertTrue($group_transaction->store($this->history));
        $this->adh = $member_two;
        $data = $this->getContribData();
        $contribution = $this->createContrib($data + [\Galette\Entity\Transaction::PK => $transaction->id]);
        $this->login->logOut();

        $treasurer = $this->createRole(
            'Treasurer',
            ['contribution:read', 'contribution:edit', 'transaction:read', 'transaction:attach']
        );
        $this->giveRole((int)$member_one->id, $treasurer, $group->getId());

        $previous = $this->container->get(AccessControl::class);
        $this->container->set(AccessControl::class, $this->access);
        try {
            $this->logMemberOne();
            $stored = function () use ($contribution): ?int {
                $select = $this->zdb->select(\Galette\Entity\Contribution::TABLE);
                $select->where([\Galette\Entity\Contribution::PK => $contribution->id]);
                $trans_id = $this->zdb->execute($select)->current()->{\Galette\Entity\Transaction::PK};
                return $trans_id === null ? null : (int)$trans_id;
            };

            $edited = new \Galette\Entity\Contribution($this->zdb, $this->login);
            $this->assertTrue($edited->load((int)$contribution->id));
            //transaction is not shown...
            $this->assertFalse($edited->isTransactionPart());
            $this->expectLogEntry(
                \Analog\Analog::ERROR,
                'No transaction #' . $transaction->id . ' (user ' . $member_one->id . ')'
            );

            //...but kept, as the form posts no transaction
            $posted = ['type_paiement_cotis' => 2, \Galette\Entity\Transaction::PK => ''] + $data;
            $this->assertTrue($edited->check($posted, $edited->getRequired(), []));
            $this->assertTrue($edited->store());
            $this->assertSame((int)$transaction->id, $stored());

            //posting the hidden transaction keeps it, an unknown one is refused
            $this->assertTrue($edited->check(
                [\Galette\Entity\Transaction::PK => (string)$transaction->id] + $posted,
                $edited->getRequired(),
                []
            ));
            $this->expectLogEntry(
                \Analog\Analog::ERROR,
                'No transaction #' . $transaction->id . ' (user ' . $member_one->id . ')'
            );
            $this->assertTrue($edited->store());
            $this->assertSame((int)$transaction->id, $stored());
            $errors = $edited->check(
                [\Galette\Entity\Transaction::PK => '999999'] + $posted,
                $edited->getRequired(),
                []
            );
            $this->assertSame(['- You are not allowed to attach a contribution to this transaction!'], $errors);
            $this->expectLogEntry(\Analog\Analog::ERROR, 'No transaction #999999 (user ' . $member_one->id . ')');
            $this->expectLogEntry(
                \Analog\Analog::WARNING,
                'Trying to detach a contribution from hidden transaction #' . $transaction->id
            );
            $this->expectLogEntry(\Analog\Analog::ERROR, 'Some errors has been threw attempting to edit/store a contribution');

            //nor is it detached from the hidden transaction, even to an attachable one
            $this->assertTrue($group_transaction->canAttachAndDetach($this->login));
            $errors = $edited->check(
                [\Galette\Entity\Transaction::PK => (string)$group_transaction->id] + $posted,
                $edited->getRequired(),
                []
            );
            $this->assertSame(['- You are not allowed to attach a contribution to this transaction!'], $errors);
            $this->expectLogEntry(
                \Analog\Analog::WARNING,
                'Trying to detach a contribution from hidden transaction #' . $transaction->id
            );
            $this->expectLogEntry(\Analog\Analog::ERROR, 'Some errors has been threw attempting to edit/store a contribution');
            $this->assertSame((int)$transaction->id, $stored());
            $this->assertTrue($edited->load((int)$contribution->id));
            $this->expectLogEntry(
                \Analog\Analog::ERROR,
                'No transaction #' . $transaction->id . ' (user ' . $member_one->id . ')'
            );

            //its amount cannot change, transaction balance would be disclosed checking it
            foreach ([91, 93] as $amount) {
                $posted['montant_cotis'] = $amount;
                $errors = $edited->check($posted, $edited->getRequired(), []);
                $this->assertSame(
                    ['- You are not allowed to change the amount of a contribution attached to a transaction you cannot access!'],
                    $errors
                );
                $this->expectLogEntry(\Analog\Analog::ERROR, 'Some errors has been threw attempting to edit/store a contribution');
            }
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
        foreach ($contributions->getList(as_contrib: false) as $row) {
            $ids[] = (int)$row->id_cotis;
        }
        return $ids;
    }

    /**
     * Test plugins permissions on plugins objects
     */
    public function testPluginSubjects(): void
    {
        //system roles get plugin permissions when roles are first loaded
        $catalog = $this->container->get(Permissions::class);
        $resolver = new RoleResolver($this->zdb, new Roles($this->zdb, $catalog));
        $access = new AccessControl($catalog, $resolver, $this->login);

        $group = $this->logGroupManager();
        $this->login->logOut();
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();

        $this->logSuperAdmin();
        $other = new \Galette\Entity\Group();
        $other->setName('Not managed');
        $this->assertTrue($other->store());
        $this->login->logOut();

        $this->logMemberOne();
        $this->assertTrue($access->can('plugin1:read'));
        //owner
        $this->assertTrue($access->can('plugin1:manage', $this->getSubject((int)$member_one->id, [])));
        //groups managers, on their groups
        $this->assertTrue($access->can('plugin1:manage', $this->getSubject(owner: null, groups: [(int)$group->getId()])));
        $this->assertFalse($access->can('plugin1:manage', $this->getSubject(owner: null, groups: [(int)$other->getId()])));
        $this->assertFalse($access->can('plugin1:manage', $this->getSubject((int)$member_two->id)));
        $this->login->logOut();

        $this->logSuperAdmin();
        $this->assertTrue($group->setMembers([$member_two]));
        $this->login->logOut();

        $this->logMemberOne();
        $this->assertTrue($access->can('plugin1:manage', $this->getSubject((int)$member_two->id)));
    }
}
