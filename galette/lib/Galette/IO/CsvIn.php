<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * CSV imports
 *
 * PHP version 5
 *
 * Copyright © 2013-2024 The Galette Team
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
 *
 * @category  IO
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.7.6dev - 2013-08-27
 */

namespace Galette\IO;

use Galette\Core\I18n;
use Galette\Entity\Title;
use Throwable;
use Analog\Analog;
use Galette\Core\Db;
use Galette\Core\Preferences;
use Galette\Core\History;
use Galette\Entity\Adherent;
use Galette\Entity\ImportModel;
use Galette\Entity\FieldsConfig;
use Galette\Entity\Status;
use Galette\Repository\Titles;
use Galette\Repository\Members;

/**
 * CSV imports
 *
 * @category  IO
 * @name      Csv
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.7.6dev - 2013-08-27
 */

class CsvIn extends Csv implements FileInterface
{
    use FileTrait;

    public const DEFAULT_DIRECTORY = GALETTE_IMPORTS_PATH;
    public const DATA_IMPORT_ERROR = -10;

    /** @var array<string> */
    protected array $extensions = array('csv', 'txt');

    /** @var array<string> */
    private $_fields;
    /** @var array<string> */
    private array $_default_fields = array(
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
        'prof_adh',
        'pseudo_adh',
        'societe_adh',
        'login_adh',
        'date_crea_adh',
        'id_statut',
        'info_public_adh',
        'info_adh'
    );

    private bool $_dryrun = true;

    /** @var array<string,mixed>  */
    private array $_members_fields;
    /** @var array<string,mixed> */
    private array $_members_fields_cats;
    /** @var array<string,bool> */
    private array $_required;
    /** @var array<int, string> */
    private array $statuses;
    /** @var Title[]  */
    private array $titles;
    /** @var array<string,string> */
    private array $langs;
    /** @var array<string,int> */
    private $emails;
    private Db $zdb;
    private Preferences $preferences;
    private History $history;

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
     * @return array<string>
     */
    public function getDefaultFields()
    {
        return $this->_default_fields;
    }

