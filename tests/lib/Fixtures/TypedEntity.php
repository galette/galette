<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Fixtures;

use Galette\Entity\AbstractEntity;
use Galette\Entity\Attributes\Column;

/**
 * Entity with typed properties, not bound to any real table
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TypedEntity extends AbstractEntity
{
    public const string TABLE = 'typed';
    public const string PK = 'id_typed';

    #[Column(self::PK)]
    protected int $id;
    #[Column('flag')]
    public bool $flag = false;
    #[Column('number')]
    public int $number = 0;
    #[Column('optional')]
    public ?int $optional = null;
    #[Column('label')]
    public string $label = '';
}
