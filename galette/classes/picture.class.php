<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Picture handling
 *
 * PHP version 5
 *
 * Copyright © 2006-2010 The Galette Team
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
 * @category  Classes
 * @package   Galette
 *
 * @author    Frédéric Jaqcuot <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2006-2010 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 */

/**
 * Picture handling
 *
 * @name Picture
 * @category  Classes
 * @package   Galette
 * @author    Frédéric Jaqcuot <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2006-2010 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */
class Picture
{
    //constants that will not be overrided
    const INVALID_FILE = -1;
    const FILE_TOO_BIG = -2;
    const MIME_NOT_ALLOWED = -3;
    const SQL_ERROR = -4;
    const SQL_BLOB_ERROR = -5;
    //constants that can be overrided
    //(do not use self::CONSTANT, but get_class[$this]::CONSTANT)
    const MAX_FILE_SIZE = 1024;
    const TABLE = 'pictures';
    const PK = Adherent::PK;

    /*private $_bad_chars = array(
        '\.', '\\\\', "'", ' ', '\/', ':', '\*', '\?', '"', '<', '>', '|'
    );*/
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
    private $_allowed_extensions = array('jpeg', 'jpg', 'png', 'gif');
    private $_allowed_mimes = array(
        'jpg'    =>    'image/jpeg',
        'png'    =>    'image/png',
        'gif'    =>    'image/gif'
    );

    protected $tbl_prefix = '';

    protected $id;
    protected $height;
    protected $width;
    protected $optimal_height;
    protected $optimal_width;
    protected $file_path;
    protected $format;
    protected $mime;
    protected $has_picture = true;
    protected $store_path = '../photos/';
    protected $max_width = 200;
    protected $max_height = 200;
    protected $custom = true;

    /**
    * Default constructor.
    *
    * @param int $id_adh the id of the member
    */
    public function __construct( $id_adh='' )
    {
        // '!==' needed, otherwise ''==0
        if ($id_adh!=='') {
            $this->id = $id_adh;

            //if file does not exists on the FileSystem, check for it in the database
            if ( !$this->_checkFileOnFS() ) {
                $this->_checkFileInDB();
            }
        }

        // if we still have no picture, take the default one
        if ( $this->file_path=='' ) {
            $this->getDefaultPicture();
        }

        //we should not have an empty file_path, but...
        if ( $this->file_path !== '' ) {
            $this->_setSizes();
        }
    }

    /**
    * "Magic" function called on unserialize
    *
    * @return void
    */
    public function __wakeup()
    {
        //if file has been deleted since we store our object in the session,
        //we try to retrieve it
        if ( !$this->_checkFileOnFS() ) {
            //if file does not exists on the FileSystem,
            //check for it in the database
            $this->_checkFileInDB();
        }

        // if we still have no picture, take the default one
        if ( $this->file_path=='' ) {
            $this->getDefaultPicture();
        }

        //we should not have an empty file_path, but...
        if ( $this->file_path !== '' ) {
            $this->_setSizes();
        }
    }

    /**
    * Check if current file is present on the File System
    *
    * @return boolean true if file is present on FS, false otherwise
    */
    private function _checkFileOnFS()
    {
        $file_wo_ext = dirname(__FILE__).'/' . $this->store_path . $this->id;
        if ( file_exists($file_wo_ext . '.jpg') ) {
            $this->file_path = $file_wo_ext . '.jpg';
            $this->format = 'jpg';
            $this->mime = 'image/jpeg';
            return true;
        } elseif ( file_exists($file_wo_ext . '.png') ) {
            $this->file_path = $file_wo_ext . '.png';
            $this->format = 'png';
            $this->mime = 'image/png';
            return true;
        } elseif ( file_exists($file_wo_ext . '.gif') ) {
            $this->file_path = $file_wo_ext . '.gif';
            $this->format = 'gif';
            $this->mime = 'image/gif';
            return true;
        }
        return false;
    }

