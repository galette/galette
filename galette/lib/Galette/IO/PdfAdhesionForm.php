<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\IO;

use Galette\Core\Db;
use Galette\Core\Preferences;
use Galette\Entity\Adherent;
use Galette\Entity\PdfModel;
use Galette\Entity\PdfAdhesionFormModel;
use Analog\Analog;

use function Safe\realpath;

/**
 * Adhesion Form PDF
 *
 * @author Guillaume Rousse <guillomovitch@gmail.com>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class PdfAdhesionForm extends Pdf
{
    protected string $filename;
    private string $path;

    /**
     * Main constructor
     *
     * @param Adherent    $adh   Adherent
     * @param Db          $zdb   Database instance
     * @param Preferences $prefs Preferences instance
     */
    public function __construct(
        protected Adherent $adh,
        protected Db $zdb,
        protected Preferences $prefs
    ) {
        $model = $this->getModel();
        parent::__construct($prefs, $model);

        $this->filename = $adh->id
            ? __("adherent_form") . '.' . $adh->id . '.pdf' : __("adherent_form") . '.pdf';

        if ($model !== null) {
            $this->PageHeader();
            $this->PageBody();
        }
    }

    /**
     * Get model
     */
    protected function getModel(): ?PdfModel
    {
        $model = new PdfAdhesionFormModel($this->zdb, $this->prefs);
        $model->setMember($this->adh);

        return $model;
    }

    /**
     * Store PDF
     *
     * @param string $path Path
     */
    public function store(string $path): bool
    {
        if (file_exists($path) && is_dir($path) && is_writable($path)) {
            $this->path = $path . '/' . $this->filename;
            $this->Output($this->path, 'F');
            return true;
        } else {
            Analog::log(
                __METHOD__ . ' ' . $path
                . ' does not exists or is not a directory or is not writeable.',
                Analog::ERROR
            );
        }
        return false;
    }

    /**
     * Get store path
     */
    public function getPath(): string
    {
        return realpath($this->path);
    }
}
