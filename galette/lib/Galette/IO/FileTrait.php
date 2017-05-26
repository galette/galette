<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Files
 *
 * PHP version 5
 *
 * Copyright © 2013-2014 The Galette Team
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
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.8.1 - 2014-09-18
 */

namespace Galette\IO;

use Analog\Analog;

/**
 * Files
 *
 * @category  IO
 * @name      Csv
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.8.1 - 2014-09-18
 */

trait FileTrait
{
    //array keys contain litteral value of each forbidden character
    //(to be used when showing an error).
    //Maybe is there a better way to handle this...
    protected $bad_chars = array(
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

    protected $name;
    protected $dest_dir;
    protected $allowed_extensions = array();
    protected $allowed_mimes = array();
    protected $maxlenght;

    public static $mime_types = array(
        'txt'       => 'text/plain',
        'htm'       => 'text/html',
        'html'      => 'text/html',
        'xhtml'     => 'application/xhtml+xml',
        'xht'       => 'application/xhtml+xml',
        'php'       => 'text/html',
        'css'       => 'text/css',
        'js'        => 'application/javascript',
        'json'      => 'application/json',
        'xml'       => 'application/xml',
        'xslt'      => 'application/xslt+xml',
        'xsl'       => 'application/xml',
        'dtd'       => 'application/xml-dtd',
        'atom'      =>'application/atom+xml',
        'mathml'    =>'application/mathml+xml',
        'rdf'       =>'application/rdf+xml',
        'smi'       =>'application/smil',
        'smil'      =>'application/smil',
        'vxml'      =>'application/voicexml+xml',
        'latex'     =>'application/x-latex',
        'tcl'       =>'application/x-tcl',
        'tex'       =>'application/x-tex',
        'texinfo'   =>'application/x-texinfo',
        'wrl'       =>'model/vrml',
        'wrml'      =>'model/vrml',
        'ics'       =>'text/calendar',
        'ifb'       =>'text/calendar',
        'sgml'      =>'text/sgml',
        'htc'       =>'text/x-component',
        'pgp'       => 'application/pgp-signature',
        'rtf'       => 'application/rtf',
        // images
        'png'       => 'image/png',
        'jpeg'      => 'image/jpeg',
        'jpg'       => 'image/jpeg',
        'gif'       => 'image/gif',
        'bmp'       => 'image/bmp',
        'ico'       => 'image/x-icon',
        'tiff'      => 'image/tiff',
        'tif'       => 'image/tiff',
        'svg'       => 'image/svg+xml',
        'svgz'      => 'image/svg+xml',
        'djvu'      => 'image/vnd.djvu',
        'djv'       => 'image/vnd.djvu',
        // archives
        'zip'       => 'application/zip',
        'rar'       => 'application/x-rar-compressed',
        'tar'       => 'application/x-tar',
        'gz'        => 'application/x-gzip',
        'tgz'       => 'application/x-gzip',
        'bz2'       => 'application/x-bzip2',
        // audio/video
        'mp2'       => 'audio/mpeg',
        'mp3'       => 'audio/mpeg',
        'qt'        => 'video/quicktime',
        'mov'       => 'video/quicktime',
        'mpeg'      => 'video/mpeg',
        'mpg'       => 'video/mpeg',
        'mpe'       => 'video/mpeg',
        'wav'       => 'audio/wav',
        'aiff'      => 'audio/aiff',
        'aif'       => 'audio/aiff',
        'avi'       => 'video/msvideo',
        'wmv'       => 'video/x-ms-wmv',
        'ogg'       => 'application/ogg',
        'flv'       => 'video/x-flv',
        'dvi'       => 'application/x-dvi',
        'au'        => 'audio/basic',
        'snd'       => 'audio/basic',
        'mid'       => 'audio/midi',
        'midi'      => 'audio/midi',
        'm3u'       => 'audio/x-mpegurl',
        'm4u'       => 'video/vnd.mpegurl',
        'ram'       => 'audio/x-pn-realaudio',
        'ra'        => 'audio/x-pn-realaudio',
        'rm'        => 'application/vnd.rn-realmedia',
        // adobe
        'pdf'       => 'application/pdf',
        'psd'       => 'image/vnd.adobe.photoshop',
        'ai'        => 'application/postscript',
        'eps'       => 'application/postscript',
        'ps'        => 'application/postscript',
        'swf'       => 'application/x-shockwave-flash',
        // ms office
        'doc'       => 'application/msword',
        'docx'      => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'       => 'application/vnd.ms-excel',
        'xlsx'      => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'       => 'application/vnd.ms-powerpoint',
        'pptx'      => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'pps'       => 'application/vnd.ms-powerpoint',
        // open office
        'odt'       => 'application/vnd.oasis.opendocument.text',
        'ods'       => 'application/vnd.oasis.opendocument.spreadsheet',
        'odc'       => 'application/vnd.oasis.opendocument.chart',
        'odb'       => 'application/vnd.oasis.opendocument.database',
        'odg'       => 'application/vnd.oasis.opendocument.graphics',
        'odp'       => 'application/vnd.oasis.opendocument.presentation',
    );

    /**
     * Initialization
     *
     * @param string $dest       File destination directory
     * @param array  $extensions Array of permitted extensions
     * @param array  $mimes      Array of permitted mime types
     * @param int    $maxlenght  Maximum lenght for each file
     *
     * @return void
     */
    protected function init(
        $dest,
        $extensions = null,
        $mimes = null,
        $maxlenght = null
    ) {
        if ($dest !== null && substr($dest, -1) !== '/') {
            //normalize path
            $dest .= '/';
        }
        $this->dest_dir = $dest;
        if ($extensions !== null) {
            $this->allowed_extensions = $extensions;
        }
        if ($mimes !== null) {
            $this->allowed_mimes = $mimes;
        }
        if ($maxlenght !== null) {
            $this->maxlenght = $maxlenght;
        } else {
            $this->maxlenght = self::MAX_FILE_SIZE;
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
            $this->dest_dir . $this->name,
            $dest . $this->name
        );
        if ($res === true) {
            $this->dest_dir = $dest;
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

        $this->name = $file['name'];
        $tmpfile = $file['tmp_name'];

        //First, does the file have a valid name?
        $reg = "/^([^" . implode('', $this->bad_chars) . "]+)\.";
        if (count($this->allowed_extensions) > 0) {
            $reg .= "(" . implode('|', $this->allowed_extensions) . ")";
        } else {
            $reg .= "(.*)";
        }
        $reg .= "$/i";
        if (preg_match($reg, $this->name, $matches)) {
            Analog::log(
                '[' . $class . '] Filename and extension are OK, proceed.',
                Analog::DEBUG
            );
            $extension = strtolower($matches[2]);
        } else {
            $erreg = "/^([^" . implode('', $this->bad_chars) . "]+)\.(.*)/i";
            $m = preg_match($erreg, $this->name, $errmatches);

            $err_msg = '[' . $class . '] ';
            if ($m == 1) {
                //ok, we got a good filename and an extension. Extension is bad :)
                $err_msg .= 'Invalid extension for file ' . $this->name . '.';
                $ret = self::INVALID_EXTENSION;
            } else {
                $err_msg = 'Invalid filename `' . $this->name  . '` (Tip: ';
                $err_msg .= preg_replace(
                    '|%s|',
                    htmlentities($this->getBadChars()),
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
        if ($file['size'] > ($this->maxlenght * 1024)) {
            Analog::log(
                '[' . $class . '] File is too big (' . ( $file['size'] * 1024 ) .
                'Ko for maximum authorized ' . ( $this->maxlenght * 1024 ) .
                'Ko',
                Analog::ERROR
            );
            return self::FILE_TOO_BIG;
        } else {
            Analog::log('[' . $class . '] Filesize is OK, proceed', Analog::DEBUG);
        }

        $mime = $this->getMimeType($tmpfile);

        if (count($this->allowed_mimes) > 0
            && !in_array($mime, $this->allowed_mimes)
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

        $new_file = $this->dest_dir . $this->name;

        if (file_exists($new_file)) {
            Analog::log(
                '[' . $class . '] File `' . $new_file . '` already exists',
                Analog::ERROR
            );
            return self::NEW_FILE_EXISTS;
        }

        $in_place = false;
        if ($ajax === true) {
            $in_place = rename($tmpfile, $new_file);
        } else {
            $in_place = move_uploaded_file($tmpfile, $new_file);
        }

        if ($in_place === false) {
            return self::CANT_WRITE;
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
        return $this->dest_dir;
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
        $this->dest_dir = $dir;
    }

    /**
     * Get file name
     *
     * @return string
     */
    public function getFileName()
    {
        return $this->name;
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
        $this->name = $name;
    }

    /**
     * Returns unauthorized characters litteral values quoted, comma separated values
     *
     * @return string comma separated disallowed characters
     */
    public function getBadChars()
    {
        return '`' . implode('`, `', array_keys($this->bad_chars)) . '`';
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
     * Get file mime type
     *
     * @param string $file File
     *
     * @return string
     */
    public static function getMimeType($file)
    {
        $mime = null;
        $class = get_called_class();

        if (function_exists('finfo_open')) {
            Analog::log(
                '[' . $class . '] Function File Info exist ',
                Analog::DEBUG
            );
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file);
            finfo_close($finfo);
        } elseif (function_exists('mime_content_type')) {
            Analog::log(
                '[' . $class . '] Function mime_content_type exist ',
                Analog::DEBUG
            );
            $mime = mime_content_type($file);
        } else {
            Analog::log(
                '[' . $class . '] Search from extension ',
                Analog::DEBUG
            );
            $ext = strtolower(array_pop(explode('.', $file)));
            Analog::log(
                '[' . $class . '] Extension : ' . $ext,
                Analog::DEBUG
            );
            if (array_key_exists($ext, self::$mime_types)) {
                $mime = self::$mime_types[$ext];
            } else {
                $mime = 'application/octet-stream';
            }
        }

        Analog::log(
            '[' . $class . '] Found mimetype : ' . $mime . ' for file ' .  $file,
            Analog::INFO
        );
        return $mime;
    }

    /**
     * Return textual error message
     *
     * @param int $code The error code
     *
     * @return string Localized message
     */
    protected function getErrorMessageFromCode($code)
    {
        $error = _T("An error occued.");

        switch ($code) {
            case self::INVALID_FILENAME:
                $error = _T("File name is invalid, it should not contain any special character or space.");
                break;
            case self::INVALID_EXTENSION:
                $error = preg_replace(
                    '|%s|',
                    $this->getAllowedExts(),
                    _T("File extension is not allowed, only %s files are.")
                );
                break;
            case self::FILE_TOO_BIG:
                $error = preg_replace(
                    '|%d|',
                    $this->maxlenght,
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
            case self::CANT_WRITE:
                $error = _T("Unable to write file or temporary file");
                break;
        }

        return $error;
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
        return $this->getErrorMessageFromCode($code);
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
