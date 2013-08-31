<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * PDF Model
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
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-02-19
 */

namespace Galette\Entity;

use Analog\Analog as Analog;

/**
 * PDF Model
 *
 * @category  Entity
 * @name      PdfModel
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-02-19
 */

abstract class PdfModel
{
    const TABLE = 'pdfmodels';
    const PK = 'model_id';

    const MAIN_MODEL = 1;
    const INVOICE_MODEL = 2;
    const RECEIPT_MODEL = 3;

    private $_zdb;

    private $_id;
    private $_name;
    private $_type;
    private $_header;
    private $_footer;
    private $_title;
    private $_subtitle;
    private $_body;
    private $_styles;
    private $_parent;

    private $_patterns;
    private $_replaces;

    /**
     * Main constructor
     *
     * @param Db          $zdb         Database instance
     * @param Preferences $preferences Galette preferences
     * @param int         $type        Model type
     * @param mixed       $args        Arguments
     */
    public function __construct($zdb, $preferences, $type, $args = null)
    {
        if ( !$zdb ) {
            throw new \RuntimeException(
                get_class($this) . ' Database instance required!'
            );
        }

        $this->_zdb = $zdb;
        $this->_type = $type;

        if ( is_int($args) ) {
            $this->load($args, $preferences);
        } else if ( $args !== null && is_object($args) ) {
            $this->loadFromRs($args, $preferences);
        }

        $this->_patterns = array(
            'asso_name'         => '/{ASSO_NAME}/',
            'asso_slogan'       => '/{ASSO_SLOGAN}/',
            'asso_address'      => '/{ASSO_ADDRESS}/',
            'asso_website'      => '/{ASSO_WEBSITE}/'
        );

        $address = $preferences->getPostalAdress();

        $website = '';
        if ( $preferences->pref_website != '' ) {
            $website = '<a href="' . $preferences->pref_website . '">' .
                $preferences->pref_website . '</a>';
        }

        $this->_replaces = array(
            'asso_name'         => $preferences->pref_nom,
            'asso_slogan'       => $preferences->pref_slogan,
            'asso_address'      => $address,
            'asso_website'      => $website
        );
    }

    /**
     * Load a Model from its identifier
     *
     * @param int         $id          Identifier
     * @param Preferences $preferences Galette preferences
     *
     * @return void
     */
    protected function load($id, $preferences)
    {
        try {
            $select = new \Zend_Db_Select($this->_zdb->db);
            $select->limit(1)->from(PREFIX_DB . self::TABLE)
                ->where(self::PK . ' = ?', $id);

            $res = $select->query()->fetchAll();
            $this->loadFromRs($res[0], $preferences);
        } catch ( \Exception $e ) {
            Analog::log(
                'An error occured loading model #' . $id . "Message:\n" .
                $e->getMessage() . "\nQuery was:\n" . $select->__toString(),
                Analog::ERROR
            );
        }
    }

    /**
     * Load model from a db ResultSet
     *
     * @param ResultSet   $rs          ResultSet
     * @param Preferences $preferences Galette preferences
     *
     * @return void
     */
    protected function loadFromRs($rs, $preferences)
    {
        $pk = self::PK;
        $this->_id = (int)$rs->$pk;

        $callback = function ($matches) {
            return _T($matches[1]);
        };
        $this->_name = preg_replace_callback(
            '/_T\("([^\"]+)"\)/',
            $callback,
            $rs->model_name
        );

        $this->_title = $rs->model_title;
        $this->_subtitle = $rs->model_subtitle;
        $this->_header = $rs->model_header;
        $this->_footer = $rs->model_footer;
        $this->_body = $rs->model_body;
        $this->_styles .= $rs->model_styles;

        if ( $this->_id > self::MAIN_MODEL ) {
            //FIXME: for now, parent will always be a PdfMain
            $this->_parent = new PdfMain(
                $this->_zdb,
                $preferences,
                (int)$rs->model_parent
            );
        }

        //let's check if some values should be retrieved from parent model
        /*if ( $this->_id > self::MAIN_MODEL ) {
            //some infos are missing, load parent
            if ( trim($this->_header) === ''
                || trim($this->_footer) === ''
            ) {
                if ( trim($this->_header) === '' ) {
                    $this->_header = $parent->header;
                }
                if ( trim($this->_header) === '' ) {
                    $this->_footer = $parent->footer;
                }
            }
        }*/
    }

