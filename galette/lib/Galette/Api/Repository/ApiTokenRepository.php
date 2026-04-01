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
use Throwable;

/**
 * Galette API token repository
 *
 * Manages refresh tokens for the API using Laminas DB.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ApiTokenRepository
{
    private const TABLE = 'api_tokens';

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
     * @param string      $token    Raw token (will be hashed)
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
        try {
            $insert = $this->zdb->insert(self::TABLE);
            $insert->values([
                'id_adh'        => $idAdh,
                'client_id'     => $clientId,
                'token_hash'    => hash('sha256', $token),
                'allowed_scope' => implode(' ', $scopes),
                'created_at'    => date('Y-m-d H:i:s'),
                'expires_at'    => date('Y-m-d H:i:s', time() + $ttl),
                'is_revoked'    => false,
            ]);
            $result = $this->zdb->execute($insert);
            return $result->count() > 0;
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
            $select = $this->zdb->select(self::TABLE);
            $select->where([
                'token_hash' => $hash,
                'is_revoked' => false,
            ]);
            if ($clientId !== null) {
                $select->where(['client_id' => $clientId]);
            }
            $select->where->greaterThan('expires_at', date('Y-m-d H:i:s'));

            $results = $this->zdb->execute($select);
            if ($results->count() === 0) {
                return null;
            }

            $row = $results->current();

            // Rotate: revoke the used token immediately
            $this->revokeByHash($hash);

            return [
                'id_adh'        => $row->id_adh !== null ? (int)$row->id_adh : null,
                'client_id'     => $row->client_id !== null ? (string)$row->client_id : null,
                'allowed_scope' => (string)$row->allowed_scope,
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
     */
    public function revokeAllForUser(int $idAdh): void
    {
        try {
            $update = $this->zdb->update(self::TABLE);
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
     * Revoke a token by its SHA-256 hash.
     */
    private function revokeByHash(string $hash): void
    {
        $update = $this->zdb->update(self::TABLE);
        $update->set(['is_revoked' => true])->where(['token_hash' => $hash]);
        $this->zdb->execute($update);
    }
}
