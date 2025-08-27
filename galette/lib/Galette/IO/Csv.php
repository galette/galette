<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

use Analog\Analog;

/**
 * CSV files
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

abstract class Csv
{
    public const NEWLINE = "\r\n";
    public const BUFLINES = 100;

    public const DEFAULT_SEPARATOR = ';';
    public const DEFAULT_QUOTE = '"';
    public const DEFAULT_ESCAPE = '\\';

    public const FILE_NOT_WRITABLE = -1;
    public const DB_ERROR = -2;

    protected string $separator;
    protected string $quote;
    protected string $escaped;
    protected mixed $file;
    protected string $result;
    protected int $current_line;

    /** @var array<string>  */
    protected array $extensions = array('csv');

    /** @var array<string>  */
    protected array $accepted_separators = array(
        ',',
        ';',
        '\t'
    );

    /** @var array<string>  */
    protected array $accepted_quotes = array(
        '"',
        "'"
    );

    /** @var array<string> */
    private array $errors = array();
    private string $default_directory;

    /**
     * Default constructor
     *
     * @param string $default_dir Default directory
     */
    public function __construct(string $default_dir)
    {
        $this->default_directory = $default_dir;
    }

    /**
     * Retrieve a list of already existing CSV files
     *
     * @return array<int, array<string,string>>
     */
    public function getExisting(): array
    {
        $csv_files = array();
        $files = glob(
            $this->default_directory . '*.{' .
            implode(',', $this->extensions) . '}',
            GLOB_BRACE
        );
        foreach ($files as $file) {
            if ($file === $this->default_directory . 'readme.txt') {
                continue;
            }
            $mdate = date(__("Y-m-d H:i:s"), filemtime($file));

            $raw_size = filesize($file);
            $size = 0;
            if ($raw_size >= 1024 * 1024 * 1024) { // Go
                $size = round(($raw_size / 1024) / 1024 / 1024, 2) . ' Go';
            } elseif ($raw_size >= 1024 * 1024) { // Mo
                $size = round(($raw_size / 1024) / 1024, 2) . ' Mo';
            } elseif ($raw_size >= 1024) { // ko
                $size = round(($raw_size / 1024), 2) . ' Ko';
            } else { // octets
                $size = $raw_size . ' octets';
            }

            $csv_files[] = array(
                'name'  => str_replace($this->default_directory, '', $file),
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
    public function remove(string $name): bool
    {
        //let's ensure we do not have a path here
        $name = basename($name);
        $filename = $this->default_directory . $name;

        if (file_exists($filename)) {
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
     * @return array<string> list of accepted separators
     */
    public function getAcceptedSeparators(): array
    {
        return $this->accepted_separators;
    }

    /**
     * Accepted quotes
     *
     * @return array<string> list of accepted quotes
     */
    public function getAcceptedQuotes(): array
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
    public function addError(string $msg): void
    {
        $class = get_class($this);
        Analog::log(
            '[' . $class . '] ' . $msg,
            Analog::ERROR
        );
        $this->errors[] = $msg;
    }

    /**
     * Get errors
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Reset errors
     *
     * @return void
     */
    protected function resetErrors(): void
    {
        $this->errors = [];
    }
}
