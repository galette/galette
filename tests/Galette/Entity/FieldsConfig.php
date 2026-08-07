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
 * Preferences tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class FieldsConfig extends GaletteTestCase
{
    private ?\Galette\Entity\FieldsConfig $fields_config = null;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->fields_config = new \Galette\Entity\FieldsConfig(
            zdb: $this->zdb,
            table: \Galette\Entity\Adherent::TABLE,
            defaults: $this->members_fields,
            cats_defaults: $this->members_fields_cats,
            install: true
        );
    }

    /**
     * Test non required fields
     */
    public function testNonRequired(): void
    {
        $nrequired = $this->fields_config->getNonRequired();
        $expected = [
            'id_adh',
            'date_echeance',
            'bool_display_info',
            'bool_exempt_adh',
            'bool_admin_adh',
            'activite_adh',
            'date_crea_adh',
            'date_modif_adh',
            'societe_adh',
            'id_statut',
            'pref_lang',
            'sexe_adh',
            'parent_id'
        ];
        $this->assertSame($expected, $nrequired);
    }

    /**
     * Test FieldsConfig initialization
     */
    public function testInstallInit(): void
    {
        $result = $this->fields_config->installInit();
        $this->assertTrue($result);

        //new object with values loaded from database to compare
        $fields_config = new \Galette\Entity\FieldsConfig(
            zdb: $this->zdb,
            table: \Galette\Entity\Adherent::TABLE,
            defaults: $this->members_fields,
            cats_defaults: $this->members_fields_cats
        );

        $categorized = $fields_config->getCategorizedFields();
        $this->countCategorizedFields($categorized);

        $required = $fields_config->getRequired();
        $expected = [
            'nom_adh'       => 1,
            'login_adh'     => 1,
            'mdp_adh'       => 1,
            'adresse_adh'   => 1,
            'cp_adh'        => 1,
            'ville_adh'     => 1
        ];
        $this->assertEquals($expected, $required);

        $isrequired = $fields_config->isRequired('login_adh');
        $this->assertTrue($isrequired);

        $isrequired = $fields_config->isRequired('info_adh');
        $this->assertFalse($isrequired);

        $lists_config = new \Galette\Entity\ListsConfig(
            zdb: $this->zdb,
            table: \Galette\Entity\Adherent::TABLE,
            defaults: $this->members_fields,
            cats_defaults: $this->members_fields_cats,
            install: true
        );
        $this->assertTrue($lists_config->load());

        $visibles = $fields_config->getVisibilities();
        $this->assertCount(
            count($categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY])
            + count($categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_GALETTE])
            + count($categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT])
            + count($lists_config->getAclMapping()),
            $visibles
        );

        $this->assertSame(0, $visibles['id_adh']);
        $this->assertSame(1, $visibles['nom_adh']);
    }

    /**
     * Count categorized_fields
     *
     * @param array<int, array<int, array<string, mixed>>> $categorized Categorized fields
     */
    private function countCategorizedFields(array $categorized): void
    {
        $this->assertCount(3, $categorized);
        $this->assertCount(13, $categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY]);
        $this->assertCount(11, $categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_GALETTE]);
        $this->assertCount(11, $categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT]);
    }

    /**
     * Test setNotRequired
     */
    public function testSetNotRequired(): void
    {
        $fields_config = $this->fields_config;
        $fields_config->load();

        $required_mdp = $fields_config->getRequired()['mdp_adh'];
        $this->assertTrue($required_mdp);

        $cat = \Galette\Entity\FieldsCategories::ADH_CATEGORY_GALETTE;
        $required_mdp = $fields_config->getCategorizedFields()[$cat][6]['required'];
        $this->assertTrue($required_mdp);

        $fields_config->setNotRequired('mdp_adh');

        $required_mdp = $fields_config->getRequired();
        $this->assertFalse(isset($required_mdp['mdp_adh']));

        $required_mdp = $fields_config->getCategorizedFields()[$cat][6]['required'];
        $this->assertFalse($required_mdp);
    }

    /**
     * Test getVisibility
     */
    public function testGetVisibility(): void
    {
        $this->fields_config->load();

        $visible = $this->fields_config->getVisibility('nom_adh');
        $this->assertSame(\Galette\Entity\FieldsConfig::USER_WRITE, $visible);

        $visible = $this->fields_config->getVisibility('id_adh');
        $this->assertSame(\Galette\Entity\FieldsConfig::NOBODY, $visible);

        $visible = $this->fields_config->getVisibility('info_adh');
        $this->assertSame(\Galette\Entity\FieldsConfig::STAFF, $visible);
    }

    /**
     * Test setFields and storage
     */
    public function testSetFields(): void
    {
        $fields_config = $this->fields_config;
        $fields_config->installInit();
        $fields_config->load();

        $fields = $fields_config->getCategorizedFields();

        //town
        $town = &$fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT][2];
        $this->assertTrue($town['required']);
        $this->assertSame(\Galette\Entity\FieldsConfig::USER_WRITE, $town['visible']);

        $town['required'] = false;
        $town['visible'] = \Galette\Entity\FieldsConfig::NOBODY;

        //gsm
        $gsm = $fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT][5];
        $gsm['position'] = count($fields[1]);
        unset($fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT][5]);
        $gsm['category'] = \Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY;
        $fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY][] = $gsm;

        $this->assertTrue($fields_config->setFields($fields));

        $fields_config->load();
        $fields = $fields_config->getCategorizedFields();

        $town = $fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT][2];
        $this->assertFalse($town['required']);
        $this->assertSame(\Galette\Entity\FieldsConfig::NOBODY, $town['visible']);

        $gsm2 = $fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY][13];
        $this->assertSame($gsm, $gsm2);
    }

    /**
     * Test isSelfExcluded
     */
    public function testIsSelfExcluded(): void
    {
        $this->assertTrue($this->fields_config->isSelfExcluded('bool_admin_adh'));
        $this->assertTrue($this->fields_config->isSelfExcluded('info_adh'));
        $this->assertFalse($this->fields_config->isSelfExcluded('nom_adh'));
    }

    /**
     * Test checkUpdate
     */
    public function testCheckUpdate(): void
    {
        $fields_config = $this->fields_config;
        $fields_config->load();

        $categorized_init = $fields_config->getCategorizedFields();

        $exists = false;
        foreach ($categorized_init[1] as $field) {
            if ($field['field_id'] === 'nom_adh') {
                $exists = true;
                break;
            }
        }
        $this->assertTrue($exists);

        $delete = $this->zdb->delete(\Galette\Entity\FieldsConfig::TABLE);
        $delete->where(
            [
                'table_name'    => \Galette\Entity\Adherent::TABLE,
                'field_id'      => 'nom_adh'
            ]
        );
        $res = $this->zdb->execute($delete);
        $this->assertSame(1, $res->count());

        $fields_config->load();

        $categorized = $fields_config->getCategorizedFields();
        $this->assertSame(
            12,
            count($categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY])
        );

        //new object instanciation should add missing field back
        $fields_config = new \Galette\Entity\FieldsConfig(
            zdb: $this->zdb,
            table: \Galette\Entity\Adherent::TABLE,
            defaults: $this->members_fields,
            cats_defaults: $this->members_fields_cats
        );

        $categorized = $fields_config->getCategorizedFields();
        $this->assertSame($categorized_init, $categorized);
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Fields configuration count for `adherents` columns does not match records.'
        );
    }

    /**
     * Test check update when all is empty
     */
    public function testCheckUpdateWhenEmpty(): void
    {
        $this->zdb->db->query(
            'DELETE FROM ' . PREFIX_DB . \Galette\Entity\FieldsConfig::TABLE,
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
        $this->zdb->db->query(
            'DELETE FROM ' . PREFIX_DB . \Galette\Entity\FieldsCategories::TABLE,
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );

        //new object instanciation should add missing fieldis and categories
        $fields_config = new \Galette\Entity\FieldsConfig(
            zdb: $this->zdb,
            table: \Galette\Entity\Adherent::TABLE,
            defaults: $this->members_fields,
            cats_defaults: $this->members_fields_cats
        );

        $categorized = $fields_config->getCategorizedFields();
        $this->countCategorizedFields($categorized);
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Fields configuration count for `adherents` columns does not match records. Is : 0 and should be'
        );
    }

    /**
     * Test get display elements
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetDisplayElements(): void
    {
        $fields_config = $this->fields_config;
        $fields_config->load();

        $admin_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, new \Galette\Core\I18n()])
            ->onlyMethods(['isAdmin'])
            ->getMock();
        $admin_login->method('isAdmin')->willReturn(true);

        $elements = $fields_config->getDisplayElements($admin_login);
        $this->assertCount(3, $elements);

        $this->assertInstanceOf('\stdClass', $elements[0]);
        $this->assertSame(1, $elements[0]->id);
        $this->assertCount(9, $elements[0]->elements);

        $this->assertInstanceOf('\stdClass', $elements[1]);
        $this->assertSame(3, $elements[1]->id);
        $this->assertCount(10, $elements[1]->elements);

        $this->assertInstanceOf('\stdClass', $elements[2]);
        $this->assertSame(2, $elements[2]->id);
        $this->assertCount(10, $elements[2]->elements);
        $this->assertTrue(isset($elements[2]->elements['info_adh']));

        $user_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, new \Galette\Core\I18n()])
            ->onlyMethods(['isUp2Date'])
            ->getMock();
        $user_login->method('isUp2Date')->willReturn(true);


        $elements = $fields_config->getDisplayElements($user_login);
        $this->assertCount(3, $elements);

        $this->assertInstanceOf('\stdClass', $elements[0]);
        $this->assertSame(1, $elements[0]->id);
        $this->assertCount(8, $elements[0]->elements);

        $this->assertInstanceOf('\stdClass', $elements[1]);
        $this->assertSame(3, $elements[1]->id);
        $this->assertCount(10, $elements[1]->elements);

        $this->assertInstanceOf('\stdClass', $elements[2]);
        $this->assertSame(2, $elements[2]->id);
        $this->assertCount(4, $elements[2]->elements);
        $this->assertFalse(isset($elements[2]->elements['info_adh']));
    }

    /**
     * Test get form elements
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetFormElements(): void
    {
        $fields_config = $this->fields_config;
        $fields_config->load();

        $admin_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, new \Galette\Core\I18n()])
            ->onlyMethods(['isAdmin'])
            ->getMock();
        $admin_login->method('isAdmin')->willReturn(true);

        $elements = $fields_config->getFormElements($admin_login, false);
        $this->assertCount(2, $elements);
        $this->assertTrue(isset($elements['fieldsets']));
        $this->assertTrue(isset($elements['hiddens']));

        $this->assertCount(3, $elements['fieldsets']);

        $this->assertInstanceOf('\stdClass', $elements['fieldsets'][0]);
        $this->assertSame(1, $elements['fieldsets'][0]->id);
        $this->assertCount(12, $elements['fieldsets'][0]->elements);

        $this->assertInstanceOf('\stdClass', $elements['fieldsets'][1]);
        $this->assertSame(3, $elements['fieldsets'][1]->id);
        $this->assertCount(10, $elements['fieldsets'][1]->elements);

        $this->assertInstanceOf('\stdClass', $elements['fieldsets'][2]);
        $this->assertSame(2, $elements['fieldsets'][2]->id);
        $this->assertCount(10, $elements['fieldsets'][2]->elements);
        $this->assertTrue(isset($elements['fieldsets'][2]->elements['info_adh']));

        $this->assertCount(1, $elements['hiddens']);

        $user_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, new \Galette\Core\I18n()])
            ->onlyMethods(['isUp2Date'])
            ->getMock();
        $user_login->method('isUp2Date')->willReturn(true);

        $elements = $fields_config->getFormElements($user_login, false);
        $this->assertCount(2, $elements);
        $this->assertTrue(isset($elements['fieldsets']));
        $this->assertTrue(isset($elements['hiddens']));

        $this->assertCount(3, $elements['fieldsets']);

        $this->assertInstanceOf('\stdClass', $elements['fieldsets'][0]);
        $this->assertSame(1, $elements['fieldsets'][0]->id);
        $this->assertCount(11, $elements['fieldsets'][0]->elements);

        $this->assertInstanceOf('\stdClass', $elements['fieldsets'][1]);
        $this->assertSame(3, $elements['fieldsets'][1]->id);
        $this->assertCount(10, $elements['fieldsets'][1]->elements);

        $mail = $elements['fieldsets'][1]->elements['email_adh'];
        $this->assertFalse($mail->required); //email is not required per default

        $this->assertInstanceOf('\stdClass', $elements['fieldsets'][2]);
        $this->assertSame(2, $elements['fieldsets'][2]->id);
        $this->assertCount(4, $elements['fieldsets'][2]->elements);
        $this->assertFalse(isset($elements['fieldsets'][2]->elements['info_adh']));

        $login = $elements['fieldsets'][2]->elements['login_adh'];
        $this->assertTrue($login->required);
        $pass  = $elements['fieldsets'][2]->elements['mdp_adh'];
        $this->assertTrue($pass->required);

        $this->assertCount(1, $elements['hiddens']);

        //form elements for self subscription
        $no_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, new \Galette\Core\I18n()])
            ->getMock();

        $elements = $fields_config->getFormElements($no_login, false, true);
        $this->assertCount(2, $elements);
        $this->assertTrue(isset($elements['fieldsets']));
        $this->assertTrue(isset($elements['hiddens']));

        $this->assertCount(3, $elements['fieldsets']);

        $this->assertInstanceOf('\stdClass', $elements['fieldsets'][0]);
        $this->assertSame(1, $elements['fieldsets'][0]->id);
        $this->assertCount(11, $elements['fieldsets'][0]->elements);

        $this->assertInstanceOf('\stdClass', $elements['fieldsets'][1]);
        $this->assertSame(3, $elements['fieldsets'][1]->id);
        $this->assertCount(10, $elements['fieldsets'][1]->elements);

        $mail = $elements['fieldsets'][1]->elements['email_adh'];
        $this->assertTrue($mail->required); //email is required for self subscription

        $this->assertInstanceOf('\stdClass', $elements['fieldsets'][2]);
        $this->assertSame(2, $elements['fieldsets'][2]->id);
        $this->assertCount(4, $elements['fieldsets'][2]->elements);
        $this->assertFalse(isset($elements['fieldsets'][2]->elements['info_adh']));

        $this->assertCount(1, $elements['hiddens']);
    }

    /**
     * Test permissions list
     */
    public function testGetPermissionsList(): void
    {
        $list = \Galette\Entity\FieldsConfig::getPermissionsList();
        $this->assertArrayNotHasKey(\Galette\Entity\FieldsConfig::ALL, $list);
    }
}
