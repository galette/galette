<?php

/**
 * Copyright © 2003-2024 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
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
 */

declare(strict_types=1);

namespace Galette\Updater;

use Analog\Analog;
use Galette\Core\Db;
use Galette\Core\Install;

/**
 * Galette abstract updater script
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
abstract class AbstractUpdater
{
    public const REPORT_SUCCESS = 0;
    public const REPORT_ERROR = 1;
    public const REPORT_WARNING = 2;

    /**
     * SQL scripts to run
     *
     * @var array<string,string>
     */
    protected ?array $sql_scripts = null;
    protected ?string $db_version = null;
    /**
     * Supported SQL engines
     *
     * @var array<string,string>
     */
    private array $engines = array(
        Db::MYSQL   => Db::MYSQL,
        Db::PGSQL   => Db::PGSQL,
    );
    protected Db $zdb;
    protected Install $installer;
    /**
     * Report
     *
     * @var array<string,array<int|string>>
     */
    private array $report = array();

    /**
     * Main constructor
     */
    public function __construct()
    {
        if ($this->db_version === null) {
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
    private function hasSql(): bool
    {
        return !($this->sql_scripts === null);
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
     * @return void
     */
    final public function run(Db $zdb, Install $installer): void
    {
        $this->zdb = $zdb;
        $this->installer = $installer;

        $res = $this->preUpdate();
        if ($res !== true) {
            throw new \RuntimeException(
                'Fail executing pre-update instructions'
            );
        }

        $res = $this->update();
        if ($res !== true) {
            throw new \RuntimeException(
                'Fail executing update instructions'
            );
        }

        if ($this->hasSql()) {
            $res = $this->sql($zdb, $installer);
            if ($res !== true) {
                throw new \RuntimeException(
                    'Fail executing SQL instructions'
                );
            }
        }

        $res = $this->postUpdate();
        if ($res !== true) {
            throw new \RuntimeException(
                'Fail executing post-update instructions'
            );
        }

        $this->updateDbVersion();
    }

    /**
     * Update instructions
     *
     * @return boolean
     */
    abstract protected function update(): bool;

    /**
     * Pre stuff, if any.
     * Will be executed first.
     *
     * @return boolean
     */
    protected function preUpdate(): bool
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
    private function sql(Db $zdb, Install $installer): bool
    {
        $script = $this->sql_scripts[TYPE_DB];

        $sql_query = @fread(
            @fopen($script, 'r'),
            @filesize($script)
        ) . "\n";

        if (trim($sql_query) !== '') {
            return $installer->executeSql($zdb, $sql_query);
        }

        return false;
    }

    /**
     * Post stuff, if any.
     * Will be executed at the end.
     *
     * @return boolean
     */
    protected function postUpdate(): bool
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
    protected function setSqlScripts(string $version): bool
    {
        $scripts = $this->getSqlScripts($version);
        if (
            count($scripts) === count($this->engines)
            && count(array_diff(array_keys($scripts), $this->engines)) == 0
        ) {
            $checked = false;
            foreach ($scripts as $file) {
                if (file_exists($file)) {
                    $checked = true;
                } else {
                    $checked = false;
                    break;
                }
            }

            if ($checked === true) {
                $this->sql_scripts = $scripts;
            }
            return $checked;
        } else {
            Analog::log(
                'Unable to see SQL scripts. Please check that scripts exists ' .
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
     * @return array<string,string>
     */
    private function getSqlScripts(string $version): array
    {
        $dh = opendir(GALETTE_ROOT . '/install/scripts/sql');
        $scripts = array();

        if ($dh !== false) {
            while (($file = readdir($dh)) !== false) {
                if (preg_match('/upgrade-to-(.*)-(.+)\.sql/', $file, $ver)) {
                    if ($ver[1] == $version) {
                        $scripts[$ver[2]] = GALETTE_ROOT . '/install/scripts/sql/' . $file;
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
    public function addReportEntry(string $msg, int $type): void
    {
        $res = true;
        if ($type === self::REPORT_ERROR) {
            $res = false;
        }
        $this->report[] = array(
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
    public function addError(string $msg): void
    {
        $this->addReportEntry($msg, self::REPORT_ERROR);
    }

    /**
     * Has current update errors?
     *
     * @return boolean
     */
    public function hasErrors(): bool
    {
        foreach ($this->report as $report) {
            if ($report['type'] === self::REPORT_ERROR) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get upgrade report
     *
     * @return array<string, array<int|string>>
     */
    public function getReport(): array
    {
        return $this->report;
    }

    /**
     * Update database version
     *
     * @return void
     */
    private function updateDbVersion(): void
    {
        $update = $this->zdb->update('database');
        $update->set(
            array('version' => $this->db_version)
        );
        $this->zdb->execute($update);
    }
}
