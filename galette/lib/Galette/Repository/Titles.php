<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Titles repository management
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
 * @since     Available since 0.7dev - 2009-03-04
 */

namespace Galette\Repository;

use Galette\Entity\Title as Title;
use Analog\Analog as Analog;

/**
 * Titles repository management
 *
 * @category  Entity
 * @name      Titles
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-04
 */

class Titles
{
    const TABLE = 'titles';
    const PK = 'id_title';

    const MR = 1;
    const MRS = 2;
    const MISS = 3;

    private static $_defaults = array(
        array(
            'id_title'      => 1,
            'short_label'   => 'Mr.',
            'long_label'    => null
        ),
        array(
            'id_title'      => 2,
            'short_label'   => 'Mrs.',
            'long_label'    => null
        )
    );

    /**
     * Get the list of all titles
     *
     * @param Db $zdb Database instance
     *
     * @return array
     */
    public static function getList($zdb)
    {
        $select = new \Zend_Db_Select($zdb->db);
        $select->from(PREFIX_DB . self::TABLE)
            ->order(self::PK);
        $res = $select->query()->fetchAll();

        $pols = array();
        foreach ( $res as $r ) {
            $pk = self::PK;
            $pols[$r->$pk] = new Title($r);
        }
        return $pols;
    }


    /**
     * Set default titles at install time
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
                ' (id_title, short_label, long_label) ' .
                'VALUES(:id, :short, :long)'
            );

            foreach ( self::$_defaults as $d ) {
                $short = _T($d['short_label']);
                $long = null;
                if ( $d['long_label'] !== null ) {
                    $long = _T($d['long_label']);
                } else {
                    $long = new \Zend_Db_Expr('NULL');
                }
                $stmt->bindParam(':id', $d['id_title']);
                $stmt->bindParam(':short', $short);
                $stmt->bindParam(':long', $long);
                $stmt->execute();
            }

            Analog::log(
                'Default titles were successfully stored into database.',
                Analog::INFO
            );
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'Unable to initialize default titles. ' . $e->getMessage(),
                Analog::WARNING
            );
            return $e;
        }
    }

    /**
     * Get the title
     *
     * @param integer $title The title id to retrieve
     *
     * @return translated title short version
     */
    public static function getTitle($title)
    {
        global $zdb;

        $select = new \Zend_Db_Select($zdb->db);
        $select->limit(1)->from(PREFIX_DB . self::TABLE)
            ->where(self::PK . ' = ?', $title);

        $res = $select->query()->fetchColumn(1);
        return _T($res);
    }
}
