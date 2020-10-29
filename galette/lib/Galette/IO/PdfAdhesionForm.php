<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Adhesion form PDF
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
 * @author    Guillaume Rousse <guillomovitch@gmail.com>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-07-07
 */

namespace Galette\IO;

use Galette\Core\Db;
use Galette\Core\Preferences;
use Galette\DynamicFields\DynamicField;
use Galette\Entity\Adherent;
use Galette\Entity\PdfModel;
use Galette\Entity\PdfAdhesionFormModel;
use Galette\Entity\DynamicFieldsHandle;
use Galette\IO\Pdf;
use Analog\Analog;

/**
 * Adhesion Form PDF
 *
 * @category  IO
 * @name      PDF
 * @package   Galette
 * @abstract  Class for expanding TCPDF.
 * @author    Guillaume Rousse <guillomovitch@gmail.com>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-07-07
 */

class PdfAdhesionForm extends Pdf
{
    protected $zdb;
    protected $adh;
    protected $prefs;
    protected $filename;
    private $path;

    /**
     * Main constructor
     *
     * @param Adherent    $adh   Adherent
     * @param Db          $zdb   Database instance
     * @param Preferences $prefs Preferences instance
     */
    public function __construct(Adherent $adh = null, Db $zdb, Preferences $prefs)
    {
        $this->zdb = $zdb;
        $this->adh = $adh;
        $this->prefs = $prefs;

        $model = $this->getModel();
        parent::__construct($prefs, $model);

        $this->filename = $adh ?
            __("adherent_form") . '.' . $adh->id . '.pdf' : __("adherent_form") . '.pdf';

        $this->Open();

        $this->AddPage();
        if ($model !== null) {
            $this->PageHeader();
            $this->PageBody();
        }
    }

