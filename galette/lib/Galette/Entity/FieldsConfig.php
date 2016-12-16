<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Fields config handling
 *
 * PHP version 5
 *
 * Copyright © 2009-2014 The Galette Team
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
 * @copyright 2009-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-26
 */

namespace Galette\Entity;

use Analog\Analog;
use Zend\Db\Adapter\Adapter;
use Galette\Core\Db;
use Galette\Core\Login;

/**
 * Fields config class for galette :
 * defines fields mandatory, order and visibility
 *
 * @category  Entity
 * @name      FieldsConfig
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-26
 */
class FieldsConfig
{
    const HIDDEN = 0;
    const VISIBLE = 1;
    const ADMIN = 2;

    const TYPE_STR = 0;
    const TYPE_HIDDEN = 1;
    const TYPE_BOOL = 2;
    const TYPE_INT = 3;
    const TYPE_DEC = 4;
    const TYPE_DATE = 5;
    const TYPE_TXT = 6;
    const TYPE_PASS = 7;
    const TYPE_EMAIL = 8;
    const TYPE_URL = 9;
    const TYPE_RADIO = 10;
    const TYPE_SELECT = 11;

    private $zdb;
    private $all_required;
    private $all_visibles;
    //private $error = array();
    private $categorized_fields = array();
    private $table;
    private $defaults = null;
    private $cats_defaults = null;

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

