<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Core\AccessControl;
use Galette\Core\Voters\SubscriptionVoter;
use Galette\Core\Voters\GroupVoter;
use Galette\Tests\GaletteTestCase;

/**
 * AccessControl tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AccessControlTest extends GaletteTestCase
{
    protected int $seed = 420322;
    private AccessControl $accessControl;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->accessControl = new AccessControl($this->zdb);
        $this->setupRbacTables();
    }

    /**
     * @inheritDoc
     */
    public function tearDown(): void
    {
        $this->cleanMembers();
        $this->zdb->db->query("DELETE FROM galette_groups", \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        $this->zdb->db->query("DELETE FROM galette_adherent_roles", \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        $this->zdb->db->query("DELETE FROM galette_role_permissions", \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        $this->zdb->db->query("DELETE FROM galette_roles", \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        $this->zdb->db->query("DELETE FROM galette_permissions", \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        
        try {
            parent::tearDown();
        } catch (\Throwable $e) {
            // Ignore transaction issues caused by implicit DDL commits in MySQL
            if (!str_contains($e->getMessage(), 'no active transaction')) {
                throw $e;
            }
        }
    }

    /**
     * Setup RBAC tables for testing
     */
    private function setupRbacTables(): void
    {
        $sqlPath = GALETTE_ROOT . '../patches/2026-03-22_rbac_migration.sql';
        
        $sql = file_get_contents($sqlPath);
        $statements = explode(';', $sql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                try {
                    $this->zdb->db->query($statement, \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
                } catch (\Throwable $e) {
                    if (!str_contains($e->getMessage(), 'already exists')) {
                        throw $e;
                    }
                }
            }
        }

        if (!$this->zdb->inTransaction()) {
            $this->zdb->beginTransaction();
        }
    }

    /**
     * Test superadmin bypass
     */
    public function testSuperAdminBypass(): void
    {
        $this->login->logAdmin('superadmin', $this->preferences);
        $this->assertTrue($this->accessControl->can('any:permission', null, $this->login));
    }

    /**
     * Test inactive user denied
     */
    public function testInactiveUserDenied(): void
    {
        $this->logSuperAdmin();
        $adh = $this->getMemberOne();
        $adh->check(['activite_adh' => false], [], []);
        $adh->store();
        $this->login->logOut();

        // Expect warning about inactive member when logging in
        $this->assertFalse($this->login->login($adh->login, 'J^B-()f'));
        $this->expectLogEntry(\Analog\Analog::WARNING, "Member `{$adh->login} is inactive!`");

        $this->assertFalse($this->accessControl->can('any:permission', null, $this->login));
    }

    /**
     * Test RBAC static permission granted
     */
    public function testRbacPermissionGranted(): void
    {
        // 1. Create a role and a permission
        $this->zdb->db->query("INSERT INTO galette_roles (nom_role) VALUES ('TestRole')", \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        $roleId = (int)$this->zdb->db->getDriver()->getLastGeneratedValue();
        
        $this->zdb->db->query("INSERT INTO galette_permissions (nom_perm, description_perm) VALUES ('test:perm', 'Test')", \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        $permId = (int)$this->zdb->db->getDriver()->getLastGeneratedValue();
        
        $this->zdb->db->query("INSERT INTO galette_role_permissions (id_role, id_perm) VALUES ($roleId, $permId)", \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        
        // 2. Assign role to user
        $this->logSuperAdmin();
        $adh = $this->getMemberOne();
        $adh->check(['activite_adh' => true], [], []);
        $adh->store();
        
        $this->zdb->db->query("INSERT INTO galette_adherent_roles (id_adh, id_role) VALUES ({$adh->id}, $roleId)", \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        $this->login->logOut();
        
        // 3. Login as user and check
        $this->assertTrue($this->login->login($adh->login, 'J^B-()f'));
        $this->assertTrue($this->accessControl->can('test:perm', null, $this->login));
    }

    /**
     * Test SubscriptionVoter
     */
    public function testSubscriptionVoter(): void
    {
        $this->accessControl->addVoter(new SubscriptionVoter());
        
        $this->logSuperAdmin();
        $adh = $this->getMemberOne();
        $adh->check(['activite_adh' => true], [], []);
        $adh->store();
        // Force not up to date
        $this->zdb->db->query("UPDATE galette_adherents SET date_echeance = '2000-01-01' WHERE id_adh = {$adh->id}", \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        $this->login->logOut();
        
        $this->assertTrue($this->login->login($adh->login, 'J^B-()f'));
        // 'member:read' is restricted in SubscriptionVoter
        $this->assertFalse($this->accessControl->can('member:read', null, $this->login));
    }

    /**
     * Test GroupVoter
     */
    public function testGroupVoter(): void
    {
        $this->accessControl->addVoter(new GroupVoter());
        
        $this->logSuperAdmin();
        // 1. Create a group
        $now = date('Y-m-d H:i:s');
        $this->zdb->db->query("INSERT INTO galette_groups (group_name, creation_date) VALUES ('TestGroup', '$now')", \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        $groupId = (int)$this->zdb->db->getDriver()->getLastGeneratedValue();
        
        // 2. Create a manager and a member with unique emails to avoid validation errors
        $adh1 = $this->dataAdherentOne();
        $adh1['login_adh'] = 'manager.' . $this->seed . '.' . uniqid();
        $adh1['email_adh'] = 'manager.' . $this->seed . '.' . uniqid() . '@example.com';
        $manager = $this->createMember($adh1);
        
        $adh2 = $this->dataAdherentTwo();
        $adh2['login_adh'] = 'member.' . $this->seed . '.' . uniqid();
        $adh2['email_adh'] = 'member.' . $this->seed . '.' . uniqid() . '@example.com';
        $member = $this->createMember($adh2);
        
        // 3. Make member part of group
        $this->zdb->db->query("INSERT INTO galette_groups_members (id_group, id_adh) VALUES ($groupId, {$member->id})", \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        
        // 4. Make manager... manager of group
        $this->zdb->db->query("INSERT INTO galette_groups_managers (id_group, id_adh) VALUES ($groupId, {$manager->id})", \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        $this->login->logOut();
        
        // 5. Login as manager and check access to member
        $this->assertTrue($this->login->login($manager->login, 'J^B-()f'));
        $this->assertTrue($this->accessControl->can('any:perm', $member, $this->login));
    }
}
