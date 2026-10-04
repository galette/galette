<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Webhooks\Payload;

use ArrayObject;
use DI\Attribute\Inject;
use Galette\Core\Db;
use Galette\Entity\Adherent;
use Galette\Entity\FieldsConfig;
use Galette\Entity\Status;
use Galette\Entity\Title;
use InvalidArgumentException;
use Throwable;

/**
 * Members normalizer
 *
 * Fields are filtered according to their visibility at the webhook access
 * level, as the members CSV export does for the logged-in user. The password
 * is never sent.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MemberNormalizer implements Normalizer
{
    /** Columns always sent: a receiver has to know which member it is */
    private const array ALWAYS = ['id_adh', 'parent_id'];
    private const array NEVER = ['mdp_adh'];
    /** Columns kept for a member summary, embedded in other objects data */
    private const array SUMMARY = ['id_adh', 'nom_adh', 'prenom_adh', 'societe_adh', 'email_adh'];
    private const array INTEGERS = ['id_adh', 'id_statut', 'titre_adh', 'sexe_adh', 'parent_id'];
    private const array BOOLEANS = ['activite_adh', 'bool_admin_adh', 'bool_exempt_adh', 'bool_display_info'];

    /**
     * Constructor
     *
     * @param Db                                  $zdb            Database instance
     * @param FieldsConfig                        $fields_config  Members fields configuration
     * @param array<string, array<string, mixed>> $members_fields Members fields definitions
     */
    public function __construct(
        private readonly Db $zdb,
        private readonly FieldsConfig $fields_config,
        #[Inject('members_fields')]
        private readonly array $members_fields
    ) {
    }

    /**
     * Can this normalizer handle given object?
     *
     * Removed members are only known from their database row.
     *
     * @param object $subject Object carried by the event
     */
    public function supports(object $subject): bool
    {
        return $subject instanceof Adherent
            || ($subject instanceof ArrayObject && isset($subject[Adherent::PK]));
    }

    /**
     * Normalize member
     *
     * @param object $subject      Adherent, or database row of a removed member
     * @param int    $access_level Webhook access level
     *
     * @return array<string, mixed>
     */
    public function normalize(object $subject, int $access_level): array
    {
        return $this->normalizeRow($this->getRow($subject), $access_level);
    }

    /**
     * Normalize a member summary, to be embedded in other objects data
     *
     * @param int $id           Member identifier
     * @param int $access_level Webhook access level
     *
     * @return ?array<string, mixed> Null if member does not exist anymore
     */
    public function summarize(int $id, int $access_level): ?array
    {
        $row = $this->loadRow($id);
        if ($row === null) {
            return null;
        }
        return $this->normalizeRow(
            array_intersect_key($row, array_flip(self::SUMMARY)),
            $access_level
        );
    }

    /**
     * Get raw database values
     *
     * Values are read again from the database, so that data is the stored one,
     * with raw dates, rather than the object possibly formatted ones.
     *
     * @param object $subject Adherent or database row
     *
     * @return array<string, mixed>
     */
    private function getRow(object $subject): array
    {
        if ($subject instanceof ArrayObject) {
            return $subject->getArrayCopy();
        }
        if (!$subject instanceof Adherent) {
            throw new InvalidArgumentException('Unsupported subject ' . $subject::class);
        }
        return $this->loadRow((int)$subject->id) ?? [Adherent::PK => $subject->id];
    }

    /**
     * Load member row
     *
     * @param int $id Member identifier
     *
     * @return ?array<string, mixed>
     */
    private function loadRow(int $id): ?array
    {
        $select = $this->zdb->select(Adherent::TABLE);
        $select->where([Adherent::PK => $id])->limit(1);
        $row = $this->zdb->execute($select)->current();
        return $row instanceof ArrayObject ? $row->getArrayCopy() : null;
    }

    /**
     * Filter and convert values
     *
     * @param array<string, mixed> $row          Database values
     * @param int                  $access_level Webhook access level
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row, int $access_level): array
    {
        $data = [];
        foreach ($row as $column => $value) {
            if (
                in_array($column, self::NEVER, strict: true)
                || !isset($this->members_fields[$column])
                || (!in_array($column, self::ALWAYS, strict: true)
                    && !$this->fields_config->isVisibleFor($column, $access_level))
            ) {
                continue;
            }

            $key = (string)($this->members_fields[$column]['propname'] ?? $column);
            $data[$key] = match ($column) {
                'id_statut' => $this->getStatus($value),
                'titre_adh' => $this->getTitle($value),
                default => $this->convert($column, $value),
            };
        }
        return $data;
    }

    /**
     * Convert a database value
     *
     * @param string $column Column name
     * @param mixed  $value  Raw value
     */
    private function convert(string $column, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (in_array($column, self::INTEGERS, strict: true)) {
            return (int)$value;
        }
        if (in_array($column, self::BOOLEANS, strict: true)) {
            return in_array($value, [true, 1, '1', 't', 'true'], strict: true);
        }
        return $value;
    }

    /**
     * Get status identifier and label
     *
     * @param mixed $value Status identifier
     *
     * @return ?array{id: int, label: string}
     */
    private function getStatus(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        $label = (new Status($this->zdb))->getLabel((int)$value, translated: false);
        return ['id' => (int)$value, 'label' => (string)$label];
    }

    /**
     * Get title identifier and label
     *
     * @param mixed $value Title identifier
     *
     * @return ?array{id: int, label: string}
     */
    private function getTitle(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            $title = new Title((int)$value);
            return ['id' => (int)$value, 'label' => (string)$title->short];
        } catch (Throwable) {
            return ['id' => (int)$value, 'label' => ''];
        }
    }
}
