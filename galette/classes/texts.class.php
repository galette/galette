<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */


/**
 * Texts handling
 *
 * PHP version 5
 *
 * Copyright © 2007-2009 The Galette Team
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
 * @author    John Perr <johnperr@abul.org>
 * @author    Johan Cwiklinski <joahn@x-tnd.be>
 * @copyright 2007-2009 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Avaialble since 0.7dev - 2007-07-16
 */

/** TODO
* - all errors messages should be handled by pear::log
*/

require_once 'MDB2.php';

/**
 * Texts class for galette
 *
 * @category  Classes
 * @name      Texts
 * @package   Galette
 * @author    John Perr <johnperr@abul.org>
 * @author    Johan Cwiklinski <joahn@x-tnd.be>
 * @copyright 2007-2009 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Avaialble since 0.7dev - 2007-07-16
 */
class Texts
{
    private $_all_texts;
    const TABLE = "texts";
    const DEFAULT_REF = 'sub';

    private static $_defaults = array(
        array(
            'tid' => 1,
            'tref' => 'sub',
            'tsubject' => 'Your identifiers',
            'tbody' => "Hello,\r\n\r\nYou've just been subscribed on the members management system of {NAME}.\r\n\r\nIt is now possible to follow in real time the state of your subscription and to update your preferences from the web interface.\r\n\r\nPlease login at this address:\r\n{LOGIN_URI}\r\n\r\nUsername: {LOGIN}\r\nPassword: {PASSWORD}\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)",
            'tlang'=> 'en_US',
            'tcomment'=>'New user registration'
        ),
        array(
            'tid' => 2,
            'tref' => 'sub',
            'tsubject' => 'Votre adhésion',
            'tbody' =>"Bonjour,\r\n\r\nVous venez d'adhérer à {NAME}.\r\n\r\nVous pouvez désormais accéder à vos coordonnées et souscriptions en vous connectant à l'adresse suivante :\r\n\r\n{LOGIN_URI} \r\n\r\nIdentifiant : {LOGIN}\r\nMot de passe : {PASSWORD}\r\n\r\nA bientôt!\r\n\r\n(Ce courriel est un envoi automatique)",
            'tlang'=>'fr_FR',
            'tcomment'=>'Nouvelle adhésion'
        ),

        array(
            'tid' => 4,
            'tref' => 'pwd',
            'tsubject' => 'Your identifiers',
            'tbody' =>"Hello,\r\n\r\nSomeone (probably you) asked to recover your password.\r\n\r\nPlease login at this address to set your new password :\r\n{CHG_PWD_URI}\r\n\r\nUsername: {LOGIN}\r\nTemporary password: {PASSWORD}\r\n\r\nSee you soon!\r\n\r\n(this mail was sent automatically)",
            'tlang'=> 'en_US',
            'tcomment'=>'Lost password email'
        ),
        array(
            'tid' => 5,
            'tref' => 'pwd',
            'tsubject' => 'Vos Identifiants',
            'tbody' =>"Bonjour,\r\n\r\nQuelqu'un (probablement vous) a demandé la récupération de votre mot de passe.\r\n\r\nConnectez vous à cette adresse pour valider le nouveau mot de passe :\r\n{CHG_PWD_URI}\r\n\r\nIdentifiant : {LOGIN}\r\nMot de passe Temporaire : {PASSWORD}\r\n\r\nA Bientôt!\r\n\r\n(Ce courriel est un envoi automatique)",
            'tlang'=>'fr_FR',
            'tcomment'=>'Récupération du mot de passe'
        ),

        array(
            'tid' => 7,
            'tref' => 'contrib',
            'tsubject' => 'Your contribution',
            'tbody' =>"Your contribution has succefully been taken into account by {NAME}.\r\n\r\nIt is valid until {DEADLINE}.\r\n\r\nYou can now login and browse or modify your personnal data using your galette identifiers.\r\n\r\n{COMMENT}",
            'tlang'=> 'en_US',
            'tcomment'=>'Receipt send for every new contribution'
        ),
        array(
            'tid' => 8,
            'tref' => 'contrib',
            'tsubject' => 'Votre cotisation',
            'tbody' => "Votre cotisation à {NAME} a été enregistrée et validée par l'association.\r\n\r\nElle est valable jusqu'au {DEADLINE}\r\n\r\nVous pouvez désormais accéder à vos données personnelles à l'aide de vos identifiants galette.\r\n\r\n{COMMENT}",
            'tlang'=>'fr_FR',
            'tcomment'=>'Accusé de réception de cotisation'
        ),

        array(
            'tid' => 10,
            'tref' => 'newadh',
            'tsubject' => 'New registration from  {SURNAME_ADH} {NAME_ADH}',
            'tbody' =>"{SURNAME_ADH} {NAME_ADH} has registred on line with login: {LOGIN}",
            'tlang'=> 'en_US',
            'tcomment'=>'New registration (sent to admin)'
        ),
        array(
            'tid' => 11,
            'tref' => 'newadh',
            'tsubject' => 'Nouvelle inscription de {SURNAME_ADH} {NAME_ADH}',
            'tbody' =>"{SURNAME_ADH} {NAME_ADH} s'est inscrit via l'interface web avec le login {LOGIN}",
            'tlang'=>'fr_FR',
            'tcomment'=>'Nouvelle inscription (envoyée a l\'admin)'
        ),

        array(
            'tid' => 13,
            'tref' => 'newcont',
            'tsubject' => 'New contribution for  {SURNAME_ADH} {NAME_ADH}',
            'tbody' =>"The contribution from {SURNAME_ADH} {NAME_ADH} has been registered (new deadline: {DEADLINE})\r\n\r\n{COMMENT}",
            'tlang'=> 'en_US',
            'tcomment'=>'New contribution (sent to admin)'
        ),
        array(
            'tid' => 14,
            'tref' => 'newcont',
            'tsubject' => 'Nouvelle contribution de {SURNAME_ADH} {NAME_ADH}',
            'tbody' =>"La contribution de {SURNAME_ADH} {NAME_ADH} a été enregistrée (nouvelle échéance: {DEADLINE})\r\n\r\n{COMMENT}",
            'tlang'=>'fr_FR',
            'tcomment'=>'Nouvelle contribution (envoyée a l\'admin)'
        )
    );

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
        global $mdb, $log;


