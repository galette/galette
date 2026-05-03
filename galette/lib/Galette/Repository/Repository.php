<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Repository;

use Analog\Analog;
use Galette\Core\Db;
use Galette\Core\Pagination;
use Galette\Core\Preferences;
use Galette\Core\Login;
use Laminas\Db\ResultSet\ResultSet;
use RuntimeException;

/**
 * Repositories
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
abstract class Repository
{
    protected Pagination $filters;
    /** @var array<int|string,mixed> */
    protected array $defaults = [];

    /**
     * Main constructor
     *
     * @param Db          $zdb         Database instance
     * @param Preferences $preferences Galette preferences
     * @param Login       $login       Logged in instance
     * @param ?string     $entity      Related entity class name
     * @param ?string     $ns          Related entity namespace
     * @param string      $prefix      Prefix (for plugins)
     */
    public function __construct(
        protected Db $zdb,
        protected Preferences $preferences,
        protected Login $login,
        protected ?string $entity = null,
        ?string $ns = null,
        protected string $prefix = ''
    ) {
        if ($entity === null) {
            //no entity class name provided. Take Repository
            //class name and remove trailing 's'
            $r = array_slice(explode('\\', static::class), -1);
            $repo = $r[0];
            $ent = substr($repo, 0, -1);
            if ($ent != $repo) {
                $entity = $ent;
            } else {
                throw new RuntimeException(
                    'Unable to find entity name from repository one. Please '
                    . 'provide entity name in repository constructor'
                );
            }
        }
        if ($ns === null) {
            $ns = 'Galette\\Entity';
        }
        $entity = $ns . '\\' . $entity;
        if (class_exists($entity)) {
            $this->entity = $entity;
        } else {
            throw new RuntimeException(
                'Entity class ' . $entity . ' cannot be found!'
            );
        }

        if (method_exists($this, 'checkUpdate')) {
            $this->loadDefaults();
            if (count($this->defaults)) {
                $this->checkUpdate();
            } else {
                Analog::log(
                    'No defaults loaded!',
                    Analog::ERROR
                );
            }
        }
    }

    /**
     * Get list
     *
     * @return array<int, object>|ResultSet
     */
    abstract public function getList(): array|ResultSet;

    /**
     * Add default values in database
     *
     * @param bool $check_first Check first if it seems initialized, defaults to true
     */
    abstract public function installInit(bool $check_first = true): bool;

    /**
     * Get filters
     */
    protected function getFilters(): Pagination
    {
        return $this->filters;
    }

    /**
     * Set filters
     *
     * @param Pagination $filters Filters
     */
    protected function setFilters(Pagination $filters): self
    {
        $this->filters = $filters;
        return $this;
    }

    /**
     * Load and get default values
     *
     * @return array<string,mixed>
     */
    protected function loadDefaults(): array
    {
        return $this->defaults;
    }

    /**
     * Is field allowed to order? it should be present in
     * provided fields list (those that are SELECT'ed).
     *
     * @param string         $field_name Field name to order by
     * @param ?array<string> $fields     SELECTE'ed fields
     */
    protected function canOrderBy(string $field_name, ?array $fields): bool
    {
        if ($fields === null) {
            return true;
        } elseif (in_array($field_name, $fields)) {
            return true;
        } else {
            Analog::log(
                'Trying to order by ' . $field_name . ' while it is not in '
                . 'selected fields.',
                Analog::WARNING
            );
            return false;
        }
    }
}
