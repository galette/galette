<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Safe\DateTime;
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
     */
    protected function getDefaultPicture(): void
    {
        $now = new DateTime();
        $special = ''; //default logo

        // Halloween special logo
        $compare_date = new DateTime(date('Y') . '-10-31');
        $date_diff = $compare_date->diff($now);
        if ($date_diff->days == 0 || $date_diff->invert == 1 && $date_diff->days <= 30) {
            $special = '_halloween';
        }

        // Xmas special logo
        $compare_date = new DateTime(date('Y') . '-12-25');
        $date_diff = $compare_date->diff($now);
        if ($date_diff->days == 0 || $date_diff->invert == 1 && $date_diff->days <= 30) {
            $special = '_xmas';
        }

        $this->format = 'webp';
        $this->mime = 'image/webp';
        $this->custom = false;
        $this->setDefaultPath(
            sprintf(
                '%s/images/galette%s.webp',
                _CURRENT_THEME_PATH,
                $special
            )
        );
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
     */
    public function isCustom(): bool
    {
        return $this->custom;
    }
}
