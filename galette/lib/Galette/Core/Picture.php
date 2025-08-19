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

namespace Galette\Core;

use ArrayObject;
use Laminas\Db\Adapter\Driver\StatementInterface;
use Laminas\Db\Sql\Select;
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
 * @author Frédéric Jacquot <gna@logeek.com>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Picture implements FileInterface
{
    use FileTrait {
        writeOnDisk as protected trait_writeOnDisk;
        store as protected trait_store;
        getMimeType as protected trait_getMimeType;
    }

    //constants that will not be overridden
    public const SQL_ERROR = -10;
    public const SQL_BLOB_ERROR = -11;
    //constants that can be overridden
    //(do not use self::CONSTANT, but get_class[$this]::CONSTANT)
    public const TABLE = 'pictures';
    public const PK = Adherent::PK;

    protected string $tbl_prefix = '';

    protected string|int $id;
    protected int $db_id;
    protected int $height;
    protected int $width;
    protected int $optimal_height;
    protected int $optimal_width;
    protected string $file_path;
    protected string $format;
    protected string $mime;
    protected bool $has_picture = false;
    protected string $store_path = GALETTE_PHOTOS_PATH;
    protected int $max_width = 200;
    protected int $max_height = 200;
    private StatementInterface $insert_stmt;
    /** @var ?array<string, mixed> */
    private ?array $cropping;

    /**
     * Default constructor.
     *
     * @param string|int|null $id_adh the id of the member
     */
    public function __construct(string|int|null $id_adh = null)
    {
        $this->init(
            null,
            ['jpeg', 'jpg', 'png', 'gif', 'webp'],
            [
                'jpg'    =>    'image/jpeg',
                'png'    =>    'image/png',
                'gif'    =>    'image/gif',
                'webp'   =>    'image/webp'
            ]
        );

        // '!==' needed, otherwise ''==0
        if (!empty($id_adh)) {
            $this->id = $id_adh;
            if (!isset($this->db_id)) {
                $this->db_id = (int)$id_adh;
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
    public function __wakeup(): void
    {
        //if file has been deleted since we store our object in the session,
        //we try to retrieve it
        if (isset($this->id) && !$this->checkFileOnFS()) {
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
    private function checkFileOnFS(): bool
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
    private function checkFileInDB(): bool
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
     * @return Select SELECT query
     */
    protected function getCheckFileQuery(): Select
    {
        global $zdb;
        $class = get_class($this);

        $select = $zdb->select($this->tbl_prefix . $class::TABLE);
        $select->columns(
            [
                'picture',
                'format'
            ]
        );
        $select->where([$class::PK => $this->db_id]);
        return $select;
    }

    /**
     * Gets the default picture to show, anyway
     *
     * @return void
     */
    protected function getDefaultPicture(): void
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
    private function setSizes(): void
    {
        [$width, $height] = getimagesize($this->file_path);
        $this->height = $height;
        $this->width = $width;
        $this->optimal_height = $height;
        $this->optimal_width = $width;

        if ($this->height > $this->width) {
            if ($this->height > $this->max_height) {
                $ratio = $this->max_height / $this->height;
                $this->optimal_height = $this->max_height;
                $this->optimal_width = (int)($this->width * $ratio);
            }
        } elseif ($this->width > $this->max_width) {
            $ratio = $this->max_width / $this->width;
            $this->optimal_width = $this->max_width;
            $this->optimal_height = (int)($this->height * $ratio);
        }
    }

    /**
     * Get image file contents in stdOut
     *
     * @return void
     */
    public function getContents(): void
    {
        readfile($this->file_path);
    }

    /**
     * Set header and displays the picture.
     *
     * @param Response $response Response
     *
     * @return Response the binary file
     */
    public function display(Response $response): Response
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
    public function delete(bool $transaction = true): bool
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
                    'The file ' . $_file
                    . ' was found on the disk but cannot be removed.',
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
                'An error occurred attempting to delete picture ' . $this->db_id
                . 'from database | ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Stores an image on the disk and in the database
     *
     * @param array<string, mixed>  $file     The uploaded file
     * @param boolean               $ajax     If the image comes from an ajax call (dnd)
     * @param ?array<string, mixed> $cropping Cropping properties
     *
     * @return true|int
     */
    public function store(array $file, bool $ajax = false, ?array $cropping = null): bool|int
    {
        $this->cropping = $cropping;
        return $this->trait_store($file, $ajax);
    }

    /**
     * Build destination path
     *
     * @return string
     */
    protected function buildDestPath(): string
    {
        return $this->dest_dir . $this->id . '.' . $this->extension;
    }

    /**
     * Get file mime type
     *
     * @param string $file File
     *
     * @return string
     */
    public static function getMimeType(string $file): string
    {
        $info = getimagesize($file);
        if ($info !== false) {
            return $info['mime'];
        }

        //fallback if file is not an image
        return static::trait_getMimeType($file);
    }

    /**
     * Write file on disk
     *
     * @param string $tmpfile Temporary file
     * @param bool   $ajax    If the file comes from an ajax call (dnd)
     *
     * @return true|int
     */
    public function writeOnDisk(string $tmpfile, bool $ajax): bool|int
    {
        global $zdb;

        $this->setDestDir($this->store_path);
        $current = getimagesize($tmpfile);

        // Source image must have minimum dimensions to match the cropping process requirements
        // and ensure the final picture will fit the maximum allowed resizing dimensions.
        if (isset($this->cropping['ratio']) && isset($this->cropping['focus'])) {
            if ($current[0] < $this->mincropsize || $current[1] < $this->mincropsize) {
                $min_current = min($current[0], $current[1]);
                Analog::log(
                    '[' . get_class($this) . '] Image is too small. The minimum image side size allowed is '
                    . $this->mincropsize . 'px, but current is ' . $min_current . 'px.',
                    Analog::ERROR
                );
                return self::IMAGE_TOO_SMALL;
            } else {
                Analog::log('[' . get_class($this) . '] Image dimensions are OK, proceed', Analog::DEBUG);
            }
        }
        $this->delete();

        $result = $this->trait_writeOnDisk($tmpfile, $ajax);
        if ($result !== true) {
            return $result;
        }

        // current[0] gives width; current[1] gives height
        if ($current[0] > $this->max_width || $current[1] > $this->max_height) {
            /** FIXME: what if image cannot be resized?
            Should'nt we want to stop the process here? */
            $this->resizeImage($this->buildDestPath(), $this->extension, null, $this->cropping);
        }

        return $this->storeInDb($zdb, $this->db_id, $this->buildDestPath(), $this->extension);
    }

    /**
     * Stores an image in the database
     *
     * @param Db     $zdb  Database instance
     * @param int    $id   Member ID
     * @param string $file File path on disk
     * @param string $ext  File extension
     *
     * @return true|int
     */
    private function storeInDb(Db $zdb, int $id, string $file, string $ext): bool|int
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

            if (isset($this->insert_stmt)) {
                $stmt = $this->insert_stmt;
            } else {
                $insert = $zdb->insert($this->tbl_prefix . $class::TABLE);
                $insert->values(
                    [
                        $class::PK  => ':' . $class::PK,
                        'picture'   => ':picture',
                        'format'    => ':format'
                    ]
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
                [
                    $class::PK  => $id,
                    'picture'   => $picture,
                    'format'    => $ext
                ]
            );
            $zdb->connection->commit();
            $this->has_picture = true;
        } catch (Throwable $e) {
            $zdb->connection->rollBack();
            Analog::log(
                'An error occurred storing picture in database: '
                . $e->getMessage(),
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
    public function missingInDb(Db $zdb): void
    {
        $existing_disk = [];

        //retrieve files on disk
        if ($handle = opendir($this->store_path)) {
            while (false !== ($entry = readdir($handle))) {
                $reg = "/^(\d+)\.("
                    . implode('|', $this->allowed_extensions) . ")$/i";
                if (preg_match($reg, $entry, $matches)) {
                    $id = $matches[1];
                    $extension = strtolower($matches[2]);
                    if ($extension == 'jpeg') {
                        //jpeg is an allowed extension,
                        //but we change it to jpg to reduce further tests :)
                        $extension = 'jpg';
                    }
                    $existing_disk[$id] = [
                        'name'  => $entry,
                        'id'    => $id,
                        'ext'   => $extension
                    ];
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
                ->columns([$class::PK])
                ->where->in($class::PK, array_keys($existing_disk));

            $results = $zdb->execute($select);

            $existing_db = [];
            foreach ($results as $result) {
                $existing_db[] = (int)$result[self::PK];
            }

            $existing_diff = array_diff(array_keys($existing_disk), $existing_db);

            //retrieve valid members ids
            $members = new Members();
            $valids = $members->getArrayList(
                array_map('intval', $existing_diff),
                null,
                false,
                false,
                [self::PK]
            );

            foreach ($valids as $valid) {
                /** @var ArrayObject<string,mixed> $valid */
                $file = $existing_disk[$valid->id_adh];
                $this->storeInDb(
                    $zdb,
                    (int)$file['id'],
                    $this->store_path . $file['id'] . '.' . $file['ext'],
                    $file['ext']
                );
            }
        } else {
            Analog::log(
                'Something went wrong opening images directory '
                . $this->store_path,
                Analog::ERROR
            );
        }
    }

    /**
     * Resize and eventually crop the image if it exceeds max allowed sizes
     *
     * @param string                $source   The source image
     * @param string                $ext      File's extension
     * @param ?string               $dest     The destination image.
     *                                        If null, we'll use the source image. Defaults to null
     * @param ?array<string, mixed> $cropping Cropping properties
     *
     * @return boolean
     */
    private function resizeImage(string $source, string $ext, ?string $dest = null, ?array $cropping = null): bool
    {
        $class = get_class($this);

        if (!function_exists("gd_info")) {
            Analog::log(
                '[' . $class . '] GD is not present - '
                . 'pictures could not be resized!',
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
                        '[' . $class . '] GD has no JPEG Support - '
                        . 'pictures could not be resized!',
                        Analog::ERROR
                    );
                    return false;
                }
                break;
            case 'png':
                if (!$gdinfo['PNG Support']) {
                    Analog::log(
                        '[' . $class . '] GD has no PNG Support - '
                        . 'pictures could not be resized!',
                        Analog::ERROR
                    );
                    return false;
                }
                break;
            case 'gif':
                if (!$gdinfo['GIF Create Support']) {
                    Analog::log(
                        '[' . $class . '] GD has no GIF Support - '
                        . 'pictures could not be resized!',
                        Analog::ERROR
                    );
                    return false;
                }
                break;
            case 'webp':
                if (!$gdinfo['WebP Support']) {
                    Analog::log(
                        '[' . $class . '] GD has no WebP Support - '
                        . 'pictures could not be resized!',
                        Analog::ERROR
                    );
                    return false;
                }
                break;

            default:
                return false;
        }

        [$cur_width, $cur_height, $cur_type, $curattr]
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
            $thumb_cropped = imagecreatetruecolor((int)$crop_width, (int)$crop_height);
            // Cropped ratio.
            $ratio = $crop_width / $crop_height;
        } elseif ($cur_width > $cur_height) {
            // Otherwise, calculate image size according to the source's ratio.
            $h = round($w / $ratio);
        } else {
            $w = round($h * $ratio);
        }

        //fix typehints
        $h = (int)$h;
        $w = (int)$w;
        $crop_x = (int)$crop_x;
        $crop_y = (int)$crop_y;
        $crop_width = (int)$crop_width;
        $crop_height = (int)$crop_height;
        $cur_width = (int)$cur_width;
        $cur_height = (int)$cur_height;

        // Resized image.
        $thumb = imagecreatetruecolor($w, $h);

        $image = false;
        switch ($ext) {
            case 'jpg':
                $image = imagecreatefromjpeg($source);
                if ($thumb_cropped !== false) {
                    // Crop: first, crop.
                    imagecopyresampled($thumb_cropped, $image, 0, 0, $crop_x, $crop_y, $cur_width, $cur_height, $cur_width, $cur_height);
                    // Then, resize.
                    imagecopyresampled($thumb, $thumb_cropped, 0, 0, 0, 0, $w, $h, $crop_width, $crop_height);
                } else {
                    // Resize
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
                if ($thumb_cropped !== false) {
                    // Crop
                    imagealphablending($thumb_cropped, false);
                    imagesavealpha($thumb_cropped, true);
                    // First, crop.
                    imagecopyresampled($thumb_cropped, $image, 0, 0, $crop_x, $crop_y, $cur_width, $cur_height, $cur_width, $cur_height);
                    // Then, resize.
                    imagecopyresampled($thumb, $thumb_cropped, 0, 0, 0, 0, $w, $h, $crop_width, $crop_height);
                } else {
                    // Resize
                    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $w, $h, $cur_width, $cur_height);
                }
                imagepng($thumb, $dest);
                break;
            case 'gif':
                $image = imagecreatefromgif($source);
                if ($thumb_cropped !== false) {
                    // Crop: first, crop.
                    imagecopyresampled($thumb_cropped, $image, 0, 0, $crop_x, $crop_y, $cur_width, $cur_height, $cur_width, $cur_height);
                    // Then, resize.
                    imagecopyresampled($thumb, $thumb_cropped, 0, 0, 0, 0, $w, $h, $crop_width, $crop_height);
                } else {
                    // Resize
                    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $w, $h, $cur_width, $cur_height);
                }
                imagegif($thumb, $dest);
                break;
            case 'webp':
                $image = imagecreatefromwebp($source);
                if ($thumb_cropped !== false) {
                    // Crop: first, crop.
                    imagecopyresampled($thumb_cropped, $image, 0, 0, $crop_x, $crop_y, $cur_width, $cur_height, $cur_width, $cur_height);
                    // Then, resize.
                    imagecopyresampled($thumb, $thumb_cropped, 0, 0, 0, 0, $w, $h, $crop_width, $crop_height);
                } else {
                    // Resize
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
    public function getOptimalHeight(): int
    {
        return (int)round($this->optimal_height, 1);
    }

    /**
     * Returns current file height
     *
     * @return int current height
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Returns current file optimal width (resized)
     *
     * @return int optimal width
     */
    public function getOptimalWidth(): int
    {
        return (int)round($this->optimal_width, 1);
    }

    /**
     * Returns current file width
     *
     * @return int current width
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Returns current file format
     *
     * @return string
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * Have we got a picture?
     *
     * @return bool True if a picture matches adherent's id, false otherwise
     */
    public function hasPicture(): bool
    {
        return $this->has_picture;
    }

    /**
     * Returns current file full path
     *
     * @return string full file path
     */
    public function getPath(): string
    {
        return $this->file_path;
    }

    /**
     * Returns current mime type
     *
     * @return string
     */
    public function getMime(): string
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
    public function getErrorMessage(int $code): string
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
