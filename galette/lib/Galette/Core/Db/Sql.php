<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Db;

use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Adapter\Driver\StatementInterface;
use Laminas\Db\Sql\Delete;
use Laminas\Db\Sql\Insert;
use Laminas\Db\Sql\PreparableSqlInterface;
use Laminas\Db\Sql\Sql as LaminasSql;
use Laminas\Db\Sql\TableIdentifier;
use Laminas\Db\Sql\Update;

/**
 * SQL builder whose prepared writes forget cached rows of their table
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @see \Galette\Core\RowsCache
 */
class Sql extends LaminasSql
{
    /**
     * Prepare a statement
     *
     * @param PreparableSqlInterface $sqlObject SQL object
     * @param ?StatementInterface    $statement Statement
     * @param ?AdapterInterface      $adapter   Adapter
     */
    public function prepareStatementForSqlObject(
        PreparableSqlInterface $sqlObject,
        ?StatementInterface $statement = null,
        ?AdapterInterface $adapter = null
    ): StatementInterface {
        $statement = parent::prepareStatementForSqlObject($sqlObject, $statement, $adapter);
        if ($sqlObject instanceof Insert || $sqlObject instanceof Update || $sqlObject instanceof Delete) {
            $table = $sqlObject->getRawState('table');
            return new ForgettingStatement(
                $statement,
                $table instanceof TableIdentifier ? $table->getTable() : (string)$table
            );
        }
        return $statement;
    }
}
