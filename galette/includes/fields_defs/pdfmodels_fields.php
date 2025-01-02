<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

declare(strict_types=1);

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
        <td id="pdf_logo">{ASSO_PRINT_LOGO}</td>
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
}

td[dir=rtl]#pdf_logo {
    text-align: left;
    width: 0%;
}

td[dir=rtl]#pdf_assoname {
    width: 100%;
    margin-left: 25%;
}

hr {
    cap:\'\';
    join:\'\';
    dash:\'\';
    phase:\'\';
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
        'model_name'    => '_T("Adhesion form")',
        'model_title'   => '_T("Adhesion form")',
        'model_type'    => PdfModel::ADHESION_FORM_MODEL,
        'model_header'  => null,
        'model_footer'  => null,
        'model_body'    => '<hr/>
<div class="infos">_T("Complete the following form and send it with your funds, in order to complete your subscription.")</div>
<table>
    <tr>
        <td width="50%"></td>
        <td width="50%">{ASSO_ADDRESS_MULTI}</td>
    </tr>
</table>
<hr/>
<table>
    <tr>
        <td height="30"></td>
    </tr>
    <tr>
        <td>
            <table>
                <tr>
                <td class="boxlabel">_T("Required membership:")</td>
                <td class="box"><input type="radio" name="cotisation" value="none1"></td><td class="boxlabel">_T("Active member")</td>
                <td class="box"><input type="radio" class="box" name="cotisation" value="none2"></td><td class="boxlabel">_T("Benefactor member")</td>
                <td class="box"><input type="radio" class="box" name="cotisation" value="none3"></td><td class="boxlabel">_T("Donation")</td>
                </tr>
            </table>
            <div class="infos">_T("The minimum contribution for each type of membership are defined on the website of the association. The amount of donations are free to be decided by the generous donor.")  </div>
        </td>
    </tr>
    <tr>
        <td height="30"></td>
    </tr>
</table>
<table class="member">
    <tr>
        <td class="label">_T("Politeness")</td>
        <td class="input">{TITLE_ADH}</td>
    </tr>
    <tr>
        <td class="label">_T("Name")</td>
        <td class="input">{LAST_NAME_ADH}</td>
    </tr>
    <tr>
        <td class="label">_T("First name")</td>
        <td class="input">{FIRST_NAME_ADH}</td>
    </tr>
    <tr>
        <td class="label">_T("Company name") *</td>
        <td class="input">{COMPANY_ADH}</td>
    </tr>
    <tr>
        <td class="label">_T("Address")</td>
        <td class="input">{ADDRESS_ADH}</td>
    </tr>
    <tr>
        <td class="label"></td>
        <td class="input"></td>
    </tr>
    <tr>
        <td class="label"></td>
        <td class="input"></td>
    </tr>
    <tr>
        <td class="label">_T("Zip Code")</td>
        <td class="cpinput">{ZIP_ADH}</td>
        <td class="label">_T("City")</td>
        <td class="towninput">{TOWN_ADH}</td>
    </tr>
    <tr>
        <td class="label">_T("Country")</td>
        <td class="input">{COUNTRY_ADH}</td>
    </tr>
    <tr>
        <td class="label">_T("Email address")</td>
        <td class="input">{EMAIL_ADH}</td>
    </tr>
    <tr>
        <td class="label">_T("Username") **</td>
        <td class="input">{LOGIN_ADH}</td>
    </tr>
    <tr>
        <td colspan="2" height="10"></td>
    </tr>
    <tr>
        <td class="label">_T("Amount")</td>
        <td class="input"></td>
    </tr>
</table>
<p>str_replace(\'%s\', \'{ASSO_NAME}\', \'_T("Hereby, I agree to comply to %s association statutes and its rules.")\')</p><p>_T("At ................................................")</p><p>_T("On .......... / .......... / .......... ")</p><p>_T("Signature")</p>
<p class="notes">_T("* Only for compagnies")<br/>_T("** Galette identifier, if applicable")</p>',
        'model_styles'  => 'td.label {
    width: 20%;
    font-weight: bold;
}
td.input {
    width: 80%;
    border-bottom: 1px dotted black;
}

td.cpinput {
    width: 10%;
    border-bottom: 1px dotted black;
}

td.towninput {
    width: 50%;
    border-bottom: 1px dotted black;
}

div.infos {
    font-size: .8em;
}

p.notes {
    font-size: 0.6em;
    text-align: right;
}

.member td {
    line-height: 20px;
    height: 20px;
}

td.boxlabel {
    width: 20%;
}

td.box {
    width: 5%;
}',
        'model_parent'  => PdfModel::MAIN_MODEL
    )
);
