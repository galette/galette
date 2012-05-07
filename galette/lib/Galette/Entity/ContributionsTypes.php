<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contributions types handling
 *
 * PHP version 5
 *
 * Copyright © 2007-2012 The Galette Team
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
 * @category  Classes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-27
 */

namespace Galette\Entity;

use Galette\Common\KLogger as KLogger;

/* TODO: Most of the code is duplicated in Galette\Entity\Status. Should
 * probably use a superclass for genericity.
 */

/**
 * Contributions types handling
 *
 * @category  Classes
 * @name      ContibutionTypes
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-27
 */

class ContributionsTypes
{
    const TABLE = 'types_cotisation';
    const PK = 'id_type_cotis';

    const ID_NOT_EXITS = -1;

    private $_id;
    private $_libelle;
    private $_extension;

    private static $_fields = array(
        'id_type_cotis',
        'libelle_type_cotis',
        'cotis_extension'
    );

    private static $_defaults = array(
        array('id' => 1, 'libelle' => 'annual fee', 'extension' => '1'),
        array('id' => 2, 'libelle' => 'reduced annual fee', 'extension' => '1'),
        array('id' => 3, 'libelle' => 'company fee', 'extension' => '1'),
        array('id' => 4, 'libelle' => 'donation in kind', 'extension' => 0),
        array('id' => 5, 'libelle' => 'donation in money', 'extension' => 0),
        array('id' => 6, 'libelle' => 'partnership', 'extension' => 0),
        array('id' => 7, 'libelle' => 'annual fee (to be paid)', 'extension' => '1')
    );

    /**
    * Default constructor
    *
    * @param ResultSet $args Optionnal existing result set
    */
    public function __construct($args = null)
    {
        if ( is_int($args) ) {
            $this->load($args);
        } else if ( is_object($args) ) {
            $this->_loadFromRS($args);
        }
    }

    /**
    * Loads a contribution type from its id
    *
    * @param int $id the identifiant to load
    *
    * @return boolean true if query succeed, false otherwise
    */
    public function load($id)
    {
        global $zdb, $log;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . self::TABLE)
                ->where(self::PK . ' = ?', $id);

            $result = $select->query();
            $this->_loadFromRS($result->fetch());

