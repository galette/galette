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

namespace Galette\Entity;

use ArrayObject;
use DateTime;
use Galette\Core\Authentication;
use Galette\Core\Login;
use Galette\Features\I18n;
use Galette\Features\Permissions;
use Galette\IO\FileInterface;
use Galette\IO\FileTrait;
use Throwable;
use Galette\Core\Db;
use Analog\Analog;

/**
 * Documents
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class Document implements FileInterface
{
    use I18n;
    use Permissions;
    use FileTrait {
        store as protected trait_store;
        writeOnDisk as protected trait_writeOnDisk;
    }

    public const TABLE = 'documents';
    public const PK = 'id_document';

    public const STATUS = 'status';
    public const RULES = 'rules';
    public const ADHESION = 'adhesion';
    public const MINUTES = 'minutes';
    public const VOTES = 'votes';

    private Db $zdb;
    private int $id;
    private string $type;
    private string $filename;
    private DateTime $creation_date;
    protected string $store_path = GALETTE_DOCUMENTS_PATH;
    private ?string $comment = null;
    /** @var array<string> */
    private array $errors = [];
    private bool $public_list = false;

    /**
     * Main constructor
     *
     * @param Db                                      $zdb  Database instance
     * @param int|ArrayObject<string,int|string>|null $args Arguments
     */
    public function __construct(Db $zdb, int|ArrayObject $args = null)
    {
        $this->zdb = $zdb;
        $this->can_public = true;

        $this->init($this->store_path);

        if (is_int($args)) {
            $this->load($args);
        } elseif ($args instanceof ArrayObject) {
            $this->loadFromRs($args);
        }
    }

    /**
     * Load a document from its identifier
     *
     * @param integer $id Identifier
     *
     * @return void
     */
    private function load(int $id): void
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->limit(1)->where([self::PK => $id]);

            $results = $this->zdb->execute($select);
            /** @var ArrayObject<string, int|string> $res */
            $res = $results->current();
            $this->loadFromRs($res);
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred loading document #' . $id . "Message:\n" .
                $e->getMessage(),
                Analog::ERROR
            );
        }
    }

    /**
     * Get documents
     *
     * @param string|null $type Type to retrieve
     *
     * @return array<int,Document>
     *
     * @throws Throwable
     */
    public function getList(string $type = null): array
    {
        global $login;

        try {
            $select = $this->zdb->select(self::TABLE);

            if ($type !== null) {
                $select->where(['type' => $type]);
            }

            $select->order(self::PK);

            $results = $this->zdb->execute($select);
            $documents = [];
            $access_level = $login->getAccessLevel();

            foreach ($results as $r) {
                // skip entries according to access control
                if (
                    $r->visible == FieldsConfig::NOBODY &&
                    ($this->public_list === true || ($this->public_list === false && !$login->isAdmin())) ||
                    ($r->visible == FieldsConfig::ADMIN &&
                        $access_level < Authentication::ACCESS_ADMIN) ||
                    ($r->visible == FieldsConfig::STAFF &&
                        $access_level < Authentication::ACCESS_STAFF) ||
                    ($r->visible == FieldsConfig::MANAGER &&
                        $access_level < Authentication::ACCESS_MANAGER) ||
                    (($r->visible == FieldsConfig::USER_READ || $r->visible == FieldsConfig::USER_WRITE) &&
                        $access_level < Authentication::ACCESS_USER)
                ) {
                    continue;
                }

                $documents[$r->{self::PK}] = new Document($this->zdb, $r);
            }
            return $documents;
        } catch (Throwable $e) {
            Analog::log(
                "An error occurred loading documents. Message:\n" .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Get list by type
     *
     * @return array<string, array<int, Document>>
     *
     * @throws Throwable
     */
    public function getTypedList(): array
    {
        $this->public_list = true;
        $list = $this->getList();
        $sys_types = $this->getSystemTypes(false);

        $typed_list = array_fill_keys($sys_types, []);
        foreach ($list as $doc_id => $document) {
            $typed_list[$document->getType()][] = $document;
        }

        //cleanup: some system types may have no entries
        foreach ($sys_types as $type) {
            if (count($typed_list[$type]) == 0) {
                unset($typed_list[$type]);
            }
        }

        return $typed_list;
    }

    /**
     * Check if a document can be shown
     *
     * @param Login $login Login
     *
     * @return boolean
     */
    public function canShow(Login $login): bool
    {
        $access_level = $login->getAccessLevel();

        switch ($this->getPermission()) {
            case FieldsConfig::ALL:
                return true;
            case FieldsConfig::NOBODY:
                return false;
            case FieldsConfig::ADMIN:
                return $access_level >= Authentication::ACCESS_ADMIN;
            case FieldsConfig::STAFF:
                return $access_level >= Authentication::ACCESS_STAFF;
            case FieldsConfig::MANAGER:
                return $access_level >= Authentication::ACCESS_MANAGER;
            case FieldsConfig::USER_WRITE:
            case FieldsConfig::USER_READ:
                return $access_level >= Authentication::ACCESS_USER;
        }

        return false;
    }

    /**
     * Load document from a db ResultSet
     *
     * @param ArrayObject<string, int|string> $rs ResultSet
     *
     * @return void
     */
    private function loadFromRs(ArrayObject $rs): void
    {
        $this->id = $rs->{self::PK};
        $this->type = $rs->type;
        $this->permission = $rs->visible;
        $this->filename = $rs->filename;
        $this->comment = $rs->comment;
        $this->creation_date = new DateTime($rs->creation_date);
    }

    /**
     * Store document in database
     *
     * @param array<string,mixed> $post  POST data
     * @param array<string,mixed> $files Files
     *
     * @return boolean
     */
    public function store(array $post, array $files): bool
    {
        $this->setType($post['document_type']);
        $this->setComment($post['comment']);
        $this->permission = (int)$post['visible'];

        $handled = $this->handleFiles($files);
        if ($handled !== true) {
            $this->errors = $handled;
            return false;
        }

        try {
            $values = [
                'type' => $this->type,
                'filename' => $this->filename,
                'visible' => $this->getPermission(),
                'comment' => $this->comment,
            ];
            if (isset($this->id) && $this->id > 0) {
                $update = $this->zdb->update(self::TABLE);
                $update->set($values)->where([self::PK => $this->id]);
                $this->zdb->execute($update);
            } else {
                $values['creation_date'] = date('Y-m-d H:i:s');
                $insert = $this->zdb->insert(self::TABLE);
                $insert->values($values);
                $add = $this->zdb->execute($insert);
                if (!$add->count() > 0) {
                    Analog::log('Not stored!', Analog::ERROR);
                    return false;
                }

                $this->id = $this->zdb->getLastGeneratedValue($this);
                if (!in_array($this->type, $this->getSystemTypes(false))) {
                    $this->addTranslation($this->type);
                }
            }
            return true;
        } catch (Throwable $e) {
            $this->removeFile();
            Analog::log(
                'An error occurred storing document: ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Remove document
     *
     * @param array<int>|null $ids IDs to remove, default to current id
     *
     * @return boolean
     */
    public function remove(array $ids = null): bool
    {
        if ($ids == null) {
            $ids[] = $this->id;
        }

        try {
            $this->zdb->connection->beginTransaction();
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where([self::PK => $ids]);
            $this->zdb->execute($delete);
            if (!$this->removeFile()) {
                throw new \RuntimeException('cannot remove file document from disk');
            }
            Analog::log(
                'Document #' . implode(', #', $ids) . ' deleted successfully.',
                Analog::INFO
            );

            $this->zdb->connection->commit();
            return true;
        } catch (Throwable $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                'Unable to delete document #' . implode(', #', $ids) . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Remove document file
     *
     * @return bool
     */
    protected function removeFile(): bool
    {
        $file = $this->getDestDir() . $this->getDocumentFilename();
        if (file_exists($file)) {
            return unlink($file);
        }

        Analog::log('File ' . $file . ' does not exist', Analog::WARNING);
        return false;
    }

    /**
     * Get file URL
     *
     * @return string
     */
    public function getURL(): string
    {
        return $this->getDestDir() . $this->getDocumentFileName();
    }

    /**
     * Get document ID
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Get document file name
     *
     * @return string
     */
    public function getDocumentFilename(): string
    {
        return $this->filename ?? '';
    }

    /**
     * Set comment
     * @param ?string $comment Comment to set
     *
     * @return self
     */
    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * Get comment
     *
     * @return ?string
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * Set type
     *
     * @param string $type Type
     *
     * @return self
     */
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type ?? '';
    }

    /**
     * Get creation date
     *
     * @param boolean $formatted Return formatted date (default) or not
     *
     * @return string|DateTime
     */
    public function getCreationDate(bool $formatted = true): string|DateTime
    {
        if ($formatted) {
            return $this->creation_date->format(_T('Y-m-d H:i:s'));
        }
        return $this->creation_date;
    }

    /**
     * Get system social types
     *
     * @param boolean $translated Return translated types (default) or not
     *
     * @return array<string,string>
     */
    public function getSystemTypes(bool $translated = true): array
    {
        if ($translated) {
            $systypes = [
                self::STATUS => _T('Association status'),
                self::RULES => _T('Rules of procedure'),
                self::ADHESION => _T('Adhesion form'),
                self::MINUTES => _T('Meeting minutes'),
                self::VOTES => _T('Votes results')
            ];
        } else {
            $systypes = [
                self::STATUS => 'Association status',
                self::RULES => 'Rules of procedure',
                self::ADHESION => 'Adhesion form',
                self::MINUTES => 'Meeting minutes',
                self::VOTES => 'Votes results'
            ];
        }
        return $systypes;
    }

    /**
     * Get system documents types
     *
     * @param string  $type       Document type
     * @param boolean $translated Return translated types (default) or not
     *
     * @return string
     */
    public function getSystemType(string $type, bool $translated = true): string
    {
        return $this->getSystemTypes($translated)[$type] ?? _T($type);
    }

    /**
     * Get all known types
     *
     * @return array<string,string>
     *
     * @throws Throwable
     */
    public function getTypes(): array
    {
        $types = $this->getSystemTypes();

        $select = $this->zdb->select(self::TABLE);
        $select->quantifier('DISTINCT');
        $select->where->notIn('type', array_keys($this->getSystemTypes(false)));
        $results = $this->zdb->execute($select);

        foreach ($results as $r) {
            $types[$r->type] = $r->type;
        }

        return $types;
    }

    /**
     * Handle files
     *
     * @param array<string,mixed> $files Files sent
     *
     * @return array<string>|true
     */
    public function handleFiles(array $files): array|bool
    {
        $this->errors = [];
        // document upload
        if (isset($files['document_file'])) {
            if ($files['document_file']['error'] === UPLOAD_ERR_OK) {
                if ($files['document_file']['tmp_name'] != '') {
                    if (is_uploaded_file($files['document_file']['tmp_name'])) {
                        $res = $this->trait_store($files['document_file']);
                        if ($res < 0) {
                            $this->errors[] = $this->getErrorMessage($res);
                        } else {
                            $this->filename = sprintf(
                                '%s.%s',
                                $this->name_wo_ext,
                                $this->extension
                            );
                        }
                    }
                }
            } elseif (!isset($this->id)) {
                Analog::log(
                    $this->getPhpErrorMessage($files['document_file']['error']),
                    Analog::WARNING
                );
                $this->errors[] = $this->getPhpErrorMessage($files['document_file']['error']);
            }
        }

        if (count($this->errors) > 0) {
            Analog::log(
                'Some errors has been thew attempting to edit/store a document file' . "\n" .
                print_r($this->errors, true),
                Analog::ERROR
            );
            return $this->errors;
        } else {
            return true;
        }
    }

    /**
     * Get errors
     *
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Write file on disk
     *
     * @param string $tmpfile Temporary file
     * @param bool   $ajax    If the file comes from an ajax call (dnd)
     *
     * @return bool|int
     */
    public function writeOnDisk(string $tmpfile, bool $ajax): bool|int
    {
        //remove existing file when updating
        if (isset($this->id) && $this->id > 0) {
            $this->removeFile();
        }
        return $this->trait_writeOnDisk($tmpfile, $ajax);
    }
}
