<?php

namespace Galette\Repository;

use Laminas\Db\ResultSet\ResultSet;
use Throwable;
use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Galette\Entity\LegalStatus;

/**
 * Payment types
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class LegalStatuss extends Repository
{
    use RepositoryTrait;

    public const TABLE = 'legalstatus';
    public const PK = 'id_legalstatus';


    /**
     * Get defaults values
     *
     * @return array<string, mixed>
     */
    protected function loadDefaults(): array
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
