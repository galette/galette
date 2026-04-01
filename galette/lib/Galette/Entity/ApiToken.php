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

namespace Galette\Entity;

use Analog\Analog;
use ArrayObject;
use Galette\Core\Db;
use Galette\Entity\Attributes\Column;
use Throwable;

/**
 * API refresh token entity
 *
 * Represents one row of the api_tokens table. Bulk operations
 * (revoke by user, revoke by client) are handled by ApiTokenRepository.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ApiToken extends AbstractEntity
{
    public const TABLE = 'api_tokens';
    public const PK    = 'id';

    /** @phpstan-ignore property.parentPropertyType */
    #[Column('id')]
    protected int $id;

    #[Column('id_adh')]
    private ?int $id_adh = null;

    #[Column('client_id')]
    private ?string $client_id = null;

    #[Column('token_hash')]
    private string $token_hash;

    #[Column('allowed_scope')]
    private ?string $allowed_scope = null;

    #[Column('created_at')]
    private string $created_at;

    #[Column('expires_at')]
    private string $expires_at;

    #[Column('is_revoked')]
    private bool $is_revoked = false;

    /**
     * Constructor
     *
     * @param int|ArrayObject<string, int|string>|null $args Row to load, integer PK, or null
     */
    public function __construct(int|ArrayObject|null $args = null)
    {
        if (is_int($args) && $args > 0) {
            $this->load($args);
        } elseif ($args instanceof ArrayObject) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Load from a DB ResultSet row with explicit type casting
     *
     * @param ArrayObject<string, int|string> $rs ResultSet row
     */
    public function loadFromRS(ArrayObject $rs): static
    {
        $this->id          = (int)$rs->{self::PK};
        $this->id_adh      = $rs->id_adh !== null ? (int)$rs->id_adh : null;
        $this->client_id   = $rs->client_id !== null ? (string)$rs->client_id : null;
        $this->token_hash  = (string)$rs->token_hash;
        $this->allowed_scope = $rs->allowed_scope !== null ? (string)$rs->allowed_scope : null;
        $this->created_at  = (string)$rs->created_at;
        $this->expires_at  = (string)$rs->expires_at;
        $this->is_revoked  = (bool)$rs->is_revoked;
        return $this;
    }

    /**
     * Revoke this token — marks it as consumed and persists the change
     */
    public function revoke(): bool
    {
        $this->is_revoked = true;
        return $this->save();
    }

    /**
     * Find a valid (non-expired, non-revoked) token by its SHA-256 hash.
     *
     * Returns null if the token does not exist, is expired, or has been revoked.
     *
     * @param Db          $zdb      Database instance
     * @param string      $hash     SHA-256 of the raw token
     * @param string|null $clientId Optional client_id constraint
     */
    public static function findValid(Db $zdb, string $hash, ?string $clientId): ?self
    {
        try {
            $select = $zdb->select(self::TABLE);
            $select->where([
                'token_hash' => $hash,
                'is_revoked' => false,
            ]);
            if ($clientId !== null) {
                $select->where(['client_id' => $clientId]);
            }
            $select->where->greaterThan('expires_at', date('Y-m-d H:i:s'));

            $results = $zdb->execute($select);
            if ($results->count() === 0) {
                return null;
            }

            return new self($results->current());
        } catch (Throwable $e) {
            Analog::log(
                'ApiToken: error finding valid token: ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Setters
    // -------------------------------------------------------------------------

    /**
     * Set member ID
     *
     * @param int|null $idAdh Member ID, or null for client-only tokens
     */
    public function setIdAdh(?int $idAdh): static
    {
        $this->id_adh = $idAdh;
        return $this;
    }

    /**
     * Set OAuth2 client ID
     *
     * @param string|null $clientId Client ID, or null for user tokens
     */
    public function setClientId(?string $clientId): static
    {
        $this->client_id = $clientId;
        return $this;
    }

    /**
     * Set the token hash (SHA-256 of the raw token)
     *
     * @param string $tokenHash SHA-256 hex digest
     */
    public function setTokenHash(string $tokenHash): static
    {
        $this->token_hash = $tokenHash;
        return $this;
    }

    /**
     * Set allowed scopes (space-separated)
     *
     * @param string|null $allowedScope Space-separated scopes
     */
    public function setAllowedScope(?string $allowedScope): static
    {
        $this->allowed_scope = $allowedScope;
        return $this;
    }

    /**
     * Set creation timestamp
     *
     * @param string $createdAt Datetime string (Y-m-d H:i:s)
     */
    public function setCreatedAt(string $createdAt): static
    {
        $this->created_at = $createdAt;
        return $this;
    }

    /**
     * Set expiration timestamp
     *
     * @param string $expiresAt Datetime string (Y-m-d H:i:s)
     */
    public function setExpiresAt(string $expiresAt): static
    {
        $this->expires_at = $expiresAt;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    /**
     * Get member ID
     */
    public function getIdAdh(): ?int
    {
        return $this->id_adh;
    }

    /**
     * Get OAuth2 client ID
     */
    public function getClientId(): ?string
    {
        return $this->client_id;
    }

    /**
     * Get token SHA-256 hash
     */
    public function getTokenHash(): string
    {
        return $this->token_hash;
    }

    /**
     * Get allowed scopes (space-separated string)
     */
    public function getAllowedScope(): ?string
    {
        return $this->allowed_scope;
    }

    /**
     * Get creation timestamp
     */
    public function getCreatedAt(): string
    {
        return $this->created_at;
    }

    /**
     * Get expiration timestamp
     */
    public function getExpiresAt(): string
    {
        return $this->expires_at;
    }

    /**
     * Whether the token has been revoked
     */
    public function isRevoked(): bool
    {
        return $this->is_revoked;
    }
}
