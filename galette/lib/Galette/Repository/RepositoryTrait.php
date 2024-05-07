<?php

namespace Galette\Repository;

use Laminas\Db\ResultSet\ResultSet;
use Throwable;
use Laminas\Db\Sql\Expression;
use Galette\Core\DB;
use Galette\Core\Preferences;
use Galette\Core\Login;
use DI\Attribute\Inject;
use Analog\Analog;

/**
 * RepositoryTrait
 *
 * @author Manuel <manuelh78dev@ik.me>
 */
trait RepositoryTrait
{
    private $entityClassName;

    /**
     * Get list
     *
     * @return array<int, stdClass>|ResultSet
     */
    public function getList(): array|ResultSet
    {
        try {
            $select = $this->zdb->select(constant($this->entity . '::TABLE'), 'a');
            $PK = constant($this->entity . '::PK');
            $select->order($PK);

            $ret = array();
            $results = $this->zdb->execute($select);
            foreach ($results as $row) {
                $ret[$row->$PK] = new $this->entity($this->zdb, $row);
            }
            return $ret;
        } catch (Throwable $e) {
            Analog::log(
                "Cannot list {$this->entityShortName}s | " . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Get a list of all entries
     *
     * @return array<int, mixed>
     */
    public static function getAll(): array
    {
        global $zdb;
        $ptypes = new self($zdb);
        return $ptypes->getList();
    }

    /**
     * Loads an entity from its id
     *
     * @param int $id Entry ID
     *
     * @return boolean true if query succeed, false otherwise
     */
    public function load(int $id): null|\ArrayObject
    {
        $select = $this->zdb->select(constant($this->entity . '::TABLE'));
        $select->limit(1)
            ->where(array(constant($this->entity . '::PK') => $id));

        $results = $this->zdb->execute($select);
        return $results->current();
    }

    /**
     * Count all entities
     *
     *
     * @return int a count, 0 isf empty
     */
    public function countAll(): int
    {
        $ent = $this->entity;
        $TABLE = constant($ent . '::TABLE');

        $select = $this->zdb->select($TABLE);
        $select->columns(
            array(
                'counter' => new Expression('COUNT(' . $ent::PK . ')')
            )
        );

        $results = $this->zdb->execute($select);
        $result = $results->current();
        $count = $result->counter;
        return $count;
    }

    /**
     * Add default values in database
     *
     * @param boolean $check_first Check first if it seems initialized, defaults to true
     *
     * @return boolean
     */
    public function installInit(bool $check_first = true): bool
    {
        $defaults = $this->getInstallDefaultValues();
        try {
            $ent = $this->entity;
            $TABLE = constant($ent . '::TABLE');
            //first of all, let's check if data seem to have already
            //been initialized
            $proceed = false;
            if ($check_first === true) {
                $count = $this->countAll();
                if ($count == 0) {
                    //if we got no values in table, let's proceed
                    $proceed = true;
                } else {
                    if ($count < count($defaults)) {
                        return $this->checkUpdate();
                    }
                    return false;
                }
            } else {
                $proceed = true;
            }

            if ($proceed === true) {
                $this->zdb->connection->beginTransaction();

                //first, we drop all values
                $delete = $this->zdb->delete($TABLE);
                $this->zdb->execute($delete);

                $this->zdb->handleSequence(
                    $TABLE,
                    count($defaults)
                );
                $this->multipleInsert($TABLE, $defaults);

                $this->zdb->connection->commit();

                Analog::log(
                    "Default {$this->entityShortName}s were successfully stored into database.",
                    Analog::INFO
                );

                return true;
            }
        } catch (Throwable $e) {
            if ($this->zdb->connection->inTransaction()) {
                $this->zdb->connection->rollBack();
            }
            throw $e;
        }
        return false;
    }

    /**
     * Checks for missing entries in the database
     *
     * @return boolean
     */
    protected function checkUpdate(): bool
    {
        return false;
    }

    /**
     * Insert values in database
     *
     * @param string       $table  Table name
     * @param array<array> $values Values to insert; Format array<[col1=>value, col2=>value2]>
     *
     * @return void
     */
    protected function multipleInsert(string $table, array $values): void
    {
        foreach ($values as $row) {
            $insert = $this->zdb->insert($table);
            $insert->values($row);
            $this->zdb->execute($insert);
        }
    }

    /**
     * Convert a keyvalue pair array <ID, VALUE> to simple array for DB Insert [[ID=>k,$COL=>v]]
     *
     * @param array $array Array to convert
     * @param string $idColumn  ID column name in DB 
     * @param string $valueColumn Value column name in DB 
     *
     * @return array
     */
    static public function convertArrayKeyValueForDBInsert(array $values, $idColumn, $valueColumn): array
    {
        $ret = [];
        foreach ($values as $k => $v) {
            $ret[] = [
                $idColumn => $k,
                $valueColumn => $v
            ];
        }
        return $ret;
    }
}
