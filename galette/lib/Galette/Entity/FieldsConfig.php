<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Fields config handling
 *
 * PHP version 5
 *
 * Copyright © 2009-2021 The Galette Team
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
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-26
 */

namespace Galette\Entity;

use ArrayObject;
use Throwable;
use Analog\Analog;
use Laminas\Db\Adapter\Adapter;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Core\Authentication;

/**
 * Fields config class for galette :
 * defines fields visibility for lists and forms
 * defines fields order and requirement flag for forms
 *
 * @category  Entity
 * @name      FieldsConfig
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-26
 */
class FieldsConfig
{
    public const NOBODY = 0;
    public const USER_WRITE = 1;
    public const ADMIN = 2;
    public const STAFF = 3;
    public const MANAGER = 4;
    public const USER_READ = 5;

    public const TYPE_STR = 0;
    public const TYPE_HIDDEN = 1;
    public const TYPE_BOOL = 2;
    public const TYPE_INT = 3;
    public const TYPE_DEC = 4;
    public const TYPE_DATE = 5;
    public const TYPE_TXT = 6;
    public const TYPE_PASS = 7;
    public const TYPE_EMAIL = 8;
    public const TYPE_URL = 9;
    public const TYPE_RADIO = 10;
    public const TYPE_SELECT = 11;

    protected $zdb;
    protected $core_db_fields = array();
    protected $all_required = array();
    protected $all_visibles = array();
    protected $categorized_fields = array();
    protected $table;
    protected $defaults = null;
    protected $cats_defaults = null;

    private $staff_fields = array(
        'activite_adh',
        'id_statut',
        'bool_exempt_adh',
        'date_crea_adh',
        'info_adh'
    );
    private $admin_fields = array(
        'bool_admin_adh'
    );

    public const TABLE = 'fields_config';

    /*
     * Fields that are not visible in the
     * form should not be visible here.
     */
    private $non_required = array(
        'id_adh',
        'date_echeance',
        'bool_display_info',
        'bool_exempt_adh',
        'bool_admin_adh',
        'activite_adh',
        'date_crea_adh',
        'date_modif_adh',
        //Fields we do not want to be set as required
        'societe_adh',
        'id_statut',
        'pref_lang',
        'sexe_adh',
        'parent_id'
    );

    private $non_form_elements = array(
        'date_echeance',
        'date_modif_adh'
    );

    private $non_display_elements = array(
        'date_echeance',
        'mdp_adh',
        'titre_adh',
        'sexe_adh',
        'prenom_adh'
    );

    /**
     * Default constructor
     *
     * @param Db      $zdb           Database
     * @param string  $table         the table for which to get fields configuration
     * @param array   $defaults      default values
     * @param array   $cats_defaults default categories values
     * @param boolean $install       Are we calling from installer?
     */
    public function __construct(Db $zdb, string $table, array $defaults, array $cats_defaults, bool $install = false)
    {
        $this->zdb = $zdb;
        $this->table = $table;
        $this->defaults = $defaults;
        $this->cats_defaults = $cats_defaults;
        //prevent check at install time...
        if (!$install) {
            $this->load();
            $this->checkUpdate();
        }
    }

    /**
     * Load current fields configuration from database.
     *
     * @return boolean
     */
    public function load()
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select
                ->where(array('table_name' => $this->table))
                ->order(array(FieldsCategories::PK, 'position ASC'));

            $results = $this->zdb->execute($select);
            $this->core_db_fields = [];

            foreach ($results as $k) {
                $field = $this->buildField($k);
                $this->core_db_fields[$k->field_id] = $field;
            }

