<?php
/**
 * Copyright © 2003-2025 The Galette Team
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

namespace Galette\Updates;

use Analog\Analog;
use Galette\DynamicFields\DynamicField;
use Galette\Entity\ContributionsTypes;
use Galette\Updater\AbstractUpdater;
use GalettePaypal\Paypal;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Metadata\Source\Factory;

/**
 * Galette 1.2.0 upgrade script
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class UpgradeTo120 extends AbstractUpdater
{
    protected ?string $db_version = '1.20';
    protected array $reworked_fkeys = [];

    /**
     * Main constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setSqlScripts($this->db_version);
    }

    /**
     * Pre stuff, if any.
     * Will be executed first.
     *
     * @return boolean
     */
    protected function preUpdate(): bool
    {
        if ($this->zdb->isPostgres()) {
            return true;
        }
        $tables = $this->zdb->getTables();
        $metadata = Factory::createSourceFromAdapter($this->zdb->db);
        $fkeys_tables = [
            PREFIX_DB . 'groups',
            PREFIX_DB . 'fields_categories'
        ];
        foreach ($tables as $table) {
            foreach ($metadata->getConstraints($table) as $constraint) {
                if (
                    $constraint->isForeignKey()
                    && in_array($constraint->getReferencedTableName(), $fkeys_tables, true)
                ) {
                    $this->reworked_fkeys[] = $constraint;
                }
            }
        }

        return true;
    }

    /**
     * Update instructions
     *
     * @return boolean
     */
    protected function update(): bool
    {
        foreach ($this->reworked_fkeys as $reworked_fkey) {
            $this->zdb->db->query(
                sprintf(
                    'ALTER TABLE %s DROP FOREIGN KEY %s;',
                    $reworked_fkey->getTableName(),
                    $reworked_fkey->getName()
                ),
                Adapter::QUERY_MODE_EXECUTE
            );
        }
        return true;
    }

    /**
     * Post stuff, if any.
     * Will be executed at the end.
     *
     * @return boolean
     */
    protected function postUpdate(): bool
    {
        foreach ($this->reworked_fkeys as $reworked_fkey) {
            $this->zdb->db->query(
                sprintf(
                    'ALTER TABLE %s ADD FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE %s ON UPDATE %s;',
                    $reworked_fkey->getTableName(),
                    $reworked_fkey->getColumns()[0],
                    PREFIX_DB . 'groups',
                    \Galette\Entity\Group::PK,
                    $reworked_fkey->getDeleteRule(),
                    $reworked_fkey->getUpdateRule()
                ),
                Adapter::QUERY_MODE_EXECUTE
            );
        }
        return true;
    }
}
