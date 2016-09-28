<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Entitleds handling
 *
 * PHP version 5
 *
 * Copyright © 2007-2014 The Galette Team
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
 * @copyright 2007-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-27
 */

namespace Galette\Entity;

use Analog\Analog;
use Zend\Db\Sql\Expression;

/**
 * Entitled handling. Manage:
 *      - id
 *      - label
 *      - extra (that may differ from one entity to another)
 *
 * @category  Entity
 * @name      Entitled
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-27
 */

abstract class Entitled
{
    const ID_NOT_EXITS = -1;

    private $_table;
    private $_fpk;
    private $_flabel;
    private $_fthird;
    private $_used;

    protected static $fields;
    protected static $defaults;

    protected $order_field = false;

    private $_id;
    private $_label;
    private $_third;

    private $_errors = array();

    /**
     * Default constructor
     *
     * @param string $table  Table name
     * @param string $fpk    Primary key field name
     * @param string $flabel Label fields name
     * @param string $fthird The third field name
     * @param string $used   Table name for isUsed function
     * @param mixed  $args   Either an int or a resultset to load
     */
    public function __construct($table, $fpk, $flabel, $fthird, $used, $args = null)
    {
        $this->_table = $table;
        $this->_fpk = $fpk;
        $this->_flabel = $flabel;
        $this->_fthird = $fthird;
        $this->_used = $used;
        if ( is_int($args) ) {
            $this->load($args);
        } else if ( is_object($args) ) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Loads an entry from its id
     *
     * @param int $id Entry ID
     *
     * @return boolean true if query succeed, false otherwise
     */
    public function load($id)
    {
        global $zdb;

        try {
            $select = $zdb->select($this->_table);
            $select->where($this->_fpk . ' = ' . $id);

            $results = $zdb->execute($select);
            $result = $results->current();
            $this->_loadFromRS($result);

            return true;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot load ' . $this->getType()  . ' from id `' . $id . '` | ' .
                $e->getMessage(),
                Analog::WARNING
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
        $pk = $this->_fpk;
        $this->_id = $r->$pk;
        $flabel = $this->_flabel;
        $this->_libelle = $r->$flabel;
        $fthird = $this->_fthird;
        $this->_third = $r->$fthird;
    }

    /**
     * Set defaults at install time
     *
     * @return boolean|Exception
     */
    public function installInit()
    {
        global $zdb;

        $class = get_class($this);

        try {
            //first, we drop all values
            $delete = $zdb->delete($this->_table);
            $zdb->execute($delete);

            $values = array();
            $i = 0;
            foreach ( $class::$fields as $f ) {
                $v = null;
                if ( $i === 0 ) {
                    $v = ':id';
                } else if ( $i === 1 ) {
                    $v = ':libelle';
                } else if ( $i === 2 ) {
                    $v = ':third';
                }
                $values[$f] = $v;
                $i++;
            }

            $insert = $zdb->insert($this->_table);
            $insert->values($values);
            $stmt = $zdb->sql->prepareStatementForSqlObject($insert);

            $fnames = array_keys($values);
            foreach ( $class::$defaults as $d ) {
                $val = null;
                if ( isset($d['priority']) ) {
                    $val = $d['priority'];
                } else {
                    $val = $d['extension'];
                }

                $stmt->execute(
                    array(
                        $fnames[0]  => $d['id'],
                        $fnames[1]  => $d['libelle'],
                        $fnames[2]  => $val
                    )
                );
            }

            Analog::log(
                'Defaults (' . $this->getType()  .
                ') were successfully stored into database.',
                Analog::INFO
            );
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'Unable to initialize defaults (' . $this->getType()  . ').' .
                $e->getMessage(),
                Analog::WARNING
            );
            return $e;
        }
    }

    /**
     * Get list in an array built as:
     * $array[id] = "translated label"
     *
     * @param int $extent Filter on (non) cotisations types
     *
     * @return array|false
     */
    public function getList($extent = null)
    {
        global $zdb;
        $list = array();

        try {
            $select = $zdb->select($this->_table);
            $fields = array($this->_fpk, $this->_flabel);
            if ( $this->order_field !== false
                && $this->order_field !== $this->_fpk
                && $this->order_field !== $this->_flabel
            ) {
                $fields[] = $this->order_field;
            }
            $select->quantifier('DISTINCT');
            $select->columns($fields);

            if ( $this->order_field !== false ) {
                $select->order($this->order_field, $this->_fpk);
            }
            if ( $extent !== null ) {
                if ( $extent === true ) {
                    $select->where(array($this->_fthird => new Expression('true')));
                } else if ( $extent === false ) {
                    $select->where(array($this->_fthird => new Expression('false')));
                }
            }

            $results = $zdb->execute($select);

            foreach ( $results as $r ) {
                $fpk = $this->_fpk;
                $flabel = $this->_flabel;
                $list[$r->$fpk] = _T($r->$flabel);
            }
            return $list;
        } catch (\Exception $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Complete list
     *
     * @return array of all objects if succeed, false otherwise
     */
    public function getCompleteList()
    {
        global $zdb;
        $list = array();

        try {
            $select = $zdb->select($this->_table);
            if ( $this->order_field !== false ) {
                $select->order(array($this->order_field, $this->_fpk));
            }

            $results = $zdb->execute($select);

            if ( $results->count() == 0 ) {
                Analog::log(
                    'No entries (' . $this->getType()  . ') defined in database.',
                    Analog::INFO
                );
            } else {
                $pk = $this->_fpk;
                $flabel = $this->_flabel;
                $fprio = $this->_fthird;

                foreach ( $results as $r ) {
                    $list[$r->$pk] = array(
                        'name'  => _T($r->$flabel),
                        'extra' => $r->$fprio
                    );
                }
            }
            return $list;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list entries (' . $this->getType() . 
                ') | ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Get a entry
     *
     * @param integer $id Entry ID
     *
     * @return mixed|false Row if succeed ; false: no such id
     */
    public function get($id)
    {

        if ( !is_numeric($id) ) {
            $this->_errors[] = _T("- ID must be an integer!");
            return false;
        }

        global $zdb;

        try {
            $select = $zdb->select($this->_table);
            $select->where($this->_fpk . '=' . $id);

            $results = $zdb->execute($select);
            $result = $results->current();

            if ( !$result ) {
                $this->_errors[] = _T("- Label does not exist");
                return false;
            }

            return $result;
        } catch (\Exception $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Get a label
     *
     * @param integer $id         Id
     * @param boolean $translated Do we want translated or original label?
     *                            Defaults to true.
     *
     * @return string
     */
    public function getLabel($id, $translated = true)
    {
        $res = $this->get($id);
        if ( $res === false ) {
            //get() alred logged
            return self::ID_NOT_EXITS;
        };
        $field = $this->_flabel;
        return ($translated) ? _T($res->$field) : $res->$field;
    }

    /**
     * Get an ID from a label
     *
     * @param string $label The label
     *
     * @return int|false Return id if it exists false otherwise
     */
    public function getIdByLabel($label)
    {
        global $zdb;

        try {
            $pk = $this->_fpk;
            $select = $zdb->select($this->_table);
            $select->columns(array($pk))
                ->where(array($this->_flabel => $label));

            $results = $zdb->execute($select);
            $result = $results->current();
            if ( $result ) {
                return $result->$pk;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            Analog::log(
                'Unable to retrieve ' . $this->getType()  . ' from label `' .
                $label . '` | ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Add a new entry
     *
     * @param string  $label The label
     * @param integer $extra Extra values (priority for statuses,
     *                       extension for contributions types, ...)
     *
     * @return integer id if success ; -1 : DB error ; -2 : label already exists
     */
    public function add($label, $extra)
    {
        global $zdb;

        // Avoid duplicates.
        $ret = $this->getIdByLabel($label);

        if ( $ret !== false ) {
            Analog::log(
                $this->getType() . ' with label `' . $label . '` already exists',
                Analog::WARNING
            );
            return -2;
        }

        try {
            $values = array(
                $this->_flabel  => $label,
                $this->_fthird  => $extra
            );

            $insert = $zdb->insert($this->_table);
            $insert->values($values);

            $ret = $zdb->execute($insert);

            if ( $ret->count() >  0) {
                Analog::log(
                    'New ' . $this->getType() .' `' . $label .
                    '` added successfully.',
                    Analog::INFO
                );

                if ( $zdb->isPostgres() ) {
                    return $zdb->driver->getLastGeneratedValue(
                        PREFIX_DB . $this->_table . '_id_seq'
                    );
                } else {
                    return $zdb->driver->getLastGeneratedValue();
                }


                return $zdb->driver->getLastGeneratedValue();
            } else {
                throw new \Exception('New ' . $this->getType() .' not added.');
            }
        } catch (\Exception $e) {
            Analog::log(
                'Unable to add new entry `' . $label . '` | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Update in database.
     *
     * @param integer $id    Entry ID
     * @param string  $field Field to update
     * @param mixed   $value The value to set
     *
     * @return integer -2 : ID does not exist ; -1 : DB error ; 0 : success.
     */
    public function update($id, $field, $value)
    {
        global $zdb;

        $ret = $this->get($id);
        if ( !$ret ) {
            /* get() already logged and set $this->error. */
            return self::ID_NOT_EXITS;
        }

        $fieldtype = '';
        if ( $field == self::$fields[1] ) {
            // label.
            $fieldtype = 'text';
        } elseif ( self::$fields[2] ) {
            // priority.
            $fieldtype = 'integer';
        }

        Analog::log(
            "Setting field $field to $value for " . $this->getType()  . " $id",
            Analog::INFO
        );

        try {
            $values= array(
                $field => $value
            );

            $update = $zdb->update($this->_table);
            $update->set($values);
            $update->where($this->_fpk . ' = ' . $id);

            $zdb->execute($update);

            Analog::log(
                $this->getType() . ' ' . $id . ' updated successfully.',
                Analog::INFO
            );
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'Unable to update ' . $this->getType()  . ' ' . $id .
                ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Delete entry
     *
     * @param integer $id Entry ID
     *
     * @return integer -2 : ID does not exist ; -1 : DB error ; 0 : success.
     */
    public function delete($id)
    {
        global $zdb;

        $ret = $this->get($id);
        if ( !$ret ) {
            /* get() already logged */
            return self::ID_NOT_EXITS;
        }

        $ret = $this->isUsed($id);
        if ( $ret === true ) {
            $this->_errors[] = _T("- Cannot delete this label: it's still used");
            return false;
        }

        try {
            $delete = $zdb->delete($this->_table);
            $delete->where($this->_fpk . ' = ' . $id);

            $zdb->execute($delete);

            Analog::log(
                $this->getType() . ' ' . $id . ' deleted successfully.',
                Analog::INFO
            );
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'Unable to delete ' . $this->getType()  . ' ' . $id .
                ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Check whether this entry is used.
     *
     * @param integer $id Entry ID
     *
     * @return boolean
     */
    public function isUsed($id)
    {
        global $zdb;

        // Check if it's used.
        try {
            $select = $zdb->select($this->_used);
            $select->where($this->_fpk . ' = ' . $id);

            $results = $zdb->execute($select);
            $result = $results->current();

            if ( $result !== false ) {
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            Analog::log(
                'Unable to check if ' . $this->getType  . ' `' . $id .
                '` is used. | ' . $e->getMessage(),
                Analog::ERROR
            );
            //in case of error, we consider that it is used, to avoid errors
            return true;
        }
    }

    /**
     * Get textual type representation
     *
     * @return string
     */
    protected abstract function getType();

    /**
     * Get translated textual representation
     *
     * @return string
     */
    protected abstract function getI18nType();

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
        $virtuals = array('extension');
        $rname = '_' . $name;
        if ( in_array($name, $virtuals)
            || !in_array($name, $forbidden)
            && isset($this->$rname)
        ) {
            switch($name) {
            case 'libelle':
                return _T($this->_libelle);
                break;
            case 'extension':
                return $this->_third;
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

