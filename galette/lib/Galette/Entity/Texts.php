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
    private $_defaults;

    /**
     * Main constructor
     *
     * @param array       $texts_fields Text fields definition
     * @param Preferences $preferences  Galette's preferences
     * @param array       $replaces     Data that will be used as replacments
     */
    public function __construct($texts_fields, $preferences, $replaces = null)
    {
        $this->_defaults = $texts_fields;
        $this->_patterns = array(
            'asso_name'         => '/{ASSO_NAME}/',
            'asso_slogan'       => '/{ASSO_SLOGAN}/',
            'name_adh'          => '/{NAME_ADH}/',
            'lastname_adh'      => '/{LASTNAME_ADH}/',
            'firstname_adh'     => '/{FIRSTNAME_ADH}/',
            'login_adh'         => '/{LOGIN}/',
            'mail_adh'          => '/{MAIL_ADH}/',
            'login_uri'         => '/{LOGIN_URI}/',
            'password_adh'      => '/{PASSWORD}/',
            'change_pass_uri'   => '/{CHG_PWD_URI}/',
            'link_validity'     => '/{LINK_VALIDITY}/',
            'deadline'          => '/{DEADLINE}/',
            'contrib_info'      => '/{CONTRIB_INFO}/',
            'days_remaining'    => '/{DAYS_REMAINING}/',
            'days_expired'      => '/{DAYS_EXPIRED}/',
            'contrib_amount'    => '/{CONTRIB_AMOUNT}/',
            'contrib_type'      => '/{CONTRIB_TYPE}/'
        );

        $login_uri = null;
        if ( isset($_SERVER['SERVER_NAME']) && isset($_SERVER['REQUEST_URI']) ) {
            $login_uri = 'http://' . $_SERVER['SERVER_NAME'] .
                dirname($_SERVER['REQUEST_URI']);
        }
        $this->_replaces = array(
            'asso_name'         => $preferences->pref_nom,
            'asso_slogan'       => $preferences->pref_slogan,
            'name_adh'          => null,
            'lastname_adh'      => null,
            'firstname_adh'     => null,
            'login_adh'         => null,
            'mail_adh'          => null,
            'login_uri'         => $login_uri,
            'password_adh'      => null,
            'change_pass_uri'   => null,
            'link_validity'     => null,
            'deadline'          => null,
            'contrib_info'      => null,
            'days_remaining'    => null,
            'days_expired'      => null,
            'contrib_amount'    => null,
            'contrib_type'      => null
        );

        if ( $replaces != null && is_array($replaces) ) {
            $this->setReplaces($replaces);
        }
    }

    /**
     * Set replacements values
     *
     * @param array $replaces Replacements values
     *
     * @return void
     */
    public function setReplaces($replaces)
    {
        //let's populate replacement array with values provided
        foreach ( $replaces as $k=>$v ) {
            $this->_replaces[$k] = $v;
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
        global $zdb, $i18n;

        //check if language is set and exists
        $langs = $i18n->getList();
        $is_lang_ok = false;
        foreach ( $langs as $l ) {
            if ( $lang === $l->getID() ) {
                $is_lang_ok = true;
                break;
            }
        }

        if ( $is_lang_ok !== true ) {
            Analog::log(
                'Language ' . $lang .
                ' does not exists. Falling back to default Galette lang.',
                Analog::ERROR
            );
            $lang = $i18n->getID();
        }

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
                foreach ( $this->_defaults as $d) {
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
     * @deprecated Do not seem to be used as of 2013-07-16
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
                    if ( $count < count($this->_defaults) ) {
                        return $this->_checkUpdate();
                    }
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

                foreach ( $this->_defaults as $d ) {
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
     * Checks for missing texts in the database
     *
     * @return boolean
     */
    private function _checkUpdate()
    {
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(
                PREFIX_DB . self::TABLE
            );
            $list = $select->query()->fetchAll();

            $missing = array();
            foreach ( $this->_defaults as $default ) {
                $exists = false;
                foreach ( $list as $text ) {
                    if ( $text->tref == $default['tref']
                        && $text->tlang == $default['tlang']
                    ) {
                        $exists = true;
                        break;
                    }
                }

                if ( $exists === false ) {
                    //text does not exists in database, insert it.
                    $missing[] = $default;
                }
            }

            if ( count($missing) >0 ) {
                $stmt = $zdb->db->prepare(
                    'INSERT INTO ' . PREFIX_DB . self::TABLE .
                    ' (tid, tref, tsubject, tbody, tlang, tcomment) ' .
                    'VALUES(:tid, :tref, :tsubject, :tbody, :tlang, :tcomment )'
                );

                foreach ( $missing as $d ) {
                    $stmt->bindParam(':tid', $d['tid']);
                    $stmt->bindParam(':tref', $d['tref']);
                    $stmt->bindParam(':tsubject', $d['tsubject']);
                    $stmt->bindParam(':tbody', $d['tbody']);
                    $stmt->bindParam(':tlang', $d['tlang']);
                    $stmt->bindParam(':tcomment', $d['tcomment']);
                    $stmt->execute();
                }

                Analog::log(
                    'Missing texts were successfully stored into database.',
                    Analog::INFO
                );
                return true;
            }
        } catch (\Exception $e) {
            Analog::log(
                'An error occured checking missing texts.' . $e->getMessage(),
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