            $this->buildLists();
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Fields configuration cannot be loaded!',
                Analog::URGENT
            );
            throw $e;
        }
    }

    /**
     * Prepare a field (required data, automation)
     *
     * @param ArrayObject $rset DB ResultSet row
     *
     * @return ArrayObject
     */
    protected function prepareField(ArrayObject $rset): ArrayObject
    {
        if ($rset->field_id === 'parent_id') {
            $rset->readonly = true;
            $rset->required = false;
        }
        return $rset;
    }

    /**
     * Prepare a field (required data, automation)
     *
     * @param ArrayObject $rset DB ResultSet row
     *
     * @return array
     */
    protected function buildField(ArrayObject $rset): array
    {
        $rset = $this->prepareField($rset);
        $f = array(
            'field_id'  => $rset->field_id,
            'label'     => $this->defaults[$rset->field_id]['label'],
            'category'  => (int)$rset->id_field_category,
            'visible'   => (int)$rset->visible,
            'required'  => (bool)$rset->required,
            'propname'  => $this->defaults[$rset->field_id]['propname'],
            'position'  => (int)$rset->position,
            'disabled'  => false
        );
        return $f;
    }

    /**
     * Create field array configuration,
     * Several lists of fields are kept (visible, requireds, etc), build them.
     *
     * @return void
     */
    protected function buildLists()
    {
        $this->categorized_fields = [];
        $this->all_required = [];
        $this->all_visibles = [];

        foreach ($this->core_db_fields as $field) {
            $this->addToLists($field);
        }
    }

    /**
     * Adds a field to lists
     *
     * @param array $field Field values
     *
     * @return void
     */
    protected function addToLists(array $field)
    {
        if ($field['position'] >= 0) {
            $this->categorized_fields[$field['category']][] = $field;
        }

        //array of all required fields
        if ($field['required']) {
            $this->all_required[$field['field_id']] = $field['required'];
        }

        //array of all fields visibility
        $this->all_visibles[$field['field_id']] = $field['visible'];
    }

    /**
     * Is a field set as required?
     *
     * @param string $field Field name
     *
     * @return boolean
     */
    public function isRequired($field)
    {
        return isset($this->all_required[$field]);
    }

    /**
     * Temporary set a field as not required
     * (password for existing members for example)
     *
     * @param string $field Field name
     *
     * @return void
     */
    public function setNotRequired($field)
    {
        if (isset($this->all_required[$field])) {
            unset($this->all_required[$field]);
        }

        foreach ($this->categorized_fields as &$cat) {
            foreach ($cat as &$f) {
                if ($f['field_id'] === $field) {
                    $f['required'] = false;
                    return;
                }
            }
        }
    }

    /**
     * Checks if all fields are present in the database.
     *
     * For now, this function only checks if count matches.
     *
     * @return void
     */
    private function checkUpdate()
    {
        $class = get_class($this);

        try {
            $_all_fields = array();
            if (count($this->core_db_fields)) {
                array_walk(
                    $this->core_db_fields,
                    function ($field) use (&$_all_fields) {
                        $_all_fields[$field['field_id']] = $field;
                    }
                );
            } else {
                //hum... no records. Let's check if any category exists
                $select = $this->zdb->select(FieldsCategories::TABLE);
                $results = $this->zdb->execute($select);

                if ($results->count() == 0) {
                    //categories are missing, add them
                    $categories = new FieldsCategories($this->zdb, $this->cats_defaults);
                    $categories->installInit();
                }
            }

            if (count($this->defaults) != count($_all_fields)) {
                Analog::log(
                    'Fields configuration count for `' . $this->table .
                    '` columns does not match records. Is : ' .
                    count($_all_fields) . ' and should be ' .
                    count($this->defaults),
                    Analog::WARNING
                );

                $params = array();
                foreach ($this->defaults as $k => $f) {
                    if (!isset($_all_fields[$k])) {
                        Analog::log(
                            'Missing field configuration for field `' . $k . '`',
                            Analog::INFO
                        );
                        $params[] = array(
                            'field_id'      => $k,
                            'table_name'    => $this->table,
                            'required'      => $f['required'],
                            'visible'       => $f['visible'],
                            'position'      => $f['position'],
                            'category'      => $f['category'],
                            'list_visible'  => $f['list_visible'] ?? false,
                            'list_position' => $f['list_position'] ?? null
                        );
                    }
                }

                if (count($params) > 0) {
                    $this->insert($params);
                    $this->load();
                }
            }
        } catch (Throwable $e) {
            Analog::log(
                '[' . $class . '] An error occurred while checking update for ' .
                'fields configuration for table `' . $this->table . '`. ' .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Set default fields configuration at install time. All previous
     * existing values will be dropped first, including fields categories.
     *
     * @return boolean
     * @throws Throwable
     */
    public function installInit()
    {
        try {
            $fields = array_keys($this->defaults);
            $categories = new FieldsCategories($this->zdb, $this->cats_defaults);

            //first, we drop all values
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where(
                array('table_name' => $this->table)
            );
            $this->zdb->execute($delete);
            //take care of fields categories, for db relations
            $categories->installInit($this->zdb);

            $params = [];
            foreach ($fields as $f) {
                //build default config for each field
                $params[] = array(
                    'field_id'      => $f,
                    'table_name'    => $this->table,
                    'required'      => $this->defaults[$f]['required'],
                    'visible'       => $this->defaults[$f]['visible'],
                    'position'      => (int)$this->defaults[$f]['position'],
                    'category'      => $this->defaults[$f]['category'],
                    'list_visible'  => $this->defaults[$f]['list_visible'] ?? false,
                    'list_position' => $this->defaults[$f]['list_position'] ?? -1
                );
            }
            $this->insert($params);

            Analog::log(
                'Default fields configuration were successfully stored.',
                Analog::INFO
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to initialize default fields configuration.' . $e->getMessage(),
                Analog::ERROR
            );

            throw $e;
        }
    }

    /**
     * Get non required fields
     *
     * @return array
     */
    public function getNonRequired()
    {
        return $this->non_required;
    }

    /**
     * Retrieve form elements
     *
     * @param Login   $login Login instance
     * @param boolean $new   True when adding a new member
     * @param boolean $selfs True if we're called from self subscription page
     *
     * @return array
     */
    public function getFormElements(Login $login, $new, $selfs = false)
    {
        global $preferences;

        $hidden_elements = [];
        $form_elements = [];

        //get columns descriptions
        $columns = $this->zdb->getColumns($this->table);

        $access_level = $login->getAccessLevel();
        $categories = FieldsCategories::getList($this->zdb);
        try {
            foreach ($categories as $c) {
                $cpk = FieldsCategories::PK;
                $cat_label = null;
                foreach ($this->cats_defaults as $conf_cat) {
                    if ($conf_cat['id'] == $c->$cpk) {
                        $cat_label = $conf_cat['category'];
                        break;
                    }
                }
                if ($cat_label === null) {
                    $cat_label = $c->category;
                }
                $cat = (object)array(
                    'id'        => (int)$c->$cpk,
                    'label'     => $cat_label,
                    'elements'  => array()
                );

                $elements = $this->categorized_fields[$c->$cpk];
                $cat->elements = array();

                foreach ($elements as $elt) {
                    $o = (object)$elt;
                    $o->readonly = false;

                    if ($o->field_id == 'id_adh') {
                        // ignore access control, as member ID is always needed
                        if (!$preferences->pref_show_id || $new === true) {
                            $hidden_elements[] = $o;
                        } else {
                            $o->type = self::TYPE_STR;
                            $o->readonly = true;
                            $cat->elements[$o->field_id] = $o;
                        }
                    } elseif ($o->field_id == 'parent_id') {
                        $hidden_elements[] = $o;
                    } else {
                        // skip fields blacklisted for edition
                        if (
                            in_array($o->field_id, $this->non_form_elements)
                            || $selfs && $this->isSelfExcluded($o->field_id)
                        ) {
                            continue;
                        }

                        // skip fields according to access control
                        if (
                            $o->visible == self::NOBODY ||
                            ($o->visible == self::ADMIN &&
                                $access_level < Authentication::ACCESS_ADMIN) ||
                            ($o->visible == self::STAFF &&
                                $access_level < Authentication::ACCESS_STAFF) ||
                            ($o->visible == self::MANAGER &&
                                $access_level < Authentication::ACCESS_MANAGER)
                        ) {
                            continue;
                        }

                        if (preg_match('/date/', $o->field_id)) {
                            $o->type = self::TYPE_DATE;
                        } elseif (preg_match('/bool/', $o->field_id)) {
                            $o->type = self::TYPE_BOOL;
                        } elseif (
                            $o->field_id == 'titre_adh'
                            || $o->field_id == 'pref_lang'
                            || $o->field_id == 'id_statut'
                        ) {
                            $o->type = self::TYPE_SELECT;
                        } elseif ($o->field_id == 'sexe_adh') {
                            $o->type = self::TYPE_RADIO;
                        } else {
                            $o->type = self::TYPE_STR;
                        }

                        //retrieve field information from DB
                        foreach ($columns as $column) {
                            if ($column->getName() === $o->field_id) {
                                $o->max_length
                                    = $column->getCharacterMaximumLength();
                                $o->default = $column->getColumnDefault();
                                $o->datatype = $column->getDataType();
                                break;
                            }
                        }

                        // disabled field according to access control
                        if (
                            $o->visible == self::USER_READ &&
                                $access_level == Authentication::ACCESS_USER
                        ) {
                            $o->disabled = true;
                        } else {
                            $o->disabled = false;
                        }

                        if ($selfs === true) {
                            //email, login and password are always required for self subscription
                            $srequireds = ['email_adh', 'login_adh'];
                            if (in_array($o->field_id, $srequireds)) {
                                $o->required = true;
                            }
                        }
                        $cat->elements[$o->field_id] = $o;
                    }
                }

                if (count($cat->elements) > 0) {
                    $form_elements[] = $cat;
                }
            }
            return array(
                'fieldsets' => $form_elements,
                'hiddens'   => $hidden_elements
            );
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred getting form elements',
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Retrieve display elements
     *
     * @param Login $login Login instance
     *
     * @return array
     */
    public function getDisplayElements(Login $login)
    {
        global $preferences;

        $display_elements = [];
        $access_level = $login->getAccessLevel();
        $categories = FieldsCategories::getList($this->zdb);
        try {
            foreach ($categories as $c) {
                $cpk = FieldsCategories::PK;
                $cat_label = null;
                foreach ($this->cats_defaults as $conf_cat) {
                    if ($conf_cat['id'] == $c->$cpk) {
                        $cat_label = $conf_cat['category'];
                        break;
                    }
                }
                if ($cat_label === null) {
                    $cat_label = $c->category;
                }
                $cat = (object)array(
                    'id'        => (int)$c->$cpk,
                    'label'     => $cat_label,
                    'elements'  => array()
                );

                $elements = $this->categorized_fields[$c->$cpk];
                $cat->elements = array();

                foreach ($elements as $elt) {
                    $o = (object)$elt;

                    if ($o->field_id == 'id_adh') {
                        // ignore access control, as member ID is always needed
                        if (!isset($preferences) || !$preferences->pref_show_id) {
                            $hidden_elements[] = $o;
                        } else {
                            $o->type = self::TYPE_STR;
                            $cat->elements[$o->field_id] = $o;
                        }
                    } else {
                        // skip fields blacklisted for display
                        if (in_array($o->field_id, $this->non_display_elements)) {
                            continue;
                        }

                        // skip fields according to access control
                        if (
                            $o->visible == self::NOBODY ||
                            ($o->visible == self::ADMIN &&
                                $access_level < Authentication::ACCESS_ADMIN) ||
                            ($o->visible == self::STAFF &&
                                $access_level < Authentication::ACCESS_STAFF) ||
                            ($o->visible == self::MANAGER &&
                                $access_level < Authentication::ACCESS_MANAGER)
                        ) {
                            continue;
                        }

                        $cat->elements[$o->field_id] = $o;
                    }
                }

                if (count($cat->elements) > 0) {
                    $display_elements[] = $cat;
                }
            }
            return $display_elements;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred getting display elements',
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Get required fields
     *
     * @return array of all required fields. Field names = keys
     */
    public function getRequired()
    {
        return $this->all_required;
    }

    /**
     * Get visible fields
     *
     * @return array of all visibles fields
     */
    public function getVisibilities()
    {
        return $this->all_visibles;
    }

    /**
     * Get visibility for specified field
     *
     * @param string $field The requested field
     *
     * @return boolean
     */
    public function getVisibility($field)
    {
        return $this->all_visibles[$field];
    }

    /**
     * Get all fields with their categories
     *
     * @return array
     */
    public function getCategorizedFields()
    {
        return $this->categorized_fields;
    }

    /**
     * Set fields
     *
     * @param array $fields categorized fields array
     *
     * @return boolean
     */
    public function setFields($fields)
    {
        $this->categorized_fields = $fields;
        return $this->store();
    }

    /**
     * Store config in database
     *
     * @return boolean
     */
    private function store()
    {
        $class = get_class($this);

        try {
            $this->zdb->connection->beginTransaction();

            $update = $this->zdb->update(self::TABLE);
            $update->set(
                array(
                    'required'              => ':required',
                    'visible'               => ':visible',
                    'position'              => ':position',
                    FieldsCategories::PK    => ':' . FieldsCategories::PK
                )
            )->where(
                array(
                    'field_id'      => ':field_id',
                    'table_name'    => $this->table
                )
            );
            $stmt = $this->zdb->sql->prepareStatementForSqlObject($update);

            $params = null;
            foreach ($this->categorized_fields as $cat) {
                foreach ($cat as $pos => $field) {
                    if (in_array($field['field_id'], $this->non_required)) {
                        $field['required'] = $this->zdb->isPostgres() ? 'false' : 0;
                    }

                    if ($field['field_id'] === 'parent_id') {
                        $field['visible'] = 0;
                    }

                    $params = array(
                        'required'              => $field['required'],
                        'visible'               => $field['visible'],
                        'position'              => $pos,
                        FieldsCategories::PK    => $field['category'],
                        'field_id'              => $field['field_id']
                    );

                    $stmt->execute($params);
                }
            }

            Analog::log(
                '[' . $class . '] Fields configuration stored successfully! ',
                Analog::DEBUG
            );
            Analog::log(
                str_replace(
                    '%s',
                    $this->table,
                    '[' . $class . '] Fields configuration for table %s stored ' .
                    'successfully.'
                ),
                Analog::INFO
            );

            $this->zdb->connection->commit();
            return $this->load();
        } catch (Throwable $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                '[' . $class . '] An error occurred while storing fields ' .
                'configuration for table `' . $this->table . '`.' .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Migrate old required fields configuration
     * Only needeed for 0.7.4 upgrade
     * (should have been 0.7.3 - but I missed that.)
     *
     * @return boolean
     */
    public function migrateRequired()
    {
        $old_required = null;

        try {
            $select = $this->zdb->select('required');
            $select->from(PREFIX_DB . 'required');

            $old_required = $this->zdb->execute($select);
        } catch (\Exception $pe) {
            Analog::log(
                'Unable to retrieve required fields_config. Maybe ' .
                'the table does not exists?',
                Analog::WARNING
            );
            //not a blocker
            return true;
        }

        $this->zdb->connection->beginTransaction();
        try {
            $update = $this->zdb->update(self::TABLE);
            $update->set(
                array(
                    'required'  => ':required'
                )
            )->where(
                array(
                    'field_id'      => ':field_id',
                    'table_name'    => $this->table
                )
            );

            $stmt = $this->zdb->sql->prepareStatementForSqlObject($update);

            foreach ($old_required as $or) {
                $stmt->execute(
                    array(
                        'required'  => ($or->required === false) ?
                            ($this->zdb->isPostgres() ? 'false' : 0) : true,
                        'field_id'  => $or->field_id
                    )
                );
            }

            $class = get_class($this);
            Analog::log(
                str_replace(
                    '%s',
                    $this->table,
                    '[' . $class . '] Required fields for table %s upgraded ' .
                    'successfully.'
                ),
                Analog::INFO
            );

            $this->zdb->db->query(
                'DROP TABLE ' . PREFIX_DB . 'required',
                Adapter::QUERY_MODE_EXECUTE
            );

            $this->zdb->connection->commit();
            return true;
        } catch (Throwable $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                'An error occurred migrating old required fields. | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Insert values in database
     *
     * @param array $values Values to insert
     *
     * @return void
     */
    private function insert($values)
    {
        $insert = $this->zdb->insert(self::TABLE);
        $insert->values(
            array(
                'field_id'              => ':field_id',
                'table_name'            => ':table_name',
                'required'              => ':required',
                'visible'               => ':visible',
                FieldsCategories::PK    => ':category',
                'position'              => ':position',
                'list_visible'          => ':list_visible',
                'list_position'         => ':list_position'
            )
        );
        $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);
        foreach ($values as $d) {
            $required = $d['required'];
            if ($required === false) {
                $required = $this->zdb->isPostgres() ? 'false' : 0;
            }

            $list_visible = $d['list_visible'] ?? false;
            if ($list_visible === false) {
                $list_visible = $this->zdb->isPostgres() ? 'false' : 0;
            }

            $stmt->execute(
                array(
                    'field_id'              => $d['field_id'],
                    'table_name'            => $d['table_name'],
                    'required'              => $required,
                    'visible'               => $d['visible'],
                    'category'              => $d['category'],
                    'position'              => $d['position'],
                    'list_visible'          => $list_visible,
                    'list_position'         => $d['list_position'] ?? -1
                )
            );
        }
    }

    /**
     * Does field should be displayed in self subscription page
     *
     * @param string $name Field name
     *
     * @return boolean
     */
    public function isSelfExcluded($name)
    {
        return in_array(
            $name,
            array_merge(
                $this->staff_fields,
                $this->admin_fields
            )
        );
    }

    /**
     * Filter visible fields
     *
     * @param Login $login  Login instance
     * @param array $fields Fields list
     *
     * @return void
     */
    public function filterVisible(Login $login, array &$fields): void
    {
        $access_level = $login->getAccessLevel();
        $visibles = $this->getVisibilities();

        //remove not searchable fields
        unset($fields['mdp_adh']);

        foreach ($fields as $k => $f) {
            if (
                $visibles[$k] == FieldsConfig::NOBODY ||
                ($visibles[$k] == FieldsConfig::ADMIN &&
                    $access_level < Authentication::ACCESS_ADMIN) ||
                ($visibles[$k] == FieldsConfig::STAFF &&
                    $access_level < Authentication::ACCESS_STAFF) ||
                ($visibles[$k] == FieldsConfig::MANAGER &&
                    $access_level < Authentication::ACCESS_MANAGER)
            ) {
                unset($fields[$k]);
            }
        }
    }

    /**
     * Get fields for massive changes
     * @see FieldsConfig::getFormElements
     *
     * @param array $fields Member fields
     * @param Login $login  Login instance
     *
     * @return array
     */
    public function getMassiveFormElements(array $fields, Login $login)
    {
        $this->filterVisible($login, $fields);

        $mass_fields = [
            'titre_adh',
            'sexe_adh',
            'pref_lang',
            'cp_adh',
            'ville_adh',
            'pays_adh',
            'bool_display_info',
            'activite_adh',
            Status::PK,
            'bool_admin_adh',
            'bool_exempt_adh',
        ];
        $mass_fields = array_intersect(array_keys($fields), $mass_fields);

        foreach ($mass_fields as $mass_field) {
            $this->setNotRequired($mass_field);
        }
        $form_elements = $this->getFormElements($login, false);
        unset($form_elements['hiddens']);

        foreach ($form_elements['fieldsets'] as &$form_element) {
            $form_element->elements = array_intersect_key($form_element->elements, array_flip($mass_fields));
        }
        return $form_elements;
    }

    /**
     * Get field configuration
     *
     * @param string $name Field name
     *
     * @return array
     */
    public function getField($name): array
    {
        if (!isset($this->core_db_fields[$name])) {
            throw new \UnexpectedValueException("$name fied does not exists");
        }
        return $this->core_db_fields[$name];
    }
}
