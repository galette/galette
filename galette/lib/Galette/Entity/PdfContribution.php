<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * PDF contributions model
 *
 * PHP version 5
 *
 * Copyright © 2020 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-11-21
 */

namespace Galette\Entity;

use Analog\Analog;
use Galette\Core\Db;
use Galette\Core\Preferences;
use Galette\Entity\Contribution;
use NumberFormatter;

/**
 * PDF contribution model
 *
 * @category  Entity
 * @name      PdfContribution
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-11-21
 */

abstract class PdfContribution extends PdfModel
{
    /**
     * Main constructor
     *
     * @param Db          $zdb         Database instance
     * @param Preferences $preferences Galette preferences
     * @param int         $type        Model type
     * @param mixed       $args        Arguments
     */
    public function __construct(Db $zdb, Preferences $preferences, int $type, $args = null)
    {
        parent::__construct($zdb, $preferences, $type, $args);

        $this->setPatterns(
            $this->getMemberPatterns() + $this->getContributionPatterns()
        );
    }

    /**
     * Build legend array
     *
     * @return array
     */
    public function getLegend(): array
    {
        $legend = parent::getLegend();

        $patterns = $this->getContributionPatterns(false);

        $legend['contribution'] = [
            'title'     => _T('Contribution information'),
            'patterns'  => $patterns
        ];

        return $legend;
    }
}
