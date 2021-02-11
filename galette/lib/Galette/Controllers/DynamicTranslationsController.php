<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette dynamic translations controller
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
 * @category  Controllers
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-02
 */

namespace Galette\Controllers;

use Throwable;
use Slim\Http\Request;
use Slim\Http\Response;
use Galette\Core\L10n;
use Analog\Analog;

/**
 * Galette dynamic translations controller
 *
 * @category  Controllers
 * @name      DynamicTranslationsController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-02
 */

class DynamicTranslationsController extends AbstractController
{
    /**
     * Dynamic fields translations
     *
     * @param Request  $request   PSR Request
     * @param Response $response  PSR Response
     * @param string   $text_orig Original translatext
     *
     * @return Response
     */
    public function dynamicTranslations(Request $request, Response $response, string $text_orig = null): Response
    {
        if ($text_orig == null && isset($_GET['text_orig'])) {
            $text_orig = $_GET['text_orig'];
        }

        $params = [
            'page_title'    => _T("Translate labels")
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
                    $orig[] = $row->text_orig;
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

        // display page
        $this->view->render(
            $response,
            'traduire_libelles.tpl',
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
        $post['text_orig'] = htmlspecialchars($post['text_orig'], ENT_QUOTES);
        $error_detected = [];

        if (isset($post['trans']) && isset($post['text_orig'])) {
            if (isset($post['new']) && $post['new'] == 'true') {
                //create translation if it does not exists yet
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
            ->withHeader('Location', $this->router->pathFor(
                'dynamicTranslations',
                ['text_orig' => $post['text_orig']]
            ));
    }
}
