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

use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Entity\Texts;

/**
 * Galette texts controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class TextController extends AbstractController
{
    /**
     * List texts
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param ?string  $lang     Language
     * @param ?string  $ref      Ref code
     *
     * @return Response
     */
    public function list(Request $request, Response $response, ?string $lang = null, ?string $ref = null): Response
    {
        if ($lang === null) {
            $lang = $this->preferences->pref_lang;
        }
        if ($ref === null) {
            $ref = Texts::DEFAULT_REF;
        }

        $texts = new Texts(
            $this->preferences,
            $this->routeparser
        );

        $texts->setCurrent($ref);
        $mtxt = $texts->getTexts($ref, $lang);

        // display page
        $this->view->render(
            $response,
            'pages/configuration_texts.html.twig',
            [
                'page_title'        => _T("Emails content"),
                'texts'             => $texts,
                'reflist'           => $texts->getRefs($lang),
                'langlist'          => $this->i18n->getList(),
                'cur_lang'          => $lang,
                'cur_lang_name'     => $this->i18n->getNameFromId($lang),
                'cur_ref'           => $ref,
                'mtxt'              => $mtxt,
                'documentation'     => 'usermanual/configuration.html#emails-contents'
            ]
        );
        return $response;
    }

    /**
     * Change texts
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function change(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        return $response
            ->withStatus(301)
            ->withHeader(
                'Location',
                $this->routeparser->urlFor(
                    'texts',
                    [
                        'lang'  => $post['sel_lang'],
                        'ref'   => $post['sel_ref']
                    ]
                )
            );
    }

    /**
     * Edit text
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function edit(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $texts = new Texts($this->preferences, $this->routeparser);

        //set the language
        $cur_lang = $post['cur_lang'];
        //set the text entry
        $cur_ref = $post['cur_ref'];

        $mtxt = $texts->getTexts($cur_ref, $cur_lang);
        $res = $texts->setTexts(
            $cur_ref,
            $cur_lang,
            $post['text_subject'],
            $post['text_body']
        );

        if (!$res) {
            $this->flash->addMessage(
                'error_detected',
                preg_replace(
                    '(%s)',
                    $mtxt->tcomment,
                    _T("Email: '%s' has not been modified!")
                )
            );
        } else {
            $this->flash->addMessage(
                'success_detected',
                preg_replace(
                    '(%s)',
                    $mtxt->tcomment,
                    _T("Email: '%s' has been successfully modified.")
                )
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader(
                'Location',
                $this->routeparser->urlFor(
                    'texts',
                    [
                        'lang'  => $cur_lang,
                        'ref'   => $cur_ref
                    ]
                )
            );
    }
}
