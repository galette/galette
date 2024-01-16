<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contribution PDF: invoices and receipts
 *
 * PHP version 5
 *
 * Copyright © 2013-2023 The Galette Team
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
 *
 * @category  IO
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.7.5dev - 2013-07-07
 */

namespace Galette\IO;

use Galette\Core\Db;
use Galette\Core\Preferences;
use Galette\Entity\Contribution;
use Galette\Entity\Adherent;
use Galette\Entity\PdfModel;
use Analog\Analog;

/**
 * Contribution PDF: invoices and receipts
 *
 * @category  IO
 * @name      PDF
 * @package   Galette
 * @abstract  Class for expanding TCPDF.
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.7.5dev - 2013-07-07
 */

class PdfContribution extends Pdf
{
    private $contrib;
    private $model;
    private $path;

    /**
     * Main constructor
     *
     * @param Contribution $contrib Contribution
     * @param Db           $zdb     Database instance
     * @param Preferences  $prefs   Preferences instance
     */
    public function __construct(Contribution $contrib, Db $zdb, $prefs)
    {
        $this->contrib = $contrib;

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

        $this->Open();

        $this->AddPage();
        $this->PageHeader();
        $this->PageBody();
    }

    /**
     * Download PDF from browser
     *
     * @return string
     */
    public function download(): string
    {
        return $this->Output($this->filename, 'D');
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

    /**
     * Get filename
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }
}
