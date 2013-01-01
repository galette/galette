<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Logo handling
 *
 * PHP version 5
 *
 * Copyright © 2009-2013 The Galette Team
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
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-09-13
 */

namespace Galette\Core;

use Analog\Analog as Analog;

/**
 * This class stores and serve the logo.
 * If no custom logo is found, we take galette's default one.
 *
 * @category  Core
 * @name      Logo
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-09-13
 */
class Logo extends Picture
{
    protected $id = 'custom_logo';
    //database wants a member id (integer), not a string.
    //  Will be used to query the correct id
    protected $db_id = 0;
    protected $custom = true;

    /**
    * Default constructor.
    */
    public function __construct()
    {
        parent::__construct($this->id);
    }

    /**
    * Gets the default picture to show, anyways
    *
    * @see Picture::getDefaultPicture()
    *
    * @return void
    */
    protected function getDefaultPicture()
    {
        $this->file_path = _CURRENT_TEMPLATE_PATH . 'images/galette.png';
        $this->format = 'png';
        $this->mime = 'image/png';
        $this->custom = false;
    }

    /**
    * Returns the relevant query to check if picture exists in database.
    *
    * @see picture::getCheckFileQuery()
    *
    * @return string SELECT query
    */
    protected function getCheckFileQuery()
    {
        global $zdb;

        $select = new \Zend_Db_Select($zdb->db);
        $select->from(
            array(PREFIX_DB . self::TABLE),
            array(
                'picture',
                'format'
            )
        );
        $select->where(self::PK . ' = ?', $this->db_id);
        return $select;
    }

    /**
    * Returns custom state
    *
    * @return boolean
    */
    public function isCustom()
    {
        return $this->custom;
    }
}