            return true;
        } catch (\Exception $e) {
            /** TODO */
            $log->log(
                'Cannot load contribution type form id `' . $id . '` | ' .
                $e->getMessage(),
                KLogger::WARN
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                KLogger::ERR
            );
            return false;
        }
    }

    /**
    * Populate object from a resultset row
    *
    * @param ResultSet $r the resultset row
    *
    * @return void
    */
    private function _loadFromRS($r)
    {
        $pk = self::PK;
        $this->_id = $r->$pk;
        $this->_libelle = $r->libelle_type_cotis;
        $this->_extension = $r->cotis_extension;
    }

    /**
    * Set default contribution types at install time
    *
    * @return boolean|Exception
    */
    public function installInit()
    {
        global $zdb, $log;

        try {
            //first, we drop all values
            $zdb->db->delete(PREFIX_DB . self::TABLE);

            $stmt = $zdb->db->prepare(
                'INSERT INTO ' . PREFIX_DB . self::TABLE .
                ' (id_type_cotis, libelle_type_cotis, cotis_extension) ' .
                'VALUES(:id, :libelle, :extension)'
            );

            foreach ( self::$_defaults as $d ) {
                $stmt->bindParam(':id', $d['id']);
                $stmt->bindParam(':libelle', $d['libelle']);
                $stmt->bindParam(':extension', $d['extension'], \PDO::PARAM_INT);
                $stmt->execute();
            }

            $log->log(
                'Default contributions types were successfully stored into database.',
                KLogger::INFO
            );
            return true;
        } catch (\Exception $e) {
            $log->log(
                'Unable to initialize default contributions types.' .
                $e->getMessage(),
                KLogger::WARN
            );
            return $e;
        }
    }

    /**
     * Returns the list of statuses, in an array built as :
     * $array[id] = "translated label status"
     *
     * @param int $extent Filter on (non) cotisations types
     *
     * @return array|false
     */
    public static function getList($extent = null)
    {
        global $zdb, $log;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->distinct()->from(
                PREFIX_DB . self::TABLE,
                array(self::PK, 'libelle_type_cotis')
            )->order('libelle_type_cotis');
            if ( $extent !== null ) {
                if ( $extent === true ) {
                    $select->where('cotis_extension = ?', $extent);
                } else if ( $extent === false ) {
                    $select->where('cotis_extension = false');
                }
            }
            $result = $select->query()->fetchAll();
            foreach ( $result as $r ) {
                $list[$r->id_type_cotis] = _T($r->libelle_type_cotis);
            }
            return $list;
        } catch (\Exception $e) {
            $log->log(
                'An error occured. ' . $e->getMessage(),
                KLogger::ERR
            );
            $log->log(
                'Query was: ' . $select->__toString(),
                KLogger::DEBUG
            );
            return false;
        }
    }

    /** TODO: replace with a static function ? */
    /**
    * Complete list of contributions types
    *
    * @return array of all contributions if succeed, false otherwise
    */
    public function getCompleteList()
    {
        global $zdb, $log;
        $list = array();

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . self::TABLE)
                ->order(self::PK);

            $types = $select->query()->fetchAll();

            if ( count($types) == 0 ) {
                $log->log(
                    'No contributions types defined in database.',
                    KLogger::INFO
                );
            } else {
                foreach ( $types as $type ) {
                    $list[$type->id_type_cotis] = array(
                        _T($type->libelle_type_cotis),
                        $type->cotis_extension
                    );
                }
            }
            return $list;
        } catch (\Exception $e) {
            /** TODO */
            $log->log(
                'Cannot list contribution types | ' . $e->getMessage(),
                KLogger::WARN
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                KLogger::ERR
            );
            return false;
        }
    }

    /**
    * Get a contribution type.
    *
    * @param integer $id Contribution's id
    *
    * @return mixed|false Row if succeed ; false : no such id
    */
    public function get($id)
    {
        global $zdb, $log;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(array(PREFIX_DB . self::TABLE));
            $select->where(self::PK . '=' . $id);

            $result = $select->query()->fetch();

            return $result;
        } catch (\Exception $e) {
            /** TODO */
            $log->log(
                __METHOD__ . ' | ' . $e->getMessage(),
                KLogger::WARN
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                KLogger::ERR
            );
            return false;
        }
    }

    /**
    * Get a label.
    *
    * @param integer $id Status' id
    *
    * @return string
    */
    public function getLabel($id)
    {
        $res = self::get($id);
        return ($translated) ? _T($res->libelle_type_cotis) : $res->libelle_type_cotis;
    }

    /**
    * Get a contribution type ID from a label.
    *
    * @param string $label The label
    *
    * @return int|false Return id if it exists false otherwise
    */
    public function getIdByLabel($label)
    {
        global $zdb, $log;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . self::TABLE, self::PK)
                ->where('libelle_type_cotis = ?', $label);
            return $result = $select->query()->fetchColumn();
        } catch (\Exception $e) {
            /** FIXME */
            $log->log(
                'Unable to retrieve contributions type from label `' .
                $label . '` | ' . $e->getMessage(),
                KLogger::ERR
            );
            return false;
        }
    }

    /**
    * Add a new contribution type.
    *
    * @param string  $label     The label
    * @param boolean $extension Contribution extension
    *
    * @return integer id if success ; -1 : DB error ; -2 : label already exists
    */
    public function add($label, $extension)
    {
        global $zdb, $log;

        // Avoid duplicates.
        $ret = $this->getidByLabel($label);

        if ( $ret !== false ) {
            $log->log(
                'Contribution type `' . $label . '` already exists',
                KLogger::WARN
            );
            return -2;
        }

        try {
            $values = array(
                'libelle_type_cotis'  => $label,
                'cotis_extension' => ($extension == 1) ? true : 'false'
            );

            $ret = $zdb->db->insert(
                PREFIX_DB . self::TABLE,
                $values
            );

            if ( $ret >  0) {
                $log->log(
                    'New contributions type `' . $label . '` added successfully.',
                    KLogger::INFO
                );
                return $zdb->db->lastInsertId(
                    PREFIX_DB . self::TABLE,
                    'id'
                );
            } else {
                throw new \Exception('New contributions type not added.');
            }
        } catch (\Exception $e) {
            /** FIXME */
            $log->log(
                'Unable to add new contributions type `' . $label . '` | ' .
                $e->getMessage(),
                KLogger::ERR
            );
            return false;
        }
    }

    /**
    * Update a contribution type.
    *
    * @param integer $id    Contribution's id
    * @param string  $field Field to update
    * @param mixed   $value The value to set
    *
    * @return integer -2 : ID does not exist ; -1 : DB error ; 0 : success.
    */
    public function update($id, $field, $value)
    {
        global $zdb, $log;

        $ret = $this->get($id);
        if ( !$ret ) {
            /* get() already logged and set $this->error. */
            return self::ID_NOT_EXITS;
        }

        $fieldtype = '';
        if ( $field == self::$_fields[1] ) {
            // label.
            $fieldtype = 'text';
        } elseif ( self::$_fields[2] ) {
            // membership extension.
            $fieldtype = 'integer';
        }

        $log->log("Setting field $field to $value for ctype $id", KLogger::INFO);

        try {
            $values= array(
                $field => $value
            );

            $zdb->db->update(
                PREFIX_DB . self::TABLE,
                $values,
                self::PK . ' = ' . $id
            );

            $log->log(
                'Contributions type ' . $id . ' updated successfully.',
                KLogger::INFO
            );
            return true;
        } catch (\Exception $e) {
            /** FIXME */
            $log->log(
                'Unable to update contributions types ' . $id . ' | ' .
                $e->getMessage(),
                KLogger::ERR
            );
            return false;
        }
    }

    /**
    * Delete a contribution type.
    *
    * @param integer $id Contribution's id
    *
    * @return integer -2 : ID does not exist ; -1 : DB error ; 0 : success.
    */
    public function delete($id)
    {
        global $zdb, $log;

        $ret = $this->get($id);
        if ( !$ret ) {
            /* get() already logged */
            return self::ID_NOT_EXITS;
        }

        try {
            $zdb->db->delete(
                PREFIX_DB . self::TABLE,
                self::PK . ' = ' . $id
            );
            $log->log(
                'Contributions type ' . $id . ' deleted successfully.',
                KLogger::INFO
            );
            return true;
        } catch (\Exception $e) {
            /** FIXME */
            $log->log(
                'Unable to delete contributions type ' . $id . ' | ' .
                $e->getMessage(),
                KLogger::ERR
            );
            return false;
        }
    }

    /**
    * Check whether this contribution type is used.
    *
    * @param integer $id Contribution's id
    *
    * @return integer -1 : DB error ; 0 : not used ; 1 : used.
    */
    public function isUsed($id)
    {
        global $zdb, $log;

        // Check if it's used.
        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . \Contribution::TABLE)
                ->where(self::PK . ' = ?', $id);
            if ( $select->query()->fetch() !== false ) {
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            /** FIXME */
            $log->log(
                'Unable to check if contribution `' . $id . '` is used. | ' .
                $e->getMessage(),
                KLogger::ERR
            );
            //in case of error, we consider that type is used, to avoid errors
            return true;
        }
    }

    /**
    * Global getter method
    *
    * @param string $name name of the property we want to retrive
    *
    * @return false|object the called property
    */
    public function __get($name)
    {
        $forbidden = array();
        $rname = '_' . $name;
        if ( !in_array($name, $forbidden) && isset($this->$rname)) {
            switch($name) {
            case 'libelle':
                return _T($this->_libelle);
                break;
            default:
                return $this->$rname;
                break;
            }
        } else {
            return false;
        }
    }

}
?>
