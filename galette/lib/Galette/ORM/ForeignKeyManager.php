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

namespace Galette\ORM;

use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * ORM Foreign Key Manager
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ForeignKeyManager
{
    /**
     * Post schema generation, to fix FK constraints names
     *
     * @param GenerateSchemaEventArgs $args Event arguments
     *
     * @return void
     *
     * @throws SchemaException
     */
    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $schema = $args->getSchema();

        foreach ($schema->getTables() as $table) {
            foreach ($table->getForeignKeys() as $fk) {
                $table->removeForeignKey($fk->getName());
                $table->addForeignKeyConstraint(
                    $fk->getForeignTableName(),
                    $fk->getLocalColumns(),
                    $fk->getForeignColumns(),
                    $fk->getOptions(),
                    $fk->getLocalColumns()[0]
                );
            }
        }
    }
}
