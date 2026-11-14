<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

use function Safe\define;
use function Safe\mb_convert_encoding;
use function Safe\preg_match;

/**
 * Galette tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Galette extends GaletteTestCase
{
    /**
     * Test gitVersion
     */
    public function testGitVersion(): void
    {
        $gitversion = \Galette\Core\Galette::gitVersion();
        $this->assertStringStartsWith(str_replace('-dev', '', GALETTE_VERSION), $gitversion);
        $this->assertSame(
            1,
            preg_match(
                '/-git-.+ \(\d{4}-\d{2}-\d{2}\)/',
                str_replace(GALETTE_VERSION, '', $gitversion)
            )
        );
    }

    /**
     * Test storing into session of various objects to detect serialization issues
     */
    public function testSerialization(): void
    {
        //global objects
        $login = new \Galette\Core\Login($this->zdb, $this->i18n);
        $this->session->login_test = $login;
        $this->assertInstanceOf(\Galette\Core\Login::class, $this->session->login_test);

        $mailing = new \Galette\Core\Mailing($this->preferences);
        $this->session->mailing_test = $mailing;
        $this->assertInstanceOf(\Galette\Core\Mailing::class, $this->session->mailing_test);

        $gaptcha = new \Galette\Core\Gaptcha($this->i18n);
        $this->session->gaptcha_test = $gaptcha;
        $this->assertInstanceOf(\Galette\Core\Gaptcha::class, $this->session->gaptcha_test);

        $plugin_install = new \Galette\Core\PluginInstall();
        $this->session->plugininstall_test = $plugin_install;
        $this->assertInstanceOf(\Galette\Core\PluginInstall::class, $this->session->plugininstall_test);

        $i18n = new \Galette\Core\I18n();
        $this->session->i18n_test = $i18n;
        $this->assertInstanceOf(\Galette\Core\I18n::class, $this->session->i18n_test);

        //entities
        $contribution = new \Galette\Entity\Contribution($this->zdb, $this->login);
        $this->session->contribution_test = $contribution;
        $this->assertInstanceOf(\Galette\Entity\Contribution::class, $this->session->contribution_test);

        $df = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, \Galette\DynamicFields\DynamicField::LINE);
        $this->session->df_filter_test = $df;
        $this->assertInstanceOf(\Galette\DynamicFields\Line::class, $this->session->df_filter_test);

        $member = new \Galette\Entity\Adherent($this->zdb);
        $this->session->member_test = $member;
        $this->assertInstanceOf(\Galette\Entity\Adherent::class, $this->session->member_test);

        $transaction = new \Galette\Entity\Transaction($this->zdb, $this->login);
        $this->session->transaction_test = $transaction;
        $this->assertInstanceOf(\Galette\Entity\Transaction::class, $this->session->transaction_test);

        //filters
        $contribution_filter = new \Galette\Filters\ContributionsList();
        $this->session->contribution_filter_test = $contribution_filter;
        $this->assertInstanceOf(\Galette\Filters\ContributionsList::class, $this->session->contribution_filter_test);

        $member_advanced_filter = new \Galette\Filters\AdvancedMembersList();
        $this->session->member_advanced_filter_test = $member_advanced_filter;
        $this->assertInstanceOf(\Galette\Filters\AdvancedMembersList::class, $this->session->member_advanced_filter_test);

        $member_filter = new \Galette\Filters\MembersList();
        $this->session->member_filter_test = $member_filter;
        $this->assertInstanceOf(\Galette\Filters\MembersList::class, $this->session->member_filter_test);

        $history_filter = new \Galette\Filters\HistoryList();
        $this->session->history_filter_test = $history_filter;
        $this->assertInstanceOf(\Galette\Filters\HistoryList::class, $this->session->history_filter_test);

        $mailing_filter = new \Galette\Filters\MailingsList();
        $this->session->mailing_filter_test = $mailing_filter;
        $this->assertInstanceOf(\Galette\Filters\MailingsList::class, $this->session->mailing_filter_test);

        $saved_filter = new \Galette\Filters\SavedSearchesList();
        $this->session->saved_filter_test = $saved_filter;
        $this->assertInstanceOf(\Galette\Filters\SavedSearchesList::class, $this->session->saved_filter_test);

        $transaction_filter = new \Galette\Filters\TransactionsList();
        $this->session->transaction_filter_test = $transaction_filter;
        $this->assertInstanceOf(\Galette\Filters\TransactionsList::class, $this->session->transaction_filter_test);
    }

    /**
     * Test isNightly
     */
    public function testIsNightly(): void
    {
        $this->assertFalse(\Galette\Core\Galette::isNightly());
    }

    /**
     * Test isSqlDebugEnabled
     *
     * Neither the constant nor debug mode: nothing to dump.
     */
    public function testIsSqlDebugEnabled(): void
    {
        $this->assertFalse(defined('GALETTE_SQL_DEBUG'));
        $this->assertFalse(\Galette\Core\Galette::isDebugEnabled());
        $this->assertFalse(\Galette\Core\Galette::isSqlDebugEnabled());
    }

    /**
     * Declared to false, the dump stays off
     *
     * The value is what counts, not the mere existence of the constant.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSqlDebugDeclaredFalse(): void
    {
        define('GALETTE_SQL_DEBUG', value: false);
        $this->assertFalse(\Galette\Core\Galette::isSqlDebugEnabled());
    }

    /**
     * Declared to true, every query is dumped
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSqlDebugDeclaredTrue(): void
    {
        define('GALETTE_SQL_DEBUG', value: true);
        $this->assertTrue(\Galette\Core\Galette::isSqlDebugEnabled());
    }

    /**
     * Only well-formed demonstration accounts are kept
     */
    public function testFilterDemoAccounts(): void
    {
        $this->assertSame([], \Galette\Core\Galette::filterDemoAccounts(accounts: null));
        $this->assertSame([], \Galette\Core\Galette::filterDemoAccounts('admin'));

        $this->assertSame(
            [
                ['login' => 'admin', 'password' => 'secret', 'label' => 'Super administrator'],
                ['login' => 'member', 'password' => 'secret', 'label' => 'member'],
                ['login' => 'staff', 'password' => 'secret', 'label' => 'staff'],
            ],
            \Galette\Core\Galette::filterDemoAccounts([
                ['login' => 'admin', 'password' => 'secret', 'label' => 'Super administrator'],
                ['login' => 'member', 'password' => 'secret'],
                ['login' => 'staff', 'password' => 'secret', 'label' => ' '],
                ['login' => 'nopassword'],
                ['login' => 'emptypassword', 'password' => ''],
                ['login' => ' ', 'password' => 'secret'],
                ['password' => 'secret'],
                ['login' => 42, 'password' => 'secret'],
                'admin',
            ])
        );
    }

    /**
     * Declared accounts are not offered out of demonstration mode
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDemoAccountsOnlyInDemoMode(): void
    {
        $this->assertSame([], \Galette\Core\Galette::getDemoAccounts());

        define('GALETTE_DEMO_ACCOUNTS', [['login' => 'admin', 'password' => 'admin']]);
        $this->assertFalse(\Galette\Core\Galette::isDemo());
        $this->assertSame([], \Galette\Core\Galette::getDemoAccounts());
    }

    /**
     * Test jsonDecode
     */
    public function testJsonDecode(): void
    {
        $json = '{"key1": "value1", "key2": "value2"}';
        $decoded = \Galette\Core\Galette::jsonDecode($json);
        $this->assertEquals(
            [
                'key1' => 'value1',
                'key2' => 'value2',
            ],
            $decoded
        );

        $invalid_json = '{"key1": "value1", "key2": "value2"'; // missing closing brace
        $this->expectExceptionMessage('JSON decode error: Syntax error');
        \Galette\Core\Galette::jsonDecode($invalid_json);
    }

    /**
     * Test jsonEncode
     */
    public function testJsonEncode(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];
        $encoded = \Galette\Core\Galette::jsonEncode($data);
        $this->assertJsonStringEqualsJsonString(
            '{"key1":"value1","key2":"value2"}',
            $encoded
        );

        // Test with an invalid data type
        $data['key3'] = mb_convert_encoding('éè', 'ISO-8859-1', 'UTF-8'); //invalid UTF-8 characters
        $this->expectExceptionMessage('JSON encode error: Malformed UTF-8 characters, possibly incorrectly encoded');
        \Galette\Core\Galette::jsonEncode($data);
    }
}
