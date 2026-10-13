<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Core\L10n;
use Galette\Tests\GaletteRoutingTestCase;

/**
 * Dynamic translations controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DynamicTranslationsControllerTest extends GaletteRoutingTestCase
{
    protected int $seed = 20261013190000;

    /**
     * Count stored translations for a text
     *
     * @param string $text Original text
     */
    private function countStored(string $text): int
    {
        $select = $this->zdb->select(L10n::TABLE);
        $select->where(['text_orig_sum' => md5($text)]);
        return $this->zdb->execute($select)->count();
    }

    /**
     * Core labels translations are displayed read-only
     */
    public function testCoreLabelIsReadOnly(): void
    {
        $this->logSuperAdmin();

        $request = $this->createRequest('dynamicTranslations', query_params: ['text_orig' => 'annual fee']);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);

        $body = (string)$test_response->getBody();
        //page is about this single label, as the modal is
        $this->assertStringContainsString('<title>Translate &#039;annual fee&#039; - Galette</title>', $body);
        $this->assertStringContainsString('data-modal-readonly', $body);
        $this->assertStringContainsString('data-modal-title="Translate &#039;annual fee&#039;"', $body);
        $this->assertStringContainsString('value="Jährlicher Beitrag" readonly="readonly"', $body);
        //nothing can be submitted
        $this->assertStringNotContainsString('name="text_trans_', $body);
        $this->assertStringNotContainsString('name="trans"', $body);
        $this->assertStringNotContainsString('No translation for', $body);
    }

    /**
     * Core labels translations cannot be stored, even with a forged request
     */
    public function testCoreLabelCannotBeSaved(): void
    {
        $this->logSuperAdmin();

        $request = $this->createRequest('editDynamicTranslation', method: 'POST')
            ->withParsedBody([
                'trans' => 'true',
                'new' => 'true',
                'text_orig' => 'annual fee',
                'text_trans_fr_FR_utf8' => 'cotisation pirate'
            ]);
        $test_response = $this->app->handle($request);

        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('dynamicTranslations', [], ['text_orig' => 'annual fee'])]],
            $test_response->getHeaders()
        );
        $this->expectFlashData(
            ['error_detected' => ['Label `annual fee` is translated by Galette, it cannot be translated here.']]
        );
        $this->assertSame(0, $this->countStored('annual fee'));
    }

    /**
     * User labels translations are editable and stored
     */
    public function testUserLabelIsEditable(): void
    {
        $this->logSuperAdmin();
        $text = 'A user label for test';

        $request = $this->createRequest('dynamicTranslations', query_params: ['text_orig' => $text]);
        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);

        $body = (string)$test_response->getBody();
        $this->assertStringNotContainsString('data-modal-readonly', $body);
        $this->assertStringNotContainsString('readonly="readonly"', $body);
        $this->assertStringContainsString('name="text_trans_fr_FR.utf8"', $body);
        $this->assertStringContainsString('No translation for', $body);
        $this->assertStringContainsString('When a translation is empty, the original text is displayed.', $body);

        $request = $this->createRequest('editDynamicTranslation', method: 'POST')
            ->withParsedBody([
                'trans' => 'true',
                'new' => 'true',
                'text_orig' => $text,
                'text_trans_fr_FR_utf8' => 'Un libellé utilisateur de test'
            ]);
        $test_response = $this->app->handle($request);

        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['success_detected' => ['Labels has been successfully translated!']]);
        $this->assertSame(count($this->i18n->getList()), $this->countStored($text));
        $this->assertSame(
            'Un libellé utilisateur de test',
            $this->container->get(L10n::class)->getDynamicTranslation($text, 'fr_FR.utf8')
        );
    }

    /**
     * Page can be displayed when there is nothing to translate yet
     */
    public function testNothingToTranslate(): void
    {
        $this->logSuperAdmin();
        $this->zdb->execute($this->zdb->delete(L10n::TABLE));

        $test_response = $this->app->handle($this->createRequest('dynamicTranslations'));
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('No fields to translate.', $body);
        $this->assertStringContainsString('<title>Labels translation - Galette</title>', $body);
    }
}
