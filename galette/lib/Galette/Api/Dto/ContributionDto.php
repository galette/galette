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

use Galette\Entity\Contribution;

/**
 * Data Transfer Object for contribution API responses
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ContributionDto
{
    /**
     * Constructor
     */
    public function __construct(
        public readonly ?int $id,
        public readonly ?int $id_adh,
        public readonly mixed $id_type,
        public readonly ?float $amount,
        public readonly ?string $date,
        public readonly ?string $begin_date,
        public readonly ?string $end_date,
        public readonly mixed $payment,
        public readonly ?string $info,
    ) {
    }

    /**
     * Create a ContributionDto from a Contribution entity
     */
    public static function fromContribution(Contribution $contribution): self
    {
        return new self(
            id: $contribution->id,
            id_adh: $contribution->member,
            id_type: $contribution->type,
            amount: $contribution->amount,
            date: $contribution->date,
            begin_date: $contribution->begin_date,
            end_date: $contribution->end_date,
            payment: $contribution->payment_type,
            info: $contribution->info,
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
            'id'         => $this->id,
            'id_adh'     => $this->id_adh,
            'id_type'    => $this->id_type,
            'amount'     => $this->amount,
            'date'       => $this->date,
            'begin_date' => $this->begin_date,
            'end_date'   => $this->end_date,
            'payment'    => $this->payment,
            'info'       => $this->info,
        ];
    }
}
