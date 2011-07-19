<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Preferences handling
 *
 * PHP version 5
 *
 * Copyright © 2007-2011 The Galette Team
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
 * @category  Classes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-14
 */

/** @ignore */
require_once 'i18n.class.php';

/**
 * Preferences for galette
 *
 * @category  Classes
 * @name      Preferences
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-14
 */
class Preferences
{
    private $_prefs;
    private $_error;

    const TABLE = 'preferences';
    const PK = 'nom_pref';

    /** Postal adress will be the one given in the preferences */
    const POSTAL_ADRESS_FROM_PREFS = 0;
    /** Postal adress will be the one of the selected staff member */
    const POSTAL_ADRESS_FROM_STAFF = 1;

    private static $_fields = array(
        'nom_pref',
        'val_pref'
    );

    private static $_defaults = array(
        'pref_admin_login'    =>    'admin',
        'pref_admin_pass'    =>    'admin',
        'pref_nom'        =>    'Galette',
        'pref_slogan'        =>    '',
        'pref_adresse'        =>    '-',
        'pref_adresse2'        =>    '',
        'pref_cp'        =>    '',
        'pref_ville'        =>    '',
        'pref_pays'        =>    '',
        'pref_postal_adress'  => self::POSTAL_ADRESS_FROM_PREFS,
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
        'pref_mail_method'    =>    0,
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
        'pref_theme'        =>    'default'
    );

    /**
    * Default constructor
    *
    * @return void
    */
    public function __construct($load = true)
    {
        $error = null;
        if ( $load ) {
            $this->load();
            $this->_checkUpdate();
        }
    }

    /**
    * Check if all fields referenced in the default array does exists,
    * create them if not
    *
    * @return void
    */
    private function _checkUpdate()
    {
        global $log, $mdb;
        $proceed = false;
        $params = array();
        foreach ( self::$_defaults as $k=>$v ) {
            if ( !isset($this->_prefs[$k]) ) {
                $this->_prefs[$k] = $v;
                $log->log(
                    'The field `' . $k . '` does not exists, Galette will attempt to create it.',
                    PEAR_LOG_INFO
                );
                $proceed = true;
                $params[] = array(
                    'nom_pref'  => $k,
                    'val_pref'  => $v
                );
            }
        }
        if ( $proceed !== false ) {
            //store newly created values
            $stmt = $mdb->prepare(
                'INSERT INTO ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE) .
                ' (' . $mdb->quoteIdentifier('nom_pref') . ', ' .
                $mdb->quoteIdentifier('val_pref') . ') VALUES(:nom_pref, :val_pref)',
                array('text', 'text'),
                MDB2_PREPARE_MANIP
            );

            $mdb->getDb()->loadModule('Extended', null, false);
            $mdb->getDb()->extended->executeMultiple($stmt, $params);

            if (MDB2::isError($stmt)) {
                $this->_error = $stmt;
                $log->log(
                    'Unable to add missing preferences.' . $stmt->getMessage() .
                    '(' . $stmt->getDebugInfo() . ')',
                    PEAR_LOG_WARNING
                );
                return false;
            }

            $stmt->free();
            $log->log(
                'Missing preferences were successfully stored into database.',
                PEAR_LOG_INFO
            );
        }
    }

    /**
    * Load current preferences from database.
    *
    * @return void
    */
    public function load()
    {
        global $mdb, $log;

        $this->_prefs = array();

        $requete = 'SELECT * FROM ' . PREFIX_DB . self::TABLE;

        $result = $mdb->query($requete);
        if ( MDB2::isError($result) ) {
            return -1;
        }

        if ( $result->numRows() == 0 ) {
            $log->log(
                'Preferences cannot be loaded. Galette should not work without ' .
                'preferences. Exiting.',
                PEAR_LOG_EMERG
            );
            return(-10);
        } else {
            $r = $result->fetchAll();
            $array = array();
            foreach ( $r as $pref ) {
                $this->_prefs[$pref->nom_pref] = $pref->val_pref;
            }
        }
    }

    /**
    * Set default preferences at install time
    *
    * @param staing $lang      language selected at install screen
    * @param string $adm_login admin login entered at install time
    * @param string $adm_pass  admin password entered at install time
    *
    * @return boolean
    */
    public function installInit($lang, $adm_login, $adm_pass)
    {
        global $mdb, $log;

        //first, we drop all values
        $query = 'DELETE FROM '  . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE);
        $result = $mdb->execute($query);

        if (MDB2::isError($result)) {
            /** FIXME: we surely want to return sthing and print_r for debug. */
            print_r($result);
        }

        //we then replace default values with the ones user has selected
        $values = self::$_defaults;
        $values['pref_lang'] = $lang;
        $values['pref_admin_login'] = $adm_login;
        $values['pref_admin_pass'] = $adm_pass;
        $values['pref_card_year'] = date('Y');

        $stmt = $mdb->prepare(
            'INSERT INTO ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE) .
            ' (' . $mdb->quoteIdentifier('nom_pref') . ', ' .
            $mdb->quoteIdentifier('val_pref') . ') VALUES(:nom_pref, :val_pref)',
            array('text', 'text'),
            MDB2_PREPARE_MANIP
        );

        $params = array();
        foreach ( $values as $k=>$v ) {
            $params[] = array(
                'nom_pref'    =>    $k,
                'val_pref'    =>    $v
            );
        }

