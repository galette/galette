<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Updates;

use Galette\Updater\AbstractUpdater;

/**
 * Galette 0.8 upgrade script
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class UpgradeTo08 extends AbstractUpdater
{
    protected ?string $db_version = '0.80';

    /**
     * Main constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setSqlScripts('0.80');
    }

    /**
     * Update instructions
     */
    protected function update(): bool
    {
        $dirs = [
            'logs',
            'templates_c',
            'cache',
            'exports',
            'imports',
            'photos',
            'attachments',
            'tempimages',
            'files'
        ];

        if (!file_exists(GALETTE_ROOT . 'data')) {
            $created = @mkdir(GALETTE_ROOT . 'data'); //@phpstan-ignore theCodingMachineSafe.function
            if (!$created) {
                $this->addError(
                    sprintf(
                        //TRANS: parameter is the path
                        _T('Unable to create main datadir in %1$s!'),
                        GALETTE_ROOT . 'data'
                    )
                );
                return false;
            }
        }

        foreach ($dirs as $dir) {
            $path = GALETTE_ROOT . 'data/' . $dir;
            if (!file_exists($path)) {
                $created = @mkdir($path); //@phpstan-ignore theCodingMachineSafe.function
                if (!$created) {
                    $this->addError(
                        sprintf(
                            //TRANS: parameter is the directory
                            _T('Unable to create datadir in %1$s!'),
                            $path
                        )
                    );
                }
            }
            $this->moveDataDir($dir);
        }

        return !$this->hasErrors();
    }

    /**
     * Move data directory
     *
     * @param string $dirname Directory name to move
     */
    private function moveDataDir(string $dirname): void
    {
        //all directories should not be moved
        $nomove = [
            'templates_c',
            'cache',
            'tempimages'
        ];

        if (!in_array($dirname, $nomove)) {
            $origdir = GALETTE_ROOT . $dirname . '/';
            $destdir = GALETTE_DATA_PATH . $dirname . '/';

            $go = false;
            //move directory contents
            switch ($dirname) {
                case 'logs':
                    if (GALETTE_LOGS_PATH === $destdir && file_exists($origdir)) {
                        $go = true;
                    }
                    break;
                case 'exports':
                    if (GALETTE_EXPORTS_PATH === $destdir && file_exists($origdir)) {
                        $go = true;
                    }
                    break;
                case 'imports':
                    if (GALETTE_IMPORTS_PATH === $destdir && file_exists($origdir)) {
                        $go = true;
                    }
                    break;
                case 'photos':
                    if (GALETTE_PHOTOS_PATH === $destdir && file_exists($origdir)) {
                        $go = true;
                    }
                    break;
                case 'attachments':
                    if (GALETTE_ATTACHMENTS_PATH === $destdir && file_exists($origdir)) {
                        $go = true;
                    }
                    break;
                case 'files':
                    if (GALETTE_FILES_PATH === $destdir && file_exists($origdir)) {
                        $go = true;
                    }
                    break;
            }

            if ($go) {
                $moved = true;
                $d = dir($origdir); //@phpstan-ignore theCodingMachineSafe.function
                while (($entry = $d->read()) !== false) {
                    if ($entry != '.' && $entry != '..') {
                        $moved = @rename($origdir . $entry, $destdir . $entry); //@phpstan-ignore theCodingMachineSafe.function
                        if (!$moved) {
                            $moved = false;
                            $this->addError(
                                sprintf(
                                    _T('File %1$s has not been moved :-/'),
                                    $entry
                                )
                            );
                        }
                    }
                }
                $d->close();

                if ($moved) {
                    $this->addReportEntry(
                        sprintf(
                            //TRANS: parameter is the directory
                            _T('Directory %1$s has been moved!'),
                            $dirname
                        ),
                        self::REPORT_SUCCESS
                    );

                    //remove old directory?
                    //maybe it would be done by the user
                } else {
                    $this->addError(
                        sprintf(
                            //TRANS: parameter is the directory
                            _T('Directory %1$s has not been moved :('),
                            $dirname
                        )
                    );
                }
            } else {
                $this->addReportEntry(
                    sprintf(
                        //TRANS: parameter is the directory
                        _T('Directory %1$s is not in its original path and will not be moved.'),
                        $dirname
                    ),
                    self::REPORT_WARNING
                );
            }
        }
    }
}
