<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * CSV exports
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
 * CSV exports
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

class Csv
{
    const NEWLINE = "\r\n";
    const BUFLINES = 100;

    const DEFAULT_SEPARATOR = ';';
    const DEFAULT_QUOTE = '"';
    const DEFAULT_DIRECTORY = GALETTE_EXPORTS_PATH;

    const FILE_NOT_WRITABLE = -1;
    const DB_ERROR = -2;

    private $_rs;
    private $_separator;
    private $_quote;
    private $_escaped;
    private $_file;
    private $_result;
    private $_current_line;

    private $_parameted_path;
    private $_parameted_file = 'exports.xml';

    private $_accepted_separators = array(
        ',',
        ';',
        '\t'
    );

    private $_accepted_quotes = array(
        '"',
        "'"
    );

    /**
    * Default constructor
    */
    public function __construct()
    {
        $this->_parameted_path = GALETTE_CONFIG_PATH;
        $this->_parameted_file = $this->_parameted_path . $this->_parameted_file;
    }


    /**
    * Export Array result set to CSV
    *
    * @param aray   $rs        Results as an array
    * @param string $separator The CSV separator (either '\t', ';' or ','
    *                          are accepted)
    * @param char   $quote     how does fields should be quoted
    * @param bool   $titles    does export shows column titles or not.
    *                          Defaults to false.
    * @param object $file      export to a file on disk. A file pointer
    *                          should be passed here. Defaults to false.
    *
    * @return string CSV result
    */
    function export($rs, $separator, $quote, $titles=false, $file=false)
    {
        if (!$rs) {
            return '';
        }
        //switch back to the default separator if not in accepted_separators array
        if ( !in_array($separator, $this->_accepted_separators) ) {
            $separator = self::DEFAULT_SEPARATOR;
        }
        //switch back to the default quote if not in accepted_quotes array
        if ( !in_array($quote, $this->_accepted_quotes) ) {
            $quote = self::DEFAULT_QUOTE;
        }

        $this->_result = '';
        $this->_rs = $rs;
        $this->max = count($this->_rs);
        $this->_separator = $separator;
        $this->_quote = $quote;
        //dubbing quote for escaping
        $this->_escaped = $quote . $quote;
        $this->_file = $file;
        $this->_current_line = 0;

        $fields = array();
        if ( $titles && !count($titles>1) ) {
            foreach ( array_key($this->_rs) as $field ) {
                $fields[] = $this->_quote . str_replace(
                    $this->_quote, $this->_escaped, $field
                ) . $this->_quote;
            }
            $this->_result .= implode($this->_separator, $fields) . self::NEWLINE;
        } else if ( $titles && is_array($titles) && count($titles)>1 ) {
            foreach ( $titles as $field ) {
                $fields[] = $this->_quote . str_replace(
                    $this->_quote, $this->_escaped, $field
                ) . $this->_quote;
            }
            $this->_result .= implode($this->_separator, $fields) . self::NEWLINE;
        }

        foreach ( $this->_rs as $row ) {
            $elts = array();

            foreach ($row as $k => $v) {
                $elts[] = $this->_quote . str_replace(
                    $this->_quote, $this->_escaped, $v
                ) . $this->_quote;
            }

            $this->_result .= implode($this->_separator, $elts) . self::NEWLINE;

            $this->_current_line += 1;

            $this->_write();
        }
        $this->_write(true);
        return $this->_result;
    }

    /**
    * Write export.
    * If a file is defined, export will be outpoutted into it.
    *   If not, it will be returned
    *
    * @param bool $last true if we write the latest line
    *
    * @return void
    */
    private function _write($last=false)
    {
        if (   $last && $this->_file
            || !$last && $this->_file
            && ($this->_current_line % self::BUFLINES) == 0
        ) {
            if ($this->_file === true) {
                echo $this->_result;
            } else {
                fwrite($this->_file, $this->_result);
            }
            $this->_result = '';
        }
    }

