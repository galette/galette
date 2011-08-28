<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Mailing features
 *
 * PHP version 5
 *
 * Copyright © 2009-2011 The Galette Team
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
 * @copyright 2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-08-27
 */

/** @ignore */
//require_once 'members.class.php';
require_once 'history.class.php';
require_once 'mailing.class.php';

/**
 * Mailing features
 *
 * @category  Classes
 * @name      MailingHistory
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-08-27
 */
class MailingHistory extends History
{
    const TABLE = 'mailing_history';
    const PK = 'mailing_id';

    private $_mailing = null;
    private $_id;
    private $_date;
    private $_subject;
    private $_message;
    private $_recipients;
    private $_sender;
    private $_sent = false;
    private $_no_longer_members;

    /**
    * Default constructor
    *
    * @param Mailing $mailing Mailing
    */
    public function __construct($mailing = null)
    {
        global $log;

        parent::__construct();

        if ( $mailing instanceof Mailing ) {
            $this->_mailing = $mailing;
        } else if ( $mailing !== null ) {
            $log->log(
                '[' . __METHOD__ .
                '] Mailing should be either null or an instance of Mailing',
                PEAR_LOG_ERR
            );
        }
        
    }

    /**
    * Get the entire history list
    *
    * @return array
    */
    public function getHistory()
    {
        return $this->getMailingHistory();
    }

    /**
    * Get the entire mailings list
    *
    * @return array
    */
    public function getMailingHistory()
    {
        global $zdb, $log;

        if ($this->counter == null) {
            $c = $this->getCount();

            if ($c == 0) {
                $log->log('No entry in history (yet?).', PEAR_LOG_DEBUG);
                return;
            } else {
                $this->counter = (int)$c;
                $this->countPages();
            }
        }

        try {
            $select = new Zend_Db_Select($zdb->db);
            $select->from(
                array('a' => $this->getTableName())
            )->joinLeft(
                array('b' => PREFIX_DB . Adherent::TABLE),
                'a.mailing_sender=b.' . Adherent::PK,
                array('b.nom_adh', 'b.prenom_adh')
            )->order($this->orderby . ' ' . $this->ordered);
            //add limits to retrieve only relavant rows
            $sql = $select->__toString();
            $this->setLimits($select);
            return $select->query(Zend_Db::FETCH_ASSOC)->fetchAll();
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                'Unable to get history. | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                PEAR_LOG_ERR
            );
            return false;
        }
    }

    /**
     * Store a mailing in the history
     *
     * @param Mailing $mailing
     *
     * @returns boolean
     */
    public function storeMailing()
    {
        global $log, $login;

        if ( $this->_mailing instanceof Mailing ) {
            $this->_sender = $login->id;
            $this->_subject = $this->_mailing->subject;
            $this->_message = $this->_mailing->message;
            $this->_recipients = $this->_mailing->recipients;
            $this->_sent = true;
            $this->_date = date('Y-m-d H:m:s');
            $this->store();
        } else {
            $log->log(
                '[' . __METHOD__ .
                '] Mailing should be either null or an instance of Mailing',
                PEAR_LOG_ERR
            );
            return false;
        }
    }

    public function store()
    {
        global $zdb, $log;

        try {
            $_recipients = array();
            foreach ( $this->_recipients as $_r ) {
                $_recipients[$_r->id] = $r->sname . ' <' . $_r->email . '>';
            }
            $values = array(
                'mailing_sender' => $this->_sender,
                'mailing_subject' => $this->_subject,
                'mailing_body' => $this->_message,
                'mailing_date' => $this->_date,
                'mailing_recipients' => serialize($_recipients),
                'mailing_sent' => $this->_sent
            );

            $zdb->db->insert(PREFIX_DB . self::TABLE, $values);
        } catch (Exception $e) {
            $log->log(
                'An error occurend storing Mailing | ' . $e->getMessage(),
                PEAR_LOG_ERR
            );
            return false;
        }
    }

    /**
     * Get table's name
     *
     * @return string
     */
    protected function getTableName()
    {
        return PREFIX_DB . self::TABLE;
    }

    /**
     * Get table's PK
     *
     * @return string
     */
    protected function getPk()
    {
        return self::PK;
    }

   /**
    * Returns the field we want to default set order to
    *
    * @return string field name
    */
    protected function getDefaultOrder()
    {
        return 'mailing_date';
    }

}
?>