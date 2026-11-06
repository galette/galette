<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Updates;

use Galette\Access\Roles;
use Galette\Core\Preferences;
use Galette\Tests\GaletteTestCase;
use Galette\Updater\AbstractUpdater;

/**
 * Rights settings conversion into system roles permissions (1.40 upgrade script)
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RightsSettingsConversion extends GaletteTestCase
{
    /**
     * Get 1.40 upgrade script
     */
    private function getUpdater(): AbstractUpdater
    {
        include_once GALETTE_BASE_PATH . 'install/scripts/upgrade-to-1.40.php';
        $updater = new \Galette\Updates\UpgradeTo140();
        $zdb = new \ReflectionProperty($updater, 'zdb');
        $zdb->setValue($updater, $this->zdb);
        return $updater;
    }

    /**
     * Get permissions of a system role
     *
     * @param string $role System role key
     *
     * @return array<string>
     */
    private function getRolePermissions(string $role): array
    {
        $select = $this->zdb->select(Roles::PERMISSIONS_TABLE, 'p');
        $select->join(['r' => PREFIX_DB . Roles::TABLE], 'r.' . Roles::PK . ' = p.' . Roles::PK, [])
            ->where(['r.role_key' => $role]);
        $permissions = [];
        foreach ($this->zdb->execute($select) as $row) {
            $permissions[] = $row->permission;
        }
        sort($permissions);
        return $permissions;
    }

    /**
     * Test system roles get what rights settings allowed, and settings are removed
     */
    public function testConversion(): void
    {
        $settings = [
            'pref_bool_create_member' => '1',
            'pref_bool_groupsmanagers_edit_member' => '1',
            'pref_bool_groupsmanagers_exports' => '0',
        ];
        foreach ($settings as $name => $value) {
            $insert = $this->zdb->insert(Preferences::TABLE);
            $insert->values(['nom_pref' => $name, 'val_pref' => $value]);
            $this->zdb->execute($insert);
        }

        $updater = $this->getUpdater();
        $post_update = new \ReflectionMethod($updater, 'postUpdate');
        $this->assertTrue($post_update->invoke($updater));

        $this->assertSame(['member:create-child'], $this->getRolePermissions(Roles::MEMBER));
        //exports were disabled, editing members enabled; other settings were missing, defaults apply
        $this->assertSame(['group:read', 'member:edit', 'member:read'], $this->getRolePermissions(Roles::MANAGER));

        $select = $this->zdb->select(Preferences::TABLE);
        $select->where(['nom_pref' => array_keys(Roles::LEGACY_RIGHTS)]);
        $this->assertCount(0, $this->zdb->execute($select));
    }
}
