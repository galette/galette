<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

namespace Galette\IO;

use DI\Attribute\Inject;
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
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class CsvIn extends Csv implements FileInterface
{
    use FileTrait;

    public const DEFAULT_DIRECTORY = GALETTE_IMPORTS_PATH;
    public const DATA_IMPORT_ERROR = -10;

    /** @var array<string> */
    protected array $extensions = array('csv', 'txt');

    /** @var array<string> */
    private array $fields;
    /** @var array<string> */
    private array $default_fields = array(
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

    private bool $dryrun = true;

    /** @var array<string,mixed>  */
    private array $members_fields;
    /** @var array<string,mixed> */
    private array $members_fields_cats;
    /** @var array<string,bool> */
    private array $required;
    /** @var array<int, string> */
    private array $statuses;
    /** @var Title[]  */
    private array $titles;
    /** @var array<string,string> */
    private array $langs;
    /** @var array<string,int> */
    private array $emails;
    private Db $zdb;
    private Preferences $preferences;
    private History $history;
    #[Inject]
    private Status $status; // @phpstan-ignore property.onlyRead (this is what's expected here)

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
    private function loadFields(): void
    {
        //at last, we got the defaults
        $this->fields = $this->default_fields;

        $model = new ImportModel();
        //we go with default fields if model cannot be loaded
        if ($model->load()) {
            $this->fields = $model->getFields();
        }
    }

    /**
     * Get default fields
     *
     * @return array<string>
     */
    public function getDefaultFields(): array
    {
        return $this->default_fields;
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
        string $filename,
        array $members_fields,
        array $members_fields_cats,
        bool $dryrun
    ): bool|int {
        if (
            !file_exists(self::DEFAULT_DIRECTORY . '/' . $filename)
            || !is_readable(self::DEFAULT_DIRECTORY . '/' . $filename)
        ) {
            Analog::log(
                'File ' . $filename . ' does not exists or cannot be read.',
                Analog::ERROR
            );
            $this->addError(
                str_replace(
                    '%filename',
                    $filename,
                    _T('File %filename cannot be open!')
                )
            );

            return self::INVALID_FILE;
        }

        $this->zdb = $zdb;
        $this->preferences = $preferences;
        $this->history = $history;
        if ($dryrun === false) {
            $this->dryrun = false;
        }

        $this->loadFields();
        $this->members_fields = $members_fields;
        $this->members_fields_cats = $members_fields_cats;

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
    private function check(string $filename): bool
    {
        $this->resetErrors();
        unset($this->emails);
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

        $cnt_fields = count($this->fields);

        //check required fields
        $fc = new FieldsConfig(
            $this->zdb,
            Adherent::TABLE,
            $this->members_fields,
            $this->members_fields_cats
        );
        $config_required = $fc->getRequired();
        $this->required = array();

        foreach (array_keys($config_required) as $field) {
            if (in_array($field, $this->fields)) {
                $this->required[$field] = $field;
            }
        }

        $member = new Adherent($this->zdb);
        $dfields = [];
        $member->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $row = 0;
        while (
            ($data = fgetcsv(
                $handle,
                1000,
                self::DEFAULT_SEPARATOR,
                self::DEFAULT_QUOTE,
                self::DEFAULT_ESCAPE
            )) !== false
        ) {
            //check fields count
            $count = count($data);
            if ($count != $cnt_fields) {
                $this->addError(
                    str_replace(
                        array('%should_count', '%count', '%row'),
                        array((string)$cnt_fields, (string)$count, (string)$row),
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
                        in_array($this->fields[$col], $this->required)
                        && empty($column)
                    ) {
                        $this->addError(
                            str_replace(
                                array('%field', '%row'),
                                array($this->fields[$col], (string)$row),
                                _T("Field %field is required, but missing in row %row")
                            )
                        );
                        return false;
                    }

                    //check for statuses
                    //if missing, set default one; if not check it does exist
                    if ($this->fields[$col] == Status::PK) {
                        if (empty($column)) {
                            $column = $this->preferences->pref_statut ?? Status::DEFAULT_STATUS;
                        } else {
                            if (!isset($this->statuses)) {
                                //load existing status
                                $this->statuses = $this->status->getList();
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
                    if ($this->fields[$col] == 'titre_adh' && !empty($column)) {
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
                    if ($this->fields[$col] == 'email_adh' && !empty($column)) {
                        if (!isset($this->emails)) {
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
                    if ($this->fields[$col] == 'pref_lang') {
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
                                        '%lang',
                                        $column,
                                        _T("Lang %lang does not exists!")
                                    )
                                );
                                return false;
                            }
                        }
                    }

                    //passwords
                    if ($this->fields[$col] == 'mdp_adh' && !empty($column)) {
                        $this->fields['mdp_adh2'] = $column;
                    }

                    if (substr($this->fields[$col], 0, strlen('dynfield_')) === 'dynfield_') {
                        //dynamic field, keep to check later
                        $dfields[$this->fields[$col] . '_1'] = $column;
                    } else {
                        //standard field
                        $member->validate($this->fields[$col], $column, $this->fields);
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
    private function storeMembers(string $filename): bool
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
                    self::DEFAULT_QUOTE,
                    self::DEFAULT_ESCAPE
                )) !== false
            ) {
                if ($row > 0) {
                    $col = 0;
                    $values = array();
                    foreach ($data as $column) {
                        if (substr($this->fields[$col], 0, strlen('dynfield_')) === 'dynfield_') {
                            //dynamic field, keep to check later
                            $values[str_replace('dynfield_', 'info_field_', $this->fields[$col] . '_1')] = $column;
                            $col++;
                            continue;
                        }

                        $values[$this->fields[$col]] = $column;
                        if ($this->fields[$col] === 'societe_adh') {
                            $values['is_company'] = true;
                        }
                        //check for booleans
                        if (
                            ($this->fields[$col] == 'bool_admin_adh'
                            || $this->fields[$col] == 'bool_exempt_adh'
                            || $this->fields[$col] == 'bool_display_info'
                            || $this->fields[$col] == 'activite_adh')
                            && ($column == null || trim($column) == '')
                        ) {
                            $values[$this->fields[$col]] = 0; //defaults to 0 as in Adherent
                        }

                        if ($this->fields[$col] == Status::PK && empty(trim($column))) {
                            $values[Status::PK] = $this->preferences->pref_statut ?? Status::DEFAULT_STATUS;
                        }

                        if ($this->fields[$col] == 'pref_lang' && empty(trim($column))) {
                            $values[$this->fields[$col]] = $this->preferences->pref_lang;
                        }

                        $col++;
                    }
                    //import member itself
                    $member = new Adherent($this->zdb);
                    $member->setDependencies(
                        $this->preferences,
                        $this->members_fields,
                        $this->history
                    );
                    //check for empty creation date
                    if (isset($values['date_crea_adh']) && trim($values['date_crea_adh']) === '') {
                        unset($values['date_crea_adh']);
                    }
                    if (isset($values['mdp_adh'])) {
                        $values['mdp_adh2'] = $values['mdp_adh'];
                    }

                    $valid = $member->check($values, $this->required, []);
                    if ($valid === true) {
                        if ($this->dryrun === false) {
                            $store = $member->store();
                            if ($store !== true) {
                                $this->addError(
                                    str_replace(
                                        array('%row', '%name'),
                                        array((string)$row, $member->sname),
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
                                array((string)$row, $member->sname),
                                _T("An error occurred storing member at row %row (%name):")
                            )
                        );
                        foreach ($valid as $e) {
                            $this->addError($e);
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
    public function getErrorMessage(int $code): string
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
