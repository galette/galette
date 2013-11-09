<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Files
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
 * @since     Available since 0.7.7dev - 2013-09-06
 */

namespace Galette\IO;

use Analog\Analog as Analog;

/**
 * Files
 *
 * @category  IO
 * @name      Csv
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.7dev - 2013-09-06
 */

class File
{
    //constants that will not be overrided
    const INVALID_FILENAME = -1;
    const INVALID_EXTENSION = -2;
    const FILE_TOO_BIG = -3;
    const MIME_NOT_ALLOWED = -4;
    const NEW_FILE_EXISTS = -5;
    const INVALID_FILE = -6;
    const MAX_FILE_SIZE = 1024;

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

    private $_name;
    private $_dest_dir;
    private $_allowed_extensions = array();
    private $_allowed_mimes = array();
    private $_maxlenght;

    /**
     * Default constructor
     *
     * @param string $dest       File destination directory
     * @param array  $extensions Array of permitted extensions
     * @param array  $mimes      Array of permitted mime types
     * @param int    $maxlentgh  Maximum lenght for each file
     */
    public function __construct($dest, $extensions = null, $mimes = null, $maxlentgh = null)
    {
        if ( substr($dest, -1) !== '/' ) {
            //normalize path
            $dest .= '/';
        }
        $this->_dest_dir = $dest;
        if ( $extensions !== null ) {
            $this->_allowed_extensions = $extensions;
        }
        if ( $mimes !== null ) {
            $this->_allowed_mimes = $mimes;
        }
        if ( $maxlentgh !== null ) {
            $this->_maxlenght = $maxlentgh;
        } else {
            $this->_maxlenght = self::MAX_FILE_SIZE;
        }
    }

    /**
     * Copy existing file to new Location
     *
     * @param string $dest Destination directory
     *
     * @return boolean
     */
    public function copyTo($dest)
    {
        $res = copy(
            $this->_dest_dir . $this->_name,
            $dest . $this->_name
        );
        if ( $res === true ) {
            $this->_dest_dir = $dest;
        }
        return $res;
    }

    /**
     * Stores an file on the disk
     *
     * @param object  $file the uploaded file
     * @param boolean $ajax If the file cames from an ajax call (dnd)
     *
     * @return true|false result of the storage process
     */
    public function store($file, $ajax = false)
    {
        $class = get_class($this);

        $this->_name = $file['name'];
        $tmpfile = $file['tmp_name'];

        //First, does the file have a valid name?
        $reg = "/^(.[^" . implode('', $this->_bad_chars) . "]+)\.";
        if ( count($this->_allowed_extensions) > 0 ) {
            $reg .= "(" . implode('|', $this->_allowed_extensions) . ")";
        } else {
            $reg .= "(.*)";
        }
        $reg .= "$/i";
        if ( preg_match($reg, $this->_name, $matches) ) {
            Analog::log(
                '[' . $class . '] Filename and extension are OK, proceed.',
                Analog::DEBUG
            );
            $extension = strtolower($matches[2]);
        } else {
            $erreg = "/^(.[^" . implode('', $this->_bad_chars) . "]+)\.(.*)/i";
            $m = preg_match($erreg, $this->_name, $errmatches);

            $err_msg = '[' . $class . '] ';
            if ( $m == 1 ) {
                //ok, we got a good filename and an extension. Extension is bad :)
                $err_msg .= 'Invalid extension for file ' . $this->_name . '.';
                $ret = self::INVALID_EXTENSION;
            } else {
                $err_msg = 'Invalid filename `' . $this->_name  . '` (Tip: ';
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
        if ( $file['size'] > ( $this->_maxlenght * 1024 ) ) {
            Analog::log(
                '[' . $class . '] File is too big (' . ( $file['size'] * 1024 ) .
                'Ko for maximum authorized ' . ( $this->_maxlenght * 1024 ) .
                'Ko',
                Analog::ERROR
            );
            return self::FILE_TOO_BIG;
        } else {
            Analog::log('[' . $class . '] Filesize is OK, proceed', Analog::DEBUG);
        }

        $mime = mime_content_type($tmpfile);

        if ( count($this->_allowed_mimes) > 0
            && !in_array($mime, $this->_allowed_mimes)
        ) {
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

        $new_file = $this->_dest_dir . $this->_name;

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

        return $in_place;
    }

    /**
     * Get destination dir
     *
     * @return string
     */
    public function getDestDir()
    {
        return $this->_dest_dir;
    }

    /**
     * Set destination directory
     *
     * @param string $dir Directory
     *
     * @return void
     */
    public function setDestDir($dir)
    {
        $this->_dest_dir = $dir;
    }

    /**
     * Get file name
     *
     * @return string
     */
    public function getFileName()
    {
        return $this->_name;
    }

    /**
     * Set file name
     *
     * @param string $name file name
     *
     * @return void
     */
    public function setFileName($name)
    {
        $this->_name = $name;
    }

    /**
     * Returns unauthorized characters litteral values quoted, comma separated values
     *
     * @return string comma separated disallowed characters
     */
    public function getBadChars()
    {
        $ret = '';
        foreach ( $this->_bad_chars as $char=>$regchar ) {
            $ret .= '`' . $char . '`, ';
        }
        return $ret;
    }

    /**
     * Returns allowed extensions
     *
     * @return string comma separated allowed extensiosn
     */
    public function getAllowedExts()
    {
        return implode(', ', $this->_allowed_extensions);
    }

    /**
     * Return the array of allowed mime types
     *
     * @return array
     */
    public function getAllowedMimeTypes()
    {
        return $this->_allowed_mimes;
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
                $this->_maxlenght,
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
        case self::DATA_IMPORT_ERROR:
            $error = _T("An error occured while importing members");
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
