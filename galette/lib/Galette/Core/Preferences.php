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

namespace Galette\Core;

use Galette\Entity\PaymentType;
use Galette\Entity\Social;
use Galette\Features\Replacements;
use Galette\Features\Socials;
use Galette\Util\Text;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;
use Analog\Analog;
use Galette\Entity\Adherent;
use Galette\Entity\Status;
use Galette\IO\PdfMembersCards;
use Galette\Repository\Members;

/**
 * Preferences for galette
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property string $pref_admin_login Super admin login
 * @property string $pref_admin_pass Super admin password
 * @property string $pref_nom Association name
 * @property string $pref_slogan Association slogan
 * @property string $pref_adresse Address
 * @property string $pref_adresse2 Address continuation
 * @property string $pref_cp Association zipcode
 * @property string $pref_ville Association
 * @property string $pref_region Region
 * @property string $pref_pays Country
 * @property integer $pref_postal_address Postal address to use, one of self::POSTAL_ADDRESS*
 * @property integer $pref_postal_staff_member Staff member ID from which retrieve postal address
 * @property string $pref_org_phone_number Phone number
 * @property integer $pref_org_phone Phone number to use, one of self::PHONE_NUMBER*
 * @property integer $pref_org_phone_staff_member Staff member ID from which retrieve phone number
 * @property string $pref_org_email Email address
 * @property boolean $pref_disable_members_socials Disable social networks for members
 * @property string $pref_lang Default instance language
 * @property integer $pref_numrows Default number of rows in lists
 * @property integer $pref_statut Default status for new members
 * @property string $pref_email_nom
 * @property string $pref_email
 * @property string $pref_email_newadh
 * @property boolean $pref_bool_mailadh
 * @property boolean $pref_bool_mailowner
 * @property boolean $pref_editor_enabled
 * @property integer $pref_mail_method Mail method, see GaletteMail::METHOD_*
 * @property string $pref_mail_smtp
 * @property string $pref_mail_smtp_host
 * @property boolean $pref_mail_smtp_auth
 * @property boolean $pref_mail_smtp_secure
 * @property integer $pref_mail_smtp_port
 * @property string $pref_mail_smtp_user
 * @property string $pref_mail_smtp_password
 * @property integer $pref_membership_ext
 * @property string $pref_beg_membership
 * @property integer $pref_membership_offermonths
 * @property string $pref_email_reply_to
 * @property string $pref_website
 * @property integer $pref_etiq_marges_v
 * @property integer $pref_etiq_marges_h
 * @property integer $pref_etiq_hspace
 * @property integer $pref_etiq_vspace
 * @property integer $pref_etiq_hsize
 * @property integer $pref_etiq_vsize
 * @property integer $pref_etiq_cols
 * @property integer $pref_etiq_rows
 * @property integer $pref_etiq_corps
 * @property boolean $pref_etiq_border
 * @property boolean $pref_force_picture_ratio
 * @property string $pref_member_picture_ratio
 * @property string $pref_card_abrev
 * @property string $pref_card_strip
 * @property string $pref_card_tcol
 * @property string $pref_card_scol
 * @property string $pref_card_bcol
 * @property string $pref_card_hcol
 * @property string $pref_bool_display_title
 * @property integer $pref_card_address
 * @property string $pref_card_year
 * @property integer $pref_card_marges_v
 * @property integer $pref_card_marges_h
 * @property integer $pref_card_vspace
 * @property integer $pref_card_hspace
 * @property string $pref_card_self
 * @property integer $pref_card_hsize
 * @property integer $pref_card_vsize
 * @property integer $pref_card_cols
 * @property integer $pref_card_rows
 * @property string $pref_theme Preferred theme
 * @property boolean $pref_hide_bg_image
 * @property boolean $pref_enable_custom_colors
 * @property string $pref_cc_primary
 * @property string $pref_cc_primary_text
 * @property string $pref_cc_secondary
 * @property string $pref_cc_secondary_text
 * @property boolean $pref_bool_publicpages
 * @property integer $pref_publicpages_visibility_generic
 * @property integer $pref_publicpages_visibility_documents
 * @property integer $pref_publicpages_visibility_memberslist
 * @property integer $pref_publicpages_visibility_membersgallery
 * @property integer $pref_publicpages_visibility_stafflist
 * @property integer $pref_publicpages_visibility_staffgallery
 * @property boolean $pref_bool_groupsmanagers_are_staff
 * @property boolean $pref_bool_selfsubscribe
 * @property boolean $pref_bool_empty_form_link
 * @property string $pref_member_form_grid
 * @property string $pref_mail_sign
 * @property string $pref_new_contrib_script
 * @property boolean $pref_bool_wrap_mails
 * @property string $pref_rss_url
 * @property string $pref_adhesion_form
 * @property boolean $pref_mail_allow_unsecure
 * @property string $pref_instance_uuid
 * @property string $pref_registration_uuid
 * @property string $pref_telemetry_date
 * @property string $pref_registration_date
 * @property string $pref_footer
 * @property integer $pref_filter_account
 * @property string $pref_galette_url
 * @property integer $pref_redirect_on_create
 * @property integer $pref_password_length
 * @property boolean $pref_password_blacklist
 * @property integer $pref_password_strength
 * @property integer $pref_default_paymenttype
 * @property boolean $pref_bool_create_member
 * @property boolean $pref_bool_groupsmanagers_create_member
 * @property boolean $pref_bool_groupsmanagers_edit_member
 * @property boolean $pref_bool_groupsmanagers_edit_groups
 * @property boolean $pref_bool_groupsmanagers_mailings
 * @property boolean $pref_bool_groupsmanagers_exports
 * @property boolean $pref_bool_groupsmanagers_create_contributions
 * @property boolean $pref_bool_groupsmanagers_create_transactions
 * @property boolean $pref_bool_groupsmanagers_see_contributions
 * @property boolean $pref_bool_groupsmanagers_see_transactions
 * @property-read string[] $vpref_email_newadh list of mail senders
 * @property boolean $pref_noindex
 */
class Preferences
{
    use Replacements;
    use Socials;

    protected Preferences $preferences; //redefined from Replacements feature - avoid circular dependency
    /** @var array<string, bool|int|string> */
    private array $prefs;
    /** @var array<string> */
    private array $errors = [];

    public const TABLE = 'preferences';
    public const PK = 'nom_pref';

    /** Postal address will be the one given in the preferences */
    public const POSTAL_ADDRESS_FROM_PREFS = 0;
    /** Postal address will be the one of the selected staff member */
    public const POSTAL_ADDRESS_FROM_STAFF = 1;

    /** Phone number will be the one given in the preferences */
    public const PHONE_NUMBER_FROM_PREFS = 0;
    /** Phone number will be the one of the selected staff member */
    public const PHONE_NUMBER_FROM_STAFF = 1;
    /** Phone number will be the GSM of the selected staff member */
    public const PHONE_NUMBER_MOBILE_FROM_STAFF = 2;

