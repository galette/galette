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

declare(strict_types=1);

namespace Galette\Core;

use Galette\Entity\PaymentType;
use Galette\Entity\Social;
use Galette\Features\Replacements;
use Galette\Features\Socials;
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
 * @property boolean $pref_disable_members_socials Disable social networks for members
 * @property string $pref_lang Default instance language
 * @property integer $pref_numrows Default number of rows in lists
 * @property integer $pref_log History, one of self::LOG_*
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
 * @property string $pref_theme Prefered theme
 * @property boolean $pref_bool_publicpages
 * @property integer $pref_publicpages_visibility
 * @property boolean $pref_bool_selfsubscribe
 * @property string $pref_member_form_grid
 * @property string $pref_mail_sign
 * @property string $pref_new_contrib_script
 * @property boolean $pref_bool_wrap_mails
 * @property string $pref_rss_url
 * @property boolean $pref_show_id
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
 * @property-read array $vpref_email_newadh list of mail senders
 * @property-read array $vpref_email list of mail senders
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

    /** Public pages stuff */
    /** Public pages are publically visibles */
    public const PUBLIC_PAGES_VISIBILITY_PUBLIC = 0;
    /** Public pages are visibles for up to date members only */
    public const PUBLIC_PAGES_VISIBILITY_RESTRICTED = 1;
    /** Public pages are visibles for admin and staff members only */
    public const PUBLIC_PAGES_VISIBILITY_PRIVATE = 2;

    public const LOG_DISABLED = 0;
    public const LOG_ENABLED = 1;

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

    /** @var array<string> */
    private static array $fields = array(
        'nom_pref',
        'val_pref'
    );

    /** @var array<string, bool|int|string> */
    private static array $defaults = array(
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
        'pref_disable_members_socials' => false,
        'pref_lang'        =>    I18n::DEFAULT_LANG,
        'pref_numrows'        =>    30,
        'pref_log'        =>    self::LOG_ENABLED,
        'pref_statut'        =>    Status::DEFAULT_STATUS,
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
        'pref_card_hsize'    =>    84,
        'pref_card_vsize'    =>    52,
        'pref_card_rows'    =>    6,
        'pref_card_cols'    =>    2,
        'pref_card_address'    =>    1,
        'pref_card_year'    =>    '',
        'pref_card_marges_v'    =>    15,
        'pref_card_marges_h'    =>    20,
        'pref_card_vspace'    =>    5,
        'pref_card_hspace'    =>    10,
        'pref_card_self'    =>    1,
        'pref_theme'        =>    'default',
        'pref_bool_publicpages' => true,
        'pref_publicpages_visibility' => self::PUBLIC_PAGES_VISIBILITY_RESTRICTED,
        'pref_mail_sign' => "{ASSO_NAME}\r\n\r\n{ASSO_WEBSITE}",
        /* Preferences for member/subscribe form */
        'pref_bool_selfsubscribe' => true,
        'pref_member_form_grid' => 'one',
        /* New contribution script */
        'pref_new_contrib_script' => '',
        'pref_bool_wrap_mails' => true,
        'pref_rss_url' => 'https://galette.eu/dc/index.php/feed/atom',
        'pref_show_id' => false,
        'pref_adhesion_form' => '\Galette\IO\PdfAdhesionForm',
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
        'pref_noindex' => false
    );

    /** @var Social[] */
    private array $socials;

    // flagging required fields
    /** @var array<string> */
    private array $required = array(
        'pref_nom',
        'pref_lang',
        'pref_numrows',
        'pref_log',
        'pref_statut',
        'pref_etiq_marges_v',
        'pref_etiq_marges_h',
        'pref_etiq_hspace',
        'pref_etiq_vspace',
        'pref_etiq_hsize',
        'pref_etiq_vsize',
        'pref_etiq_cols',
        'pref_etiq_rows',
        'pref_etiq_corps',
        'pref_card_marges_v',
        'pref_card_marges_h',
        'pref_card_hspace',
        'pref_card_vspace',
        'pref_card_hsize',
        'pref_card_vsize'
    );

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
     * Check if all fields referenced in the default array does exists,
     * create them if not
     *
     * @return boolean
     */
    private function checkUpdate(): bool
    {
        $proceed = false;
        $params = array();
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
                $params[] = array(
                    'nom_pref'  => $k,
                    'val_pref'  => $v
                );
            }
        }
        if ($proceed !== false) {
            try {
                $insert = $this->zdb->insert(self::TABLE);
                $insert->values(
                    array(
                        'nom_pref'  => ':nom_pref',
                        'val_pref'  => ':val_pref'
                    )
                );
                $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

                foreach ($params as $p) {
                    $stmt->execute(
                        array(
                            'nom_pref' => $p['nom_pref'],
                            'val_pref' => $p['val_pref']
                        )
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
        $this->prefs = array();

        try {
            $result = $this->zdb->selectAll(self::TABLE);
            foreach ($result as $pref) {
                $this->prefs[$pref->nom_pref] = $pref->val_pref;
            }
            $this->socials = Social::getListForMember(null);
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Preferences cannot be loaded. Galette should not work without ' .
                'preferences. Exiting.',
                Analog::URGENT
            );
            return false;
        }
    }

    /**
     * Set default preferences at install time
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
                array(
                    'nom_pref'  => ':nom_pref',
                    'val_pref'  => ':val_pref'
                )
            );
            $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

            foreach ($values as $k => $v) {
                $stmt->execute(
                    array(
                        'nom_pref' => $k,
                        'val_pref' => $v
                    )
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
        $insert_values = array();
        if ($login->isSuperAdmin() && !Galette::isDemo()) {
            $this->required[] = 'pref_admin_login';
        }

        // obtain fields
        foreach ($this->getFieldsNames() as $fieldname) {
            if (isset($values[$fieldname])) {
                if (is_string($values[$fieldname])) {
                    $value = trim($values[$fieldname]);
                } else {
                    $value = $values[$fieldname];
                }
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
                Galette::isHosted() &&
                in_array(
                    $insert_values['pref_mail_method'],
                    [GaletteMail::METHOD_PHPMAIL, GaletteMail::METHOD_SENDMAIL, GaletteMail::METHOD_QMAIL]
                )
            ) {
                $this->errors[] = _T("- Only SMTP and GMail are allowed on hosted instances.");
            }
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
            if ($insert_values['pref_mail_method'] == GaletteMail::METHOD_SMTP) {
                if (
                    !isset($insert_values['pref_mail_smtp_host'])
                    || $insert_values['pref_mail_smtp_host'] == ''
                ) {
                    $this->errors[] = _T("- You must indicate the SMTP server you want to use!");
                }
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
            isset($insert_values['pref_beg_membership'])
            && $insert_values['pref_beg_membership'] != ''
            && isset($insert_values['pref_membership_ext'])
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
        foreach ($this->required as $val) {
            if (!isset($values[$val]) || is_string($values[$val]) && trim($values[$val]) == '') {
                $this->errors[] = str_replace(
                    '%field',
                    $val,
                    _T("- Mandatory field %field empty.")
                );
            }
        }

        if (!Galette::isDemo() && isset($values['pref_admin_pass_check'])) {
            // Check passwords. Hash will be done into the Preferences class
            if (strcmp($insert_values['pref_admin_pass'], $values['pref_admin_pass_check']) != 0) {
                $this->errors[] = _T("Passwords mismatch");
            }
        }

        //postal address
        if (isset($insert_values['pref_postal_address'])) {
            $value = $insert_values['pref_postal_address'];
            if ($value == Preferences::POSTAL_ADDRESS_FROM_PREFS) {
                if (isset($insert_values['pref_postal_staff_member'])) {
                    unset($insert_values['pref_postal_staff_member']);
                }
            } elseif ($value == Preferences::POSTAL_ADDRESS_FROM_STAFF) {
                if ($value < 1) {
                    $this->errors[] = _T("You have to select a staff member");
                }
            }
        }

        // update preferences
        foreach ($insert_values as $champ => $valeur) {
            if (
                $login->isSuperAdmin()
                || $champ != 'pref_admin_pass' && $champ != 'pref_admin_login'
            ) {
                if (
                    ($champ == "pref_admin_pass" && $_POST['pref_admin_pass'] != '')
                    || ($champ != "pref_admin_pass")
                ) {
                    $this->$champ = $valeur;
                }
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
                //check emails validity
                //may be a comma separated list of valid emails identifiers:
                //"The Name <mail@domain.com>,The Other <other@mail.com>" expect for reply_to.
                $addresses = [];
                if (trim($value) != '') {
                    if ($fieldname == 'pref_email_newadh') {
                        $addresses = explode(',', $value);
                    } else {
                        $addresses = [$value];
                    }
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
                } else {
                    if (strlen($value) < 4) {
                        $this->errors[] = _T("- The username must be composed of at least 4 characters!");
                    } else {
                        //check if login is already taken
                        if ($login->loginExists($value)) {
                            $this->errors[] = _T("- This username is already used by another member !");
                        }
                    }
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
                if (!is_numeric($value) || $value < 0) {
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
     * @return boolean
     */
    public function store(): bool
    {
        try {
            $this->zdb->connection->beginTransaction();
            $update = $this->zdb->update(self::TABLE);
            $update->set(
                array(
                    'val_pref'  => ':val_pref'
                )
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
                //do not store pdf_adhesion_form, it's designed to be overriden by plugin
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
                    array(
                        'val_pref'  => $value,
                        'nom_pref'  => $k
                    )
                );
            }
            $this->zdb->connection->commit();
            Analog::log(
                'Preferences were successfully stored into database.',
                Analog::INFO
            );

            $this->storeSocials(null);

            return true;
        } catch (Throwable $e) {
            $this->zdb->connection->rollBack();

            $messages = array();
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
        $regs = array(
            '/%name/',
            '/%complement/',
            '/%address/',
            '/%zip/',
            '/%town/',
            '/%country/',
        );

        $replacements = null;

        if ($this->prefs['pref_postal_address'] == self::POSTAL_ADDRESS_FROM_PREFS) {
            $_address = $this->prefs['pref_adresse'];
            if ($this->prefs['pref_adresse2'] && $this->prefs['pref_adresse2'] != '') {
                $_address .= "\n" . $this->prefs['pref_adresse2'];
            }
            $replacements = array(
                $this->prefs['pref_nom'],
                "\n",
                $_address,
                $this->prefs['pref_cp'],
                $this->prefs['pref_ville'],
                $this->prefs['pref_pays']
            );
        } else {
            //get selected staff member address
            $adh = new Adherent($this->zdb, (int)$this->prefs['pref_postal_staff_member']);
            $_complement = preg_replace(
                array('/%name/', '/%status/'),
                array($this->prefs['pref_nom'], $adh->sstatus),
                _T("%name association's %status")
            ) . "\n";
            $_address = $adh->address;

            $replacements = array(
                $adh->sfullname . "\n",
                $_complement,
                $_address,
                $adh->zipcode,
                $adh->town,
                $adh->country
            );
        }

        /*FIXME: i18n fails :/ */
        /*$r = preg_replace(
            $regs,
            $replacements,
            _T("%name\n%complement\n%address\n%zip %town - %country")
        );*/
        $r = preg_replace(
            $regs,
            $replacements,
            "%name%complement%address\n%zip %town - %country"
        );
        return $r;
    }

    /**
     * Are public pages visible?
     *
     * @param Authentication $login Authentication instance
     *
     * @return boolean
     */
    public function showPublicPages(Authentication $login): bool
    {
        if ($this->prefs['pref_bool_publicpages']) {
            //if public pages are actives, let's check if we
            //display them for curent call
            switch ($this->prefs['pref_publicpages_visibility']) {
                case self::PUBLIC_PAGES_VISIBILITY_PUBLIC:
                    //pages are publicly visibles
                    return true;
                case self::PUBLIC_PAGES_VISIBILITY_RESTRICTED:
                    //pages should be displayed only for up-to-date members
                    if (
                        $login->isUp2Date()
                        || $login->isAdmin()
                        || $login->isStaff()
                    ) {
                        return true;
                    } else {
                        return false;
                    }
                case self::PUBLIC_PAGES_VISIBILITY_PRIVATE:
                    //pages should be displayed only for staff and admins
                    if ($login->isAdmin() || $login->isStaff()) {
                        return true;
                    } else {
                        return false;
                    }
                default:
                    //should never be there
                    return false;
            }
        } else {
            return false;
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
        $forbidden = array('defaults');
        $virtuals = array('vpref_email', 'vpref_email_newadh');
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
                'pref_log',
                'pref_mail_method',
                'pref_membership_ext',
                'pref_numrows',
                'pref_postal_address',
                'pref_postal_staff_member',
                'pref_password_length',
                'pref_password_strength',
                'pref_publicpages_visibility',
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
                'pref_bool_mailadh',
                'pref_bool_mailowner',
                'pref_bool_publicpages',
                'pref_bool_selfsubscribe',
                'pref_bool_wrap_mails',
                'pref_disable_members_socials',
                'pref_editor_enabled',
                'pref_etiq_border',
                'pref_force_picture_ratio',
                'pref_mail_smtp_auth',
                'pref_mail_smtp_secure',
                'pref_mail_allow_unsecure',
                'pref_password_blacklist',
                'pref_show_id',
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
                if (TYPE_DB === Db::PGSQL) {
                    if ($value === 'f') {
                        $value = false;
                    }
                }

                if (in_array($name, ['vpref_email', 'pref_email_newadh'])) {
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
        $forbidden = array('defaults');
        $virtuals = array('vpref_email', 'vpref_email_newadh');

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
        //does this pref exists ?
        if (!array_key_exists($name, self::$defaults)) {
            Analog::log(
                'Trying to set a preference value which does not seem to exist ('
                . $name . ')',
                Analog::WARNING
            );
            return;
        }

        if (
            $name == 'pref_email'
            || $name == 'pref_email_newadh'
            || $name == 'pref_email_reply_to'
        ) {
            if (Galette::isDemo()) {
                Analog::log(
                    'Trying to set pref_email while in DEMO.',
                    Analog::WARNING
                );
                return;
            }
        }

        // now, check validity
        if ($value != '') {
            $value = $this->validateValue($name, $value);
        }

        //some values need to be changed (eg. passwords)
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
        $uri = $scheme . '://' . $_SERVER['HTTP_HOST'];
        return $uri;
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
     * @return array<string, array<string, string>>
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
            mkdir($cache_dir, 0755, true);
        }
        $config->set('Cache.SerializerPath', $cache_dir);
        $purifier = new \HTMLPurifier($config);
        return $purifier->purify($value);
    }
}
