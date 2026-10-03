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
use Galette\Entity\Contribution;
use Galette\Entity\Adherent;
use Galette\Entity\PdfModel;
use Analog\Analog;

use function Safe\realpath;

/**
 * Contribution PDF: invoices and receipts
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class PdfContribution extends Pdf
{
    private readonly PdfModel $model;
    private string $path;

    /**
     * Main constructor
     *
     * @param Contribution $contrib Contribution
     * @param Db           $zdb     Database instance
     * @param Preferences  $prefs   Preferences instance
     */
    public function __construct(
        private readonly Contribution $contrib,
        Db $zdb,
        Preferences $prefs
    ) {
        $class = PdfModel::getTypeClass($this->contrib->model);
        $this->model = new $class($zdb, $prefs);

        $member = new Adherent($zdb, $this->contrib->member, ['dynamics' => true]);

        $this->model->setMember($member);
        $this->model->setContribution($this->contrib);

        $this->filename = __("contribution");
        $this->filename .= '_' . $this->contrib->id . '_';

        if ($this->model->type === PdfModel::RECEIPT_MODEL) {
            $this->filename .= __("receipt");
        } else {
            $this->filename .= __("invoice");
        }
        $this->filename .= '.pdf';

        parent::__construct($prefs, $this->model);

        $this->PageHeader();
        $this->PageBody();
    }

    /**
     * Download PDF from browser
     */
    public function download(): string
    {
        return $this->Output($this->filename, 'D');
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

    /**
     * Get filename
     */
    public function getFilename(): string
    {
        return $this->filename;
    }
}
