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

use Laminas\Db\Sql\Select;

/**
 * This class stores and serve the logo.
 * If no custom logo is found, we take galette's default one.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Logo extends Picture
{
    protected string|int $id = 'custom_logo';
    // Database wants a member id (integer), not a string.
    // Will be used to query the correct id
    protected int $db_id = 0;
    protected bool $custom = true;

    /**
     * Default constructor.
     */
    public function __construct()
    {
        parent::__construct($this->id);
    }

    /**
     * Gets the default picture to show, anyway
     *
     * @see Picture::getDefaultPicture()
     *
     * @return void
     */
    protected function getDefaultPicture(): void
    {
        $this->file_path = realpath(_CURRENT_THEME_PATH . 'images/galette.png');
        $this->format = 'png';
        $this->mime = 'image/png';
        $this->custom = false;
    }

    /**
     * Returns the relevant query to check if picture exists in database.
     *
     * @see picture::getCheckFileQuery()
     *
     * @return Select SELECT query
     */
    protected function getCheckFileQuery(): Select
    {
        global $zdb;

        $select = $zdb->select(self::TABLE);
        $select->columns(
            [
                'picture',
                'format'
            ]
        );
        $select->where([self::PK => $this->db_id]);
        return $select;
    }

    /**
     * Returns custom state
     *
     * @return boolean
     */
    public function isCustom(): bool
    {
        return $this->custom;
    }
}
