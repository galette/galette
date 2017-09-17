<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contribution PDF: invoices and receipts
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
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
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
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-07-07
 */

class PdfContribution
{
    private $contrib;
    private $pdf;
    private $model;
    private $filename;
    private $path;

    /**
     * Main constructor
     *
     * @param Contribution $contrib Contribution
     * @param Db           $zdb     Database instance
     * @param Preferences  $prefs   Preferences instance
     */
    public function __construct(Contribution $contrib, $zdb, $prefs)
    {
        $this->contrib = $contrib;

        $class = PdfModel::getTypeClass($this->contrib->model);
        $this->model = new $class($zdb, $prefs, $this->contrib->model);

        $member = new Adherent($zdb, $this->contrib->member);

        $this->model->setPatterns(
            array(
                'adh_name'          => '/{NAME_ADH}/',
                'adh_address'       => '/{ADDRESS_ADH}/',
                'adh_zip'           => '/{ZIP_ADH}/',
                'adh_town'          => '/{TOWN_ADH}/',
                'adh_main_group'    => '/{GROUP_ADH}/',
                'adh_groups'        => '/{GROUPS_ADH}/',
                'adh_company'       => '/{COMPANY_ADH}/',
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

        $address = $member->getAddress();
        if ($member->getAddressContinuation() != '') {
            $address .= '<br/>' . $member->getAddressContinuation();
        }

        $member_groups = $member->groups;
        $main_group = _T("None");
        $group_list = _T("None");
        if (count($member_groups) > 0) {
            $main_group = $member_groups[0]->getName();
            $group_list = '<ul>';
            foreach ($member_groups as $group) {
                $group_list .= '<li>' . $group->getName()  . '</li>';
            }
            $group_list .= '</ul>';
        }

        $this->model->setReplacements(
            array(
                'adh_name'          => $member->sfullname,
                'adh_address'       => $address,
                'adh_zip'           => $member->getZipcode(),
                'adh_town'          => $member->getTown(),
                'adh_main_group'    => $main_group,
                'adh_groups'        => $group_list,
                'adh_company'       => $member->company_name,
                'contrib_label'     => $this->contrib->type->libelle,
                'contrib_amount'    => $this->contrib->amount,
                'contrib_date'      => $this->contrib->date,
                'contrib_year'      => $this->contrib->raw_date->format('Y'),
                'contrib_comment'   => $this->contrib->info,
                'contrib_bdate'     => $this->contrib->begin_date,
                'contrib_edate'     => $this->contrib->end_date,
                'contrib_id'        => $this->contrib->id,
                'contrib_payment'   => $this->contrib->spayment_type
            )
        );

        $this->filename = _T("contribution");
        $this->filename .= '_' . $this->contrib->id . '_';

        if ($this->model->type === PdfModel::RECEIPT_MODEL) {
            $this->filename .= _T("receipt");
        } else {
            $this->filename .= _T("invoice");
        }
        $this->filename .= '.pdf';

        $this->pdf = new Pdf($prefs, $this->model);

        $this->pdf->Open();

        $this->pdf->AddPage();
        $this->pdf->PageHeader();
        $this->pdf->PageBody();
    }

    /**
     * Download PDF from browser
     *
     * @return void
     */
    public function download()
    {
        $this->pdf->Output($this->filename, 'D');
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
            $this->pdf->Output($this->path, 'F');
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
