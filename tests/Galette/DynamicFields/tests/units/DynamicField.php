<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Dynamic fields tests
 *
 * PHP version 5
 *
 * Copyright © 2021-2023 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  DynamicFields
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2021-11-11
 */

namespace Galette\DynamicFields\test\units;

use atoum;

/**
 * Dynamic fields test
 *
 * @category  DynamicFields
 * @name      DynamicField
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2021-11-11
 */
class DynamicField extends atoum
{
    private \Galette\Core\Db $zdb;

    /**
     * Set up tests
     *
     * @param string $method Current test method
     *
     * @return void
     */
    public function beforeTestMethod($method)
    {
        $this->zdb = new \Galette\Core\Db();
    }

    /**
     * Tear down tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function afterTestMethod($method)
    {
        $delete = $this->zdb->delete(\Galette\DynamicFields\DynamicField::TABLE);
        $this->zdb->execute($delete);
        //cleanup dynamic translations
        $delete = $this->zdb->delete(\Galette\Core\L10n::TABLE);
        $this->zdb->execute($delete);

        $tables = $this->zdb->getTables();
        foreach ($tables as $table) {
            if (str_starts_with($table, 'galette_field_contents_')) {
                $this->zdb->db->query(
                    'DROP TABLE ' . $table,
                    \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
                );
            }
        }
    }

    /**
     * Test loadFieldType
     *
     * @return void
     */
    public function testLoadFieldType()
    {
        $this->boolean(\Galette\DynamicFields\DynamicField::loadFieldType($this->zdb, 10))->isFalse();

        $field_data = [
            'form_name'         => 'adh',
            'field_name'        => 'Dynamic text field',
            'field_perm'        => \Galette\DynamicFields\DynamicField::PERM_USER_WRITE,
            'field_type'        => \Galette\DynamicFields\DynamicField::TEXT,
            'field_required'    => true,
            'field_repeat'      => 1
        ];

        $df = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $field_data['field_type']);
        $stored = $df->store($field_data);
        $this->boolean($stored)->isTrue(
            implode(
                ' ',
                $df->getErrors() + $df->getWarnings()
            )
        );
        $this->boolean($stored)->isTrue();
        $this->object(\Galette\DynamicFields\DynamicField::loadFieldType($this->zdb, $df->getId()))->isEqualTo($df);
        $this->string($df->getForm())->isIdenticalTo('adh');
        $this->integer($df->getIndex())->isIdenticalTo(1);

