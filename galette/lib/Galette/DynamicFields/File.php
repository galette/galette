<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\DynamicFields;

use Galette\Core\Db;
use Galette\Core\Logs;
use Galette\Entity\DynamicFieldsHandle;
use Throwable;

use function Safe\unlink;

/**
 * File dynamic field
 *
 * @author Guillaume Rousse <guillomovitch@gmail.com>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class File extends DynamicField
{
    /**
     * Default constructor
     *
     * @param Db   $zdb Database instance
     * @param ?int $id  Optional field id to load data
     */
    public function __construct(Db $zdb, ?int $id = null)
    {
        parent::__construct($zdb, $id);
        $this->has_data = true;
        $this->has_size = true;
        $this->multi_valued = true;
    }

    /**
     * Get field type
     */
    public function getType(): int
    {
        return self::FILE;
    }

    /**
     * Get file name on disk
     *
     * @param int         $id     Object (member, contribution, ...) ID
     * @param int         $pos    Value index in the list of values (1-based)
     * @param string|null $prefix Forced file prefix; if null (defaults) form_name wil be used verbatim
     */
    public function getFileName(int $id, int $pos, ?string $prefix = null): string
    {
        $form_name = $this->form;
        if ($form_name === 'adh') {
            $form_name = 'member'; //fix expected filename
        }

        return str_replace(
            [
                '%form',
                '%oid',
                '%fid',
                '%pos'
            ],
            [
                $prefix ?? $form_name,
                (string)$id,
                (string)$this->id,
                (string)$pos
            ],
            '%form_%oid_field_%fid_value_%pos'
        );
    }

    /**
     * Get the path of the file stored for an occurrence, if any
     *
     * Files uploaded before Galette 1.2 are all prefixed with `member`, whatever
     * the form they belong to; fall back on that name, as other call sites do.
     *
     * @param int $id  Object (member, contribution, ...) ID
     * @param int $pos Value index in the list of values (1-based)
     */
    public function getFilePath(int $id, int $pos): ?string
    {
        $filename = GALETTE_FILES_PATH . $this->getFileName($id, $pos);
        if (file_exists($filename)) {
            return $filename;
        }

        $legacy = GALETTE_FILES_PATH . $this->getFileName($id, $pos, 'member');
        return file_exists($legacy) ? $legacy : null;
    }

    /**
     * Get the paths of the files stored on disk for the file fields of a form
     *
     * Must be called before the values are removed from the database, since
     * file names are built from them.
     *
     * @param Db          $zdb   Database instance
     * @param string      $form  Form name
     * @param ?array<int> $items Restrict to these objects; all of them when null
     * @param ?int        $field Restrict to this field; all file fields of the form when null
     *
     * @return array<string>
     */
    public static function getStoredFiles(Db $zdb, string $form, ?array $items = null, ?int $field = null): array
    {
        if ($items === []) {
            return [];
        }

        $select = $zdb->select(DynamicFieldsHandle::TABLE, 'c');
        $select->columns([DynamicFieldsHandle::PK, 'val_index'])
            ->join(
                ['t' => PREFIX_DB . self::TABLE],
                'c.' . self::PK . '=t.' . self::PK,
                [self::PK]
            )
            ->where([
                'c.field_form' => $form,
                't.field_type' => self::FILE
            ]);
        if ($items !== null) {
            $select->where->in('c.' . DynamicFieldsHandle::PK, $items);
        }
        if ($field !== null) {
            $select->where(['c.' . self::PK => $field]);
        }

        $fields = [];
        $files = [];
        foreach ($zdb->execute($select) as $row) {
            $fid = (int)$row->{self::PK};
            $fields[$fid] ??= new self($zdb, $fid);
            $path = $fields[$fid]->getFilePath((int)$row->{DynamicFieldsHandle::PK}, (int)$row->val_index);
            if ($path !== null) {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Remove files from disk
     *
     * Call it once database changes are committed (see Db::afterCommit()): a
     * failure is logged, and does not stop the removal of the other files.
     *
     * @param array<string> $files Paths of the files to remove
     */
    public static function removeFiles(array $files): void
    {
        foreach ($files as $file) {
            try {
                if (file_exists($file)) {
                    unlink($file);
                }
            } catch (Throwable $e) {
                Logs::exception($e, 'Unable to remove dynamic field file ' . $file);
            }
        }
    }
}
