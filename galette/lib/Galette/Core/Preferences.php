<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Galette\Features\Dynamics;
use Safe\DateTime;
use Galette\Entity\Social;
use Galette\Features\Replacements;
use Galette\Features\Socials;
use Galette\Util\Text;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;
use Analog\Analog;
use Galette\Entity\Adherent;
use Galette\Entity\Status;
use Galette\IO\PdfMembersCards;
use Galette\Repository\Members;

use function Safe\mkdir;
use function Safe\preg_match;
use function Safe\preg_replace;
use function Safe\unlink;

/**
 * Preferences for galette
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property      string   $pref_admin_login                              Super admin login
 * @property      string   $pref_admin_pass                               Super admin password
 * @property      string   $pref_nom                                      Association name
 * @property      string   $pref_slogan                                   Association slogan
 * @property      string   $pref_adresse                                  Address
 * @property      string   $pref_adresse2                                 Address continuation
 * @property      string   $pref_cp                                       Association zipcode
 * @property      string   $pref_ville                                    Association
 * @property      string   $pref_region                                   Region
 * @property      string   $pref_pays                                     Country
 * @property      int      $pref_postal_address                           Postal address to use, one of self::POSTAL_ADDRESS*
 * @property      int      $pref_postal_staff_member                      Staff member ID from which retrieve postal address
 * @property      string   $pref_org_phone_number                         Phone number
 * @property      int      $pref_org_phone                                Phone number to use, one of self::PHONE_NUMBER*
 * @property      int      $pref_org_phone_staff_member                   Staff member ID from which retrieve phone number
 * @property      string   $pref_org_email                                Email address
 * @property      bool     $pref_disable_members_socials                  Disable social networks for members
 * @property      string   $pref_lang                                     Default instance language
 * @property      int      $pref_numrows                                  Default number of rows in lists
 * @property      int      $pref_statut                                   Default status for new members
 * @property      string   $pref_email_nom
 * @property      string   $pref_email
 * @property      string   $pref_email_newadh
 * @property      bool     $pref_bool_mailadh
 * @property      bool     $pref_bool_mailowner
 * @property      bool     $pref_editor_enabled
 * @property      int      $pref_mail_method                              Mail method, see GaletteMail::METHOD_*
 * @property      string   $pref_mail_smtp
 * @property      string   $pref_mail_smtp_host
 * @property      bool     $pref_mail_smtp_auth
 * @property      bool     $pref_mail_smtp_secure
 * @property      int      $pref_mail_smtp_port
 * @property      string   $pref_mail_smtp_user
 * @property      string   $pref_mail_smtp_password
 * @property      int      $pref_membership_ext
 * @property      string   $pref_beg_membership
 * @property      int      $pref_membership_offermonths
 * @property      string   $pref_email_reply_to
 * @property      string   $pref_website
 * @property      int      $pref_etiq_marges_v
 * @property      int      $pref_etiq_marges_h
 * @property      int      $pref_etiq_hspace
 * @property      int      $pref_etiq_vspace
 * @property      int      $pref_etiq_hsize
 * @property      int      $pref_etiq_vsize
 * @property      int      $pref_etiq_cols
 * @property      int      $pref_etiq_rows
 * @property      int      $pref_etiq_corps
 * @property      bool     $pref_etiq_border
 * @property      bool     $pref_force_picture_ratio
 * @property      string   $pref_member_picture_ratio
 * @property      string   $pref_card_abrev
 * @property      string   $pref_card_strip
 * @property      string   $pref_card_tcol
 * @property      string   $pref_card_scol
 * @property      string   $pref_card_bcol
 * @property      string   $pref_card_hcol
 * @property      bool     $pref_bool_display_title
 * @property      int      $pref_card_address
 * @property      string   $pref_card_year
 * @property      int      $pref_card_marges_v
 * @property      int      $pref_card_marges_h
 * @property      int      $pref_card_vspace
 * @property      int      $pref_card_hspace
 * @property      bool     $pref_card_self
 * @property      int      $pref_card_hsize
 * @property      int      $pref_card_vsize
 * @property      string   $pref_theme                                    Preferred theme
 * @property      bool     $pref_hide_bg_image
 * @property      bool     $pref_enable_custom_colors
 * @property      string   $pref_cc_primary
 * @property      string   $pref_cc_primary_text
 * @property      string   $pref_cc_secondary
 * @property      string   $pref_cc_secondary_text
 * @property      bool     $pref_bool_publicpages
 * @property      int      $pref_publicpages_visibility_generic
 * @property      int      $pref_publicpages_visibility_documents
 * @property      int      $pref_publicpages_visibility_memberslist
 * @property      int      $pref_publicpages_visibility_membersgallery
 * @property      int      $pref_publicpages_visibility_stafflist
 * @property      int      $pref_publicpages_visibility_staffgallery
 * @property      bool     $pref_bool_groupsmanagers_are_staff
 * @property      bool     $pref_bool_selfsubscribe
 * @property      bool     $pref_bool_empty_form_link
 * @property      string   $pref_member_form_grid
 * @property      string   $pref_mail_sign
 * @property      string   $pref_new_contrib_script
 * @property      bool     $pref_bool_wrap_mails
 * @property      string   $pref_rss_url
 * @property      string   $pref_adhesion_form
 * @property      bool     $pref_mail_allow_unsecure
 * @property      string   $pref_instance_uuid
 * @property      string   $pref_registration_uuid
 * @property      string   $pref_telemetry_date
 * @property      string   $pref_registration_date
 * @property      string   $pref_footer
 * @property      int      $pref_filter_account
 * @property      string   $pref_galette_url
 * @property      int      $pref_redirect_on_create
 * @property      int      $pref_password_length
 * @property      bool     $pref_password_blacklist
 * @property      int      $pref_password_strength
 * @property      int      $pref_default_paymenttype
 * @property      bool     $pref_bool_create_member
 * @property      bool     $pref_bool_groupsmanagers_create_member
 * @property      bool     $pref_bool_groupsmanagers_edit_member
 * @property      bool     $pref_bool_groupsmanagers_edit_groups
 * @property      bool     $pref_bool_groupsmanagers_mailings
 * @property      bool     $pref_bool_groupsmanagers_exports
 * @property      bool     $pref_bool_groupsmanagers_create_contributions
 * @property      bool     $pref_bool_groupsmanagers_create_transactions
 * @property      bool     $pref_bool_groupsmanagers_see_contributions
 * @property      bool     $pref_bool_groupsmanagers_see_transactions
 * @property-read string[] $vpref_email_newadh                            list of mail senders
 * @property      bool     $pref_noindex
 * @property      int      $pref_x_forwarded_for_index
 * @property      int      $pref_session_timeout
 */
