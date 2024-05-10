<?php

namespace Galette\Entity\Base;

use ArrayObject;
use Galette\Core\Db;
use Throwable;
use Analog\Analog;
use Galette\Features\I18n;

/**
 * base class EntityFromDb
 *
 * @author Manuel <manuelh78dev@ik.me>
 */
class EntityFromDb
{
    use I18n;

    protected DB $zdb;

    protected string $tableName;
    protected array $tableFields;
    protected array $tableFieldsReversed;
    protected array $options;
    protected array $values = [];
    protected array $oldValues = [];
    private string $entity = self::class;
    private array $i18nProperties = [];


    protected string $TABLE;
    protected string $PK;

    /**
     * Main constructor
     *
     * @param DB                                       $zdb              Database
     * @param array<string,string>                     $tableDescription propertyname => db column name
     * @param array                                    $options          add virtual properties, override or valid a value...
     * @param int|ArrayObject<string, int|string>|null $args             Arguments
     */
    public function __construct(DB $zdb, array $tableDescription, array $options, int|ArrayObject $args = null)
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
     * @return bool true if loaded
     */
    public function load(int $id): bool
    {
        try {
            $select = $this->zdb->select($this->TABLE);
            $select->limit(1)->where([$this->PK => $id]);

            $results = $this->zdb->execute($select);
            $res = $results->current();

            if ($res) {
                $this->loadFromRs($res);
                return true;
            }
        } catch (Throwable $e) {
            Analog::log(
                "Error when loading {$this->entity} (#$id) Message:\n" .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
        return false;
    }

    /**
     * Load entity from a DB ResultSet
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
            return $this->getValue($this->options['toString'], true);
        }
        return "options[toString] not defined";
    }

    /**
     * Store this entity in database
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
     * @param string $name       Property name
     * @param string $translated translate returned string
     *
     * @return mixed
     */
    public function getValue(string $name, bool $translated): mixed
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
                if (is_callable($f)) {
                    $value = $f();
                } elseif ($f != '') {
                    $value = $this->getValue($f, $translated); //equivalent $this->{$f};
                }
                $found = true;
            }
        }

        if ($found) { //value can be null
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

                if (!$fct($value)) {
                    throw new \Exception($name . ' ' . _T('invalid value !'));
                }
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
    public function __set(string $name, mixed $value): void
    {
        $this->setValue($name, $value);
    }

    /**
     * setValue
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     *
     * @return void
     */
    public function setValue(string $name, mixed $value): void
    {
        if (in_array($name, $this->tableFieldsReversed)) {
            if ($this->getOption("$name:warningnoempty", $option) && $option === true && $value !== null && strlen(trim($value)) == 0) {
                Analog::log(
                    "$name cannot be empty",
                    Analog::WARNING
                );
                //                throw new \Exception($name . ' '. _T('cannot be empty'));
            }

            //validate this value
            $k = "$name:validate";
            if (array_key_exists($k, $this->options)) {
                $fct = $this->options[$k];

                if (!$fct($value)) {
                    throw new \Exception($name . ' ' . _T('invalid value !'));
                }
            }

            if (array_key_exists($name, $this->values)) {
                $this->oldValues[$name] = $this->values[$name];
            }
            $this->values[$name] = $value;
        } else {
            Analog::log(
                "Unable to set property {$this->entity}->{$name}",
                Analog::WARNING
            );
        }
    }

    /**
     * getOption
     *
     * @param string $name   Option name
     * @param mixed  $option by reference, Property value
     *
     * @return bool true if this option exist
     */
    private function getOption(string $name, mixed &$option): bool
    {
        if (!array_key_exists($name, $this->options)) {
            return false;
        }
        $option = $this->options[$name];
        return true;
    }

   /**
    * __call
    * Implement a getMyProperty() for all columns in database; example : getId(), getName()...
    * @param string $name      Method name getXXXX
    * @param array  $arguments getXXXX([$arguments])
    * @return mixed optional returned value
    */
    public function __call(string $name, array $arguments): mixed
    {
        $arg1 = (count($arguments) >= 1) ? $arguments[0] : false;
        //All getters
        if (str_starts_with($name, 'get')) {
            $prop = lcfirst(substr($name, 3));
            return $this->getValue($prop, $arg1);
        }
        //All setters
        if (str_starts_with($name, 'set')) {
            $prop = lcfirst(substr($name, 3));
            return $this->setValue($prop, $arg1);
        }
        throw new \RuntimeException("Entity::$name property not available.");
    }
}
