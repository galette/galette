<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

namespace Galette\Features;

use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Core\Logo;
use Galette\Core\Preferences;
use Galette\DynamicFields\Choice;
use Galette\DynamicFields\Separator;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\PdfModel;
use Galette\Entity\Reminder;
use Galette\Entity\Texts;
use Galette\Repository\DynamicFieldsSet;
use Galette\DynamicFields\DynamicField;
use Analog\Analog;
use NumberFormatter;
use PHPMailer\PHPMailer\PHPMailer;
use Slim\Routing\RouteParser;
use DI\Attribute\Inject;

/**
 * Replacements feature
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

trait Replacements
{
    /** @var array<string,array<string,string>> */
    private array $patterns = [];
    /** @var array<string,?string> */
    private array $replaces = [];
    /** @var array<string,array<string,string>> */
    private array $dynamic_patterns = [];
    private ?PHPMailer $mail = null;

    #[Inject("zdb")]
    protected Db $zdb;

    #[Inject("login")]
    protected Login $login;

    #[Inject("preferences")]
    protected Preferences $preferences;

    protected RouteParser $routeparser;

    /**
     * Get dynamic patterns
     *
     * @param string  $form_name Dynamic form name
     * @param boolean $legacy    Whether to load legacy patterns
     *
     * @return array<string,array<string,string>>
     */
    public function getDynamicPatterns(string $form_name, bool $legacy = true): array
    {
        $fields = new DynamicFieldsSet($this->zdb, $this->login);
        $dynamic_fields = $fields->getList($form_name);

        $dynamic_patterns = [];
        foreach ($dynamic_fields as $dynamic_field) {
            //no pattern for separators
            if ($dynamic_field instanceof  Separator) {
                continue;
            }
            $key = strtoupper('DYNFIELD_' . $dynamic_field->getId() . '_' . $form_name);
            $capabilities = [
                'LABEL',
                ''
            ];
            if (!($this instanceof Texts) && ($legacy === true || $dynamic_field instanceof Choice)) {
                $capabilities[] = 'INPUT';
            }
            foreach ($capabilities as $capability) {
                $skey = sprintf('%s_%s', $capability, $key);
                switch ($capability) {
                    case 'LABEL':
                        $title = _T('Label for dynamic field "%s"');
                        break;
                    case 'INPUT':
                        $title = _T('Form entry for dynamic field "%s"');
                        break;
                    case '':
                    case 'VALUE':
                    default:
                        $skey = $key;
                        $title = _T('Value for dynamic field "%s"');
                        break;
                }
                $dynamic_patterns[strtolower($skey)] = [
                    'title' => sprintf(
                        $title,
                        $dynamic_field->getName()
                    ),
                    'pattern'   => sprintf('/{%s}/', $skey)
                ];
            }
        }

        $this->dynamic_patterns[$form_name] = $dynamic_patterns;
        return $this->dynamic_patterns[$form_name];
    }

    /**
     * Set patterns
     *
     * @param array<string,array<string,string>> $patterns Patterns to add
     *
     * @return self
     */
    protected function setPatterns(array $patterns): self
    {
        $toset = [];
        foreach ($patterns as $key => $info) {
            $toset[$key] = $info['pattern'];
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
     * @param array<string,?string> $replaces Replacements to add
     *
     * @return void
     */
    public function setReplacements(array $replaces): void
    {
        $this->replaces = array_merge(
            $this->replaces,
            $replaces
        );
    }

    /**
     * Get main patterns
     *
     * @return array<string,array<string,string>>
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
            'asso_print_logo'             => [
                'title'     => _T('Your organisation logo (print specific)'),
                'pattern'          => '/{ASSO_PRINT_LOGO}/',
            ],
            'date_now'              => [
                //TRANS: see https://www.php.net/manual/datetime.format.php
                'title'     => _T('Current date (Y-m-d)'),
                'pattern'   => '/{DATE_NOW}/'
            ],
            'login_uri'             => [
                'title'     => _T("Galette's login URI"),
                'pattern'   => '/{LOGIN_URI}/'
            ],
            'asso_footer' => [
                'title'     => trim(trim(_T("Footer text:"), ':')),
                'pattern'   => '/{ASSO_FOOTER}/'
            ]
        ];
    }

    /**
     * Get patterns for a member
     *
     * @param boolean $legacy Whether to load legacy patterns
     *
     * @return array<string,array<string,string>>
     */
    protected function getMemberPatterns(bool $legacy = true): array
    {
        $dynamic_patterns = $this->getDynamicPatterns('adh', $legacy);
        $m_patterns = [
            'adh_title'         => [
                'title'     => _('Title'),
                'pattern'   => '/{TITLE_ADH}/',
            ],
            'adh_id'            =>  [
                'title'     => _T("Member's ID"),
                'pattern'   => '/{ID_ADH}/',
            ],
            'adh_num'            =>  [
                'title'     => _T("Member number"),
                'pattern'   => '/{NUM_ADH}/',
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
            'adh_address_multi'    => [
                'title'     => sprintf('%s (%s)', _T('Address'), _T('with break lines')),
                'pattern'   => '/{ADDRESS_ADH_MULTI}/',
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
            'adh_dues'          => [
                'title'     => _T('Member state of dues'),
                'pattern'   => '/{ADH_DUES}/'
            ],
            'days_remaining'    => [
                'title'     => _T('Membership remaining days'),
                'pattern'   => '/{DAYS_REMAINING}/',
            ],
            'days_expired'      => [
                'title'     => _T('Membership expired since'),
                'pattern'   => '/{DAYS_EXPIRED}/',
            ]
        ];

        if ($legacy === true) {
            $m_patterns += [
                '_adh_company' => [
                    'title'     => _T("Company name"),
                    'pattern'   => '/{COMPANY_NAME_ADH}/',
                ],
                '_adh_last_name'     =>  [
                    'title'     => _T('Last name'),
                    'pattern'   => '/{LASTNAME_ADH}/',
                ],
                '_adh_first_name'    =>  [
                    'title'     => _T('First name'),
                    'pattern'   => '/{FIRSTNAME_ADH}/',
                ],
                '_adh_login'         =>  [
                    'title'     => _T('Login'),
                    'pattern'   => '/{LOGIN}/',
                ],
                '_adh_email'         =>  [
                    'title'     => _T('Email'),
                    'pattern'   => '/{MAIL_ADH}/',
                ],
            ];
        }

        return $m_patterns + $dynamic_patterns;
    }

    /**
     * Get patterns for a contribution
     *
     * @param boolean $legacy Whether to load legacy patterns
     *
     * @return array<string,array<string,string>>
     */
    protected function getContributionPatterns(bool $legacy = true): array
    {
        $dynamic_patterns = $this->getDynamicPatterns('contrib', $legacy);

        $c_patterns = [
            'contrib_label'     => [
                'title'     => _T('Contribution label'),
                'pattern'   => '/{CONTRIB_LABEL}/',
            ],
            'contrib_amount'    => [
                'title'     => _T('Amount'),
                'pattern'   => '/{CONTRIB_AMOUNT}/',
            ],
            'contrib_amount_letters' => [
                'title'     => _T('Amount (in letters)'),
                'pattern'   => '/{CONTRIB_AMOUNT_LETTERS}/',
            ],
            'contrib_date'      => [
                'title'     => _T('Full date'),
                'pattern'   => '/{CONTRIB_DATE}/',
            ],
            'contrib_year'      => [
                'title'     => _T('Contribution year'),
                'pattern'   => '/{CONTRIB_YEAR}/',
            ],
            'contrib_comment'   => [
                'title'     => _T('Comment'),
                'pattern'   => '/{CONTRIB_COMMENT}/',
            ],
            'contrib_bdate'     => [
                'title'     => _T('Begin date'),
                'pattern'   => '/{CONTRIB_BEGIN_DATE}/',
            ],
            'contrib_edate'     => [
                'title'     => _T('End date'),
                'pattern'   => '/{CONTRIB_END_DATE}/',
            ],
            'contrib_id'        => [
                'title'     => _T('Contribution id'),
                'pattern'   => '/{CONTRIB_ID}/',
            ],
            'contrib_payment'   => [
                'title'     => _T('Payment type'),
                'pattern'   => '/{CONTRIB_PAYMENT_TYPE}/'
            ],
            'contrib_info'      => [
                'title'     => _T('Contribution information'),
                'pattern'   => '/{CONTRIB_INFO}/'
            ]
        ];

        if ($legacy === true) {
            foreach ($c_patterns as $key => $pattern) {
                $nkey = '_' . $key;
                $pattern['pattern'] = str_replace(
                    'CONTRIB_',
                    'CONTRIBUTION_',
                    $pattern['pattern']
                );
                $c_patterns[$nkey] = $pattern;
            }

            $c_patterns['__contrib_label'] = [
                'title'     => $c_patterns['contrib_label']['title'],
                'pattern'   => '/{CONTRIB_TYPE}/'
            ];
        }

        //handle DEADLINE alias
        $c_patterns['deadline'] = [
            'title'     => $c_patterns['contrib_edate']['title'],
            'pattern'   => '/{DEADLINE}/'
        ];

        return $c_patterns + $dynamic_patterns;
    }

    /**
     * Set mail instance
     *
     * @param PHPMailer $mail PHPMailer instance
     *
     * @return self
     */
    public function setMail(PHPMailer $mail): self
    {
        $this->mail = $mail;
        return $this;
    }

    /**
     * Set main replacements
     *
     * @return self
     */
    public function setMain(): self
    {
        $address = $this->preferences->getPostalAddress();
        $address_multi = preg_replace("/\n/", "<br>", $address);

        $website = '';
        if ($this->preferences->pref_website !== '') {
            $website = '<a href="' . $this->preferences->pref_website . '">' .
                $this->preferences->pref_website . '</a>';
        }

        $logo = new Logo();
        if ($this->mail !== null) {
            $logo_content = $this->preferences->getURL() . $this->routeparser->urlFor('logo');
        } else {
            $logo_content = '@' . base64_encode(file_get_contents($logo->getPath()));
        }
        $logo_elt = sprintf(
            '<img src="%1$s" width="%2$s" height="%3$s" alt="" />',
            $logo_content,
            $logo->getOptimalWidth(),
            $logo->getOptimalHeight()
        );

        $print_logo = new Logo();
        if ($this->mail !== null) {
            $print_logo_content = $this->preferences->getURL() . $this->routeparser->urlFor('printLogo');
        } else {
            $print_logo_content = '@' . base64_encode(file_get_contents($print_logo->getPath()));
        }
        $print_logo_elt = sprintf(
            '<img src="%1$s" width="%2$s" height="%3$s" alt="" />',
            $print_logo_content,
            $logo->getOptimalWidth(),
            $logo->getOptimalHeight()
        );

        $this->setReplacements(
            array(
                'asso_name'          => $this->preferences->pref_nom,
                'asso_slogan'        => $this->preferences->pref_slogan,
                'asso_address'       => $address,
                'asso_address_multi' => $address_multi,
                'asso_website'       => $website,
                'asso_logo'          => $logo_elt,
                'asso_print_logo'    => $print_logo_elt,
                //TRANS: see https://www.php.net/manual/datetime.format.php
                'date_now'           => date(_T('Y-m-d')),
                'login_uri'          => $this->preferences->getURL() . $this->routeparser->urlFor('login'),
                'asso_footer'        => $this->preferences->pref_footer
            )
        );

        return $this;
    }

    /**
     * Set contribution and proceed related replacements
     *
     * @return self
     */
    public function setNoContribution(): self
    {
        global $login;

        $c_replacements = [
            'contrib_label'     => null,
            'contrib_amount'    => null,
            'contrib_amount_letters' => null,
            'contrib_date'      => null,
            'contrib_year'      => null,
            'contrib_comment'   => null,
            'contrib_bdate'     => null,
            'contrib_edate'     => null,
            'contrib_id'        => null,
            'contrib_payment'   => null,
            'contrib_info'      => null
        ];

        foreach ($c_replacements as $key => $replacement) {
            $nkey = '_' . $key;
            $c_replacements[$nkey] = $replacement;
        }
        $c_replacements['__contrib_label'] = $c_replacements['contrib_label'];

        //handle DEADLINE alias
        $c_replacements['deadline'] = null;

        $this->setReplacements($c_replacements);

        /** the list of all dynamic fields */
        $fields = new DynamicFieldsSet($this->zdb, $login);
        $dynamic_fields = $fields->getList('contrib');
        $this->setDynamicFields('contrib', $dynamic_fields, null);

        return $this;
    }

    /**
     * Set contribution and proceed related replacements
     *
     * @param Contribution $contrib Contribution
     *
     * @return self
     */
    public function setContribution(Contribution $contrib): self
    {
        global $login, $i18n;

        $formatter = new NumberFormatter($i18n->getID(), NumberFormatter::SPELLOUT);

        $c_replacements = [
            'contrib_label'     => $contrib->type->libelle,
            'contrib_amount'    => $contrib->amount,
            'contrib_amount_letters' => $formatter->format($contrib->amount),
            'contrib_date'      => $contrib->date,
            'contrib_year'      => $contrib->raw_date->format('Y'),
            'contrib_comment'   => $contrib->info,
            'contrib_bdate'     => $contrib->begin_date,
            'contrib_edate'     => $contrib->end_date,
            'contrib_id'        => $contrib->id,
            'contrib_payment'   => $contrib->getPaymentType(),
            'contrib_info'      => $contrib->info
        ];

        foreach ($c_replacements as $key => $replacement) {
            $nkey = '_' . $key;
            $c_replacements[$nkey] = $replacement;
        }
        $c_replacements['__contrib_label'] = $c_replacements['contrib_label'];

        //handle DEADLINE alias
        $c_replacements['deadline'] = $c_replacements['contrib_edate'];

        $this->setReplacements($c_replacements);

        /** the list of all dynamic fields */
        $fields = new DynamicFieldsSet($this->zdb, $login);
        $dynamic_fields = $fields->getList('contrib');
        $this->setDynamicFields('contrib', $dynamic_fields, $contrib);

        return $this;
    }

    /**
     * Set member and proceed related replacements
     *
     * @param Adherent $member Member
     *
     * @return self
     */
    public function setMember(Adherent $member): self
    {
        global $login;

        $address = $member->getAddress();
        $address_multi = preg_replace("/\n/", "<br>", $address);

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
                'adh_num'           => $member->number,
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
                'adh_address_multi' => $address_multi,
                'adh_zip'           => $member->getZipcode(),
                'adh_town'          => $member->getTown(),
                'adh_country'       => $member->getCountry(),
                'adh_phone'         => $member->phone,
                'adh_mobile'        => $member->gsm,
                //always take current member email, to be sure.
                'adh_email'         => $member->email,
                'adh_login'         => $member->login,
                'adh_main_group'    => $main_group,
                'adh_groups'        => $group_list,
                'adh_dues'          => $member->getDues(),
                'days_remaining'    => $member->days_remaining,
                'days_expired'      => (int)$member->days_remaining + 1,
                //Handle COMPANY_NAME_ADH... https://bugs.galette.eu/issues/1530
                '_adh_company'      => $member->company_name,
                //Handle old names for variables ... https://bugs.galette.eu/issues/1393
                '_adh_last_name'    => $member->name,
                '_adh_first_name'   => $member->surname,
                '_adh_login'        => $member->login,
                '_adh_email'        => $member->email
            )
        );

        /** the list of all dynamic fields */
        $fields = new DynamicFieldsSet($this->zdb, $login);
        $dynamic_fields = $fields->getList('adh');
        $this->setDynamicFields('adh', $dynamic_fields, $member);

        return $this;
    }

    /**
     * Set dynamic fields and proceed related replacements
     *
     * @param string              $form_name      Form name
     * @param array<string,mixed> $dynamic_fields Dynamic fields
     * @param ?object             $object         Related object (Adherent, Contribution, ...)
     *
     * @return self
     */
    public function setDynamicFields(string $form_name, array $dynamic_fields, ?object $object): self
    {
        $uform_name = strtoupper($form_name);

        $dynamic_patterns = $this->getDynamicPatterns($form_name);
        foreach ($dynamic_patterns as $dynamic_pattern) {
            $pattern = trim($dynamic_pattern['pattern'], '/');
            $key   = strtolower(rtrim(ltrim($pattern, '{'), '}'));
            $value = '';

            if (preg_match("/^{LABEL_DYNFIELD_([0-9]+)_$uform_name}$/", $pattern, $match)) {
                /** dynamic field label */
                $field_id = $match[1];
                $value    = $dynamic_fields[$field_id]->getName();
            }
            if (preg_match("/^{(INPUT_|VALUE_)?DYNFIELD_([0-9]+)_$uform_name}$/", $pattern, $match)) {
                /** dynamic field value */
                $capacity = trim($match[1], '_');
                $field_id    = $match[2];
                $field_name  = $dynamic_fields[$field_id]->getName();
                $field_type  = $dynamic_fields[$field_id]->getType();
                $field_values = [];
                if ($object !== null) {
                    $all_values = $object->getDynamicFields()->getValues($field_id);
                    foreach ($all_values as $field_value) {
                        $field_values[$field_value['field_val']] = $field_value['text_val'] ?? $field_value['field_val'];
                    }
                } else {
                    $field_values = [];
                }

                switch ($field_type) {
                    case DynamicField::CHOICE:
                        $choice_values = $dynamic_fields[$field_id]->getValues();
                        if ($capacity == 'INPUT') {
                            foreach ($choice_values as $choice_idx => $choice_value) {
                                $value .= '<input type="radio" class="box" name="' . $field_name . '" value="' . $field_id . '"';
                                if (isset($field_values[$choice_idx])) {
                                    $value .= ' checked="checked"';
                                }
                                $value .= ' disabled="disabled">' . $choice_value . '&nbsp;';
                            }
                        } else {
                            foreach ($field_values as $field_value) {
                                $value .= $field_value;
                            }
                        }
                        break;
                    case DynamicField::BOOLEAN:
                        foreach ($field_values as $field_value) {
                            $value .= ($field_value ? _T("Yes") : _T("No"));
                        }
                        break;
                    case DynamicField::FILE:
                        $pos = 0;
                        foreach ($field_values as $field_value) {
                            if (empty($field_value)) {
                                continue;
                            }
                            $spattern = (($this instanceof Texts) ?
                                '%3$s (%1$s%2$s)' :
                                '<a href="%1$s%2$s">%3$s</a>'
                            );
                            $value .= sprintf(
                                $spattern,
                                $this->preferences->getURL(),
                                $this->routeparser->urlFor(
                                    'getDynamicFile',
                                    [
                                        'form_name' => $form_name,
                                        'id' => $object->id,
                                        'fid' => $field_id,
                                        'pos' => ++$pos,
                                        'name' => $field_value
                                    ]
                                ),
                                $field_value
                            );
                        }
                        break;
                    case DynamicField::TEXT:
                    case DynamicField::LINE:
                    case DynamicField::DATE:
                        $value .= implode('<br/>', $field_values);
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
     * @return array<string,array<string,string>>
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
            'patterns'  => $this->getMemberPatterns(false)
        ];

        return $legend;
    }

    /**
     * Get configured replacements
     *
     * @return array<string,string>
     */
    public function getReplacements(): array
    {
        return $this->replaces;
    }

    /**
     * Set Db dependency
     *
     * @param Db $db Db instance
     *
     * @return self
     */
    public function setDb(Db $db): self
    {
        $this->zdb = $db;
        return $this;
    }

    /**
     * Set Login dependency
     *
     * @param Login $login Login instance
     *
     * @return self
     */
    public function setLogin(Login $login): self
    {
        $this->login = $login;
        return $this;
    }

    /**
     * Set Preferences dependency
     *
     * @param Preferences $preferences Preferences instance
     *
     * @return self
     */
    public function setPreferences(Preferences $preferences): self
    {
        $this->preferences = $preferences;
        return $this;
    }

    /**
     * Set RouteParser dependency
     *
     * @param RouteParser $routeparser RouteParser instance
     *
     * @return self
     */
    public function setRouteparser(RouteParser $routeparser): self
    {
        $this->routeparser = $routeparser;
        return $this;
    }

    /**
     * Proceed replacement on given entry
     *
     * @param string $source Source string
     *
     * @return string
     */
    protected function proceedReplacements(string $source): string
    {
        //handle translations
        $callback = static function ($matches) {
            return _T($matches[1]);
        };
        $replaced = preg_replace_callback(
            '/_T\("([^\"]+)"\)/',
            $callback,
            $source
        );

        //order matters
        ksort($this->patterns, SORT_NATURAL);
        ksort($this->replaces, SORT_NATURAL);

        if (array_keys($this->patterns) !== array_keys($this->replaces)) {
            throw new \RuntimeException('Patterns and replacements does not match!');
        }

        //handle replacements
        $replaced = preg_replace(
            $this->patterns,
            $this->replaces,
            $replaced
        );

        //handle translations with replacements
        $repl_callback = static function ($matches) {
            return str_replace(
                $matches[1],
                $matches[2],
                $matches[3]
            );
        };
        $replaced = preg_replace_callback(
            '/str_replace\(\'([^,]+)\', ?\'([^,]+)\', ?\'(.*)\'\)/',
            $repl_callback,
            $replaced
        );

        return trim($replaced);
    }

    /**
     * Get patterns
     *
     * @return array<string,array<string,string>>
     */
    public function getPatterns(): array
    {
        return $this->patterns;
    }
}
