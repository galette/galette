<?php

/**
 * Copyright © 2003-2024 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
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
 */

declare(strict_types=1);

namespace Galette\IO\test\units;

use Galette\Entity\FieldsConfig;
use PHPUnit\Framework\TestCase;
use Galette\Entity\Adherent;
use Galette\DynamicFields\DynamicField;
use Galette\GaletteTestCase;

/**
 * CsvIn tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CsvIn extends GaletteTestCase
{
    private ?string $contents_table = null;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->contents_table = null;
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $this->zdb->execute($delete);
        $delete = $this->zdb->delete(\Galette\Entity\DynamicFieldsHandle::TABLE);
        $this->zdb->execute($delete);
        $delete = $this->zdb->delete(DynamicField::TABLE);
        $this->zdb->execute($delete);
        //cleanup dynamic translations
        $delete = $this->zdb->delete(\Galette\Core\L10n::TABLE);
        $delete->where([
            'text_orig' => [
                'Dynamic choice field',
                'Dynamic date field',
                'Dynamic text field'
            ]
        ]);
        $this->zdb->execute($delete);

        if ($this->contents_table !== null) {
            $this->zdb->drop($this->contents_table);
        }
    }

    /**
     * Import text CSV file
     *
     * @param array   $fields         Fields name to use at import
     * @param string  $file_name      File name
     * @param array   $flash_messages Expected flash messages from doImport route
     * @param array   $members_list   List of faked members data
     * @param integer $count_before   Count before insertions. Defaults to 0 if null.
     * @param integer $count_after    Count after insertions. Default to $count_before + count $members_list
     * @param array   $values         Textual values for dynamic choices fields
     *
     * @return void
     */
    private function doImportFileTest(
        array $fields,
        string $file_name,
        array $flash_messages,
        array $members_list,
        int $count_before = null,
        int $count_after = null,
        array $values = []
    ): void {
        if ($count_before === null) {
            $count_before = 0;
        }
        if ($count_after === null) {
            $count_after = $count_before + count($members_list);
        }

        $members = new \Galette\Repository\Members();
        $list = $members->getList();
        $this->assertSame(
            $count_before,
            $list->count(),
            print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1), true)
        );

        $model = $this->getModel($fields);

        //get csv model file to add data in
        $controller = new \Galette\Controllers\CsvController($this->container);
        $this->container->injectOn($controller);

        $rfactory = new \Slim\Psr7\Factory\RequestFactory();
        $request = $rfactory->createRequest('GET', 'http://localhost/models/csv');
        $response = new \Slim\Psr7\Response();

        $response = $controller->getImportModel($request, $response);
        $csvin = new \Galette\IO\CsvIn($this->container->get('zdb'));

        $this->assertSame(200, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertIsArray($headers);
        $this->assertSame(['text/csv'], $headers['Content-Type']);
        $this->assertSame(
            ['attachment;filename="galette_import_model.csv"'],
            $headers['Content-Disposition']
        );

        $csvfile_model = $response->getBody()->__toString();
        $this->assertSame(
            "\"" . implode("\";\"", $fields) . "\"\r\n",
            $csvfile_model
        );

        $contents = $csvfile_model;
        foreach ($members_list as $member) {
            $amember = [];
            foreach ($fields as $field) {
                $amember[$field] = $member[$field];
            }
            $contents .= "\"" . implode("\";\"", $amember) . "\"\r\n";
        }

        $path = GALETTE_CACHE_DIR . $file_name;
        $this->assertIsInt(file_put_contents($path, $contents));
        $_FILES['new_file'] = [
            'error' => UPLOAD_ERR_OK,
            'name'      => $file_name,
            'tmp_name'  => $path,
            'size'      => filesize($path)
        ];
        $this->assertTrue($csvin->store($_FILES['new_file'], true));
        $this->assertTrue(file_exists($csvin->getDestDir() . $csvin->getFileName()));

        $post = [
            'import_file'   => $file_name
        ];

        $request = clone $request;
        $request = $request->withParsedBody($post);

        $response = $controller->doImports($request, $response);
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame($flash_messages, $this->flash_data['slimFlash']);
        $this->flash->clearMessages();

        $members = new \Galette\Repository\Members();
        $list = $members->getList();
        $this->assertSame($count_after, $list->count());

        if ($count_before != $count_after) {
            foreach ($list as $member) {
                if (!isset($members_list[$member->fingerprint])) {
                    continue;
                }
                $created = $members_list[$member->fingerprint];
                foreach ($fields as $field) {
                    if (property_exists($member, $field)) {
                        if ($field === \Galette\Entity\Status::PK && $created[$field] === null) {
                            $this->assertNotNull($member->$field);
                        } else if ($field === 'pref_lang' && $created[$field] === null) {
                            $this->assertNotNull($member->$field);
                        } else {
                            $this->assertEquals($created[$field], $member->$field);
                        }
                    } else {
                        //manage dynamic fields
                        $matches = [];
                        if (preg_match('/^dynfield_(\d+)/', $field, $matches)) {
                            $adh = new Adherent($this->zdb, (int)$member->id_adh, ['dynamics' => true]);
                            $expected = [
                                [
                                    'item_id'       => $adh->id,
                                    'field_form'    => 'adh',
                                    'val_index'     => 1,
                                    'field_val'     => $created[$field]
                                ]
                            ];

                            $dfield = $adh->getDynamicFields()->getValues((int)$matches[1]);
                            if (isset($dfield[0]['text_val'])) {
                                //choice, add textual value
                                $expected[0]['text_val'] = $values[$created[$field]];
                            }

                            $this->assertEquals(
                                $expected,
                                $adh->getDynamicFields()->getValues((int)$matches[1])
                            );
                        } else {
                            throw new \RuntimeException("Unknown field $field");
                        }
                    }
                }
            }
        }
    }

    /**
     * Test CSV import loading
     *
     * @return void
     */
    public function testImport(): void
    {
        $fields = ['nom_adh', 'ville_adh', 'bool_exempt_adh', 'fingerprint'];
        $file_name = 'test-import-atoum.csv';
        $flash_messages = [
            'success_detected' => ["File '$file_name' has been successfully imported :)"]
        ];
        $members_list = $this->getMemberData1();
        $count_before = 0;
        $count_after = 10;

        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        //missing name
        $file_name = 'test-import-atoum-noname.csv';
        $flash_messages = [
            'error_detected' => [
                'File does not comply with requirements.',
                'Field nom_adh is required, but missing in row 3'
            ]
        ];

        $members_list = $this->getMemberData2NoName();
        $count_before = 10;
        $count_after = 10;

        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        //test status import
        $fields = ['nom_adh', 'ville_adh', 'fingerprint', \Galette\Entity\Status::PK];
        $file_name = 'test-import-status-ko.csv';
        $flash_messages = [
            'error_detected' => [
                'File does not comply with requirements.',
                'Status 42 does not exists!'
            ]
        ];
        $members_list = [
            'FAKER_STATUS' => [
                'nom_adh' => 'Status tests name',
                'ville_adh' => 'Status tests city',
                'fingerprint' => 'FAKER_STATUS',
                \Galette\Entity\Status::PK => 42 //non-existing status
            ]
        ];
        $count_before = 10;
        $count_after = 10;
        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        $members_list['FAKER_STATUS'][\Galette\Entity\Status::PK] = 1; //existing status
        $file_name = 'test-import-status-ok.csv';
        $flash_messages = [
            'success_detected' => ["File '$file_name' has been successfully imported :)"]
        ];
        $count_after = 11;
        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        //create with default status
        $members_list = [
            'FAKER_DEF_STATUS' => [
                'nom_adh' => 'Member with default status',
                'ville_adh' => 'Member with default status city',
                'fingerprint' => 'FAKER_DEF_STATUS',
                \Galette\Entity\Status::PK => null //no specified status
            ]
        ];
        $file_name = 'test-import-default-status.csv';
        $flash_messages = [
            'success_detected' => ["File '$file_name' has been successfully imported :)"]
        ];
        $count_before = 11;
        $count_after = 12;
        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        //check created member
        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE);
        $select->where(['fingerprint' => 'FAKER_DEF_STATUS']);
        $result = $this->zdb->execute($select)->current();
        $this->assertSame(
            (int)($this->preferences->pref_statut ?? \Galette\Entity\Status::DEFAULT_STATUS),
            $result[\Galette\Entity\Status::PK]
        );

        //test title import
        $fields = ['nom_adh', 'ville_adh', 'fingerprint', 'titre_adh'];
        $file_name = 'test-import-title-ko.csv';
        $flash_messages = [
            'error_detected' => [
                'File does not comply with requirements.',
                'Title 42 does not exists!'
            ]
        ];
        $members_list = [
            'FAKER_TITLE' => [
                'nom_adh' => 'Status tests name',
                'ville_adh' => 'Status tests city',
                'fingerprint' => 'FAKER_TITLE',
                'titre_adh' => 42 //non-existing title
            ]
        ];
        $count_before = 12;
        $count_after = 12;
        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        $members_list['FAKER_TITLE']['titre_adh'] = \Galette\Entity\Title::MR; //existing title
        $file_name = 'test-import-title-ok.csv';
        $flash_messages = [
            'success_detected' => ["File '$file_name' has been successfully imported :)"]
        ];
        $count_after = 13;
        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        //test email unicity
        $fields = ['nom_adh', 'email_adh', 'fingerprint'];
        $file_name = 'test-import-email-duplicate.csv';
        $flash_messages = [
            'error_detected' => [
                'File does not comply with requirements.',
                'Email address mail@domain.com is already used! (from another member in import)'
            ]
        ];
        $members_list = [
            'FAKER_MAIL_1' => [
                'nom_adh' => 'Member email 1',
                'email_adh' => 'mail@domain.com',
                'fingerprint' => 'FAKER_MAIL_1'
            ],
            'FAKER_MAIL_12' => [
                'nom_adh' => 'Member email 2',
                'email_adh' => 'mail@domain.com',
                'fingerprint' => 'FAKER_MAIL_2'
            ]
        ];
        $count_before = 13;
        $count_after = 13;
        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        $file_name = 'test-import-email-ok.csv';
        $flash_messages = [
            'success_detected' => ["File '$file_name' has been successfully imported :)"]
        ];
        $members_list = [
            'FAKER_MAIL_1' => [
                'nom_adh' => 'Member email 1',
                'email_adh' => 'mail@domain.com',
                'fingerprint' => 'FAKER_MAIL_1'
            ]
        ];
        $count_before = 13;
        $count_after = 14;
        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        //get created member
        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE);
        $select->where(['fingerprint' => 'FAKER_MAIL_1']);
        $result = $this->zdb->execute($select)->current();
        $this->assertSame('mail@domain.com', $result['email_adh']);

        $file_name = 'test-import-email-duplicate-again.csv';
        $flash_messages = [
            'error_detected' => [
                'File does not comply with requirements.',
                'Email address mail@domain.com is already used! (from member ' . $result['id_adh'] . ')'
            ]
        ];
        $members_list = [
            'FAKER_MAIL_12' => [
                'nom_adh' => 'Member email 2',
                'email_adh' => 'mail@domain.com',
                'fingerprint' => 'FAKER_MAIL_2'
            ]
        ];
        $count_before = 14;
        $count_after = 14;
        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        //test lang import
        $fields = ['nom_adh', 'ville_adh', 'fingerprint', 'pref_lang'];
        $file_name = 'test-import-lang-ko.csv';
        $flash_messages = [
            'error_detected' => [
                'File does not comply with requirements.',
                'Lang NO_EX does not exists!'
            ]
        ];
        $members_list = [
            'FAKER_LANG' => [
                'nom_adh' => 'Lang tests name',
                'ville_adh' => 'Lang tests city',
                'fingerprint' => 'FAKER_LANG',
                'pref_lang' => 'NO_EX' //non-existing lang
            ]
        ];
        $count_before = 14;
        $count_after = 14;
        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        $members_list['FAKER_LANG']['pref_lang'] = 'fr_FR'; //existing title
        $file_name = 'test-import-lang-ok.csv';
        $flash_messages = [
            'success_detected' => ["File '$file_name' has been successfully imported :)"]
        ];
        $count_after = 15;
        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        //create with default lang
        $members_list = [
            'FAKER_DEF_LANG' => [
                'nom_adh' => 'Member with default lang',
                'ville_adh' => 'Member with default lang city',
                'fingerprint' => 'FAKER_DEF_LANG',
                'pref_lang' => null //no specified lang
            ]
        ];
        $file_name = 'test-import-default-lang.csv';
        $flash_messages = [
            'success_detected' => ["File '$file_name' has been successfully imported :)"]
        ];
        $count_before = 15;
        $count_after = 16;
        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        //check created member
        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE);
        $select->where(['fingerprint' => 'FAKER_DEF_LANG']);
        $result = $this->zdb->execute($select)->current();
        $this->assertSame(
            $this->preferences->pref_lang,
            $result['pref_lang']
        );
    }

    /**
     * Get CSV import model
     *
     * @param array $fields Fields list
     *
     * @return \Galette\Entity\ImportModel
     */
    protected function getModel(array $fields): \Galette\Entity\ImportModel
    {
        $model = new \Galette\Entity\ImportModel();
        $this->assertTrue($model->remove($this->zdb));

        $this->assertInstanceOf(\Galette\Entity\ImportModel::class, $model->setFields($fields));
        $this->assertTrue($model->store($this->zdb));
        $this->assertTrue($model->load());
        return $model;
    }

    /**
     * Test dynamic translation has been added properly
     *
     * @param string $text_orig Original text
     * @param string $lang      Lang text has been added in
     *
     * @return void
     */
    protected function checkDynamicTranslation(string $text_orig, string $lang = 'fr_FR.utf8'): void
    {
        $langs = array_keys($this->i18n->getArrayList());
        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->columns([
            'text_locale',
            'text_nref',
            'text_trans'
        ]);
        $select->where(['text_orig' => $text_orig]);
        $results = $this->zdb->execute($select);
        $this->assertSame(count($langs), $results->count());

        foreach ($results as $result) {
            $this->assertTrue(in_array(str_replace('.utf8', '', $result['text_locale']), $langs));
            $this->assertSame(1, (int)$result['text_nref']);
            $this->assertSame(
                ($result['text_locale'] == 'en_US' ? $text_orig : ''),
                $result['text_trans']
            );
        }
    }

    /**
     * Test import with dynamic fields
     *
     * @return void
     */
    public function testImportDynamics(): void
    {

        $field_data = [
            'form_name'         => 'adh',
            'field_name'        => 'Dynamic text field',
            'field_perm'        => FieldsConfig::USER_WRITE,
            'field_type'        => DynamicField::TEXT,
            'field_required'    => 1,
            'field_repeat'      => 1
        ];

        $df = DynamicField::getFieldType($this->zdb, $field_data['field_type']);

        $stored = $df->store($field_data);
        $error_detected = $df->getErrors();
        $warning_detected = $df->getWarnings();
        $this->assertTrue(
            $stored,
            implode(
                ' ',
                $df->getErrors() + $df->getWarnings()
            )
        );
        $this->assertEmpty($error_detected, implode(' ', $df->getErrors()));
        $this->assertEmpty($warning_detected, implode(' ', $df->getWarnings()));
        //check if dynamic translation has been added
        $this->checkDynamicTranslation($field_data['field_name']);

        $select = $this->zdb->select(DynamicField::TABLE);
        $select->columns(array('num' => new \Laminas\Db\Sql\Expression('COUNT(1)')));
        $result = $this->zdb->execute($select)->current();
        $this->assertSame(1, (int)$result->num);

        $fields = ['nom_adh', 'ville_adh', 'dynfield_' . $df->getId(), 'fingerprint'];
        $file_name = 'test-import-atoum-dyn.csv';
        $flash_messages = [
            'success_detected' => ["File '$file_name' has been successfully imported :)"]
        ];
        $members_list = $this->getMemberData1();
        foreach ($members_list as $fingerprint => &$data) {
            $data['dynfield_' . $df->getId()] = 'Dynamic field value for ' . $data['fingerprint'];
        }
        $count_before = 0;
        $count_after = 10;

        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        //missing name
        //$fields does not change from previous
        $file_name = 'test-import-atoum-dyn-noname.csv';
        $flash_messages = [
            'error_detected' => [
                'File does not comply with requirements.',
                'Field nom_adh is required, but missing in row 3'
            ]
        ];
        $members_list = $this->getMemberData2NoName();
        foreach ($members_list as $fingerprint => &$data) {
            $data['dynfield_' . $df->getId()] = 'Dynamic field value for ' . $data['fingerprint'];
        }

        $count_before = 10;
        $count_after = 10;

        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        //missing required dynamic field
        //$fields does not change from previous
        $file_name = 'test-import-atoum-dyn-nodyn.csv';
        $flash_messages = [
            'error_detected' => [
                'File does not comply with requirements.',
                'Missing required field Dynamic text field'
            ]
        ];
        $members_list = $this->getMemberData2();
        $i = 0;
        foreach ($members_list as $fingerprint => &$data) {
            //two lines without required dynamic field.
            $data['dynfield_' . $df->getId()] = (($i == 2 || $i == 5) ? '' :
                'Dynamic field value for ' . $data['fingerprint']);
            ++$i;
        }

        $count_before = 10;
        $count_after = 10;

        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        //cleanup members and dynamic fields values
        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $this->zdb->execute($delete);
        $delete = $this->zdb->delete(\Galette\Entity\DynamicFieldsHandle::TABLE);
        $this->zdb->execute($delete);

        //new dynamic field, of type choice.
        $values = [
            'First value',
            'Second value',
            'Third value'
        ];
        $cfield_data = [
            'form_name'         => 'adh',
            'field_name'        => 'Dynamic choice field',
            'field_perm'        => FieldsConfig::USER_WRITE,
            'field_type'        => DynamicField::CHOICE,
            'field_required'    => 0,
            'field_repeat'      => 1,
            'fixed_values'      => implode("\n", $values)
        ];

        $cdf = DynamicField::getFieldType($this->zdb, $cfield_data['field_type']);

        $stored = $cdf->store($cfield_data);
        $error_detected = $cdf->getErrors();
        $warning_detected = $cdf->getWarnings();
        $this->assertTrue(
            $stored,
            implode(
                ' ',
                $cdf->getErrors() + $cdf->getWarnings()
            )
        );
        $this->assertEmpty($error_detected, implode(' ', $cdf->getErrors()));
        $this->assertEmpty($warning_detected, implode(' ', $cdf->getWarnings()));
        //check if dynamic translation has been added
        $this->checkDynamicTranslation($cfield_data['field_name']);

        $select = $this->zdb->select(DynamicField::TABLE);
        $select->columns(array('num' => new \Laminas\Db\Sql\Expression('COUNT(1)')));
        $result = $this->zdb->execute($select)->current();
        $this->assertSame(2, (int)$result->num);

        $this->assertSame($values, $cdf->getValues());

        $fields = ['nom_adh', 'ville_adh', 'dynfield_' . $cdf->getId(), 'fingerprint'];
        $file_name = 'test-import-atoum-dyn-cdyn.csv';
        $flash_messages = [
            'success_detected' => ["File '$file_name' has been successfully imported :)"]
        ];
        $members_list = $this->getMemberData1();
        foreach ($members_list as $fingerprint => &$data) {
            //two lines without required dynamic field.
            $data['dynfield_' . $cdf->getId()] = rand(0, 2);
        }

        $count_before = 0;
        $count_after = 10;

        $this->doImportFileTest(
            $fields,
            $file_name,
            $flash_messages,
            $members_list,
            $count_before,
            $count_after,
            $values
        );

        //cleanup members and dynamic fields values
        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $this->zdb->execute($delete);
        $delete = $this->zdb->delete(\Galette\Entity\DynamicFieldsHandle::TABLE);
        $this->zdb->execute($delete);
        //cleanup dynamic choices table
        $this->contents_table = $cdf->getFixedValuesTableName($cdf->getId());

        //new dynamic field, of type date.
        $cfield_data = [
            'form_name'         => 'adh',
            'field_name'        => 'Dynamic date field',
            'field_perm'        => FieldsConfig::USER_WRITE,
            'field_type'        => DynamicField::DATE,
            'field_required'    => 0,
            'field_repeat'      => 1
        ];

        $cdf = DynamicField::getFieldType($this->zdb, $cfield_data['field_type']);

        $stored = $cdf->store($cfield_data);
        $error_detected = $cdf->getErrors();
        $warning_detected = $cdf->getWarnings();
        $this->assertTrue(
            $stored,
            implode(
                ' ',
                $cdf->getErrors() + $cdf->getWarnings()
            )
        );
        $this->assertEmpty($error_detected, implode(' ', $cdf->getErrors()));
        $this->assertEmpty($warning_detected, implode(' ', $cdf->getWarnings()));
        //check if dynamic translation has been added
        $this->checkDynamicTranslation($cfield_data['field_name']);

        $select = $this->zdb->select(DynamicField::TABLE);
        $select->columns(array('num' => new \Laminas\Db\Sql\Expression('COUNT(1)')));
        $result = $this->zdb->execute($select)->current();
        $this->assertSame(3, (int)$result->num);


        $fields = ['nom_adh', 'ville_adh', 'dynfield_' . $cdf->getId(), 'fingerprint'];
        $file_name = 'test-import-atoum-cdyn-date.csv';
        $flash_messages = [
            'success_detected' => ["File '$file_name' has been successfully imported :)"]
        ];
        $members_list = $this->getMemberData1();
        foreach ($members_list as $fingerprint => &$data) {
            //two lines without required dynamic field.
            $data['dynfield_' . $cdf->getId()] = $data['date_crea_adh'];
        }

        $count_before = 0;
        $count_after = 10;

        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        //Test with a bad date
        //$fields does not change from previous
        $file_name = 'test-import-atoum-cdyn-baddate.csv';
        $flash_messages = [
            'error_detected' => [
                'File does not comply with requirements.',
                '- Wrong date format (Y-m-d) for Dynamic date field!'
            ]
        ];
        $members_list = $this->getMemberData2();
        $i = 0;
        foreach ($members_list as $fingerprint => &$data) {
            //two lines without required dynamic field.
            $data['dynfield_' . $cdf->getId()] = (($i == 2 || $i == 5) ? '20200513' : $data['date_crea_adh']);
            ++$i;
        }

        $count_before = 10;
        $count_after = 10;

        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);
    }

    /**
     * Test non existing file
     *
     * @return void
     */
    public function testNoFile(): void
    {
        $cin = new \Galette\IO\CsvIn($this->zdb);
        $this->assertSame(
            \Galette\IO\FileInterface::INVALID_FILE,
            $cin->import(
                $this->zdb,
                $this->preferences,
                $this->history,
                'non-existing-file.csv',
                $this->members_fields,
                $this->members_fields_cats,
                true
            )
        );
        $this->assertSame(
            ['File non-existing-file.csv cannot be open!'],
            $cin->getErrors()
        );
    }

    /**
     * Test empty file
     *
     * @return void
     */
    public function testEmptyFile(): void
    {
        $fields = ['nom_adh', 'ville_adh', 'fingerprint'];
        $file_name = 'test-empty-file.csv';
        $flash_messages = [
            'error_detected' => [
                'File does not comply with requirements.',
                'File is empty!'
            ]
        ];

        $members_list = [];
        $count_before = 0;
        $count_after = 0;

        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);
    }

    /**
     * Test missing columns
     *
     * @return void
     */
    public function testMissingColumn(): void
    {
        $csvin = new \Galette\IO\CsvIn($this->zdb);

        $fields = ['nom_adh', 'ville_adh', 'fingerprint'];
        $file_name = 'test-import-missing-column.csv';
        $this->getModel($fields);

        $contents = "\"" . implode("\";\"", $fields) . "\"\r\n";
        $fields = ['nom_adh', 'fingerprint'];
        $members_list = $this->getMemberData1();

        foreach ($members_list as $member) {
            $amember = [];
            foreach ($fields as $field) {
                $amember[$field] = $member[$field];
            }
            $contents .= "\"" . implode("\";\"", $amember) . "\"\r\n";
        }

        $path = GALETTE_CACHE_DIR . $file_name;
        $this->assertIsInt(file_put_contents($path, $contents));
        $_FILES['new_file'] = [
            'error' => UPLOAD_ERR_OK,
            'name'      => $file_name,
            'tmp_name'  => $path,
            'size'      => filesize($path)
        ];
        $this->assertTrue($csvin->store($_FILES['new_file'], true));
        $this->assertTrue(file_exists($csvin->getDestDir() . $csvin->getFileName()));

        $this->assertSame(
            \Galette\IO\FileInterface::INVALID_FILE,
            $csvin->import(
                $this->zdb,
                $this->preferences,
                $this->history,
                $file_name,
                $this->members_fields,
                $this->members_fields_cats,
                true
            )
        );
        $this->assertSame(
            ['Fields count mismatch... There should be 3 fields and there are 2 (row 1)'],
            $csvin->getErrors()
        );

        $csvin = new \Galette\IO\CsvIn($this->zdb);
        $file_name = 'test-import-missing-column-headers.csv';
        $fields = ['nom_adh', 'fingerprint'];
        $members_list = $this->getMemberData1();

        $contents = "\"" . implode("\";\"", $fields) . "\"\r\n";
        foreach ($members_list as $member) {
            $amember = [];
            foreach ($fields as $field) {
                $amember[$field] = $member[$field];
            }
            $contents .= "\"" . implode("\";\"", $amember) . "\"\r\n";
        }

        $path = GALETTE_CACHE_DIR . $file_name;
        $this->assertIsInt(file_put_contents($path, $contents));
        $_FILES['new_file'] = [
            'error' => UPLOAD_ERR_OK,
            'name'      => $file_name,
            'tmp_name'  => $path,
            'size'      => filesize($path)
        ];
        $this->assertTrue($csvin->store($_FILES['new_file'], true));
        $this->assertTrue(file_exists($csvin->getDestDir() . $csvin->getFileName()));

        $this->assertSame(
            \Galette\IO\FileInterface::INVALID_FILE,
            $csvin->import(
                $this->zdb,
                $this->preferences,
                $this->history,
                $file_name,
                $this->members_fields,
                $this->members_fields_cats,
                true
            )
        );
        $this->assertSame(
            ['Fields count mismatch... There should be 3 fields and there are 2 (row 0)'],
            $csvin->getErrors()
        );
    }

    /**
     * Get first set of member data
     *
     * @return array
     */
    private function getMemberData1(): array
    {
        return array(
            'FAKER_0' => array (
                'nom_adh' => 'Boucher',
                'prenom_adh' => 'Roland',
                'ville_adh' => 'Dumas',
                'cp_adh' => '61276',
                'adresse_adh' => '5, chemin de Meunier',
                'email_adh' => 'remy44@lopez.net',
                'login_adh' => 'jean36',
                'mdp_adh' => 'HM~OCSl[]UkZp%Y',
                'mdp_adh2' => 'HM~OCSl[]UkZp%Y',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => true,
                'bool_display_info' => false,
                'sexe_adh' => 1,
                'prof_adh' => 'Technicien géomètre',
                'titre_adh' => null,
                'ddn_adh' => '1914-03-22',
                'lieu_naissance' => 'Laurent-sur-Guyot',
                'pseudo_adh' => 'tgonzalez',
                'pays_adh' => null,
                'tel_adh' => '+33 8 93 53 99 52',
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2020-03-09',
                'pref_lang' => 'br',
                'fingerprint' => 'FAKER_0',
            ),
            'FAKER_1' =>  array (
                'nom_adh' => 'Lefebvre',
                'prenom_adh' => 'François',
                'ville_adh' => 'Laine',
                'cp_adh' => '53977',
                'adresse_adh' => '311, rue de Costa',
                'email_adh' => 'astrid64@masse.fr',
                'login_adh' => 'olivier.pierre',
                'mdp_adh' => '.4y/J>yN_QUh7Bw@NW>)',
                'mdp_adh2' => '.4y/J>yN_QUh7Bw@NW>)',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => false,
                'sexe_adh' => 2,
                'prof_adh' => 'Conseiller relooking',
                'titre_adh' => null,
                'ddn_adh' => '1989-10-31',
                'lieu_naissance' => 'Collet',
                'pseudo_adh' => 'agnes.evrard',
                'pays_adh' => null,
                'tel_adh' => '0288284193',
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2019-11-29',
                'pref_lang' => 'oc',
                'fingerprint' => 'FAKER_1',
            ),
            'FAKER_2' =>  array (
                'nom_adh' => 'Lemaire',
                'prenom_adh' => 'Georges',
                'ville_adh' => 'Teixeira-sur-Mer',
                'cp_adh' => '40141',
                'adresse_adh' => 'place Guillaume',
                'email_adh' => 'lefort.vincent@club-internet.fr',
                'login_adh' => 'josette46',
                'mdp_adh' => '(IqBaAIR',
                'mdp_adh2' => '(IqBaAIR',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 0,
                'prof_adh' => 'Assistant logistique',
                'titre_adh' => null,
                'ddn_adh' => '1935-09-07',
                'lieu_naissance' => 'Ponsboeuf',
                'pseudo_adh' => 'fgay',
                'pays_adh' => null,
                'tel_adh' => '+33 7 45 45 19 81',
                'activite_adh' => true,
                'id_statut' => 8,
                'date_crea_adh' => '2019-02-03',
                'pref_lang' => 'uk',
                'fingerprint' => 'FAKER_2',
            ),
            'FAKER_3' =>  array (
                'nom_adh' => 'Paul',
                'prenom_adh' => 'Thibaut',
                'ville_adh' => 'Mallet-sur-Prevost',
                'cp_adh' => '50537',
                'adresse_adh' => '246, boulevard Daniel Mendes',
                'email_adh' => 'ihamel@pinto.fr',
                'login_adh' => 'josephine.fabre',
                'mdp_adh' => '`2LrQcb9Utgm=Y\\S$',
                'mdp_adh2' => '`2LrQcb9Utgm=Y\\S$',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 0,
                'prof_adh' => 'Aide à domicile',
                'titre_adh' => null,
                'ddn_adh' => '1961-09-17',
                'lieu_naissance' => 'Gomez',
                'pseudo_adh' => 'chauvin.guillaume',
                'pays_adh' => 'Hong Kong',
                'tel_adh' => '+33 5 48 57 32 28',
                'activite_adh' => true,
                'id_statut' => 1,
                'date_crea_adh' => '2017-11-20',
                'pref_lang' => 'nb_NO',
                'fingerprint' => 'FAKER_3',
                'societe_adh' => 'Jacques',
                'is_company' => true,
            ),
            'FAKER_4' =>  array (
                'nom_adh' => 'Pascal',
                'prenom_adh' => 'Isaac',
                'ville_adh' => 'Jourdanboeuf',
                'cp_adh' => '93966',
                'adresse_adh' => '5, boulevard de Boucher',
                'email_adh' => 'valerie.becker@gmail.com',
                'login_adh' => 'lucie08',
                'mdp_adh' => '|%+wtMW{l',
                'mdp_adh2' => '|%+wtMW{l',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 1,
                'prof_adh' => 'Bruiteur',
                'titre_adh' => null,
                'ddn_adh' => '1953-12-11',
                'lieu_naissance' => 'Foucher',
                'pseudo_adh' => 'sauvage.dorothee',
                'pays_adh' => 'Bangladesh',
                'tel_adh' => '+33 4 75 14 66 56',
                'activite_adh' => false,
                'id_statut' => 9,
                'date_crea_adh' => '2018-08-16',
                'pref_lang' => 'en_US',
                'fingerprint' => 'FAKER_4',
            ),
            'FAKER_5' =>  array (
                'nom_adh' => 'Morvan',
                'prenom_adh' => 'Joseph',
                'ville_adh' => 'Noel',
                'cp_adh' => '05069',
                'adresse_adh' => 'place de Barthelemy',
                'email_adh' => 'claunay@tele2.fr',
                'login_adh' => 'marthe.hoarau',
                'mdp_adh' => '\'C?}vJAU>:-iE',
                'mdp_adh2' => '\'C?}vJAU>:-iE',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 1,
                'prof_adh' => 'Opérateur du son',
                'titre_adh' => null,
                'ddn_adh' => '1938-05-11',
                'lieu_naissance' => 'Beguedan',
                'pseudo_adh' => 'andre.guillou',
                'pays_adh' => null,
                'tel_adh' => '09 26 70 06 55',
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2018-09-28',
                'pref_lang' => 'ca',
                'fingerprint' => 'FAKER_5',
            ),
            'FAKER_6' =>  array (
                'nom_adh' => 'Lebreton',
                'prenom_adh' => 'Emmanuelle',
                'ville_adh' => 'Lefevre',
                'cp_adh' => '29888',
                'adresse_adh' => '98, rue Moulin',
                'email_adh' => 'zacharie77@ruiz.fr',
                'login_adh' => 'marianne.collin',
                'mdp_adh' => '=jG{wyE',
                'mdp_adh2' => '=jG{wyE',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 1,
                'prof_adh' => 'Galeriste',
                'titre_adh' => null,
                'ddn_adh' => '2001-02-01',
                'lieu_naissance' => 'Berthelot',
                'pseudo_adh' => 'ferreira.rene',
                'pays_adh' => 'Tuvalu',
                'tel_adh' => '+33 (0)7 47 56 89 70',
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2018-01-13',
                'pref_lang' => 'es',
                'fingerprint' => 'FAKER_6',
            ),
            'FAKER_7' =>  array (
                'nom_adh' => 'Maurice',
                'prenom_adh' => 'Capucine',
                'ville_adh' => 'Renaultdan',
                'cp_adh' => '59 348',
                'adresse_adh' => '56, avenue Grenier',
                'email_adh' => 'didier.emmanuel@tiscali.fr',
                'login_adh' => 'william.herve',
                'mdp_adh' => '#7yUz#qToZ\'',
                'mdp_adh2' => '#7yUz#qToZ\'',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 1,
                'prof_adh' => 'Cintrier-machiniste',
                'titre_adh' => null,
                'ddn_adh' => '1984-04-17',
                'lieu_naissance' => 'Rolland',
                'pseudo_adh' => 'roger27',
                'pays_adh' => 'Antilles néerlandaises',
                'tel_adh' => '0922523762',
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2020-02-13',
                'pref_lang' => 'br',
                'fingerprint' => 'FAKER_7',
                'societe_adh' => 'Mace',
                'is_company' => true,
            ),
            'FAKER_8' =>  array (
                'nom_adh' => 'Hubert',
                'prenom_adh' => 'Lucy',
                'ville_adh' => 'Lagarde',
                'cp_adh' => '22 829',
                'adresse_adh' => '3, rue Pénélope Marie',
                'email_adh' => 'zoe02@morvan.com',
                'login_adh' => 'bernard.agathe',
                'mdp_adh' => '@9di}eJyc"0s_d(',
                'mdp_adh2' => '@9di}eJyc"0s_d(',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 2,
                'prof_adh' => 'Facteur',
                'titre_adh' => null,
                'ddn_adh' => '2008-01-13',
                'lieu_naissance' => 'Ribeiro',
                'pseudo_adh' => 'julien.isabelle',
                'pays_adh' => 'Mexique',
                'tel_adh' => '0809527977',
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2019-06-26',
                'pref_lang' => 'de_DE',
                'fingerprint' => 'FAKER_8',
            ),
            'FAKER_9' =>  array (
                'nom_adh' => 'Goncalves',
                'prenom_adh' => 'Corinne',
                'ville_adh' => 'LesageVille',
                'cp_adh' => '15728',
                'adresse_adh' => '18, rue de Pinto',
                'email_adh' => 'julien.clement@dbmail.com',
                'login_adh' => 'xavier.nicolas',
                'mdp_adh' => '<W0XdOj2Gp|@;W}gWh]',
                'mdp_adh2' => '<W0XdOj2Gp|@;W}gWh]',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 1,
                'prof_adh' => 'Eleveur de volailles',
                'titre_adh' => null,
                'ddn_adh' => '2013-09-12',
                'lieu_naissance' => 'Breton',
                'pseudo_adh' => 'louis.pruvost',
                'pays_adh' => null,
                'tel_adh' => '+33 (0)6 80 24 46 58',
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2020-08-09',
                'pref_lang' => 'br',
                'fingerprint' => 'FAKER_9',
            )
        );
    }

    /**
     * Get second set of member data
     * two lines without name.
     *
     * @return array
     */
    private function getMemberData2(): array
    {
        return array (
            'FAKER_0' => array (
                'nom_adh' => 'Goncalves',
                'prenom_adh' => 'Margot',
                'ville_adh' => 'Alves',
                'cp_adh' => '76254',
                'adresse_adh' => '43, impasse Maurice Imbert',
                'email_adh' => 'guillou.richard@yahoo.fr',
                'login_adh' => 'suzanne.mathieu',
                'mdp_adh' => 'Thihk2z0',
                'mdp_adh2' => 'Thihk2z0',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 2,
                'prof_adh' => 'Cueilleur de cerises',
                'titre_adh' => null,
                'ddn_adh' => '2020-04-24',
                'lieu_naissance' => 'Poulain-les-Bains',
                'pseudo_adh' => 'olivier.roux',
                'pays_adh' => 'République Dominicaine',
                'tel_adh' => '08 95 04 73 14',
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2020-07-31',
                'pref_lang' => 'ca',
                'fingerprint' => 'FAKER_0',
            ),
            'FAKER_1' => array (
                'nom_adh' => 'Da Silva',
                'prenom_adh' => 'Augustin',
                'ville_adh' => 'Perrin-sur-Masson',
                'cp_adh' => '31519',
                'adresse_adh' => '154, place Boulay',
                'email_adh' => 'marc60@moreno.fr',
                'login_adh' => 'hoarau.maryse',
                'mdp_adh' => '\\9Si%r/FAmz.HE4!{Q\\',
                'mdp_adh2' => '\\9Si%r/FAmz.HE4!{Q\\',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 2,
                'prof_adh' => 'Séismologue',
                'titre_adh' => null,
                'ddn_adh' => '1988-06-26',
                'lieu_naissance' => 'Martel',
                'pseudo_adh' => 'hchevalier',
                'pays_adh' => 'Kiribati',
                'tel_adh' => '04 55 49 80 92',
                'activite_adh' => true,
                'id_statut' => 1,
                'date_crea_adh' => '2020-06-02',
                'pref_lang' => 'fr_FR',
                'fingerprint' => 'FAKER_1',
            ),
            'FAKER_2' => array (
                'nom_adh' => 'Doe',
                'prenom_adh' => 'Laetitia',
                'ville_adh' => 'SimonBourg',
                'cp_adh' => '90351',
                'adresse_adh' => '147, chemin de Chauvet',
                'email_adh' => 'jean.joseph@pinto.fr',
                'login_adh' => 'marianne.bourgeois',
                'mdp_adh' => '[oT:"ExE',
                'mdp_adh2' => '[oT:"ExE',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 0,
                'prof_adh' => 'Porteur de hottes',
                'titre_adh' => null,
                'ddn_adh' => '2010-03-13',
                'lieu_naissance' => 'Gallet',
                'pseudo_adh' => 'abarre',
                'pays_adh' => 'Kirghizistan',
                'tel_adh' => '07 47 63 11 31',
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2020-10-28',
                'pref_lang' => 'ar',
                'fingerprint' => 'FAKER_2',
            ),
            'FAKER_3' => array (
                'nom_adh' => 'Cordier',
                'prenom_adh' => 'Olivier',
                'ville_adh' => 'Lacroixboeuf',
                'cp_adh' => '58 787',
                'adresse_adh' => '77, place Gilbert Perrier',
                'email_adh' => 'adelaide07@yahoo.fr',
                'login_adh' => 'riou.sebastien',
                'mdp_adh' => '%"OC/UniE46',
                'mdp_adh2' => '%"OC/UniE46',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => false,
                'sexe_adh' => 2,
                'prof_adh' => 'Oenologue',
                'titre_adh' => null,
                'ddn_adh' => '2010-10-08',
                'lieu_naissance' => 'Leger',
                'pseudo_adh' => 'frederique.bernier',
                'pays_adh' => null,
                'tel_adh' => '+33 2 50 03 01 12',
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2020-08-14',
                'pref_lang' => 'ar',
                'fingerprint' => 'FAKER_3',
            ),
            'FAKER_4' => array (
                'nom_adh' => 'Robert',
                'prenom_adh' => 'Grégoire',
                'ville_adh' => 'Delannoy-sur-Mer',
                'cp_adh' => '41185',
                'adresse_adh' => '15, boulevard de Pierre',
                'email_adh' => 'normand.matthieu@orange.fr',
                'login_adh' => 'guilbert.louis',
                'mdp_adh' => 'y(,HodJF*j',
                'mdp_adh2' => 'y(,HodJF*j',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 2,
                'prof_adh' => 'Mannequin détail',
                'titre_adh' => null,
                'ddn_adh' => '1974-05-14',
                'lieu_naissance' => 'Barbe-sur-Laurent',
                'pseudo_adh' => 'stoussaint',
                'pays_adh' => 'Îles Mineures Éloignées des États-Unis',
                'tel_adh' => '+33 (0)1 30 50 01 54',
                'activite_adh' => true,
                'id_statut' => 3,
                'date_crea_adh' => '2018-12-05',
                'pref_lang' => 'it_IT',
                'fingerprint' => 'FAKER_4',
                'societe_adh' => 'Chretien Martineau S.A.',
                'is_company' => true,
            ),
            'FAKER_5' =>  array (
                'nom_adh' => 'Doe',
                'prenom_adh' => 'Charles',
                'ville_adh' => 'Charpentier-sur-Lebrun',
                'cp_adh' => '99129',
                'adresse_adh' => '817, chemin de Bonnin',
                'email_adh' => 'guillou.augustin@live.com',
                'login_adh' => 'dominique80',
                'mdp_adh' => '~g??E0HE$A>2"e*C7+Kw',
                'mdp_adh2' => '~g??E0HE$A>2"e*C7+Kw',
                'bool_admin_adh' => true,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 0,
                'prof_adh' => 'Commandant de police',
                'titre_adh' => null,
                'ddn_adh' => '2007-03-26',
                'lieu_naissance' => 'Boutin',
                'pseudo_adh' => 'virginie.jacquet',
                'pays_adh' => null,
                'tel_adh' => '0393209420',
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2018-02-17',
                'pref_lang' => 'fr_FR',
                'fingerprint' => 'FAKER_5',
            ),
            'FAKER_6' => array (
                'nom_adh' => 'Thierry',
                'prenom_adh' => 'Louis',
                'ville_adh' => 'Henry',
                'cp_adh' => '98 144',
                'adresse_adh' => '383, avenue Éléonore Bouchet',
                'email_adh' => 'bernard.elodie@orange.fr',
                'login_adh' => 'ubreton',
                'mdp_adh' => 'lTBT@,hsE`co?C2=',
                'mdp_adh2' => 'lTBT@,hsE`co?C2=',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => false,
                'sexe_adh' => 2,
                'prof_adh' => 'Endocrinologue',
                'titre_adh' => null,
                'ddn_adh' => '1994-07-19',
                'lieu_naissance' => 'Pagesdan',
                'pseudo_adh' => 'diallo.sebastien',
                'pays_adh' => null,
                'tel_adh' => '+33 5 72 28 24 81',
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2020-03-16',
                'pref_lang' => 'en_US',
                'fingerprint' => 'FAKER_6',
            ),
            'FAKER_7' =>  array (
                'nom_adh' => 'Delattre',
                'prenom_adh' => 'Susanne',
                'ville_adh' => 'Roche-les-Bains',
                'cp_adh' => '37 104',
                'adresse_adh' => '44, rue Suzanne Guilbert',
                'email_adh' => 'tmartel@wanadoo.fr',
                'login_adh' => 'lebreton.alexandre',
                'mdp_adh' => '{(3mCWC7[YL]n',
                'mdp_adh2' => '{(3mCWC7[YL]n',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 0,
                'prof_adh' => 'Gérant d\'hôtel',
                'titre_adh' => null,
                'ddn_adh' => '1914-05-16',
                'lieu_naissance' => 'Traore',
                'pseudo_adh' => 'helene59',
                'pays_adh' => null,
                'tel_adh' => '0383453389',
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2020-02-03',
                'pref_lang' => 'oc',
                'fingerprint' => 'FAKER_7',
            ),
            'FAKER_8' =>  array (
                'nom_adh' => 'Peltier',
                'prenom_adh' => 'Inès',
                'ville_adh' => 'Thierry-sur-Carre',
                'cp_adh' => '80690',
                'adresse_adh' => '43, impasse Texier',
                'email_adh' => 'qdubois@mendes.fr',
                'login_adh' => 'julie.carlier',
                'mdp_adh' => '.ATai-E6%LIxE{',
                'mdp_adh2' => '.ATai-E6%LIxE{',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 1,
                'prof_adh' => 'Gynécologue',
                'titre_adh' => null,
                'ddn_adh' => '1988-05-29',
                'lieu_naissance' => 'Dijoux-sur-Michaud',
                'pseudo_adh' => 'wpierre',
                'pays_adh' => null,
                'tel_adh' => '01 32 14 47 74',
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2020-03-28',
                'pref_lang' => 'ar',
                'fingerprint' => 'FAKER_8',
            ),
            'FAKER_9' => array (
                'nom_adh' => 'Marchand',
                'prenom_adh' => 'Audrey',
                'ville_adh' => 'Lenoirdan',
                'cp_adh' => '06494',
                'adresse_adh' => '438, place de Carre',
                'email_adh' => 'luc42@yahoo.fr',
                'login_adh' => 'margot.bousquet',
                'mdp_adh' => 'FH,q5udclwM(',
                'mdp_adh2' => 'FH,q5udclwM(',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 1,
                'prof_adh' => 'Convoyeur garde',
                'titre_adh' => null,
                'ddn_adh' => '1977-09-02',
                'lieu_naissance' => 'Arnaud-sur-Antoine',
                'pseudo_adh' => 'gerard66',
                'pays_adh' => null,
                'tel_adh' => '+33 1 46 04 81 87',
                'activite_adh' => true,
                'id_statut' => 5,
                'date_crea_adh' => '2019-05-16',
                'pref_lang' => 'fr_FR',
                'fingerprint' => 'FAKER_9',
            )
        );
    }

    /**
     * Get second set of member data but two lines without name.
     *
     * @return array
     */
    private function getMemberData2NoName(): array
    {
        $data = $this->getMemberData2();
        $data['FAKER_2']['nom_adh'] = '';
        $data['FAKER_5']['nom_adh'] = '';
        return $data;
    }
}
