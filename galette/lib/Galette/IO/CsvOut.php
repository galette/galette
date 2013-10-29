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

class CsvOut extends Csv
{
    const DEFAULT_DIRECTORY = GALETTE_EXPORTS_PATH;

    private $_rs;
    private $_parameted_path;
    private $_parameted_file = 'exports.xml';

    /**
     * Default constructor
     */
    public function __construct()
    {
        parent::__construct(self::DEFAULT_DIRECTORY);
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
        if ( !in_array($separator, $this->accepted_separators) ) {
            $separator = self::DEFAULT_SEPARATOR;
        }
        //switch back to the default quote if not in accepted_quotes array
        if ( !in_array($quote, $this->accepted_quotes) ) {
            $quote = self::DEFAULT_QUOTE;
        }

        $this->result = '';
        $this->_rs = $rs;
        $this->max = count($this->_rs);
        $this->separator = $separator;
        $this->quote = $quote;
        //dubbing quote for escaping
        $this->escaped = $quote . $quote;
        $this->file = $file;
        $this->current_line = 0;

        $fields = array();
        if ( $titles && !count($titles>1) ) {
            foreach ( array_key($this->_rs) as $field ) {
                $fields[] = $this->quote . str_replace(
                    $this->quote, $this->escaped, $field
                ) . $this->quote;
            }
            $this->result .= implode($this->separator, $fields) . self::NEWLINE;
        } else if ( $titles && is_array($titles) && count($titles)>1 ) {
            foreach ( $titles as $field ) {
                $fields[] = $this->quote . str_replace(
                    $this->quote, $this->escaped, $field
                ) . $this->quote;
            }
            $this->result .= implode($this->separator, $fields) . self::NEWLINE;
        }

        foreach ( $this->_rs as $row ) {
            $elts = array();

            if ( is_array($row) || is_object($row) ) {
                foreach ($row as $k => $v) {
                    $elts[] = $this->quote . str_replace(
                        $this->quote, $this->escaped, $v
                    ) . $this->quote;
                }

                $this->result .= implode($this->separator, $elts) . self::NEWLINE;

                $this->current_line += 1;

                $this->_write();
            }
        }
        $this->_write(true);
        return $this->result;
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
        if (   $last && $this->file
            || !$last && $this->file
            && ($this->current_line % self::BUFLINES) == 0
        ) {
            if ($this->file === true) {
                echo $this->result;
            } else {
                fwrite($this->file, $this->result);
            }
            $this->result = '';
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
}
