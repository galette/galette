<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contribution PDF: invoices and receipts
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
 * @category  IO
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-07-07
 */

namespace Galette\IO;

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
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-07-07
 */

class PdfContribution
{
    private $_contrib;
    private $_pdf;
    private $_model;
    private $_filename;
    private $_path;

    /**
     * Main constructor
     *
     * @param Contribution $contrib Contribution
     * @param Db           $zdb     Database instance
     * @param Preferences  $prefs   Preferences instance
     */
    public function __construct(Contribution $contrib, $zdb, $prefs)
    {
        $this->_contrib = $contrib;

        $class = PdfModel::getTypeClass($this->_contrib->model);
        $this->_model = new $class($zdb, $prefs, $this->_contrib->model);

        $member = new Adherent($this->_contrib->member);

        $this->_model->setPatterns(
            array(
                'adh_name'          => '/{NAME_ADH}/',
                'adh_address'       => '/{ADDRESS_ADH}/',
                'adh_zip'           => '/{ZIP_ADH}/',
                'adh_town'          => '/{TOWN_ADH}/',
                'contrib_label'     => '/{CONTRIBUTION_LABEL}/',
                'contrib_amount'    => '/{CONTRIBUTION_AMOUNT}/',
                'contrib_date'      => '/{CONTRIBUTION_DATE}/',
                'contrib_year'      => '/{CONTRIBUTION_YEAR}/',
                'contrib_comment'   => '/{CONTRIBUTION_COMMENT}/',
                'contrib_bdate'     => '/{CONTRIBUTION_BEGIN_DATE}/',
                'contrib_edate'     => '/{CONTRIBUTION_END_DATE}/',
                'contrib_id'        => '/{CONTRIBUTION_ID}/',
                'contrib_payment'   => '/{CONTRIBUTION_PAYMENT_TYPE}/'
            )
        );

        $address = $member->adress;
        if ( $member->adress_continuation != '' ) {
            $address .= '<br/>' . $member->adress_continuation;
        }

        $this->_model->setReplacements(
            array(
                'adh_name'          => $member->sfullname,
                'adh_address'       => $address,
                'adh_zip'           => $member->zipcode,
                'adh_town'          => $member->town,
                'contrib_label'     => $this->_contrib->type->libelle,
                'contrib_amount'    => $this->_contrib->amount,
                'contrib_date'      => $this->_contrib->date,
                'contrib_year'      => $this->_contrib->raw_date->format('Y'),
                'contrib_comment'   => $this->_contrib->info,
                'contrib_bdate'     => $this->_contrib->begin_date,
                'contrib_edate'     => $this->_contrib->end_date,
                'contrib_id'        => $this->_contrib->id,
                'contrib_payment'   => $this->_contrib->spayment_type
            )
        );

        $this->_filename = _T("contribution");
        $this->_filename .= '_' . $this->_contrib->id . '_';

        if ( $this->_model->type === PdfModel::RECEIPT_MODEL ) {
            $this->_filename .= _T("receipt");
        } else {
            $this->_filename .= _T("invoice");
        }
        $this->_filename .= '.pdf';

        $this->_pdf = new Pdf($this->_model);

        $this->_pdf->Open();

        $this->_pdf->AddPage();
        $this->_pdf->PageHeader();
        $this->_pdf->PageBody();
    }

    /**
     * Download PDF from browser
     *
     * @return void
     */
    public function download()
    {
        $this->_pdf->Output($this->_filename, 'D');
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
        if ( file_exists($path) && is_dir($path) && is_writeable($path) ) {
            $this->_path = $path . '/' . $this->_filename;
            $this->_pdf->Output($this->_path, 'F');
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
        return realpath($this->_path);
    }
}