class Preferences
{
    use Replacements;
    use Socials;
    use Dynamics;

    protected Preferences $preferences; //redefined from Replacements feature - avoid circular dependency
    /** @var array<string, bool|int|string> */
    private array $prefs;
    /** @var array<string> */
    private array $errors = [];

    public const string TABLE = 'preferences';
    public const string PK = 'id_pref';

    /** Postal address will be the one given in the preferences */
    public const int POSTAL_ADDRESS_FROM_PREFS = 0;
    /** Postal address will be the one of the selected staff member */
    public const int POSTAL_ADDRESS_FROM_STAFF = 1;

    /** Phone number will be the one given in the preferences */
    public const int PHONE_NUMBER_FROM_PREFS = 0;
    /** Phone number will be the one of the selected staff member */
    public const int PHONE_NUMBER_FROM_STAFF = 1;
    /** Phone number will be the GSM of the selected staff member */
    public const int PHONE_NUMBER_MOBILE_FROM_STAFF = 2;

    /** Public pages stuff */
    /** Public pages are publicly visibles */
    public const int PUBLIC_PAGES_VISIBILITY_PUBLIC = 0;
    /** Public pages are visibles for up-to-date members only */
    public const int PUBLIC_PAGES_VISIBILITY_RESTRICTED = 1;
    /** Public pages are visibles for admin and staff members only */
    public const int PUBLIC_PAGES_VISIBILITY_PRIVATE = 2;
    /** Public pages are hidden */
    public const int PUBLIC_PAGES_VISIBILITY_HIDDEN = 3;
    public const int PUBLIC_PAGES_VISIBILITY_INHERIT = 4;

    /** No password strength */
    public const int PWD_NONE = 0;
    /** Weak password strength */
    public const int PWD_WEAK = 1;
    /** Medium password strength */
    public const int PWD_MEDIUM = 2;
    /** Strong password strength */
    public const int PWD_STRONG = 3;
    /** Very strong password strength */
    public const int PWD_VERY_STRONG = 4;

    /** Dark mode CSS file should be deleted from cache */
    private bool $delete_dark_css = false;
    /** @var array<string> */
    private static array $fields = [
        'nom_pref',
        'val_pref'
    ];

    /**
     * Preferences defaults, lazily derived from PreferencesSchema
     *
     * @var array<string, bool|int|string>|null
     */
    private static ?array $defaults = null;

    /** @var Social[] */
    private array $socials;

    /** @var array<string, bool> Constants already reported as overriding a preference */
    private array $reported_overrides = [];

    // flagging required fields, derived from PreferencesSchema
    /** @var array<string,int> */
    private array $required = [];

    /**
     * Default constructor
     *
     * @param Db   $zdb  Db instance
     * @param bool $load Automatically load preferences on load
     *
     * @return void
     */
    public function __construct(Db $zdb, bool $load = true)
    {
        $this->zdb = $zdb;
        $this->required = PreferencesSchema::getRequired();
        if ($load) {
            $this->load();
            $this->checkUpdate();
        }
    }

