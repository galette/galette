<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Repository;

use Analog\Analog;
use ArrayObject;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Core\RowsCache;
use Galette\Core\Plugins\DynamicFormsProviderInterface;
use Galette\Core\Logs;
use Galette\Features\Dynamics;
use ReflectionClass;
use Throwable;
use Galette\DynamicFields\Choice;
use Galette\DynamicFields\DynamicField;
use Galette\DynamicFields\Text;
use Galette\Entity\DynamicFieldsHandle;
use Galette\Entity\FieldsConfig;

use function Safe\class_parents;
use function Safe\class_uses;
use function Safe\preg_match;

/**
 * Dynamic field descriptors set
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class DynamicFieldsSet
{
    /**
     * Valid form names: a plugin form name must fit the `field_form` columns
     */
    public const string FORM_PATTERN = '[a-z][a-z0-9_]{0,49}';

    /**
     * Stands for the declared form names in route patterns; replaced when routes are loaded
     */
    public const string FORMS_PLACEHOLDER = '%dynamic_forms%';

    /**
     * Names the core uses, beyond its forms: files of member fields are named after `member`
     */
    private const array RESERVED_FORMS = ['member'];

    /** @var array<string, array{class: class-string, plugin: string, provider: DynamicFormsProviderInterface}> */
    private static array $plugin_forms = [];

    /**
     * Main constructor
     *
     * @param Db    $zdb   Database instance
     * @param Login $login Login instance
     */
    public function __construct(private readonly Db $zdb, private readonly Login $login)
    {
    }

    /**
     * Get form names and associated classes
     *
     * @return array<string, string>
     */
    public static function getClasses(): array
    {
        return self::getCoreClasses() + array_map(
            static fn(array $form): string => $form['class'],
            self::$plugin_forms
        );
    }

    /**
     * Get core form names and associated classes
     *
     * @return array<string, class-string>
     */
    private static function getCoreClasses(): array
    {
        return [
            'adh' => \Galette\Entity\Adherent::class,
            'contrib' => \Galette\Entity\Contribution::class,
            'trans' => \Galette\Entity\Transaction::class,
            'prefs' => \Galette\Core\Preferences::class
        ];
    }

    /**
     * Get the route pattern matching declared forms, those of the core and of active plugins
     */
    public static function getFormsRoutePattern(): string
    {
        return implode('|', array_keys(self::getClasses()));
    }

    /**
     * Register the forms a plugin declares
     *
     * A registration replaces the previous one of the same plugin.
     * Forms whose name or class is not valid are dropped and reported.
     *
     * @param string                        $plugin   Plugin route name
     * @param DynamicFormsProviderInterface $provider Plugin declaring the forms
     */
    public static function registerForms(string $plugin, DynamicFormsProviderInterface $provider): void
    {
        self::unregisterForms($plugin);
        if (!preg_match('/^[a-z][a-z0-9]*\z/', $plugin)) {
            //form names start with the route: "auto" and "auto_vehicle" would both own
            //"auto_vehicle_record", as "auto" and "Auto" would both own "auto_record"
            Analog::log(
                sprintf(
                    'Plugin "%s" dynamic forms are ignored: its route name must hold lowercase letters and digits only',
                    $plugin
                ),
                Analog::ERROR
            );
            return;
        }

        try {
            $forms = $provider->getDynamicForms();
        } catch (Throwable $e) {
            //one plugin must not prevent Galette from starting
            Logs::exception($e, sprintf('Plugin "%s" dynamic forms are ignored', $plugin));
            return;
        }

        foreach ($forms as $form => $class) {
            $error = self::rejectForm($plugin, (string)$form, $class);
            if ($error !== null) {
                Analog::log(
                    sprintf('Plugin "%s" dynamic form "%s" is ignored: %s', $plugin, $form, $error),
                    Analog::ERROR
                );
                continue;
            }
            self::$plugin_forms[$form] = [
                //as PHP spells it, getFormName() compares it strictly
                'class' => (new ReflectionClass($class))->getName(),
                'plugin' => $plugin,
                'provider' => $provider
            ];
        }
    }

    /**
     * Why a plugin form cannot be registered, if it cannot
     *
     * @param string $plugin Plugin route name
     * @param string $form   Form name
     * @param mixed  $class  Form class
     */
    private static function rejectForm(string $plugin, string $form, mixed $class): ?string
    {
        if (!preg_match('/^' . self::FORM_PATTERN . '\z/', $form)) {
            return 'name must be at most 50 lowercase letters, digits or underscores, starting with a letter';
        }
        //stored fields and values stay with their plugin, even once it is disabled:
        //routes holding no underscore, the name tells its single owner
        if ($form !== $plugin && !str_starts_with($form, $plugin . '_')) {
            return sprintf('name must be "%1$s", or start with "%1$s_"', $plugin);
        }
        if (
            isset(self::getCoreClasses()[$form])
            || in_array($form, self::RESERVED_FORMS, strict: true)
            || isset(self::$plugin_forms[$form])
        ) {
            return 'name is already used';
        }

        try {
            $exists = is_string($class) && class_exists($class);
        } catch (Throwable $e) {
            // no log: the reason is logged along with the ignored form.
            //a broken class file must not prevent Galette from starting
            return 'class cannot be loaded: ' . $e->getMessage();
        }
        if (!$exists) {
            return 'class does not exist';
        }
        $class = (new ReflectionClass($class))->getName();
        if (in_array($class, self::getClasses(), strict: true)) {
            //an object belongs to a single form
            return 'class is already used by another form';
        }
        if (!in_array(Dynamics::class, self::getTraits($class), strict: true)) {
            return sprintf('class does not use the %s trait', Dynamics::class);
        }

        return null;
    }

    /**
     * Get the traits a class uses, through its parents and other traits
     *
     * @param class-string $class Class name
     *
     * @return array<string>
     */
    private static function getTraits(string $class): array
    {
        $pending = array_merge([$class], array_values(class_parents($class)));
        $traits = [];
        while (($current = array_shift($pending)) !== null) {
            foreach (class_uses($current) as $trait) {
                if (!in_array($trait, $traits, strict: true)) {
                    $traits[] = $trait;
                    $pending[] = $trait;
                }
            }
        }
        return $traits;
    }

    /**
     * Drop the forms a plugin declared
     *
     * @param string $plugin Plugin route name
     */
    public static function unregisterForms(string $plugin): void
    {
        self::$plugin_forms = array_filter(
            self::$plugin_forms,
            static fn(array $form): bool => $form['plugin'] !== $plugin
        );
    }

    /**
     * Drop every plugin registration
     *
     * The registry is static, so it outlives a request only under a test
     * runner; that is where this is needed.
     */
    public static function resetForms(): void
    {
        self::$plugin_forms = [];
    }

    /**
     * Get the plugin that declared a form
     *
     * @param string $form Form name
     *
     * @return ?DynamicFormsProviderInterface Null for a core or an unknown form
     */
    public static function getFormProvider(string $form): ?DynamicFormsProviderInterface
    {
        return self::$plugin_forms[$form]['provider'] ?? null;
    }

    /**
     * Get the labels of the forms plugins declared
     *
     * Asked for on each call, so they follow the current language.
     *
     * @return array<string, string>
     */
    public static function getPluginFormsNames(): array
    {
        $names = [];
        foreach (self::$plugin_forms as $form => $entry) {
            $names[$form] = $entry['provider']->getDynamicFormLabel($form);
        }
        return $names;
    }

    /**
     * Get fields list for one form
     *
     * @param string $form_name    Form name
     * @param bool   $inaccessible Include fields nobody can see, for configuration
     *
     * @return DynamicField[]
     */
    public function getList(string $form_name, bool $inaccessible = false): array
    {
        //every form of a list item looks its fields up: read them once
        $results = array_filter(
            RowsCache::all($this->zdb, DynamicField::TABLE, DynamicField::PK),
            fn(ArrayObject $r): bool => $r['field_form'] === $form_name
        );
        //rows come by primary key, keep it for fields at the same index
        uasort($results, fn(ArrayObject $a, ArrayObject $b): int => (int)$a['field_index'] <=> (int)$b['field_index']);
        $access_level = $this->login->getAccessLevel();

        $fields = [];
        if (count($results) > 0) {
            foreach ($results as $r) {
                /** @var ArrayObject<string, int|string> $r */
                $df = DynamicField::getFieldType($this->zdb, (int)$r['field_type']);
                $df->loadFromRS($r);
                if (
                    !$df->isAccessible($access_level)
                    && !($inaccessible && $df->getPermission() === FieldsConfig::NOBODY)
                ) {
                    continue;
                }
                $fields[$r[DynamicField::PK]] = $df;
            }
        }
        return $fields;
    }

    /**
     * Get dynamic fields values of several items, occurrences in their order
     *
     * @param string     $form_name Form name
     * @param array<int> $ids       Items ids
     * @param array<int> $fields    Dynamic fields ids
     *
     * @return array<int, array<int, array<int, string>>> values per field and item id
     */
    public function getValuesFor(string $form_name, array $ids, array $fields): array
    {
        $values = [];
        if (count($ids) === 0 || count($fields) === 0) {
            return $values;
        }

        $select = $this->zdb->select(DynamicFieldsHandle::TABLE);
        $select->columns([DynamicFieldsHandle::PK, DynamicField::PK, 'field_val']);
        $select->where(['field_form' => $form_name]);
        $select->where->in(DynamicField::PK, $fields);
        $select->where->in(DynamicFieldsHandle::PK, $ids);
        $select->order([DynamicFieldsHandle::PK, DynamicField::PK, 'val_index']);

        foreach ($this->zdb->execute($select) as $row) {
            $values[(int)$row->{DynamicField::PK}][(int)$row->{DynamicFieldsHandle::PK}][]
                = (string)$row->field_val;
        }

        return $values;
    }

    /**
     * Get the text value of a dynamic field, as exported
     *
     * @param DynamicField $field Dynamic field
     * @param string       $value Stored value
     */
    public static function getTextValue(DynamicField $field, string $value): string
    {
        if ($field instanceof Text) {
            //raw text, display value is HTML
            return $value;
        }

        if ($field instanceof Choice) {
            //imported values may have been stored as text instead of an index
            $choices = $field->getValues();
            return is_array($choices) && isset($choices[$value]) ? (string)$choices[$value] : $value;
        }

        return $field->getDisplayValue($value);
    }
}
