<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Slim application
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
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.5dev - 2020-12-12
 */

namespace Galette\Core;

use DI\Bridge\Slim;
use DI\ContainerBuilder;

/**
 * Light Slim application
 *
 * @category  Core
 * @name      Db
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://framework.zend.com/apidoc/2.2/namespaces/Zend.Db.html
 * @since     Available since 0.9.5dev - 2020-12-12
 */
class LightSlimApp extends \DI\Bridge\Slim\App
{
    /**
     * Configure the container builder.
     *
     * @param ContainerBuilder $builder Builder to configure
     *
     * @return void
     */
    protected function configureContainer(ContainerBuilder $builder)
    {
        $builder->useAnnotations(true);
        $builder->addDefinitions([
            'templates.path'                    => GALETTE_ROOT . GALETTE_THEME,
            'settings.displayErrorDetails'      => (GALETTE_MODE === 'DEV'),
            'settings.addContentLengthHeader'   => false,
            'galette'                           => [
                'mode'      => 'NEED_UPDATE',
                'logger'    => [
                    'name'  => 'galette',
                    'level' => \Monolog\Logger::DEBUG,
                    'path'  => GALETTE_LOGS_PATH . '/galette_slim.log',
                ]
            ],
            'mode'          => 'NEED_UPDATE', //TODO: rely on galette.mode
            'galette.mode'  => 'NEED_UPDATE',
            'session'       => \DI\autowire('\RKA\Session')
        ]);
    }
}
