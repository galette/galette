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

namespace Galette\Entity;

use ArrayObject;
use Throwable;
use Analog\Analog;
use Galette\Core\Login;
use Galette\Core\Authentication;

/**
 * Lists config class for galette:
 * defines fields order and visibility
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ListsConfig extends FieldsConfig
{
    /** @var array<int,array<string,mixed>> */
    protected array $listed_fields = [];

    /**
     * Fields that are not part of lists
     *
     * @var array<string>
     */
    private array $non_list_elements = [
        'mdp_adh',
        'info_adh',
        'info_public_adh',
        'nom_adh',
        'prenom_adh'
    ];

    /**
     * ACL mapping for list elements not present in form configuration
     *
     * @var array<string,string>
     */
    private array $acl_mapping = [
        'list_adh_name'             => 'nom_adh',
        'list_adh_contribstatus'    => 'id_statut'
    ];

    /**
     * Prepare a field (required data, automation)
     *
     * @param ArrayObject<string, int|string> $rset DB ResultSet row
     *
     * @return array<string, int|string>
     */
    protected function buildField(ArrayObject $rset): array
    {
        $f = parent::buildField($rset);
        $f['list_position'] = (int)$rset->list_position;
        $f['list_visible'] = ($f['list_position'] >= 0);
        $f['width_in_forms'] = (int)$rset->width_in_forms;
        return $f;
    }

    /**
     * Create field array configuration
     * Several lists of fields are kept (visible, required, etc), build them.
     *
     * @return void
     */
    protected function buildLists(): void
    {
        //Specific list fields does not have rights; fix this from mapping
        //Cannot be done preparing fields, cannot be sure of the order it is processed
        foreach ($this->acl_mapping as $list_key => $field_key) {
            $this->core_db_fields[$list_key]['visible'] = $this->core_db_fields[$field_key]['visible'];
        }

        //handle parent field: is always inactive on form. Hardcode to STAFF.
        if (isset($this->core_db_fields['parent_id'])) {
            $this->core_db_fields['parent_id']['visible'] = self::STAFF;
        }

        parent::buildLists();
        //make sure array order is the same as in the database, since query is ordered differently
        ksort($this->listed_fields);
    }

    /**
     * Adds a field to lists
     *
     * @param array<string,mixed> $field Field values
     *
     * @return void
     */
    protected function addToLists(array $field): void
    {
        if (in_array($field['field_id'], $this->non_list_elements)) {
            return;
        }
        parent::addToLists($field);

        if ($field['list_visible'] ?? false) {
            $this->listed_fields[(int)$field['list_position']] = $field;
        }
    }

    /**
     * Retrieve display elements
     *
     * @param Login $login Login instance
     *
     * @return array<int,object>
     */
    public function getDisplayElements(Login $login): array
    {
        $display_elements = [];
        $access_level = $login->getAccessLevel();
        try {
            $elements = $this->listed_fields;

            foreach ($elements as $elt) {
                $o = (object)$elt;
                $this->handleLabel($o);

                if ($o->field_id == 'id_adh') {
                    // ignore access control, as member ID is always needed
                    $o->type = self::TYPE_STR;
                    $display_elements[] = $o;
                } else {
                    // skip fields blacklisted for display
                    if (in_array($o->field_id, $this->non_list_elements)) {
                        continue;
                    }

                    // skip fields according to access control
                    if (
                        $o->visible == self::NOBODY
                        || ($o->visible == self::ADMIN
                            && $access_level < Authentication::ACCESS_ADMIN)
                        || ($o->visible == self::STAFF
                            && $access_level < Authentication::ACCESS_STAFF)
                        || ($o->visible == self::MANAGER
                            && $access_level < Authentication::ACCESS_MANAGER)
                    ) {
                        continue;
                    }
                    $display_elements[] = $o;
                }
            }

            return $display_elements;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred getting list elements to display',
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Handle list labels
     *
     * @param object $field Field data
     *
     * @return void
     */
    private function handleLabel(object $field): void
    {
        switch ($field->field_id) {
            case 'bool_admin_adh':
                $field->label = __('Is admin');
                break;
            case 'date_modif_adh':
                $field->label = _T('Modified');
                break;
            case 'tel_adh':
                $field->label = _T('Phone');
                break;
            case 'bool_display_info':
                $field->label = _T('Public');
                break;
        }

        $field->label = trim(str_replace('&nbsp;', ' ', $field->label));
        $field->label = preg_replace('/\s?:$/', '', $field->label);
    }

    /**
     * Get all fields for list
     *
     * @return array<int,array<string,mixed>>
     */
    public function getListedFields(): array
    {
        return $this->listed_fields;
    }

    /**
     * Get remaining free fields for list
     *
     * @return array<string,array<string,mixed>>
     */
    public function getRemainingFields(): array
    {
        $db_fields = $this->core_db_fields;

        //remove non list
        foreach ($this->non_list_elements as $todrop) {
            unset($db_fields[$todrop]);
        }

        //remove already listed
        foreach ($this->listed_fields as $listed) {
            unset($db_fields[$listed['field_id']]);
        }

        $remainings = [];
        foreach ($db_fields as $key => $db_field) {
            $remainings[$key] = $db_field;
        }

        return $remainings;
    }

    /**
     * Set fields
     *
     * @param array<int,array<string,mixed>> $fields categorized fields array
     *
     * @return boolean
     */
    public function setListFields(array $fields): bool
    {
        $this->listed_fields = $fields;
        return $this->storeList();
    }

    /**
     * Store list config in database
     *
     * @return boolean
     */
    private function storeList(): bool
    {
        $class = get_class($this);

        try {
            if (!count($this->listed_fields)) {
                throw new \RuntimeException('No fields for list, aborting.');
            }

            $this->zdb->connection->beginTransaction();

            $update = $this->zdb->update(self::TABLE);
            $update->set(
                [
                    'list_visible'          => ':list_visible',
                    'list_position'         => ':list_position',
                    'width_in_forms'        => ':width_in_forms'
                ]
            )->where(
                [
                    'field_id'      => ':field_id',
                    'table_name'    => $this->table
                ]
            );
            $stmt = $this->zdb->sql->prepareStatementForSqlObject($update);

            $params = null;

            foreach ($this->listed_fields as $pos => $field) {
                $params = [
                    'list_visible'   => $field['list_visible'],
                    'list_position'  => $pos,
                    'field_id'       => $field['field_id'],
                    'width_in_forms' => $field['width_in_forms']
                ];
                $stmt->execute($params);
            }

            foreach (array_keys($this->getRemainingFields()) as $field) {
                $params = [
                    'list_visible'  => $this->zdb->isPostgres() ? 'false' : 0,
                    'list_position' => -1,
                    'field_id'      => $field
                ];
                $stmt->execute($params);
            }

            Analog::log(
                str_replace(
                    '%s',
                    $this->table,
                    '[' . $class . '] List configuration for table %s stored '
                    . 'successfully.'
                ),
                Analog::INFO
            );

            $this->zdb->connection->commit();
            return $this->load();
        } catch (Throwable $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                '[' . $class . '] An error occurred while storing list '
                . 'configuration for table `' . $this->table . '`.'
                . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Get ACL mapping for list elements not present in form configuration
     *
     * @return array<string,string>
     */
    public function getAclMapping(): array
    {
        return $this->acl_mapping;
    }

    /**
     * Get visibility for specified field
     *
     * @param string $field The requested field
     *
     * @return integer
     */
    public function getVisibility(string $field): int
    {
        if (in_array($field, $this->non_list_elements)) {
            return self::NOBODY;
        }
        return $this->all_visibles[$field];
    }
}
