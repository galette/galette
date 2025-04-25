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

namespace Galette\Entity\test\units;

use Galette\Entity\Adherent;
use Galette\DynamicFields\DynamicField;
use Galette\GaletteTestCase;

/**
 * PDF model tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PdfModel extends GaletteTestCase
{
    private array $remove = [];
    protected int $seed = 95842354;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $models = new \Galette\Repository\PdfModels($this->zdb, $this->preferences, $this->login);
        $res = $models->installInit(false);
        $this->assertTrue($res);

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
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

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
    public function testExpectedPatterns(): void
    {
        $model = new class ($this->zdb, $this->preferences, 1) extends \Galette\Entity\PdfModel {
        };

        $main_expected = [
            'asso_name'          => '/{ASSO_NAME}/',
            'asso_slogan'        => '/{ASSO_SLOGAN}/',
            'asso_address'       => '/{ASSO_ADDRESS}/',
            'asso_address_multi' => '/{ASSO_ADDRESS_MULTI}/',
            'asso_phone_number'  => '/{ASSO_PHONE}/',
            'asso_email'         => '/{ASSO_EMAIL}/',
            'asso_website'       => '/{ASSO_WEBSITE}/',
            'asso_logo'          => '/{ASSO_LOGO}/',
            'asso_print_logo'    => '/{ASSO_PRINT_LOGO}/',
            'date_now'           => '/{DATE_NOW}/',
            'login_uri'          => '/{LOGIN_URI}/',
            'asso_footer'        => '/{ASSO_FOOTER}/',
        ];
        $this->assertSame($main_expected, $model->getPatterns());

        $model = new \Galette\Entity\PdfMain($this->zdb, $this->preferences);
        $this->assertSame($main_expected, $model->getPatterns());

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
            'adh_address_multi' => '/{ADDRESS_ADH_MULTI}/',
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
        $this->assertSame($expected, $model->getPatterns());

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
        $this->assertSame($expected, $model->getPatterns());

        $model = new \Galette\Entity\PdfReceipt($this->zdb, $this->preferences);
        $this->assertSame($expected, $model->getPatterns());
    }

    /**
     * Types provider
     *
     * @return array
     */
    public static function typesProvider(): array
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
    public function testGetypeClass(int $type, string $expected): void
    {
        $this->assertSame($expected, \Galette\Entity\PdfModel::getTypeClass($type));
    }

    /**
     * Test model replacements
     *
     * @return void
     */
    public function testReplacements(): void
    {
        //create dynamic fields
        $field_data = [
            'form_name'        => 'adh',
            'field_name'        => 'Dynamic text field',
            'field_perm'        => \Galette\Entity\FieldsConfig::USER_WRITE,
            'field_type'        => DynamicField::TEXT,
            'field_required'    => 1,
            'field_repeat'      => 1
        ];

        $adf = DynamicField::getFieldType($this->zdb, $field_data['field_type']);

        $stored = $adf->store($field_data);
        $error_detected = $adf->getErrors();
        $warning_detected = $adf->getWarnings();
        $this->assertTrue(
            $stored,
            implode(
                ' ',
                $adf->getErrors() + $adf->getWarnings()
            )
        );
        $this->assertEmpty($error_detected, implode(' ', $adf->getErrors()));
        $this->assertEmpty($warning_detected, implode(' ', $adf->getWarnings()));

        $field_data = [
            'form_name'         => 'contrib',
            'field_form'        => 'contrib',
            'field_name'        => 'Dynamic date field',
            'field_perm'        => \Galette\Entity\FieldsConfig::USER_WRITE,
            'field_type'        => DynamicField::DATE,
            'field_required'    => 1,
            'field_repeat'      => 1
        ];

        $cdf = DynamicField::getFieldType($this->zdb, $field_data['field_type']);

        $stored = $cdf->store($field_data);
        $error_detected = $cdf->getErrors();
        $warning_detected = $cdf->getWarnings();
        $this->assertTrue(
            $stored,
            implode(
                ' ',
                $cdf->getErrors() + $cdf->getWarnings()
            )
        );
        $this->assertEmpty($error_detected, implode(' ', $cdf->getErrors()));
        $this->assertEmpty($warning_detected, implode(' ', $cdf->getWarnings()));

        //prepare model
        $pk = \Galette\Entity\PdfModel::PK;
        $rs = new \ArrayObject([
            $pk => 42,
            'model_name' => 'Test model',
            'model_title' => 'A simple tmodel for tests',
            'model_subtitle' => 'The subtitle',
            'model_header' => null,
            'model_footer' => null,
            'model_body' => 'name: {NAME_ADH} login: {LOGIN_ADH} birthdate: {ADH_BIRTH_DATE} dynlabel: {LABEL_DYNFIELD_' .
            $adf->getId() . '_ADH} dynvalue: {INPUT_DYNFIELD_' . $adf->getId() . '_ADH} ' .
            '- enddate: {CONTRIB_END_DATE} amount: {CONTRIB_AMOUNT} ({CONTRIB_AMOUNT_LETTERS}) dynlabel: ' .
            '{LABEL_DYNFIELD_' . $cdf->getId() . '_CONTRIB} dynvalue: {INPUT_DYNFIELD_' . $cdf->getId() . '_CONTRIB}',
            'model_styles' => null,
            'model_parent' => \Galette\Entity\PdfModel::MAIN_MODEL
        ], \ArrayObject::ARRAY_AS_PROPS);
        $model = new \Galette\Entity\PdfInvoice($this->zdb, $this->preferences, $rs);

        $data = $this->dataAdherentOne() + [
            'info_field_' . $adf->getId() . '_1' => 'My value (:'
        ];
        $this->createMember($data);
        $model->setMember($this->adh);

        $this->createPdfContribution($cdf);
        $model->setContribution($this->contrib);

        $this->assertStringContainsString(
            '<td id="pdf_assoname"><strong id="asso_name">Galette</strong><br/></td>',
            $model->hheader
        );

        $this->assertMatchesRegularExpression(
            '/<td id="pdf_logo"><img src="@.+" width="129" height="60" alt="" \/><\/td>/',
            $model->hheader
        );

        $this->assertSame(
            '<div id="pdf_footer">
    Association Galette - Galette
Palais des Papes
Au milieu
84000 Avignon - France<br/>
    
</div>',
            $model->hfooter
        );

        $this->assertSame(
            'name: DURAND René login: arthur.hamon' .  $this->seed . ' birthdate: ' . $data['ddn_adh'] . ' dynlabel: Dynamic text field dynvalue: ' .
            'My value (: ' .
            '- enddate: ' . $this->contrib->end_date . ' amount: 92 (ninety-two) dynlabel: Dynamic date field ' .
            'dynvalue: 2020-12-03',
            $model->hbody
        );

        $legend = $model->getLegend();
        $this->assertCount(3, $legend);
        $this->assertArrayHasKey('main', $legend);
        $this->assertArrayHasKey('member', $legend);
        $this->assertArrayHasKey('contribution', $legend);

        $this->assertCount(12, $legend['main']['patterns']);
        $this->assertCount(28, $legend['member']['patterns']);
        $this->assertTrue(isset($legend['member']['patterns']['label_dynfield_' . $adf->getId() . '_adh']));
        $this->assertCount(14, $legend['contribution']['patterns']);
        $this->assertTrue(isset($legend['contribution']['patterns']['label_dynfield_' . $cdf->getId() . '_contrib']));
    }

    /**
     * Create test contribution in database
     *
     * @param DynamicField $cdf Contribution dynamic field
     *
     * @return void
     */
    protected function createPdfContribution(DynamicField $cdf): void
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
    public function testStorage(): void
    {
        $model = new \Galette\Entity\PdfInvoice($this->zdb, $this->preferences);

        $orig_title = $model->title;
        $this->assertSame('_T("Invoice") {CONTRIBUTION_YEAR}-{CONTRIBUTION_ID}', $orig_title);

        $model->title = 'Another test';
        $this->assertTrue($model->store());

        $model = new \Galette\Entity\PdfInvoice($this->zdb, $this->preferences);
        $this->assertSame('Another test', $model->title);
    }
}
