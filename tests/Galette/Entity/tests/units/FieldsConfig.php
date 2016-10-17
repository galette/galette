<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * FieldsConfig tests
 *
 * PHP version 5
 *
 * Copyright © 2013-2014 The Galette Team
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
 * @copyright 2016 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2016-09-24
 */

namespace Galette\Entity\test\units;

use \atoum;

/**
 * Preferences tests class
 *
 * @category  Entity
 * @name      FieldsConfig
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2016 The Galette Team
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
     * @param string $testMethod Calling method
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
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
            'sexe_adh'
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

        $this->array($categorized)
            ->hasSize(3);
        $this->array($categorized[1])
            ->hasSize(12);
        $this->array($categorized[2])
            ->hasSize(11);
        $this->array($categorized[3])
            ->hasSize(15);

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


        $visibles = $fields_config->getVisibilities();
        $this->array($visibles)
            ->hasSize(count($categorized[1]) + count($categorized[2]) + count($categorized[3]))
            ->integer['id_adh']->isIdenticalTo(0)
            ->integer['nom_adh']->isIdenticalTo(1);
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

        $required_mdp = $fields_config->getCategorizedFields()[2][6]['required'];
        $this->boolean($required_mdp)->isTrue();

        $fields_config->setNotRequired('mdp_adh');

        $required_mdp = $fields_config->getRequired();
        $this->array($required_mdp)->notHasKey('mdp_adh');

        $required_mdp = $fields_config->getCategorizedFields()[2][6]['required'];
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
        $this->integer($visible)->isIdenticalTo(\Galette\Entity\FieldsConfig::VISIBLE);

        $visible = $this->fields_config->getVisibility('id_adh');
        $this->integer($visible)->isIdenticalTo(\Galette\Entity\FieldsConfig::HIDDEN);

        $visible = $this->fields_config->getVisibility('info_adh');
        $this->integer($visible)->isIdenticalTo(\Galette\Entity\FieldsConfig::ADMIN);
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
        $town = &$fields[3][3];
        $this->boolean($town['required'])->isTrue();
        $this->integer($town['visible'])->isIdenticalTo(\Galette\Entity\FieldsConfig::VISIBLE);

        $town['required'] = false;
        $town['visible'] = \Galette\Entity\FieldsConfig::HIDDEN;

        //jabber
        $jabber = $fields[3][10];
        unset($fields[3][10]);
        $jabber['category'] = 1;
        $fields[1][] = $jabber;

        $fields_config->setFields($fields);

        $fields_config->load();
        $fields = $fields_config->getCategorizedFields();

        $town = $fields[3][3];
        $this->boolean($town['required'])->isFalse();
        $this->integer($town['visible'])->isIdenticalTo(\Galette\Entity\FieldsConfig::HIDDEN);

        $jabber2 = $fields[1][12];
        $this->array($jabber2)->isIdenticalTo($jabber);
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
}