        $requete = 'SELECT * FROM ' .
            $mdb->quoteIdentifier(PREFIX_DB . self::TABLE) .
            ' WHERE tref=' . $mdb->quote($ref) . ' AND tlang=' . $mdb->quote($lang);
        $result = $mdb->query($requete);

        if ( MDB2::isError($result) ) {
            $log->log(
                'Cannot get text `' . $ref . '` for lang `' . $lang . '` | ' .
                $result->getMessage() . '(' . $result->getDebugInfo() . ')',
                PEAR_LOG_ERR
            );
            return false;
        } elseif ( $result->numRows() > 0 ) {
            $this->_all_texts = $result->fetchRow();
        }
        return $this->_all_texts;
    }

    /**
    * Set text
    *
    * @param string $ref     Texte ref to locate
    * @param string $lang    Texte language to locate
    * @param string $subject Subject to set
    * @param string $body    Body text to set
    *
    * @return result mdb2 error or integer
    */
    public function setTexts($ref, $lang, $subject, $body)
    {
        global $mdb;
        //set texts
        $requete = 'UPDATE ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE);
        $requete .= ' SET ' . $mdb->quoteIdentifier('tsubject') . '=' .
            $mdb->quote($subject) . ', ' . $mdb->quoteIdentifier('tbody') .
            '=' . $mdb->quote($body);
        $requete .= ' WHERE ' . $mdb->quoteIdentifier('tref') . '=' .
            $mdb->quote($ref) . ' AND ' . $mdb->quoteIdentifier('tlang') .
            '=' . $mdb->quote($lang);

        $result = $mdb->execute($requete);

        /** FIXME: what if we send here a mdb2 error object? */
        return $result;
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
        global $mdb;
        $requete = 'SELECT ' . $mdb->quoteIdentifier('tref') . ', ' .
            $mdb->quoteIdentifier('tcomment') . ' FROM ' .
            $mdb->quoteIdentifier(PREFIX_DB . self::TABLE) . ' WHERE ' .
            $mdb->quoteIdentifier('tlang') . '=' . $mdb->quote($lang);
        $result = $mdb->query($requete);

        if ( MDB2::isError($result) ) {
            $log->log(
                'Cannot get refs for lang `' . $lang . '` | ' .
                $result->getMessage() . '(' . $result->getDebugInfo() . ')',
                PEAR_LOG_WARNING
            );
            return false;
        } elseif ( $result->numRows() > 0 ) {
            $refs = $result->fetchAll(MDB2_FETCHMODE_ASSOC);
        }
        return $refs;
    }

    /**
    * Initialize texts at install time
    *
    * @return boolean
    */
    public function installInit()
    {
        global $mdb, $log;

        //first, we drop all values
        $query = 'DELETE FROM '  . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE);
        $result = $mdb->execute($query);

        if ( MDB2::isError($result) ) {
            /** FIXME: we surely want to return sthing and print_r for debug. */
            print_r($result);
        }

        $stmt = $mdb->prepare(
            'INSERT INTO ' . $mdb->quoteIdentifier(PREFIX_DB . self::TABLE) .
            ' (' . $mdb->quoteIdentifier('tid') . ', ' .
            $mdb->quoteIdentifier('tref') . ', ' .
            $mdb->quoteIdentifier('tsubject') . ', ' .
            $mdb->quoteIdentifier('tbody') . ', ' .
            $mdb->quoteIdentifier('tlang') . ', ' .
            $mdb->quoteIdentifier('tcomment') .
            ') VALUES(:tid, :tref, :tsubject, :tbody, :tlang, :tcomment )',
            array('integer', 'text', 'text', 'text', 'text', 'text'),
            MDB2_PREPARE_MANIP
        );

        $mdb->getDb()->loadModule('Extended', null, false);
        $mdb->getDb()->extended->executeMultiple($stmt, self::$_defaults);

        if ( MDB2::isError($stmt) ) {
            $this->error = $stmt;
            $log->log(
                'Unable to initialize default texts.' . $stmt->getMessage() .
                '(' . $stmt->getDebugInfo() . ')',
                PEAR_LOG_WARNING
            );
            return false;
        }

        $stmt->free();
        $log->log(
            'Default texts were successfully stored into database.',
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
        if ( MDB2::isError($this->error) ) {
            return true;
        } else {
            return false;
        }
    }

    /**
    * Get main MDB2 error message
    *
    * @return MDB2::Error's message
    */
    public function getErrorMessage()
    {
        return $this->error->getMessage();
    }

    /**
    * Get additionnal informations about the error
    *
    * @return MDB2::Error's debug infos
    */
    public function getErrorDetails()
    {
        return $this->error->getDebugInfo();
    }
}
?>
