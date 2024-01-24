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

namespace Galette\Entity;

use ArrayObject;
use Galette\Core\Db;
use Galette\Core\Preferences;

/**
 * PDF form model
 *
 * @author Guillaume Rousse <guillomovitch@gmail.com>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class PdfAdhesionFormModel extends PdfModel
{
    /**
     * Main constructor
     *
     * @param Db                                      $zdb         Database instance
     * @param Preferences                             $preferences Galette preferences
     * @param ArrayObject<string,int|string>|int|null $args        Arguments
     */
    public function __construct(Db $zdb, Preferences $preferences, ArrayObject|int $args = null)
    {
        parent::__construct($zdb, $preferences, self::ADHESION_FORM_MODEL, $args);
        $this->setPatterns($this->getMemberPatterns());
    }
}
