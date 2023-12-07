<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Picture handling
 *
 * PHP version 5
 *
 * Copyright © 2006-2023 The Galette Team
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
 * @category  Core
 * @package   Galette
 *
 * @author    Frédéric Jacquot <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2006-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

namespace Galette\Core;

use ArrayObject;
use Slim\Psr7\Response;
use Throwable;
use Analog\Analog;
use Galette\Entity\Adherent;
use Galette\Repository\Members;
use Galette\IO\FileInterface;
use Galette\IO\FileTrait;

/**
 * Picture handling
 *
 * @name Picture
 * @category  Core
 * @package   Galette
 * @author    Frédéric Jacquot <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2006-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */
class Picture implements FileInterface
{
    use FileTrait;

    //constants that will not be overrided
    public const SQL_ERROR = -10;
    public const SQL_BLOB_ERROR = -11;
    //constants that can be overrided
    //(do not use self::CONSTANT, but get_class[$this]::CONSTANT)
    public const TABLE = 'pictures';
    public const PK = Adherent::PK;

    protected $tbl_prefix = '';

    protected $id;
    protected $db_id;
    protected $height;
    protected $width;
    protected $optimal_height;
    protected $optimal_width;
    protected $file_path;
    protected $format;
    protected $mime;
    protected $has_picture = false;
    protected $store_path = GALETTE_PHOTOS_PATH;
    protected $max_width = 200;
    protected $max_height = 200;
    private $insert_stmt;

