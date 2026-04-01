<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

namespace Galette\Api\Repository;

use Analog\Analog;
use Galette\Core\Db;
use Galette\Entity\ApiToken;
use Throwable;

/**
 * Galette API token repository
 *
 * Handles bulk operations on ApiToken entities (revoke by user, revoke by client).
 * Single-token lifecycle (create, revoke one) is delegated to ApiToken.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ApiTokenRepository
{
    /**
     * Constructor
     *
     * @param Db $zdb Database instance
     */
    public function __construct(private readonly Db $zdb)
    {
    }

    /**
     * Store a new refresh token for a user or OAuth2 client.
     *
     * @param int|null    $idAdh    Member ID (null for client-only tokens)
     * @param string|null $clientId OAuth2 client ID (null for user-only tokens)
     * @param string      $token    Raw token (will be hashed with SHA-256)
     * @param string[]    $scopes   Granted scopes
     * @param int         $ttl      Time-to-live in seconds (default: 30 days)
     */
    public function createRefreshToken(
        ?int $idAdh,
        ?string $clientId,
        string $token,
        array $scopes,
        int $ttl = 2592000
    ): bool {
        $apiToken = new ApiToken();
        $apiToken
            ->setIdAdh($idAdh)
            ->setClientId($clientId)
            ->setTokenHash(hash('sha256', $token))
            ->setAllowedScope($scopes !== [] ? implode(' ', $scopes) : null)
            ->setCreatedAt(date('Y-m-d H:i:s'))
            ->setExpiresAt(date('Y-m-d H:i:s', time() + $ttl));

        try {
            return $apiToken->save();
        } catch (Throwable $e) {
            Analog::log(
                'API: error storing refresh token: ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Verify a refresh token, revoke it (rotation), and return its data.
     *
     * Returns an array with keys 'id_adh', 'client_id', 'allowed_scope',
     * or null if the token is invalid/expired/revoked.
     *
     * @return array{id_adh: int|null, client_id: string|null, allowed_scope: string}|null
     */
    public function verifyAndRotate(string $token, ?string $clientId): ?array
    {
        $hash = hash('sha256', $token);

        try {
            $apiToken = ApiToken::findValid($this->zdb, $hash, $clientId);

            if ($apiToken === null) {
                return null;
            }

            $apiToken->revoke();

            return [
                'id_adh'        => $apiToken->getIdAdh(),
                'client_id'     => $apiToken->getClientId(),
                'allowed_scope' => $apiToken->getAllowedScope() ?? '',
            ];
        } catch (Throwable $e) {
            Analog::log(
                'API: error verifying refresh token: ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Revoke all refresh tokens for a given member.
     *
     * Bulk operation — not expressible as a single-entity lifecycle call.
     *
     * @param int $idAdh Member ID
     */
    public function revokeAllForUser(int $idAdh): void
    {
        try {
            $update = $this->zdb->update(ApiToken::TABLE);
            $update->set(['is_revoked' => true])->where(['id_adh' => $idAdh]);
            $this->zdb->execute($update);
        } catch (Throwable $e) {
            Analog::log(
                'API: error revoking tokens for user ' . $idAdh . ': ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Revoke all refresh tokens for a given OAuth2 client.
     *
     * Bulk operation — not expressible as a single-entity lifecycle call.
     *
     * @param string $clientId OAuth2 client ID
     */
    public function revokeAllForClient(string $clientId): void
    {
        try {
            $update = $this->zdb->update(ApiToken::TABLE);
            $update->set(['is_revoked' => true])->where(['client_id' => $clientId]);
            $this->zdb->execute($update);
        } catch (Throwable $e) {
            Analog::log(
                'API: error revoking tokens for client ' . $clientId . ': ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }
}
