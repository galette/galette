<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * PDF models
 *
 * PHP version 5
 *
 * Copyright © 2013 The Galette Team
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
 * @category  Repository
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-02-25
 */

namespace Galette\Repository;

use Analog\Analog;
use Galette\Entity\PdfModel;
use Galette\Entity\PdfMain;
use Galette\Entity\PdfInvoice;
use Galette\Entity\PdfReceipt;

/**
 * PDF models
 *
 * @category  Repository
 * @name      PdfModels
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-02-25
 */
class PdfModels extends Repository
{
    /**
     * Get models list
     *
     * @return PdfModel[]
     */
    public function getList()
    {
        try {
            $select = new \Zend_Db_Select($this->zdb->db);
            $select->from(
                array('a' => PREFIX_DB . PdfModel::TABLE)
            )->order(PdfModel::PK);

            $models = array();
            $res = $select->query()->fetchAll();
            foreach ( $res as $row ) {
                $class = PdfModel::getTypeClass($row->model_type);
                $models[] = new $class($this->zdb, $this->preferences, $row);
            }
            return $models;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list pdf models | ' . $e->getMessage(),
                Analog::WARNING
            );
            Analog::log(
                'Query was: ' . $select->__toString() . ' ' . $e->getTraceAsString(),
                Analog::ERROR
            );
        }
    }

    /**
     * Add default models in database
     *
     * @param array   $defaults    Fields definition defaults
     * @param boolean $check_first Check first if it seem initialized
     *
     * @return boolean
     */
    public function installInit($defaults, $check_first = true)
    {
        try {
            $ent = $this->entity;
            //first of all, let's check if data seem to have already
            //been initialized
            $proceed = false;
            if ( $check_first === true ) {
                $select = new \Zend_Db_Select($this->zdb->db);
                $select->from(
                    PREFIX_DB . $ent::TABLE,
                    'COUNT(' . $ent::PK . ') as counter'
                );
                $count = $select->query()->fetchObject()->counter;
                if ( $count == 0 ) {
                    //if we got no values in texts table, let's proceed
                    $proceed = true;
                } else {
                    if ( $count < count($defaults) ) {
                        return $this->_checkUpdate($defaults);
                    }
                    return false;
                }
            } else {
                $proceed = true;
            }

            if ( $proceed === true ) {
                $this->zdb->db->beginTransaction();

                //first, we drop all values
                $this->zdb->db->delete(PREFIX_DB . $ent::TABLE);

                $stmt = $this->zdb->db->prepare(
                    'INSERT INTO ' . PREFIX_DB . $ent::TABLE .
                    ' (model_id, model_name, model_title, model_type, ' .
                    'model_header, model_footer, model_body, model_styles, ' .
                    'model_parent) ' .
                    'VALUES(:model_id, :model_name, :model_title, :model_type, ' .
                    ':model_header, :model_footer, :model_body, :model_styles, ' .
                    ':model_parent)'
                );

                foreach ( $defaults as $d ) {
                    $stmt->execute($d);
                }

                $this->zdb->db->commit();
                return true;
            }
        } catch (\Exception $e) {
            $this->zdb->db->rollBack();
            return $e;
        }
    }

    /**
     * Checks for missing texts in the database
     *
     * @param array $defaults Fields definition defaults
     *
     * @return boolean
     */
    private function _checkUpdate($defaults)
    {
        try {
            $select = new \Zend_Db_Select($this->zdb->db);
            $ent = $this->entity;
            $select->from(
                PREFIX_DB . $ent::TABLE
            );
            $list = $select->query()->fetchAll();

            $missing = array();
            foreach ( $defaults as $default ) {
                $exists = false;
                foreach ( $list as $model ) {
                    if ( $model->model_id == $default['model_id'] ) {
                        $exists = true;
                        break;
                    }
                }

                if ( $exists === false ) {
                    //model does not exists in database, insert it.
                    $missing[] = $default;
                }
            }

            if ( count($missing) >0 ) {
                $this->zdb->db->beginTransaction();
                $stmt = $this->zdb->db->prepare(
                    'INSERT INTO ' . PREFIX_DB . $ent::TABLE .
                    ' (model_id, model_name, model_title, model_type, ' .
                    'model_header, model_footer, model_body, model_styles, ' .
                    'model_parent) ' .
                    'VALUES(:model_id, :model_name, :model_title, :model_type, ' .
                    ':model_header, :model_footer, :model_body, :model_styles, ' .
                    ':model_parent)'
                );

                foreach ( $missing as $d ) {
                    $stmt->execute($d);
                }

                $this->zdb->db->commit();
                Analog::log(
                    'Missing texts were successfully stored into database.',
                    Analog::INFO
                );
                return true;
            }
        } catch (\Exception $e) {
            $this->zdb->db->rollBack();
            throw $e;
        }
    }
}

