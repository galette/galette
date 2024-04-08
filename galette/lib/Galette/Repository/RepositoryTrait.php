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

trait RepositoryTrait
{
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
                "Cannot list {$this->entity} | " . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    public function countAll()
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
        $defaults = $this->loadDefaults();
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
     * @param string              $table  Table name
     * @param array<array> $values Values to insert
     *
     * @return void
     */
    private function multipleInsert(string $table, array $values): void
    {
        foreach ($values as $row)
        {
            $insert = $this->zdb->insert($table);
            $insert->values($row);
            $this->zdb->execute($insert);
        }
    }
}
