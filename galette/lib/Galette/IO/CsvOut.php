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

namespace Galette\IO;

use ArrayObject;
use Laminas\Db\ResultSet\ResultSet;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use Analog\Analog;
use Laminas\Db\Adapter\Adapter;

/**
 * CSV exports
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class CsvOut extends Csv
{
    public const DEFAULT_DIRECTORY = GALETTE_EXPORTS_PATH;

    private string $legacy_parameted_file = 'exports.xml';
    private string $parameted_file = 'exports.yaml';

    /**
     * Default constructor
     */
    public function __construct()
    {
        parent::__construct(self::DEFAULT_DIRECTORY);
        if (file_exists(GALETTE_CONFIG_PATH . $this->parameted_file)) {
            $this->parameted_file = GALETTE_CONFIG_PATH . $this->parameted_file;
        } else {
            $this->parameted_file = GALETTE_SYSCONFIG_PATH . $this->parameted_file;
        }
        $this->legacy_parameted_file = GALETTE_CONFIG_PATH . $this->legacy_parameted_file;
    }

    /**
     * Export Array result set to CSV
     *
     * @param ResultSet|array<int,mixed> $rs        Results as an array
     * @param string                     $separator The CSV separator (either '\t', ';' or ','
     *                                              are accepted)
     * @param string                     $quote     how does fields should be quoted
     * @param array<string>|bool         $titles    does export shows column titles or not.
     *                                              Defaults to false.
     * @param resource|false             $file      export to a file on disk. A file pointer
     *                                              should be passed here. Defaults to false.
     *
     * @return string CSV result
     */
    public function export(
        ResultSet|array $rs,
        string $separator,
        string $quote,
        array|bool $titles = false,
        mixed $file = false //FIXME: replace resource from fopen() with SplFileObject
    ): string {
        //switch back to the default separator if not in accepted_separators array
        if (!in_array($separator, $this->accepted_separators)) {
            $separator = self::DEFAULT_SEPARATOR;
        }
        //switch back to the default quote if not in accepted_quotes array
        if (!in_array($quote, $this->accepted_quotes)) {
            $quote = self::DEFAULT_QUOTE;
        }

        $this->result = '';
        $results = [];
        if (count($rs) > 0) {
            foreach ($rs as $row) {
                $results[] = $row;
            }
        }
        $this->separator = $separator;
        $this->quote = $quote;
        //dubbing quote for escaping
        $this->escaped = $quote . $quote;
        $this->file = $file;
        $this->current_line = 0;

        $fields = array();
        if ($titles === true) {
            $row = $results[0];
            foreach (array_keys((array)$row) as $field) {
                $fields[] = $this->quote . str_replace(
                    $this->quote,
                    $this->escaped,
                    (string)$field
                ) . $this->quote;
            }
            $this->result .= implode($this->separator, $fields) . self::NEWLINE;
        } elseif (is_array($titles) && count($titles) > 1) {
            foreach ($titles as $field) {
                $field = str_replace(
                    array(':', '&nbsp;'),
                    '',
                    $field
                );
                $fields[] = $this->quote . str_replace(
                    $this->quote,
                    $this->escaped,
                    $field
                ) . $this->quote;
            }
            $this->result .= implode($this->separator, $fields) . self::NEWLINE;
        }

        foreach ($results as $row) {
            $elts = array();

            if (is_array($row) || is_object($row)) {
                foreach ($row as $v) {
                    $elts[] = $this->quote . str_replace(
                        $this->quote,
                        $this->escaped,
                        (string)($v ?? '')
                    ) . $this->quote;
                }

                $this->result .= implode($this->separator, $elts) . self::NEWLINE;

                $this->current_line += 1;

                $this->write();
            }
        }
        $this->write(true);
        return $this->result;
    }

    /**
     * Write export.
     * If a file is defined, export will be outputted into it.
     *   If not, it will be returned
     *
     * @param bool $last true if we write the latest line
     *
     * @return void
     */
    private function write(bool $last = false): void
    {
        if (
            $last && $this->file
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
     * @return ?string
     */
    public function getParamedtedExportName(string $id): ?string
    {
        //check first in YAML configuration file
        $data = Yaml::parseFile($this->parameted_file);
        foreach ($data as $export) {
            if (!isset($export['inactive']) || $export['inactive']) {
                $keys = array_keys($export);
                $anid = array_shift($keys);
                if ($anid == $id) {
                    return $export['name'];
                }
            }
        }

        //if id has not been found, look for it in legacy XML configuration file
        if (file_exists($this->legacy_parameted_file)) {
            $xml = simplexml_load_file($this->legacy_parameted_file);
            $xpath = $xml->xpath(
                '/exports/export[@id=\'' . $id . '\'][1]/@name'
            );
            return (string)$xpath[0];
        }

        return null;
    }

    /**
     * Get al list of all parameted exports
     *
     * @return array<string, mixed>
     */
    public function getParametedExports(): array
    {
        $parameted = [];

        //first, load legacy config; if exists
        if (file_exists($this->legacy_parameted_file)) {
            $xml = simplexml_load_file($this->legacy_parameted_file);

            foreach ($xml->export as $export) {
                if (!($export['inactive'] == 'inactive')) {
                    $id = (string)$export['id'];
                    $parameted[$id] = array(
                        'id' => $id,
                        'name' => (string)$export['name'],
                        'description' => (string)$export['description']
                    );
                }
            }
        }

        //then, load config from YAML file
        $data = Yaml::parseFile($this->parameted_file);
        foreach ($data as $export) {
            if (!isset($export['inactive']) || $export['inactive']) {
                $keys = array_keys($export);
                $id = array_shift($keys);
                $parameted[$id] = [
                    'id'    => $id,
                    'name' => $export['name'],
                    'description' => $export['description']
                ];
            }
        }

        return $parameted;
    }

    /**
     * Run selected export parameted as XML
     *
     * @param string $id export's id to run
     *
     * @return string|int filename used or error code
     */
    private function runXmlParametedExport(string $id): string|int
    {
        global $zdb;

        $xml = simplexml_load_file($this->legacy_parameted_file);

        $xpath = $xml->xpath(
            '/exports/export[@id=\'' . $id . '\'][not(@inactive)][1]'
        );
        $export = $xpath[0];

        try {
            $results = $zdb->db->query(
                str_replace('galette_', PREFIX_DB, (string)$export->query),
                Adapter::QUERY_MODE_EXECUTE
            );

            $filename = self::DEFAULT_DIRECTORY . $export['filename'];

            $fp = fopen($filename, 'w');
            if ($fp) {
                $separator = ($export->separator)
                    ? $export->separator
                    : self::DEFAULT_SEPARATOR;
                $quote = ($export->quote) ? $export->quote : self::DEFAULT_QUOTE;
                if ($export->headers->none) {
                    //No title
                    $title = false;
                } else {
                    $xpath = $export->xpath('headers/header');
                    if (count($xpath) == 0) {
                        //show titles
                        $title = true;
                    } else {
                        //titles from array
                        foreach ($xpath as $header) {
                            $title[] = (string)$header;
                        }
                    }
                }

                $this->export($results, $separator, $quote, $title, $fp);
                fclose($fp);
            } else {
                Analog::log(
                    'File ' . $filename . ' is not writeable.',
                    Analog::ERROR
                );
                return self::FILE_NOT_WRITABLE;
            }
            return (string)$export['filename'];
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred while exporting | ' . $e->getMessage(),
                Analog::ERROR
            );
            return self::DB_ERROR;
        }
    }

    /**
     * Run selected export parameted as YAML
     *
     * @param string $id export's id to run
     *
     * @return string|int|false filename used, error code or failure
     */
    private function runYamlParametedExport(string $id): string|int|false
    {
        global $zdb;

        $export = [];
        $data = Yaml::parseFile($this->parameted_file);
        foreach ($data as $anexport) {
            if (!isset($anexport['inactive']) || $anexport['inactive']) {
                $keys = array_keys($anexport);
                $anid = array_shift($keys);
                if ($anid == $id) {
                    $export = $anexport;
                }
            }
        }

        if ($export['inactive'] ?? false) {
            return false;
        }

        try {
            $results = $zdb->db->query(
                str_replace('galette_', PREFIX_DB, $export['query']),
                Adapter::QUERY_MODE_EXECUTE
            );

            $filename = self::DEFAULT_DIRECTORY . $export['filename'];

            $fp = fopen($filename, 'w');
            if ($fp) {
                $separator = $export['separator'] ?? self::DEFAULT_SEPARATOR;
                $quote = $export['quote'] ?? self::DEFAULT_QUOTE;
                $title = [];
                if (isset($export['headers'])) {
                    if ($export['headers'] === false) {
                        //No title
                        $title = false;
                    } else {
                        foreach ($export['headers'] as $header) {
                            $title[] = (string)$header;
                        }
                    }
                }

                $this->export($results, $separator, $quote, $title, $fp);
                fclose($fp);
            } else {
                Analog::log(
                    'File ' . $filename . ' is not writeable.',
                    Analog::ERROR
                );
                return self::FILE_NOT_WRITABLE;
            }
            return $export['filename'];
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred while exporting | ' . $e->getMessage(),
                Analog::ERROR
            );
            return self::DB_ERROR;
        }
    }

    /**
     * Run selected export
     *
     * @param string $id export's id to run
     *
     * @return ?string filename used
     */
    public function runParametedExport(string $id): ?string
    {
        //try first to run from YAML configuration file
        $run = $this->runYamlParametedExport($id);
        if ($run !== null && $run !== false) {
            return $run;
        }

        //if nothing has been run yet, look into legacy XML configuration file
        if (file_exists($this->legacy_parameted_file)) {
            return $this->runXmlParametedExport($id);
        }

        return null;
    }
}
