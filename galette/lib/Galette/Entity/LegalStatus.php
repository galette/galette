<?php

namespace Galette\Entity;

use Galette\Core\Db;
use ArrayObject;
use Galette\Entity\Base\EntityFromDb;

class LegalStatus extends EntityFromDb
{
    public const TABLE = 'legalstatus';
    public const PK = 'id_legalstatus';

    public const INDIVIDUAL = 1; //physical member 

    public function __construct(Db $zdb, ArrayObject|int $args = null)
    {
        parent::__construct(
            $zdb,
            [
            'table' => self::TABLE,
            'id' => self::PK,
            'short' => 'short_label',
            'long' => 'long_label',
        ],
            [
            'toString' => 'long'
        ],
            $args
        );
    }

    /**
     * Remove current legal status
     *
     * @param Db $zdb Database instance
     *
     * @return boolean
     */
    public function remove(): bool
    {
        $id = (int)$this->id;
        if ($id === self::INDIVIDUAL) {
            throw new \RuntimeException(_T("You cannot delete this item!"));
        }
        return parent::remove();
    }
}
