<?php

namespace Galette\Repository;

use Laminas\Db\ResultSet\ResultSet;
use Throwable;
use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Galette\Entity\LegalStatus;

/**
 * LegalStatus repository
 *
 * @author Manuel <manuelh78dev@ik.me>
 */
class LegalStatuss extends Repository
{
    use RepositoryTrait;

    public const TABLE = LegalStatus::TABLE;
    public const PK = LegalStatus::PK;


    /**
     * Get defaults values
     *
     * @return array<array>
     */
    protected function getInstallDefaultValues(): array
    {
        return [
        array(
            self::PK      => LegalStatus::INDIVIDUAL,
            'short_label'   => '',
            'long_label'    => 'Particulier'
        ),
        array(
            self::PK      => 2,
            'short_label'   => 'Asso.',
            'long_label'    => 'Association'
        ),
        array(
            self::PK      => 3,
            'short_label'   => 'Ent.',
            'long_label'    => 'Entreprise'
        )];
    }
}