    /**
     * Import members from CSV file
     *
     * @param Db                  $zdb                 Database instance
     * @param Preferences         $preferences         Preferences instance
     * @param History             $history             History instance
     * @param string              $filename            CSV filename
     * @param array<string,mixed> $members_fields      Members fields
     * @param array<string,mixed> $members_fields_cats Members fields categories
     * @param boolean             $dryrun              Run in dry run mode (do not store in database)
     *
     * @return bool|int
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
        if (
            !file_exists(self::DEFAULT_DIRECTORY . '/' . $filename)
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
    private function check(string $filename)
    {
        $handle = fopen(self::DEFAULT_DIRECTORY . '/' . $filename, 'r');
        if (!$handle) {
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
                $this->_required[$field] = true;
            }
        }

        $member = new Adherent($this->zdb);
        $dfields = [];
        $member->setDependencies(
            $this->preferences,
            $this->_members_fields,
            $this->history
        );

        $row = 0;
        while (
            ($data = fgetcsv(
                $handle,
                1000,
                self::DEFAULT_SEPARATOR,
                self::DEFAULT_QUOTE
            )) !== false
        ) {
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

            if ($row > 0) {
                //header line is the first one. Here comes data
                $col = 0;
                $errors = [];
                foreach ($data as $column) {
                    $column = trim($column);

                    //check required fields
                    if (
                        in_array($this->_fields[$col], $this->_required)
                        && empty($column)
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

                    //check for statuses
                    //if missing, set default one; if not check it does exists
                    if ($this->_fields[$col] == Status::PK) {
                        if (empty($column)) {
                            $column = Status::DEFAULT_STATUS;
                        } else {
                            if (!isset($this->statuses)) {
                                //load existing status
                                $status = new Status($this->zdb);
                                $this->statuses = $status->getList();
                            }
                            if (!isset($this->statuses[(int)$column])) {
                                $this->addError(
                                    str_replace(
                                        '%status',
                                        $column,
                                        _T("Status %status does not exists!")
                                    )
                                );
                                return false;
                            }
                        }
                    }

                    //check for title
                    if ($this->_fields[$col] == 'titre_adh' && !empty($column)) {
                        if (!isset($this->titles)) {
                            //load existing titles
                            $titles = new Titles($this->zdb);
                            $this->titles = $titles->getList();
                        }
                        if (!isset($this->titles[$column])) {
                            $this->addError(
                                str_replace(
                                    '%title',
                                    $column,
                                    _T("Title %title does not exists!")
                                )
                            );
                            return false;
                        }
                    }

                    //check for email unicity
                    if ($this->_fields[$col] == 'email_adh' && !empty($column)) {
                        if ($this->emails === null) {
                            //load existing emails
                            $this->emails = Members::getEmails($this->zdb);
                        }
                        if (isset($this->emails[$column])) {
                            $existing = $this->emails[$column];
                            $extra = ($existing == -1 ?
                                _T("from another member in import") : str_replace('%id_adh', (string)$existing, _T("from member %id_adh"))
                            );
                            $this->addError(
                                str_replace(
                                    ['%address', '%extra'],
                                    [$column, $extra],
                                    _T("Email address %address is already used! (%extra)")
                                )
                            );
                            return false;
                        } else {
                            //add email to list
                            $this->emails[$column] = -1;
                        }
                    }

                    //check for language
                    if ($this->_fields[$col] == 'pref_lang') {
                        if (!isset($this->langs)) {
                            //load existing titles
                            /** @var I18n $i18n */
                            global $i18n;
                            $this->langs = $i18n->getArrayList();
                        }
                        if (empty($column)) {
                            $column = $this->preferences->pref_lang;
                        } else {
                            if (!isset($this->langs[$column])) {
                                $this->addError(
                                    str_replace(
                                        '%title',
                                        $column,
                                        _T("Lang %lang does not exists!")
                                    )
                                );
                                return false;
                            }
                        }
                    }

                    //passwords
                    if ($this->_fields[$col] == 'mdp_adh' && !empty($column)) {
                        $this->_fields['mdp_adh2'] = $column;
                    }

                    if (substr($this->_fields[$col], 0, strlen('dynfield_')) === 'dynfield_') {
                        //dynamic field, keep to check later
                        $dfields[$this->_fields[$col] . '_1'] = $column;
                    } else {
                        //standard field
                        $member->validate($this->_fields[$col], $column, $this->_fields);
                    }
                    $errors = $member->getErrors();
                    if (count($errors)) {
                        foreach ($errors as $error) {
                            $this->addError($error);
                        }
                        return false;
                    }

                    $col++;
                }

                //check dynamic fields
                $errcnt = count($errors);
                $member->dynamicsValidate($dfields);
                $errors = $member->getErrors();
                if (count($errors) > $errcnt) {
                    //@phpstan-ignore-next-line
                    $lcnt = ($errcnt > 0 ? $errcnt - 1 : 0);
                    $cnt_err = count($errors);
                    for ($i = $lcnt; $i < $cnt_err; ++$i) {
                        $this->addError($errors[$i]);
                    }
                    return false;
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
            $this->zdb->connection->beginTransaction();
            while (
                ($data = fgetcsv(
                    $handle,
                    1000,
                    self::DEFAULT_SEPARATOR,
                    self::DEFAULT_QUOTE
                )) !== false
            ) {
                if ($row > 0) {
                    $col = 0;
                    $values = array();
                    foreach ($data as $column) {
                        if (substr($this->_fields[$col], 0, strlen('dynfield_')) === 'dynfield_') {
                            //dynamic field, keep to check later
                            $values[str_replace('dynfield_', 'info_field_', $this->_fields[$col] . '_1')] = $column;
                            $col++;
                            continue;
                        }

                        $values[$this->_fields[$col]] = $column;
                        if ($this->_fields[$col] === 'societe_adh') {
                            $values['is_company'] = true;
                        }
                        //check for booleans
                        if (
                            ($this->_fields[$col] == 'bool_admin_adh'
                            || $this->_fields[$col] == 'bool_exempt_adh'
                            || $this->_fields[$col] == 'bool_display_info'
                            || $this->_fields[$col] == 'activite_adh')
                            && ($column == null || trim($column) == '')
                        ) {
                            $values[$this->_fields[$col]] = 0; //defaults to 0 as in Adherent
                        }

                        if ($this->_fields[$col] == Status::PK && empty(trim($column))) {
                            $values[Status::PK] = Status::DEFAULT_STATUS;
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
                    if (isset($values['mdp_adh'])) {
                        $values['mdp_adh2'] = $values['mdp_adh'];
                    }

                    $valid = $member->check($values, $this->_required, []);
                    if ($valid === true) {
                        if ($this->_dryrun === false) {
                            $store = $member->store();
                            if ($store !== true) {
                                $this->addError(
                                    str_replace(
                                        array('%row', '%name'),
                                        array($row, $member->sname),
                                        _T("An error occurred storing member at row %row (%name):")
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
                                _T("An error occurred storing member at row %row (%name):")
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
            $this->zdb->connection->commit();
            return true;
        } catch (Throwable $e) {
            $this->zdb->connection->rollBack();
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
                $error = _T("An error occurred while importing members");
                break;
        }

        if ($error === null) {
            $error = $this->getErrorMessageFromCode($code);
        }

        return $error;
    }
}
