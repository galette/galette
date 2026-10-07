<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers;

use Galette\Controllers\Attributes\Route;
use Galette\Core\AuthThrottle;
use Galette\Core\BehaviorConstants;
use Galette\Core\FeatureFlagManager;
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
            'previews'      => $this->getPreviews(),
            'previews_locked' => $this->getFeatureFlags()->isLockedByConstant(),
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
    public function confirmAdvancedConfig(Request $request, Response $response, AuthThrottle $throttle): Response
    {
        $post = $request->getParsedBody();

        $error = $this->checkSuperAdminPassword(
            (string)($post['password'] ?? ''),
            $throttle,
            'Wrong password given to reach the advanced configuration page.'
        );
        if ($error !== null) {
            return $this->redirect(
                response: $response,
                redirect_url: $this->routeparser->urlFor('advancedConfig'),
                errors: [$error]
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
            successes: $stored ? [sprintf(
                //TRANS: parameter is the preference name
                _T('Preference \'%1$s\' has been stored.'),
                $name
            )] : [],
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
            successes: $reset ? [sprintf(
                //TRANS: parameter is the preference name
                _T('Preference \'%1$s\' has been reset to its default.'),
                $name
            )] : [],
            errors: $reset ? [] : $this->preferences->getErrors()
        );
    }

    /**
     * Turn a preview feature on or off
     */
    #[Route(
        name: 'saveFeatureFlagAdvancedConfig',
        pattern: '/advanced-config/feature',
        methods: ['POST']
    )]
    public function saveFeatureFlagAdvancedConfig(Request $request, Response $response): Response
    {
        if (!$this->isConfirmed()) {
            return $this->redirect(
                response: $response,
                redirect_url: $this->routeparser->urlFor('advancedConfig'),
                errors: [_T("Please confirm your password again.")]
            );
        }

        $post = $request->getParsedBody();
        $flag = strtolower((string)($post['flag'] ?? ''));
        $turn_on = ($post['value'] ?? '0') === '1';
        $flags = $this->getFeatureFlags();
        $redirect_url = $this->routeparser->urlFor('advancedConfig') . '#previews';

        try {
            $change = $turn_on ? $flags->computeTurnOn($flag) : $flags->computeTurnOff($flag);
        } catch (\DomainException $e) {
            $error = match ($e->getCode()) {
                FeatureFlagManager::ERR_LOCKED => _T("Preview features are set by the GALETTE_FEATURE_FLAGS constant in behavior.inc.php, which takes precedence."),
                FeatureFlagManager::ERR_DEV_DEPENDENCY => _T("This feature requires another one that is still in development."),
                default => _T("This is not a preview feature."),
            };
            return $this->redirect(
                response: $response,
                redirect_url: $redirect_url,
                errors: [$error]
            );
        }

        if (!$this->preferences->storeFeatureFlags($change['flags'])) {
            return $this->redirect(
                response: $response,
                redirect_url: $redirect_url,
                errors: [_T("An error occurred while storing preview features.")]
            );
        }

        $changed = $turn_on ? $change['added'] : $change['removed'];
        if ($changed !== []) {
            $this->history->add(
                $turn_on ? _T("Preview feature turned on") : _T("Preview feature turned off"),
                implode(', ', $changed)
            );
        }

        $labels = array_map(
            fn(string $changed_flag): string => (string)$flags->getLabel($changed_flag),
            $changed
        );
        return $this->redirect(
            response: $response,
            redirect_url: $redirect_url,
            successes: $changed === [] ? [] : [sprintf(
                $turn_on
                    //TRANS: parameter is a list of features
                    ? _T('Turned on: %1$s.')
                    //TRANS: parameter is a list of features
                    : _T('Turned off: %1$s.'),
                implode(', ', $labels)
            )]
        );
    }

    /**
     * Feature flags, as stored right now
     *
     * Built here rather than taken from the container: the shared instance
     * may have been loaded before the preferences it reads changed.
     */
    private function getFeatureFlags(): FeatureFlagManager
    {
        return new FeatureFlagManager($this->preferences);
    }

    /**
     * Build what the page displays, one entry per preview feature
     *
     * @return array<int, array<string, mixed>>
     */
    private function getPreviews(): array
    {
        $flags = $this->getFeatureFlags();
        $status = $flags->getAllFlagsWithStatus();
        $previews = [];

        foreach (array_keys($flags->getPreviewFlags()) as $flag) {
            $previews[] = [
                'name'     => $flag,
                'label'    => $flags->getLabel($flag),
                'risk'     => $flags->getRisk($flag),
                'enabled'  => $status[$flag]['enabled'],
                'stored'   => in_array($flag, $flags->getStoredFlags(), strict: true),
                'source'   => $status[$flag]['source'],
                'requires' => array_map(
                    fn(string $dependency): string => (string)$flags->getLabel($dependency),
                    $status[$flag]['requires']
                ),
            ];
        }

        return $previews;
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
                'alpha'     => PreferencesSchema::isAlpha($name),
                'locked_by' => $locked ? $constant : null,
                'plugin'    => PreferencesSchema::getOwner($name),
                'min'       => $schema['min'] ?? null,
                'max'       => $schema['max'] ?? null,
            ];
        }

        //rows left in database by an older version, or by a plugin that is no
        //longer active: an active one declares its preferences and is listed above
        foreach (array_diff($this->preferences->getFieldsNames(), array_keys(PreferencesSchema::getAll())) as $name) {
            $entries[] = [
                'name'   => $name,
                'known'  => false,
                //nothing describes it any more, so nothing owns it either
                'plugin' => null,
                //nor does anything say it drives an alpha feature
                'alpha'  => false,
                'value'  => $this->preferences->$name,
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
