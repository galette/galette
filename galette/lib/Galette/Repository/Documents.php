<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Repository;

use Galette\Core\Db;
use Galette\Core\Authentication;
use Galette\Core\Login;
use Galette\Entity\Document;
use Galette\Entity\FieldsConfig;
use Galette\Filters\DocumentsList;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Safe\DateTime;
use Throwable;
use Analog\Analog;

/**
 * Documents class for galette
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @author Guillaume AGNIERAY <dev@agnieray.net>
 */
class Documents
{
    public const string TABLE = Document::TABLE;
    public const string PK = Document::PK;

    public const string STATUS = 'status';
    public const string RULES = 'rules';
    public const string ADHESION = 'adhesion';
    public const string MINUTES = 'minutes';
    public const string VOTES = 'votes';

    private int $count = 0;
    private bool $public_list = false;

    /**
     * Default constructor
     *
     * @param Db             $zdb     Database
     * @param Login          $login   Login
     * @param ?DocumentsList $filters Filtering
     */
    public function __construct(
        private readonly Db $zdb,
        private readonly Login $login,
        private readonly ?DocumentsList $filters = new DocumentsList()
    ) {
    }

    /**
     * Get system documents types
     *
     * @param bool $translated Return translated types (default) or not
     *
     * @return array<string,string>
     */
    public function getSystemTypes(bool $translated = true): array
    {
        if ($translated) {
            $systypes = [
                self::STATUS => _T('Association status'),
                self::RULES => _T('Rules of procedure'),
                self::ADHESION => _T('Adhesion form'),
                self::MINUTES => _T('Meeting minutes'),
                self::VOTES => _T('Votes results')
            ];
        } else {
            $systypes = [
                self::STATUS => 'Association status',
                self::RULES => 'Rules of procedure',
                self::ADHESION => 'Adhesion form',
                self::MINUTES => 'Meeting minutes',
                self::VOTES => 'Votes results'
            ];
        }
        return $systypes;
    }

    /**
     * Get all known types
     *
     * @return array<string,string>
     *
     * @throws Throwable
     */
    public function getTypes(): array
    {
        $types = $this->getSystemTypes();

        $select = $this->zdb->select(self::TABLE);
        $select->quantifier('DISTINCT');
        $select->where->notIn('type', array_keys($this->getSystemTypes(false)));
        $results = $this->zdb->execute($select);

        foreach ($results as $r) {
            $types[$r->type] = $r->type;
        }

        return $types;
    }

