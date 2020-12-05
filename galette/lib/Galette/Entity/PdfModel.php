<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * PDF Model
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
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-02-19
 */

namespace Galette\Entity;

use Throwable;
use Galette\Core;
use Galette\Core\Db;
use Galette\Core\Preferences;
use Galette\DynamicFields\DynamicField;
use Galette\Repository\PdfModels;
use Analog\Analog;
use Laminas\Db\Sql\Expression;

/**
 * PDF Model
 *
 * @category  Entity
 * @name      PdfModel
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-02-19
 */

abstract class PdfModel
{
    public const TABLE = 'pdfmodels';
    public const PK = 'model_id';

    public const MAIN_MODEL = 1;
    public const INVOICE_MODEL = 2;
    public const RECEIPT_MODEL = 3;
    public const ADHESION_FORM_MODEL = 4;

    protected $zdb;

    private $id;
    private $name;
    private $type;
    private $header;
    private $footer;
    private $title;
    private $subtitle;
    private $body;
    private $styles;
    private $parent;

    private $patterns = [];
    private $replaces = [];
    private $dynamic_patterns = [];

    /**
     * Main constructor
     *
     * @param Db          $zdb         Database instance
     * @param Preferences $preferences Galette preferences
     * @param int         $type        Model type
     * @param mixed       $args        Arguments
     */
    public function __construct(Db $zdb, Preferences $preferences, $type, $args = null)
    {
        global $container;
        $router = $container->get('router');
        $this->zdb = $zdb;
        $this->type = $type;

        if (is_int($args)) {
            $this->load($args, $preferences);
        } elseif ($args !== null && is_object($args)) {
            $this->loadFromRs($args, $preferences);
        } else {
            $this->load($type, $preferences);
        }

        $this->setPatterns($this->getMainPatterns());

        $address = $preferences->getPostalAddress();
        $address_multi = preg_replace("/\n/", "<br>", $address);

        $website = '';
        if ($preferences->pref_website != '') {
            $website = '<a href="' . $preferences->pref_website . '">' .
                $preferences->pref_website . '</a>';
        }

        $logo = new Core\Logo();
        $logo_elt = '<img' .
            ' src="' . $preferences->getURL() . $router->pathFor('logo') . '"' .
            ' width="' . $logo->getOptimalWidth() . '"' .
            ' height="' . $logo->getOptimalHeight() . '"' .
            '/>';

        $this->replaces = array(
            'asso_name'          => $preferences->pref_nom,
            'asso_slogan'        => $preferences->pref_slogan,
            'asso_address'       => $address,
            'asso_address_multi' => $address_multi,
            'asso_website'       => $website,
            'asso_logo'          => $logo_elt,
            'date_now'           => date(_T('Y-m-d'))
        );
    }

