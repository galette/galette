<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * FieldsConfig tests
 *
 * PHP version 5
 *
 * Copyright © 2016-2023 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Entity
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2016-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2016-09-24
 */

namespace Galette\Entity\test\units;

use PHPUnit\Framework\TestCase;

/**
 * Preferences tests class
 *
 * @category  Entity
 * @name      FieldsConfig
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2016-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2016-09-24
 */
class FieldsConfig extends TestCase
{
    private ?\Galette\Entity\FieldsConfig $fields_config = null;
    private \Galette\Core\Db $zdb;
    private array $members_fields;
    private array $members_fields_cats;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->zdb = new \Galette\Core\Db();

        include_once GALETTE_ROOT . 'includes/fields_defs/members_fields.php';
        $this->members_fields = $members_fields;
        include_once GALETTE_ROOT . 'includes/fields_defs/members_fields_cats.php';
        $this->members_fields_cats = $members_fields_cats;

        $this->fields_config = new \Galette\Entity\FieldsConfig(
            $this->zdb,
            \Galette\Entity\Adherent::TABLE,
            $this->members_fields,
            $this->members_fields_cats,
            true
        );
    }

    /**
     * Test non required fields
     *
     * @return void
     */
    public function testNonRequired()
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
     *
     * @return void
     */
    public function testInstallInit()
    {
        $result = $this->fields_config->installInit();
        $this->assertTrue($result);

        //new object with values loaded from database to compare
        $fields_config = new \Galette\Entity\FieldsConfig(
            $this->zdb,
            \Galette\Entity\Adherent::TABLE,
            $this->members_fields,
            $this->members_fields_cats
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
            $this->zdb,
            \Galette\Entity\Adherent::TABLE,
            $this->members_fields,
            $this->members_fields_cats,
            true
        );
        $this->assertTrue($lists_config->load());

        $visibles = $fields_config->getVisibilities();
        $this->assertCount(
            count($categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY]) +
            count($categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_GALETTE]) +
            count($categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT]) +
            count($lists_config->getAclMapping()),
            $visibles
        );

        $this->assertSame(0, $visibles['id_adh']);
        $this->assertSame(1, $visibles['nom_adh']);
    }

    /**
     * Count categorized_fields
     *
     * @param array $categorized Categorized fields
     *
     * @return void
     */
    private function countCategorizedFields($categorized)
    {
        $this->assertCount(3, $categorized);
        $this->assertCount(13, $categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY]);
        $this->assertCount(11, $categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_GALETTE]);
        $this->assertCount(10, $categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT]);
    }

    /**
     * Test setNotRequired
     *
     * @return void
     */
    public function testSetNotRequired()
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
     *
     * @return void
     */
    public function testGetVisibility()
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
     *
     * @return void
     */
    public function testSetFields()
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
     *
     * @return void
     */
    public function testIsSelfExcluded()
    {
        $this->assertTrue($this->fields_config->isSelfExcluded('bool_admin_adh'));
        $this->assertTrue($this->fields_config->isSelfExcluded('info_adh'));
        $this->assertFalse($this->fields_config->isSelfExcluded('nom_adh'));
    }

    /**
     * Test checkUpdate
     *
     * @return void
     */
    public function testCheckUpdate()
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
            13,
            count($categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY])
        );

        //new object instanciation should add missing field back
        $fields_config = new \Galette\Entity\FieldsConfig(
            $this->zdb,
            \Galette\Entity\Adherent::TABLE,
            $this->members_fields,
            $this->members_fields_cats
        );

        $categorized = $fields_config->getCategorizedFields();
        $this->assertSame($categorized_init, $categorized);
    }

    /**
     * Test check update when all is empty
     *
     * @return void
     */
    public function testCheckUpdateWhenEmpty()
    {
        $this->zdb->db->query(
            'TRUNCATE ' . PREFIX_DB . \Galette\Entity\FieldsConfig::TABLE,
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
        $this->zdb->db->query(
            'DELETE FROM ' . PREFIX_DB . \Galette\Entity\FieldsCategories::TABLE,
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );

        //new object instanciation should add missing fieldis and categories
        $fields_config = new \Galette\Entity\FieldsConfig(
            $this->zdb,
            \Galette\Entity\Adherent::TABLE,
            $this->members_fields,
            $this->members_fields_cats
        );

        $categorized = $fields_config->getCategorizedFields();
        $this->countCategorizedFields($categorized);
    }

    /**
     * Test get display elements
     *
     * @return void
     */
    public function testGetDisplayElements()
    {
        $fields_config = $this->fields_config;
        $fields_config->load();

        $admin_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, new \Galette\Core\I18n()))
            ->onlyMethods(array('isAdmin'))
            ->getMock();
        $admin_login->method('isAdmin')->willReturn(true);

        $elements = $fields_config->getDisplayElements($admin_login);
        $this->assertCount(3, $elements);

        $this->assertInstanceOf('\stdClass', $elements[0]);
        $this->assertSame(1, $elements[0]->id);
        $this->assertCount(8, $elements[0]->elements);

        $this->assertInstanceOf('\stdClass', $elements[1]);
        $this->assertSame(3, $elements[1]->id);
        $this->assertCount(8, $elements[1]->elements);

        $this->assertInstanceOf('\stdClass', $elements[2]);
        $this->assertSame(2, $elements[2]->id);
        $this->assertCount(10, $elements[2]->elements);
        $this->assertTrue(isset($elements[2]->elements['info_adh']));

        $user_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, new \Galette\Core\I18n()))
            ->onlyMethods(array('isUp2Date'))
            ->getMock();
        $user_login->method('isUp2Date')->willReturn(true);


        $elements = $fields_config->getDisplayElements($user_login);
        $this->assertCount(3, $elements);

        $this->assertInstanceOf('\stdClass', $elements[0]);
        $this->assertSame(1, $elements[0]->id);
        $this->assertCount(7, $elements[0]->elements);

        $this->assertInstanceOf('\stdClass', $elements[1]);
        $this->assertSame(3, $elements[1]->id);
        $this->assertCount(8, $elements[1]->elements);

        $this->assertInstanceOf('\stdClass', $elements[2]);
        $this->assertSame(2, $elements[2]->id);
        $this->assertCount(4, $elements[2]->elements);
        $this->assertFalse(isset($elements[2]->elements['info_adh']));
    }

    /**
     * Test get form elements
     *
     * @return void
     */
    public function testGetFormElements()
    {
        $fields_config = $this->fields_config;
        $fields_config->load();

        $admin_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, new \Galette\Core\I18n()))
            ->onlyMethods(array('isAdmin'))
            ->getMock();
        $admin_login->method('isAdmin')->willReturn(true);

        $elements = $fields_config->getFormElements($admin_login, false);
        $this->assertCount(2, $elements);
        $this->assertTrue(isset($elements['fieldsets']));
        $this->assertTrue(isset($elements['hiddens']));

        $this->assertCount(3, $elements['fieldsets']);

        $this->assertInstanceOf('\stdClass', $elements['fieldsets'][0]);
        $this->assertSame(1, $elements['fieldsets'][0]->id);
        $this->assertCount(11, $elements['fieldsets'][0]->elements);

        $this->assertInstanceOf('\stdClass', $elements['fieldsets'][1]);
        $this->assertSame(3, $elements['fieldsets'][1]->id);
        $this->assertCount(8, $elements['fieldsets'][1]->elements);

        $this->assertInstanceOf('\stdClass', $elements['fieldsets'][2]);
        $this->assertSame(2, $elements['fieldsets'][2]->id);
        $this->assertCount(10, $elements['fieldsets'][2]->elements);
        $this->assertTrue(isset($elements['fieldsets'][2]->elements['info_adh']));

        $this->assertCount(2, $elements['hiddens']);

        $user_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, new \Galette\Core\I18n()))
            ->onlyMethods(array('isUp2Date'))
            ->getMock();
        $user_login->method('isUp2Date')->willReturn(true);

        $elements = $fields_config->getFormElements($user_login, false);
        $this->assertCount(2, $elements);
        $this->assertTrue(isset($elements['fieldsets']));
        $this->assertTrue(isset($elements['hiddens']));

        $this->assertCount(3, $elements['fieldsets']);

        $this->assertInstanceOf('\stdClass', $elements['fieldsets'][0]);
        $this->assertSame(1, $elements['fieldsets'][0]->id);
        $this->assertCount(10, $elements['fieldsets'][0]->elements);

        $this->assertInstanceOf('\stdClass', $elements['fieldsets'][1]);
        $this->assertSame(3, $elements['fieldsets'][1]->id);
        $this->assertCount(8, $elements['fieldsets'][1]->elements);

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

        $this->assertCount(2, $elements['hiddens']);

        //form elements for self subscription
        $no_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, new \Galette\Core\I18n()))
            ->getMock();

        $elements = $fields_config->getFormElements($no_login, false, true);
        $this->assertCount(2, $elements);
        $this->assertTrue(isset($elements['fieldsets']));
        $this->assertTrue(isset($elements['hiddens']));

        $this->assertCount(3, $elements['fieldsets']);

        $this->assertInstanceOf('\stdClass', $elements['fieldsets'][0]);
        $this->assertSame(1, $elements['fieldsets'][0]->id);
        $this->assertCount(10, $elements['fieldsets'][0]->elements);

        $this->assertInstanceOf('\stdClass', $elements['fieldsets'][1]);
        $this->assertSame(3, $elements['fieldsets'][1]->id);
        $this->assertCount(8, $elements['fieldsets'][1]->elements);

        $mail = $elements['fieldsets'][1]->elements['email_adh'];
        $this->assertTrue($mail->required); //email is required for self subscription

        $this->assertInstanceOf('\stdClass', $elements['fieldsets'][2]);
        $this->assertSame(2, $elements['fieldsets'][2]->id);
        $this->assertCount(4, $elements['fieldsets'][2]->elements);
        $this->assertFalse(isset($elements['fieldsets'][2]->elements['info_adh']));

        $this->assertCount(2, $elements['hiddens']);
    }
}
