<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Db;

use Galette\Core\RowsCache;
use Laminas\Db\Adapter\Driver\ResultInterface;
use Laminas\Db\Adapter\Driver\StatementInterface;
use Laminas\Db\Adapter\ParameterContainer;

/**
 * Prepared write statement forgetting cached rows of its table each time it runs
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ForgettingStatement implements StatementInterface
{
    /**
     * @param StatementInterface $statement Decorated statement
     * @param string             $table     Written table name, with prefix
     */
    public function __construct(
        private readonly StatementInterface $statement,
        private readonly string $table
    ) {
    }

    /**
     * Execute
     *
     * @param mixed $parameters Parameters
     */
    public function execute(mixed $parameters = null): ResultInterface
    {
        $result = $this->statement->execute($parameters);
        RowsCache::forget($this->table);
        return $result;
    }

    /**
     * Get resource
     */
    public function getResource(): mixed
    {
        return $this->statement->getResource();
    }

    /**
     * Prepare
     *
     * @param mixed $sql SQL
     */
    public function prepare(mixed $sql = null): mixed
    {
        return $this->statement->prepare($sql);
    }

    /**
     * Is prepared?
     */
    public function isPrepared(): bool
    {
        return (bool)$this->statement->isPrepared();
    }

    /**
     * Set SQL
     *
     * @param mixed $sql SQL
     */
    public function setSql(mixed $sql): static
    {
        $this->statement->setSql($sql);
        return $this;
    }

    /**
     * Get SQL
     */
    public function getSql(): mixed
    {
        return $this->statement->getSql();
    }

    /**
     * Set parameter container
     *
     * @param ParameterContainer $parameterContainer Parameter container
     */
    public function setParameterContainer(ParameterContainer $parameterContainer): static
    {
        $this->statement->setParameterContainer($parameterContainer);
        return $this;
    }

    /**
     * Get parameter container
     */
    public function getParameterContainer(): mixed
    {
        return $this->statement->getParameterContainer();
    }
}