    /**
     * Load a Model from its identifier
     *
     * @param int         $id          Identifier
     * @param Preferences $preferences Galette preferences
     * @param boolean     $init        Init data if required model is missing
     *
     * @return void
     */
    protected function load($id, $preferences, $init = true)
    {
        global $login;

        try {
            $select = $this->zdb->select(self::TABLE);
            $select->limit(1)
                ->where(self::PK . ' = ' . $id);

            $results = $this->zdb->execute($select);

            $count = $results->count();
            if ($count === 0) {
                if ($init === true) {
                    $models = new PdfModels($this->zdb, $preferences, $login);
                    $models->installInit();
                    $this->load($id, $preferences, false);
                } else {
                    throw new \RuntimeException('Model not found!');
                }
            } else {
                $this->loadFromRs($results->current(), $preferences);
            }
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred loading model #' . $id . "Message:\n" .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Load model from a db ResultSet
     *
     * @param ResultSet   $rs          ResultSet
     * @param Preferences $preferences Galette preferences
     *
     * @return void
     */
    protected function loadFromRs($rs, $preferences)
    {
        $pk = self::PK;
        $this->id = (int)$rs->$pk;

        $callback = function ($matches) {
            return _T($matches[1]);
        };
        $this->name = preg_replace_callback(
            '/_T\("([^\"]+)"\)/',
            $callback,
            $rs->model_name
        );

        $this->title = $rs->model_title;
        $this->subtitle = $rs->model_subtitle;
        $this->header = $rs->model_header;
        $this->footer = $rs->model_footer;
        $this->body = $rs->model_body;
        $this->styles .= $rs->model_styles;

        if ($this->id > self::MAIN_MODEL) {
            //FIXME: for now, parent will always be a PdfMain
            $this->parent = new PdfMain(
                $this->zdb,
                $preferences,
                (int)$rs->model_parent
            );
        }
    }

    /**
     * Store model in database
     *
     * @return boolean
     */
    public function store()
    {
        $title = $this->title;
        if (trim($title === '')) {
            $title = new Expression('NULL');
        }

        $subtitle = $this->subtitle;
        if (trim($subtitle === '')) {
            $subtitle = new Expression('NULL');
        }

        $data = array(
            'model_header'      => $this->header,
            'model_footer'      => $this->footer,
            'model_type'        => $this->type,
            'model_title'       => $title,
            'model_subtitle'    => $subtitle,
            'model_body'        => $this->body,
            'model_styles'      => $this->styles
        );

        try {
            if ($this->id !== null) {
                $update = $this->zdb->update(self::TABLE);
                $update->set($data)->where(
                    self::PK . '=' . $this->id
                );
                $this->zdb->execute($update);
            } else {
                $data['model_name'] = $this->name;
                $insert = $this->zdb->insert(self::TABLE);
                $insert->values($data);
                $add = $this->zdb->execute($insert);
                if (!$add->count() > 0) {
                    Analog::log('Not stored!', Analog::ERROR);
                    return false;
                }
            }
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred storing model: ' . $e->getMessage() .
                "\n" . print_r($data, true),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Get object class for specified type
     *
     * @param int $type Type
     *
     * @return Class
     */
    public static function getTypeClass($type)
    {
        $class = null;
        switch ($type) {
            case self::INVOICE_MODEL:
                $class = 'PdfInvoice';
                break;
            case self::RECEIPT_MODEL:
                $class = 'PdfReceipt';
                break;
            case self::ADHESION_FORM_MODEL:
                $class = 'PdfAdhesionFormModel';
                break;
            default:
                $class = 'PdfMain';
                break;
        }
        $class = 'Galette\\Entity\\' . $class;
        return $class;
    }

    /**
     * Check lenght
     *
     * @param string  $value The value
     * @param int     $chars Lenght
     * @param string  $field Field name
     * @param boolean $empty Can value be empty
     *
     * @return void
     */
    protected function checkChars($value, $chars, $field, $empty = false)
    {
        if ($value !== null && trim($value) !== '') {
            if (mb_strlen($value) > $chars) {
                throw new \LengthException(
                    str_replace(
                        array('%field', '%chars'),
                        array($field, $chars),
                        _T("%field should be less than %chars characters long.")
                    )
                );
            }
        } else {
            if ($empty === false) {
                throw new \UnexpectedValueException(
                    str_replace(
                        '%field',
                        $field,
                        _T("%field should not be empty!")
                    )
                );
            }
        }
    }

    /**
     * Get dynamic patterns
     *
     * @param string $form_name Dynamic form name
     *
     * @return array
     */
    public function getDynamicPatterns($form_name): array
    {
        global $login;

        if (isset($this->dynamic_patterns[$form_name])) {
            return $this->dynamic_patterns[$form_name];
        }

        $fields = new \Galette\Repository\DynamicFieldsSet($this->zdb, $login);
        $dynamic_fields = $fields->getList($form_name);

        $dynamic_patterns = [];
        foreach ($dynamic_fields as $dynamic_field) {
            $key = strtoupper('_DYNFIELD_' . $dynamic_field->getId() . '_' . $form_name);
            foreach (['LABEL', 'INPUT'] as $capability) {
                $dynamic_patterns[$capability . $key] = [
                    'title' => sprintf(
                        ($capability == 'LABEL' ? _T('Label for dynamic field "%s"')
                        : _T('Input for dynamic field "%s"')),
                        $dynamic_field->getName()
                    ),
                    'pattern'   => sprintf(
                        '/{%s%s}/',
                        $capability,
                        $key
                    )
                ];
            }
        }

        $this->dynamic_patterns[$form_name] = $dynamic_patterns;
        return $this->dynamic_patterns[$form_name];
    }

    /**
     * Set patterns
     *
     * @param array $patterns Patterns to add
     *
     * @return void
     */
    protected function setPatterns($patterns): PdfModel
    {
        $toset = [];
        foreach ($patterns as $key => $info) {
            if (is_array($info)) {
                $toset[$key] = $info['pattern'];
            } else {
                $toset[$key] = $info;
            }
        }

        $this->patterns = array_merge(
            $this->patterns,
            $toset
        );

        return $this;
    }

    /**
     * Set replacements
     *
     * @param array $replaces Replacements to add
     *
     * @return void
     */
    public function setReplacements($replaces)
    {
        $this->replaces = array_merge(
            $this->replaces,
            $replaces
        );
    }

    /**
     * Getter
     *
     * @param string $name Property name
     *
     * @return mixed
     */
    public function __get($name)
    {
        global $lang;

        switch ($name) {
            case 'name':
            case 'id':
            case 'header':
            case 'footer':
            case 'body':
            case 'title':
            case 'subtitle':
            case 'type':
            case 'styles':
            case 'patterns':
            case 'replaces':
                return $this->$name;
                break;
            case 'hstyles':
                $value = null;

                //get header and footer from parent if not defined in current model
                if (
                    $this->id > self::MAIN_MODEL
                    && $this->parent !== null
                ) {
                    $value = $this->parent->styles;
                }

                $value .= $this->styles;
                return $value;
                break;
            case 'hheader':
            case 'hfooter':
            case 'htitle':
            case 'hsubtitle':
            case 'hbody':
                $pname = substr($name, 1);
                $prop_value = $this->$pname;

                //get header and footer from parent if not defined in current model
                if (
                    $this->id > self::MAIN_MODEL
                    && $this->parent !== null
                    && ($pname === 'footer'
                    || $pname === 'header')
                    && trim($prop_value) === ''
                ) {
                    $prop_value = $this->parent->$pname;
                }

                //handle translations
                $callback = static function ($matches) {
                    return _T($matches[1]);
                };
                $value = preg_replace_callback(
                    '/_T\("([^\"]+)"\)/',
                    $callback,
                    $prop_value
                );

                //handle replacements
                $value = preg_replace(
                    $this->patterns,
                    $this->replaces,
                    $value
                );

                //handle translations with replacements
                $repl_callback = function ($matches) {
                    return str_replace(
                        $matches[1],
                        $matches[2],
                        $matches[3]
                    );
                };
                $value = preg_replace_callback(
                    '/str_replace\(\'([^,]+)\', ?\'([^,]+)\', ?\'(.*)\'\)/',
                    $repl_callback,
                    $value
                );

                return $value;
                break;
            default:
                Analog::log(
                    'Unable to get PdfModel property ' . $name,
                    Analog::WARNING
                );
                break;
        }
    }

    /**
     * Setter
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     *
     * @return void
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'type':
                if (
                    $value === self::MAIN_MODEL
                    || $value === self::INVOICE_MODEL
                    || $value === self::RECEIPT_MODEL
                    || $value === self::ADHESION_FORM_MODEL
                ) {
                    $this->$name = $value;
                } else {
                    throw new \UnexpectedValueException(
                        str_replace(
                            '%type',
                            $value,
                            _T("Unknown type %type!")
                        )
                    );
                }
                break;
            case 'name':
                try {
                    $this->checkChars($value, 50, _T("Name"));
                    $this->$name = $value;
                } catch (Throwable $e) {
                    throw $e;
                }
                break;
            case 'title':
            case 'subtitle':
                if ($name == 'title') {
                    $field = _T("Title");
                } else {
                    $field = _T("Subtitle");
                }
                try {
                    $this->checkChars($value, 100, $field, true);
                    $this->$name = $value;
                } catch (Throwable $e) {
                    throw $e;
                }
                break;
            case 'header':
            case 'footer':
            case 'body':
                if ($value === null || trim($value) === '') {
                    if ($name !== 'body' && get_class($this) === 'PdfMain') {
                        throw new \UnexpectedValueException(
                            _T("header and footer should not be empty!")
                        );
                    } elseif ($name === 'body' && get_class($this) !== 'PdfMain') {
                        throw new \UnexpectedValueException(
                            _T("body should not be empty!")
                        );
                    }
                }

                $this->$name = $value;
                break;
            case 'styles':
                $this->styles = $value;
                break;
            default:
                Analog::log(
                    'Unable to set PdfModel property ' . $name,
                    Analog::WARNING
                );
                break;
        }
    }

    /**
     * Get main patterns
     *
     * @return array
     */
    protected function getMainPatterns(): array
    {
        return [
            'asso_name'             => [
                'title' => _T('Your organisation name'),
                'pattern'   => '/{ASSO_NAME}/'
            ],
            'asso_slogan'           => [
                'title'     => _T('Your organisation slogan'),
                'pattern'   => '/{ASSO_SLOGAN}/'
            ],
            'asso_address'          => [
                'title'     => _T('Your organisation address'),
                'pattern'   => '/{ASSO_ADDRESS}/',
            ],
            'asso_address_multi'    => [
                'title'     => sprintf('%s (%s)', _T('Your organisation address'), _T('with break lines')),
                'pattern'   => '/{ASSO_ADDRESS_MULTI}/',
            ],
            'asso_website'          => [
                'title'     => _T('Your organisation website'),
                'pattern'   => '/{ASSO_WEBSITE}/',
            ],
            'asso_logo'             => [
                'title'     => _T('Your organisation logo'),
                'pattern'          => '/{ASSO_LOGO}/',
            ],
            'date_now'              => [
                'title'     => _T('Current date (Y-m-d)'),
                'pattern'   => '/{DATE_NOW}/'
            ]
        ];
    }

    /**
     * Get patterns for a member
     *
     * @return array
     */
    protected function getMemberPatterns(): array
    {
        $dynamic_patterns = $this->getDynamicPatterns('adh');
        return [
            'adh_title'         => [
                'title'     => _('Title'),
                'pattern'   => '/{TITLE_ADH}/',
            ],
            'adh_id'            =>  [
                'title'     => _T("Member's ID"),
                'pattern'   => '/{ID_ADH}/',
            ],
            'adh_name'          =>  [
                'title'     => _T("Name"),
                'pattern'    => '/{NAME_ADH}/',
            ],
            'adh_last_name'     =>  [
                'title'     => _T('Last name'),
                'pattern'   => '/{LAST_NAME_ADH}/',
            ],
            'adh_first_name'    =>  [
                'title'     => _T('First name'),
                'pattern'   => '/{FIRST_NAME_ADH}/',
            ],
            'adh_nickname'      =>  [
                'title'     => _T('Nickname'),
                'pattern'   => '/{NICKNAME_ADH}/',
            ],
            'adh_gender'        =>  [
                'title'     => _T('Gender'),
                'pattern'   => '/{GENDER_ADH}/',
            ],
            'adh_birth_date'    =>  [
                'title'     => _T('Birth date'),
                'pattern'   => '/{ADH_BIRTH_DATE}/',
            ],
            'adh_birth_place'   =>  [
                'title'     => _T('Birth place'),
                'pattern'   => '/{ADH_BIRTH_PLACE}/',
            ],
            'adh_profession'    =>  [
                'title'     => _T('Profession'),
                'pattern'   => '/{PROFESSION_ADH}/',
            ],
            'adh_company'       => [
                'title'     => _T("Company name"),
                'pattern'   => '/{COMPANY_ADH}/',
            ],
            'adh_address'       =>  [
                'title'     => _T("Address"),
                'pattern'   => '/{ADDRESS_ADH}/',
            ],
            'adh_zip'           =>  [
                'title'     => _T("Zipcode"),
                'pattern'   => '/{ZIP_ADH}/',
            ],
            'adh_town'          =>  [
                'title'     => _T("Town"),
                'pattern'   => '/{TOWN_ADH}/',
            ],
            'adh_country'       =>  [
                'title'     => _T('Country'),
                'pattern'   => '/{COUNTRY_ADH}/',
            ],
            'adh_phone'         =>  [
                'title'     => _T('Phone'),
                'pattern'   => '/{PHONE_ADH}/',
            ],
            'adh_mobile'        =>  [
                'title'     => _T('GSM'),
                'pattern'   => '/{MOBILE_ADH}/',
            ],
            'adh_email'         =>  [
                'title'     => _T('Email'),
                'pattern'   => '/{EMAIL_ADH}/',
            ],
            'adh_login'         =>  [
                'title'     => _T('Login'),
                'pattern'   => '/{LOGIN_ADH}/',
            ],
            'adh_main_group'    =>  [
                'title'     => _T("Member's main group"),
                'pattern'   => '/{GROUP_ADH}/',
            ],
            'adh_groups'        =>  [
                'title'     => _T("Member's groups (as list)"),
                'pattern'   => '/{GROUPS_ADH}/'
            ],
        ] + $dynamic_patterns;
    }

    /**
     * Set member and proceed related replacements
     *
     * @param Adherent $member Member
     *
     * @return PdfModel
     */
    public function setMember(Adherent $member): PdfModel
    {
        global $login;

        $address = $member->address;
        if ($member->address_continuation != '') {
            $address .= '<br/>' . $member->address_continuation;
        }

        if ($member->isMan()) {
            $gender = _T("Man");
        } elseif ($member->isWoman()) {
            $gender = _T("Woman");
        } else {
            $gender = _T("Unspecified");
        }

        $member_groups = $member->groups;
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

        $this->setReplacements(
            array(
                'adh_title'         => $member->stitle,
                'adh_id'            => $member->id,
                'adh_name'          => $member->sfullname,
                'adh_last_name'     => $member->name,
                'adh_first_name'    => $member->surname,
                'adh_nickname'      => $member->nickname,
                'adh_gender'        => $gender,
                'adh_birth_date'    => $member->birthdate,
                'adh_birth_place'   => $member->birth_place,
                'adh_profession'    => $member->job,
                'adh_company'       => $member->company_name,
                'adh_address'       => $address,
                'adh_zip'           => $member->zipcode,
                'adh_town'          => $member->town,
                'adh_country'       => $member->country,
                'adh_phone'         => $member->phone,
                'adh_mobile'        => $member->gsm,
                'adh_email'         => $member->email,
                'adh_login'         => $member->login,
                'adh_main_group'    => $main_group,
                'adh_groups'        => $group_list
            )
        );

        /** the list of all dynamic fields */
        $fields = new \Galette\Repository\DynamicFieldsSet($this->zdb, $login);
        $dynamic_fields = $fields->getList('adh');
        $this->setDynamicFields('adh', $dynamic_fields, $member);

        return $this;
    }

    /**
     * Set dynamic fields and proceed related replacements
     *
     * @param string $form_name      Form name
     * @param array  $dynamic_fields Dynamic fields
     * @param mixed  $object         Related object (Adherent, Contribution, ...)
     *
     * @return PdfModel
     */
    public function setDynamicFields($form_name, $dynamic_fields, $object): PdfModel
    {
        $uform_name = strtoupper($form_name);
        $dynamic_patterns = $this->getDynamicPatterns($form_name);
        foreach ($dynamic_patterns as $dynamic_pattern) {
            $pattern = trim($dynamic_pattern['pattern'], '/');
            $key   = strtolower(rtrim(ltrim($pattern, '{'), '}'));
            $value = '';
            if (preg_match("/^{DYNFIELD_([0-9]+)_$uform_name}$/", $pattern, $match)) {
                /** dynamic field first value */
                $field_id = $match[1];
                $values = $object->getDynamicFields()->getValues($field_id);
                $value  = $values[1];
            }
            if (preg_match("/^{LABEL_DYNFIELD_([0-9]+)_$uform_name}$/", $pattern, $match)) {
                /** dynamic field label */
                $field_id = $match[1];
                $value    = $dynamic_fields[$field_id]->getName();
            }
            if (preg_match("/^{INPUT_DYNFIELD_([0-9]+)_$uform_name}$/", $pattern, $match)) {
                /** dynamic field input form element */
                $field_id    = $match[1];
                $field_name  = $dynamic_fields[$field_id]->getName();
                $field_type  = $dynamic_fields[$field_id]->getType();
                $field_values = $object->getDynamicFields()->getValues($field_id);
                $field_value  = $field_values[0];

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
                            $field_value['field_val'] .
                            '" size="10" />';
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

            $this->setReplacements(array($key => $value));
            Analog::log("adding dynamic replacement $key => $value", Analog::DEBUG);
        }

        return $this;
    }

    /**
     * Build legend array
     *
     * @return array
     */
    public function getLegend(): array
    {
        $legend = [];

        $legend['main'] = [
            'title'     => _T('Main information'),
            'patterns'  => $this->getMainPatterns()
        ];

        $legend['member'] = [
            'title'     => _T('Member information'),
            'patterns'  => $this->getMemberPatterns()
        ];

        return $legend;
    }
}
