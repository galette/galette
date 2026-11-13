<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers;

use Analog\Analog;
use DI\Attribute\Inject;
use Galette\Controllers\Attributes\Route;
use Galette\Core\Logs;
use Galette\Entity\Adherent;
use Galette\Maps\Center;
use Galette\Maps\Coordinates;
use Galette\Maps\NominatimTowns;
use Galette\Maps\Precision;
use Galette\Maps\TileProviders;
use Galette\Middleware\PublicPages;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Members positions on maps
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MapsController extends AbstractController
{
    #[Inject]
    protected Coordinates $coordinates;

    #[Inject]
    protected NominatimTowns $nominatim;

    /**
     * Member dependencies to load; groups are loaded on demand by access checks
     *
     * @return array<string, bool>
     */
    private function getMemberDeps(): array
    {
        return [
            'picture'   => false,
            'groups'    => false,
            'dues'      => false
        ];
    }

    /**
     * Message for a member that does not exist
     *
     * @param int $id Requested member ID
     */
    private function getNoMemberMessage(int $id): string
    {
        return sprintf(
            //TRANS: parameter is the member identifier
            _T('No member #%1$s.'),
            $id
        );
    }

    /**
     * Message when maps are not enabled
     */
    private function getDisabledMessage(): string
    {
        return _T('Maps are not enabled.');
    }

    /**
     * Redirect when maps are not enabled
     *
     * @param Response $response PSR Response
     */
    private function redirectDisabled(Response $response): Response
    {
        return $this->redirectWithErrors(
            response: $response,
            errors: [$this->getDisabledMessage()],
            redirect_url: $this->routeparser->urlFor('slash')
        );
    }

    /**
     * Members map
     *
     * @param Response $response PSR Response
     */
    #[Route(
        name: 'maps',
        pattern: '/public/maps',
        methods: ['GET'],
        requiresAuth: false,
        middleware: [PublicPages::class]
    )]
    public function map(Response $response): Response
    {
        if (!$this->preferences->pref_maps_enabled) {
            return $this->redirectDisabled($response);
        }

        $params = [
            'page_title'        => _T('Maps'),
            'require_maps'      => true,
            'tiles'             => TileProviders::resolve($this->preferences),
            'maps_center'       => Center::resolve($this->preferences),
            'list'              => [],
            'max_zoom'          => null
        ];

        $precision = Precision::resolve($this->preferences);
        try {
            $params['list'] = $this->coordinates->listVisible(Precision::getStep($precision));
            //zooming further would suggest a precision snapped positions have not
            if (in_array(needle: true, haystack: array_column($params['list'], 'approximate'), strict: true)) {
                $params['max_zoom'] = Precision::getMaxZoom($precision);
            }
        } catch (\Throwable $e) {
            Logs::exception($e, 'Unable to list coordinates');
            $this->flash->addMessageNow(
                'error_detected',
                _T('Coordinates have not been loaded.')
            );
        }

        // display page
        $this->view->render(
            $response,
            'pages/maps.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Logged-in member localization
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     */
    #[Route(
        name: 'mapsLocalizeMe',
        pattern: '/member/position',
        methods: ['GET']
    )]
    public function localizeMe(Request $request, Response $response): Response
    {
        return $this->localizeMember($request, $response);
    }

    /**
     * Member localization
     *
     * Requested with AJAX, only the map is sent, to be displayed in a modal.
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param ?int     $id       Member ID
     */
    #[Route(
        name: 'mapsLocalizeMember',
        pattern: '/member/position/{id:\d+}',
        methods: ['GET']
    )]
    public function localizeMember(Request $request, Response $response, ?int $id = null): Response
    {
        if (!$this->preferences->pref_maps_enabled) {
            return $this->redirectDisabled($response);
        }

        if ($id === null && $this->login->isSuperAdmin()) {
            return $this->redirectWithErrors(
                response: $response,
                errors: [_T('Superadmin cannot be localized.')],
                redirect_url: $this->routeparser->urlFor('slash')
            );
        }
        $id ??= (int)$this->login->id;
        $member = new Adherent($this->zdb, $id, $this->getMemberDeps());
        if ($member->id === null) {
            return $this->redirectWithErrors(
                response: $response,
                errors: [$this->getNoMemberMessage($id)],
                redirect_url: $this->routeparser->urlFor('slash')
            );
        }

        $can_edit = $member->canEdit($this->login);
        //those who edit the member set its position, even when they cannot display it
        if (!$can_edit && !$member->canShow($this->login)) {
            Analog::log(
                'Logged in member ' . $this->login->login
                . ' has tried to display coordinates of member #' . $id
                . ' without the right to show them.',
                Analog::WARNING
            );
            return $this->redirectWithErrors(
                response: $response,
                errors: [_T("You do not have permission for requested URL.")],
                redirect_url: $this->routeparser->urlFor('me')
            );
        }

        $mcoords = $this->coordinates->get($member->id);

        $towns = false;
        //towns are only proposed to choose a location
        if ($can_edit && $mcoords === null && trim($member->town ?? '') !== '') {
            try {
                $towns = $this->nominatim->search(
                    $member->town,
                    $member->country
                );
            } catch (\RuntimeException $e) {
                Logs::exception($e, 'Unable to search towns for member #' . $member->id, Analog::WARNING);
                $this->flash->addMessageNow(
                    'warning_detected',
                    _T('Town search is not available for now, you can still search or click on the map.')
                );
            }
        }

        $position_title = sprintf(
            //TRANS: parameter is the member name
            _T('%1$s geographic position'),
            $member->sfullname
        );
        $params = [
            'page_title'        => _T('Maps') . ' - ' . $position_title,
            'position_title'    => $position_title,
            'require_maps'      => true,
            'maps_locate'       => true,
            'mode'              => $this->isAjax($request) ? 'ajax' : '',
            'member'            => $member,
            'tiles'             => TileProviders::resolve($this->preferences),
            'maps_center'       => Center::resolve($this->preferences)
        ];

        if ($towns !== false) {
            $params['towns'] = $towns;
        } elseif ($mcoords !== null) {
            $params['town'] = $mcoords;
        }

        //logins are strings: '01' == '0001' in PHP
        $mymap = (int)$member->id === (int)$this->login->id;
        if ($mymap) {
            $params['mymap'] = true;
        }

        $params['position_config'] = [
            'member' => [
                'id' => $member->id,
                'name' => $member->sfullname,
                'can_edit' => $can_edit,
                'position' => $mcoords,
                'store_url' => $mymap
                    ? $this->routeparser->urlFor('mapsStorePosition')
                    : $this->routeparser->urlFor('mapsStorePosition', ['id' => (string)$member->id])
            ]
        ];

        // display page
        $this->view->render(
            $response,
            'pages/member_position.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Maps settings
     *
     * @param Response $response PSR Response
     */
    #[Route(
        name: 'mapsSettings',
        pattern: '/maps/settings',
        methods: ['GET']
    )]
    public function settings(Response $response): Response
    {
        $params = [
            'page_title'    => _T('Maps settings'),
            'enabled'       => $this->preferences->pref_maps_enabled,
            'providers'     => TileProviders::getSelectValues(),
            'custom'        => TileProviders::CUSTOM,
            'tiles'         => TileProviders::resolve($this->preferences),
            'provider'      => $this->preferences->pref_maps_tiles_provider,
            'vector'        => $this->preferences->pref_maps_tiles_vector,
            'url'           => $this->preferences->pref_maps_tiles_url,
            'attribution'   => $this->preferences->pref_maps_tiles_attribution,
            'maxzoom'       => $this->preferences->pref_maps_tiles_maxzoom,
            'subdomains'    => $this->preferences->pref_maps_tiles_subdomains,
            'precisions'    => Precision::getSelectValues(),
            'precision'     => Precision::resolve($this->preferences),
            'center_latitude'   => $this->preferences->{Center::PREF_LATITUDE},
            'center_longitude'  => $this->preferences->{Center::PREF_LONGITUDE},
            //only a suggestion to search with, administrators may change it
            'center_town'       => $this->preferences->pref_ville,
            'center_country'    => $this->preferences->pref_pays,
            'require_maps'  => true,
            //any provider may be previewed, a vector one among them
            'maps_gl'       => true,
            'preview'       => [
                'center'        => Center::resolve($this->preferences),
                'default_center' => Center::getDefault(),
                'center_zoom'   => Center::ZOOM,
                'presets'       => TileProviders::getPresets(),
                'fallback'      => TileProviders::rasterFallback(),
                'custom'        => TileProviders::CUSTOM,
                'precisions'    => [],
            ],
        ];

        foreach (array_keys(Precision::getSelectValues()) as $id) {
            $params['preview']['precisions'][$id] = [
                'step' => Precision::getStep($id),
                'maxzoom' => Precision::getMaxZoom($id)
            ];
        }

        $this->view->render(
            $response,
            'pages/maps_settings.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Store maps settings
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     */
    #[Route(
        name: 'doMapsSettings',
        pattern: '/maps/settings',
        methods: ['POST']
    )]
    public function doSettings(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $provider = $post[TileProviders::PREF_PROVIDER] ?? TileProviders::DEFAULT;

        $precision = (string)($post[Precision::PREF] ?? Precision::DEFAULT);
        if (!Precision::isKnown($precision)) {
            $this->flash->addMessage(
                'error_detected',
                _T('Unknown position precision.')
            );
            return $response
                ->withStatus(302)
                ->withHeader('Location', $this->routeparser->urlFor('mapsSettings'));
        }

        $center = Center::normalize(
            (string)($post[Center::PREF_LATITUDE] ?? ''),
            (string)($post[Center::PREF_LONGITUDE] ?? '')
        );
        if ($center === null) {
            $this->flash->addMessage(
                'error_detected',
                _T('Maps center needs both a latitude, between -90 and 90, and a longitude, between -180 and 180.')
            );
            return $response
                ->withStatus(302)
                ->withHeader('Location', $this->routeparser->urlFor('mapsSettings'));
        }

        $values = [
            'pref_maps_enabled' => (int)isset($post['pref_maps_enabled']),
            TileProviders::PREF_PROVIDER => $provider,
            Precision::PREF => $precision,
            Center::PREF_LATITUDE => $center[0],
            Center::PREF_LONGITUDE => $center[1],
        ];
        if ($provider === TileProviders::CUSTOM) {
            //own values are only meaningful along with the custom provider
            $values += [
                TileProviders::PREF_VECTOR => (int)isset($post[TileProviders::PREF_VECTOR]),
                TileProviders::PREF_URL => trim((string)($post[TileProviders::PREF_URL] ?? '')),
                TileProviders::PREF_ATTRIBUTION => trim((string)($post[TileProviders::PREF_ATTRIBUTION] ?? '')),
                TileProviders::PREF_MAXZOOM => (int)($post[TileProviders::PREF_MAXZOOM] ?? 19),
                TileProviders::PREF_SUBDOMAINS => trim((string)($post[TileProviders::PREF_SUBDOMAINS] ?? '')),
            ];

            if ($values[TileProviders::PREF_URL] === '') {
                $this->flash->addMessage(
                    'error_detected',
                    _T('An address is required to use your own background map.')
                );
                return $response
                    ->withStatus(302)
                    ->withHeader('Location', $this->routeparser->urlFor('mapsSettings'));
            }
        }

        //every value is checked before any is stored: a refused one must not
        //leave the others half applied
        $errors = [];
        foreach ($values as $name => $value) {
            $known = count($this->preferences->getErrors());
            $this->preferences->validateValue($name, $value, $this->login);
            array_push($errors, ...array_slice($this->preferences->getErrors(), $known));
        }

        if (count($errors) === 0) {
            //each call resets errors of the previous one
            foreach ($values as $name => $value) {
                if (!$this->preferences->setValue($name, $value, $this->login)) {
                    //a failed write records no error
                    $errors = array_merge(
                        $errors,
                        $this->preferences->getErrors() ?: [
                            _T("An SQL error has occurred while saving preferences. Please try again, and contact the administrator if the problem persists.")
                        ]
                    );
                }
            }
        }

        if (count($errors) === 0) {
            $this->flash->addMessage(
                'success_detected',
                _T('Maps settings have been saved.')
            );
        } else {
            foreach ($errors as $error) {
                $this->flash->addMessage('error_detected', $error);
            }
        }

        return $response
            ->withStatus(302)
            ->withHeader('Location', $this->routeparser->urlFor('mapsSettings'));
    }

    /**
     * Search towns maps may be centered on
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     */
    #[Route(
        name: 'mapsSearchTowns',
        pattern: '/maps/towns',
        methods: ['GET']
    )]
    public function searchTowns(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $town = trim((string)($params['town'] ?? ''));
        if ($town === '') {
            return $this->withJson($response, ['towns' => []]);
        }

        try {
            $towns = $this->nominatim->search($town, (string)($params['country'] ?? ''));
        } catch (\RuntimeException $e) {
            Logs::exception($e, 'Unable to search maps center', Analog::WARNING);
            return $this->withJson(
                $response,
                ['message' => _T('Town search is not available for now, you can still enter coordinates.')],
                503
            );
        }

        return $this->withJson($response, ['towns' => $towns]);
    }

    /**
     * Change member position
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param ?int     $id       Member ID
     */
    #[Route(
        name: 'mapsStorePosition',
        pattern: '/member/position/store[/{id:\d+}]',
        methods: ['POST']
    )]
    public function storePosition(Request $request, Response $response, ?int $id = null): Response
    {
        $id ??= $this->login->isSuperAdmin() ? null : (int)$this->login->id;
        [$error, $status] = $this->checkPositionChange($id);
        $message = null;

        if ($error === null && $id !== null) {
            $post = $request->getParsedBody();
            [$error, $message, $status] = isset($post['remove'])
                ? $this->removePosition($id)
                : $this->setPosition($id, $post);
        }

        return $this->withJson(
            $response,
            [
                'res'       => $error === null,
                'message'   => ($error ?? $message)
            ],
            $status
        );
    }

    /**
     * Can logged-in user change position of a member?
     *
     * @param ?int $id Member ID, null for the super administrator own one
     *
     * @return array{?string, int} Error, null if none, and HTTP status
     */
    private function checkPositionChange(?int $id): array
    {
        if (!$this->preferences->pref_maps_enabled) {
            return [$this->getDisabledMessage(), 404];
        }

        if ($id === null) {
            Analog::log(
                'SuperAdmin does not live anywhere!',
                Analog::INFO
            );
            return [_T('Superadmin cannot be localized.'), 400];
        }

        $member = new Adherent($this->zdb, $id, $this->getMemberDeps());
        if ($member->id === null) {
            return [$this->getNoMemberMessage($id), 404];
        }

        if (!$member->canEdit($this->login)) {
            Analog::log(
                'Logged in member ' . $this->login->login
                . ' has tried to change coordinates of member #' . $id
                . ' without the right to edit them.',
                Analog::WARNING
            );
            return [_T('You do not have permission for requested URL.'), 403];
        }

        return [null, 200];
    }

    /**
     * Remove member position
     *
     * @param int $id Member ID
     *
     * @return array{?string, ?string, int} Error, message and HTTP status
     */
    private function removePosition(int $id): array
    {
        try {
            $this->coordinates->remove($id);
            return [null, _T('Coordinates have been removed!'), 200];
        } catch (\Throwable $e) {
            Logs::exception($e, 'Unable to remove coordinates of member #' . $id);
            return [_T('Coordinates have not been removed :('), null, 500];
        }
    }

    /**
     * Store member position
     *
     * @param int   $id   Member ID
     * @param mixed $post Posted data
     *
     * @return array{?string, ?string, int} Error, message and HTTP status
     */
    private function setPosition(int $id, mixed $post): array
    {
        $latitude = filter_var($post['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($post['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
        if (
            $latitude === false
            || $longitude === false
            || abs($latitude) > 90
            || abs($longitude) > 180
        ) {
            return [_T('Invalid coordinates.'), null, 400];
        }

        try {
            $this->coordinates->set($id, $latitude, $longitude);
            return [null, _T('New coordinates have been stored!'), 200];
        } catch (\Throwable $e) {
            Logs::exception($e, 'Unable to store coordinates of member #' . $id);
            return [_T('Coordinates have not been stored :('), null, 500];
        }
    }
}
