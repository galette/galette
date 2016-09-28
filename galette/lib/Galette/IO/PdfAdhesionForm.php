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

use Galette\Entity\Adherent;
use Galette\Entity\PdfModel;
use Galette\Entity\PdfAdhesionFormModel;
use Galette\Entity\DynamicFields;
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
    private $_adh;
    private $_values;
    private $_pdf;
    private $_model;
    private $_filename;
    private $_path;

    /**
     * Main constructor
     *
     * @param Adherent    $adh   Adherent
     * @param Db          $zdb   Database instance
     * @param Preferences $prefs Preferences instance
     */
    public function __construct($adh, $zdb, $prefs)
    {
        $this->_adh = $adh;

        if ($adh !== null) {
            $dyn_fields = new DynamicFields();
            $dyn_values = $dyn_fields->getFields('adh', $adh->id, true);
            $dyn_descriptions = $dyn_fields->getFieldsDescription('adh');
        }

        $this->_model = new PdfAdhesionFormModel($zdb, $prefs, PdfModel::ADHESION_FORM_MODEL);
        Analog::log("model id: " . $this->_model->id, Analog::DEBUG);
        Analog::log("model title: " . $this->_model->title, Analog::DEBUG);

        $dynamic_patterns = $this->_model->extractDynamicPatterns();

        $this->_model->setPatterns(
            array(
                'adh_title'         => '/{TITLE_ADH}/',
                'adh_name'          => '/{NAME_ADH}/',
                'adh_last_name'     => '/{LAST_NAME_ADH}/',
                'adh_first_name'    => '/{FIRST_NAME_ADH}/',
                'adh_nick_name'     => '/{NICKNAME_ADH}/',
                'adh_gender'        => '/{GENDER_ADH}/',
                'adh_birth_date'    => '/{ADH_BIRTH_DATE}/',
                'adh_birth_place'   => '/{ADH_BIRTH_PLACE}/',
                'adh_profession'    => '/{PROFESSION_ADH}/',
                'adh_company_name'  => '/{COMPANY_NAME_ADH}/',
                'adh_address'       => '/{ADDRESS_ADH}/',
                'adh_zip'           => '/{ZIP_ADH}/',
                'adh_town'          => '/{TOWN_ADH}/',
                'adh_country'       => '/{COUNTRY_ADH}/',
                'adh_phone'         => '/{PHONE_ADH}/',
                'adh_mobile'        => '/{MOBILE_ADH}/',
                'adh_email'         => '/{EMAIL_ADH}/',
                'adh_login'         => '/{LOGIN_ADH}/'
            )
        );

        foreach ($dynamic_patterns as $pattern) {
            $key = strtolower($pattern);
            $this->_model->setPatterns(array($key => "/\{$pattern\}/"));
            Analog::log("adding dynamic pattern $key => {" . $pattern . "}", Analog::DEBUG);
        }

        $address = $adh->address;
        if ($adh->address_continuation != '') {
            $address .= '<br/>' . $adh->adress_continuation;
        }

        if ($adh !== null) {
            if ($adh->isMan()) {
                $gender = _T("Man");
            } elseif ($adh->isWoman()) {
                $gender = _T("Woman");
            } else {
                $gender = _T("Unspecified");
            }
        }

        $this->_model->setReplacements(
            array(
                'adh_title'         => $adh->stitle,
                'adh_name'          => $adh->sfullname,
                'adh_last_name'     => $adh->surname,
                'adh_first_name'    => $adh->name,
                'adh_nickname'      => $adh->nickname,
                'adh_gender'        => $gender,
                'adh_birth_date'    => $adh->birthdate,
                'adh_birth_place'   => $adh->birth_place,
                'adh_profession'    => $adh->job,
                'adh_company_name'  => $adh->company_name,
                'adh_address'       => $address,
                'adh_zip'           => $adh->zipcode,
                'adh_town'          => $adh->town,
                'adh_country'       => $adh->country,
                'adh_phone'         => $adh->phone,
                'adh_mobile'        => $adh->gsm,
                'adh_email'         => $adh->email,
                'adh_login'         => $adh->login
            )
        );

        foreach ($dynamic_patterns as $pattern) {
            $key   = strtolower($pattern);
            $value = '';
            if (preg_match('/^DYNFIELD_([0-9]+)_ADH$/', $pattern, $match)) {
                $field_id = $match[1];
                $value    = $dyn_values[$field_id][1];
            }
            if (preg_match('/^LABEL_DYNFIELD_([0-9]+)_ADH$/', $pattern, $match)) {
                $field_id = $match[1];
                $value    = $dyn_descriptions[$field_id]['field_name'];
            }
            if (preg_match('/^INPUT_DYNFIELD_([0-9]+)_ADH$/', $pattern, $match)) {
                $field_id    = $match[1];
                $field_value = $dyn_values[$field_id][1];
                $field_name  = $dyn_descriptions[$field_id]['field_name'];
                $field_type  = $dyn_descriptions[$field_id]['field_type'];
                switch ($field_type) {
                    case DynamicFields::TEXT:
                        $value = '<textarea' .
                            ' id="'    . $field_name  . '"' .
                            ' name="'  . $field_name  . '"' .
                            ' value="' . $field_value . '"' .
                            '/>';
                        break;
                    case DynamicFields::LINE:
                        $value = '<input type="text"' .
                            ' id="'    . $field_name  . '"' .
                            ' name="'  . $field_name  . '"' .
                            ' value="' . $field_value . '"' .
                            ' size="20" maxlength="30"/>';
                        break;
                    case DynamicFields::CHOICE:
                        $choice_values = $dyn_fields->getFixedValues($field_id);
                        foreach ($choice_values as $choice_value) {
                            $value .= '<input type="radio"' .
                                ' id="'    . $field_name . '"' .
                                ' name="'  . $field_name . '"' .
                                ' value="' . $choice_value . '"';
                            if ($field_value == $choice_value) {
                                $value .= ' checked="checked"';
                            }
                            $value .= '/>';
                            $value .= $choice_value;
                            $value .= '&nbsp;';
                        }
                        break;
                    case DynamicFields::DATE:
                        $value = '<input type="text" name="' .
                            $field_name  . '" value="' .
                            $field_value . '" />';
                        break;
                    case DynamicFields::BOOLEAN:
                        $value = '<input type="checkbox"' .
                            ' name="' .  $field_name . '"' .
                            ' value="1"';
                        if ($field_value == 1) {
                            $value .= ' checked="checked"';
                        }
                        $value .= '/>';
                        break;
                    case DynamicFields::FILE:
                        $value = '<input type="text" name="' .
                            $field_name  . '" value="' .
                            $field_value . '" />';
                        break;
                }
            }

            $this->_model->setReplacements(array($key => $value));
            Analog::log("adding dynamic replacement $key => $value", Analog::DEBUG);
        }

        $this->_filename = $adh ?
            _T("adherent_form") . '.' . $adh->id . '.pdf' :
            _T("adherent_form") . '.pdf';

        $this->_pdf = new Pdf($prefs, $this->_model);

        $this->_pdf->Open();

        $this->_pdf->AddPage();
        $this->_pdf->PageHeader();
        $this->_pdf->PageBody();
    }

    /**
     * Download PDF from browser
     *
     * @return void
     */
    public function download()
    {
        $this->_pdf->Output($this->_filename, 'D');
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
            $this->_path = $path . '/' . $this->_filename;
            $this->_pdf->Output($this->_path, 'F');
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
        return realpath($this->_path);
    }
}
