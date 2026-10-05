<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers\Crud;

use Galette\Access\Permissions;
use Galette\Access\Role;
use Galette\Access\Roles;
use Galette\Core\FeatureFlagManager;
use Galette\Tests\GaletteRoutingTestCase;

/**
 * Roles controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RolesController extends GaletteRoutingTestCase
{
    protected int $seed = 20261006112000;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        (new Roles($this->zdb, new Permissions()))->installInit($this->preferences);
    }

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        $this->cleanMembers();
        parent::tearDown();
    }

    /**
     * Turn acls feature flag on
     */
    private function enableAcls(): void
    {
        $flags = $this->createStub(FeatureFlagManager::class);
        $flags->method('isEnabled')->willReturnCallback(fn(string $flag): bool => $flag === 'acls');
        $this->container->set(FeatureFlagManager::class, $flags);
    }

    /**
     * Test roles management is not available without acls feature flag
     */
    public function testFlagRequired(): void
    {
        $this->logSuperAdmin();
        $request = $this->createRequest('roles');

        $test_response = $this->app->handle($request);
        $this->assertSame(404, $test_response->getStatusCode());
    }

    /**
     * Test roles management requires an administrator
     */
    public function testAdminRequired(): void
    {
        $this->enableAcls();
        $this->getMemberOne();
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));

        $test_response = $this->app->handle($this->createRequest('roles'));
        $this->expectAuthMiddlewareRefused($test_response);
    }

    /**
     * Test adding, editing, giving and removing a role
     */
    public function testManageRole(): void
    {
        $this->enableAcls();
        $member = $this->getMemberOne();
        $this->logSuperAdmin();

        //list
        $test_response = $this->app->handle($this->createRequest('roles'));
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Group manager', $body);
        $this->assertStringContainsString('Staff member', $body);

        //add
        $request = $this->createRequest('storeRole', [], 'POST');
        $request = $request->withParsedBody(['name' => 'Treasurer', 'parent' => '1']);
        $test_response = $this->app->handle($request);
        $select = $this->zdb->select(Roles::TABLE);
        $select->where(['name' => 'Treasurer']);
        $role = new Role($this->zdb->execute($select)->current());
        $this->assertSame(1, $role->getParentId());
        $edit_url = $this->routeparser->urlFor('editRole', ['id' => (string)$role->getId()]);
        $this->assertSame(['Location' => [$edit_url]], $test_response->getHeaders());
        $this->expectFlashData(['success_detected' => ['Role \'Treasurer\' has been added; choose its permissions.']]);

        //edit page
        $test_response = $this->app->handle($this->createRequest('editRole', ['id' => (string)$role->getId()]));
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Create contributions', $body);
        $this->assertStringContainsString('name="permissions[contribution:create]"', $body);

        //store permissions; inherited and unknown ones are refused
        $request = $this->createRequest('doEditRole', ['id' => (string)$role->getId()], 'POST');
        $request = $request->withParsedBody([
            'name' => 'Treasurer',
            'parent' => '',
            'permissions' => ['contribution:create' => '1', 'contribution:edit' => '1']
        ]);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$edit_url]], $test_response->getHeaders());
        $this->expectFlashData(['success_detected' => ['Role \'Treasurer\' has been successfully modified.']]);
        $role = new Role($role->getId());
        $this->assertNull($role->getParentId());
        $this->assertSame(['contribution:create', 'contribution:edit'], $role->getPermissions());

        $request = $this->createRequest('doEditRole', ['id' => (string)$role->getId()], 'POST');
        $request = $request->withParsedBody(['name' => 'Treasurer', 'permissions' => ['member:fly' => '1']]);
        $this->app->handle($request);
        $this->expectFlashData(['error_detected' => ['Role \'Treasurer\' has not been modified!']]);
        $this->assertSame(['contribution:create', 'contribution:edit'], (new Role($role->getId()))->getPermissions());

        //give and take back
        $request = $this->createRequest('giveRole', ['id' => (string)$role->getId()], 'POST');
        $request = $request->withParsedBody(['id_adh' => (string)$member->id, 'id_group' => '']);
        $this->app->handle($request);
        $this->expectFlashData(['success_detected' => ['Role \'Treasurer\' has been given.']]);
        $this->app->handle($request);
        $this->expectFlashData(['error_detected' => ['Member already has this role.']]);

        $roles = new Roles($this->zdb, new Permissions());
        $given = $roles->getMembersOf($role->getId());
        $this->assertCount(1, $given);
        $test_response = $this->app->handle($this->createRequest('editRole', ['id' => (string)$role->getId()]));
        $this->assertStringContainsString('Durand René', (string)$test_response->getBody());

        $assignment = (string)array_key_first($given);
        $request = $this->createRequest('takeRole', ['id' => (string)$role->getId(), 'assignment' => $assignment], 'POST');
        $this->app->handle($request);
        $this->expectFlashData(['success_detected' => ['Role \'Treasurer\' has been taken back.']]);
        $this->assertCount(0, $roles->getMembersOf($role->getId()));

        //remove
        $request = $this->createRequest('doRemoveRole', ['id' => (string)$role->getId()], 'POST');
        $request = $request->withParsedBody(['id' => (string)$role->getId(), 'confirm' => '1']);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor('roles')]], $test_response->getHeaders());
        $select = $this->zdb->select(Roles::TABLE);
        $select->where([Roles::PK => $role->getId()]);
        $this->assertCount(0, $this->zdb->execute($select));
        $this->flash_data = [];
    }

    /**
     * Test system roles keep their name and parent
     */
    public function testSystemRole(): void
    {
        $this->enableAcls();
        $this->logSuperAdmin();

        $test_response = $this->app->handle($this->createRequest('editRole', ['id' => '3']));
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringNotContainsString('name="name"', $body);
        $this->assertStringNotContainsString('giveRole', $body);

        $request = $this->createRequest('doEditRole', ['id' => '3'], 'POST');
        $request = $request->withParsedBody(['name' => 'Bosses', 'permissions' => ['group:edit' => '1']]);
        $this->app->handle($request);
        $this->expectFlashData(['success_detected' => ['Role \'Group manager\' has been successfully modified.']]);

        $role = new Role(3);
        $this->assertSame('Group manager', $role->getName());
        $this->assertSame(1, $role->getParentId());
        $this->assertSame(['group:edit'], $role->getPermissions());
    }
}