    const TABLE = 'fields_config';

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
        'sexe_adh'
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
        'prenom_adh',
        'adresse2_adh'
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
    public function __construct(Db $zdb, $table, $defaults, $cats_defaults, $install = false)
    {
        $this->zdb = $zdb;
        $this->table = $table;
        $this->defaults = $defaults;
        $this->cats_defaults = $cats_defaults;
        $this->all_required = array();
        $this->all_visibles = array();
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
        global $preferences;

        try {
            $select = $this->zdb->select(self::TABLE);
            $select
                ->where(array('table_name' => $this->table))
                ->order(array(FieldsCategories::PK, 'position ASC'));

            $results = $this->zdb->execute($select);

            $this->categorized_fields = null;
            foreach ($results as $k) {
                if ($k->field_id === 'id_adh' && (!isset($preferences) || !$preferences->pref_show_id)) {
                    $k->visible = self::HIDDEN;
                }
                $f = array(
                    'field_id'  => $k->field_id,
                    'label'     => $this->defaults[$k->field_id]['label'],
                    'category'  => (int)$k->id_field_category,
                    'visible'   => (int)$k->visible,
                    'required'  => (boolean)$k->required,
                    'propname'  => $this->defaults[$k->field_id]['propname']
                );
                $this->categorized_fields[$k->id_field_category][] = $f;

                //array of all required fields
                if ($k->required == 1) {
                    $this->all_required[$k->field_id] = (boolean)$k->required;
                }

                //array of all fields visibility
                $this->all_visibles[$k->field_id] = (int)$k->visible;
            }
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'Fields configuration cannot be loaded!',
                Analog::URGENT
            );
            return false;
        }
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
            if (is_array($this->categorized_fields)) {
                array_walk(
                    $this->categorized_fields,
                    function ($cat) use (&$_all_fields) {
                        $field = null;
                        array_walk(
                            $cat,
                            function ($f) use (&$field) {
                                $field[$f['field_id']] = $f;
                            }
                        );
                        $_all_fields = array_merge($_all_fields, $field);
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
                        $required = $f['required'];
                        if ($required === false) {
                            $required = $this->zdb->isPostgres() ? 'false' : 0;
                        }
                        $params[] = array(
                            'field_id'    => $k,
                            'table_name'  => $this->table,
                            'required'    => $required,
                            'visible'     => $f['visible'],
                            'position'    => $f['position'],
                            'category'    => $f['category'],
                        );
                    }
                }

                if (count($params) > 0) {
                    $this->insert($params);
                    $this->load();
                }
            }
        } catch (\Exception $e) {
            Analog::log(
                '[' . $class . '] An error occured while checking update for ' .
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
     * @return boolean|Exception
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

            $fields = array_keys($this->defaults);
            foreach ($fields as $f) {
                //build default config for each field
                $required = $this->defaults[$f]['required'];
                if ($required === false) {
                    $required = $this->zdb->isPostgres() ? 'false' : 0;
                }
                $params[] = array(
                    'field_id'    => $f,
                    'table_name'  => $this->table,
                    'required'    => $required,
                    'visible'     => $this->defaults[$f]['visible'],
                    'position'    => $this->defaults[$f]['position'],
                    'category'    => $this->defaults[$f]['category'],
                );
            }
            $this->insert($params);

            Analog::log(
                'Default fields configuration were successfully stored.',
                Analog::INFO
            );
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'Unable to initialize default fields configuration.' . $e->getMessage(),
                Analog::ERROR
            );

            /*$messages = array();
            do {
                $messages[] = $e->getMessage();
            } while ($e = $e->getPrevious());

            Analog::log(
                'Unable to initialize default fields configuration.' .
                implode("\n", $messages),
                Analog::ERROR
            );*/
            return $e;
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
     * @param boolean $selfs True if we're called from self subscirption page
     *
     * @return array
     */
    public function getFormElements(Login $login, $selfs = false)
    {
        $hidden_elements = [];
        $form_elements = [];

        //get columns descriptions
        $columns = $this->zdb->getColumns($this->table);

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
                $cat = (object) array(
                    'id'        => (int)$c->$cpk,
                    'label'     => $cat_label,
                    'elements'  => array()
                );

                $elements = $this->categorized_fields[$c->$cpk];
                $cat->elements = array();

                foreach ($elements as $elt) {
                    $o = (object)$elt;

                    if (in_array($o->field_id, $this->non_form_elements)
                        || $selfs && $this->isSelfExcluded($o->field_id)
                    ) {
                        continue;
                    }

                    if (!($o->visible == self::ADMIN
                        && (!$login->isAdmin() && !$login->isStaff()) )
                    ) {
                        if ($o->visible == self::HIDDEN) {
                            $o->type = self::TYPE_HIDDEN;
                        } elseif (preg_match('/date/', $o->field_id)) {
                            $o->type = self::TYPE_DATE;
                        } elseif (preg_match('/bool/', $o->field_id)) {
                            $o->type = self::TYPE_BOOL;
                        } elseif ($o->field_id == 'titre_adh'
                            || $o->field_id == 'pref_lang'
                            || $o->field_id == 'id_statut'
                        ) {
                            $o->type = self::TYPE_SELECT;
                        } elseif ($o->field_id == 'sexe_adh') {
                            $o->type = self::TYPE_RADIO;
                        } else {
                            $o->type = self::TYPE_STR;
                        }

                        //retrieve field informations from DB
                        foreach ($columns as $column) {
                            if ($column->getName() === $o->field_id) {
                                $o->max_length
                                    = $column->getCharacterMaximumLength();
                                $o->default = $column->getColumnDefault();
                                $o->datatype = $column->getDataType();
                                break;
                            }
                        }

                        if ($o->type === self::TYPE_HIDDEN) {
                            $hidden_elements[] = $o;
                        } else {
                            $cat->elements[$o->field_id] = $o;
                        }
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
        } catch (\Exception $e) {
            Analog::log(
                'An error occured getting form elements',
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
        $display_elements = [];
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
                $cat = (object) array(
                    'id'        => (int)$c->$cpk,
                    'label'     => $cat_label,
                    'elements'  => array()
                );

                $elements = $this->categorized_fields[$c->$cpk];
                $cat->elements = array();

                foreach ($elements as $elt) {
                    $o = (object)$elt;

                    if (in_array($o->field_id, $this->non_display_elements)) {
                        continue;
                    }

                    if (!($o->visible == self::ADMIN
                        && (!$login->isAdmin() && !$login->isStaff()) )
                    ) {
                        if ($o->visible == self::HIDDEN) {
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
        } catch (\Exception $e) {
            Analog::log(
                'An error occured getting display elements',
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
                    FieldsCategories::PK    => ':category'
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
                    $params = array(
                        'required'  => $field['required'],
                        'visible'   => $field['visible'],
                        'position'  => $pos,
                        FieldsCategories::PK => $field['category'],
                        'where1'    => $field['field_id']
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
            return true;
        } catch (\Exception $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                '[' . $class . '] An error occured while storing fields ' .
                'configuration for table `' . $this->table . '`.' .
                $e->getMessage(),
                Analog::ERROR
            );
            Analog::log(
                $e->getTraceAsString(),
                Analog::ERROR
            );
            return false;
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
                /** Why where parameter is named where1 ?? */
                $stmt->execute(
                    array(
                        'required'  => ($or->required === false) ?
                            ($this->zdb->isPostgres() ? 'false' : 0) :
                            true,
                        'where1'    => $or->field_id
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
        } catch (\Exception $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                'An error occured migrating old required fields. | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            return false;
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
                'field_id'      => ':field_id',
                'table_name'    => ':table_name',
                'required'      => ':required',
                'visible'       => ':visible',
                FieldsCategories::PK => ':category',
                'position'      => ':position'
            )
        );
        $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);
        foreach ($values as $d) {
            $stmt->execute(
                array(
                    'field_id'      => $d['field_id'],
                    'table_name'    => $d['table_name'],
                    'required'      => $d['required'],
                    'visible'       => $d['visible'],
                    FieldsCategories::PK => $d['category'],
                    'position'      => $d['position']
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
}
