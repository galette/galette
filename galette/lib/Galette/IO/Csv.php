<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * CSV files
 *
 * PHP version 5
 *
 * Copyright © 2009-2013 The Galette Team
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
 * @category  IO
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Disponible depuis la Release 0.7alpha - 2009-02-09
 */

namespace Galette\IO;

use Analog\Analog as Analog;

/**
 * CSV files
 *
 * @category  IO
 * @name      Csv
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Disponible depuis la Release 0.7alpha - 2009-02-09
 */

abstract class Csv
{
    const NEWLINE = "\r\n";
    const BUFLINES = 100;

    const DEFAULT_SEPARATOR = ';';
    const DEFAULT_QUOTE = '"';

    const FILE_NOT_WRITABLE = -1;
    const DB_ERROR = -2;

    protected $separator;
    protected $quote;
    protected $escaped;
    protected $file;
    protected $result;
    protected $current_line;

    protected $allowed_extensions = array('csv');
    protected $allowed_mimes = array(
        'csv'    =>    'text/csv'
    );

    protected $accepted_separators = array(
        ',',
        ';',
        '\t'
    );

    protected $accepted_quotes = array(
        '"',
        "'"
    );

    private $_errors = array();
    private $_default_directory;

    /**
     * Default constructor
     *
     * @param string $default_dir Default directory
     */
    public function __construct($default_dir)
    {
        $this->_default_directory = $default_dir;
    }

    /**
     * Retrieve a list of already existing CSV files
     *
     * @return array
     */
    public function getExisting()
    {
        $csv_files = array();
        $files = glob(
            $this->_default_directory . '*.{' .
            implode(',', $this->allowed_extensions) . '}',
            GLOB_BRACE
        );
        foreach ( $files as $file ) {
            if ( $file === $this->_default_directory . 'readme.txt' ) {
                continue;
            }
            $mdate = date(_T("Y-m-d H:i:s"), filemtime($file));

            $raw_size = filesize($file);
            $size = 0;
            if ($raw_size >= 1024*1024*1024) { // Go
                $size = round(($raw_size / 1024)/1024/1024, 2) . ' Go';
            } elseif ( $raw_size >= 1024*1024) { // Mo
                $size = round(($raw_size / 1024)/1024, 2) . ' Mo';
            } elseif ( $raw_size >= 1024) { // ko
                $size = round(($raw_size / 1024), 2) . ' Ko';
            } else { // octets
                $size = $raw_size . ' octets';
            }

            $csv_files[] = array(
                'name'  => str_replace($this->_default_directory, '', $file),
                'size'  => $size,
                'date'  => $mdate
            );
        }
        return $csv_files;
    }

    /**
     * Remove existing CSV file
     *
     * @param string $name File name
     *
     * @return boolean
     */
    public function remove($name)
    {
        //let's ensure we do not have a path here
        $name = basename($name);
        $filename=$this->_default_directory . $name;

        if ( file_exists($filename) ) {
            $removed = unlink($filename);
            return $removed;
        } else {
            Analog::log(
                'CSV file ' . $filename .
                ' does not exists, no way to remove it!',
                Analog::ERROR
            );
            return false;
        }

    }

    /**
     * Accepted separators
     *
     * @return array list of accepted separators
     */
    public function getAcceptedSeparators()
    {
        return $this->accepted_separators;
    }

    /**
     * Accepted quotes
     *
     * @return array list of accepted quotes
     */
    public function getAcceptedQuotes()
    {
        return $this->accepted_quotes;
    }

    /**
     * Add an error
     *
     * @param string $msg Error message
     *
     * @return void
     */
    public function addError($msg)
    {
        $class = get_class($this);
        Analog::log(
            '[' . $class  . '] ' . $msg,
            Analog::ERROR
        );
        $this->_errors[] = $msg;
    }

    /**
     * Get errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->_errors;
    }
}