        $df = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $field_data['field_type']);
        $field_data['field_name'] = 'Another one';
        $stored = $df->store($field_data);
        $this->boolean($stored)->isTrue(
            implode(
                ' ',
                $df->getErrors() + $df->getWarnings()
            )
        );
        $this->boolean($stored)->isTrue();
        $this->object(\Galette\DynamicFields\DynamicField::loadFieldType($this->zdb, $df->getId()))->isEqualTo($df);
        $this->integer($df->getIndex())->isIdenticalTo(2);

        $field_data['field_name'] = 'Another one - modified';
        $stored = $df->store($field_data);
        $this->boolean($stored)->isTrue(
            implode(
                ' ',
                $df->getErrors() + $df->getWarnings()
            )
        );
        $this->boolean($stored)->isTrue();
        $this->string($df->getName())->isIdenticalTo($field_data['field_name']);
    }

    /**
     * Permissions names provider
     *
     * @return array
     */
    protected function permsProvider(): array
    {
        return [
            [
                'perm' => \Galette\DynamicFields\DynamicField::PERM_USER_WRITE,
                'name' => "User, read/write"
            ],
            [
                'perm' => \Galette\DynamicFields\DynamicField::PERM_STAFF,
                'name' => "Staff member"
            ],
            [
                'perm' => \Galette\DynamicFields\DynamicField::PERM_ADMIN,
                'name' => "Administrator"
            ],
            [
                'perm' => \Galette\DynamicFields\DynamicField::PERM_MANAGER,
                'name' => "Group manager"
            ],
            [
                'perm' => \Galette\DynamicFields\DynamicField::PERM_USER_READ,
                'name' => "User, read only"
            ]
        ];
    }

    /**
     * Test getPermsNames
     *
     * @return void
     */
    public function testGetPermsNames()
    {
        $expected = [];
        foreach ($this->permsProvider() as $perm) {
            $expected[$perm['perm']] = $perm['name'];
        }

        $this->array(\Galette\DynamicFields\DynamicField::getPermsNames())->isIdenticalTo($expected);
    }

    /**
     * Tets getPermName
     *
     * @param integer $perm Permission
     * @param string  $name Name
     *
     * @dataProvider permsProvider
     *
     * @return void
     */
    public function testGetPermName(int $perm, string $name)
    {
        $field_data = [
            'form_name'         => 'adh',
            'field_name'        => 'Dynamic separator ' . $name,
            'field_perm'        => $perm,
            'field_type'        => \Galette\DynamicFields\DynamicField::SEPARATOR,
            'field_required'    => false,
            'field_repeat'      => null
        ];

        $df = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $field_data['field_type']);
        $stored = $df->store($field_data);
        $this->boolean($stored)->isTrue(
            implode(
                ' ',
                $df->getErrors() + $df->getWarnings()
            )
        );
        $this->boolean($stored)->isTrue();
        $this->string($df->getPermName())->isIdenticalTo($name);
    }

    /**
     * Test getFormsNames
     *
     * @return void
     */
    public function testGetFormsNames()
    {
        $expected = [];
        foreach ($this->formNamesProvider() as $form) {
            $expected[$form['form']] = $form['expected'];
        }
        $this->array(\Galette\DynamicFields\DynamicField::getFormsNames())->isIdenticalTo($expected);
    }

    /**
     * Form names provider
     *
     * @return \string[][]
     */
    protected function formNamesProvider(): array
    {
        return [
            [
                'form' => 'adh',
                'expected' => "Members"
            ],
            [
                'form' => 'contrib',
                'expected' => "Contributions"
            ],
            [
                'form' => 'trans',
                'expected' => "Transactions"
            ]
        ];
    }

    /**
     * Test getFormTitle
     *
     * @param string $form     Form name
     * @param string $expected Expected name
     *
     * @dataProvider formNamesProvider
     *
     * @return void
     */
    public function testGetFormTitle(string $form, string $expected)
    {
        $this->string(\Galette\DynamicFields\DynamicField::getFormTitle($form))->isIdenticalTo($expected);
    }

    /**
     * Test getFixedValuesTableName
     *
     * @return void
     */
    public function testGetFixedValuesTableName()
    {
        $this->string(\Galette\DynamicFields\DynamicField::getFixedValuesTableName(10))->isIdenticalTo('field_contents_10');
        $this->string(\Galette\DynamicFields\DynamicField::getFixedValuesTableName(10, false))->isIdenticalTo('field_contents_10');
        $this->string(\Galette\DynamicFields\DynamicField::getFixedValuesTableName(10, true))->isIdenticalTo('galette_field_contents_10');
    }

    /**
     * Test getValues
     *
     * @return void
     */
    public function testGetValues()
    {
        $field_data = [
            'form_name'         => 'adh',
            'field_name'        => 'Dynamic choice',
            'field_perm'        => \Galette\DynamicFields\DynamicField::PERM_USER_WRITE,
            'field_type'        => \Galette\DynamicFields\DynamicField::CHOICE,
            'field_required'    => false,
            'field_repeat'      => null,
            'fixed_values'      => implode("\n", [
                'One',
                'Two',
                'Three'
            ])
        ];

        $df = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $field_data['field_type']);
        $stored = $df->store($field_data);
        $this->boolean($stored)->isTrue(
            implode(
                ' ',
                $df->getErrors() + $df->getWarnings()
            )
        );
        $this->boolean($stored)->isTrue();

        $stored = $df->load($df->getId());
        $this->array($df->getValues())->isIdenticalTo(['One', 'Two', 'Three']);
        $this->string($df->getValues(true))->isIdenticalTo("One\nTwo\nThree");
        $this->integer($df->getIndex())->isIdenticalTo(1);
    }

    /**
     * Test check
     *
     * @return void
     */
    public function testCheck()
    {
        $values = [
            'form_name'         => 'adh',
            'field_name'        => 'Dynamic choice',
            'field_perm'        => \Galette\DynamicFields\DynamicField::PERM_USER_WRITE,
            'field_type'        => \Galette\DynamicFields\DynamicField::CHOICE,
            'field_required'    => false,
            'field_repeat'      => null,
            'fixed_values'      => implode("\n", [
                'One',
                'Two',
                'Three'
            ])
        ];
        $orig_values = $values;
        $df = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $values['field_type']);

        $this->boolean($df->check($values))->isTrue();
        $this->array($df->getErrors())->isIdenticalTo([]);

        $values['form_name'] = 'unk';
        $this->boolean($df->check($values))->isFalse();
        $this->array($df->getErrors())->isIdenticalTo(['Unknown form!']);

        $values['field_perm'] = 42;
        $this->boolean($df->check($values))->isFalse();
        $this->array($df->getErrors())->isIdenticalTo(['Unknown permission!', 'Unknown form!']);

        $values = $orig_values;
        $values['field_perm'] = '';
        $this->boolean($df->check($values))->isFalse();
        $this->array($df->getErrors())->isIdenticalTo(['Missing required field permissions!']);

        unset($values['field_perm']);
        $this->boolean($df->check($values))->isFalse();
        $this->array($df->getErrors())->isIdenticalTo(['Missing required field permissions!']);

        $values = $orig_values;
        $values['form_name'] = '';
        $this->boolean($df->check($values))->isFalse();
        $this->array($df->getErrors())->isIdenticalTo(['Missing required form!']);
        $values = $orig_values;
        unset($values['form_name']);
        $this->boolean($df->check($values))->isFalse();
        $this->array($df->getErrors())->isIdenticalTo(['Missing required form!']);

        $values = $orig_values;
        $values['field_name'] = '';
        $this->boolean($df->check($values))->isFalse();
        $this->array($df->getErrors())->isIdenticalTo(['Missing required field name!']);
        $values = $orig_values;
        unset($values['field_name']);
        $this->boolean($df->check($values))->isFalse();
        $this->array($df->getErrors())->isIdenticalTo(['Missing required field name!']);
        $this->boolean($df->store($values))->isFalse();
    }

    /**
     * Test move
     *
     * @return void
     */
    public function testMove()
    {
        $field_data = [
            'form_name'         => 'adh',
            'field_name'        => 'A first text field',
            'field_perm'        => \Galette\DynamicFields\DynamicField::PERM_USER_WRITE,
            'field_type'        => \Galette\DynamicFields\DynamicField::TEXT,
            'field_required'    => true,
            'field_repeat'      => 1
        ];

        $df = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $field_data['field_type']);
        $stored = $df->store($field_data);
        $this->boolean($stored)->isTrue(
            implode(
                ' ',
                $df->getErrors() + $df->getWarnings()
            )
        );
        $this->boolean($stored)->isTrue();
        $this->object(\Galette\DynamicFields\DynamicField::loadFieldType($this->zdb, $df->getId()))->isEqualTo($df);
        $this->integer($df->getIndex())->isIdenticalTo(1);
        $df_id_1 = $df->getId();

        $df = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $field_data['field_type']);
        $field_data['field_name'] = 'A second text field';
        $stored = $df->store($field_data);
        $this->boolean($stored)->isTrue(
            implode(
                ' ',
                $df->getErrors() + $df->getWarnings()
            )
        );
        $this->boolean($stored)->isTrue();
        $this->object(\Galette\DynamicFields\DynamicField::loadFieldType($this->zdb, $df->getId()))->isEqualTo($df);
        $this->integer($df->getIndex())->isIdenticalTo(2);
        $df_id_2 = $df->getId();

        $df = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $field_data['field_type']);
        $field_data['field_name'] = 'A third text field';
        $stored = $df->store($field_data);
        $this->boolean($stored)->isTrue(
            implode(
                ' ',
                $df->getErrors() + $df->getWarnings()
            )
        );
        $this->boolean($stored)->isTrue();
        $this->object(\Galette\DynamicFields\DynamicField::loadFieldType($this->zdb, $df->getId()))->isEqualTo($df);
        $this->integer($df->getIndex())->isIdenticalTo(3);
        $df_id_3 = $df->getId();

        $this->boolean($df->move(\Galette\DynamicFields\DynamicField::MOVE_UP))->isTrue();
        $df->load($df_id_1);
        $this->integer($df->getIndex())->isIdenticalTo(1);

        $df->load($df_id_2);
        $this->integer($df->getIndex())->isIdenticalTo(3);

        $df->load($df_id_3);
        $this->integer($df->getIndex())->isIdenticalTo(2);

        $df->load($df_id_1);
        $this->boolean($df->move(\Galette\DynamicFields\DynamicField::MOVE_DOWN))->isTrue();
        $df->load($df_id_1);
        $this->integer($df->getIndex())->isIdenticalTo(2);

        $df->load($df_id_2);
        $this->integer($df->getIndex())->isIdenticalTo(3);

        $df->load($df_id_3);
        $this->integer($df->getIndex())->isIdenticalTo(1);
    }

    /**
     * Test remove
     *
     * @return void
     */
    public function testRemove()
    {
        $field_data = [
            'form_name'         => 'adh',
            'field_name'        => 'Dynamic choice',
            'field_perm'        => \Galette\DynamicFields\DynamicField::PERM_USER_WRITE,
            'field_type'        => \Galette\DynamicFields\DynamicField::CHOICE,
            'field_required'    => false,
            'field_repeat'      => null,
            'fixed_values'      => implode("\n", [
                'One',
                'Two',
                'Three'
            ])
        ];
        $df = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $field_data['field_type']);
        $stored = $df->store($field_data);
        $this->boolean($stored)->isTrue(
            implode(
                ' ',
                $df->getErrors() + $df->getWarnings()
            )
        );
        $this->boolean($stored)->isTrue();
        $df_id = $df->getId();

        //check if table has been created
        $select = $this->zdb->select($df::getFixedValuesTableName($df->getId()));
        $results = $this->zdb->execute($select);
        $this->integer($results->count())->isIdenticalTo(3);

        $this->boolean($df->remove())->isTrue();

        $this->exception(
            function () use ($select) {
                $results = $this->zdb->execute($select);
            }
        )->isInstanceOf('\PDOException');
        $this->boolean(\Galette\DynamicFields\DynamicField::loadFieldType($this->zdb, $df_id))->isFalse();
    }

    /**
     * Test information
     *
     * @return void
     */
    public function testInformation()
    {
        $field_data = [
            'form_name'         => 'adh',
            'field_name'        => 'A first text field',
            'field_perm'        => \Galette\DynamicFields\DynamicField::PERM_USER_WRITE,
            'field_type'        => \Galette\DynamicFields\DynamicField::TEXT,
            'field_information' => '<p>This is an important information.</p><p>And here an xss...  <img src=img.png onerror=alert(1) /></p>'
        ];

        $df = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $field_data['field_type']);
        $stored = $df->store($field_data);
        $this->boolean($stored)->isTrue(
            implode(
                ' ',
                $df->getErrors() + $df->getWarnings()
            )
        );
        $this->boolean($stored)->isTrue();
        $this->object(\Galette\DynamicFields\DynamicField::loadFieldType($this->zdb, $df->getId()))->isEqualTo($df);

        $this->string($df->getInformation())->isIdenticalTo('<p>This is an important information.</p><p>And here an xss...  <img src="img.png" alt="img.png" /></p>');
    }
}
