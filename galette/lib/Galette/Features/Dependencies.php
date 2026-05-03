<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Features;

use Analog\Analog;

/**
 * Dependencies feature
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property-read array<string, bool> $deps
 */

trait Dependencies
{
    /** @var array<string, bool> */
    protected array $deps = [
        'picture'   => true,
        'groups'    => true,
        'dues'      => true,
        'parent'    => false,
        'children'  => false,
        'dynamics'  => false,
        'socials'   => false
    ];

    /**
     * Set dependencies
     *
     * @param array<string, bool> $deps Dependencies to set
     */
    public function setDeps(array $deps): self
    {
        $this->deps = array_merge(
            $this->deps,
            $deps
        );
        return $this;
    }

    /**
     * Reset dependencies to load
     */
    public function disableAllDeps(): self
    {
        $this->deps = array_fill_keys(
            array_keys($this->deps),
            false
        );
        return $this;
    }

    /**
     * Enable all dependencies to load
     */
    public function enableAllDeps(): self
    {
        foreach ($this->deps as &$dep) {
            $dep = true;
        }
        return $this;
    }

    /**
     * Enable a load dependency
     *
     * @param string $name Dependency name
     */
    public function enableDep(string $name): self
    {
        if (!isset($this->deps[$name])) {
            Analog::log(
                'dependency ' . $name . ' does not exists!',
                Analog::WARNING
            );
        } else {
            $this->deps[$name] = true;
        }

        return $this;
    }

    /**
     * Enable a load dependency
     *
     * @param string $name Dependency name
     */
    public function disableDep(string $name): self
    {
        if (!isset($this->deps[$name])) {
            Analog::log(
                'dependency ' . $name . ' does not exists!',
                Analog::WARNING
            );
        } else {
            $this->deps[$name] = false;
        }

        return $this;
    }

    /**
     * Is load dependency enabled?
     *
     * @param string $name Dependency name
     */
    protected function isDepEnabled(string $name): bool
    {
        return $this->deps[$name];
    }
}
