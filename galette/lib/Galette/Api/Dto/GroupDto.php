<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

namespace Galette\Api\Dto;

use Galette\Entity\Group;

/**
 * Data Transfer Object for group API responses
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class GroupDto
{
    /**
     * Constructor
     */
    public function __construct(
        public readonly ?int $id,
        public readonly ?string $name,
        public readonly ?int $parent_id,
        public readonly int $member_count,
    ) {
    }

    /**
     * Create a GroupDto from a Group entity
     */
    public static function fromGroup(Group $group): self
    {
        $parent = $group->getParentGroup();
        return new self(
            id: $group->getId(),
            name: $group->getName(),
            parent_id: $parent !== null ? $parent->getId() : null,
            member_count: $group->getMemberCount(),
        );
    }

    /**
     * Serialize to array for JSON output
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'parent_id'    => $this->parent_id,
            'member_count' => $this->member_count,
        ];
    }
}
