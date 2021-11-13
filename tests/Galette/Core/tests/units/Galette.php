<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette tests
 *
 * PHP version 5
 *
 * Copyright © 2021 The Galette Team
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
 * @copyright 2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2021-11-23
 */

namespace Galette\Core\test\units;

use Galette\GaletteTestCase;

/**
 * Galette tests class
 *
 * @category  Core
 * @name      Galette
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2021-11-23
 */
class Galette extends GaletteTestCase
{
    /**
     * Test gitVersion
     *
     * @return void
     */
    public function testGitVersion()
    {
        $gitversion = \Galette\Core\Galette::gitVersion();
        $this->boolean(str_starts_with(
            $gitversion,
            GALETTE_VERSION
        ))->isTrue();
        $this->integer(
            preg_match(
                '/-git-\w (\d{4}-\d{2}-\d{2})/',
                str_replace(GALETTE_VERSION, '', $gitversion)
            )
        );
    }

    /**
     * Test storign into session of various objects to detect serialization issues
     *
     * @return void
     */
    public function testSerialization()
    {
        //global objects
        $login = new \Galette\Core\Login($this->zdb, $this->i18n);
        $this->session->login_test = $login;
        $this->object($this->session->login_test)->isInstanceOf(\Galette\Core\Login::class);

        $mailing = new \Galette\Core\Mailing($this->preferences);
        $this->session->mailing_test = $mailing;
        $this->object($this->session->mailing_test)->isInstanceOf(\Galette\Core\Mailing::class);

        $gaptcha = new \Galette\Core\Gaptcha($this->i18n);
        $this->session->gaptcha_test = $gaptcha;
        $this->object($this->session->gaptcha_test)->isInstanceOf(\Galette\Core\Gaptcha::class);

        $plugin_install = new \Galette\Core\PluginInstall();
        $this->session->plugininstall_test = $plugin_install;
        $this->object($this->session->plugininstall_test)->isInstanceOf(\Galette\Core\PluginInstall::class);

        $i18n = new \Galette\Core\I18n();
        $this->session->i18n_test = $i18n;
        $this->object($this->session->i18n_test)->isInstanceOf(\Galette\Core\I18n::class);

        //entities
        $contribution = new \Galette\Entity\Contribution($this->zdb, $this->login);
        $this->session->contribution_test = $contribution;
        $this->object($this->session->contribution_test)->isInstanceOf(\Galette\Entity\Contribution::class);

        $df = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, \Galette\DynamicFields\DynamicField::LINE);
        $this->session->df_filter_test = $df;
        $this->object($this->session->df_filter_test)->isInstanceOf(\Galette\DynamicFields\Line::class);

        $member = new \Galette\Entity\Adherent($this->zdb);
        $this->session->member_test = $member;
        $this->object($this->session->member_test)->isInstanceOf(\Galette\Entity\Adherent::class);

        $transaction = new \Galette\Entity\Transaction($this->zdb, $this->login);
        $this->session->transaction_test = $transaction;
        $this->object($this->session->transaction_test)->isInstanceOf(\Galette\Entity\Transaction::class);

        //filters
        $contribution_filter = new \Galette\Filters\ContributionsList();
        $this->session->contribution_filter_test = $contribution_filter;
        $this->object($this->session->contribution_filter_test)->isInstanceOf(\Galette\Filters\ContributionsList::class);

        $member_advanced_filter = new \Galette\Filters\AdvancedMembersList();
        $this->session->member_advanced_filter_test = $member_advanced_filter;
        $this->object($this->session->member_advanced_filter_test)->isInstanceOf(\Galette\Filters\AdvancedMembersList::class);

        $member_filter = new \Galette\Filters\MembersList();
        $this->session->member_filter_test = $member_filter;
        $this->object($this->session->member_filter_test)->isInstanceOf(\Galette\Filters\MembersList::class);

        $history_filter = new \Galette\Filters\HistoryList();
        $this->session->history_filter_test = $history_filter;
        $this->object($this->session->history_filter_test)->isInstanceOf(\Galette\Filters\HistoryList::class);

        $mailing_filter = new \Galette\Filters\MailingsList();
        $this->session->mailing_filter_test = $mailing_filter;
        $this->object($this->session->mailing_filter_test)->isInstanceOf(\Galette\Filters\MailingsList::class);

        $saved_filter = new \Galette\Filters\SavedSearchesList();
        $this->session->saved_filter_test = $saved_filter;
        $this->object($this->session->saved_filter_test)->isInstanceOf(\Galette\Filters\SavedSearchesList::class);

        $transaction_filter = new \Galette\Filters\TransactionsList();
        $this->session->transaction_filter_test = $transaction_filter;
        $this->object($this->session->transaction_filter_test)->isInstanceOf(\Galette\Filters\TransactionsList::class);
    }
}
