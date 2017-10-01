<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Preferences handling
 *
 * PHP version 5
 *
 * Copyright © 2007-2014 The Galette Team
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
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-14
 */

namespace Galette\Core;

use Analog\Analog;
use Galette\Entity\Adherent;
use Galette\Core\Db;

/**
 * Preferences for galette
 *
 * @category  Core
 * @name      Preferences
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-14
 */
class Preferences
{
    private $zdb;
    private $prefs;

    const TABLE = 'preferences';
    const PK = 'nom_pref';

    /** Postal address will be the one given in the preferences */
    const POSTAL_ADDRESS_FROM_PREFS = 0;
    /** Postal address will be the one of the selected staff member */
    const POSTAL_ADDRESS_FROM_STAFF = 1;

    /** Public pages stuff */
    /** Public pages are publically visibles */
    const PUBLIC_PAGES_VISIBILITY_PUBLIC = 0;
    /** Public pages are visibles for up to date members only */
    const PUBLIC_PAGES_VISIBILITY_RESTRICTED = 1;
    /** Public pages are visibles for admin and staff members only */
    const PUBLIC_PAGES_VISIBILITY_PRIVATE = 2;

    private static $fields = array(
        'nom_pref',
        'val_pref'
    );

    private static $defaults = array(
        'pref_admin_login'    =>    'admin',
        'pref_admin_pass'    =>    'admin',
        'pref_nom'        =>    'Galette',
        'pref_slogan'        =>    '',
        'pref_adresse'        =>    '-',
        'pref_adresse2'        =>    '',
        'pref_cp'        =>    '',
        'pref_ville'        =>    '',
        'pref_pays'        =>    '',
        'pref_postal_adress'  => self::POSTAL_ADDRESS_FROM_PREFS,
        'pref_postal_staff_member' => '',
        'pref_lang'        =>    I18n::DEFAULT_LANG,
        'pref_numrows'        =>    30,
        'pref_log'        =>    2,
        /* Preferences for mails */
        'pref_email_nom'    =>    'Galette',
        'pref_email'        =>    'mail@domain.com',
        'pref_email_newadh'    =>    'mail@domain.com',
        'pref_bool_mailadh'    =>    false,
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
        /* Preferences for members cards */
        'pref_card_abrev'    =>    'GALETTE',
        'pref_card_strip'    =>    'Gestion d\'Adherents en Ligne Extrêmement Tarabiscotée',
        'pref_card_tcol'    =>    'FFFFFF',
        'pref_card_scol'    =>    '8C2453',
        'pref_card_bcol'    =>    '53248C',
        'pref_card_hcol'    =>    '248C53',
        'pref_bool_display_title'    =>    false,
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
        'pref_bool_selfsubscribe' => true,
        'pref_googleplus' => '',
        'pref_facebook' => '',
        'pref_twitter' => '',
        'pref_viadeo' => '',
        'pref_linkedin' => '',
        'pref_mail_sign' => "{NAME}\r\n\r\n{WEBSITE}\r\n{GOOGLEPLUS}\r\n{FACEBOOK}\r\n{TWITTER}\r\n{LINKEDIN}\r\n{VIADEO}",
        /* New contribution script */
        'pref_new_contrib_script' => '',
        'pref_bool_wrap_mails' => true,
        'pref_rss_url' => 'http://galette.eu/dc/index.php/feed/atom',
        'pref_show_id' => false,
        'pref_adhesion_form' => '\Galette\IO\PdfAdhesionForm',
        'pref_mail_allow_unsecure' => false,
        'pref_instance_uuid' => '',
        'pref_registration_uuid' => '',
        'pref_telemetry_date' => '',
        'pref_registration_date' => ''
    );

