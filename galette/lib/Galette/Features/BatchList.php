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

namespace Galette\Features;

use Galette\Entity\Adherent;
use Galette\Entity\Social;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Batch list feature
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

trait BatchList
{
    /**
     * Get filter name in session
     *
     * @param array|null $args Route arguments
     *
     * @return string
     */
    abstract public function getFilterName(array $args = null): string;
}
