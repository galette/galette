<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * CSV imports
 *
 * PHP version 5
 *
 * Copyright © 2013-2014 The Galette Team
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
 * @category  IO
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.6dev - 2013-08-27
 */

namespace Galette\IO;

use Analog\Analog;
use Galette\Core\Db;
use Galette\Core\Preferences;
use Galette\Core\History;
use Galette\Entity\Adherent;
use Galette\Entity\ImportModel;
use Galette\Entity\FieldsConfig;
use Galette\IO\FileTrait;

/**
 * CSV imports
 *
 * @category  IO
 * @name      Csv
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.6dev - 2013-08-27
 */

class CsvIn extends Csv implements FileInterface
{
    use FileTrait;

    const DEFAULT_DIRECTORY = GALETTE_IMPORTS_PATH;
    const DATA_IMPORT_ERROR = -10;

    protected $extensions = array('csv', 'txt');

    private $_fields;
    private $_default_fields = array(
        'nom_adh',
        'prenom_adh',
        'ddn_adh',
        'adresse_adh',
        'cp_adh',
        'ville_adh',
        'pays_adh',
        'tel_adh',
        'gsm_adh',
        'email_adh',
        'url_adh',
        'prof_adh',
        'pseudo_adh',
        'societe_adh',
        'login_adh',
        'date_crea_adh',
        'id_statut',
        'info_public_adh',
        'info_adh'
    );

    private $_dryrun = true;

    private $_members_fields;
    private $_members_fields_cats;
    private $_required;
    private $zdb;
    private $preferences;
    private $history;

    /**
     * Default constructor
     *
     * @param Db $zdb Database
     */
    public function __construct(Db $zdb)
    {
        $this->zdb = $zdb;
        $this->init(
            self::DEFAULT_DIRECTORY,
            $this->extensions,
            array(
                'csv'    =>    'text/csv',
                'txt'    =>    'text/plain'
            ),
            2048
        );

        parent::__construct(self::DEFAULT_DIRECTORY);
    }

    /**
     * Load fields list from database or from default values
     *
     * @return void
     */
    private function loadFields()
    {
        //at last, we got the defaults
        $this->_fields = $this->_default_fields;

        $model = new ImportModel();
        //we go with default fields if model cannot be loaded
        if ($model->load()) {
            $this->_fields = $model->getFields();
        }
    }

    /**
     * Get default fields
     *
     * @return array
     */
    public function getDefaultFields()
    {
        return $this->_default_fields;
    }

    /**
     * Import members from CSV file
     *
     * @param Db          $zdb                 Database instance
     * @param Preferences $preferences         Preferences instance
     * @param History     $history             History instance
     * @param string      $filename            CSV filename
     * @param array       $members_fields      Members fields
     * @param array       $members_fields_cats Members fields categories
     * @param boolean     $dryrun              Run in dry run mode (do not store in database)
     *
     * @return boolean
     */
    public function import(
        Db $zdb,
        Preferences $preferences,
        History $history,
        $filename,
        array $members_fields,
        array $members_fields_cats,
        $dryrun
    ) {
        if (!file_exists(self::DEFAULT_DIRECTORY . '/' . $filename)
            || !is_readable(self::DEFAULT_DIRECTORY . '/' . $filename)
        ) {
            Analog::log(
                'File ' . $filename . ' does not exists or cannot be read.',
                Analog::ERROR
            );
            return false;
        }

        $this->zdb = $zdb;
        $this->preferences = $preferences;
        $this->history = $history;
        if ($dryrun === false) {
            $this->_dryrun = false;
        }

        $this->loadFields();
        $this->_members_fields = $members_fields;
        $this->_members_fields_cats = $members_fields_cats;

        if (!$this->check($filename)) {
            return self::INVALID_FILE;
        }

        if (!$this->storeMembers($filename)) {
            return self::DATA_IMPORT_ERROR;
        }

        return true;
    }

