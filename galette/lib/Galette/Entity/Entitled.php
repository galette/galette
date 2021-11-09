<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Entitleds handling
 *
 * PHP version 5
 *
 * Copyright © 2007-2021 The Galette Team
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
 * @copyright 2007-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-27
 */

namespace Galette\Entity;

use Throwable;
use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Galette\Core\Db;
use Galette\Features\I18n;

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
 * @copyright 2007-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-27
 *
 * @property integer $id
 * @property string $label
 * @property string $libelle
 * @property mixed $third
 * @property mixed $extension
 */

abstract class Entitled
{
    use I18n;

    public const ID_NOT_EXITS = -1;

    private $zdb;
    private $table;
    private $fpk;
    private $flabel;
    private $fthird;
    private $used;

    public static $fields;
    protected static $defaults;

    /** @var string|false */
    protected $order_field = false;

    private $id;
    private $label;
    private $third;

    private $errors = array();

    /**
     * Default constructor
     *
     * @param Db     $zdb    Database
     * @param string $table  Table name
     * @param string $fpk    Primary key field name
     * @param string $flabel Label fields name
     * @param string $fthird The third field name
     * @param string $used   Table name for isUsed function
     * @param mixed  $args   Either an int or a resultset to load
     */
    public function __construct(Db $zdb, $table, $fpk, $flabel, $fthird, $used, $args = null)
    {
        $this->zdb = $zdb;
        $this->table = $table;
        $this->fpk = $fpk;
        $this->flabel = $flabel;
        $this->fthird = $fthird;
        $this->used = $used;
        if (is_int($args)) {
            $this->load($args);
        } elseif (is_object($args)) {
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
        try {
            $select = $this->zdb->select($this->table);
            $select->where([$this->fpk => $id]);

            $results = $this->zdb->execute($select);
            if ($results->count() > 0) {
                $result = $results->current();
                $this->loadFromRS($result);

                return true;
            } else {
                Analog::log(
                    'Unknown ID ' . $id,
                    Analog::ERROR
                );
                return false;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Cannot load ' . $this->getType() . ' from id `' . $id . '` | ' .
                $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ResultSet $r the resultset row
     *
     * @return void
     */
    private function loadFromRS($r)
    {
        $pk = $this->fpk;
        $this->id = $r->$pk;
        $flabel = $this->flabel;
        $this->label = $r->$flabel;
        $fthird = $this->fthird;
        $this->third = $r->$fthird;
    }

    /**
     * Set defaults at install time
     *
     * @return boolean
     * @throws Throwable
     */
    public function installInit()
    {
        $class = get_class($this);

        try {
            //first, we drop all values
            $delete = $this->zdb->delete($this->table);
            $this->zdb->execute($delete);

            $values = array();
            foreach ($class::$fields as $key => $f) {
                $values[$f] = ':' . $key;
            }

            $insert = $this->zdb->insert($this->table);
            $insert->values($values);
            $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

            $this->zdb->handleSequence(
                $this->table,
                count(static::$defaults)
            );

            $fnames = array_values($values);
            foreach ($class::$defaults as $d) {
                $val = null;
                if (isset($d['priority'])) {
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
                'Defaults (' . $this->getType() .
                ') were successfully stored into database.',
                Analog::INFO
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to initialize defaults (' . $this->getType() . ').' .
                $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Get list in an array built as:
     * $array[id] = "translated label"
     *
     * @param boolean|null $extent Filter on (non) cotisations types
     *
     * @return array|false
     */
    public function getList(bool $extent = null)
    {
        $list = array();

        try {
            $select = $this->zdb->select($this->table);
            $fields = array($this->fpk, $this->flabel);
            if (
                $this->order_field !== false
                && $this->order_field !== $this->fpk
                && $this->order_field !== $this->flabel
            ) {
                $fields[] = $this->order_field;
            }
            $select->quantifier('DISTINCT');
            $select->columns($fields);

            if ($this->order_field !== false) {
                $select->order($this->order_field, $this->fpk);
            }
            if ($extent !== null) {
                if ($extent === true) {
                    $select->where(array($this->fthird => new Expression('true')));
                } elseif ($extent === false) {
                    $select->where(array($this->fthird => new Expression('false')));
                }
            }

            $results = $this->zdb->execute($select);

            foreach ($results as $r) {
                $fpk = $this->fpk;
                $flabel = $this->flabel;
                $list[$r->$fpk] = _T($r->$flabel);
            }
            return $list;
        } catch (Throwable $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Complete list
     *
     * @return array of all objects if succeeded, false otherwise
     */
    public function getCompleteList()
    {
        $list = array();

        try {
            $select = $this->zdb->select($this->table);
            if ($this->order_field !== false) {
                $select->order(array($this->order_field, $this->fpk));
            }

            $results = $this->zdb->execute($select);

            if ($results->count() == 0) {
                Analog::log(
                    'No entries (' . $this->getType() . ') defined in database.',
                    Analog::INFO
                );
            } else {
                $pk = $this->fpk;
                $flabel = $this->flabel;
                $fprio = $this->fthird;

                foreach ($results as $r) {
                    $list[$r->$pk] = array(
                        'name'  => _T($r->$flabel),
                        'extra' => $r->$fprio
                    );
                }
            }
            return $list;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot list entries (' . $this->getType() .
                ') | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
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
        if (!is_numeric($id)) {
            $this->errors[] = _T("ID must be an integer!");
            return false;
        }

        try {
            $select = $this->zdb->select($this->table);
            $select->where([$this->fpk => $id]);

            $results = $this->zdb->execute($select);
            $result = $results->current();

            if (!$result) {
                $this->errors[] = _T("Label does not exist");
                return false;
            }

            return $result;
        } catch (Throwable $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
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
        if ($res === false) {
            //get() already logged
            return self::ID_NOT_EXITS;
        };
        $field = $this->flabel;
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
        try {
            $pk = $this->fpk;
            $select = $this->zdb->select($this->table);
            $select->columns(array($pk))
                ->where(array($this->flabel => $label));

            $results = $this->zdb->execute($select);
            $result = $results->current();
            if ($result) {
                return $result->$pk;
            } else {
                return false;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Unable to retrieve ' . $this->getType() . ' from label `' .
                $label . '` | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
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
        // Avoid duplicates.
        $ret = $this->getIdByLabel($label);

        if ($ret !== false) {
            Analog::log(
                $this->getType() . ' with label `' . $label . '` already exists',
                Analog::WARNING
            );
            return -2;
        }

        try {
            $this->zdb->connection->beginTransaction();
            $values = array(
                $this->flabel  => $label,
                $this->fthird  => $extra
            );

            $insert = $this->zdb->insert($this->table);
            $insert->values($values);

            $ret = $this->zdb->execute($insert);

            if ($ret->count() > 0) {
                Analog::log(
                    'New ' . $this->getType() . ' `' . $label .
                    '` added successfully.',
                    Analog::INFO
                );

                $this->id = $this->zdb->getLastGeneratedValue($this);

                $this->addTranslation($label);
            } else {
                throw new \Exception('New ' . $this->getType() . ' not added.');
            }
            $this->zdb->connection->commit();
            return true;
        } catch (Throwable $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                'Unable to add new entry `' . $label . '` | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Update in database.
     *
     * @param integer $id    Entry ID
     * @param string  $label The label
     * @param integer $extra Extra values (priority for statuses,
     *                       extension for contributions types, ...)
     *
     * @return ID_NOT_EXITS|boolean
     */
    public function update($id, $label, $extra)
    {
        $ret = $this->get($id);
        if (!$ret) {
            /* get() already logged and set $this->error. */
            return self::ID_NOT_EXITS;
        }

        $class = get_class($this);

        try {
            $oldlabel = $ret->{$this->flabel};
            $this->zdb->connection->beginTransaction();
            $values = array(
                $this->flabel  => $label,
                $this->fthird  => $extra
            );

            $update = $this->zdb->update($this->table);
            $update->set($values);
            $update->where([$this->fpk => $id]);

            $ret = $this->zdb->execute($update);

            if ($oldlabel != $label) {
                $this->deleteTranslation($oldlabel);
                $this->addTranslation($label);
            }

            Analog::log(
                $this->getType() . ' #' . $id . ' updated successfully.',
                Analog::INFO
            );
            $this->zdb->connection->commit();
            return true;
        } catch (Throwable $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                'Unable to update ' . $this->getType() . ' #' . $id . ' | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Delete entry
     *
     * @param integer $id Entry ID
     *
     * @return ID_NOT_EXITS|boolean
     */
    public function delete($id)
    {
        $ret = $this->get($id);
        if (!$ret) {
            /* get() already logged */
            return self::ID_NOT_EXITS;
        }

        if ($this->isUsed($id)) {
            $this->errors[] = _T("Cannot delete this label: it's still used");
            return false;
        }

        try {
            $this->zdb->connection->beginTransaction();
            $delete = $this->zdb->delete($this->table);
            $delete->where([$this->fpk => $id]);

            $this->zdb->execute($delete);
            $this->deleteTranslation($ret->{$this->flabel});

            Analog::log(
                $this->getType() . ' ' . $id . ' deleted successfully.',
                Analog::INFO
            );

            $this->zdb->connection->commit();
            return true;
        } catch (Throwable $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                'Unable to delete ' . $this->getType() . ' ' . $id .
                ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Check if this entry is used.
     *
     * @param integer $id Entry ID
     *
     * @return boolean
     */
    public function isUsed($id)
    {
        try {
            $select = $this->zdb->select($this->used);
            $select->where([$this->fpk => $id]);

            $results = $this->zdb->execute($select);
            $result = $results->current();

            if ($result !== null) {
                return true;
            } else {
                return false;
            }
        } catch (Throwable $e) {
            Analog::log(
                'Unable to check if ' . $this->getType . ' `' . $id .
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
    abstract protected function getType();

    /**
     * Get translated textual representation
     *
     * @return string
     */
    abstract public function getI18nType();

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
        $virtuals = array('extension', 'libelle');
        if (
            in_array($name, $virtuals)
            || !in_array($name, $forbidden)
            && isset($this->$name)
        ) {
            switch ($name) {
                case 'libelle':
                    return _T($this->label);
                    break;
                case 'extension':
                    return $this->third;
                    break;
                default:
                    return $this->$name;
                    break;
            }
        } else {
            return false;
        }
    }

    /**
     * Get errors
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
