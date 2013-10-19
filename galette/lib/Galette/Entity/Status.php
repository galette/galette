<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Statuses handling
 *
 * PHP version 5
 *
 * Copyright © 2007-2013 The Galette Team
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
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-27
 */

namespace Galette\Entity;

/**
 * Members status
 *
 * @category  Entity
 * @name      Status
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-27
 */
class Status extends Entitled
{
    const DEFAULT_STATUS = 9;
    const TABLE = 'statuts';
    const PK = 'id_statut';
    const LABEL_FIELD = 'libelle_statut';
    const ORDER_FIELD = 'priorite_statut';

    const ID_NOT_EXITS = -1;

    protected static $fields = array(
        'id_statut',
        'libelle_statut',
        'priorite_statut'
    );

    protected static $defaults = array(
        array('id' => 1, 'libelle' => 'President', 'priority' => 0),
        array('id' => 2, 'libelle' => 'Treasurer', 'priority' => 10),
        array('id' => 3, 'libelle' => 'Secretary', 'priority' => 20),
        array('id' => 4, 'libelle' => 'Active member', 'priority' => 30),
        array('id' => 5, 'libelle' => 'Benefactor member', 'priority' => 40),
        array('id' => 6, 'libelle' => 'Founder member', 'priority' => 50),
        array('id' => 7, 'libelle' => 'Old-timer', 'priority' => 60),
        array('id' => 8, 'libelle' => 'Society', 'priority' => 70),
        array('id' => 9, 'libelle' => 'Non-member', 'priority' => 80),
        array('id' => 10, 'libelle' => 'Vice-president', 'priority' => 5)
    );

    /**
     * Default constructor
     *
     * @param ResultSet $args Optionnal existing result set
     */
    public function __construct($args = null)
    {
        parent::__construct(
            self::TABLE,
            self::PK,
            self::LABEL_FIELD,
            self::ORDER_FIELD,
            Adherent::TABLE,
            $args
        );
        $this->order_field = self::ORDER_FIELD;
    }

    /**
     * Get textual type representation
     *
     * @return string
     */
    protected function getType()
    {
        return 'status';
    }

    /**
     * Get translated textual representation
     *
     * @return string
     */
    protected function getI18nType()
    {
        return _T("status");
    }

    /**
     * Delete a status.
     *
     * @param integer $id Status id
     *
     * @return integer -2 : ID does not exist ; -1 : DB error ; 0 : success.
     */
    public function delete($id)
    {
        global $zdb;

        if ( (int)$id === self::DEFAULT_STATUS ) {
            throw new \RuntimeException(_T("You cannot delete default status!"));
        }

        parent::delete($id);
    }

}