    /**
     * Get documents list
     *
     * @param string|null $type     Type to retrieve
     * @param bool        $filtered Get filtered list
     *
     * @return array<int,Document>
     *
     * @throws Throwable
     */
    public function getList(?string $type = null, ?bool $filtered = true): array
    {
        try {
            $select = $this->buildSelect($type);

            if ($filtered) {
                $this->filters->setLimits($select);
            }

            $documents = [];
            $results = $this->zdb->execute($select);
            $access_level = $this->login->getAccessLevel();

            foreach ($results as $r) {
                // skip entries according to access control
                if (
                    $r->visible == FieldsConfig::NOBODY
                    && (($this->public_list === false && !$this->login->isAdmin()) || $this->public_list === true)
                    || ($r->visible == FieldsConfig::ADMIN
                        && $access_level < Authentication::ACCESS_ADMIN)
                    || ($r->visible == FieldsConfig::STAFF
                        && $access_level < Authentication::ACCESS_STAFF)
                    || ($r->visible == FieldsConfig::MANAGER
                        && $access_level < Authentication::ACCESS_MANAGER)
                    || (($r->visible == FieldsConfig::USER_READ || $r->visible == FieldsConfig::USER_WRITE)
                        && $access_level < Authentication::ACCESS_USER)
                ) {
                    continue;
                }

                $documents[$r->{self::PK}] = new Document($this->zdb, $r);
            }
            return $documents;
        } catch (Throwable $e) {
            Analog::log(
                "An error occurred loading documents. Message:\n"
                . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Get list by type
     *
     * @return array<string, array<int, Document>>
     *
     * @throws Throwable
     */
    public function getTypedList(): array
    {
        $this->public_list = true;
        $list = $this->getList(null, false);
        $sys_types = $this->getSystemTypes(false);

        $typed_list = array_fill_keys($sys_types, []);
        foreach ($list as $document) {
            $typed_list[$document->getType()][] = $document;
        }

        //cleanup: some system types may have no entries
        foreach ($sys_types as $type) {
            if (count($typed_list[$type]) == 0) {
                unset($typed_list[$type]);
            }
        }

        return $typed_list;
    }

    /**
     * Builds the SELECT statement
     *
     * @param string|null $type Type to retrieve
     *
     * @return Select SELECT statement
     */
    private function buildSelect(?string $type = null): Select
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->columns(['*']);

            if ($type !== null) {
                $select->where(['type' => $type]);
            }
            $this->buildWhereClause($select);
            $select->order(self::buildOrderClause());

            $this->proceedCount($select);

            return $select;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot build SELECT clause for documents | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Count documents from the query
     *
     * @param Select $select Original select
     */
    private function proceedCount(Select $select): void
    {
        try {
            $countSelect = clone $select;
            $countSelect->reset($countSelect::COLUMNS);
            $countSelect->reset($countSelect::ORDER);
            $countSelect->columns(
                [
                    self::PK => new Expression('COUNT(' . self::PK . ')')
                ]
            );

            $results = $this->zdb->execute($countSelect);
            $result = $results->current();

            $k = self::PK;
            $this->count = (int)$result->$k;
            $this->filters->setCounter($this->count);
        } catch (Throwable $e) {
            Analog::log(
                'Cannot count documents | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Builds the order clause
     *
     * @return array<string> SQL ORDER clauses
     */
    private function buildOrderClause(): array
    {
        $order = [];

        switch ($this->filters->orderby) {
            case DocumentsList::ORDERBY_ID:
                $order[] = Document::PK . ' ' . $this->filters->getDirection();
                break;
            case DocumentsList::ORDERBY_NAME:
                $order[] = 'filename ' . $this->filters->getDirection();
                break;
            case DocumentsList::ORDERBY_TYPE:
                $order[] = 'type ' . $this->filters->getDirection();
                break;
            case DocumentsList::ORDERBY_DATE:
                $order[] = 'creation_date ' . $this->filters->getDirection();
                break;
            default:
                $order[] = $this->filters->orderby . ' ' . $this->filters->getDirection();
                break;
        }

        return $order;
    }

    /**
     * Builds where clause, for filtering on simple list mode
     *
     * @param Select $select Original select
     */
    private function buildWhereClause(Select $select): void
    {
        try {
            if ($this->filters->start_date_filter !== null) {
                $d = new DateTime($this->filters->raw_start_date_filter);
                $d->setTime(0, 0, 0);
                $select->where->greaterThanOrEqualTo(
                    'creation_date',
                    $d->format('Y-m-d H:i:s')
                );
            }

            if ($this->filters->end_date_filter !== null) {
                $d = new DateTime($this->filters->raw_end_date_filter);
                $d->setTime(23, 59, 59);
                $select->where->lessThanOrEqualTo(
                    'creation_date',
                    $d->format('Y-m-d H:i:s')
                );
            }

            if ($this->filters->filename_filter !== null) {
                $select->where->like(
                    'filename',
                    '%' . $this->filters->filename_filter . '%'
                );
            }

            if ($this->filters->type_filter !== null && $this->filters->type_filter != '0') {
                $select->where->equalTo(
                    'type',
                    $this->filters->type_filter
                );
            }

            if ($this->filters->visibility_filter !== null && $this->filters->visibility_filter != '0') {
                $select->where->equalTo(
                    'visible',
                    $this->filters->visibility_filter
                );
            }
        } catch (Throwable $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Get count for current query
     */
    public function getCount(): int
    {
        return $this->count;
    }
}