    /**
     * Retrieve parameted export name
     *
     * @param string $id Parameted export identifier
     *
     * @return string
     */
    public function getParamedtedExportName($id)
    {
        $xml = simplexml_load_file($this->_parameted_file);
        $xpath = $xml->xpath(
            '/exports/export[@id=\'' . $id . '\'][1]/@name'
        );
        return (string)$xpath[0];
    }

    /**
    * Get al list of all parameted exports
    *
    * @return array
    */
    public function getParametedExports()
    {
        $parameted = array();

        $xml = simplexml_load_file($this->_parameted_file);

        foreach ( $xml->export as $export) {
            if ( !($export['inactive'] == 'inactive') ) {
                $parameted[] = array(
                    'id'          => (string)$export['id'],
                    'name'        => (string)$export['name'],
                    'description' => (string)$export['description']
                );
            }
        }
        return $parameted;
    }

    /**
     * Retrieve a list of already existing exports
     *
     * @return array
     */
    public static function getExistingExports()
    {
        $exports = array();
        $files = glob(self::DEFAULT_DIRECTORY . '*.csv');
        foreach ( $files as $file ) {
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

            $exports[] = array(
                'name'  => str_replace(self::DEFAULT_DIRECTORY, '', $file),
                'size'  => $size,
                'date'  => $mdate
            );
        }
        return $exports;
    }

    /**
     * Remove existing export file
     *
     * @param string $name File name
     *
     * @return boolean
     */
    public function removeExport($name)
    {
        //let's ensure we do not have a path here
        $name = basename($name);
        $filename=self::DEFAULT_DIRECTORY . $name;

        if ( file_exists($filename) ) {
            $removed = unlink($filename);
            return $removed;
        } else {
            Analog::log(
                'Export file ' . $filename .
                ' does not exists, no way to remove it!',
                Analog::ERROR
            );
            return false;
        }

    }

    /**
    * Run selected export
    *
    * @param string $id export's id to run
    *
    * @return string filename used
    */
    public function runParametedExport($id)
    {
        global $zdb;

        $xml = simplexml_load_file($this->_parameted_file);

        $xpath = $xml->xpath(
            '/exports/export[@id=\'' . $id . '\'][not(@inactive)][1]'
        );
        $export = $xpath[0];

        try {
            $result = $zdb->db->query(
                str_replace('galette_', PREFIX_DB, $export->query)
            )->fetchAll(
                \Zend_Db::FETCH_ASSOC
            );

            $filename=self::DEFAULT_DIRECTORY . $export['filename'];

            $fp = fopen($filename, 'w');
            if ( $fp ) {
                $separator = ( $export->separator )
                    ? $export->separator
                    : self::DEFAULT_SEPARATOR;
                $quote = ( $export->quote ) ? $export->quote : self::DEFAULT_QUOTE;
                if ( $export->headers->none ) {
                    //No title
                    $title = false;
                } else {
                    $xpath = $export->xpath('headers/header');
                    if ( count($xpath) == 0 ) {
                        //show titles
                        $title = true;
                    } else {
                        //titles from array
                        foreach ( $xpath as $header ) {
                            $title[] = (string)$header;
                        }
                    }
                }

                $this->export($result, $separator, $quote, $title, $fp);
                fclose($fp);
            } else {
                Analog::log(
                    'File ' . $filename . ' is not writeable.',
                    Analog::ERROR
                );
                return self::FILE_NOT_WRITABLE;
            }
            return $export['filename'];
        } catch (\Exception $e) {
            Analog::log(
                'An error occured while exporting | ' . $e->getMessage(),
                Analog::ERROR
            );
            return self::DB_ERROR;
        }

    }

    /**
    * Accepted separators
    *
    * @return array list of accepted separators
    */
    public function getAcceptedSeparators()
    {
        return $this->_accepted_separators;
    }

    /**
    * Accepted quotes
    *
    * @return array list of accepted quotes
    */
    public function getAcceptedQuotes()
    {
        return $this->_accepted_quotes;
    }
}
