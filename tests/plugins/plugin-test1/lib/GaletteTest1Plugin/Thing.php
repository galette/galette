<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteTest1Plugin;

use Galette\Core\Db;
use Galette\Core\Login;
use Galette\DynamicFields\DynamicField;
use Galette\Features\Dynamics;
use Psr\Http\Message\UploadedFileInterface;

/**
 * An object of the test plugin with dynamic fields, but no table
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Thing
{
    use Dynamics;

    /** @var array<string> */
    private array $errors = [];

    /** @var array<int, bool> Fields this thing does not use, by field ID */
    public array $hidden_fields = [];

    /** @var array<int, bool> Whether fields are required for this thing, by field ID */
    public array $required_fields = [];

    /**
     * Constructor
     *
     * @param Db     $zdb   Database instance
     * @param ?Login $login Login instance, the global one when null
     * @param ?int   $id    Thing identifier
     */
    public function __construct(
        private readonly Db $zdb,
        private readonly ?Login $login = null,
        private ?int $id = null
    ) {
    }

    /**
     * Is a dynamic field hidden for this thing?
     *
     * @param DynamicField $field Dynamic field
     */
    public function isDynamicFieldHidden(DynamicField $field): bool
    {
        return $this->hidden_fields[$field->getId()] ?? false;
    }

    /**
     * Is a dynamic field required for this thing?
     *
     * @param DynamicField $field Dynamic field
     */
    public function isDynamicFieldRequired(DynamicField $field): bool
    {
        return $this->required_fields[$field->getId()] ?? $field->isRequired();
    }

    /**
     * Check posted values
     *
     * @param array<string, mixed>                  $post  Posted values
     * @param ?array<string, UploadedFileInterface> $files Uploaded files
     */
    public function check(array $post, ?array $files = null): bool
    {
        $this->errors = [];
        return $this->dynamicsCheck(post: $post, required: [], disabled: [], files: $files);
    }

    /**
     * Store values
     *
     * Things have no table: a new thing gets the identifier an insert would have given.
     *
     * @param ?int $new_id Identifier of a new thing
     */
    public function store(?int $new_id = null): bool
    {
        if ($this->id === null) {
            if ($new_id === null) {
                return false;
            }
            $this->id = $new_id;
        }
        return $this->dynamicsStore();
    }

    /**
     * Store uploaded files
     *
     * @param array<string, UploadedFileInterface> $files Uploaded files
     */
    public function storeFiles(array $files): void
    {
        $this->dynamicsFiles($files);
    }

    /**
     * Remove values
     */
    public function remove(): bool
    {
        return $this->dynamicsRemove();
    }
}
