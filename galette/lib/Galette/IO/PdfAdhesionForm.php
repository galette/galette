<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Adhesion form PDF
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
 * @category  IO
 * @package   Galette
 *
 * @author    Guillaume Rousse <guillomovitch@gmail.com>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-07-07
 */

namespace Galette\IO;

use Galette\Core\Db;
use Galette\Core\Preferences;
use Galette\Entity\Adherent;
use Galette\Entity\PdfModel;
use Galette\Entity\PdfAdhesionFormModel;
use Galette\IO\Pdf;
use Analog\Analog;

/**
 * Adhesion Form PDF
 *
 * @category  IO
 * @name      PDF
 * @package   Galette
 * @abstract  Class for expanding TCPDF.
 * @author    Guillaume Rousse <guillomovitch@gmail.com>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-07-07
 */

class PdfAdhesionForm extends Pdf
{
    protected $zdb;
    protected $adh;
    protected $prefs;
    protected $filename;
    private $path;

    /**
     * Main constructor
     *
     * @param Adherent    $adh   Adherent
     * @param Db          $zdb   Database instance
     * @param Preferences $prefs Preferences instance
     */
    public function __construct(Adherent $adh = null, Db $zdb, Preferences $prefs)
    {
        $this->zdb = $zdb;
        $this->adh = $adh;
        $this->prefs = $prefs;

        $model = $this->getModel();
        parent::__construct($prefs, $model);

        $this->filename = $adh ?
            __("adherent_form") . '.' . $adh->id . '.pdf' : __("adherent_form") . '.pdf';

        $this->Open();

        $this->AddPage();
        if ($model !== null) {
            $this->PageHeader();
            $this->PageBody();
        }
    }

    /**
     * Get model
     *
     * @return PdfModel
     */
    protected function getModel()
    {
        $model = new PdfAdhesionFormModel($this->zdb, $this->prefs);
        $model->setMember($this->adh);

        return $model;
    }

    /**
     * Store PDF
     *
     * @param string $path Path
     *
     * @return boolean
     */
    public function store($path)
    {
        if (file_exists($path) && is_dir($path) && is_writeable($path)) {
            $this->path = $path . '/' . $this->filename;
            $this->Output($this->path, 'F');
            return true;
        } else {
            Analog::log(
                __METHOD__ . ' ' . $path .
                ' does not exists or is not a directory or is not writeable.',
                Analog::ERROR
            );
        }
        return false;
    }

    /**
     * Get store path
     *
     * @return string
     */
    public function getPath()
    {
        return realpath($this->path);
    }
}
