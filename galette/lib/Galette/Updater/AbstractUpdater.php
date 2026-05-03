<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Updater;

use Analog\Analog;
use Galette\Core\Db;
use Galette\Core\Install;
use Safe\Exceptions\DirException;

use function Safe\filesize;
use function Safe\fopen;
use function Safe\fread;
use function Safe\opendir;
use function Safe\preg_match;

/**
 * Galette abstract updater script
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
abstract class AbstractUpdater
{
    public const int REPORT_SUCCESS = 0;
    public const int REPORT_ERROR = 1;
    public const int REPORT_WARNING = 2;

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
    private array $engines = [
        Db::MYSQL   => Db::MYSQL,
        Db::PGSQL   => Db::PGSQL,
    ];
    protected Db $zdb;
    protected Install $installer;
    /**
     * Report
     *
     * @var array<string,array<int|string>>
     */
    private array $report = [];

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
     */
    private function hasSql(): bool
    {
        return $this->sql_scripts !== null;
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
     */
    abstract protected function update(): bool;

    /**
     * Pre stuff, if any.
     * Will be executed first.
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
     */
    protected function postUpdate(): bool
    {
        return true;
    }

    /**
     * Set SQL files instructions for all supported databases
     *
     * @param string $version Version for scripts
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
                'Unable to see SQL scripts. Please check that scripts exists '
                . 'in scripts/sql directory, for all supported SQL engines.',
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
        $scripts = [];
        try {
            $dh = opendir(GALETTE_ROOT . '/install/scripts/sql');
            while (($file = readdir($dh)) !== false) {
                if (preg_match('/upgrade-to-(.*)-(.+)\.sql/', $file, $ver) && $ver[1] == $version) {
                    $scripts[$ver[2]] = GALETTE_ROOT . '/install/scripts/sql/' . $file;
                }
            }
            closedir($dh);
        } catch (DirException) {
            //empty catch
        }

        return $scripts;
    }

    /**
     * Add report entry in array
     *
     * @param string $msg  Report message
     * @param int    $type Entry type
     */
    public function addReportEntry(string $msg, int $type): void
    {
        $res = true;
        if ($type === self::REPORT_ERROR) {
            $res = false;
        }
        $this->report[] = [
            'message'   => $msg,
            'type'      => $type,
            'res'       => $res
        ];
    }

    /**
     * Add an error in array
     *
     * @param string $msg Error message
     */
    public function addError(string $msg): void
    {
        $this->addReportEntry($msg, self::REPORT_ERROR);
    }

    /**
     * Has current update errors?
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
     */
    private function updateDbVersion(): void
    {
        $update = $this->zdb->update('database');
        $update->set(
            ['version' => $this->db_version]
        );
        $this->zdb->execute($update);
    }
}
