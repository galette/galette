<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * PDF model tests
 *
 * PHP version 5
 *
 * Copyright © 2020 The Galette Team
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
 * @category  Entity
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2020-11-21
 */

namespace Galette\Entity\test\units;

use Galette\Entity\Adherent;
use Galette\DynamicFields\DynamicField;
use Galette\GaletteTestCase;

/**
 * PDF model tests
 *
 * @category  Entity
 * @name      PdfModel
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-11-21
 */
class PdfModel extends GaletteTestCase
{
    private $remove = [];
    protected $seed = 95842354;

    /**
     * Set up tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function beforeTestMethod($method)
    {
        parent::beforeTestMethod($method);

        $models = new \Galette\Repository\PdfModels($this->zdb, $this->preferences, $this->login);
        $res = $models->installInit(false);
        $this->boolean($res)->isTrue();

        $this->adh = new \Galette\Entity\Adherent($this->zdb);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
        $this->contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);
    }

    /**
     * Tear down tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function afterTestMethod($method)
    {
        parent::afterTestMethod($method);

        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where(['info_cotis' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);
        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $this->zdb->execute($delete);
        $delete = $this->zdb->delete(\Galette\Entity\DynamicFieldsHandle::TABLE);
        $this->zdb->execute($delete);
        $delete = $this->zdb->delete(DynamicField::TABLE);
        $this->zdb->execute($delete);
        //cleanup dynamic translations
        $delete = $this->zdb->delete(\Galette\Core\L10n::TABLE);
        $delete->where([
            'text_orig' => [
                'Dynamic choice field',
                'Dynamic date field',
                'Dynamic text field'
            ]
        ]);
        $this->zdb->execute($delete);
    }

    /**
     * Test expected patterns
     *
     * @return void
     */
    public function testExpectedPatterns()
    {
        $model = new class ($this->zdb, $this->preferences, 1) extends \Galette\Entity\PdfModel {
        };

        $main_expected = [
            'asso_name'          => '/{ASSO_NAME}/',
            'asso_slogan'        => '/{ASSO_SLOGAN}/',
            'asso_address'       => '/{ASSO_ADDRESS}/',
            'asso_address_multi' => '/{ASSO_ADDRESS_MULTI}/',
            'asso_website'       => '/{ASSO_WEBSITE}/',
            'asso_logo'          => '/{ASSO_LOGO}/',
            'date_now'           => '/{DATE_NOW}/',
            'login_uri'          => '/{LOGIN_URI}/'
        ];
        $this->array($model->getPatterns())->isIdenticalTo($main_expected);

        $model = new \Galette\Entity\PdfMain($this->zdb, $this->preferences);
        $this->array($model->getPatterns())->isIdenticalTo($main_expected);

        $expected = $main_expected + [
            'adh_title'         => '/{TITLE_ADH}/',
            'adh_id'            => '/{ID_ADH}/',
            'adh_num'           => '/{NUM_ADH}/',
            'adh_name'          => '/{NAME_ADH}/',
            'adh_last_name'     => '/{LAST_NAME_ADH}/',
            'adh_first_name'    => '/{FIRST_NAME_ADH}/',
            'adh_nickname'      => '/{NICKNAME_ADH}/',
            'adh_gender'        => '/{GENDER_ADH}/',
            'adh_birth_date'    => '/{ADH_BIRTH_DATE}/',
            'adh_birth_place'   => '/{ADH_BIRTH_PLACE}/',
            'adh_profession'    => '/{PROFESSION_ADH}/',
            'adh_company'       => '/{COMPANY_ADH}/',
            'adh_address'       => '/{ADDRESS_ADH}/',
            'adh_zip'           => '/{ZIP_ADH}/',
            'adh_town'          => '/{TOWN_ADH}/',
            'adh_country'       => '/{COUNTRY_ADH}/',
            'adh_phone'         => '/{PHONE_ADH}/',
            'adh_mobile'        => '/{MOBILE_ADH}/',
            'adh_email'         => '/{EMAIL_ADH}/',
            'adh_login'         => '/{LOGIN_ADH}/',
            'adh_main_group'    => '/{GROUP_ADH}/',
            'adh_groups'        => '/{GROUPS_ADH}/',
            'adh_dues'          => '/{ADH_DUES}/',
            'days_remaining'    => '/{DAYS_REMAINING}/',
            'days_expired'      => '/{DAYS_EXPIRED}/',
            '_adh_company'      => '/{COMPANY_NAME_ADH}/',
            '_adh_last_name'    => '/{LASTNAME_ADH}/',
            '_adh_first_name'   => '/{FIRSTNAME_ADH}/',
            '_adh_login'        => '/{LOGIN}/',
            '_adh_email'        => '/{MAIL_ADH}/',
        ];
        $model = new \Galette\Entity\PdfAdhesionFormModel($this->zdb, $this->preferences);
        $this->array($model->getPatterns())->isIdenticalTo($expected);

        $expected += [
            'contrib_label'     => '/{CONTRIB_LABEL}/',
            'contrib_amount'    => '/{CONTRIB_AMOUNT}/',
            'contrib_amount_letters' => '/{CONTRIB_AMOUNT_LETTERS}/',
            'contrib_date'      => '/{CONTRIB_DATE}/',
            'contrib_year'      => '/{CONTRIB_YEAR}/',
            'contrib_comment'   => '/{CONTRIB_COMMENT}/',
            'contrib_bdate'     => '/{CONTRIB_BEGIN_DATE}/',
            'contrib_edate'     => '/{CONTRIB_END_DATE}/',
            'contrib_id'        => '/{CONTRIB_ID}/',
            'contrib_payment'   => '/{CONTRIB_PAYMENT_TYPE}/',
            'contrib_info'       => '/{CONTRIB_INFO}/',
            '_contrib_label'     => '/{CONTRIBUTION_LABEL}/',
            '_contrib_amount'    => '/{CONTRIBUTION_AMOUNT}/',
            '_contrib_amount_letters' => '/{CONTRIBUTION_AMOUNT_LETTERS}/',
            '_contrib_date'      => '/{CONTRIBUTION_DATE}/',
            '_contrib_year'      => '/{CONTRIBUTION_YEAR}/',
            '_contrib_comment'   => '/{CONTRIBUTION_COMMENT}/',
            '_contrib_bdate'     => '/{CONTRIBUTION_BEGIN_DATE}/',
            '_contrib_edate'     => '/{CONTRIBUTION_END_DATE}/',
            '_contrib_id'        => '/{CONTRIBUTION_ID}/',
            '_contrib_payment'   => '/{CONTRIBUTION_PAYMENT_TYPE}/',
            '_contrib_info'      => '/{CONTRIBUTION_INFO}/',
            '__contrib_label'    => '/{CONTRIB_TYPE}/',
            'deadline'           => '/{DEADLINE}/'
        ];
        $model = new \Galette\Entity\PdfInvoice($this->zdb, $this->preferences);
        $this->array($model->getPatterns())->isIdenticalTo($expected);

        $model = new \Galette\Entity\PdfReceipt($this->zdb, $this->preferences);
        $this->array($model->getPatterns())->isIdenticalTo($expected);
    }

