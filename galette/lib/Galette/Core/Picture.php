<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use ArrayObject;
use Laminas\Db\Adapter\Driver\StatementInterface;
use Laminas\Db\Sql\Select;
use Psr\Http\Message\UploadedFileInterface;
use Safe\Exceptions\DirException;
use Safe\Exceptions\ImageException;
use Slim\Psr7\Response;
use Throwable;
use Analog\Analog;
use Galette\Entity\Adherent;
use Galette\Exception\MissingAssetException;
use Galette\Repository\Members;
use Galette\IO\FileTrait;
use UnhandledMatchError;

use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\fclose;
use function Safe\fopen;
use function Safe\fread;
use function Safe\fwrite;
use function Safe\getimagesize;
use function Safe\imagealphablending;
use function Safe\imagecreatetruecolor;
use function Safe\imagesavealpha;
use function Safe\imagecopyresampled;
use function Safe\opendir;
use function Safe\preg_match;
use function Safe\readfile;
use function Safe\realpath;
use function Safe\rewind;

/**
 * Picture handling
 *
 * @author Frédéric Jacquot <gna@logeek.com>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Picture
{
    use FileTrait {
        writeOnDisk as protected trait_writeOnDisk;
        storeFile as protected trait_store;
        getMimeType as protected trait_getMimeType;
        upload as protected trait_upload;
    }

    //constants that will not be overridden
    public const int SQL_ERROR = -10;
    public const int SQL_BLOB_ERROR = -11;
    //constants that can be overridden
    //(do not use self::CONSTANT, but get_class[$this]::CONSTANT)
    public const string TABLE = 'pictures';
    public const string PK = Adherent::PK;

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
    /** Path of the default picture, when it could not be found on disk */
    protected ?string $missing_asset = null;
    /** Whether file_path comes from the default picture */
    private bool $on_default_path = false;
    protected string $store_path = GALETTE_PHOTOS_PATH;
    protected int $max_width = 200;
    protected int $max_height = 200;
    private StatementInterface $insert_stmt;
    /** @var ?array<string, mixed> */
    private ?array $cropping = null;

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
        if (!empty($this->file_path) && !$this->setSizes() && !$this->on_default_path) {
            //picture could not be read; fall back to the default one
            $this->has_picture = false;
            $this->getDefaultPicture();
            if (!empty($this->file_path)) {
                $this->setSizes();
            }
        }
    }

    /**
     * "Magic" function called on unserialize
     *
     * @param array<string, mixed> $data Data to unserialize
     */
    public function __unserialize(array $data): void
    {
        if (isset($data['id'])) {
            $this->id = $data['id'];
        }
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
        if (!empty($this->file_path) && !$this->setSizes() && !$this->on_default_path) {
            //picture could not be read; fall back to the default one
            $this->has_picture = false;
            $this->getDefaultPicture();
            if (!empty($this->file_path)) {
                $this->setSizes();
            }
        }
    }

    /**
     * Check if current file is present on the File System
     *
     * @return bool true if file is present on FS, false otherwise
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
     * @return bool true if file is present in the DB, false otherwise
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
        } catch (Throwable) {
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
        $class = static::class;

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
     */
    protected function getDefaultPicture(): void
    {
        $this->format = 'png';
        $this->mime = 'image/png';
        $this->has_picture = false;
        $this->setDefaultPath(_CURRENT_THEME_PATH . 'images/default.png');
    }

    /**
     * Set path to the default picture, if it can be found.
     *
     * A missing default picture means assets have not been built. Rather than
     * failing here - pictures are built from the dependency container, long
     * before any error page can be rendered - the failure is recorded and
     * reported when the picture is actually used.
     *
     * @param string $path Path to the default picture
     */
    protected function setDefaultPath(string $path): void
    {
        $this->on_default_path = true;

        //realpath() cannot be trusted here: its cache outlives the request
        //(realpath_cache_ttl, 120s by default), so it keeps resolving paths
        //removed meanwhile by another process - a build, or a branch switch.
        if (!file_exists($path)) {
            $this->missing_asset = $path;
            return;
        }

        $this->file_path = realpath($path);
        $this->missing_asset = null;
    }

    /**
     * Intended path of the picture file, whether it exists or not.
     *
     * Unlike getPath(), this does not throw on a missing file; it is meant for
     * subclasses that build upon another picture.
     */
    protected function getDefaultPath(): ?string
    {
        return $this->missing_asset ?? ($this->file_path ?? null);
    }

    /**
     * Throw if the picture file is not available.
     *
     * @throws MissingAssetException
     */
    protected function checkAvailable(): void
    {
        if ($this->missing_asset !== null) {
            throw new MissingAssetException($this->missing_asset);
        }
    }

    /**
     * Set picture sizes
     */
    private function setSizes(): bool
    {
        try {
            [$width, $height] = getimagesize($this->file_path);
        } catch (Throwable) {
            //file went away since its path was resolved; never fail here,
            //pictures are built long before an error page can be rendered.
            //Falling back to the default picture clears this again.
            $this->missing_asset = $this->file_path;
            unset($this->file_path);
            return false;
        }

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

        return true;
    }

    /**
     * Get image file contents in stdOut
     */
    public function getContents(): void
    {
        $this->checkAvailable();
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
        $this->checkAvailable();
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
     * @param bool $transaction Whether to use a transaction here or not
     *
     * @return bool true if image was successfully deleted, false otherwise
     */
    public function delete(bool $transaction = true): bool
    {
        global $zdb;
        $class = static::class;

        try {
            if ($transaction === true) {
                $zdb->beginTransaction();
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
                $_file = $file_wo_ext . '.jpg';
                $success = unlink($_file); //@phpstan-ignore theCodingMachineSafe.function
            } elseif (file_exists($file_wo_ext . '.png')) {
                $_file = $file_wo_ext . '.png';
                $success = unlink($_file); //@phpstan-ignore theCodingMachineSafe.function
            } elseif (file_exists($file_wo_ext . '.gif')) {
                $_file = $file_wo_ext . '.gif';
                $success = unlink($_file); //@phpstan-ignore theCodingMachineSafe.function
            } elseif (file_exists($file_wo_ext . '.webp')) {
                $_file = $file_wo_ext . '.webp';
                $success = unlink($_file); // @phpstan-ignore theCodingMachineSafe.function
            }

            if ($_file !== null && $success !== true) {
                //unable to remove file that exists!
                if ($transaction === true) {
                    $zdb->rollback();
                }
                Analog::log(
                    'The file ' . $_file
                    . ' was found on the disk but cannot be removed.',
                    Analog::ERROR
                );
                return false;
            } else {
                if ($transaction === true) {
                    $zdb->commit();
                }
                $this->has_picture = false;
                return true;
            }
        } catch (Throwable $e) {
            if ($transaction === true) {
                $zdb->rollback();
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
     * Do a file upload
     *
     * @param UploadedFileInterface[] $request_files Array of uploaded files (typically from PSR7 request)
     * @param string                  $key           Key to look for in uploaded files
     * @param callable|null           $callback      Callback to use for storing the file. If null, will use $this->storeFile()
     * @param ?array<string,mixed>    $cropping      Cropping properties
     */
    public function upload(array $request_files, string $key, ?callable $callback = null, ?array $cropping = null): bool
    {
        global $preferences;

        $callback = function ($uploaded_file) use ($cropping, $preferences) {
            if ($preferences->pref_force_picture_ratio == 1 && isset($cropping)) {
                return $this->storeFile($uploaded_file, $cropping);
            } else {
                return $this->storeFile($uploaded_file);
            }
        };

        return $this->trait_upload($request_files, $key, $callback);
    }

    /**
     * Stores an image on the disk and in the database
     *
     * @param UploadedFileInterface $file     The uploaded file
     * @param ?array<string, mixed> $cropping Cropping properties
     *
     * @return true|int
     */
    public function storeFile(UploadedFileInterface $file, ?array $cropping = null): bool|int
    {
        $this->cropping = $cropping;
        return $this->trait_store($file);
    }

    /**
     * Build destination path
     */
    protected function buildDestPath(): string
    {
        return $this->dest_dir . $this->id . '.' . $this->extension;
    }

    /**
     * Get file mime type
     *
     * @param string $file File
     */
    public static function getMimeType(string $file): string
    {
        try {
            $info = getimagesize($file);
            return $info['mime'];
        } catch (ImageException) {
            //fallback if file is not an image
            return static::trait_getMimeType($file);
        }
    }

    /**
     * Write file on disk
     *
     * @param UploadedFileInterface $file Uploaded file
     *
     * @return true|int
     */
    public function writeOnDisk(UploadedFileInterface $file): bool|int
    {
        global $zdb;

        $this->setDestDir($this->store_path);
        $current = getimagesize($file->getStream()->getMetadata('uri'));

        // Source image must have minimum dimensions to match the cropping process requirements
        // and ensure the final picture will fit the maximum allowed resizing dimensions.
        if (isset($this->cropping['ratio']) && isset($this->cropping['focus'])) {
            if ($current[0] < $this->mincropsize || $current[1] < $this->mincropsize) {
                $min_current = min($current[0], $current[1]);
                Analog::log(
                    '[' . static::class . '] Image is too small. The minimum image side size allowed is '
                    . $this->mincropsize . 'px, but current is ' . $min_current . 'px.',
                    Analog::ERROR
                );
                return self::IMAGE_TOO_SMALL;
            } else {
                Analog::log('[' . static::class . '] Image dimensions are OK, proceed', Analog::DEBUG);
            }
        }
        $this->delete();

        $result = $this->trait_writeOnDisk($file);
        if ($result !== true) {
            return $result;
        }

        // current[0] gives width; current[1] gives height
        if ($current[0] > $this->max_width || $current[1] > $this->max_height) {
            /** FIXME: what if image cannot be resized?
            Shouldn't we want to stop the process here? */
            $this->resizeImage(
                source: $this->buildDestPath(),
                ext: $this->extension,
                dest: null,
                cropping: $this->cropping
            );
        }

        return $this->storeInDb(zdb: $zdb, id: $this->db_id, file: $this->buildDestPath(), ext: $this->extension);
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

        $class = static::class;

        try {
            $zdb->beginTransaction();

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
            $zdb->commit();
            $this->has_picture = true;
        } catch (Throwable $e) {
            $zdb->rollback();
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
     */
    public function missingInDb(Db $zdb): void
    {
        $existing_disk = [];

        //retrieve files on disk
        try {
            $handle = opendir($this->store_path);
        } catch (DirException $e) {
            Analog::log(
                'Something went wrong opening images directory '
                . $this->store_path . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            return;
        }

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
        $class = static::class;
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
            ids: array_map(intval(...), $existing_diff),
            orderby: null,
            with_photos: false,
            as_members: false,
            fields: [self::PK]
        );

        foreach ($valids as $valid) {
            /** @var ArrayObject<string,mixed> $valid */
            $file = $existing_disk[$valid->id_adh];
            $this->storeInDb(
                zdb: $zdb,
                id: (int)$file['id'],
                file: $this->store_path . $file['id'] . '.' . $file['ext'],
                ext: $file['ext']
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
     */
    private function resizeImage(string $source, string $ext, ?string $dest = null, ?array $cropping = null): bool
    {
        $class = static::class;

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

        [$cur_width, $cur_height] = getimagesize($source);

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

        $image = match ($ext) {
            'jpg' => imagecreatefromjpeg($source), // @phpstan-ignore theCodingMachineSafe.function
            'png' => imagecreatefrompng($source), // @phpstan-ignore theCodingMachineSafe.function
            'gif' => imagecreatefromgif($source), // @phpstan-ignore theCodingMachineSafe.function
            'webp' => imagecreatefromwebp($source), // @phpstan-ignore theCodingMachineSafe.function
            default => throw new UnhandledMatchError($ext),
        };

        // Turn off alpha blending and set alpha flag. That prevent alpha
        // transparency to be saved as an arbitrary color (black in my tests)
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        if ($thumb_cropped !== false) { // Crop
            imagealphablending($thumb_cropped, false);
            imagesavealpha($thumb_cropped, true);
            // First, crop.
            imagecopyresampled(
                dst_image: $thumb_cropped,
                src_image: $image,
                dst_x: 0,
                dst_y: 0,
                src_x: $crop_x,
                src_y: $crop_y,
                dst_width: $cur_width,
                dst_height: $cur_height,
                src_width: $cur_width,
                src_height: $cur_height
            );
            // Then, resize.
            imagecopyresampled(
                dst_image: $thumb,
                src_image: $thumb_cropped,
                dst_x: 0,
                dst_y: 0,
                src_x: 0,
                src_y: 0,
                dst_width: $w,
                dst_height: $h,
                src_width: $crop_width,
                src_height: $crop_height
            );
        } else { // Resize
            imagecopyresampled(
                dst_image: $thumb,
                src_image: $image,
                dst_x: 0,
                dst_y: 0,
                src_x: 0,
                src_y: 0,
                dst_width: $w,
                dst_height: $h,
                src_width: $cur_width,
                src_height: $cur_height
            );
        }

        return match ($ext) {
            'jpg' => imagejpeg($thumb, $dest), // @phpstan-ignore theCodingMachineSafe.function
            'png' => imagepng($thumb, $dest), // @phpstan-ignore theCodingMachineSafe.function
            'gif' => imagegif($thumb, $dest), // @phpstan-ignore theCodingMachineSafe.function
            'webp' => imagewebp($thumb, $dest), // @phpstan-ignore theCodingMachineSafe.function, match.alwaysTrue
            default => false
        };
    }

    /**
     * Returns current file optimal height (resized)
     *
     * @return int optimal height
     */
    public function getOptimalHeight(): int
    {
        $this->checkAvailable();
        return (int)round($this->optimal_height, 1);
    }

    /**
     * Returns current file height
     *
     * @return int current height
     */
    public function getHeight(): int
    {
        $this->checkAvailable();
        return $this->height;
    }

    /**
     * Returns current file optimal width (resized)
     *
     * @return int optimal width
     */
    public function getOptimalWidth(): int
    {
        $this->checkAvailable();
        return (int)round($this->optimal_width, 1);
    }

    /**
     * Returns current file width
     *
     * @return int current width
     */
    public function getWidth(): int
    {
        $this->checkAvailable();
        return $this->width;
    }

    /**
     * Returns current file format
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
        $this->checkAvailable();
        return $this->file_path;
    }

    /**
     * Returns current mime type
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
