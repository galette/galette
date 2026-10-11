<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Access\test\units;

use Galette\Access\LegacyResolver;
use Galette\Access\Permissions;
use Galette\Access\RoleResolver;
use Galette\Access\Roles;
use Galette\Core\Authentication;
use Galette\Core\Login;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\Group;
use Galette\Entity\Transaction;
use Galette\Tests\GaletteTestCase;

/**
 * System roles grant what legacy access levels and preferences grant
 *
 * For each set of rights preferences, system roles are created from them;
 * then every permission is checked for every profile, on subjects of its
 * domain, with both resolvers.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class EquivalenceTest extends GaletteTestCase
{
    protected int $seed = 20261011;

    private const array RIGHTS_PREFERENCES = [
        'pref_bool_create_member',
        'pref_bool_groupsmanagers_create_member',
        'pref_bool_groupsmanagers_edit_member',
        'pref_bool_groupsmanagers_edit_groups',
        'pref_bool_groupsmanagers_mailings',
        'pref_bool_groupsmanagers_exports',
        'pref_bool_groupsmanagers_see_contributions',
        'pref_bool_groupsmanagers_create_contributions',
        'pref_bool_groupsmanagers_see_transactions',
        'pref_bool_groupsmanagers_create_transactions',
    ];

    /** @var array<string, bool> */
    private array $saved_preferences = [];

    /** @var array<string, Adherent> */
    private array $members = [];

    /** @var array<string, Group> */
    private array $groups = [];

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        foreach ($this->saved_preferences as $preference => $value) {
            $this->preferences->$preference = $value;
        }
        (new Roles($this->zdb, new Permissions()))->installInit($this->preferences);

        $this->cleanContributions();
        $this->cleanMembers();
        parent::tearDown();
    }

    /**
     * Create a member
     *
     * @param string $key Member key
     */
    private function addMember(string $key): Adherent
    {
        $data = $this->dataAdherentOne();
        $data['nom_adh'] = 'Equivalence ' . $key;
        $data['login_adh'] = 'equivalence.' . $key . $this->seed;
        $data['email_adh'] = 'equivalence.' . $key . $this->seed . '@galette.eu';
        $this->members[$key] = $this->createMember($data);
        return $this->members[$key];
    }

    /**
     * Create members, groups, contributions and transactions
     *
     * - "self" is the member profiles are logged in as, "child" their child;
     * - "manager" manages the "managed" group, "in" belongs to it;
     * - "out" belongs to the "other" group.
     *
     * @return array<string, array<string, mixed>> Subjects by domain
     */
    private function createSubjects(): array
    {
        $this->logSuperAdmin();
        foreach (['self', 'child', 'manager', 'in', 'out'] as $key) {
            $this->addMember($key);
        }

        $update = $this->zdb->update(Adherent::TABLE);
        $update->set(['parent_id' => $this->members['self']->id])
            ->where([Adherent::PK => $this->members['child']->id]);
        $this->zdb->execute($update);

        foreach (['managed', 'other'] as $key) {
            $group = new Group();
            $group->setName('Equivalence ' . $key);
            $this->assertTrue($group->store());
            $this->groups[$key] = $group;
        }
        $this->assertTrue($this->groups['managed']->setManagers([$this->members['manager']]));
        $this->assertTrue($this->groups['managed']->setMembers([$this->members['in']]));
        $this->assertTrue($this->groups['other']->setMembers([$this->members['out']]));

        $subjects = [
            'member' => ['none' => null, 'new' => new Adherent($this->zdb)],
            'contribution' => ['none' => null, 'new' => new Contribution($this->zdb, $this->login)],
            'transaction' => ['none' => null, 'new' => new Transaction($this->zdb, $this->login)],
            'group' => ['none' => null],
        ];
        foreach (['in', 'out'] as $key) {
            $subjects['contribution']['new for ' . $key] = new Contribution(
                $this->zdb,
                $this->login,
                ['type' => 1, 'adh' => (int)$this->members[$key]->id]
            );
        }
        foreach (['self', 'child', 'in', 'out'] as $key) {
            $member = new Adherent($this->zdb, (int)$this->members[$key]->id);
            $subjects['member'][$key] = $member;

            $this->adh = $member;
            $contribution = $this->createContrib($this->getContribData());
            $subjects['contribution'][$key] = new Contribution($this->zdb, $this->login, (int)$contribution->id);

            $transaction = new Transaction($this->zdb, $this->login);
            $this->assertTrue($transaction->check(
                [
                    'id_adh' => $member->id,
                    'trans_date' => date('Y-m-d'),
                    'trans_amount' => 92,
                    'trans_desc' => 'FAKER' . $this->seed,
                    'type_paiement_trans' => 6,
                ],
                [],
                []
            ));
            $this->assertTrue($transaction->store($this->history));
            $subjects['transaction'][$key] = new Transaction($this->zdb, $this->login, (int)$transaction->id);
        }
        foreach ($this->groups as $key => $group) {
            $subjects['group'][$key] = new Group((int)$group->getId());
        }
        $this->login->logOut();

        return $subjects;
    }

    /**
     * Get a login in a given state, without going through authentication
     *
     * @param array<string, mixed> $state Authentication properties
     */
    private function getLogin(array $state): Login
    {
        $login = new Login($this->zdb, $this->i18n);
        $set = function (array $state): void {
            foreach ($state as $property => $value) {
                $this->$property = $value;
            }
        };
        \Closure::bind($set, $login, Authentication::class)($state);
        return $login;
    }

    /**
     * Get profiles to check
     *
     * @return array<string, Login>
     */
    private function getProfiles(): array
    {
        $self = [
            'logged' => true,
            'active' => true,
            'id' => (int)$this->members['self']->id,
            'login' => $this->members['self']->login,
            'name' => 'Self',
        ];

        return [
            'anonymous' => $this->getLogin([]),
            'member' => $this->getLogin($self),
            'up to date member' => $this->getLogin(['uptodate' => true] + $self),
            'group manager' => $this->getLogin([
                'logged' => true,
                'active' => true,
                'id' => (int)$this->members['manager']->id,
                'login' => $this->members['manager']->login,
                'name' => 'Manager',
                'managed_groups' => [(int)$this->groups['managed']->getId()],
            ]),
            'staff member' => $this->getLogin(['staff' => true] + $self),
            'administrator' => $this->getLogin(['admin' => true] + $self),
            'staff administrator' => $this->getLogin(['admin' => true, 'staff' => true] + $self),
            'super administrator' => $this->getLogin([
                'logged' => true,
                'active' => true,
                'id' => 0,
                'login' => 'superadmin',
                'name' => 'Admin',
                'admin' => true,
                'superadmin' => true,
            ]),
        ];
    }

    /**
     * Get sets of rights preferences to check: all off, all on, then each one alone
     *
     * @return array<string, array<string, bool>>
     */
    private function getPreferencesSets(): array
    {
        $off = array_fill_keys(self::RIGHTS_PREFERENCES, false);
        $sets = [
            'all off' => $off,
            'all on' => array_fill_keys(self::RIGHTS_PREFERENCES, true),
        ];
        foreach (self::RIGHTS_PREFERENCES as $preference) {
            $sets[$preference] = [$preference => true] + $off;
        }
        return $sets;
    }

    /**
     * Describe a group scope
     *
     * @param ?array<int> $scope Groups identifiers, null for all
     */
    private function describeScope(?array $scope): string
    {
        if ($scope === null) {
            return 'all';
        }
        $names = [];
        foreach ($scope as $group_id) {
            $names[] = array_search($group_id, array_map(fn(Group $group): int => (int)$group->getId(), $this->groups), true) ?: (string)$group_id;
        }
        sort($names);
        return '[' . implode(',', $names) . ']';
    }

    /**
     * Why roles grant differently, if they are expected to
     *
     * @param string $profile Profile
     * @param string $name    Permission name
     * @param string $subject Subject key
     * @param bool   $legacy  Whether legacy levels grant
     */
    private function getAcceptedDifference(string $profile, string $name, string $subject, bool $legacy): ?string
    {
        $manager = $profile === 'group manager';
        return match (true) {
            !$legacy && $manager && $subject === 'none' && in_array($name, ['member:read', 'member:edit'], true)
                => 'roles grant on their groups; members are never checked without a member',
            !$legacy && $manager && $subject === 'none' && $name === 'group:edit'
                => 'managers allowed to edit their groups reach the members search, restricted to accessible members',
            $legacy
                && ($subject === 'none' || str_starts_with($subject, 'new'))
                && in_array($name, ['contribution:read', 'transaction:read'], true)
                => 'legacy grants what is not loaded; callers check it has been loaded first',
            $legacy && in_array($profile, ['member', 'up to date member'], true) && $name === 'member:create' && $subject === 'self'
                => 'creating a member is checked on new members only',
            $legacy && $manager && $subject !== 'none' && in_array(
                $name,
                ['member:create', 'member:export', 'member:print', 'contribution:create', 'transaction:create', 'group:export'],
                true
            ) => 'roles restrict managers to their groups, legacy granted on any member or group',
            default => null,
        };
    }

    /**
     * Test roles created from preferences grant what levels and preferences grant
     */
    public function testRolesGrantWhatLevelsGrant(): void
    {
        foreach (self::RIGHTS_PREFERENCES as $preference) {
            $this->saved_preferences[$preference] = (bool)$this->preferences->$preference;
        }

        $subjects = $this->createSubjects();
        $profiles = $this->getProfiles();
        $catalog = new Permissions();
        $legacy = new LegacyResolver($this->zdb, $this->preferences);

        $mismatches = [];
        foreach ($this->getPreferencesSets() as $set => $preferences) {
            foreach ($preferences as $preference => $value) {
                $this->preferences->$preference = $value;
            }
            (new Roles($this->zdb, $catalog))->installInit($this->preferences);
            $roles = new RoleResolver($this->zdb);

            foreach ($profiles as $profile => $login) {
                foreach ($catalog->getAll() as $name => $permission) {
                    $legacy_scope = $this->describeScope($legacy->getGroupScope($login, $permission));
                    $roles_scope = $this->describeScope($roles->getGroupScope($login, $permission));
                    if ($legacy_scope !== $roles_scope) {
                        $mismatches[] = sprintf(
                            '%s | %s | %s scope: legacy %s, roles %s',
                            $set,
                            $profile,
                            $name,
                            $legacy_scope,
                            $roles_scope
                        );
                    }

                    foreach ($subjects[$permission->getDomain()] ?? ['none' => null] as $key => $subject) {
                        $granted_legacy = $legacy->isGranted($login, $permission, $subject);
                        $granted_roles = $roles->isGranted($login, $permission, $subject);
                        if (
                            $granted_legacy !== $granted_roles
                            && $this->getAcceptedDifference($profile, $name, $key, $granted_legacy) === null
                        ) {
                            $mismatches[] = sprintf(
                                '%s | %s | %s on %s: legacy %s, roles %s',
                                $set,
                                $profile,
                                $name,
                                $key,
                                $granted_legacy ? 'yes' : 'no',
                                $granted_roles ? 'yes' : 'no'
                            );
                        }
                    }
                }
            }
        }

        $this->assertSame([], $mismatches);
    }
}
