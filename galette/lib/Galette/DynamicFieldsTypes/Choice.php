<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Choice field type
 *
 * PHP version 5
 *
 * Copyright © 2012-2013 The Galette Team
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
 * @category  DynamicFields
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.1dev - 2012-07-28
 */

namespace Galette\DynamicFieldsTypes;

use Analog\Analog as Analog;

/**
 * Choice field type
 *
 * @name      Separator
 * @category  DynamicFields
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

class Choice extends DynamicFieldType
{
    /**
     * Default constructor
     *
     * @param int $id Optionnal field id to load data
     */
    public function __construct($id)
    {
        parent::__construct($id);
        $this->has_data = true;
        $this->fixed_values = true;
    }

    /**
     * Get field type name
     *
     * @return String
     */
    public function getTypeName()
    {
        return _T("choice");
    }

    /**
     * Load field
     *
     * @return void
     */
    public function load()
    {
        parent::load();
        $this->repeat = 1;
    }
}
