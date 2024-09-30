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

namespace Galette\Repository;

use Laminas\Db\ResultSet\ResultSet;
use Throwable;
use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Galette\Entity\PdfModel;
use Galette\Entity\PdfMain;
use Galette\Entity\PdfInvoice;
use Galette\Entity\PdfReceipt;

/**
 * PDF models
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PdfModels extends Repository
{
    /**
     * Get models list
     *
     * @return array<int, PdfModel>|ResultSet
     */
    public function getList(): array|ResultSet
    {
        try {
            $select = $this->zdb->select(PdfModel::TABLE, 'a');
            $select->order(PdfModel::PK);

            $models = array();
            $results = $this->zdb->execute($select);
            foreach ($results as $row) {
                $class = PdfModel::getTypeClass((int)$row->model_type);
                $models[] = new $class($this->zdb, $this->preferences, $row);
            }
            return $models;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot list pdf models | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Add default models in database
     *
     * @param boolean $check_first Check first if it seems initialized
     *
     * @return boolean
     */
    public function installInit(bool $check_first = true): bool
    {
        try {
            $ent = $this->entity;
            //first of all, let's check if data seem to have already
            //been initialized
            $proceed = false;
            if ($check_first === true) {
                $select = $this->zdb->select(PdfModel::TABLE);
                $select->columns(
                    array(
                        'counter' => new Expression('COUNT(' . $ent::PK . ')')
                    )
                );

                $results = $this->zdb->execute($select);
                $result = $results->current();
                $count = $result->counter;
                if ($count == 0) {
                    //if we got no values in texts table, let's proceed
                    $proceed = true;
                } else {
                    if ($count < count($this->defaults)) {
                        return $this->checkUpdate();
                    }
                    return false;
                }
            } else {
                $proceed = true;
            }

            if ($proceed === true) {
                $this->zdb->connection->beginTransaction();

                //first, we drop all values
                $update = $this->zdb->update($ent::TABLE);
                $update->set(['model_parent' => null]);
                $this->zdb->execute($update);

                $delete = $this->zdb->delete($ent::TABLE);
                $this->zdb->execute($delete);

                $this->zdb->handleSequence(
                    $ent::TABLE,
                    $ent::PK,
                    count($this->defaults)
                );

                $this->insert($ent::TABLE, $this->defaults);

                $this->zdb->connection->commit();
                return true;
            }
        } catch (Throwable $e) {
            if ($this->zdb->connection->inTransaction()) {
                $this->zdb->connection->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Checks for missing texts in the database
     *
     * @return boolean
     */
    protected function checkUpdate(): bool
    {
        try {
            $ent = $this->entity;
            $select = $this->zdb->select($ent::TABLE);
            $list = $this->zdb->execute($select);
            $list->buffer();

            $missing = array();
            foreach ($this->defaults as $key => $default) {
                $exists = false;
                foreach ($list as $model) {
                    if ($model->model_id == $default['model_id']) {
                        $exists = true;
                        break;
                    }
                }

                if ($exists === false) {
                    //model does not exist in database, insert it.
                    $missing[$key] = $default;
                }
            }

            if (count($missing) > 0) {
                $this->zdb->connection->beginTransaction();
                $this->insert($ent::TABLE, $missing);
                Analog::log(
                    'Missing texts were successfully stored into database.',
                    Analog::INFO
                );
                $this->zdb->connection->commit();
                return true;
            }
        } catch (Throwable $e) {
            if ($this->zdb->connection->inTransaction()) {
                $this->zdb->connection->rollBack();
            }
            throw $e;
        }
        return false;
    }

    /**
     * Insert values in database
     *
     * @param string              $table  Table name
     * @param array<string,mixed> $values Values to insert
     *
     * @return void
     */
    private function insert(string $table, array $values): void
    {
        $insert = $this->zdb->insert($table);
        $insert->values(
            array(
                'model_id'      => ':model_id',
                'model_name'    => ':model_name',
                'model_title'   => ':model_title',
                'model_type'    => ':model_type',
                'model_header'  => ':model_header',
                'model_footer'  => ':model_footer',
                'model_body'    => ':model_body',
                'model_styles'  => ':model_styles',
                'model_parent'  => ':model_parent'
            )
        );
        $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

        foreach ($values as $value) {
            $stmt->execute($value);
        }
    }

    /**
     * Load and get default PDF models
     *
     * @return array<string,mixed>
     */
    protected function loadDefaults(): array
    {
        if (!count($this->defaults)) {
            include GALETTE_ROOT . 'includes/fields_defs/pdfmodels_fields.php';
            //@phpstan-ignore-next-line
            $this->defaults = $pdfmodels_fields;
        }
        return parent::loadDefaults();
    }
}
