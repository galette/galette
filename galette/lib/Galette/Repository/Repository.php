<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Repositories
 *
 * PHP version 5
 *
 * Copyright © 2013 The Galette Team
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
 * @category  Repository
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-02-26
 */

namespace Galette\Repository;

use Analog\Analog;

/**
 * Repositories
 *
 * @category  Repository
 * @name      Repository
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-02-26
 */
abstract class Repository
{
    protected $zdb;
    protected $preferences;
    protected $entity;

    /**
     * Main constructor
     *
     * @param Db          $zdb         Database instance
     * @param Preferences $preferences Galette preferences
     * @param string      $entity      Related entity class name
     */
    public function __construct($zdb, $preferences, $entity = null)
    {
        if ( !isset($zdb) ) {
            throw new \RuntimeException(
                get_class($this) . ' Database instance required!'
            );
        }
        $this->zdb = $zdb;
        $this->preferences = $preferences;

        if ( $entity === null ) {
            //no entity class name provided. Take Repository
            //class name and remove trailing 's'
            $r = array_slice(explode('\\', get_class($this)), -1);
            $repo = $r[0];
            $ent = substr($repo, 0, -1);
            if ( $ent != $repo ) {
                $entity = $ent;
            } else {
                throw new \RuntimeException(
                    'Unable to find entity name rom repository one. Please '.
                    'provide entity name in repository constructor'
                );
            }
        }
        $entity = 'Galette\\Entity\\' . $entity;
        if ( class_exists($entity) ) {
            $this->entity = $entity;
        } else {
            throw new \RuntimeException(
                'Entity class ' . $entity . ' cannot be found!'
            );
        }

    }

    /**
     * Get list
     *
     * @return Object[]
     */
    abstract public function getList();

    /**
     * Add default values in database
     *
     * @param array $defaults Defaults fields definition (optionnal)
     *
     * @return boolean
     */
    abstract public function installInit($defaults);
}

