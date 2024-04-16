<?php

namespace Galette\Entity;

use Galette\Core\Db;
use ArrayObject;
use Galette\Entity\Base\EntityFromDb;

/**
 * LegalStatus
 *
 * @author Manuel <manuelh78dev@ik.me>
 */
class LegalStatus extends EntityFromDb
{
    public const TABLE = 'legalstatus';
    public const PK = 'id_legalstatus';

    public const INDIVIDUAL = 1; //physical member

    /**
    *  Main constructor
    *
    * @param DB               $zdb  Database
    * @param ?ArrayObject|int $args item data to load
    */
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
            'toString' => 'long',
            //Automatic add and removeTranslation() when store()
            'i18n' => ['short', 'long']
            ],
            $args
        );
    }

    /**
     * Remove current legal status
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
