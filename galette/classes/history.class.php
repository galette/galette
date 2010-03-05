<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * History management
 *
 * PHP version 5
 *
 * Copyright © 2009-2010 The Galette Team
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
 * @copyright 2009-2010 The Galette Team
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
 * @copyright 2009-2010 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-02-09
 */

class History extends GalettePagination
{
    const TABLE = 'logs';
    const PK = 'id_log';

    /** TODO: check for the date type */
    private $_types = array(
        'date',
        'text',
        'text',
        'text',
        'text',
        'text'
    );

    private $_fields = array(
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
    * @param string $argument the arguemnt
    * @param string $query    the query (if relevant)
    *
    * @return bool true if entry was successfully added, false otherwise
    */
    public function add($action, $argument = '', $query = '')
    {
        global $mdb, $log, $login;

        MDB2::loadFile('Date');

        $requete = 'INSERT INTO ' .
            $mdb->quoteIdentifier(PREFIX_DB . self::TABLE) . ' (';
        $requete .= implode(', ', $this->_fields);
        $requete .= ') VALUES (:date, :ip, :adh, :action, :text, :sql)';

        $stmt = $mdb->prepare($requete, $this->_types, MDB2_PREPARE_MANIP);

        if (MDB2::isError($stmt)) {
            $log->log(
                'Unable to initialize add log entry into database.' .
                $stmt->getMessage() . '(' . $stmt->getDebugInfo() . ')',
                PEAR_LOG_WARNING
            );
            return false;
        }

        $stmt->execute(
            array(
                'date'      => MDB2_Date::mdbNow(),
                'ip'        => $_SERVER["REMOTE_ADDR"],
                'adh'       => $login->login,
                'action'    => $action,
                'text'      => $argument,
                'sql'       => $query
            )
        );

        if (MDB2::isError($stmt)) {
            $log->log(
                "An error occured trying to add log entry. " . $stmt->getMessage(),
                PEAR_LOG_ERR
            );
            return false;
        } else {
            $log->log('Log entry added', PEAR_LOG_DEBUG);
        }

        $stmt->free();

        return true;
    }

    /**
    * Delete all entries
    *
    * @return integer : number of entries deleted
    */
    public function clean()
    {
        global $mdb, $log;
        $requete = 'TRUNCATE TABLE ' .
            $mdb->quoteIdentifier(PREFIX_DB . self::TABLE);

        $result = $mdb->execute($requete);

        if (MDB2::isError($stmt)) {
            $log->log(
                'An error occured cleaning history. ' . $result->getMessage(),
                PEAR_LOG_WARNING
            );
            $this->add('Arror flushing logs');
            return -1;
        }

        $this->add('Logs flushed');

        return $result;
    }

    /**
    * Get the entire history list
    *
    * @return array
    */
    public function getHistory()
    {
        global $mdb, $log;

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

        $requete = 'SELECT * FROM ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE);
        $requete .= 'ORDER BY ' . $this->orderby . ' ' . $this->ordered;

        $mdb->getDb()->setLimit($this->show, ($this->current_page - 1) * $this->show);

        $result = $mdb->query($requete);
        if ( MDB2::isError($result) ) {
            return -1;
        }

        $return = array();
        while ($row = $result->fetchRow(MDB2_FETCHMODE_ASSOC)) {
            $return[] = $row;
        }
        return $return;
    }

    /**
    * Count history entries
    *
    * @return int
    */
    private function _getCount()
    {
        global $mdb, $log;
        $requete = 'SELECT count(' . self::PK . ') as counter FROM ' .
            $mdb->quoteIdentifier(PREFIX_DB . self::TABLE);

        $result = $mdb->query($requete);
        if (MDB2::isError($result)) {
            $this->error = $result;
            $log->log(
                'Unable to get history count.' . $result->getMessage() .
                '(' . $result->getDebugInfo() . ')',
                PEAR_LOG_WARNING
            );
            return -1;
        }

        return $result->fetchOne();
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
                        $this->$rname = $value;
                    }
                    break;
                case 'show':
                    $this->$rname = $value;
                    $this->countPages();
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
}
?>