    /** Public pages stuff */
    /** Public pages are publicly visibles */
    public const PUBLIC_PAGES_VISIBILITY_PUBLIC = 0;
    /** Public pages are visibles for up-to-date members only */
    public const PUBLIC_PAGES_VISIBILITY_RESTRICTED = 1;
    /** Public pages are visibles for admin and staff members only */
    public const PUBLIC_PAGES_VISIBILITY_PRIVATE = 2;
    /** Public pages are hidden */
    public const PUBLIC_PAGES_VISIBILITY_HIDDEN = 3;
    public const PUBLIC_PAGES_VISIBILITY_INHERIT = 4;

    /** No password strength */
    public const PWD_NONE = 0;
    /** Weak password strength */
    public const PWD_WEAK = 1;
    /** Medium password strength */
    public const PWD_MEDIUM = 2;
    /** Strong password strength */
    public const PWD_STRONG = 3;
    /** Very strong password strength */
    public const PWD_VERY_STRONG = 4;

    /** Dark mode CSS file should be deleted from cache */
    private bool $delete_dark_css = false;
    /** @var array<string> */
    private static array $fields = [
        'nom_pref',
        'val_pref'
    ];

    /** @var array<string, bool|int|string> */
    private static array $defaults = [
        'pref_admin_login'    =>    'admin',
        'pref_admin_pass'    =>    'admin',
        'pref_nom'        =>    'Galette',
        'pref_slogan'        =>    '',
        'pref_adresse'        =>    '-',
        'pref_adresse2'        =>    '',
        'pref_cp'        =>    '',
        'pref_ville'        =>    '',
        'pref_region'        =>    '',
        'pref_pays'        =>    '',
        'pref_postal_address'  => self::POSTAL_ADDRESS_FROM_PREFS,
        'pref_postal_staff_member' => '',
        'pref_org_phone_number' => '',
        'pref_org_phone' => self::PHONE_NUMBER_FROM_PREFS,
        'pref_org_phone_staff_member' => '',
        'pref_org_email' => '',
        'pref_disable_members_socials' => false,
        'pref_lang'        =>    I18n::DEFAULT_LANG,
        'pref_numrows'        =>    30,
        'pref_statut'        =>    Status::DEFAULT_STATUS,
        /* Appearance */
        'pref_hide_bg_image'    =>    false,
        'pref_enable_custom_colors'    =>    false,
        'pref_cc_primary'    =>    '#ffb619',
        'pref_cc_primary_text'    =>    '#000000',
        'pref_cc_secondary'    =>    '#ffda89',
        'pref_cc_secondary_text'    =>    '#1b1c1d',
        /* Preferences for emails */
        'pref_email_nom'    =>    'Galette',
        'pref_email'        =>    'mail@domain.com',
        'pref_email_newadh'    =>    'mail@domain.com',
        'pref_bool_mailadh'    =>    false,
        'pref_bool_mailowner' => false,
        'pref_editor_enabled'    =>    false,
        'pref_mail_method'    =>    GaletteMail::METHOD_DISABLED,
        'pref_mail_smtp'    =>    '',
        'pref_mail_smtp_host'   => '',
        'pref_mail_smtp_auth'   => false,
        'pref_mail_smtp_secure' => false,
        'pref_mail_smtp_port'   => '',
        'pref_mail_smtp_user'   => '',
        'pref_mail_smtp_password'   => '',
        'pref_membership_ext'    =>    12,
        'pref_beg_membership'    =>    '',
        'pref_membership_offermonths' => 0,
        'pref_email_reply_to'    =>    '',
        'pref_website'        =>    '',
        /* Preferences for labels */
        'pref_etiq_marges_v'    =>    10,
        'pref_etiq_marges_h'    =>    10,
        'pref_etiq_hspace'    =>    10,
        'pref_etiq_vspace'    =>    5,
        'pref_etiq_hsize'    =>    90,
        'pref_etiq_vsize'    =>    35,
        'pref_etiq_cols'    =>    2,
        'pref_etiq_rows'    =>    7,
        'pref_etiq_corps'    =>    12,
        'pref_etiq_border'    =>    true,
        /* Preferences for members cards */
        'pref_force_picture_ratio'    =>    false,
        'pref_member_picture_ratio'    =>    'square_ratio',
        'pref_card_abrev'    =>    'GALETTE',
        'pref_card_strip'    =>    'Gestion d\'Adherents en Ligne Extrêmement Tarabiscotée',
        'pref_card_tcol'    =>    '#FFFFFF',
        'pref_card_scol'    =>    '#8C2453',
        'pref_card_bcol'    =>    '#53248C',
        'pref_card_hcol'    =>    '#248C53',
        'pref_bool_display_title'    =>    false,
        'pref_card_hsize'    =>    PdfMembersCards::WIDTH,
        'pref_card_vsize'    =>    PdfMembersCards::HEIGHT,
        'pref_card_rows'    =>    PdfMembersCards::ROWS,
        'pref_card_cols'    =>    PdfMembersCards::COLS,
        'pref_card_address'    =>    1,
        'pref_card_year'    =>    '',
        'pref_card_marges_v'    =>    15,
        'pref_card_marges_h'    =>    20,
        'pref_card_vspace'    =>    5,
        'pref_card_hspace'    =>    10,
        'pref_card_self'    =>    1,
        'pref_theme'        =>    'default',
        'pref_bool_publicpages' => true,
        'pref_publicpages_visibility_generic' => self::PUBLIC_PAGES_VISIBILITY_RESTRICTED,
        'pref_publicpages_visibility_documents' => self::PUBLIC_PAGES_VISIBILITY_RESTRICTED,
        'pref_publicpages_visibility_memberslist' => self::PUBLIC_PAGES_VISIBILITY_RESTRICTED,
        'pref_publicpages_visibility_membersgallery' => self::PUBLIC_PAGES_VISIBILITY_RESTRICTED,
        'pref_publicpages_visibility_stafflist' => self::PUBLIC_PAGES_VISIBILITY_RESTRICTED,
        'pref_publicpages_visibility_staffgallery' => self::PUBLIC_PAGES_VISIBILITY_RESTRICTED,
        'pref_bool_groupsmanagers_are_staff' => false,
        'pref_mail_sign' => "{ASSO_NAME}\r\n\r\n{ASSO_WEBSITE}",
        /* Preferences for member/subscribe form */
        'pref_bool_selfsubscribe' => true,
        'pref_member_form_grid' => 'one',
        'pref_bool_empty_form_link' => false,
        /* New contribution script */
        'pref_new_contrib_script' => '',
        'pref_bool_wrap_mails' => true,
        'pref_rss_url' => Galette::RSS_URL,
        'pref_adhesion_form' => \Galette\IO\PdfAdhesionForm::class,
        'pref_mail_allow_unsecure' => false,
        'pref_instance_uuid' => '',
        'pref_registration_uuid' => '',
        'pref_telemetry_date' => '',
        'pref_registration_date' => '',
        'pref_footer' => '',
        'pref_filter_account' => Members::ALL_ACCOUNTS,
        'pref_galette_url' => '',
        'pref_redirect_on_create' => Adherent::AFTER_ADD_DEFAULT,
        /* Security related */
        'pref_password_length' => 6,
        'pref_password_blacklist' => false,
        'pref_password_strength' => self::PWD_NONE,
        'pref_default_paymenttype' => PaymentType::CHECK,
        'pref_bool_create_member' => false,
        'pref_bool_groupsmanagers_create_member' => false,
        'pref_bool_groupsmanagers_edit_member' => false,
        'pref_bool_groupsmanagers_edit_groups' => false,
        'pref_bool_groupsmanagers_mailings' => false,
        'pref_bool_groupsmanagers_exports' => true,
        'pref_bool_groupsmanagers_create_contributions' => false,
        'pref_bool_groupsmanagers_create_transactions' => false,
        'pref_bool_groupsmanagers_see_contributions' => false,
        'pref_bool_groupsmanagers_see_transactions' => false,
        'pref_noindex' => false
    ];

