<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * CSV imports
 *
 * PHP version 5
 *
 * Copyright © 2013 The Galette Team
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
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Disponible depuis la Release 0.7.6dev - 2013-08-27
 */

namespace Galette\IO;

use Galette\Entity\Adherent as Adherent;
use Galette\Entity\ImportModel as ImportModel;
use Galette\Entity\FieldsConfig as FieldsConfig;
use Analog\Analog as Analog;

/**
 * CSV imports
 *
 * @category  IO
 * @name      Csv
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Disponible depuis la Release 0.7.6dev - 2013-08-27
 */

class CsvIn extends Csv
{
    const DEFAULT_DIRECTORY = GALETTE_IMPORTS_PATH;

    //constants that will not be overrided
    const INVALID_FILENAME = -1;
    const INVALID_EXTENSION = -2;
    const FILE_TOO_BIG = -3;
    const MIME_NOT_ALLOWED = -4;
    const NEW_FILE_EXISTS = -5;
    const INVALID_FILE = -6;
    const DATA_IMPORT_ERROR = -7;
    const CANT_WRITE = -8;
    const MAX_FILE_SIZE = 2048;

    //array keys contain litteral value of each forbidden character
    //(to be used when showing an error).
    //Maybe is there a better way to handle this...
    private $_bad_chars = array(
        '.'    =>    '\.',
        '\\'    =>    '\\\\',
        "'"    =>    "'",
        ' '    =>    ' ',
        '/'    =>    '\/',
        ':'    =>    ':',
        '*'    =>    '\*',
        '?'    =>    '\?',
        '"'    =>    '"',
        '<'    =>    '<',
        '>'    =>    '>',
        '|'    =>    '|'
    );
    protected $allowed_extensions = array('csv', 'txt');
    protected $allowed_mimes = array(
        'csv'    =>    'text/csv',
        'txt'    =>    'text/plain'
    );

    private $_fields;
    private $_default_fields = array(
        'nom_adh',
        'prenom_adh',
        'ddn_adh',
        'adresse_adh',
        'cp_adh',
        'ville_adh',
        'pays_adh',
        'tel_adh',
        'gsm_adh',
        'email_adh',
        'url_adh',
        'prof_adh',
        'pseudo_adh',
        'societe_adh',
        'login_adh',
        'date_crea_adh',
        'id_statut',
        'info_public_adh',
        'info_adh'
    );

    private $_dryrun = true;

    private $_members_fields;
    private $_required;

    /**
     * Default constructor
     */
    public function __construct()
    {
        parent::__construct(self::DEFAULT_DIRECTORY);
    }

    /**
     * Load fields list from database or from default values
     *
     * @return void
     */
    private function _loadFields()
    {
        //at last, we got the defaults
        $this->_fields = $this->_default_fields;

        $model = new ImportModel();
        //we go with default fields if model cannot be loaded
        if ( $model->load() ) {
            $this->_fields = $model->getFields();
        }
    }

    /**
     * Get default fields
     *
     * @return array
     */
    public function getDefaultFields()
    {
        return $this->_default_fields;
    }

    /**
     * Import members from CSV file
     *
     * @param string  $filename       CSV filename
     * @param array   $members_fields Members fields
     * @param boolean $dryrun         Run in dry run mode (do not store in database)
     *
     * @return boolean
     */
    public function import($filename, $members_fields, $dryrun)
    {
        if ( !file_exists(self::DEFAULT_DIRECTORY . '/' . $filename)
            || !is_readable(self::DEFAULT_DIRECTORY . '/' . $filename)
        ) {
            Analog::log(
                'File ' . $filename . ' does not exists or cannot be read.',
                Analog::ERROR
            );
            return false;
        }

        if ( $dryrun === false ) {
            $this->_dryrun = false;
        }

        $this->_loadFields();
        $this->_members_fields = $members_fields;

        if ( !$this->_check($filename) ) {
            return self::INVALID_FILE;
        }

        if ( !$this->_storeMembers($filename) ) {
            return self::DATA_IMPORT_ERROR;
        }

        return true;
    }

