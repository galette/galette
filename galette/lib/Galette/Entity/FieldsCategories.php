<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Fields categories handling
 *
 * PHP version 5
 *
 * Copyright © 2009-2013 The Galette Team
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
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-28
 */

namespace Galette\Entity;

use Analog\Analog as Analog;

/**
 * Fields categories class for galette
 *
 * @category  Entity
 * @name      FieldsCategories
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-28
 */

class FieldsCategories
{
    private $_id;
    private $_category;
    const TABLE = 'fields_categories';
    const PK = 'id_field_category';

    const ADH_CATEGORY_IDENTITY = 1;
    const ADH_CATEGORY_GALETTE = 2;
    const ADH_CATEGORY_CONTACT = 3;

    private static $_defaults = array(
        array(
            'id'         => 1,
            'table_name' => Adherent::TABLE,
            'category'   => 'Identity',
            'position'   => 1
        ),
        array(
            'id'         => 2,
            'table_name' => Adherent::TABLE,
            'category'   => 'Galette-related data',
            'position'   => 2
        ),
        array(
            'id'         => 3,
            'table_name' => Adherent::TABLE,
            'category'   => 'Contact information',
            'position'   => 3
        )
    );

    /**
    * Default constructor
    */
    function __construct()
    {
    }

    /**
    * Get list of categories
    *
    * @return array
    */
    public static function getList()
    {
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . self::TABLE)
                ->order('position');
            return $select->query()->fetchAll();
        } catch (\Exception $e) {
            /** TODO */
            Analog::log(
                '[' . get_class($this) . '] Cannot get fields categories list | ' .
                $e->getMessage(),
                Analog::WARNING
            );
            Analog::log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Set default fields categories at install time
     *
     * @param Db $zdb Database instance
     *
     * @return boolean|Exception
     */
    public function installInit($zdb)
    {
        try {
            //first, we drop all values
            $zdb->db->delete(PREFIX_DB . self::TABLE);

            $stmt = $zdb->db->prepare(
                'INSERT INTO ' . PREFIX_DB . self::TABLE .
                ' (' . self::PK . ', table_name, category, position) ' .
                'VALUES(:id, :table_name, :category, :position)'
            );

            foreach ( self::$_defaults as $d ) {
                $stmt->bindParam(':id', $d['id']);
                $stmt->bindParam(':table_name', $d['table_name']);
                $stmt->bindParam(':category', $d['category']);
                $stmt->bindParam(':position', $d['position']);
                $stmt->execute();
            }

            Analog::log(
                'Default fields configurations were successfully stored.',
                Analog::INFO
            );
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'Unable to initialize default fields configuration.' .
                $e->getMessage(),
                Analog::WARNING
            );
            return $e;
        }
    }

    /**
    * GETTERS
    */

    /**
    * SETTERS
    */
}
