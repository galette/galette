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

        $visibles = $fields_config->getVisibilities();
        $this->array($visibles)
            ->hasSize(count($categorized[1]) + count($categorized[2]) + count($categorized[3]))
            ->integer['id_adh']->isIdenticalTo(0)
            ->integer['nom_adh']->isIdenticalTo(1);
    }
}
