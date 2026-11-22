<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Db;

use Doctrine\DBAL\Connection as DbalConnection;
use Laminas\Db\Adapter\Driver\Pdo\Connection;

/**
 * Laminas connection running on the PDO instance owned by Doctrine DBAL
 *
 * Both libraries track their own transaction depth. Transactions are delegated
 * to DBAL so there is only one depth to follow, whichever API the caller uses:
 * plugins still calling beginTransaction() on the Laminas connection end up in
 * the very same transaction, with savepoints for nested levels.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @deprecated 1.5.0 Laminas queries are kept as a fallback for plugins; use the DBAL connection instead.
 */
class LaminasConnection extends Connection
{
    /**
     * Constructor
     *
     * @param DbalConnection $dbal DBAL connection owning the PDO instance
     */
    public function __construct(private readonly DbalConnection $dbal)
    {
        $pdo = $dbal->getNativeConnection();
        if (!$pdo instanceof \PDO) {
            throw new \LogicException('DBAL connection does not run on PDO');
        }
        parent::__construct($pdo);
    }

    /**
     * Start a transaction, or a savepoint when one is already running
     */
    public function beginTransaction(): static
    {
        $this->dbal->beginTransaction();
        return $this;
    }

    /**
     * Commit current transaction, or release its savepoint when nested
     */
    public function commit(): static
    {
        $this->dbal->commit();
        return $this;
    }

    /**
     * Rollback current transaction, or to its savepoint when nested
     */
    public function rollback(): static
    {
        $this->dbal->rollBack();
        return $this;
    }

    /**
     * Is a transaction actually running?
     */
    public function inTransaction(): bool
    {
        return $this->dbal->isTransactionActive();
    }

    /**
     * Connection is owned by DBAL, it cannot be closed from here
     */
    public function disconnect(): static
    {
        return $this;
    }
}