    /**
    * Check if current file is present in the database,
    *   and copy it to the File System
    *
    * @return boolean true if file is present in the DB, false otherwise
    */
    private function _checkFileInDB()
    {
        global $mdb;
        $sql = $this->getCheckFileQuery();

        $result = $mdb->query($sql);
        if ( MDB2::isError($result) ) {
            return false;
        }

        if ( $result->numRows() > 0 ) {
            // we must regenerate the picture file
            $pic = $result->fetchRow();
            $file_wo_ext = dirname(__FILE__).'/' . $this->store_path . $this->id;
            $f = fopen($file_wo_ext . '.' . $pic->format, 'wb');
            fwrite($f, $pic->picture);
            fclose($f);
            $this->format = $pic->format;
            switch($this->format) {
            case 'jpg':
                $this->mime = 'image/jpeg';
                break;
            case 'png':
                $this->mime = 'image/png';
                break;
            case 'gif':
                $this->mime = 'image/gif';
                break;
            }
            $this->file_path = $file_wo_ext . '.' . $this->format;
            return true;
        }
        return false;
    }

    /**
    * Returns the relevant query to check if picture exists in database.
    *
    * @return string SELECT query
    */
    protected function getCheckFileQuery()
    {
        $class = get_class($this);
        //echo static::PK . '|' . $class::PK . "\n";
        return 'SELECT picture, format FROM ' . PREFIX_DB . $this->tbl_prefix .
            $class::TABLE . ' WHERE ' . $class::PK . '=\'' . $this->id . '\'';
    }

    /**
    * Gets the default picture to show, anyways
    *
    * @return void
    */
    protected function getDefaultPicture()
    {
        $this->file_path = _CURRENT_TEMPLATE_PATH . 'images/default.png';
        $this->format = 'png';
        $this->mime = 'image/png';
        $this->has_picture = false;
    }

    /**
    * Set picture sizes
    *
    * @return void
    */
    private function _setSizes()
    {
        list($width, $height) = getimagesize($this->file_path);
        $this->height = $height;
        $this->width = $width;
        $this->optimal_height = $height;
        $this->optimal_width = $width;

        if ($this->height > $this->width) {
            if ($this->height > $this->max_height) {
                $ratio = $this->max_height / $this->height;
                $this->optimal_height = $this->max_height;
                $this->optimal_width = $this->width * $ratio;
            }
        } else {
            if ($this->width > $this->max_width) {
                $ratio = $this->max_width / $this->width;
                $this->optimal_width = $this->max_width;
                $this->optimal_height = $this->height * $ratio;
            }
        }
    }

    /**
    * Set header and displays the picture.
    *
    * @return object the binary file
    */
    public function display()
    {
        header('Content-type: '.$this->mime);
        readfile($this->file_path);
    }

    /**
    * Deletes a picture, from both database and filesystem
    *
    * @return boolean true if image was successfully deleted, false otherwise
    */
    public function delete()
    {
        global $mdb;
        $class = get_class($this);
        $sql = 'DELETE FROM ' . PREFIX_DB . $this->tbl_prefix . $class::TABLE .
            ' WHERE ' . $class::PK . '=\'' . $this->id . '\'';
        $result = $mdb->query($sql);
        if ( MDB2::isError($result) ) {
            return false;
        } else {
            $file_wo_ext = dirname(__FILE__).'/' . $this->store_path . $this->id;
            if ( file_exists($file_wo_ext . '.jpg') ) {
                return unlink($file_wo_ext . '.jpg');
            } elseif ( file_exists($file_wo_ext . '.png') ) {
                return unlink($file_wo_ext . '.png');
            } elseif ( file_exists($file_wo_ext . '.gif') ) {
                return unlink($file_wo_ext . '.gif');
            }
        }
        return true;
    }