    /**
     * Check if all fields referenced in the default array do exist,
     * create them if not
     */
    private function checkUpdate(): bool
    {
        $params = [];
        foreach (self::defaults() as $k => $v) {
            if (!isset($this->prefs[$k])) {
                if ($k == 'pref_admin_pass' && $v == 'admin') {
                    $v = password_hash($v, PASSWORD_BCRYPT);
                }
                $this->prefs[$k] = $v;
                Analog::log(
                    'The field `' . $k . '` does not exist, Galette will attempt to create it.',
                    Analog::INFO
                );
                $params[] = [
                    'nom_pref'  => $k,
                    'val_pref'  => $v
                ];
            }
        }
        if (count($params)) {
            try {
                $this->zdb->handleSequence(
                    self::TABLE,
                    self::PK,
                    7 //there were 7 entries in preferences before autoincrement was added...
                );

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
                    sprintf(
                        'Unable to add missing preferences. %s',
                        $e->getMessage()
                    ),
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
        } catch (Throwable) {
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
     * @throws Throwable
     */
    public function installInit(string $lang, string $adm_login, string $adm_pass): bool
    {
        try {
            //first, we drop all values
            $delete = $this->zdb->delete(self::TABLE);
            $this->zdb->execute($delete);

            //we then replace default values with the ones user has selected
            $values = self::defaults();
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

            $this->zdb->handleSequence(
                self::TABLE,
                self::PK,
                count(self::defaults())
            );

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
     */
    public function check(array $values, Login $login): bool
    {
        $this->errors = [];
        $this->getRequiredFields($login); //make sure required are all set

        $this->checkCssImpacted($values);

        $insert_values = $this->completeValues($values);

        //cleanup fields for demo
        if (Galette::isDemo()) {
            unset(
                $insert_values['pref_admin_login'],
                $insert_values['pref_admin_pass'],
                $insert_values['pref_mail_method']
            );
        }

        $this->checkRelations($values, $insert_values);

        $this->dynamicsCheck($values, [], []);

        $this->assignValues($insert_values, $login);

        $this->checkSocials($values);

        return 0 === count($this->errors);
    }

    /**
     * Build a complete set of values out of a submitted payload
     *
     * Every known preference gets an entry: a field missing from the payload
     * is blanked, which is what lets an unchecked checkbox turn its preference
     * off.
     *
     * @param array<string, mixed> $values Submitted values
     *
     * @return array<string, mixed>
     */
    private function completeValues(array $values): array
    {
        $complete = [];

        foreach ($this->getFieldsNames() as $fieldname) {
            if (isset($values[$fieldname])) {
                $value = is_string($values[$fieldname]) ? trim($values[$fieldname]) : $values[$fieldname];
            } else {
                $value = "";
            }

            $complete[$fieldname] = $value;
        }

        return $complete;
    }

    /**
     * Run every relation between preferences, in order
     *
     * These cannot be expressed on a single field: they need the whole set.
     * Order matters, errors are reported in the order rules run here.
     *
     * @param array<string, mixed> $values        Submitted values
     * @param array<string, mixed> $insert_values Complete set, altered in place
     */
    private function checkRelations(array $values, array &$insert_values): void
    {
        $this->checkMailRelations($insert_values);
        $this->checkMembershipDates($insert_values);
        $this->checkOfferedMonths($insert_values);
        $this->checkRequiredValues($values);
        $this->checkPasswordConfirmation($values, $insert_values);
        $this->checkStaffMemberSource(
            insert_values: $insert_values,
            source_field: 'pref_postal_address',
            member_field: 'pref_postal_staff_member',
            from_prefs: self::POSTAL_ADDRESS_FROM_PREFS,
            from_staff: [self::POSTAL_ADDRESS_FROM_STAFF],
            error: _T("You have to select a staff member to retrieve its address")
        );
        $this->checkStaffMemberSource(
            insert_values: $insert_values,
            source_field: 'pref_org_phone',
            member_field: 'pref_org_phone_staff_member',
            from_prefs: self::PHONE_NUMBER_FROM_PREFS,
            from_staff: [self::PHONE_NUMBER_FROM_STAFF, self::PHONE_NUMBER_MOBILE_FROM_STAFF],
            error: _T("You have to select a staff member to retrieve its phone number")
        );
    }

    /**
     * Check what sending emails requires, according to the chosen method
     *
     * @param array<string, mixed> $insert_values Complete set of values
     */
    private function checkMailRelations(array $insert_values): void
    {
        if (
            Galette::isDemo()
            || !isset($insert_values['pref_mail_method'])
            || $insert_values['pref_mail_method'] <= GaletteMail::METHOD_DISABLED
        ) {
            return;
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

        if (
            $insert_values['pref_mail_method'] == GaletteMail::METHOD_SMTP
            && (!isset($insert_values['pref_mail_smtp_host']) || $insert_values['pref_mail_smtp_host'] == '')
        ) {
            $this->errors[] = _T("- You must indicate the SMTP server you want to use!");
        }

        $needs_credentials = $insert_values['pref_mail_method'] == GaletteMail::METHOD_GMAIL
            || ($insert_values['pref_mail_method'] == GaletteMail::METHOD_SMTP
            && $insert_values['pref_mail_smtp_auth']);

        if (!$needs_credentials) {
            return;
        }

        if (
            !isset($insert_values['pref_mail_smtp_user'])
            || trim((string)$insert_values['pref_mail_smtp_user']) == ''
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

    /**
     * A membership either extends for a duration or starts on a fixed date
     *
     * @param array<string, mixed> $insert_values Complete set of values
     */
    private function checkMembershipDates(array $insert_values): void
    {
        $has_beginning = isset($insert_values['pref_beg_membership'])
            && $insert_values['pref_beg_membership'] != '';
        $has_extension = isset($insert_values['pref_membership_ext'])
            && $insert_values['pref_membership_ext'] != '';

        if (!$has_beginning && !$has_extension) {
            $this->errors[] = _T("- You must indicate a membership extension or a beginning of membership.");
        } elseif ($has_beginning && $has_extension) {
            $this->errors[] = _T("- Default membership extension and beginning of membership are mutually exclusive.");
        }
    }

    /**
     * Offered months only make sense along a fixed beginning of membership
     *
     * @param array<string, mixed> $insert_values Complete set of values
     */
    private function checkOfferedMonths(array $insert_values): void
    {
        if (
            isset($insert_values['pref_membership_offermonths'])
            && (int)$insert_values['pref_membership_offermonths'] > 0
            && isset($insert_values['pref_membership_ext'])
            && $insert_values['pref_membership_ext'] != ''
        ) {
            $this->errors[] = _T("- Offering months is only compatible with beginning of membership.");
        }
    }

    /**
     * Check required preferences are all filled
     *
     * Reads the submitted payload rather than the completed set: a preference
     * missing from the payload is blank there, and would not be reported.
     *
     * @param array<string, mixed> $values Submitted values
     */
    private function checkRequiredValues(array $values): void
    {
        foreach (array_keys($this->required) as $val) {
            if (!isset($values[$val]) || is_string($values[$val]) && trim($values[$val]) == '') {
                $this->errors[] = sprintf(
                    //TRANS: parameter is a field name
                    _T('- Mandatory field %1$s empty.'),
                    $val
                );
            }
        }
    }

    /**
     * Check the superadmin password against its confirmation
     *
     * Hashing happens later, in __set().
     *
     * @param array<string, mixed> $values        Submitted values
     * @param array<string, mixed> $insert_values Complete set of values
     */
    private function checkPasswordConfirmation(array $values, array $insert_values): void
    {
        if (
            !Galette::isDemo()
            && isset($values['pref_admin_pass_check'])
            && strcmp((string)$insert_values['pref_admin_pass'], (string)$values['pref_admin_pass_check']) != 0
        ) {
            $this->errors[] = _T("Passwords mismatch");
        }
    }

    /**
     * Check a preference taken from a staff member designates one
     *
     * When the value is read from the preferences themselves, the staff member
     * is dropped from the set rather than blanked, so the stored one survives.
     *
     * @param array<string, mixed> $insert_values Complete set, altered in place
     * @param string               $source_field  Preference telling where to read from
     * @param string               $member_field  Preference holding the staff member
     * @param int                  $from_prefs    Value meaning "from the preferences"
     * @param array<int>           $from_staff    Values meaning "from a staff member"
     * @param string               $error         Message when no staff member is set
     */
    private function checkStaffMemberSource(
        array &$insert_values,
        string $source_field,
        string $member_field,
        int $from_prefs,
        array $from_staff,
        string $error
    ): void {
        if (!isset($insert_values[$source_field])) {
            return;
        }

        $value = $insert_values[$source_field];
        if ($value == $from_prefs) {
            unset($insert_values[$member_field]);
        } elseif (in_array($value, $from_staff)) {
            if (!isset($insert_values[$member_field]) || $insert_values[$member_field] < 1) {
                $this->errors[] = $error;
            }
        }
    }

    /**
     * Assign checked values, honouring the access level each one requires
     *
     * @param array<string, mixed> $insert_values Complete set of values
     * @param Login                $login         Logged in user
     */
    private function assignValues(array $insert_values, Login $login): void
    {
        foreach ($insert_values as $champ => $valeur) {
            //values Galette maintains itself are never taken from a payload
            if (PreferencesSchema::isReadOnly($champ)) {
                continue;
            }

            if (
                PreferencesSchema::getAcl($champ) === PreferencesSchema::ACL_SUPERADMIN
                && !$login->isSuperAdmin()
            ) {
                continue;
            }

            //an empty password must not overwrite the stored one
            if ($champ === 'pref_admin_pass' && empty($_POST['pref_admin_pass'] ?? '')) {
                continue;
            }

            $this->$champ = $valeur;
        }
    }

    /**
     * Store a single preference
     *
     * The change is merged into the current complete set and run through the
     * very same rules as a whole form submission, so a value breaking a
     * relation between preferences is refused here too.
     *
     * @param string $name  Preference name
     * @param mixed  $value Value to store
     * @param Login  $login Logged in user
     */
    public function setValue(string $name, mixed $value, Login $login): bool
    {
        $this->errors = [];

        if (!PreferencesSchema::has($name)) {
            $this->errors[] = str_replace(
                '%name',
                $name,
                _T("Unknown preference '%name'!")
            );
            return false;
        }

        if (PreferencesSchema::isReadOnly($name)) {
            $this->errors[] = str_replace(
                '%name',
                $name,
                _T("Preference '%name' is maintained by Galette and cannot be changed!")
            );
            return false;
        }

        if (
            PreferencesSchema::getAcl($name) === PreferencesSchema::ACL_SUPERADMIN
            && !$login->isSuperAdmin()
        ) {
            $this->errors[] = str_replace(
                '%name',
                $name,
                _T("You are not allowed to change preference '%name'!")
            );
            return false;
        }

        $this->getRequiredFields($login); //make sure required are all set

        //merge the change into what is currently stored
        $values = $this->prefs;
        $values[$name] = $value;

        $this->checkCssImpacted($values);

        $insert_values = $this->completeValues($values);
        $this->checkRelations($values, $insert_values);

        if (count($this->errors) > 0) {
            return false;
        }

        //a relation may have dropped the key as not applicable
        $checked = $insert_values[$name] ?? $values[$name];

        //validation and normalisation happen in __set(), which reports through
        //the error channel rather than a return value
        $this->$name = $checked;
        if ($this->getErrors() !== []) {
            return false;
        }

        return $this->persistValue($name, $this->prefs[$name]);
    }

    /**
     * Reset a single preference to its default
     *
     * @param string $name  Preference name
     * @param Login  $login Logged in user
     */
    public function resetValue(string $name, Login $login): bool
    {
        $this->errors = [];

        if (!PreferencesSchema::has($name)) {
            $this->errors[] = str_replace(
                '%name',
                $name,
                _T("Unknown preference '%name'!")
            );
            return false;
        }

        if (PreferencesSchema::isReadOnly($name)) {
            $this->errors[] = str_replace(
                '%name',
                $name,
                _T("Preference '%name' is maintained by Galette and cannot be changed!")
            );
            return false;
        }

        if (PreferencesSchema::isSensitive($name)) {
            //resetting a secret would set a publicly known value
            $this->errors[] = str_replace(
                '%name',
                $name,
                _T("Preference '%name' holds a secret and cannot be reset to its default!")
            );
            return false;
        }

        return $this->setValue($name, self::defaults()[$name], $login);
    }

    /**
     * Validate value of a field
     *
     * Constraints come from the schema. The few checks that cannot be
     * expressed declaratively are delegated to a dedicated method each.
     *
     * @param string $fieldname Field name
     * @param mixed  $value     Value to be set
     */
    public function validateValue(string $fieldname, mixed $value): mixed
    {
        $entry = PreferencesSchema::get($fieldname);
        if ($entry === null) {
            //unknown preference, it may come from a plugin: leave it untouched
            return $value;
        }

        return match ($entry['type']) {
            PreferencesSchema::TYPE_EMAIL,
            PreferencesSchema::TYPE_EMAILS => $this->validateEmails($fieldname, $value),
            PreferencesSchema::TYPE_INT => $this->validateNumber($fieldname, $entry, $value),
            PreferencesSchema::TYPE_COLOR => $this->validateColor($fieldname, $value),
            PreferencesSchema::TYPE_LOGIN => $this->validateAdminLogin($entry, $value),
            PreferencesSchema::TYPE_PASSWORD => $this->validateAdminPass($value),
            PreferencesSchema::TYPE_DATE_MD => $this->validateBegMembership($value),
            PreferencesSchema::TYPE_YEAR => $this->validateCardYear($value),
            PreferencesSchema::TYPE_URL => $this->validateWebUrl($value),
            PreferencesSchema::TYPE_HTML => $this->cleanHtmlValue((string)$value),
            default => $value,
        };
    }

    /**
     * Check email validity
     *
     * A TYPE_EMAILS field accepts a comma-separated list of valid addresses,
     * such as "mail@domain.com,other@mail.com".
     *
     * @param string $fieldname Field name
     * @param mixed  $value     Value to check
     */
    private function validateEmails(string $fieldname, mixed $value): mixed
    {
        $addresses = [];
        if (trim((string)$value) != '') {
            $addresses = PreferencesSchema::getType($fieldname) === PreferencesSchema::TYPE_EMAILS
                ? explode(',', (string)$value)
                : [$value];
        }

        foreach ($addresses as $address) {
            if (!GaletteMail::isValidEmail($address)) {
                $msg = str_replace('%s', $address, _T("Invalid E-Mail address: %s"));
                Analog::log($msg, Analog::WARNING);
                $this->errors[] = $msg;
            }
        }

        return $value;
    }

    /**
     * Check a number against the bounds declared in the schema
     *
     * Integers without any bound are not validated, only cast on read.
     *
     * @param string               $fieldname Field name
     * @param array<string, mixed> $entry     Schema entry
     * @param mixed                $value     Value to check
     */
    private function validateNumber(string $fieldname, array $entry, mixed $value): mixed
    {
        if (!isset($entry['min']) && !isset($entry['max'])) {
            return $value;
        }

        if (
            !is_numeric($value)
            || (isset($entry['min']) && $value < $entry['min'])
            || (isset($entry['max']) && $value > $entry['max'])
        ) {
            $this->errors[] = PreferencesSchema::getErrorMessage((string)$entry['error'], $fieldname);
        }

        return $value;
    }

    /**
     * Normalize a color
     *
     * An unparsable color is not an error: strip background colors fall back
     * to black, and the text color to white.
     *
     * @param string $fieldname Field name
     * @param mixed  $value     Value to normalize
     */
    private function validateColor(string $fieldname, mixed $value): string
    {
        $matches = [];
        if (!preg_match("/^(#)?([0-9A-F]{6})$/i", (string)$value, $matches)) {
            return $fieldname == 'pref_card_tcol' ? '#FFFFFF' : '#000000';
        }

        return '#' . $matches[2];
    }

    /**
     * Check superadmin login
     *
     * @param array<string, mixed> $entry Schema entry
     * @param mixed                $value Value to check
     */
    private function validateAdminLogin(array $entry, mixed $value): mixed
    {
        global $login;

        if (Galette::isDemo()) {
            Analog::log(
                'Trying to set superadmin login while in DEMO.',
                Analog::WARNING
            );
        } elseif (strlen((string)$value) < (int)$entry['minlength']) {
            $this->errors[] = PreferencesSchema::getErrorMessage((string)$entry['error']);
        } elseif ($login->loginExists($value)) {
            //check if login is already taken
            $this->errors[] = PreferencesSchema::getErrorMessage(PreferencesSchema::ERR_LOGIN_EXISTS);
        }

        return $value;
    }

    /**
     * Check superadmin password strength
     *
     * @param mixed $value Value to check
     */
    private function validateAdminPass(mixed $value): mixed
    {
        if (Galette::isDemo()) {
            Analog::log(
                'Trying to set superadmin pass while in DEMO.',
                Analog::WARNING
            );
            return $value;
        }

        $pwcheck = new \Galette\Util\Password($this);
        $pwcheck->addPersonalInformation([$this->pref_admin_login]);
        if (!$pwcheck->isValid($value)) {
            $this->errors = array_merge(
                $this->errors,
                $pwcheck->getErrors()
            );
        }

        return $value;
    }

    /**
     * Check beginning of membership, expressed as a day/month pair
     *
     * @param mixed $value Value to check
     */
    private function validateBegMembership(mixed $value): mixed
    {
        $beg_membership = explode("/", (string)$value);
        if (count($beg_membership) != 2) {
            $this->errors[] = PreferencesSchema::getErrorMessage(
                PreferencesSchema::ERR_BEG_MEMBERSHIP_FORMAT
            );
        } else {
            $now = getdate();
            if (!checkdate((int)$beg_membership[1], (int)$beg_membership[0], $now['year'])) {
                $this->errors[] = PreferencesSchema::getErrorMessage(
                    PreferencesSchema::ERR_BEG_MEMBERSHIP_DATE
                );
            }
        }

        return $value;
    }

    /**
     * Check year for members cards
     *
     * @param mixed $value Value to check
     */
    private function validateCardYear(mixed $value): mixed
    {
        if (
            $value !== 'DEADLINE'
            && !preg_match('/^(?:\d{4}|\d{2})(\D?)(?:\d{4}|\d{2})$/', (string)$value)
        ) {
            $this->errors[] = PreferencesSchema::getErrorMessage(PreferencesSchema::ERR_CARD_YEAR);
        }

        return $value;
    }

    /**
     * Check website URL
     *
     * @param mixed $value Value to check
     */
    private function validateWebUrl(mixed $value): mixed
    {
        if (!isValidWebUrl($value)) {
            $this->errors[] = PreferencesSchema::getErrorMessage(PreferencesSchema::ERR_WEBSITE);
        }

        return $value;
    }

    /**
     * Will store all preferences in the database
     *
     * @param bool $updating True if we're updating instance
     */
    public function store(bool $updating = false): bool
    {
        try {
            $this->zdb->beginTransaction();
            $update = $this->zdb->update(self::TABLE);
            $update->set(
                [
                    'val_pref'  => ':val_pref'
                ]
            )->where->equalTo('nom_pref', ':nom_pref');

            $stmt = $this->zdb->sql->prepareStatementForSqlObject($update);

            foreach (self::defaults() as $k => $v) {
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
                        $v = self::defaults()['pref_adhesion_form'];
                    }
                    $value = $v;
                }

                $stmt->execute(
                    [
                        'val_pref'  => $value,
                        'nom_pref'  => $k
                    ]
                );
            }
            $this->zdb->commit();
            Analog::log(
                'Preferences were successfully stored into database.',
                Analog::INFO
            );

            //prevent socials removal; see https://bugs.galette.eu/issues/1912
            if ($updating === false) {
                $this->storeSocials(null);
            }

            if ($updating === false) {
                //dynamic fields
                $this->dynamicsStore(true);
            }

            return true;
        } catch (Throwable $e) {
            if ($this->zdb->inTransaction()) {
                $this->zdb->rollback();
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
            $_complement = sprintf(
                //TRANS: first parameter is name, second is status
                _T('%1$s association\'s %2$s'),
                $this->prefs['pref_nom'],
                $adh->sstatus,
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
        if ($this->prefs['pref_org_phone'] == self::PHONE_NUMBER_FROM_PREFS) {
            $_phone = $this->prefs['pref_org_phone_number'];
        } else {
            //get selected staff phone number
            $adh = new Adherent($this->zdb, (int)$this->prefs['pref_org_phone_staff_member']);
            $_phone = $this->prefs['pref_org_phone'] == self::PHONE_NUMBER_MOBILE_FROM_STAFF ? $adh->gsm : $adh->phone;
        }

        return $_phone ?? '';
    }

    /**
     * Are public pages visible?
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

        if ($name === 'pref_card_vsize' && empty($this->prefs['pref_card_vsize'])) {
            return PdfMembersCards::HEIGHT;
        }

        if ($name === 'pref_card_hsize' && empty($this->prefs['pref_card_hsize'])) {
            return PdfMembersCards::WIDTH;
        }

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
                    $this->prefs[$name] = self::defaults()['pref_adhesion_form'];
                }
                $value = $this->prefs[$name];
                if ($this->zdb->isPostgres() && $value === 'f') {
                    $value = false;
                }

                if ($name === 'pref_email_newadh') {
                    $values = explode(',', $value);
                    $value = $values[0]; //take first as default
                }

                if ($value !== '') {
                    $value = match (PreferencesSchema::getType($name)) {
                        PreferencesSchema::TYPE_INT => (int)$value,
                        PreferencesSchema::TYPE_BOOL => (bool)$value,
                        default => $value,
                    };
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
     * Get a preference, letting the legacy constant win if it is defined
     *
     * Some settings used to live in behavior.inc.php only. They are now stored
     * like any other preference, but an instance still carrying the constant
     * must keep behaving as before, so the constant takes precedence and its
     * use is reported once.
     *
     * @param string $name Preference name
     */
    public function getConfigValue(string $name): mixed
    {
        $constant = PreferencesSchema::getConstant($name);

        if ($constant === null || !defined($constant)) {
            return $this->$name;
        }

        if (!isset($this->reported_overrides[$name])) {
            $this->reported_overrides[$name] = true;
            Analog::log(
                sprintf(
                    'Constant %1$s is defined and takes precedence over preference %2$s. '
                    . 'Remove it from behavior.inc.php to manage that setting from the '
                    . 'advanced configuration page.',
                    $constant,
                    $name
                ),
                Analog::WARNING
            );
        }

        return constant($constant);
    }

    /**
     * Get preferences defaults
     *
     * Derived from the schema on first access: a static property initializer
     * cannot call a method.
     *
     * @return array<string, bool|int|string>
     */
    private static function defaults(): array
    {
        if (self::$defaults === null) {
            self::$defaults = PreferencesSchema::getDefaults();
        }
        return self::$defaults;
    }

    /**
     * Get default preferences
     *
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        return self::defaults();
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param mixed  $value a relevant value for the property
     */
    public function __set(string $name, mixed $value): void
    {
        //does this pref exist?
        if (!array_key_exists($name, self::defaults())) {
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
            $value = password_hash((string)$value, PASSWORD_BCRYPT);
        }

        //okay, let's update value
        $this->prefs[$name] = $value;
    }

    /**
     * Get instance URL from configuration (if set) or guessed if not
     */
    public function getURL(): string
    {
        //GALETTE_URI, when defined, wins over the stored value
        $url = $this->getConfigValue('pref_galette_url');
        if (!empty($url)) {
            return (string)$url;
        }

        return $this->getDefaultURL();
    }

    /**
     * Get default URL (when neither preference nor constant is set)
     */
    public function getDefaultURL(): string
    {
        if (defined('GALETTE_CRON')) {
            //no incoming request to guess the instance URL from
            throw new \RuntimeException(
                _T('Please define constant "GALETTE_URI" with the path to your instance.')
            );
        }

        $scheme = (isset($_SERVER['HTTPS']) ? 'https' : 'http');
        if ($scheme === 'http' && isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $scheme = 'https';
        }
        return $scheme . '://' . $_SERVER['HTTP_HOST'];
    }

    /**
     * Get last telemetry date
     */
    public function getTelemetryDate(): string
    {
        $rawdate = $this->prefs['pref_telemetry_date'];
        if ($rawdate) {
            $date = new DateTime($rawdate);
            return $date->format(_T('Y-m-d H:i:s'));
        } else {
            return _T('Never');
        }
    }

    /**
     * Get last telemetry registration date
     */
    public function getRegistrationDate(): ?string
    {
        $rawdate = $this->prefs['pref_registration_date'];
        if ($rawdate) {
            $date = new DateTime($rawdate);
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
     * @param PHPMailer $mail    PHPMailer instance
     * @param bool      $as_text Whether to return signature as text or HTML (default)
     */
    public function getMailSignature(PHPMailer $mail, bool $as_text = false): string
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
        if ($as_text) {
            $signature = Text::convertHtmlToText($signature);
        }

        return "\r\n-- \r\n" . $signature;
    }

    /**
     * Get patterns for mail signature
     *
     * @param bool $legacy Whether to load legacy patterns
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
     */
    public function cleanHtmlValue(string $value): string
    {
        $config = \HTMLPurifier_Config::createDefault();
        $cache_dir = rtrim(GALETTE_CACHE_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'htmlpurifier';
        if (!file_exists($cache_dir)) {
            mkdir($cache_dir, 0o755, true);
        }
        $config->set('Cache.SerializerPath', $cache_dir);
        $config->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'mailto' => true,
            'ftp' => true,
        ]);
        $purifier = new \HTMLPurifier($config);

        // Remove all dangerous schemes
        $value = preg_replace(
            '/\b(?:javascript|data|vbscript):\s*/i',
            '',
            $value
        );
        return $purifier->purify($value);
    }

    /**
     * Update one preference field in database
     *
     * @param string $field Field name
     * @param mixed  $value Field value
     */
    protected function updateOneField(
        string $field,
        mixed $value,
    ): bool {
        if (!$this->persistValue($field, $value)) {
            return false;
        }

        $this->$field = $value;
        return true;
    }

    /**
     * Write one preference to database, without touching the loaded value
     *
     * Assigning goes through __set(), which validates and, for the superadmin
     * password, hashes. A caller holding an already normalised value must not
     * go through it again.
     *
     * @param string $field Field name
     * @param mixed  $value Field value
     */
    private function persistValue(string $field, mixed $value): bool
    {
        try {
            $update = $this->zdb->update(self::TABLE);
            $update
                ->set(['val_pref'  => $value])
                ->where->equalTo('nom_pref', $field);
            $this->zdb->execute($update);
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
            fn($field) => str_starts_with((string)$field, 'pref_cc_')
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
     * @param Logo|PrintLogo        $logo          Logo instance
     * @param UploadedFileInterface $uploaded_file Uploaded file
     *
     * @return array<string>|true
     */
    public function handleLogo(Logo|PrintLogo $logo, UploadedFileInterface $uploaded_file): array|bool
    {
        $this->errors = [];
        if ($uploaded_file->getError() === UPLOAD_ERR_OK) {
            $res = $logo->storeFile($uploaded_file);
            if ($res !== true) {
                $this->errors[] = $logo->getErrorMessage($res);
            }
        } elseif ($uploaded_file->getError() !== UPLOAD_ERR_NO_FILE) {
            $this->errors[] = $logo->getPhpErrorMessage(
                $uploaded_file->getError()
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
     * Handle files (dynamics files)
     *
     * @param array<UploadedFileInterface> $files Files sent
     *
     * @return array<string>|true
     */
    public function handleFiles(array $files): bool|array
    {
        $this->errors = [];

        $this->dynamicsFiles($files);

        if (count($this->errors) > 0) {
            Analog::log(
                'Some errors has been threw attempting to edit/store preferences files' . "\n"
                . print_r($this->errors, true),
                Analog::ERROR
            );
            return $this->errors;
        } else {
            return true;
        }
    }

    /**
     * Get ID
     */
    public function getID(): ?int
    {
        return 0;
    }
}
