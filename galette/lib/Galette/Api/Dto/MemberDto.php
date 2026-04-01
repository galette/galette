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

use Galette\Entity\Adherent;

/**
 * Data Transfer Object for member API responses
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MemberDto
{
    /**
     * Constructor
     */
    public function __construct(
        public readonly ?int $id,
        public readonly ?string $login,
        public readonly ?string $name,
        public readonly ?string $surname,
        public readonly ?string $company,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $gsm,
        public readonly ?string $address,
        public readonly ?string $zipcode,
        public readonly ?string $town,
        public readonly ?string $country,
        public readonly bool $active,
        public readonly bool $admin,
        public readonly bool $staff,
        public readonly bool $due_free,
        public readonly ?string $due_date,
        public readonly ?string $creation_date,
        public readonly ?string $number,
    ) {
    }

    /**
     * Create a MemberDto from an Adherent entity
     */
    public static function fromAdherent(Adherent $member): self
    {
        return new self(
            id: $member->id,
            login: $member->login,
            name: $member->name,
            surname: $member->surname,
            company: $member->company_name,
            email: $member->email,
            phone: $member->phone,
            gsm: $member->gsm,
            address: $member->address,
            zipcode: $member->zipcode,
            town: $member->town,
            country: $member->country,
            active: $member->isActive(),
            admin: $member->isAdmin(),
            staff: $member->isStaff(),
            due_free: $member->isDueFree(),
            due_date: $member->due_date,
            creation_date: $member->creation_date,
            number: $member->number,
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
            'id'            => $this->id,
            'login'         => $this->login,
            'name'          => $this->name,
            'surname'       => $this->surname,
            'company'       => $this->company,
            'email'         => $this->email,
            'phone'         => $this->phone,
            'gsm'           => $this->gsm,
            'address'       => $this->address,
            'zipcode'       => $this->zipcode,
            'town'          => $this->town,
            'country'       => $this->country,
            'active'        => $this->active,
            'admin'         => $this->admin,
            'staff'         => $this->staff,
            'due_free'      => $this->due_free,
            'due_date'      => $this->due_date,
            'creation_date' => $this->creation_date,
            'number'        => $this->number,
        ];
    }
}