    /**
    * Stores an image on the disk and in the database
    *
    * @param object $file the uploaded file
    *
    * @return true|false result of the storage process
    */
    public function store($file)
    {
        /** TODO:
            - make upload dir configurable
            - fix max size (by preferences ?)
            - make possible to store images in database, filesystem or both
        */
        global $mdb, $log;

        $class = get_class($this);

        $name = $file['name'];
        $tmpfile = $file['tmp_name'];

        //First, does the file have a valid name?
        $reg = "/^(.[^" . implode('', $this->_bad_chars) . "]+)\.(" .
            implode('|', $this->_allowed_extensions) . ")$/i";
        if ( preg_match($reg, $name, $matches) ) {
            $log->log(
                '[' . $class . '] Filename and extension are OK, proceed.',
                PEAR_LOG_DEBUG
            );
            $extension = $matches[2];
            if ( $extension == 'jpeg' ) {
                //jpeg is an allowed extension,
                //but we change it to jpg to reduce further tests :)
                $extension = 'jpg';
            }
        } else {
            $log->log(
                '[' . $class . '] Invalid filename or extension.',
                PEAR_LOG_ERR
            );
            return self::INVALID_FILE;
        }

        //Second, let's check file size
        if ( $file['size'] > ( $class::MAX_FILE_SIZE * 1024 ) ) {
            $log->log(
                '[' . $class . '] File is too big (' . ( $file['size'] * 1024 ) .
                'Ko for maximum authorized ' . ( $class::MAX_FILE_SIZE * 1024 ) .
                'Ko',
                PEAR_LOG_ERR
            );
            return self::FILE_TOO_BIG;
        } else {
            $log->log('[' . $class . '] Filesize is OK, proceed', PEAR_LOG_DEBUG);
        }

        $current = getimagesize($tmpfile);

        if ( !in_array($current['mime'], $this->_allowed_mimes) ) {
            $log->log('[' . $class . '] Mimetype not allowed', PEAR_LOG_ERR);
            return self::MIME_NOT_ALLOWED;
        } else {
            $log->log(
                '[' . $class . '] Mimetype is allowed, proceed',
                PEAR_LOG_DEBUG
            );
        }

        $this->delete();

        $new_file = dirname(__FILE__).'/' . $this->store_path .
            $this->id . '.' . $extension;
        move_uploaded_file($tmpfile, $new_file);

        // current[0] gives width ; current[1] gives height
        if ( $current[0] > $this->max_width || $current[1] > $this->max_height ) {
            /** FIXME: what if image cannot be resized?
                Should'nt we want to stop the process here? */
            $this->_resizeImage($new_file, $extension);
        }

        //store file in database
        $f = fopen($new_file, 'r');
        $picture = '';
        while ( $r=fread($f, 8192) ) {
            $picture .= $r;
        }
        fclose($f);

        $class = get_class($this);
        $stmt = $mdb->prepare(
            'INSERT INTO ' . PREFIX_DB . $this->tbl_prefix . $class::TABLE .
            ' (' . $class::PK .
            ', picture, format) VALUES (:id, :picture, :extension)',
            array('integer', 'blob', 'text'),
            MDB2_PREPARE_MANIP,
            array('picture')
        );

        $stmt->bindParam(0, $this->id);
        $stmt->bindParam(1, $picture);
        $stmt->bindParam(2, $extension);
        $stmt->execute();

        if ( MDB2::isError($stmt) ) {
            $log->log(
                '[' . $class . '] An error has occured inserting ' .
                'picture in database | ' . $stmt->getMessage() .
                '(' . $stmt->getDebugInfo() . ')',
                PEAR_LOG_ERR
            );
            return self::SQL_ERROR;
        }
        $stmt->free();
        return true;
    }

