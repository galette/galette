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

use Galette\Core\Db;

/**
 * Galette API token repository
 */
class ApiTokenRepository
{
    public function __construct(private readonly Db $zdb)
    {
    }

    /**
     * Crée un nouveau Refresh Token en base
     */
    public function createRefreshToken(int $id_adh, string $client_id, string $token, array $scopes, int $ttl = 2592000): bool
    {
        $insert = $this->zdb->insert('api_tokens');
        $insert->values([
            'id_adh'     => $id_adh,
            'client_id'  => $client_id,
            'token_hash' => hash('sha256', $token),
            'allowed_scope' => implode(' ', $scopes),
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', time() + $ttl)
        ]);
        $add = $this->zdb->execute($insert);
        return $add->count() > 0;
    }

    /**
     * Valide un Refresh Token et retourne les infos associées
     */
    public function verifyAndRevoke(string $token, string $client_id): ?array
    {
        $hash = hash('sha256', $token);

        // 1. On cherche le token valide
        $sql = "SELECT id_adh, scope FROM galette_api_tokens 
                WHERE token_hash = :hash 
                AND client_id = :client_id 
                AND expires_at > NOW() 
                AND is_revoked = 0";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':hash' => $hash, ':client_id' => $client_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            // 2. Rotation : On révoque immédiatement l'ancien token utilisé
            $this->revokeToken($hash);
            return $data; // Retourne ['id_adh' => ..., 'scope' => ...]
        }

        return null;
    }

    private function revokeToken(string $hash): void
    {
        $sql = "UPDATE galette_api_tokens SET is_revoked = 1 WHERE token_hash = :hash";
        $this->db->prepare($sql)->execute([':hash' => $hash]);
    }
}