<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * PDF Model
 *
 * PHP version 5
 *
 * Copyright © 2013-2021 The Galette Team
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
 * @copyright 2013-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-02-19
 */

namespace Galette\Entity;

use Throwable;
use Galette\Core\Db;
use Galette\Core\Preferences;
use Galette\Features\Replacements;
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
 * @copyright 2013-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-02-19
 *
 * @property integer $id
 * @property string $name
 * @property integer $type
 * @property string $header
 * @property string $footer
 * @property string $title
 * @property string $subtitle
 * @property string $body
 * @property string $styles
 * @property PdfMain $parent
 */

abstract class PdfModel
{
    use Replacements;

    public const TABLE = 'pdfmodels';
    public const PK = 'model_id';

    public const MAIN_MODEL = 1;
    public const INVOICE_MODEL = 2;
    public const RECEIPT_MODEL = 3;
    public const ADHESION_FORM_MODEL = 4;

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

    /**
     * Main constructor
     *
     * @param Db          $zdb         Database instance
     * @param Preferences $preferences Galette preferences
     * @param int         $type        Model type
     * @param mixed       $args        Arguments
     */
    public function __construct(Db $zdb, Preferences $preferences, $type, $args = null)
    {
        global $container, $login;
        $this->router = $container->get('router');
        $this->preferences = $preferences;
        $this
            ->setDb($zdb)
            ->setLogin($login);
        $this->type = $type;

        if (is_int($args)) {
            $this->load($args);
        } elseif ($args !== null && is_object($args)) {
            $this->loadFromRs($args);
        } else {
            $this->load($type);
        }

        $this->setPatterns($this->getMainPatterns());
        $this->setMain();
    }

    /**
     * Load a Model from its identifier
     *
     * @param int     $id   Identifier
     * @param boolean $init Init data if required model is missing
     *
     * @return void
     */
    protected function load($id, $init = true)
    {
        global $login;

        try {
            $select = $this->zdb->select(self::TABLE);
            $select->limit(1)
                ->where([self::PK => $id]);

            $results = $this->zdb->execute($select);

            $count = $results->count();
            if ($count === 0) {
                if ($init === true) {
                    $models = new PdfModels($this->zdb, $this->preferences, $login);
                    $models->installInit();
                    $this->load($id, false);
                } else {
                    throw new \RuntimeException('Model not found!');
                }
            } else {
                $this->loadFromRs($results->current());
            }
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred loading model #' . $id . "Message:\n" .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Load model from a db ResultSet
     *
     * @param ResultSet $rs ResultSet
     *
     * @return void
     */
    protected function loadFromRs($rs)
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
                $this->preferences,
                (int)$rs->model_parent
            );
        }
    }

    /**
     * Store model in database
     *
     * @return boolean
     */
    public function store()
    {
        $title = $this->title;
        if ($title === null || trim($title) === '') {
            $title = new Expression('NULL');
        }

        $subtitle = $this->subtitle;
        if ($subtitle === null || trim($subtitle) === '') {
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
                    [self::PK => $this->id]
                );
                $this->zdb->execute($update);
            } else {
                $data['model_name'] = $this->name;
                $insert = $this->zdb->insert(self::TABLE);
                $insert->values($data);
                $add = $this->zdb->execute($insert);
                if (!($add->count() > 0)) {
                    Analog::log('Not stored!', Analog::ERROR);
                    return false;
                }
            }
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred storing model: ' . $e->getMessage() .
                "\n" . print_r($data, true),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Get object class for specified type
     *
     * @param int $type Type
     *
     * @return string
     */
    public static function getTypeClass(int $type)
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
     * Check length
     *
     * @param string  $value The value
     * @param int     $chars Length
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
            case 'patterns':
            case 'replaces':
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
                $prop_value = $this->$pname ?? '';

                //get header and footer from parent if not defined in current model
                if (
                    $this->id > self::MAIN_MODEL
                    && $this->parent !== null
                    && ($pname === 'footer'
                    || $pname === 'header')
                    && trim($prop_value) === ''
                ) {
                    $prop_value = $this->parent->$pname;
                }

                $value = $this->proceedReplacements($prop_value);
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
                } catch (Throwable $e) {
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
                } catch (Throwable $e) {
                    throw $e;
                }
                break;
            case 'header':
            case 'footer':
            case 'body':
                if ($value === null || trim($value) === '') {
                    if ($name !== 'body' && get_class($this) === 'PdfMain') {
                        throw new \UnexpectedValueException(
                            _T("header and footer should not be empty!")
                        );
                    } elseif ($name === 'body' && get_class($this) !== 'PdfMain') {
                        throw new \UnexpectedValueException(
                            _T("body should not be empty!")
                        );
                    }
                }

                $this->$name = $value;
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
