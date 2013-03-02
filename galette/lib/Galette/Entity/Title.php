<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Title
 *
 * PHP version 5
 *
 * Copyright © 2013 The Galette Team
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
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.4dev - 2013-01-27
 */

namespace Galette\Entity;

use Analog\Analog as Analog;

/**
 * Title
 *
 * @category  Entity
 * @name      Title
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-04
 */

class Title
{
    const TABLE = 'titles';
    const PK = 'id_title';

    private $_id;
    private $_short;
    private $_long;

    const MR = 1;
    const MRS = 2;
    const MISS = 3;

    /**
     * Main constructor
     *
     * @param mixed $args Arguments
     */
    public function __construct($args = null)
    {
        if ( is_int($args) ) {
            $this->_load($args);
        } else if ( $args !== null && is_object($args) ) {
            $this->_loadFromRs($args);
        }
    }

    /**
     * Load a title from its identifier
     *
     * @param int $id Identifier
     *
     * @return void
     */
    private function _load($id)
    {
        global $zdb;
        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->limit(1)->from(PREFIX_DB . self::TABLE)
                ->where(self::PK . ' = ?', $id);

            $res = $select->query()->fetchAll();
            $this->_id = $id;
            $this->_short = $res[0]->short_label;
            $this->_long = $res[0]->long_label;
        } catch ( \Exception $e ) {
            Analog::log(
                'An error occured loading title #' . $id . "Message:\n" .
                $e->getMessage() . "\nQuery was:\n" . $select->__toString(),
                Analog::ERROR
            );
        }
    }

    /**
     * Load title from a db ResultSet
     *
     * @param ResultSet $rs ResultSet
     *
     * @return void
     */
    private function _loadFromRs($rs)
    {
        $pk = self::PK;
        $this->_id = $rs->$pk;
        $this->_short = $rs->short_label;
        if ( $rs->long_label === 'NULL' ) {
            //mysql's null...
            $this->_long = null;
        } else {
            $this->_long = $rs->long_label;
        }
    }

    /**
     * Store title in database
     *
     * @param Db $zdb Database instance
     *
     * @return boolean
     */
    public function store($zdb)
    {
        $data = array(
            'short_label'   => $this->_short,
            'long_label'    => $this->_long
        );
        try {
            if ( $this->_id !== null && $this->_id > 0 ) {
                $zdb->db->update(
                    PREFIX_DB . self::TABLE,
                    $data,
                    self::PK . '=' . $this->_id
                );
            } else {
                $add = $zdb->db->insert(
                    PREFIX_DB . self::TABLE,
                    $data
                );
                if ( !$add > 0 ) {
                    Analog::log('Not stored!', Analog::ERROR);
                    return false;
                }
            }
            return true;
        } catch ( \Exception $e ) {
            Analog::log(
                'An error occured storing title: ' . $e->getMessage() .
                "\n" . print_r($data, true),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Remove current title
     *
     * @param Db $zdb Database instance
     *
     * @return boolean
     */
    public function remove($zdb)
    {
        $id = (int)$this->_id;
        if ( $id === self::MR || $id === self::MRS ) {
            throw new \RuntimeException(_T("You cannot delete Mr. or Mrs. titles!"));
        }

        try {
            $zdb->db->delete(
                PREFIX_DB . self::TABLE,
                self::PK . ' = ' . $id
            );
            Analog::log(
                'Title #' . $id . ' (' . $this->_short
                . ') deleted successfully.',
                Analog::INFO
            );
            return true;
        } catch (\RuntimeException $re) {
            throw $re;
        } catch (\Exception $e) {
            Analog::log(
                'Unable to delete title ' . $id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Getter
     *
     * @param string $name Property name
     *
     * @return mixed
     */
    public function __get($name)
    {
        global $lang;

        $rname = '_' . $name;
        switch ( $name ) {
        case 'id':
            return $this->$rname;
            break;
        case 'short':
        case 'long':
            if ( $name === 'long'
                && ($this->_long == null || trim($this->_long) === '')
            ) {
                $rname = '_short';
            }
            return $this->$rname;
            break;
        case 'tshort':
        case 'tlong':
            $rname = null;
            if ( $name === 'tshort' ) {
                $rname = '_short';
            } else {
                if ( $this->_long !== null && trim($this->_long) !== '' ) {
                    $rname = '_long';
                } else {
                    //switch back to short version if long does not exists
                    $rname = '_short';
                }
            }
            if ( isset($lang) && isset($lang[$this->$rname])) {
                return _T($this->$rname);
            } else {
                return $this->$rname;
            }
            break;
        default:
            Analog::log(
                'Unable to get Title property ' . $name,
                Analog::WARNING
            );
            break;
        }
    }

    /**
     * Setter
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     *
     * @return void
     */
    public function __set($name, $value)
    {
        $rname = '_' . $name;
        switch ( $name ) {
        case 'short':
        case 'long':
            if ( trim($value) === '' ) {
                Analog::log(
                    'Trying to set empty value for title' . $name,
                    Analog::WARNING
                );
            } else {
                $this->$rname = $value;
            }
            break;
        default:
            Analog::log(
                'Unable to set property ' .$name,
                Analog::WARNING
            );
            break;
        }
    }
}
