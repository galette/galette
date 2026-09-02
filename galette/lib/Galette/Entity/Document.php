<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Entity;

use ArrayObject;
use Safe\DateTime;
use Galette\Core\Authentication;
use Galette\Core\Login;
use Galette\Features\I18n;
use Galette\Features\Permissions;
use Galette\IO\FileTrait;
use Galette\Repository\Documents;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;
use Galette\Core\Db;
use Analog\Analog;

/**
 * Documents
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class Document
{
    use I18n;
    use Permissions;
    use FileTrait {
        writeOnDisk as protected trait_writeOnDisk;
    }

    public const string TABLE = 'documents';
    public const string PK = 'id_document';

    private int $id;
    private string $type;
    private string $filename;
    private DateTime $creation_date;
    protected string $store_path = GALETTE_DOCUMENTS_PATH;
    private ?string $comment = null;
    /** @var string[] */
    private array $errors = [];

    /**
     * Main constructor
     *
     * @param Db                                      $zdb  Database instance
     * @param int|ArrayObject<string,int|string>|null $args Arguments
     */
    public function __construct(private Db $zdb, int|ArrayObject|null $args = null)
    {
        $this->can_public = true;

        $this->init($this->store_path);

        if (is_int($args)) {
            $this->load($args);
        } elseif ($args instanceof ArrayObject) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Load a document from its identifier
     *
     * @param int $id Identifier
     */
    private function load(int $id): void
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->limit(1)->where([self::PK => $id]);

            $results = $this->zdb->execute($select);
            /** @var ArrayObject<string, int|string> $res */
            $res = $results->current();
            $this->loadFromRS($res);
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred loading document #' . $id . "Message:\n"
                . $e->getMessage(),
                Analog::ERROR
            );
        }
    }

    /**
     * Check if a document can be shown
     *
     * @param Login $login Login
     */
    public function canShow(Login $login): bool
    {
        $access_level = $login->getAccessLevel();
        return match ($this->getPermission()) {
            FieldsConfig::ALL => true,
            FieldsConfig::NOBODY => false,
            FieldsConfig::ADMIN => $access_level >= Authentication::ACCESS_ADMIN,
            FieldsConfig::STAFF => $access_level >= Authentication::ACCESS_STAFF,
            FieldsConfig::MANAGER => $access_level >= Authentication::ACCESS_MANAGER,
            FieldsConfig::USER_WRITE, FieldsConfig::USER_READ => $access_level >= Authentication::ACCESS_USER,
            default => false,
        };
    }

    /**
     * Load document from a db ResultSet
     *
     * @param ArrayObject<string, int|string> $rs ResultSet
     */
    private function loadFromRS(ArrayObject $rs): void
    {
        $this->id = (int)$rs->{self::PK};
        $this->type = $rs->type;
        $this->permission = (int)$rs->visible;
        $this->filename = $rs->filename;
        $this->comment = $rs->comment;
        $this->creation_date = new DateTime($rs->creation_date);
    }

    /**
     * Store document in database
     *
     * @param array<string,mixed>          $post  POST data
     * @param array<UploadedFileInterface> $files Uploaded files
     */
    public function store(array $post, array $files): bool
    {
        global $login;

        $this->setType($post['document_type']);
        $this->setComment($post['comment']);
        $this->permission = (int)$post['visible'];

        $handled = $this->handleFiles($files);
        if ($handled !== true) {
            $this->errors = $handled;
            return false;
        }

        try {
            $documents = new Documents($this->zdb, $login);

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
                if (!in_array($this->type, $documents->getSystemTypes(false))) {
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
     */
    public function remove(?array $ids = null): bool
    {
        if ($ids == null) {
            $ids[] = $this->id;
        }

        try {
            $this->zdb->beginTransaction();
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

            $this->zdb->commit();
            return true;
        } catch (Throwable $e) {
            $this->zdb->rollback();
            Analog::log(
                'Unable to delete document #' . implode(', #', $ids) . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Remove document file
     */
    protected function removeFile(): bool
    {
        $file = $this->getDestDir() . $this->getDocumentFilename();
        if (file_exists($file)) {
            return unlink($file); //@phpstan-ignore theCodingMachineSafe.function
        }

        Analog::log('File ' . $file . ' does not exist', Analog::WARNING);
        return false;
    }

    /**
     * Get file URL
     */
    public function getURL(): string
    {
        return $this->getDestDir() . $this->getDocumentFileName();
    }

    /**
     * Get document ID
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Get document file name
     */
    public function getDocumentFilename(): string
    {
        return $this->filename ?? '';
    }

    /**
     * Set comment
     * @param ?string $comment Comment to set
     */
    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * Get comment
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * Set type
     *
     * @param string $type Type
     */
    public function setType(string $type): self
    {
        $this->type = strip_tags($type);
        return $this;
    }

    /**
     * Get type
     */
    public function getType(): string
    {
        return $this->type ?? '';
    }

    /**
     * Get creation date
     *
     * @param bool $formatted Return formatted date (default) or not
     */
    public function getCreationDate(bool $formatted = true): string|DateTime
    {
        if ($formatted) {
            return $this->creation_date->format(_T('Y-m-d H:i:s'));
        }
        return $this->creation_date;
    }

    /**
     * Get document system type
     *
     * @param string $type       Document type
     * @param bool   $translated Return translated types (default) or not
     */
    public function getSystemType(string $type, bool $translated = true): string
    {
        global $login;

        $documents = new Documents($this->zdb, $login);

        return $documents->getSystemTypes($translated)[$type] ?? _T($type);
    }

    /**
     * Handle files
     *
     * @param array<UploadedFileInterface> $files Files sent
     *
     * @return string[]|true
     */
    public function handleFiles(array $files): array|bool
    {
        if (!isset($files['document_file'])) {
            return true;
        }
        $this->errors = [];
        // document upload
        $this->upload($files, 'document_file');
        if (count($this->uploadErrors())) {
            $this->errors = $this->uploadErrors();
        } elseif (isset($this->name_wo_ext)) {
            $this->filename = sprintf(
                '%s.%s',
                $this->name_wo_ext,
                $this->extension
            );
        }

        if (count($this->errors) > 0) {
            Analog::log(
                'Some errors has been thew attempting to edit/store a document file' . "\n"
                . print_r($this->errors, true),
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
     * @param UploadedFileInterface $file Temporary file
     *
     * @return true|int
     */
    public function writeOnDisk(UploadedFileInterface $file): bool|int
    {
        //remove existing file when updating
        if (isset($this->id) && $this->id > 0) {
            $this->removeFile();
        }
        return $this->trait_writeOnDisk($file);
    }
}
