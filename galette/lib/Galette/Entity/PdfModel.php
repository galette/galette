<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * PDF Model
 *
 * PHP version 5
 *
 * Copyright © 2013-2014 The Galette Team
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
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-02-19
 */

namespace Galette\Entity;

use Galette\Core;
use Galette\Core\Db;
use Galette\Repository\PdfModels;
use Analog\Analog;
use Laminas\Db\Sql\Expression;

/**
 * PDF Model
 *
 * @category  Entity
 * @name      PdfModel
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
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
    const ADHESION_FORM_MODEL = 4;

    private $zdb;

    private $id;
    private $name;
    private $type;
    private $header;
    private $footer;
    private $title;
    private $subtitle;
    private $body;
    private $styles;
    private $parent;

    private $patterns;
    private $replaces;

    /**
     * Main constructor
     *
     * @param Db          $zdb         Database instance
     * @param Preferences $preferences Galette preferences
     * @param int         $type        Model type
     * @param mixed       $args        Arguments
     */
    public function __construct(Db $zdb, $preferences, $type, $args = null)
    {
        global $container;
        $router = $container->get('router');

        if (!$zdb) {
            throw new \RuntimeException(
                get_class($this) . ' Database instance required!'
            );
        }

        $this->zdb = $zdb;
        $this->type = $type;

        if (is_int($args)) {
            $this->load($args, $preferences);
        } elseif ($args !== null && is_object($args)) {
            $this->loadFromRs($args, $preferences);
        }

        $this->patterns = array(
            'asso_name'          => '/{ASSO_NAME}/',
            'asso_slogan'        => '/{ASSO_SLOGAN}/',
            'asso_address'       => '/{ASSO_ADDRESS}/',
            'asso_address_multi' => '/{ASSO_ADDRESS_MULTI}/',
            'asso_website'       => '/{ASSO_WEBSITE}/',
            'asso_logo'          => '/{ASSO_LOGO}/',
            'date_now'           => '/{DATE_NOW}/'
        );

        $address = $preferences->getPostalAddress();
        $address_multi = preg_replace("/\n/", "<br>", $address);

        $website = '';
        if ($preferences->pref_website != '') {
            $website = '<a href="' . $preferences->pref_website . '">' .
                $preferences->pref_website . '</a>';
        }

        $logo = new Core\Logo();
        $logo_elt = '<img' .
            ' src="'    . $preferences->getURL() . $router->pathFor('logo')  . '"' .
            ' width="'  . $logo->getOptimalWidth()  . '"' .
            ' height="' . $logo->getOptimalHeight() . '"' .
            '/>';

        $this->replaces = array(
            'asso_name'          => $preferences->pref_nom,
            'asso_slogan'        => $preferences->pref_slogan,
            'asso_address'       => $address,
            'asso_address_multi' => $address_multi,
            'asso_website'       => $website,
            'asso_logo'          => $logo_elt,
            'date_now'           => date(_T('Y-m-d'))
        );
    }

    /**
     * Load a Model from its identifier
     *
     * @param int         $id          Identifier
     * @param Preferences $preferences Galette preferences
     * @param boolean     $init        Init data if required model is missing
     *
     * @return void
     */
    protected function load($id, $preferences, $init = true)
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->limit(1)
                ->where(self::PK . ' = ' . $id);

            $results = $this->zdb->execute($select);

            $count = $results->count();
            if ($count === 0) {
                if ($init === true) {
                    $models = new PdfModels($this->zdb, $preferences, $this->login);
                    $models->installInit();
                    $this->load($id, $preferences, false);
                } else {
                    throw new \RuntimeException('Model not found!');
                }
            } else {
                $this->loadFromRs($results->current(), $preferences);
            }
        } catch (\Exception $e) {
            Analog::log(
                'An error occurred loading model #' . $id . "Message:\n" .
                $e->getMessage(),
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
        $this->id = (int)$rs->$pk;

        $callback = function ($matches) {
            return _T($matches[1]);
        };
        $this->name = preg_replace_callback(
            '/_T\("([^\"]+)"\)/',
            $callback,
            $rs->model_name
        );

        $this->title = $rs->model_title;
        $this->subtitle = $rs->model_subtitle;
        $this->header = $rs->model_header;
        $this->footer = $rs->model_footer;
        $this->body = $rs->model_body;
        $this->styles .= $rs->model_styles;

        if ($this->id > self::MAIN_MODEL) {
            //FIXME: for now, parent will always be a PdfMain
            $this->parent = new PdfMain(
                $this->zdb,
                $preferences,
                (int)$rs->model_parent
            );
        }

        //let's check if some values should be retrieved from parent model
        /*if ( $this->id > self::MAIN_MODEL ) {
            //some infos are missing, load parent
            if ( trim($this->header) === ''
                || trim($this->footer) === ''
            ) {
                if ( trim($this->header) === '' ) {
                    $this->header = $parent->header;
                }
                if ( trim($this->header) === '' ) {
                    $this->footer = $parent->footer;
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
        $title = $this->title;
        if (trim($title === '')) {
            $title = new Expression('NULL');
        }

        $subtitle = $this->subtitle;
        if (trim($subtitle === '')) {
            $subtitle = new Expression('NULL');
        }

        $data = array(
            'model_header'      => $this->header,
            'model_footer'      => $this->footer,
            'model_type'        => $this->type,
            'model_title'       => $title,
            'model_subtitle'    => $subtitle,
            'model_body'        => $this->body,
            'model_styles'      => $this->styles
        );

        try {
            if ($this->id !== null) {
                $update = $this->zdb->update(self::TABLE);
                $update->set($data)->where(
                    self::PK . '=' . $this->id
                );
                $this->zdb->execute($update);
            } else {
                $data['model_name'] = $this->name;
                $insert = $this->zdb->insert(self::TABLE);
                $insert->values($data);
                $add = $this->zdb->execute($insert);
                if (!$add->count() > 0) {
                    Analog::log('Not stored!', Analog::ERROR);
                    return false;
                }
            }
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'An error occurred storing model: ' . $e->getMessage() .
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
        switch ($type) {
            case self::INVOICE_MODEL:
                $class = 'PdfInvoice';
                break;
            case self::RECEIPT_MODEL:
                $class = 'PdfReceipt';
                break;
            case self::ADHESION_FORM_MODEL:
                $class = 'PdfAdhesionFormModel';
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
    protected function checkChars($value, $chars, $field, $empty = false)
    {
        if ($value !== null && trim($value) !== '') {
            if (mb_strlen($value) > $chars) {
                throw new \LengthException(
                    str_replace(
                        array('%field', '%chars'),
                        array($field, $chars),
                        _T("%field should be less than %chars characters long.")
                    )
                );
            }
        } else {
            if ($empty === false) {
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
     * Extract patterns
     *
     * @return array
     */
    public function extractDynamicPatterns()
    {

        $patterns = array();
        $parts    = array('header', 'footer', 'title', 'subtitle', 'body');
        foreach ($parts as $part) {
            $content = $this->$part;

            $matches = array();
            preg_match_all(
                '/{((LABEL|INPUT)?_DYNFIELD_[0-9]+_ADH)}/',
                $content,
                $matches
            );
            $patterns = array_merge($patterns, $matches[1]);

            Analog::log("dynamic patterns found in $part: " . join(",", $matches[1]), Analog::DEBUG);
        }

        return $patterns;
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
        $this->patterns = array_merge(
            $this->patterns,
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
        $this->replaces = array_merge(
            $this->replaces,
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

        switch ($name) {
            case 'name':
            case 'id':
            case 'header':
            case 'footer':
            case 'body':
            case 'title':
            case 'subtitle':
            case 'type':
            case 'styles':
                return $this->$name;
                break;
            case 'hstyles':
                $value = null;

                //get header and footer from parent if not defined in current model
                if (
                    $this->id > self::MAIN_MODEL
                    && $this->parent !== null
                ) {
                    $value = $this->parent->styles;
                }

                $value .= $this->styles;
                return $value;
                break;
            case 'hheader':
            case 'hfooter':
            case 'htitle':
            case 'hsubtitle':
            case 'hbody':
                $pname = substr($name, 1);
                $prop_value = $this->$pname;

                //get header and footer from parent if not defined in current model
                if (
                    $this->id > self::MAIN_MODEL
                    && trim($prop_value) === ''
                    && $this->parent !== null
                    && ($pname === 'footer'
                    || $pname === 'header')
                ) {
                    $prop_value = $this->parent->$pname;
                }

                //handle translations
                $callback = function ($matches) {
                    return _T($matches[1]);
                };
                $value = preg_replace_callback(
                    '/_T\("([^\"]+)"\)/',
                    $callback,
                    $prop_value
                );

                //handle replacements
                $value = preg_replace(
                    $this->patterns,
                    $this->replaces,
                    $value
                );

                //handle translations with replacements
                $repl_callback = function ($matches) {
                    return str_replace(
                        $matches[1],
                        $matches[2],
                        $matches[3]
                    );
                };
                $value = preg_replace_callback(
                    '/str_replace\(\'([^,]+)\', ?\'([^,]+)\', ?\'(.*)\'\)/',
                    $repl_callback,
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
        switch ($name) {
            case 'type':
                if (
                    $value === self::MAIN_MODEL
                    || $value === self::INVOICE_MODEL
                    || $value === self::RECEIPT_MODEL
                    || $value === self::ADHESION_FORM_MODEL
                ) {
                    $this->$name = $value;
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
                    $this->$name = $value;
                } catch (\Exception $e) {
                    throw $e;
                }
                break;
            case 'title':
            case 'subtitle':
                if ($name == 'title') {
                    $field = _T("Title");
                } else {
                    $field = _T("Subtitle");
                }
                try {
                    $this->checkChars($value, 100, $field, true);
                    $this->$name = $value;
                } catch (\Exception $e) {
                    throw $e;
                }
                break;
            case 'header':
            case 'footer':
            case 'body':
                if ($value == null || trim($value) == '') {
                    if (get_class($this) === 'PdfMain' && $name !== 'body') {
                        throw new \UnexpectedValueException(
                            _T("header and footer should not be empty!")
                        );
                    } elseif (get_class($this) !== 'PdfMain' && $name === 'body') {
                        throw new \UnexpectedValueException(
                            _T("body should not be empty!")
                        );
                    }
                }

                if (function_exists('tidy_parse_string')) {
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
                    $this->$name = tidy_get_output($tidy);*/
                    $this->$name = $value;
                } else {
                    //if it is not... Well, let's serve the text as it.
                    $this->$name = $value;
                }
                break;
            case 'styles':
                $this->styles = $value;
                break;
            default:
                Analog::log(
                    'Unable to set PdfModel property ' . $name,
                    Analog::WARNING
                );
                break;
        }
    }
}
