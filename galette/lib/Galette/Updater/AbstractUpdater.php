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
use Galette\Core\Logs;
use Safe\Exceptions\DirException;
use Throwable;

use function Safe\filesize;
use function Safe\fopen;
use function Safe\fread;
use function Safe\file_put_contents;
use function Safe\mkdir;
use function Safe\opendir;
use function Safe\preg_match;
use function Safe\stream_get_contents;

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
    /** Version of the SQL scripts to run, if any */
    private ?string $sql_version = null;
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
     * @var array<int, array{message: string, type: int, res: bool}>
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

        if ($this->sql_version !== null) {
            //scripts are looked for next to the update class: Galette, or a plugin
            $this->loadSqlScripts($installer->getSqlScriptsPath());
        }

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

        Analog::log('Execute SQL script ' . $script . ': empty or unreadable', Analog::WARNING);
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
     * Scripts are named upgrade-to-<version>-<engine>.sql, and live in the scripts/sql
     * directory, next to the update class.
     *
     * @param string $version Version for scripts
     */
    protected function setSqlScripts(string $version): bool
    {
        $this->sql_version = $version;
        return true;
    }

    /**
     * Load SQL scripts, for all supported databases
     *
     * @param string $path Scripts directory
     *
     * @throws \RuntimeException when a script is missing for one engine
     */
    private function loadSqlScripts(string $path): void
    {
        $scripts = $this->getSqlScripts($path, (string)$this->sql_version);
        if (
            count($scripts) !== count($this->engines)
            || array_diff(array_keys($scripts), $this->engines) !== []
        ) {
            Analog::log(
                'Unable to see SQL scripts. Please check that scripts exists '
                . 'in ' . $path . ' directory, for all supported SQL engines.',
                Analog::ERROR
            );
            throw new \RuntimeException(
                sprintf('Missing SQL scripts for version %1$s', $this->sql_version)
            );
        }

        $this->sql_scripts = $scripts;
    }

    /**
     * Get SQL scripts for specified version
     *
     * @param string $path    Scripts directory
     * @param string $version Scripts version
     *
     * @return array<string,string>
     */
    private function getSqlScripts(string $path, string $version): array
    {
        $scripts = [];
        try {
            $dh = opendir($path);
            while (($file = readdir($dh)) !== false) {
                if (preg_match('/upgrade-to-(.*)-(.+)\.sql/', $file, $ver) && $ver[1] == $version) {
                    $scripts[$ver[2]] = $path . '/' . $file;
                }
            }
            closedir($dh);
        } catch (DirException) {
            // no log: no SQL scripts directory, there is no SQL script to run
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
     * @return array<int, array{message: string, type: int, res: bool}>
     */
    public function getReport(): array
    {
        return $this->report;
    }

    /**
     * Write pictures stored in a database table to disk, before the table is dropped
     *
     * A picture is written only when no file exists yet for it, whatever its
     * extension: files on disk have always been the reference. A missing table
     * is not an error, it has already been dropped.
     *
     * @param string    $table      Table name, without the Galette prefix
     * @param string    $pk         Name of the id column
     * @param string    $store_path Directory to write files to, with a trailing slash
     * @param ?callable $filename   Builds the file name (without extension) from the id;
     *                              the id itself when null
     *
     * @return bool false if a picture could not be written
     */
    protected function restorePictures(
        string $table,
        string $pk,
        string $store_path,
        ?callable $filename = null
    ): bool {
        //no query on a missing table: pgsql would abort a running transaction
        $full_table = PREFIX_DB . $table;
        if (!in_array($full_table, $this->zdb->getTables($full_table), strict: true)) {
            return true;
        }

        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $select = $this->zdb->select($table);
        $select->columns([$pk, 'picture', 'format']);

        $restored = 0;
        //row by row: pictures are heavy
        foreach ($this->zdb->execute($select) as $row) {
            $id = $row->$pk;
            $name = $filename !== null ? (string)$filename($id) : (string)$id;
            $format = strtolower((string)$row->format);
            if ($format === 'jpeg') {
                $format = 'jpg';
            }
            if (!in_array($format, $extensions, strict: true)) {
                $this->addReportEntry(
                    sprintf(
                        //TRANS: %1$s is the picture identifier, %2$s its format
                        _T('Picture %1$s has an unknown format (%2$s), it has not been restored.'),
                        $name,
                        $format
                    ),
                    self::REPORT_WARNING
                );
                continue;
            }

            foreach ($extensions as $ext) {
                if (file_exists($store_path . $name . '.' . $ext)) {
                    continue 2;
                }
            }

            try {
                if (!is_dir($store_path)) {
                    mkdir($store_path, 0o755, recursive: true);
                }
                $picture = $row->picture;
                if (is_resource($picture)) {
                    //pgsql returns bytea as a stream
                    $picture = stream_get_contents($picture);
                }
                file_put_contents($store_path . $name . '.' . $format, $picture);
                ++$restored;
            } catch (Throwable $e) {
                Logs::exception($e, 'Unable to restore picture ' . $name . ' from ' . $full_table);
                $this->addError(
                    sprintf(
                        //TRANS: %1$s is the picture file path
                        _T('Unable to write picture %1$s.'),
                        $store_path . $name . '.' . $format
                    )
                );
                return false;
            }
        }

        if ($restored > 0) {
            $this->addReportEntry(
                sprintf(
                    //TRANS: %1$d is a number of files, %2$s a directory
                    _Tn('%1$d picture has been written to %2$s.', '%1$d pictures have been written to %2$s.', $restored),
                    $restored,
                    $store_path
                ),
                self::REPORT_SUCCESS
            );
        }

        return true;
    }

    /**
     * Update database version
     */
    private function updateDbVersion(): void
    {
        //a plugin version is stored once all its scripts have run
        $this->installer->updateDbVersion($this->zdb, (string)$this->db_version);
    }
}