    /**
     * Check if input file meet requirements
     *
     * @param string $filename File name
     *
     * @return boolean
     */
    private function check($filename)
    {
        //deal with mac e-o-l encoding -- see if needed
        //@ini_set('auto_detect_line_endings', true);
        $handle = fopen(self::DEFAULT_DIRECTORY . '/' . $filename, 'r');
        if (! $handle) {
            Analog::log(
                'File ' . $filename . ' cannot be open!',
                Analog::ERROR
            );
            $this->addError(
                str_replace(
                    '%filename',
                    $filename,
                    _T('File %filename cannot be open!')
                )
            );
            return false;
        }

        if ($handle !== false) {
            $cnt_fields = count($this->_fields);

            //check required fields
            $fc = new FieldsConfig(
                $this->zdb,
                Adherent::TABLE,
                $this->_members_fields,
                $this->_members_fields_cats
            );
            $config_required = $fc->getRequired();
            $this->_required = array();

            foreach (array_keys($config_required) as $field) {
                if (in_array($field, $this->_fields)) {
                    $this->_required[$field] = $field;
                }
            }

            $row = 0;
            while (($data = fgetcsv(
                $handle,
                1000,
                self::DEFAULT_SEPARATOR,
                self::DEFAULT_QUOTE
            )) !== false) {
                //check fields count
                $count = count($data);
                if ($count != $cnt_fields) {
                    $this->addError(
                        str_replace(
                            array('%should_count', '%count', '%row'),
                            array($cnt_fields, $count, $row),
                            _T("Fields count mismatch... There should be %should_count fields and there are %count (row %row)")
                        )
                    );
                    return false;
                }

                //check required fields
                if ($row > 0) {
                    //header line is the first one. Here comes data
                    $col = 0;
                    foreach ($data as $column) {
                        if (in_array($this->_fields[$col], $this->_required)
                            && trim($column) == ''
                        ) {
                            $this->addError(
                                str_replace(
                                    array('%field', '%row'),
                                    array($this->_fields[$col], $row),
                                    _T("Field %field is required, but missing in row %row")
                                )
                            );
                            return false;
                        }
                        $col++;
                    }
                }

                $row++;
            }
            fclose($handle);

            if (!($row > 1)) {
                //no data in file, just headers line
                $this->addError(
                    _T("File is empty!")
                );
                return false;
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * Store members in database
     *
     * @param string $filename CSV filename
     *
     * @return boolean
     */
    private function storeMembers($filename)
    {
        $handle = fopen(self::DEFAULT_DIRECTORY . '/' . $filename, 'r');

        $row = 0;

        try {
            while (($data = fgetcsv(
                $handle,
                1000,
                self::DEFAULT_SEPARATOR,
                self::DEFAULT_QUOTE
            )) !== false) {
                if ($row > 0) {
                    $col = 0;
                    $values = array();
                    foreach ($data as $column) {
                        $values[$this->_fields[$col]] = $column;
                        if ($this->_fields[$col] === 'societe_adh') {
                            $values['is_company'] = true;
                        }
                        $col++;
                    }
                    //import member itself
                    $member = new Adherent($this->zdb);
                    $member->setDependencies(
                        $this->preferences,
                        $this->_members_fields,
                        $this->history
                    );
                    //check for empty creation date
                    if (isset($values['date_crea_adh']) && trim($values['date_crea_adh']) === '') {
                        unset($values['date_crea_adh']);
                    }
                    $valid = $member->check($values, $this->_required, null);
                    if ($valid === true) {
                        if ($this->_dryrun === false) {
                            $store = $member->store();
                            if ($store !== true) {
                                $this->addError(
                                    str_replace(
                                        array('%row', '%name'),
                                        array($row, $member->sname),
                                        _T("An error occured storing member at row %row (%name):")
                                    )
                                );
                                return false;
                            }
                        }
                    } else {
                        $this->addError(
                            str_replace(
                                array('%row', '%name'),
                                array($row, $member->sname),
                                _T("An error occured storing member at row %row (%name):")
                            )
                        );
                        if (is_array($valid)) {
                            foreach ($valid as $e) {
                                $this->addError($e);
                            }
                        }
                        return false;
                    }
                }
                $row++;
            }
            return true;
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
        }

        return false;
    }

    /**
     * Return textual error message
     *
     * @param int $code The error code
     *
     * @return string Localized message
     */
    public function getErrorMessage($code)
    {
        $error = null;
        switch ($code) {
            case self::DATA_IMPORT_ERROR:
                $error = _T("An error occured while importing members");
                break;
        }

        if ($error === null) {
            $error = $this->getErrorMessageFromCode($code);
        }

        return $error;
    }
}
