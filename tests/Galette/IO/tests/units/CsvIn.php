<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * CsvIn tests
 *
 * PHP version 5
 *
 * Copyright © 2020 The Galette Team
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
 * @category  Core
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2020-05-11
 */

namespace Galette\IO\test\units;

use atoum;
use Galette\Entity\Adherent;
use Galette\DynamicFields\DynamicField;

/**
 * CsvIn tests class
 *
 * @category  Core
 * @name      CsvIn
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-05-11
 */
class CsvIn extends atoum
{
    private $zdb;
    private $i18n;
    private $preferences;
    private $session;
    private $login;
    private $view;
    private $history;
    private $members_fields;
    private $members_form_fields;
    private $members_fields_cats;
    private $flash;
    private $flash_data;
    private $container;
    private $request;
    private $response;
    private $mocked_router;
    private $contents_table = null;

    /**
     * Set up tests
     *
     * @param string $testMethod Calling method
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
    {
        $this->contents_table = null;
        $this->mocked_router = new \mock\Slim\Router();
        $this->calling($this->mocked_router)->pathFor = function ($name, $params) {
            return $name;
        };
        $this->zdb = new \Galette\Core\Db();
        $this->i18n = new \Galette\Core\I18n(
            \Galette\Core\I18n::DEFAULT_LANG
        );
        $this->preferences = new \Galette\Core\Preferences(
            $this->zdb
        );
        $this->session = new \RKA\Session();
        $this->login = new \Galette\Core\Login($this->zdb, $this->i18n, $this->session);
        $this->history = new \Galette\Core\History($this->zdb, $this->login, $this->preferences);
        $flash_data = [];
        $this->flash_data = &$flash_data;
        $this->flash = new \Slim\Flash\Messages($flash_data);

        global $zdb, $i18n, $login, $hist;
        $zdb = $this->zdb;
        $i18n = $this->i18n;
        $login = $this->login;
        $hist = $this->history;

        $app = new \Slim\App(['router' => $this->mocked_router, 'flash' => $this->flash]);
        $container = $app->getContainer();
        /*$this->view = new \mock\Slim\Views\Smarty(
            rtrim(GALETTE_ROOT . GALETTE_TPL_SUBDIR, DIRECTORY_SEPARATOR),
            [
                'cacheDir' => rtrim(GALETTE_CACHE_DIR, DIRECTORY_SEPARATOR),
                'compileDir' => rtrim(GALETTE_COMPILE_DIR, DIRECTORY_SEPARATOR),
                'pluginsDir' => [
                    GALETTE_ROOT . 'includes/smarty_plugins'
                ]
            ]
        );
        $this->calling($this->view)->render = function ($response) {
            $response->getBody()->write('Atoum view rendered');
            return $response;
        };

        $this->view->addSlimPlugins($container->get('router'), '/');
        //$container['view'] = $this->view;*/
        $container['view'] = null;
        $container['zdb'] = $zdb;
        $container['login'] = $this->login;
        $container['session'] = $this->session;
        $container['preferences'] = $this->preferences;
        $container['logo'] = null;
        $container['print_logo'] = null;
        $container['plugins'] = null;
        $container['history'] = $this->history;
        $container['i18n'] = null;
        $container['fields_config'] = null;
        $container['lists_config'] = null;
        $container['l10n'] = null;
        include_once GALETTE_ROOT . 'includes/fields_defs/members_fields.php';
        $this->members_fields = $members_fields;
        $container['members_fields'] = $this->members_fields;
        $members_form_fields = $members_fields;
        foreach ($members_form_fields as $k => $field) {
            if ($field['position'] == -1) {
                unset($members_form_fields[$k]);
            }
        }
        $this->members_form_fields = $members_form_fields;
        $container['members_form_fields'] = $this->members_form_fields;
        include_once GALETTE_ROOT . 'includes/fields_defs/members_fields_cats.php';
        $this->members_fields_cats = $members_fields_cats;
        $container['members_fields_cats'] = $this->members_fields_cats;
        $this->container = $container;
        $this->request = $container->get('request');
        $this->response = $container->get('response');
    }

    /**
     * Tear down tests
     *
     * @param string $testMethod Calling method
     *
     * @return void
     */
    public function afterTestMethod($testMethod)
    {
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
     * @param array   $flash_messages Excpeted flash messages from doImport route
     * @param airay   $members_list   List of faked members data
     * @param integer $count_before   Count before insertions. Defaults to 0 if null.
     * @param integer $count_after    Count after insertions. Default to $count_before + count $members_list
     * @param array   $values         Textual values for dynamic choices fields
     *
     * @return void
     */
    private function doImportFileTest(
        array $fields,
        $file_name,
        array $flash_messages,
        array $members_list,
        $count_before = null,
        $count_after = null,
        array $values = []
    ) {
        if ($count_before === null) {
            $count_before = 0;
        }
        if ($count_after === null) {
            $count_after = $count_before + count($members_list);
        }

        $members = new \Galette\Repository\Members();
        $list = $members->getList();
        $this->integer($list->count())->isIdenticalTo(
            $count_before,
            print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1), true)
        );

        $model = $this->getModel($fields);

        //get csv model file to add data in
        $controller = new \Galette\Controllers\CsvController($this->container);
        $response = $controller->getImportModel($this->request, $this->response);
        $csvin = new \galette\IO\CsvIn($this->zdb);

        $this->integer($response->getStatusCode())->isIdenticalTo(200);
        $this->array($response->getHeaders())
            ->array['Content-Type']->isIdenticalTo(['text/csv'])
            ->array['Content-Disposition']->isIdenticalTo(['attachment;filename="galette_import_model.csv"']);

        $csvfile_model = $response->getBody()->__toString();
        $this->string($csvfile_model)
             ->isIdenticalTo("\"" . implode("\";\"", $fields) . "\"\r\n");

        $fakedata = new \Galette\Util\FakeData($this->zdb, $this->i18n);
        $contents = $csvfile_model;
        foreach ($members_list as $member) {
            $amember = [];
            foreach ($fields as $field) {
                $amember[$field] = $member[$field];
            }
            $contents .= "\"" . implode("\";\"", $amember) . "\"\r\n";
        }

        $path = GALETTE_CACHE_DIR . $file_name;
        $this->integer(file_put_contents($path, $contents));
        $_FILES['new_file'] = [
            'error' => UPLOAD_ERR_OK,
            'name'      => $file_name,
            'tmp_name'  => $path,
            'size'      => filesize($path)
        ];
        $this->boolean($csvin->store($_FILES['new_file'], true))->isTrue();
        $this->boolean(file_exists($csvin->getDestDir() . $csvin->getFileName()))->isTrue();

        $post = [
            'import_file'   => $file_name
        ];

        $request = clone $this->request;
        $request = $request->withParsedBody($post);

        $response = $controller->doImports($request, $this->response);
        $this->integer($response->getStatusCode())->isIdenticalTo(301);
        $this->array($this->flash_data['slimFlash'])->isIdenticalTo($flash_messages);
        $this->flash->clearMessages();

        $members = new \Galette\Repository\Members();
        $list = $members->getList();
        $this->integer($list->count())->isIdenticalTo($count_after);

        if ($count_before != $count_after) {
            foreach ($list as $member) {
                $created = $members_list[$member->fingerprint];
                foreach ($fields as $field) {
                    if (property_exists($member, $field)) {
                        $this->variable($member->$field)->isEqualTo($created[$field]);
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

                            $dfield = $adh->getDynamicFields()->getValues($matches[1]);
                            if (isset($dfield[0]['text_val'])) {
                                //choice, add textual value
                                $expected[0]['text_val'] = $values[$created[$field]];
                            }

                            $this->array($adh->getDynamicFields()->getValues($matches[1]))->isEqualTo($expected);
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
    public function testImport()
    {
        $fakedata = new \Galette\Util\FakeData($this->zdb, $this->i18n);

        $fields = ['nom_adh', 'ville_adh', 'bool_exempt_adh', 'fingerprint'];
        $file_name = 'test-import-atoum.csv';
        $flash_messages = [
            'success_detected' => ["File '$file_name' has been successfully imported :)"]
        ];
        $members_list = [];
        for ($i = 0; $i < 10; ++$i) {
            $data = $fakedata->fakeMember();
            $data['nom_adh'] = str_replace('"', '""', $data['nom_adh']);
            $data['ville_adh'] = str_replace('"', '""', $data['ville_adh']);
            $data['fingerprint'] = 'FAKER_' . $i;
            $members_list[$data['fingerprint']] = $data;
        }
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

        $members_list = [];
        for ($i = 0; $i < 10; ++$i) {
            $data = $fakedata->fakeMember();
            //two lines without name.
            $data['nom_adh'] = (($i == 2 || $i == 5) ? '' : str_replace('"', '""', $data['nom_adh']));
            $data['ville_adh'] = str_replace('"', '""', $data['ville_adh']);
            $data['fingerprint'] = 'FAKER_' . $i;
            $members_list[$data['fingerprint']] = $data;
        }
        $count_before = 10;
        $count_after = 10;

        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);
    }

    /**
     * Get CSV import model
     *
     * @param array $fields Fields list
     *
     * @return \Galette\Entity\ImportModel
     */
    protected function getModel($fields): \Galette\Entity\ImportModel
    {
        $model = new \Galette\Entity\ImportModel();
        $this->boolean($model->remove($this->zdb))->isTrue();

        $this->object($model->setFields($fields))->isInstanceOf('Galette\Entity\ImportModel');
        $this->boolean($model->store($this->zdb))->isTrue();
        $this->boolean($model->load())->isTrue();
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
    protected function checkDynamicTranslation($text_orig, $lang = 'fr_FR.utf8')
    {
        $langs = array_keys($this->i18n->langs);
        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->columns([
            'text_locale',
            'text_nref',
            'text_trans'
        ]);
        $select->where(['text_orig' => $text_orig]);
        $results = $this->zdb->execute($select);
        $this->integer($results->count())->isIdenticalTo(count($langs));

        foreach ($results as $result) {
            $this->boolean(in_array(str_replace('.utf8', '', $result['text_locale']), $langs))->isTrue();
            $this->integer((int)$result['text_nref'])->isIdenticalTo(1);
            $this->string($result['text_trans'])->isIdenticalTo(
                ($result['text_locale'] == 'fr_FR.utf8' ? $text_orig : '')
            );
        }
    }

    /**
     * Test import with dynamic fields
     *
     * @return void
     */
    public function testImportDynamics()
    {

        $field_data = [
            'form'              => 'adh',
            'field_name'        => 'Dynamic text field',
            'field_perm'        => DynamicField::PERM_USER_WRITE,
            'field_type'        => DynamicField::TEXT,
            'field_required'    => 1,
            'field_repeat'      => 1
        ];

        $df = DynamicField::getFieldType($this->zdb, $field_data['field_type']);

        $stored = $df->store($field_data);
        $error_detected = $df->getErrors();
        $warning_detected = $df->getWarnings();
        $this->boolean($stored)->isTrue(
            implode(
                ' ',
                $df->getErrors() + $df->getWarnings()
            )
        );
        $this->array($error_detected)->isEmpty(implode(' ', $df->getErrors()));
        $this->array($warning_detected)->isEmpty(implode(' ', $df->getWarnings()));
        //check if dynamic translation has been added
        $this->checkDynamicTranslation($field_data['field_name']);

        $select = $this->zdb->select(DynamicField::TABLE);
        $select->columns(array('num' => new \Laminas\Db\Sql\Expression('COUNT(1)')));
        $result = $this->zdb->execute($select)->current();
        $this->integer((int)$result->num)->isIdenticalTo(1);

        $fakedata = new \Galette\Util\FakeData($this->zdb, $this->i18n);

        $fields = ['nom_adh', 'ville_adh', 'dynfield_' . $df->getId(), 'fingerprint'];
        $file_name = 'test-import-atoum-dyn.csv';
        $flash_messages = [
            'success_detected' => ["File '$file_name' has been successfully imported :)"]
        ];
        $members_list = [];
        for ($i = 0; $i < 10; ++$i) {
            $data = $fakedata->fakeMember();
            $data['nom_adh'] = str_replace('"', '""', $data['nom_adh']);
            $data['ville_adh'] = str_replace('"', '""', $data['ville_adh']);
            $data['fingerprint'] = 'FAKER_' . $i;
            $data['dynfield_' . $df->getId()] = 'Dynamic field value for ' . $data['fingerprint'];
            $members_list[$data['fingerprint']] = $data;
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
        $members_list = [];
        for ($i = 0; $i < 10; ++$i) {
            $data = $fakedata->fakeMember();
            //two lines without name.
            $data['nom_adh'] = (($i == 2 || $i == 5) ? '' : str_replace('"', '""', $data['nom_adh']));
            $data['ville_adh'] = str_replace('"', '""', $data['ville_adh']);
            $data['fingerprint'] = 'FAKER_' . $i;
            $data['dynfield_' . $df->getId()] = 'Dynamic field value for ' . $data['fingerprint'];
            $members_list[$data['fingerprint']] = $data;
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
        $members_list = [];
        for ($i = 0; $i < 10; ++$i) {
            $data = $fakedata->fakeMember();
            $data['nom_adh'] = str_replace('"', '""', $data['nom_adh']);
            $data['ville_adh'] = str_replace('"', '""', $data['ville_adh']);
            $data['fingerprint'] = 'FAKER_' . $i;
            //two lines without required dynamic field.
            $data['dynfield_' . $df->getId()] = (($i == 2 || $i == 5) ? '' :
                'Dynamic field value for ' . $data['fingerprint']);
            $members_list[$data['fingerprint']] = $data;
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
            'form'              => 'adh',
            'field_name'        => 'Dynamic choice field',
            'field_perm'        => DynamicField::PERM_USER_WRITE,
            'field_type'        => DynamicField::CHOICE,
            'field_required'    => 0,
            'field_repeat'      => 1,
            'fixed_values'      => implode("\n", $values)
        ];

        $cdf = DynamicField::getFieldType($this->zdb, $cfield_data['field_type']);

        $stored = $cdf->store($cfield_data);
        $error_detected = $cdf->getErrors();
        $warning_detected = $cdf->getWarnings();
        $this->boolean($stored)->isTrue(
            implode(
                ' ',
                $cdf->getErrors() + $cdf->getWarnings()
            )
        );
        $this->array($error_detected)->isEmpty(implode(' ', $cdf->getErrors()));
        $this->array($warning_detected)->isEmpty(implode(' ', $cdf->getWarnings()));
        //check if dynamic translation has been added
        $this->checkDynamicTranslation($cfield_data['field_name']);

        $select = $this->zdb->select(DynamicField::TABLE);
        $select->columns(array('num' => new \Laminas\Db\Sql\Expression('COUNT(1)')));
        $result = $this->zdb->execute($select)->current();
        $this->integer((int)$result->num)->isIdenticalTo(2);

        $this->array($cdf->getValues())->isIdenticalTo($values);

        $fields = ['nom_adh', 'ville_adh', 'dynfield_' . $cdf->getId(), 'fingerprint'];
        $file_name = 'test-import-atoum-dyn-cdyn.csv';
        $flash_messages = [
            'success_detected' => ["File '$file_name' has been successfully imported :)"]
        ];
        $members_list = [];
        for ($i = 0; $i < 10; ++$i) {
            $data = $fakedata->fakeMember();
            $data['nom_adh'] = str_replace('"', '""', $data['nom_adh']);
            $data['ville_adh'] = str_replace('"', '""', $data['ville_adh']);
            $data['fingerprint'] = 'FAKER_' . $i;
            $faker = $fakedata->getFaker();
            $data['dynfield_' . $cdf->getId()] = $faker->numberBetween(0, 2);
            $members_list[$data['fingerprint']] = $data;
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
            'form'              => 'adh',
            'field_name'        => 'Dynamic date field',
            'field_perm'        => DynamicField::PERM_USER_WRITE,
            'field_type'        => DynamicField::DATE,
            'field_required'    => 0,
            'field_repeat'      => 1
        ];

        $cdf = DynamicField::getFieldType($this->zdb, $cfield_data['field_type']);

        $stored = $cdf->store($cfield_data);
        $error_detected = $cdf->getErrors();
        $warning_detected = $cdf->getWarnings();
        $this->boolean($stored)->isTrue(
            implode(
                ' ',
                $cdf->getErrors() + $cdf->getWarnings()
            )
        );
        $this->array($error_detected)->isEmpty(implode(' ', $cdf->getErrors()));
        $this->array($warning_detected)->isEmpty(implode(' ', $cdf->getWarnings()));
        //check if dynamic translation has been added
        $this->checkDynamicTranslation($cfield_data['field_name']);

        $select = $this->zdb->select(DynamicField::TABLE);
        $select->columns(array('num' => new \Laminas\Db\Sql\Expression('COUNT(1)')));
        $result = $this->zdb->execute($select)->current();
        $this->integer((int)$result->num)->isIdenticalTo(3);


        $fields = ['nom_adh', 'ville_adh', 'dynfield_' . $cdf->getId(), 'fingerprint'];
        $file_name = 'test-import-atoum-cdyn-date.csv';
        $flash_messages = [
            'success_detected' => ["File '$file_name' has been successfully imported :)"]
        ];
        $members_list = [];
        for ($i = 0; $i < 10; ++$i) {
            $data = $fakedata->fakeMember();
            $data['nom_adh'] = str_replace('"', '""', $data['nom_adh']);
            $data['ville_adh'] = str_replace('"', '""', $data['ville_adh']);
            $data['fingerprint'] = 'FAKER_' . $i;
            $data['dynfield_' . $cdf->getId()] = $data['date_crea_adh'];
            $members_list[$data['fingerprint']] = $data;
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
        $members_list = [];
        for ($i = 0; $i < 10; ++$i) {
            $data = $fakedata->fakeMember();
            $data['nom_adh'] = str_replace('"', '""', $data['nom_adh']);
            $data['ville_adh'] = str_replace('"', '""', $data['ville_adh']);
            $data['fingerprint'] = 'FAKER_' . $i;
            $data['dynfield_' . $cdf->getId()] = (($i == 2 || $i == 5) ? '20200513' : $data['date_crea_adh']);
            $members_list[$data['fingerprint']] = $data;
        }
        $count_before = 10;
        $count_after = 10;

        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);
    }
}
