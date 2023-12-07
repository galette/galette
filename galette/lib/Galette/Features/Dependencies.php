<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Dependencies feature
 *
 * PHP version 5
 *
 * Copyright © 2023 The Galette Team
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
 *
 * @category  Features
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     2023-09-25
 */

namespace Galette\Features;

use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Core\Logo;
use Galette\Core\Preferences;
use Galette\DynamicFields\Choice;
use Galette\DynamicFields\Separator;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\PdfModel;
use Galette\Entity\Texts;
use Galette\Repository\DynamicFieldsSet;
use Galette\DynamicFields\DynamicField;
use Analog\Analog;
use NumberFormatter;
use Slim\Router;

/**
 * Dependencies feature
 *
 * @category  Features
 * @name      Dependencies
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     2023-09-25
 */

trait Dependencies
{
    protected array $_deps = array(
        'picture'   => true,
        'groups'    => true,
        'dues'      => true,
        'parent'    => false,
        'children'  => false,
        'dynamics'  => false,
        'socials'   => false
    );

    /**
     * Set dependencies
     *
     * @param array $deps Dependencies to set
     *
     * @return $this
     */
    public function setDeps(array $deps): self
    {
        $this->_deps = array_merge(
            $this->_deps,
            $deps
        );
        return $this;
    }

    /**
     * Reset dependencies to load
     *
     * @return $this
     */
    public function disableAllDeps(): self
    {
        $this->_deps = array_fill_keys(
            array_keys($this->_deps),
            false
        );
        return $this;
    }

    /**
     * Enable all dependencies to load
     *
     * @return $this
     */
    public function enableAllDeps(): self
    {
        foreach ($this->_deps as &$dep) {
            $dep = true;
        }
        return $this;
    }

    /**
     * Enable a load dependency
     *
     * @param string $name Dependency name
     *
     * @return $this
     */
    public function enableDep(string $name): self
    {
        if (!isset($this->_deps[$name])) {
            Analog::log(
                'dependency ' . $name . ' does not exists!',
                Analog::WARNING
            );
        } else {
            $this->_deps[$name] = true;
        }

        return $this;
    }

    /**
     * Enable a load dependency
     *
     * @param string $name Dependency name
     *
     * @return $this
     */
    public function disableDep(string $name): self
    {
        if (!isset($this->_deps[$name])) {
            Analog::log(
                'dependency ' . $name . ' does not exists!',
                Analog::WARNING
            );
        } else {
            $this->_deps[$name] = false;
        }

        return $this;
    }

    /**
     * Is load dependency enabled?
     *
     * @param string $name Dependency name
     *
     * @return boolean
     */
    protected function isDepEnabled(string $name): bool
    {
        return $this->_deps[$name];
    }
}
