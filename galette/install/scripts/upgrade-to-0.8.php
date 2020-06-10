<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette 0.8 upgrade script
 *
 * PHP version 5
 *
 * Copyright © 2014 The Galette Team
 *
 * This file is part of Galette (http://galette.eu).
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
 * @category  Upgrades
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.eu
 * @since     Available since 0.8 - 2014-01-04
 */

namespace Galette\Updates;

use \Analog\Analog;
use Galette\Updater\AbstractUpdater;

/**
 * Galette 0.8 upgrade script
 *
 * @category  Upgrades
 * @name      Install
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.eu
 * @since     Available since 0.8 - 2014-01-04
 */
class UpgradeTo08 extends AbstractUpdater
{
    protected $db_version = '0.80';

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
     *
     * @return boolean
     */
    protected function update()
    {
        $dirs = array(
            'logs',
            'templates_c',
            'cache',
            'exports',
            'imports',
            'photos',
            'attachments',
            'tempimages',
            'files'
        );

        if (!file_exists(GALETTE_ROOT . 'data')) {
            $created = @mkdir(GALETTE_ROOT . 'data');
            if (!$created) {
                $this->addError(
                    str_replace(
                        '%path',
                        GALETTE_ROOT . 'data',
                        _T("Unable to create main datadir in %path!")
                    )
                );
                return false;
            }
        }

        foreach ($dirs as $dir) {
            $path = GALETTE_ROOT . 'data/' . $dir;
            if (!file_exists($path)) {
                $created = @mkdir($path);
                if (!$created) {
                    $this->addError(
                        str_replace(
                            '%dir',
                            $path,
                            _T("Unable to create datadir in %dir!")
                        )
                    );
                }
            }
            $this->_moveDataDir($dir);
        }

        return !$this->hasErrors();
    }

    /**
     * Move data directory
     *
     * @param string $dirname Directory name to move
     *
     * @return boolean
     */
    private function _moveDataDir($dirname)
    {
        //all directories should not be moved
         $nomove = array(
            'templates_c',
            'cache',
            'tempimages'
        );

        if ( !in_array($dirname, $nomove) ) {
            $origdir = GALETTE_ROOT . $dirname . '/';
            $destdir = GALETTE_DATA_PATH . $dirname . '/';

            $go = false;
            //move directory contents
            switch ( $dirname ) {
                case 'logs':
                    if ( GALETTE_LOGS_PATH === $destdir && file_exists($origdir) ) {
                        $go = true;
                    }
                    break;
                case 'exports':
                    if ( GALETTE_EXPORTS_PATH === $destdir && file_exists($origdir) ) {
                        $go = true;
                    }
                    break;
                case 'imports':
                    if ( GALETTE_IMPORTS_PATH === $destdir && file_exists($origdir) ) {
                        $go = true;
                    }
                    break;
                case 'photos':
                    if ( GALETTE_PHOTOS_PATH === $destdir && file_exists($origdir) ) {
                        $go = true;
                    }
                    break;
                case 'attachments':
                    if ( GALETTE_ATTACHMENTS_PATH === $destdir
                        && file_exists($origdir)
                    ) {
                        $go = true;
                    }
                    break;
                case 'files':
                    if ( GALETTE_FILES_PATH === $destdir && file_exists($origdir) ) {
                        $go = true;
                    }
                    break;
            }

            if ( $go ) {
                $moved = true;
                $d = dir($origdir);
                while (($entry = $d->read()) !== false) {
                    if ( $entry != '.' && $entry != '..' ) {
                        $moved = @rename($origdir . $entry, $destdir . $entry);
                        if ( !$moved ) {
                            $moved = false;
                            $this->addError(
                                str_replace(
                                    '%file',
                                    $entry,
                                    _T("File %file has not been moved :-/")
                                )
                            );
                        }
                    }
                }
                $d->close();

                if ($moved) {
                    $this->addReportEntry(
                        str_replace(
                            '%dir',
                            $dirname,
                            _T("Directory %dir has been moved!")
                        ),
                        self::REPORT_SUCCESS
                    );

                    //remove old directory?
                    //maybe it would be done by the user
                } else {
                    $this->addError(
                        str_replace(
                            '%dir',
                            $dirname,
                            _T("Directory %dir has not been moved :(")
                        )
                    );
                }
            } else {
                $this->addReportEntry(
                    str_replace(
                        '%dir',
                        $dirname,
                        _T("Directory %dir is not in its original path and will not be moved.")
                    ),
                    self::REPORT_WARNING
                );
            }
        }
    }
}
