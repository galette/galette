<?php

/**
 * Copyright © 2003-2024 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
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
 * @author Guillaume Rousse <guillomovitch@gmail.com>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class PdfAdhesionForm extends Pdf
{
    protected Db $zdb;
    protected Adherent $adh;
    protected Preferences $prefs;
    protected string $filename;
    private string $path;

    /**
     * Main constructor
     *
     * @param Adherent    $adh   Adherent
     * @param Db          $zdb   Database instance
     * @param Preferences $prefs Preferences instance
     */
    public function __construct(Adherent $adh, Db $zdb, Preferences $prefs)
    {
        $this->zdb = $zdb;
        $this->adh = $adh;
        $this->prefs = $prefs;

        $model = $this->getModel();
        parent::__construct($prefs, $model);

        $this->filename = $adh->id ?
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
    protected function getModel(): PdfModel
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
    public function store(string $path): bool
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
    public function getPath(): string
    {
        return realpath($this->path);
    }
}
