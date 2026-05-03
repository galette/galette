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
 * PDF contribution model
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

abstract class PdfContribution extends PdfModel
{
    /**
     * Main constructor
     *
     * @param Db                                      $zdb         Database instance
     * @param Preferences                             $preferences Galette preferences
     * @param int                                     $type        Model type
     * @param ArrayObject<string,int|string>|int|null $args        Arguments
     */
    public function __construct(Db $zdb, Preferences $preferences, int $type, ArrayObject|int|null $args = null)
    {
        parent::__construct($zdb, $preferences, $type, $args);

        $this
            ->setLegacy()
            ->setPatterns(
                $this->getMemberPatterns() + $this->getContributionPatterns()
            )
        ;
    }

    /**
     * Build legend array
     *
     * @return array<string,array<string,mixed>>
     */
    public function getLegend(): array
    {
        $legend = parent::getLegend();

        $patterns = $this->getContributionPatterns();

        $legend['contribution'] = [
            'title'     => _T('Contribution information'),
            'patterns'  => $patterns
        ];

        return $legend;
    }
}
