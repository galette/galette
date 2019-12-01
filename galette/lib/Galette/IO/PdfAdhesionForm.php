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
 * @version   SVN: $Id$
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

class PdfAdhesionForm
{
    protected $adh;
    protected $prefs;
    protected $pdf;
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
        global $login;

        $this->adh = $adh;
        $this->prefs = $prefs;

        $model = new PdfAdhesionFormModel($zdb, $prefs, PdfModel::ADHESION_FORM_MODEL);
        Analog::log("model id: " . $model->id, Analog::DEBUG);
        Analog::log("model title: " . $model->title, Analog::DEBUG);

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

        if ($adh !== null) {
            $address = $adh->address;
            if ($adh->address_continuation != '') {
                $address .= '<br/>' . $adh->address_continuation;
            }

            if ($adh->isMan()) {
                $gender = _T("Man");
            } elseif ($adh->isWoman()) {
                $gender = _T("Woman");
            } else {
                $gender = _T("Unspecified");
            }

            $member_groups = $adh->groups;
            $main_group = _T("None");
            $group_list = _T("None");
            if (count($member_groups) > 0) {
                $main_group = $member_groups[0]->getName();
                $group_list = '<ul>';
                foreach ($member_groups as $group) {
                    $group_list .= '<li>' . $group->getName()  . '</li>';
                }
                $group_list .= '</ul>';
            }

            $model->setReplacements(
                array(
                    'adh_title'         => $adh->stitle,
                    'adh_id'            => $adh->id,
                    'adh_name'          => $adh->sfullname,
                    'adh_last_name'     => $adh->surname,
                    'adh_first_name'    => $adh->name,
                    'adh_nickname'      => $adh->nickname,
                    'adh_gender'        => $gender,
                    'adh_birth_date'    => $adh->birthdate,
                    'adh_birth_place'   => $adh->birth_place,
                    'adh_profession'    => $adh->job,
                    'adh_company'       => $adh->company_name,
                    'adh_address'       => $address,
                    'adh_zip'           => $adh->zipcode,
                    'adh_town'          => $adh->town,
                    'adh_country'       => $adh->country,
                    'adh_phone'         => $adh->phone,
                    'adh_mobile'        => $adh->gsm,
                    'adh_email'         => $adh->email,
                    'adh_login'         => $adh->login,
                    'adh_main_group'    => $main_group,
                    'adh_groups'        => $group_list
                )
            );
        }

        /** the list of all dynamic fields */
        $fields =
            new \Galette\Repository\DynamicFieldsSet($zdb, $login);
        $dynamic_fields = $fields->getList('adh');

        foreach ($dynamic_patterns as $pattern) {
            $key   = strtolower($pattern);
            $value = '';
            if (preg_match('/^DYNFIELD_([0-9]+)_ADH$/', $pattern, $match)) {
                /** dynamic field first value */
                $field_id = $match[1];
                if ($adh !== null) {
                    $values = $adh->getDynamicFields()->getValues($field_id);
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
                if ($adh !== null) {
                    $field_values = $adh->getDynamicFields()->getValues($field_id);
                    $field_value  = $field_values[0];
                }
                switch ($field_type) {
                    case DynamicField::TEXT:
                        $value .= '<textarea' .
                            ' id="'    . $field_name  . '"' .
                            ' name="'  . $field_name  . '"' .
                            ' value="' . $field_value['field_val'] . '"' .
                            '/>';
                        break;
                    case DynamicField::LINE:
                        $value .= '<input type="text"' .
                            ' id="'    . $field_name  . '"' .
                            ' name="'  . $field_name  . '"' .
                            ' value="' . $field_value['field_val'] . '"' .
                            ' size="20" maxlength="30"/>';
                        break;
                    case DynamicField::CHOICE:
                        $choice_values = $dynamic_fields[$field_id]->getValues();
                        foreach ($choice_values as $choice_idx => $choice_value) {
                            $value .= '<input type="radio"' .
                                ' id="'    . $field_name . '"' .
                                ' name="'  . $field_name . '"' .
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
                            $field_name  . '" value="' .
                            $field_value . '" />';
                        break;
                    case DynamicField::BOOLEAN:
                        $value .= '<input type="checkbox"' .
                            ' name="' .  $field_name . '"' .
                            ' value="1"';
                        if ($field_value['field_val'] == 1) {
                            $value .= ' checked="checked"';
                        }
                        $value .= '/>';
                        break;
                    case DynamicField::FILE:
                        $value .= '<input type="text" name="' .
                            $field_name  . '" value="' .
                            $field_value['field_val'] . '" />';
                        break;
                }
            }

            $model->setReplacements(array($key => $value));
            Analog::log("adding dynamic replacement $key => $value", Analog::DEBUG);
        }

        $this->filename = $adh ?
            _T("adherent_form") . '.' . $adh->id . '.pdf' :
            _T("adherent_form") . '.pdf';

        $this->pdf = new Pdf($prefs, $model);

        $this->pdf->Open();

        $this->pdf->AddPage();
        $this->pdf->PageHeader();
        $this->pdf->PageBody();
    }

    /**
     * Download PDF from browser
     *
     * @return void
     */
    public function download()
    {
        $this->pdf->Output($this->filename, 'D');
    }

    /**
     * Get filename
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
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
            $this->pdf->Output($this->path, 'F');
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
