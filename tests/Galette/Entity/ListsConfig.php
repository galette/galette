<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Entity;

use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * ListsConfig tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ListsConfig extends GaletteTestCase
{
    private ?\Galette\Entity\ListsConfig $lists_config = null;
    /** @var string[]  */
    private array $default_lists = [
        'id_adh',
        'list_adh_name',
        'pseudo_adh',
        'id_statut',
        'list_adh_contribstatus',
        'date_modif_adh'
    ];

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->lists_config = new \Galette\Entity\ListsConfig(
            zdb: $this->zdb,
            table: \Galette\Entity\Adherent::TABLE,
            defaults: $this->members_fields,
            cats_defaults: $this->members_fields_cats,
            install: true
        );
    }

    /**
     * Test getVisibility
     */
    public function testGetVisibility(): void
    {
        $this->lists_config->load();

        $visible = $this->lists_config->getVisibility('nom_adh');
        $this->assertSame(\Galette\Entity\FieldsConfig::NOBODY, $visible);

        //must be the same than nom_adh
        $visible = $this->lists_config->getVisibility('list_adh_name');
        $this->assertSame(\Galette\Entity\FieldsConfig::USER_WRITE, $visible);

        $visible = $this->lists_config->getVisibility('id_statut');
        $this->assertSame(\Galette\Entity\FieldsConfig::STAFF, $visible);

        //must be the same than id_statut
        $visible = $this->lists_config->getVisibility('list_adh_contribstatus');
        $this->assertSame(\Galette\Entity\FieldsConfig::STAFF, $visible);
    }

    /**
     * Test setFields and storage
     */
    public function testSetFields(): void
    {
        $lists_config = $this->lists_config;
        $lists_config->installInit();
        $lists_config->load();

        $fields = $lists_config->getCategorizedFields();

        $list = $lists_config->getListedFields();
        $this->assertCount(6, $list);

        $expecteds = $this->default_lists;
        foreach ($expecteds as $k => $expected) {
            $this->assertSame($expected, $list[$k]['field_id']);
            $this->assertSame($k, $list[$k]['list_position']);
        }

        $expecteds = [
            'id_adh',
            'list_adh_name',
            'email_adh',
            'tel_adh',
            'id_statut',
            'list_adh_contribstatus',
            'ville_adh'
        ];

        $new_list = [];
        foreach ($expecteds as $key) {
            $new_list[] = $lists_config->getField($key);
        }
        $this->assertTrue($lists_config->setListFields($new_list));

        $list = $lists_config->getListedFields();
        $this->assertCount(7, $list);

        foreach ($expecteds as $k => $expected) {
            $this->assertSame($expected, $list[$k]['field_id']);
            $this->assertSame($k, $list[$k]['list_position']);
        }

        $field = $lists_config->getField('pseudo_adh');
        $this->assertSame(-1, $field['list_position']);
        $this->assertFalse($field['list_visible']);

        $field = $lists_config->getField('date_modif_adh');
        $this->assertSame(-1, $field['list_position']);
        $this->assertFalse($field['list_visible']);

        // copied from FieldsConfig::testSetFields to ensure it works as excepted from here.
        //town
        $town = &$fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT][2]; //3 in FieldsConfig but 2 here.
        $this->assertSame('ville_adh', $town['field_id']);
        $this->assertTrue($town['required']);
        $this->assertSame(\Galette\Entity\FieldsConfig::USER_WRITE, $town['visible']);

        $town['required'] = false;
        $town['visible'] = \Galette\Entity\FieldsConfig::NOBODY;

        //gsm
        $gsm = $fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT][6]; //5 in FieldsConfig but 6 here.
        $gsm['position'] = count($fields[1]);
        unset($fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT][6]); //5 in FieldsConfig but 6 here.
        $gsm['category'] = \Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY;
        $fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY][] = $gsm;

        $this->assertTrue($lists_config->setFields($fields));

        $lists_config->load();
        $fields = $lists_config->getCategorizedFields();

        $town = $fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT][2]; //3 in FieldsConfig but 2 here.
        $this->assertFalse($town['required']);
        $this->assertSame(\Galette\Entity\FieldsConfig::NOBODY, $town['visible']);

        $gsm2 = $fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY][11]; //13 in FieldsConfig but 11 here
        $this->assertSame($gsm, $gsm2);
        // /copied from FieldsConfig::testSetFields to ensure it works as expected from here.
    }

    /**
     * Test get display elements
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetDisplayElements(): void
    {
        $lists_config = $this->lists_config;
        $lists_config->load();

        //admin
        $superadmin_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, new \Galette\Core\I18n()])
            ->onlyMethods(['isSuperAdmin'])
            ->getMock();
        $superadmin_login->method('isSuperAdmin')->willReturn(true);

        $elements = $lists_config->getDisplayElements($superadmin_login);
        $this->assertCount(count($this->default_lists), $elements);

        //admin
        $admin_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, new \Galette\Core\I18n()])
            ->onlyMethods(['isAdmin'])
            ->getMock();
        $admin_login->method('isAdmin')->willReturn(true);

        $elements = $lists_config->getDisplayElements($admin_login);
        $this->assertCount(count($this->default_lists), $elements);

        //staff
        $staff_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, new \Galette\Core\I18n()])
            ->onlyMethods(['isStaff'])
            ->getMock();
        $staff_login->method('isStaff')->willReturn(true);

        $elements = $lists_config->getDisplayElements($staff_login);
        $this->assertCount(count($this->default_lists), $elements);

        //following tests will have lower ACLS (cannot see status)
        $expecteds = [
            'id_adh',
            'list_adh_name',
            'pseudo_adh',
            'date_modif_adh'
        ];
        $new_list = [];
        foreach ($expecteds as $key) {
            $new_list[] = $lists_config->getField($key);
        }

        //group manager
        $manager_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, new \Galette\Core\I18n()])
            ->onlyMethods(['isGroupManager'])
            ->getMock();
        $manager_login->method('isGroupManager')->willReturn(true);

        $elements = $lists_config->getDisplayElements($manager_login);
        $this->assertCount(count($new_list), $elements);

        //to keep last know rank. May switch from 2 to 6 because of field visibility.
        $last_ok = -1;
        foreach ($expecteds as $k => $expected) {
            $this->assertSame($expected, $new_list[$k]['field_id']);
            if ($new_list[$k]['list_position'] != $k - 1) {
                $this->assertGreaterThan($last_ok, $new_list[$k]['list_position']);
                $last_ok = $new_list[$k]['list_position'];
            } else {
                $this->assertSame($k, $new_list[$k]['list_position']);
            }
        }

        //simplemember
        $user_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, new \Galette\Core\I18n()])
            ->onlyMethods(['isUp2Date'])
            ->getMock();
        $user_login->method('isUp2Date')->willReturn(true);

        $elements = $lists_config->getDisplayElements($user_login);
        $this->assertCount(count($new_list), $elements);

        //to keep last know rank. May switch from 2 to 6 because of field visibility.
        $last_ok = -1;
        foreach ($expecteds as $k => $expected) {
            $this->assertSame($expected, $new_list[$k]['field_id']);
            if ($new_list[$k]['list_position'] != $k - 1) {
                $this->assertGreaterThan($last_ok, $new_list[$k]['list_position']);
                $last_ok = $new_list[$k]['list_position'];
            } else {
                $this->assertSame($k, $new_list[$k]['list_position']);
            }
        }
    }
}
