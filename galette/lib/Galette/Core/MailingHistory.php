<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Mailing features
 *
 * PHP version 5
 *
 * Copyright © 2009-2013 The Galette Team
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
 * @copyright 2011-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-08-27
 */

namespace Galette\Core;

use Analog\Analog as Analog;
use Galette\Entity\Adherent;

/**
 * Mailing features
 *
 * @category  Core
 * @name      MailingHistory
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2013 The Galette Team
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

        parent::__construct();

        if ( $mailing instanceof Mailing ) {
            $this->_mailing = $mailing;
        } else if ( $mailing !== null ) {
            Analog::log(
                '[' . __METHOD__ .
                '] Mailing should be either null or an instance of Mailing',
                Analog::ERROR
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
        global $zdb;

        if ($this->counter == null) {
            $c = $this->getCount();

            if ($c == 0) {
                Analog::log('No entry in history (yet?).', Analog::DEBUG);
                return;
            } else {
                $this->counter = (int)$c;
                $this->countPages();
            }
        }

        try {
            $select = new \Zend_Db_Select($zdb->db);
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
            $ret = $select->query(\Zend_Db::FETCH_ASSOC)->fetchAll();

            foreach ( $ret as &$r ) {
                if ( $r['mailing_sender'] !== null ) {
                    $r['mailing_sender_name'] = Adherent::getSName($r['mailing_sender']);
                }
                $body_resume = $r['mailing_body'];
                if ( strlen($body_resume) > 150 ) {
                    $body_resume = substr($body_resume, 0, 150);
                    $body_resume .= '[...]';
                }
                if (function_exists('tidy_parse_string') ) {
                    //if tidy extension is present, we use it to clean a bit
                    $tidy_config = array(
                        'clean'             => true,
                        'show-body-only'    => true,
                        'wrap' => 0,
                    );
                    $tidy = tidy_parse_string($body_resume, $tidy_config, 'UTF8');
                    $tidy->cleanRepair();
                    $r['mailing_body_resume'] = tidy_get_output($tidy);
                } else {
                    //if it is not... Well, let's serve the text as it.
                    $r['mailing_body_resume'] = $body_resume;
                }
            }
            return $ret;
        } catch (\Exception $e) {
            /** TODO */
            Analog::log(
                'Unable to get history. | ' . $e->getMessage(),
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
     * Load mailing from an existing one
     *
     * @param integaer       $id      Model identifier
     * @param GaletteMailing $mailing Mailing object
     *
     * @return boolean
     */
    public static function loadFrom($id, $mailing)
    {
        global $zdb;

        try {
            $select = new \Zend_Db_Select($zdb->db);
            $select->from(PREFIX_DB . self::TABLE)
                ->where('mailing_id = ?', $id);
            $res = $select->query()->fetch();
            $orig_recipients = unserialize($res->mailing_recipients);

            $_recipients = array();
            foreach ( $orig_recipients as $k=>$v ) {
                $m = new Adherent($k);
                $_recipients[] = $m;
            }
            $mailing->setRecipients($_recipients);
            $mailing->subject = $res->mailing_subject;
            $mailing->message = $res->mailing_body;
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'Unable to load mailing model #' . $id . ' | ' .
                $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Store a mailing in the history
     *
     * @param boolean $sent Defaults to false
     *
     * @return boolean
     */
    public function storeMailing($sent = false)
    {
        global $log, $login;

        if ( $this->_mailing instanceof Mailing ) {
            $this->_sender = $login->id;
            $this->_subject = $this->_mailing->subject;
            $this->_message = $this->_mailing->message;
            $this->_recipients = $this->_mailing->recipients;
            $this->_sent = $sent;
            $this->_date = date('Y-m-d H:i:s');
            $this->store();
        } else {
            Analog::log(
                '[' . __METHOD__ .
                '] Mailing should be either null or an instance of Mailing',
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Store in the database
     *
     * @return boolean
     */
    public function store()
    {
        global $zdb;

        try {
            $_recipients = array();
            if ( $this->_recipients != null ) {
                foreach ( $this->_recipients as $_r ) {
                    $_recipients[$_r->id] = $_r->sname . ' <' . $_r->email . '>';
                }
            }
            $values = array(
                'mailing_sender' => ($this->_sender === 0) ? new \Zend_Db_Expr('NULL') : $this->_sender,
                'mailing_subject' => $this->_subject,
                'mailing_body' => $this->_message,
                'mailing_date' => $this->_date,
                'mailing_recipients' => serialize($_recipients),
                'mailing_sent' => ($this->_sent) ? true : 'false'
            );

            $zdb->db->insert(PREFIX_DB . self::TABLE, $values);
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'An error occurend storing Mailing | ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Remove specified entries
     *
     * @param integer|array $ids Mailing history entries identifiers
     *
     * @return boolean
     */
    public function removeEntries($ids)
    {
        global $zdb, $hist;

        $list = array();
        if ( is_numeric($ids) ) {
            //we've got only one identifier
            $list[] = $ids;
        } else {
            $list = $ids;
        }

        if ( is_array($list) ) {
            try {
                $zdb->db->beginTransaction();

                //delete members
                $del = $zdb->db->delete(
                    PREFIX_DB . self::TABLE,
                    self::PK . ' IN (' . implode(',', $list) . ')'
                );

                //commit all changes
                $zdb->db->commit();

                //add an history entry
                $hist->add(
                    _T("Delete mailing entries")
                );

                return true;
            } catch (\Exception $e) {
                $zdb->db->rollBack();
                Analog::log(
                    'Unable to delete selected mailing history entries |' .
                    $e->getMessage(),
                    Analog::ERROR
                );
                return false;
            }
        } else {
            //not numeric and not an array: incorrect.
            Analog::log(
                'Asking to remove mailing entries, but without providing an array or a single numeric value.',
                Analog::WARNING
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
