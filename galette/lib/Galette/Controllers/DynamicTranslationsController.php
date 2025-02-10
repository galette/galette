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

namespace Galette\Controllers;

use Throwable;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Core\L10n;
use Analog\Analog;

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
     * @param Request  $request   PSR Request
     * @param Response $response  PSR Response
     * @param ?string  $text_orig Original text
     *
     * @return Response
     */
    public function dynamicTranslations(Request $request, Response $response, ?string $text_orig = null): Response
    {
        if ($text_orig == null && isset($_GET['text_orig'])) {
            $text_orig = $_GET['text_orig'];
        }

        $params = [
            'page_title'    => _T("Translate labels"),
            'documentation' => 'usermanual/configuration.html#labels-translation'
        ];

        $nb_fields = 0;
        try {
            $select = $this->zdb->select(L10n::TABLE);
            $select->columns(
                array('nb' => new \Laminas\Db\Sql\Expression('COUNT(text_orig)'))
            );
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $nb_fields = $result->nb;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred counting l10n entries | ' .
                $e->getMessage(),
                Analog::WARNING
            );
        }

        if (is_numeric($nb_fields) && $nb_fields > 0) {
            try {
                $select = $this->zdb->select(L10n::TABLE);
                $select->quantifier('DISTINCT')->columns(
                    array('text_orig')
                )->order('text_orig');

                $all_texts = $this->zdb->execute($select);

                $orig = array();
                foreach ($all_texts as $idx => $row) {
                    $orig[] = $row->text_orig ?? '';
                }
                $exists = true;
                if ($text_orig == '') {
                    $text_orig = $orig[0];
                } elseif (!in_array($text_orig, $orig)) {
                    $exists = false;
                    $this->flash->addMessage(
                        'error_detected',
                        str_replace(
                            '%s',
                            $text_orig,
                            _T("No translation for '%s'!<br/>Please fill and submit above form to create it.")
                        )
                    );
                }

                $trans = array();
                /**
                 * FIXME : it would be faster to get all translations at once
                 * for a specific string
                 */
                foreach ($this->i18n->getList() as $l) {
                    $text_trans = $this->l10n->getDynamicTranslation($text_orig, $l->getLongID());
                    $lang_name = $l->getName();
                    $trans[] = array(
                        'key'  => $l->getLongID(),
                        'name' => ucwords($lang_name),
                        'text' => $text_trans
                    );
                }

                $params['exists'] = $exists;
                $params['orig'] = $orig;
                $params['trans'] = $trans;
            } catch (Throwable $e) {
                Analog::log(
                    'An error occurred retrieving l10n entries | ' .
                    $e->getMessage(),
                    Analog::WARNING
                );
            }
        }

        $params['text_orig'] = $text_orig;

        $params['mode'] = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest' ? 'ajax' : '';

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
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
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
                ['text_orig' => $post['text_orig']]
            );
        }
        $error_detected = [];

        if (isset($post['trans']) && isset($post['text_orig'])) {
            if (isset($post['new']) && $post['new'] == 'true') {
                //create translation if it does not exist yet
                $res = $this->l10n->addDynamicTranslation(
                    $post['text_orig']
                );
                if (!$res) {
                    $error_detected[] = preg_replace(
                        array(
                            '/%label/',
                            '/%lang/'
                        ),
                        array(
                            $post['text_orig'],
                            $this->i18n->getLongID()
                        ),
                        _T("An error occurred saving label `%label` for language `%lang`")
                    );
                }
            }

            // Validate form
            foreach ($post as $key => $value) {
                if (substr($key, 0, 11) == 'text_trans_') {
                    $trans_lang = substr($key, 11);
                    $trans_lang = str_replace('_utf8', '.utf8', $trans_lang);
                    $res = $this->l10n->updateDynamicTranslation(
                        $post['text_orig'],
                        $trans_lang,
                        $value
                    );
                    if (!$res) {
                        $error_detected[] = preg_replace(
                            array(
                                '/%label/',
                                '/%lang/'
                            ),
                            array(
                                $post['text_orig'],
                                $trans_lang
                            ),
                            _T("An error occurred saving label `%label` for language `%lang`")
                        );
                    }
                }
            }

            if (count($error_detected)) {
                foreach ($error_detected as $err) {
                    $this->flash->addMessage(
                        'error_detected',
                        $err
                    );
                }
            } else {
                $this->flash->addMessage(
                    'success_detected',
                    _T("Labels has been sucessfully translated!")
                );
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $redirect_url);
    }
}
