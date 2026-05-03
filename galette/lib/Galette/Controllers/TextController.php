<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
     * @param ?string $lang Language
     * @param ?string $ref  Ref code
     */
    public function list(
        Response $response,
        Texts $texts,
        ?string $lang = null,
        ?string $ref = null
    ): Response {
        if ($lang === null) {
            $lang = $this->preferences->pref_lang;
        }
        if ($ref === null) {
            $ref = Texts::DEFAULT_REF;
        }

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
     */
    public function edit(Request $request, Response $response, Texts $texts): Response
    {
        $post = $request->getParsedBody();
        $error_detected = [];
        $success_detected = [];

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
            $error_detected[] = sprintf(
                _T('Email: \'%1$s\' has not been modified!'),
                $mtxt->tcomment,
            );
        } else {
            $success_detected[] = sprintf(
                _T('Email: \'%1$s\' has been successfully modified.'),
                $mtxt->tcomment
            );
        }

        return $this->redirect(
            response: $response,
            redirect_url: $this->routeparser->urlFor(
                'texts',
                [
                    'lang'  => $cur_lang,
                    'ref'   => $cur_ref
                ]
            ),
            successes: $success_detected,
            errors: $error_detected
        );
    }
}
