<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * FieldsConfig tests
 *
 * PHP version 5
 *
 * Copyright © 2016-2021 The Galette Team
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
 * @copyright 2016-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2016-09-24
 */

namespace Galette\Entity\test\units;

use atoum;

/**
 * Preferences tests class
 *
 * @category  Entity
 * @name      FieldsConfig
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2016-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2016-09-24
 */
class FieldsConfig extends atoum
{
    private $fields_config = null;
    private $zdb;
    private $members_fields;
    private $members_fields_cats;

    /**
     * Set up tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function beforeTestMethod($method)
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
        $this->array($nrequired)->isIdenticalTo($expected);
    }

    /**
     * Test FieldsConfig initialization
     *
     * @return void
     */
    public function testInstallInit()
    {
        $result = $this->fields_config->installInit(
            $this->zdb
        );
        $this->boolean($result)->isTrue();

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
        $this->array($required)->isEqualTo($expected);

        $isrequired = $fields_config->isRequired('login_adh');
        $this->boolean($isrequired)->isTrue();

        $isrequired = $fields_config->isRequired('info_adh');
        $this->boolean($isrequired)->isFalse();

        $lists_config = new \Galette\Entity\ListsConfig(
            $this->zdb,
            \Galette\Entity\Adherent::TABLE,
            $this->members_fields,
            $this->members_fields_cats,
            true
        );
        $this->boolean($lists_config->load())->isTrue();

        $visibles = $fields_config->getVisibilities();
        $this->array($visibles)
             ->hasSize(
                 count($categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY]) +
                 count($categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_GALETTE]) +
                 count($categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT]) +
                 count($lists_config->getAclMapping())
             )
            ->integer['id_adh']->isIdenticalTo(0)
            ->integer['nom_adh']->isIdenticalTo(1);
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
        $this->array($categorized)
            ->hasSize(3);
        $this->array($categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY])
            ->hasSize(13);
        $this->array($categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_GALETTE])
            ->hasSize(11);
        $this->array($categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT])
            ->hasSize(10);
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
        $this->boolean($required_mdp)->isTrue();

        $cat = \Galette\Entity\FieldsCategories::ADH_CATEGORY_GALETTE;
        $required_mdp = $fields_config->getCategorizedFields()[$cat][6]['required'];
        $this->boolean($required_mdp)->isTrue();

        $fields_config->setNotRequired('mdp_adh');

        $required_mdp = $fields_config->getRequired();
        $this->array($required_mdp)->notHasKey('mdp_adh');

        $required_mdp = $fields_config->getCategorizedFields()[$cat][6]['required'];
        $this->boolean($required_mdp)->isFalse();
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
        $this->integer($visible)->isIdenticalTo(\Galette\Entity\FieldsConfig::USER_WRITE);

        $visible = $this->fields_config->getVisibility('id_adh');
        $this->integer($visible)->isIdenticalTo(\Galette\Entity\FieldsConfig::NOBODY);

        $visible = $this->fields_config->getVisibility('info_adh');
        $this->integer($visible)->isIdenticalTo(\Galette\Entity\FieldsConfig::STAFF);
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
        $this->boolean($town['required'])->isTrue();
        $this->integer($town['visible'])->isIdenticalTo(\Galette\Entity\FieldsConfig::USER_WRITE);

        $town['required'] = false;
        $town['visible'] = \Galette\Entity\FieldsConfig::NOBODY;

        //gsm
        $gsm = $fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT][5];
        $gsm['position'] = count($fields[1]);
        unset($fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT][5]);
        $gsm['category'] = \Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY;
        $fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY][] = $gsm;

        $this->boolean($fields_config->setFields($fields))->isTrue();

        $fields_config->load();
        $fields = $fields_config->getCategorizedFields();

        $town = $fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT][2];
        $this->boolean($town['required'])->isFalse();
        $this->integer($town['visible'])->isIdenticalTo(\Galette\Entity\FieldsConfig::NOBODY);

        $gsm2 = $fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY][13];
        $this->array($gsm2)->isIdenticalTo($gsm);
    }

    /**
     * Test isSelfExcluded
     *
     * @return void
     */
    public function testIsSelfExcluded()
    {
        $this->boolean($this->fields_config->isSelfExcluded('bool_admin_adh'))->isTrue();
        $this->boolean($this->fields_config->isSelfExcluded('info_adh'))->isTrue();
        $this->boolean($this->fields_config->isSelfExcluded('nom_adh'))->isFalse();
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
        $this->boolean($exists)->isTrue();

        $delete = $this->zdb->delete(\Galette\Entity\FieldsConfig::TABLE);
        $delete->where(
            [
                'table_name'    => \Galette\Entity\Adherent::TABLE,
                'field_id'      => 'nom_adh'
            ]
        );
        $res = $this->zdb->execute($delete);
        $this->integer($res->count())->isIdenticalTo(1);

        $fields_config->load();

        $categorized = $fields_config->getCategorizedFields();
        $this->integer(
            count($categorized[\Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY])
        )->isIdenticalTo(13);

        //new object instanciation should add missing field back
        $fields_config = new \Galette\Entity\FieldsConfig(
            $this->zdb,
            \Galette\Entity\Adherent::TABLE,
            $this->members_fields,
            $this->members_fields_cats
        );

        $categorized = $fields_config->getCategorizedFields();
        $this->array($categorized)->isIdenticalTo($categorized_init);
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
            \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
        $this->zdb->db->query(
            'DELETE FROM ' . PREFIX_DB . \Galette\Entity\FieldsCategories::TABLE,
            \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
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

        $admin_login = new \mock\Galette\Core\Login(
            $this->zdb,
            new \Galette\Core\I18n()
        );
        $this->calling($admin_login)->isAdmin = true;

        $elements = $fields_config->getDisplayElements($admin_login);
        $this->array($elements)
            ->hasSize(3);

        $this->object($elements[0])->isInstanceOf('\stdClass');
        $this->integer($elements[0]->id)->isIdenticalTo(1);
        $this->array($elements[0]->elements)->hasSize(8);

        $this->object($elements[1])->isInstanceOf('\stdClass');
        $this->integer($elements[1]->id)->isIdenticalTo(3);
        $this->array($elements[1]->elements)->hasSize(8);

        $this->object($elements[2])->isInstanceOf('\stdClass');
        $this->integer($elements[2]->id)->isIdenticalTo(2);
        $this->array($elements[2]->elements)
            ->hasSize(10)
            ->hasKey('info_adh');

        $user_login = new \mock\Galette\Core\Login(
            $this->zdb,
            new \Galette\Core\I18n()
        );
        $this->calling($user_login)->isUp2Date = true;

        $elements = $fields_config->getDisplayElements($user_login);
        $this->array($elements)
            ->hasSize(3);

        $this->object($elements[0])->isInstanceOf('\stdClass');
        $this->integer($elements[0]->id)->isIdenticalTo(1);
        $this->array($elements[0]->elements)->hasSize(7);

        $this->object($elements[1])->isInstanceOf('\stdClass');
        $this->integer($elements[1]->id)->isIdenticalTo(3);
        $this->array($elements[1]->elements)->hasSize(8);

        $this->object($elements[2])->isInstanceOf('\stdClass');
        $this->integer($elements[2]->id)->isIdenticalTo(2);
        $this->array($elements[2]->elements)
            ->hasSize(4)
            ->notHasKey('info_adh');
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

        $admin_login = new \mock\Galette\Core\Login(
            $this->zdb,
            new \Galette\Core\I18n()
        );
        $this->calling($admin_login)->isAdmin = true;

        $elements = $fields_config->getFormElements($admin_login, false);
        $this->array($elements)
            ->hasSize(2)
            ->hasKeys(['fieldsets', 'hiddens']);

        $this->array($elements['fieldsets'])
            ->hasSize(3);

        $this->object($elements['fieldsets'][0])->isInstanceOf('\stdClass');
        $this->integer($elements['fieldsets'][0]->id)->isIdenticalTo(1);
        $this->array($elements['fieldsets'][0]->elements)->hasSize(11);

        $this->object($elements['fieldsets'][1])->isInstanceOf('\stdClass');
        $this->integer($elements['fieldsets'][1]->id)->isIdenticalTo(3);
        $this->array($elements['fieldsets'][1]->elements)->hasSize(8);

        $this->object($elements['fieldsets'][2])->isInstanceOf('\stdClass');
        $this->integer($elements['fieldsets'][2]->id)->isIdenticalTo(2);
        $this->array($elements['fieldsets'][2]->elements)
            ->hasSize(10)
            ->hasKey('info_adh');

        $this->array($elements['hiddens'])
            ->hasSize(2);

        $user_login = new \mock\Galette\Core\Login(
            $this->zdb,
            new \Galette\Core\I18n()
        );
        $this->calling($user_login)->isUp2Date = true;

        $elements = $fields_config->getFormElements($user_login, false);
        $this->array($elements)
            ->hasSize(2)
            ->hasKeys(['fieldsets', 'hiddens']);

        $this->array($elements['fieldsets'])
            ->hasSize(3);

        $this->object($elements['fieldsets'][0])->isInstanceOf('\stdClass');
        $this->integer($elements['fieldsets'][0]->id)->isIdenticalTo(1);
        $this->array($elements['fieldsets'][0]->elements)->hasSize(10);

        $this->object($elements['fieldsets'][1])->isInstanceOf('\stdClass');
        $this->integer($elements['fieldsets'][1]->id)->isIdenticalTo(3);
        $this->array($elements['fieldsets'][1]->elements)->hasSize(8);

        $mail = $elements['fieldsets'][1]->elements['email_adh'];
        $this->boolean($mail->required)->isFalse(); //email is not required per default

        $this->object($elements['fieldsets'][2])->isInstanceOf('\stdClass');
        $this->integer($elements['fieldsets'][2]->id)->isIdenticalTo(2);
        $this->array($elements['fieldsets'][2]->elements)
            ->hasSize(4)
            ->notHasKey('info_adh');

        $login = $elements['fieldsets'][2]->elements['login_adh'];
        $this->boolean($login->required)->isTrue();
        $pass  = $elements['fieldsets'][2]->elements['mdp_adh'];
        $this->boolean($pass->required)->isTrue();

        $this->array($elements['hiddens'])
            ->hasSize(2);

        //form elements for self subscription
        $no_login = new \mock\Galette\Core\Login(
            $this->zdb,
            new \Galette\Core\I18n()
        );
        $elements = $fields_config->getFormElements($no_login, false, true);
        $this->array($elements)
            ->hasSize(2)
            ->hasKeys(['fieldsets', 'hiddens']);

        $this->array($elements['fieldsets'])
            ->hasSize(3);

        $this->object($elements['fieldsets'][0])->isInstanceOf('\stdClass');
        $this->integer($elements['fieldsets'][0]->id)->isIdenticalTo(1);
        $this->array($elements['fieldsets'][0]->elements)->hasSize(10);

        $this->object($elements['fieldsets'][1])->isInstanceOf('\stdClass');
        $this->integer($elements['fieldsets'][1]->id)->isIdenticalTo(3);
        $this->array($elements['fieldsets'][1]->elements)->hasSize(8);

        $mail = $elements['fieldsets'][1]->elements['email_adh'];
        $this->boolean($mail->required)->isTrue(); //email is required for self subscription

        $this->object($elements['fieldsets'][2])->isInstanceOf('\stdClass');
        $this->integer($elements['fieldsets'][2]->id)->isIdenticalTo(2);
        $this->array($elements['fieldsets'][2]->elements)
            ->hasSize(4)
            ->notHasKey('info_adh');

        $this->array($elements['hiddens'])
             ->hasSize(2);
    }
}