        $mdb->getDb()->loadModule('Extended', null, false);
        $mdb->getDb()->extended->executeMultiple($stmt, $params);

        if (MDB2::isError($stmt)) {
            $this->_error = $stmt;
            $log->log(
                'Unable to initialize default preferences.' . $stmt->getMessage() .
                '(' . $stmt->getDebugInfo() . ')',
                PEAR_LOG_WARNING
            );
            return false;
        }

        $stmt->free();
        $log->log(
            'Default preferences were successfully stored into database.',
            PEAR_LOG_INFO
        );
        return true;
    }

    /**
    * Has an error occured ?
    *
    * @return boolean
    */
    public function inError()
    {
        if ( MDB2::isError($this->_error) ) {
            return true;
        } else {
            return false;
        }
    }

    /**
    * Get main MDB2 error message
    *
    * @return string MDB2::Error's message
    */
    public function getErrorMessage()
    {
        return $this->_error->getMessage();
    }

    /**
    * Get additionnal informations about the error
    *
    * @return string MDB2::Error's debug infos
    */
    public function getErrorDetails()
    {
        return $this->_error->getDebugInfo();
    }

    /**
    * Returns all preferences keys
    *
    * @return array
    */
    public function getFieldsNames()
    {
        return array_keys($this->_prefs);
    }

    /**
    * Will store all preferences in the database
    *
    * @return boolean
    */
    public function store()
    {
        global $mdb, $log;
        $stmt = $mdb->prepare(
            'UPDATE ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE) .
            ' SET ' . $mdb->quoteIdentifier('val_pref') . '=:value WHERE ' .
            $mdb->quoteIdentifier('nom_pref') . '=:name',
            array('text', 'text'),
            MDB2_PREPARE_MANIP
        );

        $params = array();
        foreach ( self::$_defaults as $k=>$v ) {
            $params[] = array(
                'value'    =>    $this->_prefs[$k],
                'name'    =>    $k
            );
        }

        $mdb->getDb()->loadModule('Extended', null, false);
        $mdb->getDb()->extended->executeMultiple($stmt, $params);

        if (MDB2::isError($stmt)) {
            $this->_error = $stmt;
            $log->log(
                'Unable to store preferences.' . $stmt->getMessage() .
                '(' . $stmt->getDebugInfo() . ')',
                PEAR_LOG_WARNING
            );
            return false;
        }

        $stmt->free();
        $log->log(
            'Preferences were successfully stored into database.',
            PEAR_LOG_INFO
        );
        return true;

    }

    /**
     * Returns postal adress
     *
     * @return string postal adress
     */
    public function getPostalAdress()
    {
        $regs = array(
          '/%name/',
          '/%complement/',
          '/%adress/',
          '/%zip/',
          '/%town/',
          '/%country/',
        );

        $replacements = null;

        if ( $this->_prefs['pref_postal_adress'] == self::POSTAL_ADRESS_FROM_PREFS) {
            $_adress = $this->_prefs['pref_adresse'];
            if ( $this->_prefs['pref_adresse2'] && $this->_prefs['pref_adresse2'] != '' ) {
                $_adress .= "\n" . $this->_prefs['pref_adresse2'];
            }
            $replacements = array(
                $this->_prefs['pref_nom'],
                '',
                $_adress,
                $this->_prefs['pref_cp'],
                $this->_prefs['pref_ville'],
                $this->_prefs['pref_pays']
            );
        } else {
            //get selected staff member adress
            $adh = new Adherent((int)$this->_prefs['pref_postal_staff_member']);
            $_complement = preg_replace(
                array('/%name/', '/%status/'),
                array($this->_prefs['pref_nom'], $adh->sstatus),
                _T("%name association's %status")
            );
            $_adress = $adh->adress;
            if ( $adh->adress_continuation && $adh->adress_continuation != '' ) {
                $_adress .= "\n" . $adh->adress_continuation;
            }
            $replacements = array(
                $adh->sfullname,
                $_complement,
                $_adress,
                $adh->zipcode,
                $adh->town,
                $adh->country
            );
        }

        /*FIXME: i18n fails :/ */
        /*$r = preg_replace(
            $regs,
            $replacements,
            _T("%name\n%complement\n%adress\n%zip %town - %country")
        );*/
        $r = preg_replace(
            $regs,
            $replacements,
            "%name\n%complement\n%adress\n%zip %town - %country"
        );
        return $r;

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
        global $log;
        $forbidden = array('logged', 'admin', 'active', 'defaults');

        if ( !in_array($name, $forbidden) && isset($this->_prefs[$name])) {
            return $this->_prefs[$name];
        } else {
            $log->log(
                'Preference `' . $name . '` is not set or is forbidden',
                PEAR_LOG_INFO
            );
            return false;
        }
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
        global $log;

        //does this pref exists ?
        if ( !array_key_exists($name, self::$_defaults) ) {
            $log->log(
                'Trying to set a preference value which does not seems to exist ('
                . $name . ')',
                PEAR_LOG_WARNING
            );
            return false;
        }

        //some values need to be changed (eg. md5 passwords)
        if ($name == 'pref_admin_pass') {
            $value = md5($value);
        }

        //okay, let's update value
        $this->_prefs[$name] = $value;
    }

}
?>
