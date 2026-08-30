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
use Galette\Entity\Status;
use Galette\Enums\ContactSource;
use Galette\Enums\PasswordStrength;
use Galette\Core\Preferences\Assets;
use Galette\Core\Preferences\Fields;
use Galette\Core\Preferences\Identity;
use Galette\Core\Preferences\Relations;
use Galette\Core\Preferences\Signature;
use Galette\Core\Preferences\Storage;
use Galette\Enums\PublicPageVisibility;
use Galette\IO\PdfMembersCards;
use Galette\Repository\Members;

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

    public const string TABLE = Storage::TABLE;
    public const string PK = Storage::PK;

    /** @deprecated 1.3.0 Use ContactSource::Preferences */
    public const int POSTAL_ADDRESS_FROM_PREFS = ContactSource::Preferences->value;
    /** @deprecated 1.3.0 Use ContactSource::StaffMember */
    public const int POSTAL_ADDRESS_FROM_STAFF = ContactSource::StaffMember->value;

    /** @deprecated 1.3.0 Use ContactSource::Preferences */
    public const int PHONE_NUMBER_FROM_PREFS = ContactSource::Preferences->value;
    /** @deprecated 1.3.0 Use ContactSource::StaffMember */
    public const int PHONE_NUMBER_FROM_STAFF = ContactSource::StaffMember->value;
    /** @deprecated 1.3.0 Use ContactSource::StaffMemberMobile */
    public const int PHONE_NUMBER_MOBILE_FROM_STAFF = ContactSource::StaffMemberMobile->value;

    /** @deprecated 1.3.0 Use PublicPageVisibility::Everyone */
    public const int PUBLIC_PAGES_VISIBILITY_PUBLIC = PublicPageVisibility::Everyone->value;
    /** @deprecated 1.3.0 Use PublicPageVisibility::UpToDateMembers */
    public const int PUBLIC_PAGES_VISIBILITY_RESTRICTED = PublicPageVisibility::UpToDateMembers->value;
    /** @deprecated 1.3.0 Use PublicPageVisibility::StaffOnly */
    public const int PUBLIC_PAGES_VISIBILITY_PRIVATE = PublicPageVisibility::StaffOnly->value;
    /** @deprecated 1.3.0 Use PublicPageVisibility::Hidden */
    public const int PUBLIC_PAGES_VISIBILITY_HIDDEN = PublicPageVisibility::Hidden->value;
    /** @deprecated 1.3.0 Use PublicPageVisibility::Inherit */
    public const int PUBLIC_PAGES_VISIBILITY_INHERIT = PublicPageVisibility::Inherit->value;

    /** @deprecated 1.3.0 Use PasswordStrength::None */
    public const int PWD_NONE = PasswordStrength::None->value;
    /** @deprecated 1.3.0 Use PasswordStrength::Weak */
    public const int PWD_WEAK = PasswordStrength::Weak->value;
    /** @deprecated 1.3.0 Use PasswordStrength::Medium */
    public const int PWD_MEDIUM = PasswordStrength::Medium->value;
    /** @deprecated 1.3.0 Use PasswordStrength::Strong */
    public const int PWD_STRONG = PasswordStrength::Strong->value;
    /** @deprecated 1.3.0 Use PasswordStrength::VeryStrong */
    public const int PWD_VERY_STRONG = PasswordStrength::VeryStrong->value;

    /** Dark mode CSS file should be deleted from cache */
    private bool $delete_dark_css = false;
    /**
     * Preferences defaults, lazily derived from PreferencesSchema
     *
     * @var array<string, bool|int|string>|null
     */
    private static ?array $defaults = null;

    /** @var Social[] */
    private array $socials;

    private Assets $assets;
    private Fields $fields;
    private Identity $identity;
    private Relations $relations;
    private Signature $signature;
    private Storage $storage;

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
        $this->assets = new Assets();
        $this->fields = new Fields(assets: $this->assets);
        $this->identity = new Identity(zdb: $zdb);
        $this->relations = new Relations();
        $this->signature = new Signature(zdb: $zdb);
        $this->storage = new Storage(zdb: $zdb);
        $this->required = PreferencesSchema::getRequired();
        if ($load) {
            $this->load();
            $this->checkUpdate();
        }
    }

    /**
     * Take into account preferences declared after this instance was built
     *
     * Preferences are constructed before plugins are even listed, so a plugin
     * declaring its own arrives too late for the constructor. This picks the
     * new entries up and creates their missing rows.
     */
    public function refreshSchema(): void
    {
        $this->required = PreferencesSchema::getRequired();
        $this->checkUpdate();
    }

    /**
     * Forget the defaults derived from the schema
     *
     * Called by PreferencesSchema whenever a plugin registration changes what
     * the schema holds.
     */
    public static function invalidateDefaults(): void
    {
        self::$defaults = null;
    }

    /**
     * Get the value of a preference declared by a plugin
     *
     * Those are deliberately not annotated on this class: naming them here
     * would have core depend on its plugins. Static analysis therefore cannot
     * follow `$preferences->pref_myplugin_thing`, and this is the way in.
     *
     * @param string $name Preference name
     */
    public function getPluginValue(string $name): mixed
    {
        return $this->__get($name);
    }

    /**
     * Check if all fields referenced in the default array do exist,
     * create them if not
     */
    private function checkUpdate(): bool
    {
        $missing = [];
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
                $missing[$k] = $v;
            }
        }

        return $this->storage->insertMissing(values: $missing);
    }

    /**
     * Load current preferences from database.
     */
    public function load(): bool
    {
        $this->prefs = [];
        $values = $this->storage->readAll();

        if ($values === null) {
            return false;
        }

        $this->prefs = $values;
        $this->socials = Social::getListForMember(null);
        return true;
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
        //replace default values with the ones user has selected
        $values = self::defaults();
        $values['pref_lang'] = $lang;
        $values['pref_admin_login'] = $adm_login;
        $values['pref_admin_pass'] = $adm_pass;
        $values['pref_card_year'] = date('Y');

        return $this->storage->replaceAll(values: $values);
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
        $required = $this->getRequiredFields($login);

        $this->checkCssImpacted($values);

        $insert_values = $this->completeValues($values);

        //cleanup fields for demo
        if (Galette::isDemo()) {
            foreach (PreferencesSchema::getDemoLocked() as $locked) {
                unset($insert_values[$locked]);
            }
        }

        $this->errors = array_merge(
            $this->errors,
            $this->relations->check($values, $insert_values, $required)
        );

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
            if (PreferencesSchema::getOwner($fieldname) !== null) {
                //declared by a plugin: it is never part of the core form, and
                //taking it as missing would blank it on every save
                continue;
            }

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

        $required = $this->getRequiredFields($login);

        //merge the change into what is currently stored
        $values = $this->prefs;
        $values[$name] = $value;

        $this->checkCssImpacted($values);

        $insert_values = $this->completeValues($values);
        $this->errors = array_merge(
            $this->errors,
            $this->relations->check($values, $insert_values, $required)
        );

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
     * Errors are appended to the preferences error bag rather than returned:
     * the caller is __set(), which has no other channel.
     *
     * @param string     $fieldname Field name
     * @param mixed      $value     Value to be set
     * @param Login|null $login     Logged in user, to tell an already taken superadmin login
     */
    public function validateValue(string $fieldname, mixed $value, ?Login $login = null): mixed
    {
        $value = $this->fields->validate(
            fieldname: $fieldname,
            value: $value,
            preferences: $this,
            login: $login ?? $this->currentLogin()
        );

        $this->errors = array_merge($this->errors, $this->fields->getErrors());

        return $value;
    }

    /**
     * Who is connected, as far as the validators are concerned
     *
     * Nothing hands a Login down to __set(), and the superadmin login check
     * needs one; the global goes away as soon as every caller passes one.
     */
    private function currentLogin(): ?Login
    {
        $login = $GLOBALS['login'] ?? null;

        return $login instanceof Login ? $login : null;
    }

    /**
     * Will store all preferences in the database
     *
     * @param bool $updating True if we're updating instance
     */
    public function store(bool $updating = false): bool
    {
        $values = [];
        foreach (self::defaults() as $k => $v) {
            if (Galette::isDemo() && PreferencesSchema::isDemoLocked($k)) {
                continue;
            }

            //do not store pref_adhesion_form, it's designed to be overridden by plugin
            if ($k === 'pref_adhesion_form') {
                //cannot be empty, reset to default
                $values[$k] = trim($v) == '' ? self::defaults()['pref_adhesion_form'] : $v;
                continue;
            }

            $values[$k] = $this->prefs[$k];
        }

        if (!$this->storage->updateMany(values: $values)) {
            return false;
        }

        try {
            if ($updating === false) {
                //prevent socials removal; see https://bugs.galette.eu/issues/1912
                $this->storeSocials(null);
                //dynamic fields
                $this->dynamicsStore(true);
            }
        } catch (Throwable $e) {
            Analog::log(
                'Unable to store preferences related data | ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }

        return true;
    }

    /**
     * Returns postal address
     *
     * @return string postal address
     */
    public function getPostalAddress(): string
    {
        return $this->identity->getPostalAddress(prefs: $this->prefs);
    }

    /**
     * Returns phone number
     *
     * @return string phone number
     */
    public function getPhoneNumber(): string
    {
        return $this->identity->getPhoneNumber(prefs: $this->prefs);
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

        $visibility = PublicPageVisibility::tryFrom((int)$this->prefs[$right]);
        if ($visibility === null) {
            throw new \RuntimeException('Unknown public pages right: ' . $this->prefs[$right]);
        }

        return $visibility->isVisibleFor(
            login: $login,
            inherit: fn(): bool => $this->showPublicPage(
                login: $login,
                right: 'pref_publicpages_visibility_generic'
            )
        );
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
        self::$defaults ??= PreferencesSchema::getDefaults();
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

        if (Galette::isDemo() && PreferencesSchema::isDemoLocked($name)) {
            Analog::log(
                sprintf('Trying to set %s while in DEMO.', $name),
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
                _T('Please set your instance URL from the advanced configuration, or define the "GALETTE_URI" constant.')
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
        return $this->signature->getPatterns(
            core_types: $this->getCoreRegisteredTypes(),
            main_patterns: $legacy ? $this->getMainPatterns() : [],
            legacy: $legacy
        );
    }

    /**
     * Set emails replacements
     *
     * @return $this
     */
    public function setSocialReplacements(): self
    {
        $this->setReplacements(
            $this->signature->getSocialReplacements(
                core_types: $this->getCoreRegisteredTypes(),
                done_replacements: $this->getReplacements(),
                website: $this->pref_website
            )
        );

        return $this;
    }

    /**
     * Purify HTML value
     *
     * @param string $value Value to clean
     */
    public function cleanHtmlValue(string $value): string
    {
        return $this->assets->cleanHtmlValue(value: $value);
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
        return $this->storage->updateOne(name: $field, value: $value);
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
        $watched = ['pref_enable_custom_colors'];
        foreach (array_keys($this->prefs) as $field) {
            if (str_starts_with((string)$field, 'pref_cc_')) {
                $watched[] = $field;
            }
        }

        $current = [];
        foreach ($watched as $field) {
            //read through __get: a boolean stored as an empty string does not
            //compare to a submitted one the way the raw value would
            $current[$field] = $this->$field;
        }

        $this->delete_dark_css = $this->assets->isCssImpacted(
            submitted: $values,
            current: $current
        );
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

        $this->assets->resetDarkCss(flash: $flash);
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
        $this->errors = $this->assets->storeLogo(logo: $logo, uploaded_file: $uploaded_file);

        return $this->errors === [] ? true : $this->errors;
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