    /**
     * Get model
     *
     * @return PdfModel
     */
    protected function getModel()
    {
        global $login;

        $model = new PdfAdhesionFormModel($this->zdb, $this->prefs, PdfModel::ADHESION_FORM_MODEL);
        $dynamic_patterns = $model->extractDynamicPatterns();

        $model->setPatterns(
            array(
                'adh_title'         => '/{TITLE_ADH}/',
                'adh_id'            => '/{ID_ADH}/',
                'adh_name'          => '/{NAME_ADH}/',
                'adh_last_name'     => '/{LAST_NAME_ADH}/',
                'adh_first_name'    => '/{FIRST_NAME_ADH}/',
                'adh_nick_name'     => '/{NICKNAME_ADH}/',
                'adh_gender'        => '/{GENDER_ADH}/',
                'adh_birth_date'    => '/{ADH_BIRTH_DATE}/',
                'adh_birth_place'   => '/{ADH_BIRTH_PLACE}/',
                'adh_profession'    => '/{PROFESSION_ADH}/',
                'adh_company'       => '/{COMPANY_ADH}/',
                'adh_address'       => '/{ADDRESS_ADH}/',
                'adh_zip'           => '/{ZIP_ADH}/',
                'adh_town'          => '/{TOWN_ADH}/',
                'adh_country'       => '/{COUNTRY_ADH}/',
                'adh_phone'         => '/{PHONE_ADH}/',
                'adh_mobile'        => '/{MOBILE_ADH}/',
                'adh_email'         => '/{EMAIL_ADH}/',
                'adh_login'         => '/{LOGIN_ADH}/',
                'adh_main_group'    => '/{GROUP_ADH}/',
                'adh_groups'        => '/{GROUPS_ADH}/'
            )
        );

        foreach ($dynamic_patterns as $pattern) {
            $key = strtolower($pattern);
            $model->setPatterns(array($key => "/\{$pattern\}/"));
            Analog::log("adding dynamic pattern $key => {" . $pattern . "}", Analog::DEBUG);
        }

        if ($this->adh !== null) {
            $address = $this->adh->address;
            if ($this->adh->address_continuation != '') {
                $address .= '<br/>' . $this->adh->address_continuation;
            }

            if ($this->adh->isMan()) {
                $gender = _T("Man");
            } elseif ($this->adh->isWoman()) {
                $gender = _T("Woman");
            } else {
                $gender = _T("Unspecified");
            }

            $member_groups = $this->adh->groups;
            $main_group = _T("None");
            $group_list = _T("None");
            if (is_array($member_groups) && count($member_groups) > 0) {
                $main_group = $member_groups[0]->getName();
                $group_list = '<ul>';
                foreach ($member_groups as $group) {
                    $group_list .= '<li>' . $group->getName() . '</li>';
                }
                $group_list .= '</ul>';
            }

            $model->setReplacements(
                array(
                    'adh_title'         => $this->adh->stitle,
                    'adh_id'            => $this->adh->id,
                    'adh_name'          => $this->adh->sfullname,
                    'adh_last_name'     => $this->adh->name,
                    'adh_first_name'    => $this->adh->surname,
                    'adh_nickname'      => $this->adh->nickname,
                    'adh_gender'        => $gender,
                    'adh_birth_date'    => $this->adh->birthdate,
                    'adh_birth_place'   => $this->adh->birth_place,
                    'adh_profession'    => $this->adh->job,
                    'adh_company'       => $this->adh->company_name,
                    'adh_address'       => $address,
                    'adh_zip'           => $this->adh->zipcode,
                    'adh_town'          => $this->adh->town,
                    'adh_country'       => $this->adh->country,
                    'adh_phone'         => $this->adh->phone,
                    'adh_mobile'        => $this->adh->gsm,
                    'adh_email'         => $this->adh->email,
                    'adh_login'         => $this->adh->login,
                    'adh_main_group'    => $main_group,
                    'adh_groups'        => $group_list
                )
            );
        }

        /** the list of all dynamic fields */
        $fields =
            new \Galette\Repository\DynamicFieldsSet($this->zdb, $login);
        $dynamic_fields = $fields->getList('adh');

        foreach ($dynamic_patterns as $pattern) {
            $key   = strtolower($pattern);
            $value = '';
            if (preg_match('/^DYNFIELD_([0-9]+)_ADH$/', $pattern, $match)) {
                /** dynamic field first value */
                $field_id = $match[1];
                if ($this->adh !== null) {
                    $values = $this->adh->getDynamicFields()->getValues($field_id);
                    $value  = $values[1];
                }
            }
            if (preg_match('/^LABEL_DYNFIELD_([0-9]+)_ADH$/', $pattern, $match)) {
                /** dynamic field label */
                $field_id = $match[1];
                $value    = $dynamic_fields[$field_id]->getName();
            }
            if (preg_match('/^INPUT_DYNFIELD_([0-9]+)_ADH$/', $pattern, $match)) {
                /** dynamic field input form element */
                $field_id    = $match[1];
                $field_name  = $dynamic_fields[$field_id]->getName();
                $field_type  = $dynamic_fields[$field_id]->getType();
                $field_value = ['field_val' => ''];
                if ($this->adh !== null) {
                    $field_values = $this->adh->getDynamicFields()->getValues($field_id);
                    $field_value  = $field_values[0];
                }
                switch ($field_type) {
                    case DynamicField::TEXT:
                        $value .= '<textarea' .
                            ' id="' . $field_name . '"' .
                            ' name="' . $field_name . '"' .
                            ' value="' . $field_value['field_val'] . '"' .
                            '/>';
                        break;
                    case DynamicField::LINE:
                        $value .= '<input type="text"' .
                            ' id="' . $field_name . '"' .
                            ' name="' . $field_name . '"' .
                            ' value="' . $field_value['field_val'] . '"' .
                            ' size="20" maxlength="30"/>';
                        break;
                    case DynamicField::CHOICE:
                        $choice_values = $dynamic_fields[$field_id]->getValues();
                        foreach ($choice_values as $choice_idx => $choice_value) {
                            $value .= '<input type="radio"' .
                                ' id="' . $field_name . '"' .
                                ' name="' . $field_name . '"' .
                                ' value="' . $choice_value . '"';
                            if ($choice_idx == $field_values[0]['field_val']) {
                                $value .= ' checked="checked"';
                            }
                            $value .= '/>';
                            $value .= $choice_value;
                            $value .= '&nbsp;';
                        }
                        break;
                    case DynamicField::DATE:
                        $value .= '<input type="text" name="' .
                            $field_name . '" value="' .
                            $field_value . '" />';
                        break;
                    case DynamicField::BOOLEAN:
                        $value .= '<input type="checkbox"' .
                            ' name="' . $field_name . '"' .
                            ' value="1"';
                        if ($field_value['field_val'] == 1) {
                            $value .= ' checked="checked"';
                        }
                        $value .= '/>';
                        break;
                    case DynamicField::FILE:
                        $value .= '<input type="text" name="' .
                            $field_name . '" value="' .
                            $field_value['field_val'] . '" />';
                        break;
                }
            }

            $model->setReplacements(array($key => $value));
            Analog::log("adding dynamic replacement $key => $value", Analog::DEBUG);
        }

        return $model;
    }

    /**
     * Store PDF
     *
     * @param string $path Path
     *
     * @return boolean
     */
    public function store($path)
    {
        if (file_exists($path) && is_dir($path) && is_writeable($path)) {
            $this->path = $path . '/' . $this->filename;
            $this->Output($this->path, 'F');
            return true;
        } else {
            Analog::log(
                __METHOD__ . ' ' . $path .
                ' does not exists or is not a directory or is not writeable.',
                Analog::ERROR
            );
        }
        return false;
    }

    /**
     * Get store path
     *
     * @return string
     */
    public function getPath()
    {
        return realpath($this->path);
    }
}