    /**
    * Resize the image if it exceed max allowed sizes
    *
    * @param string $source the source image
    * @param string $ext    file's extension
    * @param string $dest   the destination image.
    *                           If null, we'll use the source image. Defaults to null
    *
    * @return void
    */
    private function _resizeImage($source, $ext, $dest = null)
    {
        global $log;
        $class = get_class($this);
        /** FIXME: Can GD not be present ? Is there any another solution to test? */
        if (function_exists("gd_info")) {
            $gdinfo = gd_info();
            $h = $this->max_height;
            $w = $this->max_width;
            if ( $dest == null ) {
                $dest = $source;
            }

            switch(strtolower($ext)) {
            case 'jpg':
                if (!$gdinfo['JPEG Support']) {
                    $log->log(
                        '[' . $class . '] GD has no JPEG Support - ' .
                        'pictures could not be resized!',
                        PEAR_LOG_ERROR
                    );
                    return false;
                }
                break;
            case 'png':
                if (!$gdinfo['PNG Support']) {
                    $log->log(
                        '[' . $class . '] GD has no PNG Support - ' .
                        'pictures could not be resized!',
                        PEAR_LOG_ERROR
                    );
                    return false;
                }
                break;
            case 'gif':
                if (!$gdinfo['GIF Create Support']) {
                    $log->log(
                        '[' . $class . '] GD has no GIF Support - ' .
                        'pictures could not be resized!',
                        PEAR_LOG_ERROR
                    );
                    return false;
                }
                break;
            default:
                return false;
            }

            list($cur_width, $cur_height, $cur_type, $curattr)
                = getimagesize($source);

            $ratio = $cur_width / $cur_height;

            // calculate image size according to ratio
            if ($cur_witdh>$cur_height) {
                $h = $w/$ratio;
            } else {
                $w = $h*$ratio;
            }

            $thumb = imagecreatetruecolor($w, $h);
            switch($ext) {
            case 'jpg':
                $image = ImageCreateFromJpeg($source);
                imagecopyresized(
                    $thumb, $image, 0, 0, 0, 0, $w, $h, $cur_width, $cur_height
                );
                imagejpeg($thumb, $dest);
                break;
            case 'png':
                $image = ImageCreateFromPng($source);
                // Turn off alpha blending and set alpha flag. That prevent alpha
                // transparency to be saved as an arbitrary color (black in my tests)
                imagealphablending($thumb, false);
                imagealphablending($image, false);
                imagesavealpha($thumb, true);
                imagesavealpha($image, true);
                imagecopyresized(
                    $thumb, $image, 0, 0, 0, 0, $w, $h, $cur_width, $cur_height
                );
                imagepng($thumb, $dest);
                break;
            case 'gif':
                $image = ImageCreateFromGif($source);
                imagecopyresized(
                    $thumb, $image, 0, 0, 0, 0, $w, $h, $cur_width, $cur_height
                );
                imagegif($thumb, $dest);
                break;
            }
        } else {
            $log->log(
                '[' . $class . '] GD is not present - ' .
                'pictures could not be resized!',
                PEAR_LOG_ERROR
            );
        }
    }

    /* GETTERS */
    /**
    * Returns current file optimal height (resized)
    *
    * @return int optimal height
    */
    public function getOptimalHeight()
    {
        return $this->optimal_height;
    }

    /**
    * Returns current file height
    *
    * @return int current height
    */
    public function getHeight()
    {
        return $this->height;
    }

    /**
    * Returns current file optimal width (resized)
    *
    * @return int optimal width
    */
    public function getOptimalWidth()
    {
        return $this->optimal_width;
    }

    /**
    * Returns current file width
    *
    * @return int current width
    */
    public function getWidth()
    {
        return $this->width;
    }

    /**
    * Returns current file format
    *
    * @return string
    */
    public function getFormat()
    {
        return $this->format;
    }

    /**
    * Have we got a picture ?
    *
    * @return bool True if a picture matches adherent's id, false otherwise
    */
    public function hasPicture()
    {
        return $this->has_picture;
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
    * Returns current file full path
    *
    * @return string full file path
    */
    public function getPath()
    {
        return $this->file_path;
    }

    /**
    * Returns current mime type
    *
    * @return string
    */
    public function getMime()
    {
        return $this->mime;
    }

    /**
    * Returns custom state
    *
    * @return string
    */
    public function isCustom()
    {
        return $this->custom;
    }
}
?>