    /**
     * Default constructor.
     *
     * @param mixed|null $id_adh the id of the member
     */
    public function __construct($id_adh = null)
    {

        $this->init(
            null,
            array('jpeg', 'jpg', 'png', 'gif', 'webp'),
            array(
                'jpg'    =>    'image/jpeg',
                'png'    =>    'image/png',
                'gif'    =>    'image/gif',
                'webp'   =>    'image/webp'
            )
        );

        // '!==' needed, otherwise ''==0
        if ($id_adh !== '' && $id_adh !== null) {
            $this->id = $id_adh;
            if (!isset($this->db_id)) {
                $this->db_id = $id_adh;
            }

            //if file does not exist on the FileSystem, check for it in the database
            if (!$this->checkFileOnFS()) {
                if ($this->checkFileInDB()) {
                    $this->has_picture = true;
                }
            } else {
                $this->has_picture = true;
            }
        }

        // if we still have no picture, take the default one
        if (empty($this->file_path)) {
            $this->getDefaultPicture();
        }

        //we should not have an empty file_path, but...
        if (!empty($this->file_path)) {
            $this->setSizes();
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
        if (!$this->checkFileOnFS()) {
            //if file does not exist on the FileSystem,
            //check for it in the database
            //$this->checkFileInDB();
        } else {
            $this->has_picture = false;
        }

        // if we still have no picture, take the default one
        if (empty($this->file_path)) {
            $this->getDefaultPicture();
        }

        //we should not have an empty file_path, but...
        if (!empty($this->file_path)) {
            $this->setSizes();
        }
    }

    /**
     * Check if current file is present on the File System
     *
     * @return boolean true if file is present on FS, false otherwise
     */
    private function checkFileOnFS()
    {
        $file_wo_ext = $this->store_path . $this->id;
        if (file_exists($file_wo_ext . '.jpg')) {
            $this->file_path = realpath($file_wo_ext . '.jpg');
            $this->format = 'jpg';
            $this->mime = 'image/jpeg';
            return true;
        } elseif (file_exists($file_wo_ext . '.png')) {
            $this->file_path = realpath($file_wo_ext . '.png');
            $this->format = 'png';
            $this->mime = 'image/png';
            return true;
        } elseif (file_exists($file_wo_ext . '.gif')) {
            $this->file_path = realpath($file_wo_ext . '.gif');
            $this->format = 'gif';
            $this->mime = 'image/gif';
            return true;
        } elseif (file_exists($file_wo_ext . '.webp')) {
            $this->file_path = realpath($file_wo_ext . '.webp');
            $this->format = 'webp';
            $this->mime = 'image/webp';
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
    private function checkFileInDB()
    {
        global $zdb;

        try {
            $select = $this->getCheckFileQuery();
            $results = $zdb->execute($select);
            $pic = $results->current();

            if ($pic) {
                // we must regenerate the picture file
                $file_wo_ext = $this->store_path . $this->id;
                file_put_contents(
                    $file_wo_ext . '.' . $pic->format,
                    $pic->picture
                );

                $this->format = $pic->format;
                switch ($this->format) {
                    case 'jpg':
                        $this->mime = 'image/jpeg';
                        break;
                    case 'png':
                        $this->mime = 'image/png';
                        break;
                    case 'gif':
                        $this->mime = 'image/gif';
                        break;
                    case 'webp':
                        $this->mime = 'image/webp';
                        break;
                }
                $this->file_path = realpath($file_wo_ext . '.' . $this->format);
                return true;
            }
        } catch (Throwable $e) {
            return false;
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
        global $zdb;
        $class = get_class($this);

        $select = $zdb->select($this->tbl_prefix . $class::TABLE);
        $select->columns(
            array(
                'picture',
                'format'
            )
        );
        $select->where(array($class::PK => $this->db_id));
        return $select;
    }

    /**
     * Gets the default picture to show, anyways
     *
     * @return void
     */
    protected function getDefaultPicture()
    {
        $this->file_path = realpath(_CURRENT_THEME_PATH . 'images/default.png');
        $this->format = 'png';
        $this->mime = 'image/png';
        $this->has_picture = false;
    }

    /**
     * Set picture sizes
     *
     * @return void
     */
    private function setSizes()
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
     * Get image file contents
     *
     * @return mixed
     */
    public function getContents()
    {
        readfile($this->file_path);
    }

    /**
     * Set header and displays the picture.
     *
     * @param Response $response Response
     *
     * @return object the binary file
     */
    public function display(Response $response)
    {
        $response = $response->withHeader('Content-Type', $this->mime)
            ->withHeader('Content-Transfer-Encoding', 'binary')
            ->withHeader('Expires', '0')
            ->withHeader('Cache-Control', 'must-revalidate')
            ->withHeader('Pragma', 'public');

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, file_get_contents($this->file_path));
        rewind($stream);

        return $response->withBody(new \Slim\Psr7\Stream($stream));
    }

    /**
     * Deletes a picture, from both database and filesystem
     *
     * @param boolean $transaction Whether to use a transaction here or not
     *
     * @return boolean true if image was successfully deleted, false otherwise
     */
    public function delete($transaction = true)
    {
        global $zdb;
        $class = get_class($this);

        try {
            if ($transaction === true) {
                $zdb->connection->beginTransaction();
            }

            $delete = $zdb->delete($this->tbl_prefix . $class::TABLE);
            $delete->where([$class::PK => $this->db_id]);
            $del = $zdb->execute($delete);

            if (!$del->count() > 0) {
                Analog::log(
                    'Unable to remove picture database entry for ' . $this->db_id,
                    Analog::ERROR
                );
                //it may be possible image is missing in the database.
                //let's try to remove file anyway.
            }

            $file_wo_ext = $this->store_path . $this->id;

            // take back default picture
            $this->getDefaultPicture();
            // fix sizes
            $this->setSizes();

            $success = false;
            $_file = null;
            if (file_exists($file_wo_ext . '.jpg')) {
                //return unlink($file_wo_ext . '.jpg');
                $_file = $file_wo_ext . '.jpg';
                $success = unlink($_file);
            } elseif (file_exists($file_wo_ext . '.png')) {
                //return unlink($file_wo_ext . '.png');
                $_file = $file_wo_ext . '.png';
                $success = unlink($_file);
            } elseif (file_exists($file_wo_ext . '.gif')) {
                //return unlink($file_wo_ext . '.gif');
                $_file = $file_wo_ext . '.gif';
                $success = unlink($_file);
            } elseif (file_exists($file_wo_ext . '.webp')) {
                //return unlink($file_wo_ext . '.webp');
                $_file = $file_wo_ext . '.webp';
                $success = unlink($_file);
            }

            if ($_file !== null && $success !== true) {
                //unable to remove file that exists!
                if ($transaction === true) {
                    $zdb->connection->rollBack();
                }
                Analog::log(
                    'The file ' . $_file .
                    ' was found on the disk but cannot be removed.',
                    Analog::ERROR
                );
                return false;
            } else {
                if ($transaction === true) {
                    $zdb->connection->commit();
                }
                $this->has_picture = false;
                return true;
            }
        } catch (Throwable $e) {
            if ($transaction === true) {
                $zdb->connection->rollBack();
            }
            Analog::log(
                'An error occurred attempting to delete picture ' . $this->db_id .
                'from database | ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Stores an image on the disk and in the database
     *
     * @param object  $file     The uploaded file
     * @param boolean $ajax     If the image cames from an ajax call (dnd)
     * @param array   $cropping Cropping properties
     *
     * @return bool|int
     */
    public function store($file, $ajax = false, $cropping = null)
    {
        /** TODO: fix max size (by preferences ?) */
        global $zdb;

        $class = get_class($this);

        $name = $file['name'];
        $tmpfile = $file['tmp_name'];

        //First, does the file have a valid name?
        $reg = "/^([^" . implode('', $this->bad_chars) . "]+)\.(" .
            implode('|', $this->allowed_extensions) . ")$/i";
        if (preg_match($reg, $name, $matches)) {
            Analog::log(
                '[' . $class . '] Filename and extension are OK, proceed.',
                Analog::DEBUG
            );
            $extension = strtolower($matches[2]);
            if ($extension == 'jpeg') {
                //jpeg is an allowed extension,
                //but we change it to jpg to reduce further tests :)
                $extension = 'jpg';
            }
        } else {
            $erreg = "/^([^" . implode('', $this->bad_chars) . "]+)\.(.*)/i";
            $m = preg_match($erreg, $name, $errmatches);

            $err_msg = '[' . $class . '] ';
            if ($m == 1) {
                //ok, we got a good filename and an extension. Extension is bad :)
                $err_msg .= 'Invalid extension for file ' . $name . '.';
                $ret = self::INVALID_EXTENSION;
            } else {
                $err_msg = 'Invalid filename `' . $name . '` (Tip: ';
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
                '[' . $class . '] File is too big (' . ($file['size'] * 1024) .
                'Ko for maximum authorized ' . ($this->maxlenght * 1024) .
                'Ko',
                Analog::ERROR
            );
            return self::FILE_TOO_BIG;
        } else {
            Analog::log('[' . $class . '] Filesize is OK, proceed', Analog::DEBUG);
        }

        $current = getimagesize($tmpfile);

        if (!in_array($current['mime'], $this->allowed_mimes)) {
            Analog::log(
                '[' . $class . '] Mimetype `' . $current['mime'] . '` not allowed',
                Analog::ERROR
            );
            return self::MIME_NOT_ALLOWED;
        } else {
            Analog::log(
                '[' . $class . '] Mimetype is allowed, proceed',
                Analog::DEBUG
            );
        }

        // Source image must have minimum dimensions to match the cropping process requirements
        // and ensure the final picture will fit the maximum allowed resizing dimensions.
        if (isset($cropping['ratio']) && isset($cropping['focus'])) {
            if ($current[0] < $this->mincropsize || $current[1] < $this->mincropsize) {
                $min_current = min($current[0], $current[1]);
                Analog::log(
                    '[' . $class . '] Image is too small. The minimum image side size allowed is ' .
                    $this->mincropsize . 'px, but current is ' . $min_current . 'px.',
                    Analog::ERROR
                );
                return self::IMAGE_TOO_SMALL;
            } else {
                Analog::log('[' . $class . '] Image dimensions are OK, proceed', Analog::DEBUG);
            }
        }

        $this->delete();

        $new_file = $this->store_path .
            $this->id . '.' . $extension;
        if ($ajax === true) {
            rename($tmpfile, $new_file);
        } else {
            move_uploaded_file($tmpfile, $new_file);
        }

        // current[0] gives width ; current[1] gives height
        if ($current[0] > $this->max_width || $current[1] > $this->max_height) {
            /** FIXME: what if image cannot be resized?
                Should'nt we want to stop the process here? */
            $this->resizeImage($new_file, $extension, null, $cropping);
        }

        return $this->storeInDb($zdb, $this->db_id, $new_file, $extension);
    }

    /**
     * Stores an image in the database
     *
     * @param Db     $zdb  Database instance
     * @param int    $id   Member ID
     * @param string $file File path on disk
     * @param string $ext  File extension
     *
     * @return bool|int
     */
    private function storeInDb(Db $zdb, $id, $file, $ext)
    {
        $f = fopen($file, 'r');
        $picture = '';
        while ($r = fread($f, 8192)) {
            $picture .= $r;
        }
        fclose($f);

        $class = get_class($this);

        try {
            $zdb->connection->beginTransaction();
            $stmt = $this->insert_stmt;
            if ($stmt == null) {
                $insert = $zdb->insert($this->tbl_prefix . $class::TABLE);
                $insert->values(
                    array(
                        $class::PK  => ':' . $class::PK,
                        'picture'   => ':picture',
                        'format'    => ':format'
                    )
                );
                $stmt = $zdb->sql->prepareStatementForSqlObject($insert);
                $container = $stmt->getParameterContainer();
                $container->offsetSet(
                    'picture', //'picture',
                    ':picture',
                    $container::TYPE_LOB
                );
                $stmt->setParameterContainer($container);
                $this->insert_stmt = $stmt;
            }

            $stmt->execute(
                array(
                    $class::PK  => $id,
                    'picture'   => $picture,
                    'format'    => $ext
                )
            );
            $zdb->connection->commit();
            $this->has_picture = true;
        } catch (Throwable $e) {
            $zdb->connection->rollBack();
            Analog::log(
                'An error occurred storing picture in database: ' .
                $e->getMessage(),
                Analog::ERROR
            );
            return self::SQL_ERROR;
        }

        return true;
    }

    /**
     * Check for missing images in database
     *
     * @param Db $zdb Database instance
     *
     * @return void
     */
    public function missingInDb(Db $zdb)
    {
        $existing_disk = array();

        //retrieve files on disk
        if ($handle = opendir($this->store_path)) {
            while (false !== ($entry = readdir($handle))) {
                $reg = "/^(\d+)\.(" .
                    implode('|', $this->allowed_extensions) . ")$/i";
                if (preg_match($reg, $entry, $matches)) {
                    $id = $matches[1];
                    $extension = strtolower($matches[2]);
                    if ($extension == 'jpeg') {
                        //jpeg is an allowed extension,
                        //but we change it to jpg to reduce further tests :)
                        $extension = 'jpg';
                    }
                    $existing_disk[$id] = array(
                        'name'  => $entry,
                        'id'    => $id,
                        'ext'   => $extension
                    );
                }
            }
            closedir($handle);

            if (count($existing_disk) === 0) {
                //no image on disk, nothing to do :)
                return;
            }

            //retrieve files in database
            $class = get_class($this);
            $select = $zdb->select($this->tbl_prefix . $class::TABLE);
            $select
                ->columns(array($class::PK))
                ->where->in($class::PK, array_keys($existing_disk));

            $results = $zdb->execute($select);

            $existing_db = array();
            foreach ($results as $result) {
                $existing_db[] = (int)$result[self::PK];
            }

            $existing_diff = array_diff(array_keys($existing_disk), $existing_db);

            //retrieve valid members ids
            $members = new Members();
            $valids = $members->getArrayList(
                $existing_diff,
                null,
                false,
                false,
                array(self::PK)
            );

            foreach ($valids as $valid) {
                /** @var ArrayObject $valid */
                $file = $existing_disk[$valid->id_adh];
                $this->storeInDb(
                    $zdb,
                    $file['id'],
                    $this->store_path . $file['id'] . '.' . $file['ext'],
                    $file['ext']
                );
            }
        } else {
            Analog::log(
                'Something went wrong opening images directory ' .
                $this->store_path,
                Analog::ERROR
            );
        }
    }

    /**
     * Resize and eventually crop the image if it exceeds max allowed sizes
     *
     * @param string $source   The source image
     * @param string $ext      File's extension
     * @param string $dest     The destination image.
     *                         If null, we'll use the source image. Defaults to null
     * @param array  $cropping Cropping properties
     *
     * @return boolean
     */
    private function resizeImage($source, $ext, $dest = null, $cropping = null)
    {
        $class = get_class($this);

        if (!function_exists("gd_info")) {
            Analog::log(
                '[' . $class . '] GD is not present - ' .
                'pictures could not be resized!',
                Analog::ERROR
            );
            return false;
        }

        $gdinfo = gd_info();
        $h = $this->max_height;
        $w = $this->max_width;
        if ($dest == null) {
            $dest = $source;
        }

        switch (strtolower($ext)) {
            case 'jpg':
                if (!$gdinfo['JPEG Support']) {
                    Analog::log(
                        '[' . $class . '] GD has no JPEG Support - ' .
                        'pictures could not be resized!',
                        Analog::ERROR
                    );
                    return false;
                }
                break;
            case 'png':
                if (!$gdinfo['PNG Support']) {
                    Analog::log(
                        '[' . $class . '] GD has no PNG Support - ' .
                        'pictures could not be resized!',
                        Analog::ERROR
                    );
                    return false;
                }
                break;
            case 'gif':
                if (!$gdinfo['GIF Create Support']) {
                    Analog::log(
                        '[' . $class . '] GD has no GIF Support - ' .
                        'pictures could not be resized!',
                        Analog::ERROR
                    );
                    return false;
                }
                break;
            case 'webp':
                if (!$gdinfo['WebP Support']) {
                    Analog::log(
                        '[' . $class . '] GD has no WebP Support - ' .
                        'pictures could not be resized!',
                        Analog::ERROR
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

        // Define cropping variables if necessary.
        $thumb_cropped = false;
        // Cropping is based on the smallest side of the source in order to
        // provide as less focusing options as possible if the source doesn't
        // fit the final ratio (center, top, bottom, left, right).
        $min_size = min($cur_width, $cur_height);
        // Cropping dimensions.
        $crop_width = $min_size;
        $crop_height = $min_size;
        // Cropping focus.
        $crop_x = 0;
        $crop_y = 0;
        if (isset($cropping['ratio']) && isset($cropping['focus'])) {
            // Calculate cropping dimensions
            switch ($cropping['ratio']) {
                case 'portrait_ratio':
                    // Calculate cropping dimensions
                    if ($ratio < 1) {
                        $crop_height = ceil($crop_width * 4 / 3);
                    } else {
                        $crop_width = ceil($crop_height * 3 / 4);
                    }
                    // Calculate resizing dimensions
                    $w = ceil($h * 3 / 4);
                    break;
                case 'landscape_ratio':
                    // Calculate cropping dimensions
                    if ($ratio > 1) {
                        $crop_width = ceil($crop_height * 4 / 3);
                    } else {
                        $crop_height = ceil($crop_width * 3 / 4);
                    }
                    // Calculate resizing dimensions
                    $h = ceil($w * 3 / 4);
                    break;
            }
            // Calculate focus coordinates
            switch ($cropping['focus']) {
                case 'center':
                    if ($ratio > 1) {
                        $crop_x = ceil(($cur_width - $crop_width) / 2);
                    } elseif ($ratio == 1) {
                        $crop_x = ceil(($cur_width - $crop_width) / 2);
                        $crop_y = ceil(($cur_height - $crop_height) / 2);
                    } else {
                        $crop_y = ceil(($cur_height - $crop_height) / 2);
                    }
                    break;
                case 'top':
                    $crop_x = ceil(($cur_width - $crop_width) / 2);
                    break;
                case 'bottom':
                    $crop_y = $cur_height - $crop_height;
                    break;
                case 'right':
                    $crop_x = $cur_width - $crop_width;
                    break;
            }
            // Cropped image.
            $thumb_cropped = imagecreatetruecolor($crop_width, $crop_height);
            // Cropped ratio.
            $ratio = $crop_width / $crop_height;
        // Otherwise, calculate image size according to the source's ratio.
        } else {
            if ($cur_width > $cur_height) {
                $h = round($w / $ratio);
            } else {
                $w = round($h * $ratio);
            }
        }

        // Resized image.
        $thumb = imagecreatetruecolor($w, $h);

        $image = false;
        switch ($ext) {
            case 'jpg':
                $image = imagecreatefromjpeg($source);
                // Crop
                if ($thumb_cropped !== false) {
                    // First, crop.
                    imagecopyresampled($thumb_cropped, $image, 0, 0, $crop_x, $crop_y, $cur_width, $cur_height, $cur_width, $cur_height);
                    // Then, resize.
                    imagecopyresampled($thumb, $thumb_cropped, 0, 0, 0, 0, $w, $h, $crop_width, $crop_height);
                // Resize
                } else {
                    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $w, $h, $cur_width, $cur_height);
                }
                imagejpeg($thumb, $dest);
                break;
            case 'png':
                $image = imagecreatefrompng($source);
                // Turn off alpha blending and set alpha flag. That prevent alpha
                // transparency to be saved as an arbitrary color (black in my tests)
                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                // Crop
                if ($thumb_cropped !== false) {
                    imagealphablending($thumb_cropped, false);
                    imagesavealpha($thumb_cropped, true);
                    // First, crop.
                    imagecopyresampled($thumb_cropped, $image, 0, 0, $crop_x, $crop_y, $cur_width, $cur_height, $cur_width, $cur_height);
                    // Then, resize.
                    imagecopyresampled($thumb, $thumb_cropped, 0, 0, 0, 0, $w, $h, $crop_width, $crop_height);
                // Resize
                } else {
                    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $w, $h, $cur_width, $cur_height);
                }
                imagepng($thumb, $dest);
                break;
            case 'gif':
                $image = imagecreatefromgif($source);
                // Crop
                if ($thumb_cropped !== false) {
                    // First, crop.
                    imagecopyresampled($thumb_cropped, $image, 0, 0, $crop_x, $crop_y, $cur_width, $cur_height, $cur_width, $cur_height);
                    // Then, resize.
                    imagecopyresampled($thumb, $thumb_cropped, 0, 0, 0, 0, $w, $h, $crop_width, $crop_height);
                // Resize
                } else {
                    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $w, $h, $cur_width, $cur_height);
                }
                imagegif($thumb, $dest);
                break;
            case 'webp':
                $image = imagecreatefromwebp($source);
                // Crop
                if ($thumb_cropped !== false) {
                    // First, crop.
                    imagecopyresampled($thumb_cropped, $image, 0, 0, $crop_x, $crop_y, $cur_width, $cur_height, $cur_width, $cur_height);
                    // Then, resize.
                    imagecopyresampled($thumb, $thumb_cropped, 0, 0, 0, 0, $w, $h, $crop_width, $crop_height);
                // Resize
                } else {
                    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $w, $h, $cur_width, $cur_height);
                }
                imagewebp($thumb, $dest);
                break;
        }

        return true;
    }

    /**
     * Returns current file optimal height (resized)
     *
     * @return int optimal height
     */
    public function getOptimalHeight()
    {
        return (int)round($this->optimal_height, 1);
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
        return (int)round($this->optimal_width, 1);
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
     * Return textual error message
     *
     * @param int $code The error code
     *
     * @return string Localized message
     */
    public function getErrorMessage($code)
    {
        $error = null;
        switch ($code) {
            case self::SQL_ERROR:
            case self::SQL_BLOB_ERROR:
                $error = _T("An SQL error has occurred.");
                break;
        }

        if ($error === null) {
            $error = $this->getErrorMessageFromCode($code);
        }

        return $error;
    }
}
