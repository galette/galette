<?php

namespace Galette\Entity;

use ArrayObject;
use Galette\Core\Db;
use Throwable;
use Analog\Analog;

class EntityFromDb
{
    protected $zdb;

    protected $tableName;
    protected $tableFields;
    protected $tableFieldsReversed;
    protected $options;
    protected $values = [];
    private $entity = self::class;

    protected $TABLE, $PK;
    /**
     * Main constructor
     *
     * @param int|ArrayObject<string, int|string>|null $args Arguments
     */
    public function __construct($zdb, $tableDescription, $options, int|ArrayObject $args = null)
    {
        $this->zdb = $zdb;
        $this->options = $options;

        $this->TABLE= $tableDescription['table'];
        $this->PK = $tableDescription['id'];

        $this->tableFields = $tableDescription;
        unset($this->tableFields['table']);

        //Rendre le code plus simple par la suite
        $this->tableFieldsReversed=[];
        foreach($this->tableFields as $k=>$v)
        {
            $this->tableFieldsReversed[$v] = $k;
        }

        if (is_int($args)) {
            $this->load($args);
        } elseif ($args instanceof ArrayObject) {
            $this->loadFromRs($args);
        }
    }

    /**
     * Load a title from its identifier
     *
     * @param int $id Identifier
     *
     * @return void
     */
    public function load(int $id): void
    {
        try {
            $select = $this->zdb->select($this->TABLE);
            $select->limit(1)->where([$this->PK => $id]);

            $results = $this->zdb->execute($select);
            $res = $results->current();

            $this->loadFromRs($res);
         } catch (Throwable $e) {
            Analog::log(
                "Error when loading {$this->entity} (#$id) Message:\n" .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Load title from a db ResultSet
     *
     * @param ArrayObject<string, int|string> $rs ResultSet
     *
     * @return void
     */
    private function loadFromRs(ArrayObject $rs): void
    {
        foreach($rs as $f => $v) {
            if($v === "NULL") {
                $v = null;
            }
            $prop = $this->tableFieldsReversed[$f];
            $this->$prop = $v;
        }
    }

    /**
     * Simple text representation
     *
     * @return string
     */
    public function __toString(): string
    {
        if (array_key_exists('toString', $this->options)) {
            return $this->{$this->options['toString']};
        }
        return "options[toString] not defined";
    }

    /**
     * Store title in database
     *
     * @param Db $zdb Database instance
     *
     * @return boolean
     */
    public function store(): bool
    {
        $data = [];
        foreach($this->tableFields as $prop => $tableCol) {
            if (isset($this->$prop)) {
                $data[$tableCol] = strip_tags($this->$prop);
            }
        }

        try {
            if (isset($this->id) && $this->id > 0) {
                $update = $this->zdb->update($this->TABLE);
                $update->set($data)->where([$this->PK => $this->id]);
                $this->zdb->execute($update);
            } else {
                $insert = $this->zdb->insert($this->TABLE);
                $insert->values($data);
                $add = $this->zdb->execute($insert);
                if (!$add->count() > 0) {
                    Analog::log('Not stored!', Analog::ERROR);
                    return false;
                }

                $this->id = $this->zdb->getLastGeneratedValue($this);
            }
            return true;
        } catch (Throwable $e) {
            Analog::log(
                "Error when storing {$this->entity} (#$id) Message:\n" . $e->getMessage() .
                "\n" . print_r($data, true),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Remove current title
     *
     * @param Db $zdb Database instance
     *
     * @return boolean
     */
    public function remove(): bool
    {
        try {
            $delete = $this->zdb->delete($this->TABLE);
            $delete->where([$this->PK => $this->id]);
            $this->zdb->execute($delete);
            Analog::log(
                "{$this->entity} #{$this->id} deleted successfully.",
                Analog::INFO
            );
            return true;
        } catch (\RuntimeException $re) {
            throw $re;
        } catch (Throwable $e) {
            Analog::log(
                "Unable to delete {$this->entity} #{$this->id} | " . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Getter
     *
     * @param string $name Property name
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        global $lang;
        if (array_key_exists($name, $this->values)) {
            $value =  $this->values[$name];
            if (array_key_exists($name, $this->options)) {
                $ex = explode(':', $name);
                switch($ex[1]) {
                    case 'translate':
                        if (isset($lang) && isset($lang[$value])) {
                            $value = _T($value);
                        }
                }
            }

            //validate this value
            $k = "$name:validate";
            if (array_key_exists($k, $this->options)) {
                $fct = $this->options[$k];

                $value = $fct($value);
            }
            return $value;
        }
        Analog::log(
            "Unable to get property {$this->entity}->{$name}",
            Analog::WARNING
        );
        return null;
    }

    /**
     * Isset
     * Required for twig to access properties via __get
     *
     * @param string $name Property name
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->values);
    }

    /**
     * Setter
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     *
     * @return void
     */
    public function __set(string $name, $value): void
    {
        if (in_array($name, $this->tableFieldsReversed)) {
            $this->values[$name] = $value;
        } else {
            Analog::log(
                "Unable to set property {$this->entity}->{$name}",
                Analog::WARNING
            );
        }
    }
}
