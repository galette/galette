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

declare(strict_types=1);

namespace Galette\Core;

use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Slim\App;

/**
 * Slim application
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @template TContainerInterface of (ContainerInterface|null)
 */
class SlimApp
{
    /** @var App<TContainerInterface> */
    private App $app;

    /**
     * Create a new Slim application
     */
    public function __construct()
    {
        $builder = new ContainerBuilder();
        $builder->useAttributes(true);
        $builder->addDefinitions([
            'galette'                           => [
                'mode'  => GALETTE_MODE,
                'logger'                            => [
                    'name'  => 'galette',
                    'level' => \Monolog\Logger::DEBUG,
                    'path'  => GALETTE_LOGS_PATH . '/galette_slim.log',
                ]
            ],
            'mode'              => GALETTE_MODE, //TODO: rely on galette.mode
            'galette.mode'      => GALETTE_MODE,
            'session'           => \DI\autowire('\RKA\Session')
        ]);
        $container = $builder->build();

        $this->app = Bridge::create($container);
    }

    /**
     * Get Slim application
     *
     * @return App<TContainerInterface>
     */
    public function getApp(): App
    {
        return $this->app; //@phpstan-ignore-line
    }
}
