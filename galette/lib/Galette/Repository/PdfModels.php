<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Repository;

use Laminas\Db\ResultSet\ResultSet;
use Throwable;
use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Galette\Entity\PdfModel;

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

            $models = [];
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
     * @param bool $check_first Check first if it seems initialized
     */
    public function installInit(bool $check_first = true): bool
    {
        try {
            $ent = $this->entity;
            //first of all, let's check if data seem to have already
            //been initialized
            if ($check_first === true) {
                $select = $this->zdb->select(PdfModel::TABLE);
                $select->columns(
                    [
                        'counter' => new Expression('COUNT(' . $ent::PK . ')')
                    ]
                );

                $results = $this->zdb->execute($select);
                $result = $results->current();
                $count = $result->counter;
                if ($count < count($this->defaults)) {
                    return $this->checkUpdate();
                }
            }

            $this->zdb->beginTransaction();

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

            $this->zdb->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->zdb->inTransaction()) {
                $this->zdb->rollback();
            }
            throw $e;
        }
    }

    /**
     * Checks for missing texts in the database
     */
    protected function checkUpdate(): bool
    {
        try {
            $ent = $this->entity;
            $select = $this->zdb->select($ent::TABLE);
            $list = $this->zdb->execute($select);
            $list->buffer();

            $missing = [];
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
                $this->zdb->beginTransaction();
                $this->insert($ent::TABLE, $missing);

                $this->zdb->handleSequence(
                    $ent::TABLE,
                    $ent::PK,
                    count($this->defaults)
                );

                Analog::log(
                    'Missing texts were successfully stored into database.',
                    Analog::INFO
                );
                $this->zdb->commit();
                return true;
            }
        } catch (Throwable $e) {
            if ($this->zdb->inTransaction()) {
                $this->zdb->rollback();
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
     */
    private function insert(string $table, array $values): void
    {
        $insert = $this->zdb->insert($table);
        $insert->values(
            [
                'model_id'      => ':model_id',
                'model_name'    => ':model_name',
                'model_title'   => ':model_title',
                'model_type'    => ':model_type',
                'model_header'  => ':model_header',
                'model_footer'  => ':model_footer',
                'model_body'    => ':model_body',
                'model_styles'  => ':model_styles',
                'model_parent'  => ':model_parent'
            ]
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
            //@phpstan-ignore variable.undefined
            $this->defaults = $pdfmodels_fields;
        }
        return parent::loadDefaults();
    }
}
