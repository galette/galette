<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers;

use Throwable;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Analog\Analog;

use function Safe\preg_replace;

/**
 * Galette dynamic translations controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class DynamicTranslationsController extends AbstractController
{
    /**
     * Dynamic fields translations
     *
     * @param ?string $text_orig Original text
     */
    public function dynamicTranslations(Request $request, Response $response, ?string $text_orig = null): Response
    {
        if ($text_orig == null && isset($_GET['text_orig'])) {
            $text_orig = $_GET['text_orig'];
        }

        return $this->dynamicTranslation($request, $response, $text_orig ?? '');
    }

    /**
     * Dynamic fields translations
     *
     * @param string $text_orig_sum Original text MD5 sum
     */
    public function dynamicTranslation(Request $request, Response $response, string $text_orig_sum): Response
    {
        $params = [
            'page_title'    => _T("Labels translation"),
            'documentation' => 'usermanual/configuration.html#labels-translation'
        ];

        try {
            $orig = $this->l10n->getStringsToTranslate();
            $text_exists = false;

            if ($text_orig_sum === '') {
                $text_orig_sum = array_key_first($orig);
            }

            if (isset($orig[$text_orig_sum]) || isset($orig[md5((string)$text_orig_sum)])) {
                $sum = isset($orig[$text_orig_sum]) ? $text_orig_sum : md5((string)$text_orig_sum);
                $text_exists = true;
                $text_trans = $this->l10n->getDynamicTranslations($sum);
                $text_orig = $orig[$sum];
            } else {
                $text_trans = $this->l10n->getDynamicTranslations($text_orig_sum);
                $text_orig = $text_orig_sum;
            }

            $params['exists'] = $text_exists;
            $params['orig'] = $orig;
            $params['trans'] = $text_trans;
            $params['text_orig'] = $text_orig;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred retrieving l10n entries | '
                . $e->getMessage(),
                Analog::WARNING
            );
        }

        $params['mode'] = $this->isAjax($request) ? 'ajax' : '';

        // display page
        $this->view->render(
            $response,
            'pages/configuration_dynamic_translations.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Do dynamic fields translations
     */
    public function doDynamicTranslations(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        if (isset($post['redirect_uri'])) {
            $redirect_url = $post['redirect_uri'];
            unset($post['redirect_uri']);
        } else {
            $redirect_url = $this->routeparser->urlFor(
                'dynamicTranslations',
                [],
                ['text_orig' => $post['text_orig']]
            );
        }
        $error_detected = [];
        $success_detected = [];

        if (isset($post['trans']) && isset($post['text_orig'])) {
            if (isset($post['new']) && $post['new'] == 'true') {
                //create translation if it does not exist yet
                $res = $this->l10n->addDynamicTranslation(
                    $post['text_orig']
                );
                if (!$res) {
                    $error_detected[] = preg_replace(
                        [
                            '/%label/',
                            '/%lang/'
                        ],
                        [
                            $post['text_orig'],
                            $this->i18n->getLongID()
                        ],
                        _T("An error occurred saving label `%label` for language `%lang`")
                    );
                }
            }

            // Validate form
            foreach ($post as $key => $value) {
                if (str_starts_with((string)$key, 'text_trans_')) {
                    $trans_lang = substr((string)$key, 11);
                    $trans_lang = str_replace('_utf8', '.utf8', $trans_lang);
                    $res = $this->l10n->updateDynamicTranslation(
                        $post['text_orig'],
                        $trans_lang,
                        $value
                    );
                    if (!$res) {
                        $error_detected[] = preg_replace(
                            [
                                '/%label/',
                                '/%lang/'
                            ],
                            [
                                $post['text_orig'],
                                $trans_lang
                            ],
                            _T("An error occurred saving label `%label` for language `%lang`")
                        );
                    }
                }
            }

            if (count($error_detected) === 0) {
                $success_detected[] = _T("Labels has been successfully translated!");
            }
        }

        return $this->redirect(
            response: $response,
            redirect_url: $redirect_url,
            successes: $success_detected,
            errors: $error_detected
        );
    }
}