    /** @var Social[] */
    private array $socials;

    // flagging required fields
    /** @var array<string,int> */
    private array $required = [
        'pref_nom' => 1,
        'pref_lang' => 1,
        'pref_numrows' => 1,
        'pref_statut' => 1,
        'pref_etiq_marges_v' => 1,
        'pref_etiq_marges_h' => 1,
        'pref_etiq_hspace' => 1,
        'pref_etiq_vspace' => 1,
        'pref_etiq_hsize' => 1,
        'pref_etiq_vsize' => 1,
        'pref_etiq_cols' => 1,
        'pref_etiq_rows' => 1,
        'pref_etiq_corps' => 1,
        'pref_card_marges_v' => 1,
        'pref_card_marges_h' => 1,
        'pref_card_hspace' => 1,
        'pref_card_vspace' => 1
    ];

    /**
     * Default constructor
     *
     * @param Db      $zdb  Db instance
     * @param boolean $load Automatically load preferences on load
     *
     * @return void
     */
    public function __construct(Db $zdb, bool $load = true)
    {
        $this->zdb = $zdb;
        if ($load) {
            $this->load();
            $this->checkUpdate();
        }
    }

    /**
     * Check if all fields referenced in the default array do exist,
     * create them if not
     *
     * @return boolean
     */
    private function checkUpdate(): bool
    {
        $proceed = false;
        $params = [];
        foreach (self::$defaults as $k => $v) {
            if (!isset($this->prefs[$k])) {
                if ($k == 'pref_admin_pass' && $v == 'admin') {
                    $v = password_hash($v, PASSWORD_BCRYPT);
                }
                $this->prefs[$k] = $v;
                Analog::log(
                    'The field `' . $k . '` does not exists, Galette will attempt to create it.',
                    Analog::INFO
                );
                $proceed = true;
                $params[] = [
                    'nom_pref'  => $k,
                    'val_pref'  => $v
                ];
            }
        }
        if ($proceed !== false) {
            try {
                $insert = $this->zdb->insert(self::TABLE);
                $insert->values(
                    [
                        'nom_pref'  => ':nom_pref',
                        'val_pref'  => ':val_pref'
                    ]
                );
                $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

                foreach ($params as $p) {
                    $stmt->execute(
                        [
                            'nom_pref' => $p['nom_pref'],
                            'val_pref' => $p['val_pref']
                        ]
                    );
                }
            } catch (Throwable $e) {
                Analog::log(
                    'Unable to add missing preferences.' . $e->getMessage(),
                    Analog::WARNING
                );
                return false;
            }

            Analog::log(
                'Missing preferences were successfully stored into database.',
                Analog::INFO
            );
        }

        return true;
    }