    /**
     * Check if input file meet requirements
     *
     * @param string $filename File name
     *
     * @return boolean
     */
    private function _check($filename)
    {
        //deal with mac e-o-l encoding -- see if needed
        //@ini_set('auto_detect_line_endings', true);
        $handle = fopen(self::DEFAULT_DIRECTORY . '/' . $filename, 'r');
        if (! $handle) {
            Analog::log(
                'File ' . $filename . ' cannot be open!',
                Analog::ERROR
            );
            $this->addError(
                str_replace(
                    '%filename',
                    $filename,
                    _T('File %filename cannot be open!')
                )
            );
            return false;
        }

        if ( $handle !== false ) {

            $cnt_fields = count($this->_fields);

            //check required fields
            $fc = new FieldsConfig(Adherent::TABLE, $this->_members_fields);
            $config_required = $fc->getRequired();
            $this->_required = array();

            foreach ( array_keys($config_required) as $field ) {
                if ( in_array($field, $this->_fields) ) {
                    $this->_required[$field] = $field;
                }
            }

            $row = 0;
            while ( ($data = fgetcsv(
                $handle,
                1000,
                self::DEFAULT_SEPARATOR,
                self::DEFAULT_QUOTE
            )) !== false) {

                //check fields count
                $count = count($data);
                if ( $count != $cnt_fields ) {
                    $this->addError(
                        str_replace(
                            array('%should_count', '%count', '%row'),
                            array($cnt_fields, $count, $row),
                            _T("Fields count mismatch... There should be %should_count fields and there are %count (row %row)")
                        )
                    );
                    return false;
                }

                //check required fields
                if ( $row > 0 ) {
                    //header line is the first one. Here comes data
                    $col = 0;
                    foreach ( $data as $column ) {
                        if ( in_array($this->_fields[$col], $this->_required)
                            && trim($column) == ''
                        ) {
                            $this->addError(
                                str_replace(
                                    array('%field', '%row'),
                                    array($this->_fields[$col], $row),
                                    _T("Field %field is required, but missing in row %row")
                                )
                            );
                            return false;
                        }
                        $col++;
                    }
                }

                $row++;
            }
            fclose($handle);

            if ( !($row > 1) ) {
                //no data in file, just headers line
                $this->addError(
                    _T("File is empty!")
                );
                return false;
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * Store members in database
     *
     * @param string $filename CSV filename
     *
     * @return boolean
     */
    private function _storeMembers($filename)
    {
        $handle = fopen(self::DEFAULT_DIRECTORY . '/' . $filename, 'r');

        $row = 0;

        try {
            while ( ($data = fgetcsv(
                $handle,
                1000,
                self::DEFAULT_SEPARATOR,
                self::DEFAULT_QUOTE
            )) !== false) {
                if ( $row > 0 ) {
                    $col = 0;
                    $values = array();
                    foreach ( $data as $column ) {
                        $values[$this->_fields[$col]] = $column;
                        $col++;
                    }
                    //import member itself
                    $member = new Adherent();
                    //check for empty creation date
                    if ( isset($values['date_crea_adh']) && trim($values['date_crea_adh']) === '' ) {
                        unset($values['date_crea_adh']);
                    }
                    $valid = $member->check($values, $this->_required, null);
                    if ( $valid === true ) {
                        if ( $this->_dryrun === false ) {
                            $store = $member->store();
                            if ( $store !== true ) {
                                $this->addError(
                                    str_replace(
                                        array('%row', '%name'),
                                        array($row, $member->sname),
                                        _T("An error occured storing member at row %row (%name):")
                                    )
                                );
                                return false;
                            }
                        }
                    } else {
                        $this->addError(
                            str_replace(
                                array('%row', '%name'),
                                array($row, $member->sname),
                                _T("An error occured storing member at row %row (%name):")
                            )
                        );
                        if ( is_array($valid) ) {
                            foreach ( $valid as $e ) {
                                $this->addError($e);
                            }
                        }
                        return false;
                    }
                }
                $row++;
            }
            return true;
        } catch ( \Exception $e ) {
            $this->addError($e->getMessage());
        }

        return false;
    }

    /**
     * Stores an file on the disk and in the database
     *
     * @param object  $file the uploaded file
     * @param boolean $ajax If the file cames from an ajax call (dnd)
     *
     * @return true|false result of the storage process
     */
    public function store($file, $ajax = false)
    {
        /** TODO:
            - fix max size (by preferences ?)
        */
        $class = get_class($this);

        $name = $file['name'];
        $tmpfile = $file['tmp_name'];

        //First, does the file have a valid name?
        $reg = "/^(.[^" . implode('', $this->_bad_chars) . "]+)\.(" .
            implode('|', $this->allowed_extensions) . ")$/i";
        if ( preg_match($reg, $name, $matches) ) {
            Analog::log(
                '[' . $class . '] Filename and extension are OK, proceed.',
                Analog::DEBUG
            );
            $extension = strtolower($matches[2]);
        } else {
            $erreg = "/^(.[^" . implode('', $this->_bad_chars) . "]+)\.(.*)/i";
            $m = preg_match($erreg, $name, $errmatches);

            $err_msg = '[' . $class . '] ';
            if ( $m == 1 ) {
                //ok, we got a good filename and an extension. Extension is bad :)
                $err_msg .= 'Invalid extension for file ' . $name . '.';
                $ret = self::INVALID_EXTENSION;
            } else {
                $err_msg = 'Invalid filename `' . $name  . '` (Tip: ';
                $err_msg .= preg_replace(
                    '|%s|',
                    htmlentities($this->getbadChars()),
                    "file name should not contain any of: %s). "
                );
                $ret = self::INVALID_FILENAME;
            }

            Analog::log(
                $err_msg,
                Analog::ERROR
            );
            return $ret;
        }

        //Second, let's check file size
        if ( $file['size'] > ( $class::MAX_FILE_SIZE * 1024 ) ) {
            Analog::log(
                '[' . $class . '] File is too big (' . ( $file['size'] * 1024 ) .
                'Ko for maximum authorized ' . ( $class::MAX_FILE_SIZE * 1024 ) .
                'Ko',
                Analog::ERROR
            );
            return self::FILE_TOO_BIG;
        } else {
            Analog::log('[' . $class . '] Filesize is OK, proceed', Analog::DEBUG);
        }

        //identify MIME type
        if (function_exists("finfo_open")) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tmpfile);
            finfo_close($finfo);
        } else {
            // deprecated
            $mime = mime_content_type($tmpfile);
        }

        if ( !in_array($mime, $this->allowed_mimes) ) {
            Analog::log(
                '[' . $class . '] Mimetype `' . $mime . '` not allowed',
                Analog::ERROR
            );
            return self::MIME_NOT_ALLOWED;
        } else {
            Analog::log(
                '[' . $class . '] Mimetype is allowed, proceed',
                Analog::DEBUG
            );
        }

        $new_file = self::DEFAULT_DIRECTORY . $name;

        if ( file_exists($new_file) ) {
            Analog::log(
                '[' . $class . '] File `' . $new_file . '` already exists',
                Analog::ERROR
            );
            return self::NEW_FILE_EXISTS;
        }

        $in_place = false;
        if ( $ajax === true ) {
            $in_place = rename($tmpfile, $new_file);
        } else {
            $in_place = move_uploaded_file($tmpfile, $new_file);
        }

        if ( $in_place === false ) {
            return self::CANT_WRITE;
        }
        return $in_place;
    }

    /**
     * Returns allowed extensions
     *
     * @return string comma separated allowed extensiosn
     */
    public function getAllowedExts()
    {
        return implode(', ', $this->allowed_extensions);
    }

    /**
     * Return the array of allowed mime types
     *
     * @return array
     */
    public function getAllowedMimeTypes()
    {
        return $this->allowed_mimes;
    }

    /**
     * Return textual error message
     *
     * @param int $code The error code
     *
     * @return string Localized message
     */
    public function getErrorMessage($code)
    {
        $error = _T("An error occued.");
        switch( $code ) {
        case self::INVALID_FILENAME:
            $error = _T("File name is invalid, it should not contain any special character or space.");
            break;
        case self::INVALID_EXTENSION:
            $error = preg_replace(
                '|%s|',
                $this->getAllowedExts(),
                _T("- File extension is not allowed, only %s files are.")
            );
            break;
        case self::FILE_TOO_BIG:
            $error = preg_replace(
                '|%d|',
                self::MAX_FILE_SIZE,
                _T("File is too big. Maximum allowed size is %dKo")
            );
            break;
        case self::MIME_NOT_ALLOWED:
            /** FIXME: should be more descriptive */
            $error = _T("Mime-Type not allowed");
            break;
        case self::NEW_FILE_EXISTS:
            $error = _T("A file with that name already exists!");
            break;
        case self::INVALID_FILE:
            $error = _T("File does not comply with requirements.");
            break;
        case self::DATA_IMPORT_ERROR:
            $error = _T("An error occured while importing members");
            break;
        case self::CANT_WRITE:
            $error = _T("Unable to write file or temporary file");
            break;
        }
        return $error;
    }

    /**
     * Return textual error message send by PHP after upload attempt
     *
     * @param int $error_code The error code
     *
     * @return string Localized message
     */
    public function getPhpErrorMessage($error_code)
    {
        switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return _T("The uploaded file exceeds the upload_max_filesize directive in php.ini");
        case UPLOAD_ERR_FORM_SIZE:
            return _T("The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form");
        case UPLOAD_ERR_PARTIAL:
            return _T("The uploaded file was only partially uploaded");
        case UPLOAD_ERR_NO_FILE:
            return _T("No file was uploaded");
        case UPLOAD_ERR_NO_TMP_DIR:
            return _T("Missing a temporary folder");
        case UPLOAD_ERR_CANT_WRITE:
            return _T("Failed to write file to disk");
        case UPLOAD_ERR_EXTENSION:
            return _T("File upload stopped by extension");
        default:
            return _T("Unknown upload error");
        }
    }
}
