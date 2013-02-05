<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Texts handling
 *
 * PHP version 5
 *
 * Copyright © 2007-2013 The Galette Team
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
 * @author    John Perr <johnperr@abul.org>
 * @author    Johan Cwiklinski <joahn@x-tnd.be>
 * @copyright 2007-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Avaialble since 0.7dev - 2007-07-16
 */

namespace Galette\Entity;

use Analog\Analog as Analog;

/**
 * Texts class for galette
 *
 * @category  Entity
 * @name      Texts
 * @package   Galette
 * @author    John Perr <johnperr@abul.org>
 * @author    Johan Cwiklinski <joahn@x-tnd.be>
 * @copyright 2007-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Avaialble since 0.7dev - 2007-07-16
 */
class Texts
{
    private $_all_texts;
    const TABLE = "texts";
    const PK = 'tid';
    const DEFAULT_REF = 'sub';

    private $_patterns;
    private $_replaces;

    private static $_defaults = array(
        array(
            'tid'       => 1,
            'tref'      => 'sub',
            'tsubject'  => '[{ASSO_NAME}] Your identifiers',
            'tbody'     => "Hello,\r\n\r\nYou've just been subscribed on the members management system of {ASSO_NAME}.\r\n\r\nIt is now possible to follow in real time the state of your subscription and to update your preferences from the web interface.\r\n\r\nPlease login at this address:\r\n{LOGIN_URI}\r\n\r\nUsername: {LOGIN}\r\nPassword: {PASSWORD}\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)",
            'tlang'     => 'en_US',
            'tcomment'  => 'New user registration'
        ),
        array(
            'tid'       => 2,
            'tref'      => 'sub',
            'tsubject'  => '[{ASSO_NAME}] Votre adhésion',
            'tbody'     => "Bonjour,\r\n\r\nVous venez d'adhérer à {ASSO_NAME}.\r\n\r\nVous pouvez désormais accéder à vos coordonnées et souscriptions en vous connectant à l'adresse suivante :\r\n{LOGIN_URI}\r\n\r\nIdentifiant : {LOGIN}\r\nMot de passe : {PASSWORD}\r\n\r\nA bientôt!\r\n\r\n(Ce courriel est un envoi automatique)",
            'tlang'     => 'fr_FR',
            'tcomment'  => 'Nouvelle adhésion'
        ),

        array(
            'tid'       => 4,
            'tref'      => 'pwd',
            'tsubject'  => '[{ASSO_NAME}] Your identifiers',
            'tbody'     => "Hello,\r\n\r\nSomeone (probably you) asked to recover your password.\r\n\r\nPlease login at this address to set your new password :\r\n{CHG_PWD_URI}\r\n\r\nUsername: {LOGIN}\r\nThe above link will be valid until {LINK_VALIDITY}.\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)",
            'tlang'     => 'en_US',
            'tcomment'  => 'Lost password email'
        ),
        array(
            'tid'       => 5,
            'tref'      => 'pwd',
            'tsubject'  => '[{ASSO_NAME}] Vos Identifiants',
            'tbody'     => "Bonjour,\r\n\r\nQuelqu'un (probablement vous) a demandé la récupération de votre mot de passe.\r\n\r\nConnectez vous à cette adresse pour valider le nouveau mot de passe :\r\n{CHG_PWD_URI}\r\n\r\nIdentifiant : {LOGIN}\r\nLe lien ci-dessus sera valide jusqu'au {LINK_VALIDITY}.\r\n\r\nA Bientôt!\r\n\r\n(Ce courriel est un envoi automatique)",
            'tlang'     => 'fr_FR',
            'tcomment'  => 'Récupération du mot de passe'
        ),

        array(
            'tid'       => 7,
            'tref'      => 'contrib',
            'tsubject'  => '[{ASSO_NAME}] Your contribution',
            'tbody'     => "Hello,\r\n\r\nYour contribution has successfully been taken into account by {ASSO_NAME}.\r\n\r\nIt is valid until {DEADLINE}.\r\n\r\nYou can now login and browse or modify your personnal data using your galette identifiers at this address:\r\n{LOGIN_URI}.\r\n\r\n{CONTRIB_INFO}\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)",
            'tlang'     => 'en_US',
            'tcomment'  => 'Receipt send for new contribution'
        ),
        array(
            'tid'       => 8,
            'tref'      => 'contrib',
            'tsubject'  => '[{ASSO_NAME}] Votre cotisation',
            'tbody'     => "Bonjour,\r\n\r\nVotre cotisation a été enregistrée et validée par l'association {ASSO_NAME}.\r\n\r\nElle est valable jusqu'au {DEADLINE}\r\n\r\nVous pouvez désormais accéder à vos données personnelles à l'aide de vos identifiants galette à l'adresse suivante :\r\n{LOGIN_URI}.\r\n\r\n{CONTRIB_INFO}\r\n\r\nA Bientôt!\r\n\r\n(Ce courriel est un envoi automatique)",
            'tlang'     => 'fr_FR',
            'tcomment'  => 'Accusé de réception de cotisation'
        ),

        array(
            'tid'       => 10,
            'tref'      => 'newadh',
            'tsubject'  => '[{ASSO_NAME}] New registration from {NAME_ADH}',
            'tbody'     => "Hello dear Administrator,\r\n\r\nA new member has been registered with the following informations:\r\n* Name: {NAME_ADH}\r\n* Login: {LOGIN}\r\n* E-mail: {MAIL_ADH}\r\n\r\nYours sincerly,\r\nGalette",
            'tlang'     => 'en_US',
            'tcomment'  => 'New user registration (sent to admin)'
        ),
        array(
            'tid'       => 11,
            'tref'      => 'newadh',
            'tsubject'  => '[{ASSO_NAME}] Nouvelle inscription de {NAME_ADH}',
            'tbody'     => "Bonjour cher Administrateur,\r\n\r\nUn nouveau membre a été enregistré avec les informations suivantes :\r\n* Nom : {NAME_ADH}\r\n* Login : {LOGIN}\r\n* Courriel : {MAIL_ADH}\r\n\r\nBien sincèrement,\r\nGalette",
            'tlang'     => 'fr_FR',
            'tcomment'  => 'Nouvelle adhésion (envoyée a l\'admin)'
        ),

        array(
            'tid'       => 13,
            'tref'      => 'newcont',
            'tsubject'  => '[{ASSO_NAME}] New contribution for {NAME_ADH}',
            'tbody'     => "Hello dear Administrator,\r\n\r\nA contribution from {NAME_ADH} has been registered (new deadline: {DEADLINE})\r\n{CONTRIB_INFO}\r\n\r\nYours sincerly,\r\nGalette",
            'tlang'     => 'en_US',
            'tcomment'  => 'New contribution (sent to admin)'
        ),
        array(
            'tid'       => 14,
            'tref'      => 'newcont',
            'tsubject'  => '[{ASSO_NAME}] Nouvelle contribution de {NAME_ADH}',
            'tbody'     => "Bonjour cher Administrateur,\r\n\r\nUne contribution de {NAME_ADH} a été enregistrée (nouvelle échéance: {DEADLINE})\r\n{CONTRIB_INFO}\r\n\r\nBien sincèrement,\r\nGalette",
            'tlang'     => 'fr_FR',
            'tcomment'  => 'Nouvelle contribution (envoyée a l\'admin)'
        ),

        array(
            'tid'       => 16,
            'tref'      => 'newselfadh',
            'tsubject'  => '[{ASSO_NAME}] New self registration from {NAME_ADH}',
            'tbody'     => "Hello dear Administrator,\r\n\r\nA new member has self registred on line with the following informations:\r\n* Name: {NAME_ADH}\r\n* Login: {LOGIN}\r\n* E-mail: {MAIL_ADH}\r\n\r\nYours sincerly,\r\nGalette",
            'tlang'     => 'en_US',
            'tcomment'  => 'New self registration (sent to admin)'
        ),
        array(
            'tid'       => 17,
            'tref'      => 'newselfadh',
            'tsubject'  => '[{ASSO_NAME}] Nouvelle auto inscription de {NAME_ADH}',
            'tbody'     => "Bonjour cher Administrateur,\r\n\r\nUn nouvel adhérent s'est auto inscrit en ligne avec les informations suivantes :\r\n* Nom : {NAME_ADH}\r\n* Login : {LOGIN}\r\n* Courriel : {MAIL_ADH}\r\n\r\nBien sincèrement,\r\nGalette",
            'tlang'     => 'fr_FR',
            'tcomment'  => 'Nouvelle auto-inscription (envoyée a l\'admin)'
        ),

        array(
            'tid'       => 19,
            'tref'      => 'accountedited',
            'tsubject'  => '[{ASSO_NAME}] Your account has been modified',
            'tbody'     => "Hello!\r\n\r\nYour account on {ASSO_NAME} (with the login '{LOGIN}') has been modified by an administrator or a staff member.\r\n\r\nYou can log into {LOGIN_URI} to review modifications and/or change it.\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)",
            'tlang'     => 'en_US',
            'tcomment'  => 'Informs user that his account has been modified'
        ),
        array(
            'tid'       => 20,
            'tref'      => 'accountedited',
            'tsubject'  => '[{ASSO_NAME}] Votre compte a été modifié',
            'tbody'     => "Bonjour !\r\n\r\nVotre compte chez {ASSO_NAME} (avec l'identifiant '{LOGIN}') a été modifié par un administrateur ou un membre du bureau.\r\n\r\nVous pouvez vous connecter à l'adresse {LOGIN_URI} pour vérifier ces informations ou les modifier.\r\n\r\nÀ bientôt !\r\n\r\n(ce courriel est un envoi automatique)",
            'tlang'     => 'fr_FR',
            'tcomment'  => 'Informe l\'utilisateur que son compte a été modifié'
        ),
    );

