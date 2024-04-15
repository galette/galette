<?php

namespace Galette\Entity\Base;

use ArrayObject;
use Galette\Core\Db;
use Throwable;
use Analog\Analog;
use Galette\Features\I18n;

class EntityFromDb
{
    use I18n;

    protected $zdb;

    protected $tableName;
    protected $tableFields;
    protected $tableFieldsReversed;
    protected $options;
    protected $values = [];
    protected $oldValues = [];
    private $entity = self::class;
    private $i18nProperties = [];


    protected $TABLE, $PK;
    /**
     * Main constructor
     *
     * @param int|ArrayObject<string, int|string>|null $args Arguments
     */
    public function __construct($zdb, $tableDescription, $options, int|ArrayObject $args = null)
    {
        $this->entity = basename(str_replace('\\', '/', get_class($this)));

        $this->zdb = $zdb;
        $this->options = $options;

        $this->TABLE = $tableDescription['table'];
        $this->PK = $tableDescription['id'];

        $this->tableFields = $tableDescription;
        unset($this->tableFields['table']);

        //Rendre le code plus simple par la suite
        $this->tableFieldsReversed = [];
        foreach ($this->tableFields as $k => $v) {
            $this->tableFieldsReversed[$v] = $k;
        }

        foreach ($this->tableFields as $f => $v) {
            $this->$f = null;
        }

        //I18n
        self::getOption('i18n', $this->i18nProperties);


        if (is_int($args)) {
            $this->load($args);
        } elseif ($args instanceof ArrayObject) {
            $this->loadFromRs($args);
        }

        $this->oldValues = [];
    }

    /**
     * Load an entity from identifier
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

            if ($res)
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
        foreach ($rs as $f => $v) {
            if ($v === "NULL") {
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
        foreach ($this->tableFields as $prop => $tableCol) {
            if (isset($this->$prop)) {
                $data[$tableCol] = $this->$prop !== null ? strip_tags($this->$prop) : null;
            }
        }

        try {
            if (isset($this->id) && $this->id > 0) {

                foreach ($this->i18nProperties as $prop) {
                    if ($this->oldValues[$prop] !== null) {
                        $this->deleteTranslation($this->oldValues[$prop]);
                        $this->addTranslation($this->values[$prop]);
                    }
                }

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

                foreach ($this->i18nProperties as $prop) {
                    $this->addTranslation($this->values[$prop]);
                }
            }
            return true;
        } catch (Throwable $e) {
            $id = $this->id ? $this->id : 'new';
            Analog::log(
                "Error when storing {$this->entity} (#$id) Message:\n" . $e->getMessage() .
                "\n" . print_r($data, true),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Remove current entity
     *
     * @return boolean
     */
    public function remove(): bool
    {
        try {
            $delete = $this->zdb->delete($this->TABLE);
            $delete->where([$this->PK => $this->id]);
            $this->zdb->execute($delete);

            //I18n 
            foreach ($this->i18nProperties as $prop) {
                $this->deleteTranslation($this->values[$prop]);
            }

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
    public function __get(string $name): mixed
    {
        return $this->getValue($name, true);
    }

    /**
     * getValue
     *
     * @param string $name Property name
     * @param string $translated translate returned string
     * 
     * @return mixed
     */    public function getValue(string $name, bool $translated): mixed
    {
        //$name = 'tshort';
        $value = null;
        $found = false;
        if (array_key_exists($name, $this->values)) {
            $value = $this->values[$name];
            $found = true;
        } else {
            //from other property
            $k = "$name:from";
            if (self::getOption($k, $f)) {
                $value = $this->{$f};
                $found = true;
            }
        }

        if ($found) //value can be null
        {
            //override default 
            $k = "$name:override";
            if (array_key_exists($k, $this->options)) {
                $fct = $this->options[$k];

                $value = $fct($value);
            }

            //validate this value
            $k = "$name:validate";
            if (array_key_exists($k, $this->options)) {
                $fct = $this->options[$k];

                if (!$fct($value))
                    throw new \Exception($name . ' ' . _T('invalid value !'));
            }

            if ($translated && in_array($name, $this->i18nProperties)) {
                $value = Translate::getFromLang($value);
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
            if ($this->getOption("$name:warningnoempty", $option) && $option === true && $value !== null && strlen(trim($value)) == 0) {
                Analog::log(
                    "$name cannot be empty",
                    Analog::WARNING
                );
                //                throw new \Exception($name . ' '. _T('cannot be empty'));
            }
            if (array_key_exists($name, $this->values))
                $this->oldValues[$name] = $this->values[$name];
            $this->values[$name] = $value;
        } else {
            Analog::log(
                "Unable to set property {$this->entity}->{$name}",
                Analog::WARNING
            );
        }
    }

    private function getOption(string $name, mixed &$option): bool
    {
        if (!array_key_exists($name, $this->options))
            return false;
        $option = $this->options[$name];
        return true;
    }
}
