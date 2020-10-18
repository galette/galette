<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * CheckAcls tests
 *
 * PHP version 5
 *
 * Copyright © 2016 The Galette Team
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
 * @category  Core
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2020-05-10
 */

namespace Galette\Middleware\test\units;

use atoum;

/**
 * CheckAcls tests class
 *
 * @category  Core
 * @name      CheckAcls
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-05-10
 */
class CheckAcls extends atoum
{
    private $container;

    /**
     * Set up tests
     *
     * @param stgring $testMethod Method tested
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
    {
        $app = new \Slim\App();
        require GALETTE_ROOT . 'includes/core_acls.php';
        $container = $app->getContainer();
        //$this->acls = $core_acls;
        $container['acls'] = $core_acls;
        $container['view'] = new \stdClass();
        $container['flash'] = new \stdClass();
        $this->container = $container;
    }

    /**
     * ACL provider
     *
     * @return array
     */
    protected function aclsProvider()
    {
        return [
            ['doPreferences', 'admin'],
            ['doPreferencesSthing', 'admin'],
            ['removeSthing', 'staff'],
            ['doremoveSthing', 'staff'],
            ['anyDynamicField', 'admin'],
            //existing routes as of Galette 0.9.4dev before regexp are supported
            ['preferences', 'admin'],
            ['store-preferences', 'admin'],
            ['testEmail', 'admin'],
            ['dashboard', 'member'],
            ['sysinfos', 'staff'],
            ['charts', 'staff'],
            ['plugins', 'admin'],
            ['pluginInitDb', 'admin'],
            ['pluginsActivation', 'admin'],
            ['history', 'staff'],
            ['history_filter', 'staff'],
            ['flushHistory', 'staff'],
            ['doFlushHistory', 'staff'],
            ['members', 'groupmanager'],
            ['filter-memberslist', 'groupmanager'],
            ['advanced-search', 'groupmanager'],
            ['batch-memberslist', 'groupmanager'],
            ['mailing', 'staff'],
            ['doMailing', 'staff'],
            ['mailingPreview', 'staff'],
            ['previewAttachment', 'staff'],
            ['mailingRecipients', 'staff'],
            ['csv-memberslist', 'staff'],
            ['groups', 'groupmanager'],
            ['me', 'member'],
            ['member', 'member'],
            ['pdf-members-cards', 'member'],
            ['pdf-members-labels', 'groupmanager'],
            ['mailings', 'staff'],
            ['mailings_filter', 'staff'],
            ['removeMailing', 'staff'],
            ['doRemoveMailing', 'staff'],
            ['contributions', 'member'],
            ['transactions', 'staff'],
            ['payments_filter', 'member'],
            ['addMember', 'groupmanager'],
            ['editMember', 'member'],
            ['impersonate', 'superadmin'],
            ['unimpersonate', 'member'],
            ['reminders', 'staff'],
            ['doReminders', 'staff'],
            ['reminders-filter', 'staff'],
            ['export', 'staff'],
            ['doExport', 'staff'],
            ['removeCsv', 'staff'],
            ['doRemoveCsv', 'staff'],
            ['getCsv', 'staff'],
            ['import', 'staff'],
            ['doImport', 'staff'],
            ['importModel', 'staff'],
            ['getImportModel', 'staff'],
            ['storeImportModel', 'staff'],
            ['uploadImportFile', 'staff'],
            ['pdfModels', 'staff'],
            ['titles', 'staff'],
            ['removeTitle', 'staff'],
            ['doRemoveTitle', 'staff'],
            ['editTitle', 'staff'],
            ['texts', 'staff'],
            ['changeText', 'staff'],
            ['editTransaction', 'staff'],
            ['addTransaction', 'staff'],
            ['doAddTransaction', 'staff'],
            ['doEditTransaction', 'staff'],
            ['addContribution', 'staff'],
            ['doAddContribution', 'staff'],
            ['editContribution', 'staff'],
            ['doEditContribution', 'staff'],
            ['contributionDates', 'staff'],
            ['contributionMembers', 'staff'],
            ['attendance_sheet_details', 'groupmanager'],
            ['attendance_sheet', 'groupmanager'],
            ['entitleds', 'staff'],
            ['editEntitled', 'staff'],
            ['removeEntitled', 'staff'],
            ['doRemoveEntitled', 'staff'],
            ['dynamicTranslations', 'staff'],
            ['editDynamicTranslation', 'staff'],
            ['printContribution', 'member'],
            ['attach_contribution', 'staff'],
            ['detach_contribution', 'staff'],
            ['removeContribution', 'staff'],
            ['removeContributions', 'staff'],
            ['pdf_groups', 'groupmanager'],
            ['ajax_group', 'groupmanager'],
            ['ajax_groups', 'groupmanager'],
            ['ajax_groupname_unique', 'groupmanager'],
            ['ajax_groups_reorder', 'staff'],
            ['add_group', 'staff'],
            ['removeGroup', 'staff'],
            ['doRemoveGroup', 'staff'],
            ['doEditGroup', 'groupmanager'],
            ['adhesionForm', 'member'],
            ['removeMember', 'staff'],
            ['removeMembers', 'staff'],
            ['doRemoveMember', 'staff'],
            ['doRemoveContribution', 'staff'],
            ['configureCoreFields', 'admin'],
            ['configureDynamicFields', 'admin'],
            ['storeCoreFieldsConfig', 'admin'],
            ['addDynamicField', 'admin'],
            ['editDynamicField', 'admin'],
            ['doAddDynamicField', 'admin'],
            ['doEditDynamicField', 'admin'],
            ['moveDynamicField', 'admin'],
            ['removeDynamicField', 'admin'],
            ['doRemoveDynamicField', 'admin'],
            ['photoDnd', 'staff'],
            ['ajaxMembers', 'groupmanager'],
            ['ajaxGroupMembers', 'staff'],
            ['getDynamicFile', 'member'],
            ['fakeData', 'superadmin'],
            ['doFakeData', 'superadmin'],
            ['adminTools', 'superadmin'],
            ['doAdminTools', 'superadmin'],
            ['telemetryInfos', 'admin'],
            ['telemetrySend', 'admin'],
            ['setRegistered', 'admin'],
            ['masschangeMembers', 'groupmanager'],
            ['massstoremembers', 'groupmanager'],
            ['masschangeMembersReview', 'groupmanager'],
            ['duplicateMember', 'staff'],
            ['paymentTypes', 'staff'],
            ['removePaymentType', 'staff'],
            ['doRemovePaymentType', 'staff'],
            ['editPaymentType', 'staff'],
            ['searches', 'member'],
            ['removeSearch', 'member'],
            ['removeSearches', 'member'],
            ['doRemoveSearch', 'member'],
            ['loadSearch', 'member']
        ];
    }

    /**
     * Test new PasswordImage generation
     *
     * @dataProvider aclsProvider
     *
     * @param string $name     Route name
     * @param string $expected Expected ACL
     *
     * @return void
     */
    public function testGetAclFor($name, $expected)
    {
        $this
            ->given($check = $this->newTestedInstance($this->container))
            ->if($acl_name = $this->testedInstance->getAclFor($name))
            ->then
                ->string($acl_name)->isIdenticalTo(
                    $expected,
                    sprintf(
                        '%1$s should be accesible to %2$s but is to %3$s',
                        $name,
                        $expected,
                        $acl_name
                    )
                );
    }
}