    /**
     * Default constructor
     *
     * @param Db      $zdb  Db instance
     * @param boolean $load Automatically load preferences on load
     *
     * @return void
     */
    public function __construct(Db $zdb, $load = true)
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
     * @return void
     */
    private function checkUpdate()
    {
        $proceed = false;
        $params = array();
        foreach (self::$defaults as $k => $v) {
            if (!isset($this->prefs[$k])) {
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
            } catch (\Exception $e) {
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
    }

    /**
     * Load current preferences from database.
     *
     * @return boolean
     */
    public function load()
    {
        $this->prefs = array();

        try {
            $result = $this->zdb->selectAll(self::TABLE);
            foreach ($result as $pref) {
                $this->prefs[$pref->nom_pref] = $pref->val_pref;
            }
            return true;
        } catch (\Exception $e) {
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
     * @param staing $lang      language selected at install screen
     * @param string $adm_login admin login entered at install time
     * @param string $adm_pass  admin password entered at install time
     *
     * @return boolean|Exception
     */
    public function installInit($lang, $adm_login, $adm_pass)
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
        } catch (\Exception $e) {
            Analog::log(
                'Unable to initialize default preferences.' . $e->getMessage(),
                Analog::WARNING
            );
            return $e;
        }
    }

    /**
     * Returns all preferences keys
     *
     * @return array
     */
    public function getFieldsNames()
    {
        return array_keys($this->prefs);
    }

    /**
     * Will store all preferences in the database
     *
     * @return boolean
     */
    public function store()
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

                $stmt->execute(
                    array(
                        'val_pref'  => $value,
                        'where1'    => $k
                    )
                );
            }
            $this->zdb->connection->commit();
            Analog::log(
                'Preferences were successfully stored into database.',
                Analog::INFO
            );
            return true;
        } catch (\Exception $e) {
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
    public function getPostalAddress()
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

        if ($this->prefs['pref_postal_adress'] == self::POSTAL_ADDRESS_FROM_PREFS) {
            $_address = $this->prefs['pref_adresse'];
            if ($this->prefs['pref_adresse2'] && $this->prefs['pref_adresse2'] != '') {
                $_address .= "\n" . $this->prefs['pref_adresse2'];
            }
            $replacements = array(
                '',
                '',
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
            if ($adh->address_continuation && $adh->address_continuation != '') {
                $_address .= "\n" . $adh->address_continuation;
            }
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
     * Are public pages visibles?
     *
     * @param Authentication $login Authenticaqtion instance
     *
     * @return boolean
     */
    public function showPublicPages(Authentication $login)
    {
        if ($this->prefs['pref_bool_publicpages']) {
            //if public pages are actives, let's check if we
            //display them for curent call
            switch ($this->prefs['pref_publicpages_visibility']) {
                case self::PUBLIC_PAGES_VISIBILITY_PUBLIC:
                    //pages are publically visibles
                    return true;
                    break;
                case self::PUBLIC_PAGES_VISIBILITY_RESTRICTED:
                    //pages should be displayed only for up to date members
                    if ($login->isUp2Date()
                        || $login->isAdmin()
                        || $login->isStaff()
                    ) {
                        return true;
                    } else {
                        return false;
                    }
                    break;
                case self::PUBLIC_PAGES_VISIBILITY_PRIVATE:
                    //pages should be displayed only for staff and admins
                    if ($login->isAdmin() || $login->isStaff()) {
                        return true;
                    } else {
                        return false;
                    }
                    break;
                default:
                    //should never be there
                    return false;
                    break;
            }
        } else {
            return false;
        }
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrive
     *
     * @return false|object the called property
     */
    public function __get($name)
    {
        $forbidden = array('logged', 'admin', 'active', 'defaults');

        if (!in_array($name, $forbidden) && isset($this->prefs[$name])) {
            if (GALETTE_MODE === 'DEMO'
                && $name == 'pref_mail_method'
            ) {
                return GaletteMail::METHOD_DISABLED;
            } else {
                if ($name == 'pref_adhesion_form' && $this->prefs[$name] == '') {
                    $this->prefs[$name] = self::$defaults['pref_adhesion_form'];
                }
                $value = $this->prefs[$name];
                if (TYPE_DB === \Galette\Core\Db::PGSQL) {
                    if ($value === 'f') {
                        $value = false;
                    }
                }
                return $value;
            }
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
     * @return array
     */
    public function getDefaults()
    {
        return self::$defaults;
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param object $value a relevant value for the property
     *
     * @return void
     */
    public function __set($name, $value)
    {
        //does this pref exists ?
        if (!array_key_exists($name, self::$defaults)) {
            Analog::log(
                'Trying to set a preference value which does not seem to exist ('
                . $name . ')',
                Analog::WARNING
            );
            return false;
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
    public function getURL()
    {
        $url = null;
        if (isset($this->prefs['pref_galette_url']) && !empty($this->prefs['pref_galette_url'])) {
            $url = $this->prefs['url'];
        } else {
            $url = $this->getDefaultURL();
        }
        return $url;
    }

    /**
     * Get default URL (when not setted by user in preferences)
     *
     * @return string
     */
    public function getDefaultURL()
    {
        $scheme = (isset($_SERVER['HTTPS']) ? 'https' : 'http');
        $uri = $scheme . '://' . $_SERVER['SERVER_NAME'];
        return $uri;
    }
}
