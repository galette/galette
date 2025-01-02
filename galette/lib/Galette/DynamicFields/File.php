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

namespace Galette\DynamicFields;

use Analog\Analog;
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
     *
     * @return integer
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
     *
     * @return string
     */
    public function getFileName(int $id, int $pos, ?string $prefix = null): string
    {
        $form_name = $this->form;
        if ($form_name === 'adh') {
            $form_name = 'member'; //fix expected filename
        }

        $filename = str_replace(
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

        return $filename;
    }
}
