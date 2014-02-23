<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette abstract script updater
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
 * @category  Updater
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.6dev - 2013-07-21
 */

namespace Galette\Updater;

use \Analog\Analog;
use Galette\Core\Db;

/**
 * Galette abstract updater script
 *
 * @category  Updater
 * @name      AbstractUpdater
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.6dev - 2013-07-21
 */
abstract class AbstractUpdater
{
    const REPORT_SUCCESS = 0;
    const REPORT_ERROR = 1;
    const REPORT_WARNING = 2;

    protected $sql_scripts = null;
    protected $db_version = null;
    private $_engines = array(
        Db::MYSQL,
        Db::PGSQL,
        Db::SQLITE
    );
    protected $zdb;
    protected $installer;
    private $_report = array();

    /**
     * Main constructor
     *
     * @param boolean $with_sqlite Wheter SQLite support was existing
     */
    public function __construct($with_sqlite = true)
    {
        if ( $with_sqlite === false ) {
            unset($this->_engines[Db::SQLITE]);
        }
        if ( $this->db_version === null ) {
            Analog::log(
                'Upgrade version can not be empty!',
                Analog::ERROR
            );
            throw new \RuntimeException('Upgrade version can not be empty!');
        }
    }

    /**
     * Does upgrade have a SQL script to run
     *
     * @return boolean
     */
    private function _hasSql()
    {
        return !$this->sql_scripts === null;
    }

    /**
     * Runs the update.
     * Update will take the following order:
     *     - preUpdate
     *     - update
     *     - sql (if any)
     *     - postUpdate
     *
     * If one function fails, an Exception will be thrown
     * and next function will not be called.
     *
     * @param Db      $zdb       Database instance
     * @param Install $installer Installer instance
     *
     * @return Boolean|Exception
     */
    public final function run($zdb, $installer)
    {
        $this->zdb = $zdb;
        $this->installer = $installer;

        $res = $this->preUpdate();
        if ( $res !== true ) {
            throw new \RuntimeException(
                'Fail executing pre-update instructions'
            );
        }

        $res = $this->update();
        if ( $res !== true ) {
            throw new \RuntimeException(
                'Fail executing update instructions'
            );
        }

        if ( $this->_hasSql() ) {
            $res = $this->sql($zdb, $installer);
            if ( $res !== true ) {
                throw new \RuntimeException(
                    'Fail executing SQL instructions'
                );
            }
        }

        $res = $this->postUpdate();
        if ( $res !== true ) {
            throw new \RuntimeException(
                'Fail executing post-update instructions'
            );
        }

        $this->_updateDbVersion();
    }

    /**
     * Update instructions
     *
     * @return boolean
     */
    abstract protected function update();

    /**
     * Pre stuff, if any.
     * Will be extecuted first.
     *
     * @return boolean
     */
    protected function preUpdate()
    {
        return true;
    }

    /**
     * Executes SQL instructions, if any.
     *
     * @param Db      $zdb       Database instance
     * @param Install $installer Installer instance
     *
     * @return boolean
     */
    private function _sql($zdb, $installer)
    {
        $script = $this->_sql_scripts[TYPE_DB];

        $sql_query .= @fread(
            @fopen($script, 'r'),
            @filesize($script)
        ) . "\n";

        if ( $sql_query !== '' ) {
            $installer->executeSql($zdb, $script);
        }
    }

    /**
     * Post stuff, if any.
     * Will be executed at the end.
     *
     * @return boolean
     */
    protected function postUpdate()
    {
        return true;
    }

    /**
     * Set SQL files instructions for all supported databases
     *
     * @param string $version Version for scripts
     *
     * @return boolean
     */
    protected function setSqlScripts($version)
    {
        $scripts = $this->_getSqlScripts();
        if ( is_array($scripts)
            && count($scripts) === count($this->_engines)
            && count(array_diff(array_keys($scripts), $this->_engines)) == 0
        ) {
            $checked = false;
            foreach ( $scripts as $file ) {
                if ( file_exists($file) ) {
                    $checked = true;
                } else {
                    $checked = false;
                    break;
                }
            }

            if ( $checked === true ) {
                $this->_sql_scripts = $scripts;
            }
            return $checked;
        } else {
            Analog::log(
                'Unable to se SQL scripts. Please check that scripts exists ' .
                'in scripts/sql directory, for all supported SQL engines.',
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Get SQL scripts for specified version
     *
     * @param string $version Scripts version
     *
     * @return array
     */
    private function _getSqlScripts($version)
    {
        $dh = opendir('./sql');
        $scripts = array();

        if ( $dh !== false ) {
            while ( ($file = readdir($dh)) !== false ) {
                if ( preg_match('/upgrade-to-(.*)-(.+)\.sql/', $file, $ver, $type) ) {
                    if ( $ver === $version ) {
                        $scripts[$type] = realpath('./sql/' . $file);
                    }
                }
            }
            closedir($dh);
        }

        return $scripts;
    }

    /**
     * Add report entry in array
     *
     * @param string $msg  Report message
     * @param int    $type Entry type
     *
     * @return void
     */
    public function addReportEntry($msg, $type)
    {
        $res = true;
        if ( $type === self::REPORT_ERROR ) {
            $res = false;
        }
        $this->_report[] = array(
            'message'   => $msg,
            'type'      => $type,
            'res'       => $res
        );
    }

    /**
     * Add an error in array
     *
     * @param string $msg Error message
     *
     * @return void
     */
    public function addError($msg)
    {
        $this->addReportEntry($msg, self::REPORT_ERROR);
    }

    /**
     * Has current update errors?
     *
     * @return boolean
     */
    public function hasErrors()
    {
        foreach ( $this->_report as $report ) {
            if ( $report['type'] === self::REPORT_ERROR ) {
                return true;
            }
        }
    }

    /**
     * Get upgrade report
     *
     * @return array
     */
    public function getReport()
    {
        return $this->_report;
    }

    /**
     * Update database version
     *
     * @return void
     */
    private function _updateDbVersion()
    {
        $update = $this->zdb->update('database');
        $update->set(
            array('version' => $this->db_version)
        );
        $this->zdb->execute($update);
    }
}