    /**
     * Main constructor
     *
     * @param Preferences $preferences Galette's preferences
     * @param array       $replaces    Data that will be used as replacments
     */
    public function __construct($preferences, $replaces = null)
    {
        $this->_patterns = array(
            'asso_name'         => '/{ASSO_NAME}/',
            'asso_slogan'       => '/{ASSO_SLOGAN}/',
            'name_adh'          => '/{NAME_ADH}/',
            'surname_adh'       => '/{SURNAME_ADH}/',
            'lastnam_adh'       => '/{LASTNAME_ADH}/',
            'login_adh'         => '/{LOGIN}/',
            'mail_adh'          => '/{MAIL_ADH}/',
            'login_uri'         => '/{LOGIN_URI}/',
            'password_adh'      => '/{PASSWORD}/',
            'change_pass_uri'   => '/{CHG_PWD_URI}/',
            'link_validity'     => '/{LINK_VALIDITY}/',
            'deadline'          => '/{DEADLINE}/',
            'contrib_info'      => '/{CONTRIB_INFO}/'
        );

        $this->_replaces = array(
            'asso_name'         => $preferences->pref_nom,
            'asso_slogan'       => $preferences->pref_slogan,
            'name_adh'          => null,
            'surname_adh'       => null,
            'lastnam_adh'       => null,
            'login_adh'         => null,
            'mail_adh'          => null,
            'login_uri'         => 'http://' . $_SERVER['SERVER_NAME'] .
                                    dirname($_SERVER['REQUEST_URI']),
            'password_adh'      => null,
            'change_pass_uri'   => null,
            'link_validity'     => null,
            'deadline'          => null,
            'contrib_info'      => null
        );

        if ( $replaces != null && is_array($replaces) ) {
            //let's populate replacement array with values provided
            foreach ( $replaces as $k=>$v ) {
                $this->_replaces[$k] = $v;
            }
        }
    }
    /**
    * Get specific text
    *
    * @param string $ref  Reference of text to get
    * @param string $lang Language texts to get
    *
    * @return array of all text fields for one language.
    */
    public function getTexts($ref,$lang)
    {
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . self::TABLE)
                ->where('tref = ?', $ref)
                ->where('tlang = ?', $lang);
            $result = $select->query()->fetch();
            if ( $result ) {
                $this->_all_texts = $result;
            } else {
                //hum... no result... That means text do not exist in the
                //database, let's add it
                $default = null;
                foreach ( self::$_defaults as $d) {
                    if ( $d['tref'] == $ref && $d['tlang'] == $lang ) {
                            $default = $d;
                            break;
                    }
                }
                if ( $default !== null ) {
                    $values = array(
                        'tid'       => $default['tid'],
                        'tref'      => $default['tref'],
                        'tsubject'  => $default['tsubject'],
                        'tbody'     => $default['tbody'],
                        'tlang'     => $default['tlang'],
                        'tcomment'  => $default['tcomment']
                    );

                    try {
                        $zdb->db->insert(PREFIX_DB . self::TABLE, $values);
                        return $this->getTexts($ref, $lang);
                    } catch( \Exception $e ) {
                        Analog::log(
                            'Unable to add missing requested text "' . $ref .
                            ' (' . $lang . ') | ' . $e->getMessage(),
                            Analog::WARNING
                        );
                    }
                } else {
                    Analog::log(
                        'Unable to find missing requested text "' . $ref .
                        ' (' . $lang . ')',
                        Analog::WARNING
                    );
                }
            }
            return $this->_all_texts;
        } catch (\Exception $e) {
            /** TODO */
            Analog::log(
                'Cannot get text `' . $ref . '` for lang `' . $lang . '` | ' .
                $e->getMessage(),
                Analog::WARNING
            );
            Analog::log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Set text
     *
     * @param string $ref     Texte ref to locate
     * @param string $lang    Texte language to locate
     * @param string $subject Subject to set
     * @param string $body    Body text to set
     *
     * @return integer|false affected rows (0 if record did not change)
     *                       or false on error
     */
    public function setTexts($ref, $lang, $subject, $body)
    {
        global $zdb;
        //set texts

        try {
            /** FIXME: quote? */
            $values = array(
                'tsubject' => $subject,
                'tbody'    => $body,
            );

            $where = array();
            $where[] = $zdb->db->quoteInto('tref = ?', $ref);
            $where[] = $zdb->db->quoteInto('tlang = ?', $lang);

            $edit = $zdb->db->update(
                PREFIX_DB . self::TABLE,
                $values,
                $where
            );
            return true;
        } catch (\Exception $e) {
            /** FIXME */
            Analog::log(
                'An error has occured while storing mail text. | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
    * Ref List
    *
    * @param string $lang Requested language
    *
    * @return array: list of references used for texts
    */
    public function getRefs($lang)
    {
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(
                PREFIX_DB . self::TABLE,
                array('tref', 'tcomment')
            )->where('tlang = ?', $lang);

            return $select->query(\Zend_Db::FETCH_ASSOC)->fetchAll();
        } catch (\Exception $e) {
            /** TODO */
            Analog::log(
                'Cannot get refs for lang `' . $lang . '` | ' .
                $e->getMessage(),
                Analog::WARNING
            );
            Analog::log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Retrieve fields from database
     *
     * @return array
     */
    public static function getDbFields()
    {
        global $zdb;
        return array_keys($zdb->db->describeTable(PREFIX_DB . self::TABLE));
    }

    /**
    * Initialize texts at install time
    *
    * @param boolean $check_first Check first if it seem initialized
    *
    * @return boolean|Exception false if no need to initialize, true if data
    *                           has been initialized, Exception if error
    */
    public function installInit($check_first = true)
    {
        global $zdb;

        try {
            //first of all, let's check if data seem to have already
            //been initialized
            $proceed = false;
            if ( $check_first === true ) {
                $select = new \Zend_Db_Select($zdb->db);
                $select->from(
                    PREFIX_DB . self::TABLE,
                    'COUNT(' . self::PK . ') as counter'
                );
                $count = $select->query()->fetchObject()->counter;
                if ( $count == 0 ) {
                    //if we got no values in texts table, let's proceed
                    $proceed = true;
                } else {
                    return false;
                }
            } else {
                $proceed = true;
            }

            if ( $proceed === true ) {
                //first, we drop all values
                $zdb->db->delete(PREFIX_DB . self::TABLE);

                $stmt = $zdb->db->prepare(
                    'INSERT INTO ' . PREFIX_DB . self::TABLE .
                    ' (tid, tref, tsubject, tbody, tlang, tcomment) ' .
                    'VALUES(:tid, :tref, :tsubject, :tbody, :tlang, :tcomment )'
                );

                foreach ( self::$_defaults as $d ) {
                    $stmt->bindParam(':tid', $d['tid']);
                    $stmt->bindParam(':tref', $d['tref']);
                    $stmt->bindParam(':tsubject', $d['tsubject']);
                    $stmt->bindParam(':tbody', $d['tbody']);
                    $stmt->bindParam(':tlang', $d['tlang']);
                    $stmt->bindParam(':tcomment', $d['tcomment']);
                    $stmt->execute();
                }

                Analog::log(
                    'Default texts were successfully stored into database.',
                    Analog::INFO
                );
                return true;
            }
        } catch (\Exception $e) {
            Analog::log(
                'Unable to initialize default texts.' . $e->getMessage(),
                Analog::WARNING
            );
            return $e;
        }
    }

    /**
     * Get the subject, with all replacements done
     *
     * @return string
     */
    public function getSubject()
    {
        return preg_replace(
            $this->_patterns,
            $this->_replaces,
            $this->_all_texts->tsubject
        );
    }

    /**
     * Get the body, with all replacements done
     *
     * @return string
     */
    public function getBody()
    {
        return preg_replace(
            $this->_patterns,
            $this->_replaces,
            $this->_all_texts->tbody
        );
    }
}
