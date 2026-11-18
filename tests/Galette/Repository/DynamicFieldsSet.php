<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Repository;

use Galette\DynamicFields\DynamicField;
use Galette\Tests\DynamicFieldsHelper;
use Galette\Tests\GaletteTestCase;

use function Safe\spl_autoload_register;
use function Safe\spl_autoload_unregister;

/**
 * Dynamic fields set repository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DynamicFieldsSet extends GaletteTestCase
{
    use DynamicFieldsHelper;

    protected int $seed = 20261023093512;

    /**
     * Test getValuesFor
     */
    public function testGetValuesFor(): void
    {
        $fieldset = new \Galette\Repository\DynamicFieldsSet($this->zdb, $this->login);
        $this->assertSame([], $fieldset->getValuesFor('adh', [], [1]));
        $this->assertSame([], $fieldset->getValuesFor('adh', [1], []));

        $phones = $this->createDynamicField(DynamicField::LINE, 'Phones', ['field_repeat' => 0]);
        $size = $this->createDynamicField(DynamicField::LINE, 'Size');
        $other = $this->createDynamicField(DynamicField::LINE, 'Other');

        //inserted out of order, to check occurrences are sorted
        $this->storeDynamicValues([
            [1, $phones, 2, '0607080910'],
            [1, $phones, 1, '0102030405'],
            [1, $size, 1, 'M'],
            [2, $size, 1, 'L'],
            [3, $size, 1, 'S'],
            [1, $other, 1, 'not requested'],
        ]);
        //same item id, another form
        $this->storeDynamicValues([[2, $phones, 1, 'contribution']], 'contrib');

        $this->assertSame(
            [
                $phones => [1 => ['0102030405', '0607080910']],
                $size => [1 => ['M'], 2 => ['L']],
            ],
            $fieldset->getValuesFor('adh', [1, 2], [$phones, $size])
        );
        $this->assertSame(
            [$phones => [2 => ['contribution']]],
            $fieldset->getValuesFor('contrib', [1, 2], [$phones, $size])
        );
    }

    /**
     * Test getTextValue
     */
    public function testGetTextValue(): void
    {
        $line = DynamicField::getFieldType($this->zdb, DynamicField::LINE);
        $this->assertSame('a <b>value</b>', \Galette\Repository\DynamicFieldsSet::getTextValue($line, 'a <b>value</b>'));

        $text = DynamicField::getFieldType($this->zdb, DynamicField::TEXT);
        $this->assertSame(
            "First line\nSecond line",
            \Galette\Repository\DynamicFieldsSet::getTextValue($text, "First line\nSecond line")
        );

        $size = $this->createDynamicField(DynamicField::CHOICE, 'Size', ['fixed_values' => "S\nM\nL"]);
        $choice = DynamicField::loadFieldType($this->zdb, $size);
        $this->assertInstanceOf(\Galette\DynamicFields\Choice::class, $choice);
        $this->assertSame('M', \Galette\Repository\DynamicFieldsSet::getTextValue($choice, '1'));
        //imported choices may be stored as text
        $this->assertSame('XL', \Galette\Repository\DynamicFieldsSet::getTextValue($choice, 'XL'));

        $volunteer = DynamicField::getFieldType($this->zdb, DynamicField::BOOLEAN);
        $this->assertSame(_T('Yes'), \Galette\Repository\DynamicFieldsSet::getTextValue($volunteer, '1'));
        $this->assertSame(_T('No'), \Galette\Repository\DynamicFieldsSet::getTextValue($volunteer, '0'));

        $date = DynamicField::getFieldType($this->zdb, DynamicField::DATE);
        $this->assertSame('2026-03-01', \Galette\Repository\DynamicFieldsSet::getTextValue($date, '2026-03-01'));
    }

    /**
     * Test forms declared by plugins
     */
    public function testRegisterForms(): void
    {
        $provider = new class implements \Galette\Core\Plugins\DynamicFormsProviderInterface {
            /** @var array<string, mixed> */
            public array $forms = [];

            /**
             * Get the forms the plugin declares
             *
             * @return array<string, class-string>
             */
            public function getDynamicForms(): array
            {
                if (isset($this->forms['throw'])) {
                    throw new \RuntimeException('Cannot list forms');
                }
                return $this->forms; //@phpstan-ignore return.type
            }

            /**
             * Get the label of a declared form
             *
             * @param string $form Form name
             */
            public function getDynamicFormLabel(string $form): string
            {
                return 'Label of ' . $form;
            }

            /**
             * Get an object of a declared form, if the user can see it
             *
             * @param string              $form  Form name
             * @param int                 $id    Object identifier
             * @param \Galette\Core\Login $login Current user
             */
            public function getDynamicFormObject(string $form, int $id, \Galette\Core\Login $login): ?object
            {
                return null;
            }

            /**
             * Get the page to go back to when a file of a declared form cannot be sent
             *
             * @param string                    $form        Form name
             * @param int                       $id          Object identifier
             * @param \Slim\Routing\RouteParser $routeparser Route parser
             */
            public function getDynamicFormReturnUrl(string $form, int $id, \Slim\Routing\RouteParser $routeparser): string
            {
                return $routeparser->urlFor('slash');
            }
        };

        //classes of the plugin, with dynamic fields; an object belongs to a single form
        $thing_class = (new class {
            use \Galette\Features\Dynamics;

            protected \Galette\Core\Db $zdb;
            protected ?\Galette\Core\Login $login = null;
            protected ?int $id = null;
            /** @var array<string> */
            protected array $errors = [];
        })::class;
        $other_class = (new class {
            use \Galette\Features\Dynamics;

            protected \Galette\Core\Db $zdb;
            protected ?\Galette\Core\Login $login = null;
            protected ?int $id = null;
            /** @var array<string> */
            protected array $errors = [];
        })::class;
        $long_class = (new class {
            use \Galette\Features\Dynamics;

            protected \Galette\Core\Db $zdb;
            protected ?\Galette\Core\Login $login = null;
            protected ?int $id = null;
            /** @var array<string> */
            protected array $errors = [];
        })::class;

        //a class file that cannot be loaded
        $broken_loader = static function (string $class): void {
            if ($class === 'MyPlugin\Broken') {
                throw new \ParseError('syntax error, unexpected end of file');
            }
        };
        spl_autoload_register($broken_loader);

        //once loaded, PHP finds a class whatever its case
        $this->assertTrue(class_exists(\GaletteTest1Plugin\NestedThing::class));

        $core = \Galette\Repository\DynamicFieldsSet::getClasses();
        $provider->forms = [
            //the plugin name alone, or prefixed
            'myplugin' => $thing_class,
            'myplugin_other' => $other_class,
            //found through a trait of the plugin; spelled as PHP spells it
            'myplugin_nested' => strtolower(\GaletteTest1Plugin\NestedThing::class),
            //names must fit the columns, and be lowercase
            'myplugin_' . str_repeat('a', 41) => $long_class,
            'myplugin_' . str_repeat('a', 42) => $thing_class,
            'myplugin_' . str_repeat('b', 41) . "\n" => $thing_class,
            'Myplugin_things' => $thing_class,
            //names belong to their plugin
            'things' => $thing_class,
            'mypluginthings' => $thing_class,
            'otherplugin_things' => $thing_class,
            //classes must exist, have dynamic fields, and belong to a single form
            'myplugin_nothing' => 'MyPlugin\DoesNotExist',
            'myplugin_noclass' => 42,
            'myplugin_broken' => 'MyPlugin\Broken',
            'myplugin_nodynamic' => \Galette\Entity\Title::class,
            'myplugin_twice' => $thing_class,
            'myplugin_member' => \Galette\Entity\Adherent::class,
            'myplugin_test' => \GaletteTest1Plugin\Thing::class,
        ];
        \Galette\Repository\DynamicFieldsSet::registerForms('myplugin', $provider);
        spl_autoload_unregister($broken_loader);

        $this->assertSame(
            $core + [
                'myplugin' => $thing_class,
                'myplugin_other' => $other_class,
                'myplugin_nested' => \GaletteTest1Plugin\NestedThing::class,
                'myplugin_' . str_repeat('a', 41) => $long_class,
            ],
            \Galette\Repository\DynamicFieldsSet::getClasses()
        );
        $this->assertSame($provider, \Galette\Repository\DynamicFieldsSet::getFormProvider('myplugin'));
        $this->assertNull(\Galette\Repository\DynamicFieldsSet::getFormProvider('adh'));
        $this->assertNull(\Galette\Repository\DynamicFieldsSet::getFormProvider('unknown'));
        $this->assertSame('Label of myplugin_other', DynamicField::getFormsNames()['myplugin_other']);
        $this->assertSame('Label of myplugin_other', DynamicField::getFormTitle('myplugin_other'));
        $this->assertSame(
            'myplugin_nested',
            (new \GaletteTest1Plugin\NestedThing($this->zdb, $this->login))->getFormName()
        );

        $length = 'name must be at most 50 lowercase letters, digits or underscores, starting with a letter';
        $prefix = 'name must be "myplugin", or start with "myplugin_"';
        $reasons = [
            'myplugin_' . str_repeat('a', 42) => $length,
            'myplugin_' . str_repeat('b', 41) . "\n" => $length,
            'Myplugin_things' => $length,
            'things' => $prefix,
            'mypluginthings' => $prefix,
            'otherplugin_things' => $prefix,
            'myplugin_nothing' => 'class does not exist',
            'myplugin_noclass' => 'class does not exist',
            'myplugin_broken' => 'class cannot be loaded: syntax error, unexpected end of file',
            'myplugin_nodynamic' => 'class does not use the Galette\Features\Dynamics trait',
            'myplugin_twice' => 'class is already used by another form',
            'myplugin_member' => 'class is already used by another form',
            'myplugin_test' => 'class is already used by another form',
        ];
        foreach ($reasons as $form => $reason) {
            $this->expectLogEntry(
                \Analog\Analog::ERROR,
                sprintf('Plugin "myplugin" dynamic form "%s" is ignored: %s', $form, $reason)
            );
        }

        //a form of a disabled plugin stays its own: no other route can match its name
        \Galette\Repository\DynamicFieldsSet::unregisterForms('myplugin');
        $other = clone $provider;
        $other->forms = ['myplugin_other' => $other_class];
        \Galette\Repository\DynamicFieldsSet::registerForms('myplugin_other', $other);
        \Galette\Repository\DynamicFieldsSet::registerForms('mypluginother', $other);
        $this->assertSame($core, \Galette\Repository\DynamicFieldsSet::getClasses());
        $this->expectLogEntry(
            \Analog\Analog::ERROR,
            'Plugin "myplugin_other" dynamic forms are ignored: its route name contains an underscore'
        );
        $this->expectLogEntry(
            \Analog\Analog::ERROR,
            'Plugin "mypluginother" dynamic form "myplugin_other" is ignored: name must be "mypluginother", or start with "mypluginother_"'
        );

        //it gets its forms back once enabled again
        $provider->forms = ['myplugin_other' => $other_class];
        \Galette\Repository\DynamicFieldsSet::registerForms('myplugin', $provider);
        $this->assertSame($core + ['myplugin_other' => $other_class], \Galette\Repository\DynamicFieldsSet::getClasses());
        $this->assertSame($provider, \Galette\Repository\DynamicFieldsSet::getFormProvider('myplugin_other'));

        //a plugin failing to tell its forms does not prevent others from starting
        $failing = clone $provider;
        $failing->forms = ['throw' => true];
        \Galette\Repository\DynamicFieldsSet::registerForms('failingplugin', $failing);
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Plugin "failingplugin" dynamic forms are ignored | Cannot list forms');

        //a new registration replaces the previous one
        $provider->forms = ['myplugin_stuff' => $thing_class];
        \Galette\Repository\DynamicFieldsSet::registerForms('myplugin', $provider);
        $this->assertSame(
            $core + ['myplugin_stuff' => $thing_class],
            \Galette\Repository\DynamicFieldsSet::getClasses()
        );

        \Galette\Repository\DynamicFieldsSet::unregisterForms('myplugin');
        $this->assertSame($core, \Galette\Repository\DynamicFieldsSet::getClasses());
    }
}
