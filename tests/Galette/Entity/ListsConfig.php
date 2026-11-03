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
    use \Galette\Tests\DynamicFieldsHelper;

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

    /**
     * Test dynamic fields in lists
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testDynamicFields(): void
    {
        $line_id = $this->createDynamicField(\Galette\DynamicFields\DynamicField::LINE, 'Line field');
        $choice_id = $this->createDynamicField(
            \Galette\DynamicFields\DynamicField::CHOICE,
            'Choice field',
            [
                'field_perm' => \Galette\Entity\FieldsConfig::STAFF,
                'fixed_values' => "First\nSecond"
            ]
        );
        $repeat_id = $this->createDynamicField(
            \Galette\DynamicFields\DynamicField::LINE,
            'Repeatable field',
            ['field_repeat' => 3]
        );
        $text_id = $this->createDynamicField(\Galette\DynamicFields\DynamicField::TEXT, 'Text field');
        $file_id = $this->createDynamicField(\Galette\DynamicFields\DynamicField::FILE, 'File field');
        $separator_id = $this->createDynamicField(\Galette\DynamicFields\DynamicField::SEPARATOR, 'Separator');

        $lists_config = $this->lists_config;
        $lists_config->installInit();
        $lists_config->load();

        $remainings = $lists_config->getRemainingFields();
        $this->assertArrayHasKey('dyn_' . $line_id, $remainings);
        $this->assertArrayHasKey('dyn_' . $choice_id, $remainings);
        $this->assertArrayHasKey('dyn_' . $repeat_id, $remainings);
        $this->assertArrayHasKey('dyn_' . $text_id, $remainings);
        $this->assertArrayNotHasKey('dyn_' . $file_id, $remainings);
        $this->assertArrayNotHasKey('dyn_' . $separator_id, $remainings);
        $this->assertSame('Choice field', $remainings['dyn_' . $choice_id]['label']);
        $this->assertSame(\Galette\Entity\FieldsConfig::STAFF, $remainings['dyn_' . $choice_id]['visible']);

        $this->assertTrue($lists_config->getField('dyn_' . $choice_id)['sortable']);
        $this->assertFalse($lists_config->getField('dyn_' . $repeat_id)['sortable']);
        $this->assertFalse($lists_config->getField('dyn_' . $text_id)['sortable']);
        $this->assertSame(
            \Galette\Entity\FieldsConfig::STAFF,
            $lists_config->getVisibility('dyn_' . $choice_id)
        );
        $this->assertSame(
            \Galette\Entity\FieldsConfig::NOBODY,
            $lists_config->getVisibility('dyn_' . $file_id)
        );

        foreach (['dyn_' . $file_id, 'dyn_' . $separator_id, 'dyn_999999'] as $unlistable) {
            try {
                $lists_config->getField($unlistable);
                $this->fail($unlistable . ' must not be a list field');
            } catch (\UnexpectedValueException $e) {
                $this->assertSame($unlistable . ' field does not exists', $e->getMessage());
            }
        }

        $expecteds = [
            'id_adh',
            'list_adh_name',
            'dyn_' . $line_id,
            'pseudo_adh',
            'dyn_' . $choice_id,
            'id_statut'
        ];
        $new_list = [];
        foreach ($expecteds as $key) {
            $new_list[] = $lists_config->getField($key);
        }
        $this->assertTrue($lists_config->setListFields($new_list));

        //check from a new instance, with consistency checks
        $lists_config = new \Galette\Entity\ListsConfig(
            zdb: $this->zdb,
            table: \Galette\Entity\Adherent::TABLE,
            defaults: $this->members_fields,
            cats_defaults: $this->members_fields_cats
        );
        $list = $lists_config->getListedFields();
        $this->assertSame($expecteds, array_column($list, 'field_id'));
        $this->assertSame(array_keys($expecteds), array_column($list, 'list_position'));
        $this->assertTrue($list[2]['is_dynamic']);
        $this->assertSame('Line field', $list[2]['label']);
        $this->assertSame($line_id, $list[2]['dynamic_id']);

        $remainings = $lists_config->getRemainingFields();
        $this->assertArrayNotHasKey('dyn_' . $line_id, $remainings);
        $this->assertArrayHasKey('dyn_' . $text_id, $remainings);
        $this->assertArrayHasKey('date_modif_adh', $remainings);

        //members form configuration does not see list rows
        $fields_config = new \Galette\Entity\FieldsConfig(
            zdb: $this->zdb,
            table: \Galette\Entity\Adherent::TABLE,
            defaults: $this->members_fields,
            cats_defaults: $this->members_fields_cats
        );
        $this->assertArrayNotHasKey('dyn_' . $line_id, $fields_config->getVisibilities());
        foreach ($fields_config->getCategorizedFields() as $category) {
            $this->assertNotContains('dyn_' . $line_id, array_column($category, 'field_id'));
        }

        //staff can see the STAFF choice field
        $staff_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, new \Galette\Core\I18n()])
            ->onlyMethods(['isStaff'])
            ->getMock();
        $staff_login->method('isStaff')->willReturn(true);
        $elements = $lists_config->getDisplayElements($staff_login);
        $this->assertSame($expecteds, array_column($elements, 'field_id'));

        //group manager cannot see status nor the STAFF choice field
        $manager_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, new \Galette\Core\I18n()])
            ->onlyMethods(['isGroupManager'])
            ->getMock();
        $manager_login->method('isGroupManager')->willReturn(true);
        $elements = $lists_config->getDisplayElements($manager_login);
        $this->assertSame(
            ['id_adh', 'list_adh_name', 'dyn_' . $line_id, 'pseudo_adh'],
            array_column($elements, 'field_id')
        );

        //removing a field drops it from the list
        $line = \Galette\DynamicFields\DynamicField::loadFieldType($this->zdb, $line_id);
        $this->assertInstanceOf(\Galette\DynamicFields\DynamicField::class, $line);
        $this->assertTrue($line->remove());
        $this->assertSame(0, $this->countListRows('dyn_' . $line_id));
        $this->assertTrue($lists_config->load());
        $this->assertSame(
            ['id_adh', 'list_adh_name', 'pseudo_adh', 'dyn_' . $choice_id, 'id_statut'],
            array_column($lists_config->getListedFields(), 'field_id')
        );

        //unlisting a field drops its row
        $new_list = [];
        foreach (['id_adh', 'list_adh_name', 'pseudo_adh'] as $key) {
            $new_list[] = $lists_config->getField($key);
        }
        $this->assertTrue($lists_config->setListFields($new_list));
        $this->assertSame(0, $this->countListRows('dyn_' . $choice_id));
        $this->assertArrayHasKey('dyn_' . $choice_id, $lists_config->getRemainingFields());
    }

    /**
     * Count list configuration rows for a field
     *
     * @param string $field_id Field id
     */
    private function countListRows(string $field_id): int
    {
        $select = $this->zdb->select(\Galette\Entity\FieldsConfig::TABLE);
        $select->where([
            'table_name' => \Galette\Entity\Adherent::TABLE,
            'field_id' => $field_id
        ]);
        return $this->zdb->execute($select)->count();
    }

    /**
     * Test picture cannot be added to lists
     */
    public function testPictureNotListed(): void
    {
        $lists_config = $this->lists_config;
        $lists_config->load();

        $remainings = $lists_config->getRemainingFields();
        $this->assertArrayNotHasKey('photo', $remainings);
        $this->assertArrayHasKey('ville_adh', $remainings);
    }
}