    /**
     * Types provider
     *
     * @return array
     */
    protected function typesProvider(): array
    {
        return [
            [
                'type'  => \Galette\Entity\PdfModel::MAIN_MODEL,
                'expected'  => 'Galette\Entity\PdfMain'
            ], [
                'type'  => \Galette\Entity\PdfModel::INVOICE_MODEL,
                'expected'  => 'Galette\Entity\PdfInvoice'
            ], [
                'type'  => \Galette\Entity\PdfModel::RECEIPT_MODEL,
                'expected'  => 'Galette\Entity\PdfReceipt'
            ], [
                'type'  => \Galette\Entity\PdfModel::ADHESION_FORM_MODEL,
                'expected'  => 'Galette\Entity\PdfAdhesionFormModel'
            ], [
                'type'  => 0,
                'expected'  => 'Galette\Entity\PdfMain'
            ]
        ];
    }

    /**
     * Tets getTypeClass
     * @dataProvider typesProvider
     *
     * @param integer $type     Requested type
     * @param string  $expected Expected class name
     *
     * @return void
     */
    public function testGetypeClass($type, $expected)
    {
        $this->string(\Galette\Entity\PdfModel::getTypeClass($type))->isIdenticalTo($expected);
    }

    /**
     * Test model replacements
     *
     * @return void
     */
    public function testReplacements()
    {
        //create dynamic fields
        $field_data = [
            'form_name'        => 'adh',
            'field_name'        => 'Dynamic text field',
            'field_perm'        => DynamicField::PERM_USER_WRITE,
            'field_type'        => DynamicField::TEXT,
            'field_required'    => 1,
            'field_repeat'      => 1
        ];

        $adf = DynamicField::getFieldType($this->zdb, $field_data['field_type']);

        $stored = $adf->store($field_data);
        $error_detected = $adf->getErrors();
        $warning_detected = $adf->getWarnings();
        $this->boolean($stored)->isTrue(
            implode(
                ' ',
                $adf->getErrors() + $adf->getWarnings()
            )
        );
        $this->array($error_detected)->isEmpty(implode(' ', $adf->getErrors()));
        $this->array($warning_detected)->isEmpty(implode(' ', $adf->getWarnings()));

        $field_data = [
            'form_name'         => 'contrib',
            'field_form'        => 'contrib',
            'field_name'        => 'Dynamic date field',
            'field_perm'        => DynamicField::PERM_USER_WRITE,
            'field_type'        => DynamicField::DATE,
            'field_required'    => 1,
            'field_repeat'      => 1
        ];

        $cdf = DynamicField::getFieldType($this->zdb, $field_data['field_type']);

        $stored = $cdf->store($field_data);
        $error_detected = $cdf->getErrors();
        $warning_detected = $cdf->getWarnings();
        $this->boolean($stored)->isTrue(
            implode(
                ' ',
                $cdf->getErrors() + $cdf->getWarnings()
            )
        );
        $this->array($error_detected)->isEmpty(implode(' ', $cdf->getErrors()));
        $this->array($warning_detected)->isEmpty(implode(' ', $cdf->getWarnings()));

        //prepare model
        $rs = new \stdClass();
        $pk = \Galette\Entity\PdfModel::PK;
        $rs->$pk = 42;
        $rs->model_name = 'Test model';
        $rs->model_title = 'A simple tmodel for tests';
        $rs->model_subtitle = 'The subtitle';
        $rs->model_header = null;
        $rs->model_footer = null;
        $rs->model_body = 'name: {NAME_ADH} login: {LOGIN_ADH} birthdate: {ADH_BIRTH_DATE} dynlabel: {LABEL_DYNFIELD_' .
            $adf->getId() . '_ADH} dynvalue: {INPUT_DYNFIELD_' . $adf->getId() . '_ADH} ' .
            '- enddate: {CONTRIB_END_DATE} amount: {CONTRIB_AMOUNT} ({CONTRIB_AMOUNT_LETTERS}) dynlabel: ' .
            '{LABEL_DYNFIELD_' . $cdf->getId() . '_CONTRIB} dynvalue: {INPUT_DYNFIELD_' . $cdf->getId() . '_CONTRIB}';
        $rs->model_styles = null;
        $rs->model_parent = \Galette\Entity\PdfModel::MAIN_MODEL;

        $model = new \Galette\Entity\PdfInvoice($this->zdb, $this->preferences, $rs);

        $data = [
            'nom_adh' => 'Durand',
            'prenom_adh' => 'René',
            'ville_adh' => 'Martel',
            'cp_adh' => '39 069',
            'adresse_adh' => '66, boulevard De Oliveira',
            'email_adh' => 'meunier.josephine@ledoux.com',
            'login_adh' => 'arthur.hamon',
            'mdp_adh' => 'J^B-()f',
            'mdp_adh2' => 'J^B-()f',
            'bool_admin_adh' => false,
            'bool_exempt_adh' => false,
            'bool_display_info' => true,
            'sexe_adh' => 0,
            'prof_adh' => 'Chef de fabrication',
            'titre_adh' => null,
            'ddn_adh' => '1937-12-26',
            'lieu_naissance' => 'Gonzalez-sur-Meunier',
            'pseudo_adh' => 'ubertrand',
            'pays_adh' => 'Antarctique',
            'tel_adh' => '0439153432',
            'activite_adh' => true,
            'id_statut' => 9,
            'date_crea_adh' => '2020-06-10',
            'pref_lang' => 'en_US',
            'fingerprint' => 'FAKER' . $this->seed,
            'info_field_' . $adf->getId() . '_1' => 'My value (:'
        ];
        $this->createMember($data);
        $model->setMember($this->adh);

        $this->createPdfContribution($cdf);
        $model->setContribution($this->contrib);

        $this->string($model->hheader)->isIdenticalTo("<table>
    <tr>
        <td id=\"pdf_assoname\"><strong id=\"asso_name\">Galette</strong><br/></td>
        <td id=\"pdf_logo\"><img src=\"http://logo\" width=\"129\" height=\"60\"/></td>
    </tr>
</table>");

        $this->string($model->hfooter)->isIdenticalTo('<div id="pdf_footer">
    Association Galette - Galette
Palais des Papes
Au milieu
84000 Avignon - France<br/>
    
</div>');

        $this->string($model->hbody)->isEqualTo(
            'name: DURAND René login: arthur.hamon birthdate: 1937-12-26 dynlabel: Dynamic text field dynvalue: ' .
            'My value (: ' .
            '- enddate: ' . $this->contrib->end_date . ' amount: 92 (ninety-two) dynlabel: Dynamic date field ' .
            'dynvalue: 2020-12-03'
        );

        $legend = $model->getLegend();
        $this->array($legend)
            ->hasSize(3)
            ->hasKeys(['main', 'member', 'contribution']);

        $this->array($legend['main']['patterns'])->hasSize(8);
        $this->array($legend['member']['patterns'])
            ->hasSize(27)
            ->hasKeys(['label_dynfield_' . $adf->getId() . '_adh', 'dynfield_' . $adf->getId() . '_adh']);
        $this->array($legend['contribution']['patterns'])
            ->hasSize(14)
            ->hasKeys(['label_dynfield_' . $cdf->getId() . '_contrib', 'dynfield_' . $cdf->getId() . '_contrib']);
    }

    /**
     * Create member from data
     *
     * @param array $data Data to use to create member
     *
     * @return Adherent
     */
    public function createMember(array $data)
    {
        $adh = $this->adh;
        $check = $adh->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->boolean($check)->isTrue();

        $store = $adh->store();
        $this->boolean($store)->isTrue();
        return $adh;
    }

    /**
     * Create test contribution in database
     *
     * @param DynamicField $cdf Contribution dynamic field
     *
     * @return void
     */
    protected function createPdfContribution($cdf)
    {
        $bdate = new \DateTime(); // 2020-11-07
        $bdate->sub(new \DateInterval('P5M')); // 2020-06-07
        $bdate->add(new \DateInterval('P3D')); // 2020-06-10

        $edate = clone $bdate;
        $edate->add(new \DateInterval('P1Y'));

        $dyndate = new \DateTime('2020-12-03 22:56:53');

        $data = [
            'id_adh' => $this->adh->id,
            'id_type_cotis' => 1,
            'montant_cotis' => 92,
            'type_paiement_cotis' => 3,
            'info_cotis' => 'FAKER' . $this->seed,
            'date_enreg' => $bdate->format('Y-m-d'),
            'date_debut_cotis' => $bdate->format('Y-m-d'),
            'date_fin_cotis' => $edate->format('Y-m-d'),
            'info_field_' . $cdf->getId() . '_1' => $dyndate->format('Y-m-d')
        ];
        $this->createContrib($data);
    }

    /**
     * Test model storage in db
     *
     * @return void
     */
    public function testStorage()
    {
        $model = new \Galette\Entity\PdfInvoice($this->zdb, $this->preferences);

        $orig_title = $model->title;
        $this->string($orig_title)->isIdenticalTo('_T("Invoice") {CONTRIBUTION_YEAR}-{CONTRIBUTION_ID}');

        $model->title = 'Another test';
        $this->boolean($model->store())->isTrue();

        $model = new \Galette\Entity\PdfInvoice($this->zdb, $this->preferences);
        $this->string($model->title)->isIdenticalTo('Another test');
    }
}
