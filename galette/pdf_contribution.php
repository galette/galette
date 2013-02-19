<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Invoice/Receipt PDF for contributions
 *
 * User have to select members in the member's list to generate labels.
 * Format is defined in the preferences screen
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
 * @category  Print
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 */

use Galette\IO\Pdf;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\PdfModel;
use Galette\Entity\PdfInvoice;
use Galette\Entity\PdfReceipt;
use Analog\Analog as Analog;

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header("location: index.php");
    die();
}
if ( !$login->isAdmin() && !$login->isStaff() ) {
    header("location: voir_adherent.php");
    die();
}

if ( !isset($_GET['id_cotis']) ) {
    throw new \RuntimeException('Contribution id is mandatory.');
}

$contribution = new Contribution((int)$_GET['id_cotis']);
$class = PdfModel::getTypeClass($contribution->model);
$model = new $class($zdb, $preferences, $contribution->model);

$member = new Adherent($contribution->member);

$model->setPatterns(
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

$model->setReplacements(
    array(
        'adh_name'          => $member->sfullname,
        'adh_address'       => $address,
        'adh_zip'           => $member->zipcode,
        'adh_town'          => $member->town,
        'contrib_label'     => $contribution->type->libelle,
        'contrib_amount'    => $contribution->amount,
        'contrib_date'      => $contribution->date,
        'contrib_year'      => $contribution->raw_date->format('Y'),
        'contrib_comment'   => $contribution->info,
        'contrib_bdate'     => $contribution->begin_date,
        'contrib_edate'     => $contribution->end_date,
        'contrib_id'        => $contribution->id,
        'contrib_payment'   => $contribution->spayment_type
    )
);

//var_dump($contribution);
//var_dump($model);

$pdf = new Pdf($model);

$pdf->Open();

$pdf->AddPage();
$pdf->PageHeader();
$pdf->PageBody();

$pdf->Output(_T("invoice") . '.pdf', 'D');