    /**
     * Load current preferences from database.
     *
     * @return boolean
     */
    public function load(): bool
    {
        $this->prefs = [];

        try {
            $result = $this->zdb->selectAll(self::TABLE);
            foreach ($result as $pref) {
                $this->prefs[$pref->nom_pref] = $pref->val_pref;
            }
            $this->socials = Social::getListForMember(null);
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Preferences cannot be loaded. Galette should not work without '
                . 'preferences. Exiting.',
                Analog::URGENT
            );
            return false;
        }
    }

    /**
     * Set default preferences at installation time
     *
     * @param string $lang      language selected at install screen
     * @param string $adm_login admin login entered at install time
     * @param string $adm_pass  admin password entered at install time
     *
     * @return boolean
     * @throws Throwable
     */
    public function installInit(string $lang, string $adm_login, string $adm_pass): bool
    {
        try {
            //first, we drop all values
            $delete = $this->zdb->delete(self::TABLE);
            $this->zdb->execute($delete);

            //we then replace default values with the ones user has selected
            $values = self::$defaults;
            $values['pref_lang'] = $lang;
            $values['pref_admin_login'] = $adm_login;
            $values['pref_admin_pass'] = $adm_pass;
            $values['pref_card_year'] = date('Y');

            $insert = $this->zdb->insert(self::TABLE);
            $insert->values(
                [
                    'nom_pref'  => ':nom_pref',
                    'val_pref'  => ':val_pref'
                ]
            );
            $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

            foreach ($values as $k => $v) {
                $stmt->execute(
                    [
                        'nom_pref' => $k,
                        'val_pref' => $v
                    ]
                );
            }

            Analog::log(
                'Default preferences were successfully stored into database.',
                Analog::INFO
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to initialize default preferences.' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Returns all preferences keys
     *
     * @return array<string>
     */
    public function getFieldsNames(): array
    {
        return array_keys($this->prefs);
    }

    /**
     * Check values
     *
     * @param array<string, mixed> $values Values
     * @param Login                $login  Logged in user
     *
     * @return boolean
     */
    public function check(array $values, Login $login): bool
    {
        $this->errors = [];
        $insert_values = [];
        $this->getRequiredFields($login); //make sure required are all set

        $this->checkCssImpacted($values);

        // obtain fields
        foreach ($this->getFieldsNames() as $fieldname) {
            if (isset($values[$fieldname])) {
                $value = is_string($values[$fieldname]) ? trim($values[$fieldname]) : $values[$fieldname];
            } else {
                $value = "";
            }

            $insert_values[$fieldname] = $value;
        }

        //cleanup fields for demo
        if (Galette::isDemo()) {
            unset(
                $insert_values['pref_admin_login'],
                $insert_values['pref_admin_pass'],
                $insert_values['pref_mail_method']
            );
        }

        // missing relations
        if (
            !Galette::isDemo()
            && isset($insert_values['pref_mail_method'])
            && $insert_values['pref_mail_method'] > GaletteMail::METHOD_DISABLED
        ) {
            if (
                !isset($insert_values['pref_email_nom'])
                || $insert_values['pref_email_nom'] == ''
            ) {
                $this->errors[] = _T("- You must indicate a sender name for emails!");
            }
            if (
                !isset($insert_values['pref_email'])
                || $insert_values['pref_email'] == ''
            ) {
                $this->errors[] = _T("- You must indicate an email address Galette should use to send emails!");
            }
            if ($insert_values['pref_mail_method'] == GaletteMail::METHOD_SMTP && (!isset($insert_values['pref_mail_smtp_host']) || $insert_values['pref_mail_smtp_host'] == '')) {
                $this->errors[] = _T("- You must indicate the SMTP server you want to use!");
            }
            if (
                $insert_values['pref_mail_method'] == GaletteMail::METHOD_GMAIL
                || ($insert_values['pref_mail_method'] == GaletteMail::METHOD_SMTP
                && $insert_values['pref_mail_smtp_auth'])
            ) {
                if (
                    !isset($insert_values['pref_mail_smtp_user'])
                    || trim($insert_values['pref_mail_smtp_user']) == ''
                ) {
                    $this->errors[] = _T("- You must provide a login for SMTP authentication.");
                }
                if (
                    !isset($insert_values['pref_mail_smtp_password'])
                    || ($insert_values['pref_mail_smtp_password']) == ''
                ) {
                    $this->errors[] = _T("- You must provide a password for SMTP authentication.");
                }
            }
        }

        if (
            (!isset($insert_values['pref_beg_membership']) || $insert_values['pref_beg_membership'] == '')
            && (!isset($insert_values['pref_membership_ext']) || $insert_values['pref_membership_ext'] == '')
        ) {
            $this->errors[] = _T("- You must indicate a membership extension or a beginning of membership.");
        } elseif (
            $insert_values['pref_beg_membership'] != ''
            && $insert_values['pref_membership_ext'] != ''
        ) {
            $this->errors[] = _T("- Default membership extension and beginning of membership are mutually exclusive.");
        }

        if (
            isset($insert_values['pref_membership_offermonths'])
            && (int)$insert_values['pref_membership_offermonths'] > 0
            && isset($insert_values['pref_membership_ext'])
            && $insert_values['pref_membership_ext'] != ''
        ) {
            $this->errors[] = _T("- Offering months is only compatible with beginning of membership.");
        }

        // missing required fields?
        foreach (array_keys($this->required) as $val) {
            if (!isset($values[$val]) || is_string($values[$val]) && trim($values[$val]) == '') {
                $this->errors[] = str_replace(
                    '%field',
                    $val,
                    _T("- Mandatory field %field empty.")
                );
            }
        }

        // Check passwords. Hash will be done into the Preferences class
        if (!Galette::isDemo() && isset($values['pref_admin_pass_check']) && strcmp($insert_values['pref_admin_pass'], $values['pref_admin_pass_check']) != 0) {
            $this->errors[] = _T("Passwords mismatch");
        }

        //postal address
        if (isset($insert_values['pref_postal_address'])) {
            $value = $insert_values['pref_postal_address'];
            if ($value == Preferences::POSTAL_ADDRESS_FROM_PREFS) {
                if (isset($insert_values['pref_postal_staff_member'])) {
                    unset($insert_values['pref_postal_staff_member']);
                }
            } elseif ($value == Preferences::POSTAL_ADDRESS_FROM_STAFF) {
                if (!isset($insert_values['pref_postal_staff_member']) || $insert_values['pref_postal_staff_member'] < 1) {
                    $this->errors[] = _T("You have to select a staff member to retrieve its address");
                }
            }
        }

        //phone number
        if (isset($insert_values['pref_org_phone'])) {
            $value = $insert_values['pref_org_phone'];
            if ($value == Preferences::PHONE_NUMBER_FROM_PREFS) {
                if (isset($insert_values['pref_org_phone_staff_member'])) {
                    unset($insert_values['pref_org_phone_staff_member']);
                }
            } elseif ($value == Preferences::PHONE_NUMBER_FROM_STAFF || $value == Preferences::PHONE_NUMBER_MOBILE_FROM_STAFF) {
                if (!isset($insert_values['pref_org_phone_staff_member']) || $insert_values['pref_org_phone_staff_member'] < 1) {
                    $this->errors[] = _T("You have to select a staff member to retrieve its phone number");
                }
            }
        }

        // update preferences
        foreach ($insert_values as $champ => $valeur) {
            $checked = $login->isSuperAdmin();
            if (!$checked) {
                if ($champ != 'pref_admin_pass' && $champ != 'pref_admin_login') {
                    $checked = true;
                }
            } elseif ($champ == "pref_admin_pass" && empty($_POST['pref_admin_pass'] ?? '')) {
                $checked = false;
            }

            if ($checked) {
                $this->$champ = $valeur;
            }
        }

        $this->checkSocials($values);

        return 0 === count($this->errors);
    }

    /**
     * Validate value of a field
     *
     * @param string $fieldname Field name
     * @param mixed  $value     Value to be set
     *
     * @return mixed
     */
    public function validateValue(string $fieldname, mixed $value): mixed
    {
        global $login;

        switch ($fieldname) {
            case 'pref_email':
            case 'pref_email_newadh':
            case 'pref_email_reply_to':
            case 'pref_org_email':
                //check emails validity
                //may be a comma-separated list of valid emails:
                //"mail@domain.com,other@mail.com" only for pref_email_newadh.
                $addresses = [];
                if (trim($value) != '') {
                    $addresses = $fieldname == 'pref_email_newadh' ? explode(',', $value) : [$value];
                }
                foreach ($addresses as $address) {
                    if (!GaletteMail::isValidEmail($address)) {
                        $msg = str_replace('%s', $address, _T("Invalid E-Mail address: %s"));
                        Analog::log($msg, Analog::WARNING);
                        $this->errors[] = $msg;
                    }
                }
                break;
            case 'pref_admin_login':
                if (Galette::isDemo()) {
                    Analog::log(
                        'Trying to set superadmin login while in DEMO.',
                        Analog::WARNING
                    );
                } elseif (strlen($value) < 4) {
                    $this->errors[] = _T("- The username must be composed of at least 4 characters!");
                } elseif ($login->loginExists($value)) {
                    //check if login is already taken
                    $this->errors[] = _T("- This username is already used by another member !");
                }
                break;
            case 'pref_numrows':
            case 'pref_etiq_marges_h':
            case 'pref_etiq_marges_v':
            case 'pref_etiq_hspace':
            case 'pref_etiq_vspace':
            case 'pref_etiq_hsize':
            case 'pref_etiq_vsize':
            case 'pref_etiq_cols':
            case 'pref_etiq_rows':
            case 'pref_etiq_corps':
            case 'pref_card_marges_v':
            case 'pref_card_marges_h':
            case 'pref_card_hspace':
            case 'pref_card_vspace':
                if (!is_numeric($value) || $value < 0) {
                    $this->errors[] = _T("- The numbers and measures have to be integers!");
                }
                break;
            case 'pref_card_vsize':
                if (!is_numeric($value) || $value < 40 || $value > 55) {
                    $this->errors[] = _T("- The card height have to be an integer between 40 and 55!");
                }
                break;
            case 'pref_card_hsize':
                if (!is_numeric($value) || $value < 70 || $value > 95) {
                    $this->errors[] = _T("- The card width have to be an integer between 70 and 95!");
                }
                break;
            case 'pref_card_tcol':
            case 'pref_card_scol':
            case 'pref_card_bcol':
            case 'pref_card_hcol':
                $matches = [];
                if (!preg_match("/^(#)?([0-9A-F]{6})$/i", $value, $matches)) {
                    // Set strip background colors to black or white (for tcol)
                    $value = ($fieldname == 'pref_card_tcol' ? '#FFFFFF' : '#000000');
                } else {
                    $value = '#' . $matches[2];
                }
                break;
            case 'pref_admin_pass':
                if (Galette::isDemo()) {
                    Analog::log(
                        'Trying to set superadmin pass while in DEMO.',
                        Analog::WARNING
                    );
                } else {
                    $pwcheck = new \Galette\Util\Password($this);
                    $pwcheck->addPersonalInformation([$this->pref_admin_login]);
                    if (!$pwcheck->isValid($value)) {
                        $this->errors = array_merge(
                            $this->errors,
                            $pwcheck->getErrors()
                        );
                    }
                }
                break;
            case 'pref_membership_ext':
                if (!is_numeric($value) || $value <= 0) {
                    $this->errors[] = _T("- Invalid number of months of membership extension.");
                }
                break;
            case 'pref_beg_membership':
                $beg_membership = explode("/", $value);
                if (count($beg_membership) != 2) {
                    $this->errors[] = _T("- Invalid format of beginning of membership.");
                } else {
                    $now = getdate();
                    if (!checkdate((int)$beg_membership[1], (int)$beg_membership[0], $now['year'])) {
                        $this->errors[] = _T("- Invalid date for beginning of membership.");
                    }
                }
                break;
            case 'pref_membership_offermonths':
                if (!is_numeric($value) || $value < 0) {
                    $this->errors[] = _T("- Invalid number of offered months.");
                }
                break;
            case 'pref_card_year':
                if ($value !== 'DEADLINE' && !preg_match('/^(?:\d{4}|\d{2})(\D?)(?:\d{4}|\d{2})$/', $value)) {
                    $this->errors[] = _T("- Invalid year for cards.");
                }
                break;
            case 'pref_footer':
                $value = $this->cleanHtmlValue($value);
                break;
            case 'pref_website':
                if (!isValidWebUrl($value)) {
                    $this->errors[] = _T("- Invalid website URL.");
                }
                break;
        }

        return $value;
    }

    /**
     * Will store all preferences in the database
     *
     * @param boolean $updating True if we're updating instance
     *
     * @return boolean
     */
    public function store(bool $updating = false): bool
    {
        try {
            $this->zdb->connection->beginTransaction();
            $update = $this->zdb->update(self::TABLE);
            $update->set(
                [
                    'val_pref'  => ':val_pref'
                ]
            )->where->equalTo('nom_pref', ':nom_pref');

            $stmt = $this->zdb->sql->prepareStatementForSqlObject($update);

            foreach (self::$defaults as $k => $v) {
                if (
                    Galette::isDemo()
                    && in_array($k, ['pref_admin_pass', 'pref_admin_login', 'pref_mail_method'])
                ) {
                    continue;
                }
                Analog::log('Storing ' . $k, Analog::DEBUG);

                $value = $this->prefs[$k];
                //do not store pdf_adhesion_form, it's designed to be overridden by plugin
                if ($k === 'pref_adhesion_form') {
                    if (trim($v) == '') {
                        //Reset to default, should not be empty
                        $v = self::$defaults['pref_adhesion_form'];
                    }
                    $value = $v;
                }
                if ($k === 'pref_card_cols') {
                    $v = PdfMembersCards::getCols();
                    $value = $v;
                }
                if ($k === 'pref_card_rows') {
                    $v = PdfMembersCards::getRows();
                    $value = $v;
                }

                $stmt->execute(
                    [
                        'val_pref'  => $value,
                        'nom_pref'  => $k
                    ]
                );
            }
            $this->zdb->connection->commit();
            Analog::log(
                'Preferences were successfully stored into database.',
                Analog::INFO
            );

            //prevent socials removal; see https://bugs.galette.eu/issues/1912
            if ($updating === false) {
                $this->storeSocials(null);
            }

            return true;
        } catch (Throwable $e) {
            if ($this->zdb->connection->inTransaction()) {
                $this->zdb->connection->rollBack();
            }

            $messages = [];
            do {
                $messages[] = $e->getMessage();
            } while ($e = $e->getPrevious());

            Analog::log(
                'Unable to store preferences | ' . print_r($messages, true),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Returns postal address
     *
     * @return string postal address
     */
    public function getPostalAddress(): string
    {
        $regs = [
            '/%name/',
            '/%complement/',
            '/%address/',
            '/%zip/',
            '/%town/',
            '/%country/',
        ];

        $replacements = null;

        if ($this->prefs['pref_postal_address'] == self::POSTAL_ADDRESS_FROM_PREFS) {
            $_address = $this->prefs['pref_adresse'];
            if ($this->prefs['pref_adresse2']) {
                $_address .= "\n" . $this->prefs['pref_adresse2'];
            }
            $_country = $this->prefs['pref_pays'] != '' ? '- ' . $this->prefs['pref_pays'] : '';
            $replacements = [
                $this->prefs['pref_nom'],
                "\n",
                $_address,
                $this->prefs['pref_cp'],
                $this->prefs['pref_ville'],
                $_country
            ];
        } else {
            //get selected staff member address
            $adh = new Adherent($this->zdb, (int)$this->prefs['pref_postal_staff_member']);
            $_complement = preg_replace(
                ['/%name/', '/%status/'],
                [$this->prefs['pref_nom'], $adh->sstatus],
                _T("%name association's %status")
            ) . "\n";
            $_address = $adh->address;
            $_country = $adh->country != '' ? '- ' . $adh->country : '';

            $replacements = [
                $adh->sfullname . "\n",
                $_complement,
                $_address,
                $adh->zipcode,
                $adh->town,
                $_country
            ];
        }

        /*FIXME: i18n fails :/ */
        /*$r = preg_replace(
            $regs,
            $replacements,
            _T("%name\n%complement\n%address\n%zip %town %country")
        );*/
        return preg_replace(
            $regs,
            $replacements,
            "%name%complement%address\n%zip %town %country"
        );
    }

    /**
     * Returns phone number
     *
     * @return string phone number
     */
    public function getPhoneNumber(): string
    {
        $_phone = '';
        if ($this->prefs['pref_org_phone'] == self::PHONE_NUMBER_FROM_PREFS) {
            $_phone = $this->prefs['pref_org_phone_number'];
        } else {
            //get selected staff phone number
            $adh = new Adherent($this->zdb, (int)$this->prefs['pref_org_phone_staff_member']);
            $_phone = $this->prefs['pref_org_phone'] == self::PHONE_NUMBER_FROM_STAFF ? $adh->phone : $adh->gsm;
        }

        return $_phone;
    }

    /**
     * Are public pages visible?
     *
     * @return boolean
     */
    public function arePublicPagesEnabled(): bool
    {
        return (bool)$this->prefs['pref_bool_publicpages'];
    }

    /**
     * Are public pages visible?
     *
     * @param Authentication $login Authentication instance
     *
     * @return boolean
     *
     * @deprecated 1.2.0
     */
    public function showPublicPages(Authentication $login): bool
    {
        Analog::log(
            'Preferences::showPublicPages() is deprecated, use Preferences::showPublicPage() instead.',
            Analog::WARNING
        );
        return $this->showPublicPage($login, 'pref_publicpages_visibility_memberslist')
            || $this->showPublicPage($login, 'pref_publicpages_visibility_membersgallery');
    }

    /**
     * Are public pages visible?
     *
     * @param Authentication $login Authentication instance
     * @param string         $right Right to check
     *
     * @return boolean
     */
    public function showPublicPage(Authentication $login, string $right): bool
    {
        if (!$this->arePublicPagesEnabled()) {
            return false;
        }

        //if public pages are actives, let's check if we
        //display them for curent call
        if (!isset($this->prefs[$right])) {
            //Core does not handle plugins permission, just a global right.
            $right = 'pref_publicpages_visibility_generic';
        }
        switch ($this->prefs[$right]) {
            case self::PUBLIC_PAGES_VISIBILITY_INHERIT:
                //inherit from generic right
                return $this->showPublicPage($login, 'pref_publicpages_visibility_generic');
            case self::PUBLIC_PAGES_VISIBILITY_PUBLIC:
                //pages are publicly visibles
                return true;
            case self::PUBLIC_PAGES_VISIBILITY_RESTRICTED:
                //pages should be displayed only for up-to-date members
                return
                    $login->isUp2Date()
                    || $login->isAdmin()
                    || $login->isStaff()
                ;
            case self::PUBLIC_PAGES_VISIBILITY_PRIVATE:
                //pages should be displayed only for staff and admins
                return $login->isAdmin() || $login->isStaff();
            case self::PUBLIC_PAGES_VISIBILITY_HIDDEN:
                return false;
            default:
                throw new \RuntimeException('Unknown public pages right: ' . $this->prefs[$right]);
        }
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return mixed the called property
     */
    public function __get(string $name): mixed
    {
        $forbidden = ['defaults'];
        $virtuals = ['vpref_email_newadh'];
        $types = [
            'int' => [
                'pref_card_address',
                'pref_card_hsize',
                'pref_card_hspace',
                'pref_card_marges_h',
                'pref_card_marges_v',
                'pref_card_vsize',
                'pref_card_vspace',
                'pref_default_paymenttype',
                'pref_etiq_marges_v',
                'pref_etiq_marges_h',
                'pref_etiq_hspace',
                'pref_etiq_vspace',
                'pref_etiq_hsize',
                'pref_etiq_vsize',
                'pref_etiq_cols',
                'pref_etiq_rows',
                'pref_etiq_corps',
                'pref_filter_account',
                'pref_mail_method',
                'pref_membership_ext',
                'pref_numrows',
                'pref_postal_address',
                'pref_postal_staff_member',
                'pref_org_phone',
                'pref_org_phone_staff',
                'pref_password_length',
                'pref_password_strength',
                'pref_publicpages_visibility_generic',
                'pref_publicpages_visibility_documents',
                'pref_publicpages_visibility_memberslist',
                'pref_publicpages_visibility_membersgallery',
                'pref_publicpages_visibility_stafflist',
                'pref_publicpages_visibility_staffgallery',
                'pref_redirect_on_create',
                'pref_statut'
            ],
            'bool' => [
                'pref_bool_create_member',
                'pref_bool_groupsmanagers_create_member',
                'pref_bool_groupsmanagers_edit_member',
                'pref_bool_groupsmanagers_edit_groups',
                'pref_bool_groupsmanagers_exports',
                'pref_bool_groupsmanagers_mailings',
                'pref_bool_groupsmanagers_create_contributions',
                'pref_bool_groupsmanagers_create_transactions',
                'pref_bool_groupsmanagers_see_contributions',
                'pref_bool_groupsmanagers_see_transactions',
                'pref_bool_mailadh',
                'pref_bool_mailowner',
                'pref_bool_publicpages',
                'pref_bool_selfsubscribe',
                'pref_bool_empty_form_link',
                'pref_bool_wrap_mails',
                'pref_disable_members_socials',
                'pref_editor_enabled',
                'pref_etiq_border',
                'pref_force_picture_ratio',
                'pref_mail_smtp_auth',
                'pref_mail_smtp_secure',
                'pref_mail_allow_unsecure',
                'pref_password_blacklist',
                'pref_hide_bg_image',
                'pref_enable_custom_colors'
            ]
        ];

        if (!in_array($name, $forbidden) && isset($this->prefs[$name])) {
            if (
                Galette::isDemo()
                && $name == 'pref_mail_method'
            ) {
                return GaletteMail::METHOD_DISABLED;
            } elseif ($name == 'pref_footer') {
                return $this->cleanHtmlValue($this->prefs[$name]);
            } else {
                if ($name == 'pref_adhesion_form' && $this->prefs[$name] == '') {
                    $this->prefs[$name] = self::$defaults['pref_adhesion_form'];
                }
                $value = $this->prefs[$name];
                if ($this->zdb->isPostgres() && $value === 'f') {
                    $value = false;
                }

                if ($name === 'pref_email_newadh') {
                    $values = explode(',', $value);
                    $value = $values[0]; //take first as default
                }

                if (in_array($name, $types['int']) && $value !== '') {
                    $value = (int)$value;
                }

                if (in_array($name, $types['bool']) && $value !== '') {
                    $value = (bool)$value;
                }

                return $value;
            }
        } elseif (in_array($name, $virtuals)) {
            $virtual = str_replace('vpref_', 'pref_', $name);
            return explode(',', $this->prefs[$virtual]);
        } elseif ($name === 'socials') {
            return $this->socials;
        } else {
            Analog::log(
                'Preference `' . $name . '` is not set or is forbidden',
                Analog::INFO
            );
            return false;
        }
    }

    /**
     * Global isset method
     * Required for twig to access properties via __get
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        $forbidden = ['defaults'];
        $virtuals = ['vpref_email_newadh'];

        if (!in_array($name, $forbidden) && isset($this->prefs[$name])) {
            return true;
        } elseif (in_array($name, $virtuals)) {
            return true;
        } elseif ($name === 'socials') {
            return true;
        } else {
            Analog::log(
                'Preference `' . $name . '` is not set or is forbidden',
                Analog::INFO
            );
            return false;
        }
    }

    /**
     * Get default preferences
     *
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        return self::$defaults;
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param mixed  $value a relevant value for the property
     *
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        //does this pref exist?
        if (!array_key_exists($name, self::$defaults)) {
            Analog::log(
                'Trying to set a preference value which does not seem to exist ('
                . $name . ')',
                Analog::WARNING
            );
            return;
        }

        if (($name == 'pref_email' || $name == 'pref_email_newadh' || $name == 'pref_email_reply_to') && Galette::isDemo()) {
            Analog::log(
                'Trying to set pref_email while in DEMO.',
                Analog::WARNING
            );
            return;
        }

        // now, check validity
        if ($value != '') {
            $value = $this->validateValue($name, $value);
        }

        //some values need to be changed (e.g., passwords)
        if ($name == 'pref_admin_pass') {
            $value = password_hash($value, PASSWORD_BCRYPT);
        }

        //okay, let's update value
        $this->prefs[$name] = $value;
    }

    /**
     * Get instance URL from configuration (if set) or guessed if not
     *
     * @return string
     */
    public function getURL(): string
    {
        $url = null;
        if (isset($this->prefs['pref_galette_url']) && !empty($this->prefs['pref_galette_url'])) {
            $url = $this->prefs['pref_galette_url'];
        } else {
            $url = $this->getDefaultURL();
        }
        return $url;
    }

    /**
     * Get default URL (when not set by user in preferences)
     *
     * @return string
     */
    public function getDefaultURL(): string
    {
        if (defined('GALETTE_CRON')) {
            if (defined('GALETTE_URI')) {
                return GALETTE_URI;
            } else {
                throw new \RuntimeException(_T('Please define constant "GALETTE_URI" with the path to your instance.'));
            }
        }

        $scheme = (isset($_SERVER['HTTPS']) ? 'https' : 'http');
        return $scheme . '://' . $_SERVER['HTTP_HOST'];
    }

    /**
     * Get last telemetry date
     *
     * @return string
     */
    public function getTelemetryDate(): string
    {
        $rawdate = $this->prefs['pref_telemetry_date'];
        if ($rawdate) {
            $date = new \DateTime($rawdate);
            return $date->format(_T('Y-m-d H:i:s'));
        } else {
            return _T('Never');
        }
    }

    /**
     * Get last telemetry registration date
     *
     * @return string|null
     */
    public function getRegistrationDate(): ?string
    {
        $rawdate = $this->prefs['pref_registration_date'];
        if ($rawdate) {
            $date = new \DateTime($rawdate);
            return $date->format(_T('Y-m-d H:i:s'));
        }

        return null;
    }

    /**
     * Check member cards sizes
     * Always a A4/portrait
     *
     * @return array<string>
     */
    public function checkCardsSizes(): array
    {
        $warning_detected = [];
        //check page width
        $max = PdfMembersCards::PAGE_WIDTH;
        //margins
        $size = $this->pref_card_marges_h * 2;
        //cards
        $size += PdfMembersCards::getWidth() * PdfMembersCards::getCols();
        //spacing
        $size += $this->pref_card_hspace * (PdfMembersCards::getCols() - 1);
        if ($size > $max) {
            $warning_detected[] = _T('Current cards configuration may exceed page width!');
        }

        $max = PdfMembersCards::PAGE_HEIGHT;
        //margins
        $size = $this->pref_card_marges_v * 2;
        //cards
        $size += PdfMembersCards::getHeight() * PdfMembersCards::getRows();
        //spacing
        $size += $this->pref_card_vspace * (PdfMembersCards::getRows() - 1);
        if ($size > $max) {
            $warning_detected[] = _T('Current cards configuration may exceed page height!');
        }

        return $warning_detected;
    }

    /**
     * Get errors
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Build legend array
     *
     * @return array<string, array<string, array<string, array<string, string>>|string>>
     */
    public function getLegend(): array
    {
        $legend = [];

        $legend['main'] = [
            'title'     => _T('Main information'),
            'patterns'  => $this->getMainPatterns()
        ];

        $s_patterns = $this->getSignaturePatterns(false);
        if (count($s_patterns)) {
            $legend['socials'] = [
                'title' => _T('Social networks'),
                'patterns' => $this->getSignaturePatterns(false)
            ];
        }

        return $legend;
    }

    /**
     * Get email signature
     *
     * @param PHPMailer $mail PHPMailer instance
     *
     * @return string
     */
    public function getMailSignature(PHPMailer $mail): string
    {
        global $routeparser;

        $signature = $this->pref_mail_sign;

        if (trim($signature) == '') {
            return '';
        }

        $this->setPreferences($this)->setRouteparser($routeparser);
        $this->setPatterns(
            $this->getMainPatterns() + $this->getSignaturePatterns()
        );
        $this
            ->setMail($mail)
            ->setMain()
            ->setSocialReplacements();

        $signature = $this->proceedReplacements($signature);

        return "\r\n-- \r\n" . $signature;
    }

    /**
     * Get patterns for mail signature
     *
     * @param boolean $legacy Whether to load legacy patterns
     *
     * @return array<string, array<string, string>>
     */
    protected function getSignaturePatterns(bool $legacy = true): array
    {
        $s_patterns = [];
        $social = new Social($this->zdb);

        $types = $this->getCoreRegisteredTypes() + $social->getSystemTypes(false);

        foreach ($types as $type) {
            $s_patterns['asso_social_' . strtolower($type)] = [
                'title' => $social->getSystemType($type),
                'pattern' => '/{ASSO_SOCIAL_' . strtoupper($type) . '}/'
            ];
        }

        if ($legacy === true) {
            $main = $this->getMainPatterns();
            $s_patterns['_asso_name'] = [
                'title'     => $main['asso_name']['title'],
                'pattern'   => '/{NAME}/'
            ];

            $s_patterns['_asso_website'] = [
                'title'     => $main['asso_website']['title'],
                'pattern'   => '/{WEBSITE}/'
            ];

            foreach ([Social::FACEBOOK, Social::TWITTER, Social::LINKEDIN, Social::VIADEO] as $legacy_type) {
                $s_patterns['_asso_social_' . $legacy_type] = [
                    'title' => $s_patterns['asso_social_' . $legacy_type]['title'],
                    'pattern' => '/{' . strtoupper($legacy_type) . '}/'
                ];
            }
        }

        return $s_patterns;
    }

    /**
     * Set emails replacements
     *
     * @return $this
     */
    public function setSocialReplacements(): self
    {
        $replacements = [];

        $done_replacements = $this->getReplacements();
        $replacements['_asso_name'] = $done_replacements['asso_name'];
        $replacements['asso_website'] = $this->pref_website;
        $replacements['_asso_website'] = $replacements['asso_website'];

        $social = new Social($this->zdb);
        $types = $this->getCoreRegisteredTypes() + $social->getSystemTypes(false);

        foreach ($types as $type) {
            $replace_value = null;
            $socials = Social::getListForMember(null, $type);
            if (count($socials)) {
                $replace_value = '';
                foreach ($socials as $social) {
                    if ($replace_value != '') {
                        $replace_value .= ', ';
                    }
                    $replace_value .= $social->url;
                }
            }
            $replacements['asso_social_' . strtolower($type)] = $replace_value;
        }


        foreach ([Social::FACEBOOK, Social::TWITTER, Social::LINKEDIN, Social::VIADEO] as $legacy_type) {
            $replacements['_asso_social_' . $legacy_type] = $replacements['asso_social_' . $legacy_type];
        }

        $this->setReplacements($replacements);

        return $this;
    }

    /**
     * Purify HTML value
     *
     * @param string $value Value to clean
     *
     * @return string
     */
    public function cleanHtmlValue(string $value): string
    {
        $config = \HTMLPurifier_Config::createDefault();
        $cache_dir = rtrim(GALETTE_CACHE_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'htmlpurifier';
        if (!file_exists($cache_dir)) {
            mkdir($cache_dir, 0o755, true);
        }
        $config->set('Cache.SerializerPath', $cache_dir);
        $purifier = new \HTMLPurifier($config);
        return $purifier->purify($value);
    }

    /**
     * Update one preference field in database
     *
     * @param string $field Field name
     * @param mixed  $value Field value
     *
     * @return bool
     */
    protected function updateOneField(
        string $field,
        mixed $value,
    ): bool {
        try {
            $update = $this->zdb->update(self::TABLE);
            $update
                ->set(['val_pref'  => $value])
                ->where->equalTo('nom_pref', $field);
            $this->zdb->execute($update);
            $this->$field = $value;
            Analog::log(
                sprintf('%s updated.', $field),
                Analog::INFO
            );
            return true;
        } catch (Throwable $e) {
            $messages = [];
            do {
                $messages[] = $e->getMessage();
            } while ($e = $e->getPrevious());

            Analog::log(
                sprintf('Unable to store update field %s | %s', $field, print_r($messages, true)),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Update telemetry date only
     *
     * @return bool
     */
    public function updateTelemetryDate(): bool
    {
        return $this->updateOneField(
            'pref_telemetry_date',
            date('Y-m-d H:i:s')
        );
    }

    /**
     * Update registration date only
     *
     * @return bool
     */
    public function updateRegistrationDate(): bool
    {
        return $this->updateOneField(
            'pref_registration_date',
            date('Y-m-d H:i:s')
        );
    }

    /**
     * Generate and store UUID of specified type
     *
     * @param string $type UUID type to generate
     *
     * @return string
     */
    public function generateUUID(string $type): string
    {
        $uuid = Text::getRandomString(40);
        $field = 'pref_' . $type . '_uuid';
        $this->updateOneField(
            $field,
            $uuid
        );
        $this->$field = $uuid;
        return $uuid;
    }

    /**
     * Get required fields
     *
     * @param Login $login Logged in user
     *
     * @return array<string, int>
     */
    public function getRequiredFields(Login $login): array
    {
        if ($login->isSuperAdmin() && !Galette::isDemo()) {
            $this->required['pref_admin_login'] = 1;
        }
        return $this->required;
    }

    /**
     * Check if CSS is impacted when storing preferences
     *
     * @param array<string, mixed> $values Values to check
     *
     * @return void
     */
    protected function checkCssImpacted(array $values): void
    {
        //check if custom CSS is enabled
        if (($values['pref_enable_custom_colors'] ?? '') != $this->pref_enable_custom_colors) {
            $this->delete_dark_css = true;
            return;
        }

        $css_fields = array_filter(
            array_keys($this->prefs),
            fn($field) => str_starts_with($field, 'pref_cc_')
        );
        foreach ($css_fields as $css_field) {
            if ($values[$css_field] != $this->$css_field) {
                $this->delete_dark_css = true;
                return;
            }
        }
    }

    /**
     * Reset dark mode CSS file
     *
     * @param \Slim\Flash\Messages $flash Flash messages instance
     *
     * @return void
     */
    public function resetDarkCss(\Slim\Flash\Messages $flash): void
    {
        if (!$this->delete_dark_css) {
            return;
        }

        $cssfile = GALETTE_CACHE_DIR . '/dark.css';
        if (file_exists($cssfile)) {
            unlink($cssfile);
            // Inform user when the dark mode CSS file has been reset
            $flash->addMessage(
                'info_detected',
                _T("Dark mode CSS file has been reset.")
            );
        }
    }

    /**
     * Handle logo
     *
     * @param Logo                $logo  Logo instance
     * @param array<string,mixed> $files Files sent
     *
     * @return array<string>|true
     */
    public function handleLogo(Logo $logo, array $files): array|bool
    {
        $this->errors = [];
        if ($files['logo']['error'] === UPLOAD_ERR_OK) {
            if ($files['logo']['tmp_name'] != '' && is_uploaded_file($files['logo']['tmp_name'])) {
                $res = $logo->store($files['logo']);
                if ($res !== true) {
                    $this->errors[] = $logo->getErrorMessage($res);
                }
            }
        } elseif ($files['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $this->errors[] = $logo->getPhpErrorMessage(
                $files['logo']['error']
            );
        }

        if (count($this->errors) > 0) {
            Analog::log(
                'Some errors has been thew attempting to edit/store logo' . "\n"
                . print_r($this->errors, true),
                Analog::WARNING
            );
            return $this->errors;
        } else {
            return true;
        }
    }

    /**
     * Handle print logo
     *
     * @param PrintLogo           $print_logo PrintLogo instance
     * @param array<string,mixed> $files      Files sent
     *
     * @return array<string>|true
     */
    public function handlePrintLogo(PrintLogo $print_logo, array $files): array|bool
    {
        if ($files['card_logo']['error'] === UPLOAD_ERR_OK) {
            if ($files['card_logo']['tmp_name'] != '' && is_uploaded_file($files['card_logo']['tmp_name'])) {
                $res = $print_logo->store($files['card_logo']);
                if ($res !== true) {
                    $this->errors[] = $print_logo->getErrorMessage($res);
                }
            }
        } elseif ($files['card_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $this->errors[] = $print_logo->getPhpErrorMessage(
                $files['card_logo']['error']
            );
        }

        if (count($this->errors) > 0) {
            Analog::log(
                'Some errors has been thew attempting to edit/store print logo' . "\n"
                . print_r($this->errors, true),
                Analog::WARNING
            );
            return $this->errors;
        } else {
            return true;
        }
    }
}
