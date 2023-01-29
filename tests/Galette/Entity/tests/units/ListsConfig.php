<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * ListsConfig tests
 *
 * PHP version 5
 *
 * Copyright © 2020-2023 The Galette Team
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
 * @copyright 2020-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2020-05-16
 */

namespace Galette\Entity\test\units;

use atoum;

/**
 * ListsConfig tests class
 *
 * @category  Entity
 * @name      ListsConfig
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-05-16
 */
class ListsConfig extends atoum
{
    private ?\Galette\Entity\ListsConfig $lists_config = null;
    private \Galette\Core\Db $zdb;
    private array $members_fields;
    private array $members_fields_cats;
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

        $this->lists_config = new \Galette\Entity\ListsConfig(
            $this->zdb,
            \Galette\Entity\Adherent::TABLE,
            $this->members_fields,
            $this->members_fields_cats,
            true
        );
    }

    /**
     * Tear down tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function afterTestMethod($method)
    {
        if (TYPE_DB === 'mysql') {
            $this->array($this->zdb->getWarnings())->isIdenticalTo([]);
        }
        $this->resetListsConfig();
    }

    /**
     * Resets lists configuration to defaults
     *
     * @return void
     */
    private function resetListsConfig()
    {
        $new_list = [];
        foreach ($this->default_lists as $key) {
            $new_list[] = $this->lists_config->getField($key);
        }

        $this->boolean($this->lists_config->setListFields($new_list))->isTrue();
    }

    /**
     * Test getVisibility
     *
     * @return void
     */
    public function testGetVisibility()
    {
        $this->lists_config->load();

        $visible = $this->lists_config->getVisibility('nom_adh');
        $this->integer($visible)->isIdenticalTo(\Galette\Entity\FieldsConfig::NOBODY);

        //must be the same than nom_adh
        $visible = $this->lists_config->getVisibility('list_adh_name');
        $this->integer($visible)->isIdenticalTo(\Galette\Entity\FieldsConfig::USER_WRITE);

        $visible = $this->lists_config->getVisibility('id_statut');
        $this->integer($visible)->isIdenticalTo(\Galette\Entity\FieldsConfig::STAFF);

        //must be the same than id_statut
        $visible = $this->lists_config->getVisibility('list_adh_contribstatus');
        $this->integer($visible)->isIdenticalTo(\Galette\Entity\FieldsConfig::STAFF);
    }

    /**
     * Test setFields and storage
     *
     * @return void
     */
    public function testSetFields()
    {
        $lists_config = $this->lists_config;
        $lists_config->installInit();
        $lists_config->load();

        $fields = $lists_config->getCategorizedFields();

        $list = $lists_config->getListedFields();
        $this->array($list)->hasSize(6);

        $expecteds = $this->default_lists;
        foreach ($expecteds as $k => $expected) {
            $this->string($list[$k]['field_id'])->isIdenticalTo($expected);
            $this->integer($list[$k]['list_position'])->isIdenticalTo($k);
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
        $this->boolean($lists_config->setListFields($new_list))->isTrue();

        $list = $lists_config->getListedFields();
        $this->array($list)->hasSize(7);

        foreach ($expecteds as $k => $expected) {
            $this->string($list[$k]['field_id'])->isIdenticalTo($expected);
            $this->integer($list[$k]['list_position'])->isIdenticalTo($k);
        }

        $field = $lists_config->getField('pseudo_adh');
        $this->integer($field['list_position'])->isIdenticalTo(-1);
        $this->boolean($field['list_visible'])->isFalse();

        $field = $lists_config->getField('date_modif_adh');
        $this->integer($field['list_position'])->isIdenticalTo(-1);
        $this->boolean($field['list_visible'])->isFalse();

        // copied from FieldsConfig::testSetFields to ensure it works as excpeted from here.
        //town
        $town = &$fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT][2]; //3 in FieldsConfig but 2 here.
        $this->string($town['field_id'])->isIdenticalTo('ville_adh');
        $this->boolean($town['required'])->isTrue();
        $this->integer($town['visible'])->isIdenticalTo(\Galette\Entity\FieldsConfig::USER_WRITE);

        $town['required'] = false;
        $town['visible'] = \Galette\Entity\FieldsConfig::NOBODY;

        //gsm
        $gsm = $fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT][5]; //6 in FieldsConfig but 5 here.
        $gsm['position'] = count($fields[1]);
        unset($fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT][5]); //6 in FieldsConfig but 5 here.
        $gsm['category'] = \Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY;
        $fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY][] = $gsm;

        $this->boolean($lists_config->setFields($fields))->isTrue();

        $lists_config->load();
        $fields = $lists_config->getCategorizedFields();

        $town = $fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_CONTACT][2]; //3 in FieldsConfig but 2 here.
        $this->boolean($town['required'])->isFalse();
        $this->integer($town['visible'])->isIdenticalTo(\Galette\Entity\FieldsConfig::NOBODY);

        $gsm2 = $fields[\Galette\Entity\FieldsCategories::ADH_CATEGORY_IDENTITY][11]; //13 in FieldsConfig but 11 here
        $this->array($gsm2)->isIdenticalTo($gsm);
        // /copied from FieldsConfig::testSetFields to ensure it works as expected from here.
    }

    /**
     * Test get display elements
     *
     * @return void
     */
    public function testGetDisplayElements()
    {
        $lists_config = $this->lists_config;
        $lists_config->load();

        //admin
        $superadmin_login = new \mock\Galette\Core\Login(
            $this->zdb,
            new \Galette\Core\I18n()
        );
        $this->calling($superadmin_login)->isSuperAdmin = true;

        $expecteds = $this->default_lists;
        $elements = $lists_config->getDisplayElements($superadmin_login);
        $this->array($elements)
            ->hasSize(count($this->default_lists));

        //admin
        $admin_login = new \mock\Galette\Core\Login(
            $this->zdb,
            new \Galette\Core\I18n()
        );
        $this->calling($admin_login)->isAdmin = true;

        $expecteds = $this->default_lists;
        $elements = $lists_config->getDisplayElements($admin_login);
        $this->array($elements)
            ->hasSize(count($this->default_lists));

        //staff
        $staff_login = new \mock\Galette\Core\Login(
            $this->zdb,
            new \Galette\Core\I18n()
        );
        $this->calling($staff_login)->isStaff = true;

        $expecteds = $this->default_lists;
        $elements = $lists_config->getDisplayElements($staff_login);
        $this->array($elements)
             ->hasSize(count($this->default_lists));

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
        $manager_login = new \mock\Galette\Core\Login(
            $this->zdb,
            new \Galette\Core\I18n()
        );
        $this->calling($manager_login)->isGroupManager = true;

        $elements = $lists_config->getDisplayElements($manager_login);
        $this->array($elements)
            ->hasSize(count($new_list));

        //to keep last know rank. May switch from 2 to 6 because of bield visibility.
        $last_ok = -1;
        foreach ($expecteds as $k => $expected) {
            $this->string($new_list[$k]['field_id'])->isIdenticalTo($expected);
            if ($new_list[$k]['list_position'] != $k - 1) {
                $this->integer($new_list[$k]['list_position'])->isGreaterThan($last_ok);
                $last_ok = $new_list[$k]['list_position'];
            } else {
                $this->integer($new_list[$k]['list_position'])->isIdenticalTo($k);
            }
        }

        //simplemember
        $user_login = new \mock\Galette\Core\Login(
            $this->zdb,
            new \Galette\Core\I18n()
        );
        $this->calling($user_login)->isUp2Date = true;

        $elements = $lists_config->getDisplayElements($user_login);
        $this->array($elements)
            ->hasSize(count($new_list));

        //to keep last know rank. May switch from 2 to 6 because of bield visibility.
        $last_ok = -1;
        foreach ($expecteds as $k => $expected) {
            $this->string($new_list[$k]['field_id'])->isIdenticalTo($expected);
            if ($new_list[$k]['list_position'] != $k - 1) {
                $this->integer($new_list[$k]['list_position'])->isGreaterThan($last_ok);
                $last_ok = $new_list[$k]['list_position'];
            } else {
                $this->integer($new_list[$k]['list_position'])->isIdenticalTo($k);
            }
        }
    }
}
