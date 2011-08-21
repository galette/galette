<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * History management
 *
 * PHP version 5
 *
 * Copyright © 2009-2011 The Galette Team
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
 * @copyright 2009-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-02-09
 */

require_once 'pagination.class.php';

/**
 * History management
 *
 * @category  Classes
 * @name      History
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-02-09
 */

class History extends GalettePagination
{
    const TABLE = 'logs';
    const PK = 'id_log';

    protected $_types = array(
        'date',
        'text',
        'text',
        'text',
        'text',
        'text'
    );

    protected $_fields = array(
        'date_log',
        'ip_log',
        'adh_log',
        'action_log',
        'text_log',
        'sql_log'
    );

    /**
    * Default constructor
    */
    public function __construct()
    {
        parent::__construct();
        $this->ordered = self::ORDER_DESC;
    }

    /**
    * Returns the field we want to default set order to
    *
    * @return string field name
    */
    protected function getDefaultOrder()
    {
        return 'date_log';
    }

    /**
    * Add a new entry
    *
    * @param string $action   the action to log
    * @param string $argument the argument
    * @param string $query    the query (if relevant)
    *
    * @return bool true if entry was successfully added, false otherwise
    */
    public function add($action, $argument = '', $query = '')
    {
        global $zdb, $log, $login;

        try {
            $values = array(
                'date_log'   => date('Y-m-d H:i:s'),
                'ip_log'     => $_SERVER["REMOTE_ADDR"],
                'adh_log'    => $login->login,
                'action_log' => $action,
                'text_log'   => $argument,
                'sql_log'    => $query
            );

            $zdb->db->insert(PREFIX_DB . self::TABLE, $values);
        } catch (Zend_Db_Adapter_Exception $e) {
            $log->log(
                'Unable to initialize add log entry into database.' .
                $e->getMessage(),
                PEAR_LOG_WARNING
            );
            return false;
        } catch (Exception $e) {
            $log->log(
                "An error occured trying to add log entry. " . $e->getMessage(),
                PEAR_LOG_ERR
            );
            return false;
        }

        return true;
    }

    /**
    * Delete all entries
    *
    * @return integer : number of entries deleted
    */
    public function clean()
    {
        global $zdb, $log;

        try {
            $result = $zdb->db->query('TRUNCATE TABLE ' . $this->getTableName());

            if ( !$result ) {
                $log->log(
                    'An error occured cleaning history. ',
                    PEAR_LOG_WARNING
                );
                $this->add('Arror flushing logs');
                return false;
            }
            $this->add('Logs flushed');
            return true;
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                'Unable to flush logs. | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
        }
    }

    /**
    * Get the entire history list
    *
    * @return array
    */
    public function getHistory()
    {
        global $zdb, $log;

        if ($this->counter == null) {
            $c = $this->_getCount();

            if ($c == 0) {
                $log->log('No entry in history (yet?).', PEAR_LOG_DEBUG);
                return;
            } else {
                $this->counter = (int)$c;
                $this->countPages();
            }
        }

        try {
            $select = new Zend_Db_Select($zdb->db);
            $select->from($this->getTableName())
                ->order($this->orderby . ' ' . $this->ordered);
            //add limits to retrieve only relavant rows
            $this->setLimits($select);
            return $select->query(Zend_Db::FETCH_ASSOC)->fetchAll();
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                'Unable to get history. | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                PEAR_LOG_ERR
            );
            return false;
        }
    }

    /**
    * Count history entries
    *
    * @return int
    */
    private function _getCount()
    {
        global $zdb, $log;

        try {
            $select = new Zend_Db_Select($zdb->db);
            $select->from(
                $this->getTableName(),
                'COUNT(' . $this->getPk() . ') as counter'
            );
            $qry = $select->__toString();
            return $select->query()->fetchObject()->counter;
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                'Unable to get history count. | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                PEAR_LOG_ERR
            );
            return false;
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
        global $log;

        $log->log(
            '[History] Getting property `' . $name . '`',
            PEAR_LOG_DEBUG
        );

        if ( in_array($name, $this->pagination_fields) ) {
            return parent::__get($name);
        } else {
            $forbidden = array();
            if ( !in_array($name, $forbidden) ) {
                $name = '_' . $name;
                return $this->$name;
            } else {
                $log->log(
                    '[History] Unable to get proprety `' .$name . '`',
                    PEAR_LOG_WARNING
                );
            }
        }
    }

    /**
    * Global setter method
    *
    * @param string $name  name of the property we want to assign a value to
    * @param object $value a relevant value for the property
    *
    * @return void
    */
    public function __set($name, $value)
    {
        global $log;
        if ( in_array($name, $this->pagination_fields) ) {
            parent::__set($name, $value);
        } else {
            $log->log(
                '[History] Setting property `' . $name . '`',
                PEAR_LOG_DEBUG
            );

            $forbidden = array();
            if ( !in_array($name, $forbidden) ) {
                $rname = '_' . $name;
                switch($name) {
                case 'tri':
                    if (in_array($value, $this->_fields)) {
                        $this->orderby = $value;
                    }
                    break;
                default:
                    $this->$rname = $value;
                    break;
                }
            } else {
                $log->log(
                    '[History] Unable to set proprety `' .$name . '`',
                    PEAR_LOG_WARNING
                );
            }
        }
    }

    /**
     * Get table's name
     *
     * @return string
     */
    protected function getTableName()
    {
        return PREFIX_DB . self::TABLE;
    }

    /**
     * Get table's PK
     *
     * @return string
     */
    protected function getPk()
    {
        return self::PK;
    }
}
?>