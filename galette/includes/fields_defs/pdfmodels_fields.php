<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * PDF models declarations
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
 * @category  Functions
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     0.7.5 - 2013-06-02
 */

use Galette\Entity\PdfModel;

$pdfmodels_fields = array(
    array(
        'model_id'  => PdfModel::MAIN_MODEL,
        'model_name'    => '_T("Main")',
        'model_title'   => null,
        'model_type'    => PdfModel::MAIN_MODEL,
        'model_header'  => '<table>
    <tr>
        <td id="pdf_assoname"><strong id="asso_name">{ASSO_NAME}</strong><br/>{ASSO_SLOGAN}</td>
        <td id="pdf_logo">{ASSO_LOGO}</td>
    </tr>
</table>',
        'model_footer'  => '<div id="pdf_footer">
    _T("Association") {ASSO_NAME} - {ASSO_ADDRESS}<br/>
    {ASSO_WEBSITE}
</div>',
        'model_body'    => null,
        'model_styles'  => 'div#pdf_title {
    font-size: 1.4em;
    font-wieght:bold;
    text-align: center;
}

div#pdf_subtitle {
    text-align: center;
}

div#pdf_footer {
    text-align: center;
    font-size: 0.7em;
}

td#pdf_assoname {
    width: 75%;
    font-size: 1.1em;
}

strong#asso_name {
    font-size: 1.6em;
}

td#pdf_logo {
    text-align: right;
    width: 25%;
}',
        'model_parent'  => null
    ),
    array(
        'model_id'  => PdfModel::INVOICE_MODEL,
        'model_name'    => '_T("Invoice")',
        'model_title'   => '_T("Invoice") {CONTRIBUTION_YEAR}-{CONTRIBUTION_ID}',
        'model_type'    => PdfModel::INVOICE_MODEL,
        'model_header'  => null,
        'model_footer'  => null,
        'model_body'    => '<table>
    <tr>
        <td width="300"></td>
        <td><strong>{NAME_ADH}</strong><br/>
            {ADDRESS_ADH}<br/>
            <strong>{ZIP_ADH} {TOWN_ADH}</strong>
        </td>
    </tr>
    <tr>
         <td height="100"></td>
    </tr>
    <tr>
        <td colspan="2">
            <table>
                <thead>
                    <tr>
                        <th>_T("Label")</th>
                        <th>_T("Amount")</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            {CONTRIBUTION_LABEL} (_T("on") {CONTRIBUTION_DATE})<br/>
                            _T("from") {CONTRIBUTION_BEGIN_DATE} _T("to") {CONTRIBUTION_END_DATE}<br/>
                           {CONTRIBUTION_PAYMENT_TYPE}<br/>
                           {CONTRIBUTION_COMMENT}
                        </td>
                        <td>{CONTRIBUTION_AMOUNT}</td>
                    </tr>
                </tbody>
            </table>
        </td>
    </tr>
</table>',
        'model_styles'  => null,
        'model_parent'  => PdfModel::MAIN_MODEL
    ),
    array(
        'model_id'  => PdfModel::RECEIPT_MODEL,
        'model_name'    => '_T("Receipt")',
        'model_title'   => '_T("Receipt") {CONTRIBUTION_YEAR}-{CONTRIBUTION_ID}',
        'model_type'    => PdfModel::RECEIPT_MODEL,
        'model_header'  => null,
        'model_footer'  => null,
        'model_body'    => '<table>
    <tr>
        <td width="300"></td>
        <td><strong>{NAME_ADH}</strong><br/>
            {ADDRESS_ADH}<br/>
            <strong>{ZIP_ADH} {TOWN_ADH}</strong>
        </td>
    </tr>
    <tr>
         <td height="100"></td>
    </tr>
    <tr>
        <td colspan="2">
            <table>
                <thead>
                    <tr>
                        <th>_T("Label")</th>
                        <th>_T("Amount")</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            {CONTRIBUTION_LABEL} (_T("on") {CONTRIBUTION_DATE})<br/>
                            _T("from") {CONTRIBUTION_BEGIN_DATE} _T("to") {CONTRIBUTION_END_DATE}<br/>
                           {CONTRIBUTION_PAYMENT_TYPE}<br/>
                           {CONTRIBUTION_COMMENT}
                        </td>
                        <td>{CONTRIBUTION_AMOUNT}</td>
                    </tr>
                </tbody>
            </table>
        </td>
    </tr>
</table>',
        'model_styles'  => null,
        'model_parent'  => PdfModel::MAIN_MODEL
    ),
    array(
        'model_id'  => PdfModel::ADHESION_FORM_MODEL,
        'model_name'    => '_T("Adherent form")',
        'model_title'   => '_T("Adherent form")',
        'model_type'    => PdfModel::ADHESION_FORM_MODEL,
        'model_header'  => null,
        'model_footer'  => null,
        'model_body'    => '<hr>
<div style="font-size:smaller">_T("Complete the following form and send it with your funds, in order to complete your subscription.")</div>
<table>
<tr>
<td width="50%"></td>
<td width="50%">{ASSO_ADDRESS_MULTI}</td>
</tr>
</table>
<hr>
<div>
_T("Required membership:")
<form action="none">
<input type="checkbox" name="cotisation1" value="none">_T("Active member")
<input type="checkbox" name="cotisation2" value="none">_T("Benefactor member")
<input type="checkbox" name="cotisation3" value="none">_T("Donation")
</form>
<p style="font-size:smaller">_T("The minimum contribution for each type of membership are defined on the website of the association. The amount of donations are free to be decided by the generous donor.")</p>

<table>
    <tr>
        <td class="label">_T("Politeness")</td>
        <td class="input">{TITLE_ADH}</td>
    </tr>
    <tr>
        <td class="label">_T("Name")</td>
        <td class="input">{FIRST_NAME_ADH}</td>
    </tr>
    <tr>
        <td class="label">_T("First name")</td>
        <td class="input">{LAST_NAME_ADH}</td>
    </tr>
    <tr>
        <td class="label">_T("Company name") *</td>
        <td class="input">{GENDER_ADH}</td>
    </tr>
    <tr>
        <td class="label">_T("Address")</td>
        <td class="input">{ADDRESS_ADH}</td>
    </tr>
    <tr>
        <td class="label">_T("Zip Code")</td>
        <td class="input">{ZIP_ADH}</td>
    </tr>
    <tr>
        <td class="label">_T("City")</td>
        <td class="input">{TOWN_ADH}</td>
    </tr>
    <tr>
        <td class="label">_T("Country")</td>
        <td class="input">{COUNTRY_ADH}</td>
    </tr>
    <tr>
        <td class="label">_T("Email adress")</td>
        <td class="input">{EMAIL_ADH}</td>
    </tr>
    <tr>
        <td class="label">_T("Username") **</td>
        <td class="input">{LOGIN_ADH}</td>
    </tr>
    <tr>
        <td class="label">_T("Amount")</td>
        <td class="input"></td>
    </tr>
</table>
<p>
_T("Hereby, I agree to comply to association\'s statutes and its rules.")<br>
_T("At ")<br>
_T("On            /            /            ")<br>
_T("Signature")
</p>
<p style="font-size:smaller;text-align:right;">
_T("* Only for compagnies")<br>
_T("** Galette identifier, if applicable")
</p>',
        'model_styles'  => 'td.label {
    width: 20%;
    font-weight: bold;
}
td.input {
    width: 80%;
    border-bottom: 1px solid black;
}',
        'model_parent'  => PdfModel::MAIN_MODEL
    )
);

