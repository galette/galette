<?php

namespace Galette\Entity;

use Galette\Core\Db;
use ArrayObject;

class LegalStatus extends EntityFromDb
{
    //use EntityTrait;

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
}
