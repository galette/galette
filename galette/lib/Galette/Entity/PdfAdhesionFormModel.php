<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

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
    public function __construct(Db $zdb, Preferences $preferences, ArrayObject|int|null $args = null)
    {
        parent::__construct(
            zdb: $zdb,
            preferences: $preferences,
            type: self::ADHESION_FORM_MODEL,
            args: $args
        );
        $this->setLegacy()->setPatterns($this->getMemberPatterns());
    }
}
