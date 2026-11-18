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

        //a class of the plugin, with dynamic fields
        $thing = new class {
            use \Galette\Features\Dynamics;

            protected \Galette\Core\Db $zdb;
            protected ?\Galette\Core\Login $login = null;
            protected ?int $id = null;
            /** @var array<string> */
            protected array $errors = [];
        };
        $thing_class = $thing::class;

        $core = \Galette\Repository\DynamicFieldsSet::getClasses();
        $provider->forms = [
            'things' => $thing_class,
            'objectslend_object' => $thing_class,
            //names must fit the columns, and be lowercase
            str_repeat('a', 51) => $thing_class,
            'Things' => $thing_class,
            '1things' => $thing_class,
            //names already used
            'adh' => $thing_class,
            'member' => $thing_class,
            //classes must exist and have dynamic fields
            'nothing' => 'MyPlugin\DoesNotExist',
            'noclass' => 42,
            'nodynamic' => \Galette\Entity\Title::class,
        ];
        \Galette\Repository\DynamicFieldsSet::registerForms('myplugin', $provider);

        $this->assertSame(
            $core + ['things' => $thing_class, 'objectslend_object' => $thing_class],
            \Galette\Repository\DynamicFieldsSet::getClasses()
        );
        $this->assertSame($provider, \Galette\Repository\DynamicFieldsSet::getFormProvider('things'));
        $this->assertNull(\Galette\Repository\DynamicFieldsSet::getFormProvider('adh'));
        $this->assertNull(\Galette\Repository\DynamicFieldsSet::getFormProvider('unknown'));
        $this->assertSame('Label of things', DynamicField::getFormsNames()['things']);
        $this->assertSame('Label of things', DynamicField::getFormTitle('things'));

        $reasons = [
            str_repeat('a', 51) => 'name must be at most 50 lowercase letters, digits or underscores, starting with a letter',
            'Things' => 'name must be at most 50 lowercase letters, digits or underscores, starting with a letter',
            '1things' => 'name must be at most 50 lowercase letters, digits or underscores, starting with a letter',
            'adh' => 'name is already used',
            'member' => 'name is already used',
            'nothing' => 'class does not exist',
            'noclass' => 'class does not exist',
            'nodynamic' => 'class does not use the Galette\Features\Dynamics trait',
        ];
        foreach ($reasons as $form => $reason) {
            $this->expectLogEntry(
                \Analog\Analog::ERROR,
                sprintf('Plugin "myplugin" dynamic form "%s" is ignored: %s', $form, $reason)
            );
        }

        //another plugin cannot take a registered name
        $other = clone $provider;
        $other->forms = ['things' => $thing_class];
        \Galette\Repository\DynamicFieldsSet::registerForms('otherplugin', $other);
        $this->assertSame($provider, \Galette\Repository\DynamicFieldsSet::getFormProvider('things'));
        $this->expectLogEntry(
            \Analog\Analog::ERROR,
            'Plugin "otherplugin" dynamic form "things" is ignored: name is already used'
        );

        //a new registration replaces the previous one
        $provider->forms = ['stuff' => $thing_class];
        \Galette\Repository\DynamicFieldsSet::registerForms('myplugin', $provider);
        $this->assertSame(
            $core + ['stuff' => $thing_class],
            \Galette\Repository\DynamicFieldsSet::getClasses()
        );

        \Galette\Repository\DynamicFieldsSet::unregisterForms('myplugin');
        $this->assertSame($core, \Galette\Repository\DynamicFieldsSet::getClasses());
    }
}
