<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers;

use Galette\Controllers\Attributes\Route;
use Analog\Analog;
use Galette\Core\BehaviorConstants;
use Galette\Core\PreferencesSchema;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Advanced configuration controller
 *
 * Lists every preference, including those the settings form does not show, and
 * lets the superadmin change them one at a time.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AdvancedConfigController extends AbstractController
{
    /** How long a password confirmation is honoured, in seconds */
    public const int CONFIRM_LIFETIME = 900;

    /** Session key holding the moment the password was last confirmed */
    private const string CONFIRM_KEY = 'advanced_config_confirmed_at';

    /**
     * Advanced configuration page
     */
    #[Route(
        name: 'advancedConfig',
        pattern: '/advanced-config',
        methods: ['GET']
    )]
    public function advancedConfig(Response $response): Response
    {
        if (!$this->isConfirmed()) {
            return $this->askForPassword($response);
        }

        $params = [
            'page_title'    => _T('Advanced configuration'),
            'documentation' => 'usermanual/avancee.html#advanced-configuration',
            'entries'       => $this->getEntries(),
            'constants'     => BehaviorConstants::getStatus(),
        ];

        $this->view->render(
            $response,
            'pages/advanced_config.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Check the password protecting the page
     */
    #[Route(
        name: 'confirmAdvancedConfig',
        pattern: '/advanced-config/confirm',
        methods: ['POST']
    )]
    public function confirmAdvancedConfig(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();

        if (
            !password_verify(
                (string)($post['password'] ?? ''),
                (string)$this->preferences->pref_admin_pass
            )
        ) {
            Analog::log(
                'Wrong password given to reach the advanced configuration page.',
                Analog::WARNING
            );

            return $this->redirect(
                response: $response,
                redirect_url: $this->routeparser->urlFor('advancedConfig'),
                errors: [_T("Wrong password!")]
            );
        }

        $this->session->{self::CONFIRM_KEY} = time();

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('advancedConfig'));
    }

    /**
     * Has the password been confirmed recently enough?
     */
    private function isConfirmed(): bool
    {
        $confirmed_at = $this->session->{self::CONFIRM_KEY};

        return is_int($confirmed_at)
            && $confirmed_at + self::CONFIRM_LIFETIME > time();
    }

    /**
     * Render the password form standing in front of the page
     */
    private function askForPassword(Response $response): Response
    {
        $this->view->render(
            $response,
            'pages/advanced_config_confirm.html.twig',
            ['page_title' => _T('Advanced configuration')]
        );
        return $response;
    }

    /**
     * Store one preference
     */
    #[Route(
        name: 'saveAdvancedConfig',
        pattern: '/advanced-config',
        methods: ['POST']
    )]
    public function saveAdvancedConfig(Request $request, Response $response): Response
    {
        if (!$this->isConfirmed()) {
            return $this->redirect(
                response: $response,
                redirect_url: $this->routeparser->urlFor('advancedConfig'),
                errors: [_T("Please confirm your password again.")]
            );
        }

        $post = $request->getParsedBody();
        $name = (string)($post['name'] ?? '');

        $stored = $this->preferences->setValue(
            $name,
            $post['value'] ?? '',
            $this->login
        );

        return $this->redirect(
            response: $response,
            redirect_url: $this->routeparser->urlFor('advancedConfig'),
            successes: $stored ? [str_replace('%name', $name, _T("Preference '%name' has been stored."))] : [],
            errors: $stored ? [] : $this->preferences->getErrors()
        );
    }

    /**
     * Put one preference back to its default
     */
    #[Route(
        name: 'resetAdvancedConfig',
        pattern: '/advanced-config/reset',
        methods: ['POST']
    )]
    public function resetAdvancedConfig(Request $request, Response $response): Response
    {
        if (!$this->isConfirmed()) {
            return $this->redirect(
                response: $response,
                redirect_url: $this->routeparser->urlFor('advancedConfig'),
                errors: [_T("Please confirm your password again.")]
            );
        }

        $post = $request->getParsedBody();
        $name = (string)($post['name'] ?? '');

        $reset = $this->preferences->resetValue($name, $this->login);

        return $this->redirect(
            response: $response,
            redirect_url: $this->routeparser->urlFor('advancedConfig'),
            successes: $reset ? [str_replace('%name', $name, _T("Preference '%name' has been reset to its default."))] : [],
            errors: $reset ? [] : $this->preferences->getErrors()
        );
    }

    /**
     * Build what the page displays, one entry per preference
     *
     * @return array<int, array<string, mixed>>
     */
    private function getEntries(): array
    {
        $entries = [];

        foreach (PreferencesSchema::getAll() as $name => $schema) {
            $constant = PreferencesSchema::getConstant($name);
            $locked = $constant !== null && defined($constant);
            $sensitive = PreferencesSchema::isSensitive($name);

            //show what actually applies: a locked setting is served by its
            //constant, not by the value sitting in database. Read straight
            //from it rather than through getConfigValue(), which would report
            //the override again on every render.
            $value = $locked ? constant((string)$constant) : $this->preferences->$name;
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $entries[] = [
                'name'      => $name,
                'known'     => true,
                'type'      => $schema['type'],
                //a secret is never rendered, only whether one is set
                'value'     => $sensitive ? null : $value,
                'is_set'    => $value !== '' && $value !== null,
                'default'   => $schema['default'],
                //a secret is stored hashed, so comparing it to the shipped
                //default says nothing: it would read as modified forever
                'is_default' => !$locked && !$sensitive
                    && $this->isDefault($schema['type'], $value, $schema['default']),
                'sensitive' => $sensitive,
                'readonly'  => PreferencesSchema::isReadOnly($name),
                'locked_by' => $locked ? $constant : null,
                'min'       => $schema['min'] ?? null,
                'max'       => $schema['max'] ?? null,
            ];
        }

        //rows left in database by an older version or by a plugin
        foreach (array_diff($this->preferences->getFieldsNames(), array_keys(PreferencesSchema::getAll())) as $name) {
            $entries[] = [
                'name'  => $name,
                'known' => false,
                'value' => $this->preferences->$name,
            ];
        }

        return $entries;
    }

    /**
     * Is that value the one the schema declares as default?
     *
     * Compared loosely: values come back from database as strings, while
     * defaults are declared as the scalars they logically are.
     *
     * @param string $type    Preference type
     * @param mixed  $value   Current value
     * @param mixed  $default Declared default
     */
    private function isDefault(string $type, mixed $value, mixed $default): bool
    {
        if (is_bool($value) || is_bool($default)) {
            return (bool)$value === (bool)$default;
        }

        if ($type === PreferencesSchema::TYPE_COLOR) {
            //#ffffff and #FFFFFF are the same colour; validateValue() keeps
            //whichever case was typed, so only the comparison has to know
            return strcasecmp((string)$value, (string)$default) === 0;
        }

        return (string)$value === (string)$default;
    }
}
