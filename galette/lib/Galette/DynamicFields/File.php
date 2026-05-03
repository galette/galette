<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\DynamicFields;

use Galette\Core\Db;

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
     * @param int         $pos    Position in the list of values  (0-based)
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
}