    /**
     * Store model in database
     *
     * @return boolean
     */
    public function store()
    {
        $title = $this->_title;
        if ( trim($title === '') ) {
            $title = new \Zend_Db_Expr('NULL');
        }

        $subtitle = $this->_subtitle;
        if ( trim($subtitle === '') ) {
            $subtitle = new \Zend_Db_Expr('NULL');
        }

        $data = array(
            'model_header'      => $this->_header,
            'model_footer'      => $this->_footer,
            'model_type'        => $this->_type,
            'model_title'       => $title,
            'model_subtitle'    => $subtitle,
            'model_body'        => $this->_body,
            'model_styles'      => $this->_styles
        );

        try {
            if ( $this->_id !== null ) {
                $up = $this->_zdb->db->update(
                    PREFIX_DB . self::TABLE,
                    $data,
                    self::PK . '=' . $this->_id
                );
            } else {
                $data['model_name'] = $this->_name;
                $add = $this->_zdb->db->insert(
                    PREFIX_DB . self::TABLE,
                    $data
                );
                if ( !$add > 0 ) {
                    Analog::log('Not stored!', Analog::ERROR);
                    return false;
                }
            }
            return true;
        } catch ( \Exception $e ) {
            Analog::log(
                'An error occured storing model: ' . $e->getMessage() .
                "\n" . print_r($data, true),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Get object class for specified type
     *
     * @param int $type Type
     *
     * @return Class
     */
    public static function getTypeClass($type)
    {
        $class = null;
        switch ( $type ) {
        case self::INVOICE_MODEL:
            $class = 'PdfInvoice';
            break;
        case self::RECEIPT_MODEL:
            $class = 'PdfReceipt';
            break;
        default:
            $class = 'PdfMain';
            break;
        }
        $class = 'Galette\\Entity\\' . $class;
        return $class;
    }

    /**
     * Check lenght
     *
     * @param string  $value The value
     * @param int     $chars Lenght
     * @param string  $field Field name
     * @param boolean $empty Can value be empty
     *
     * @return void
     */
    protected function checkChars($value, $chars, $field, $empty=false)
    {
        if ( $value !== null && trim($value) !== '' ) {
            if ( mb_strlen($value) > $chars ) {
                throw new \LengthException(
                    str_replace(
                        array('%field', '%chars'),
                        array($field, $chars),
                        _T("%field should be less than %chars characters long.")
                    )
                );
            }
        } else {
            if ( $empty === false ) {
                throw new \UnexpectedValueException(
                    str_replace(
                        '%field',
                        $field,
                        _T("%field should not be empty!")
                    )
                );
            }
        }
    }

    /**
     * Set patterns
     *
     * @param array $patterns Patterns to add
     *
     * @return void
     */
    public function setPatterns($patterns)
    {
        $this->_patterns = array_merge(
            $this->_patterns,
            $patterns
        );
    }

    /**
     * Set replacements
     *
     * @param array $replaces Replacements to add
     *
     * @return void
     */
    public function setReplacements($replaces)
    {
        $this->_replaces = array_merge(
            $this->_replaces,
            $replaces
        );
    }

    /**
     * Getter
     *
     * @param string $name Property name
     *
     * @return mixed
     */
    public function __get($name)
    {
        global $lang;

        switch ( $name ) {
        case 'name':
        case 'id':
        case 'header':
        case 'footer':
        case 'body':
        case 'title':
        case 'subtitle':
        case 'type':
        case 'styles':
            $rname = '_' . $name;
            return $this->$rname;
            break;
        case 'hstyles':
            $value = null;

            //get header and footer from parent if not defined in current model
            if ( $this->_id > self::MAIN_MODEL
                && $this->_parent !== null
            ) {
                $value = $this->_parent->styles;
            }

            $value .= $this->_styles;
            return $value;
            break;
        case 'hheader':
        case 'hfooter':
        case 'htitle':
        case 'hsubtitle':
        case 'hbody':
            $pname = substr($name, 1);
            $rname = '_' . $pname;
            $prop_value = $this->$rname;

            //get header and footer from parent if not defined in current model
            if ( $this->_id > self::MAIN_MODEL
                && trim($prop_value) === ''
                && $this->_parent !== null
                && ($pname === 'footer'
                || $pname === 'header')
            ) {
                $prop_value = $this->_parent->$pname;
            }

            //handle replacements
            $value = preg_replace(
                $this->_patterns,
                $this->_replaces,
                $prop_value
            );

            //handle translations
            $callback = function ($matches) {
                return _T($matches[1]);
            };
            $value = preg_replace_callback(
                '/_T\("([^\"]+)"\)/',
                $callback,
                $value
            );

            return $value;
            break;
        default:
            Analog::log(
                'Unable to get PdfModel property ' . $name,
                Analog::WARNING
            );
            break;
        }
    }

    /**
     * Setter
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     *
     * @return void
     */
    public function __set($name, $value)
    {
        $rname = '_' . $name;
        switch ( $name ) {
        case 'type':
            if ( $value === self::MAIN_MODEL
                || $value === self::INVOICE_MODEL
                || $value === self::RECEIPT_MODEL
            ) {
                $this->$rname = $value;
            } else {
                throw new \UnexpectedValueException(
                    str_replace(
                        '%type',
                        $value,
                        _T("Unknown type %type!")
                    )
                );
            }
            break;
        case 'name':
            try {
                $this->checkChars($value, 50, _T("Name"));
                $this->$rname = $value;
            } catch ( \Exception $e) {
                throw $e;
            }
            break;
        case 'title':
        case 'subtitle':
            if ( $name == 'title' ) {
                $field = _T("Title");
            } else {
                $field = _T("Subtitle");
            }
            try {
                $this->checkChars($value, 100, $field, true);
                $this->$rname = $value;
            } catch ( \Exception $e ) {
                throw $e;
            }
            break;
        case 'header':
        case 'footer':
        case 'body':
            if ( $value == null || trim($value) == '' ) {
                if ( get_class($this) === 'PdfMain' && $name !== 'body' ) {
                    throw new \UnexpectedValueException(
                        _T("header and footer should not be empty!")
                    );
                } elseif ( get_class($this) !== 'PdfMain' && $name === 'body' ) {
                    throw new \UnexpectedValueException(
                        _T("body should not be empty!")
                    );
                }
            }

            if (function_exists('tidy_parse_string') ) {
                //if tidy extension is present, we use it to clean a bit
                /*$tidy_config = array(
                    'clean'             => true,
                    'show-body-only'    => true,
                    'join-styles'       => false,
                    'join-classes'      => false,
                    'wrap' => 0,
                );
                $tidy = tidy_parse_string($value, $tidy_config, 'UTF8');
                $tidy->cleanRepair();
                $this->$rname = tidy_get_output($tidy);*/
                $this->$rname = $value;
            } else {
                //if it is not... Well, let's serve the text as it.
                $this->$rname = $value;
            }
            break;
        case 'styles':
            $this->_styles = $value;
            break;
        default:
            Analog::log(
                'Unable to set PdfModel property ' .$name,
                Analog::WARNING
            );
            break;
        }
    }
}
