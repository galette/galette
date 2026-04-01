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
use Galette\Entity\Attributes\SkipIdCheck;
use Safe\DateTime;
use Throwable;

/**
 * API client entity (OAuth2 client credentials)
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[SkipIdCheck]
class ApiClient extends AbstractEntity
{
    public const TABLE = 'api_client';
    public const PK = 'client_id';

    #[Column(self::PK)]
    private string $client_id;

    #[Column('client_secret_hash')]
    private string $client_secret_hash;

    #[Column('client_name')]
    private string $client_name;

    #[Column('redirect_uri')]
    private ?string $redirect_uri = null;

    #[Column('is_trusted')]
    private bool $is_trusted = false;

    #[Column('created_at')]
    private DateTime $created_at;

    /**
     * Main constructor
     *
     * @param string|ArrayObject<string, int|string>|null $args Client ID to load, a ResultSet row, or null
     */
    public function __construct(string|ArrayObject|null $args = null)
    {
        if (is_string($args)) {
            $this->loadByClientId($args);
        } elseif ($args instanceof ArrayObject) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Load an API client by its string client_id
     */
    private function loadByClientId(string $clientId): void
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->limit(1)->where([self::PK => $clientId]);
            $results = $this->zdb->execute($select);
            if ($results->count() === 0) {
                Analog::log('API client `' . $clientId . '` not found.', Analog::WARNING);
                return;
            }
            $this->loadFromRS($results->current());
        } catch (Throwable $e) {
            Analog::log(
                'Error loading API client `' . $clientId . '`: ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Load API client from a DB ResultSet row
     *
     * @param ArrayObject<string, int|string> $rs ResultSet row
     */
    public function loadFromRS(ArrayObject $rs): static
    {
        $this->client_id = (string)$rs->{self::PK};
        $this->client_secret_hash = (string)$rs->client_secret_hash;
        $this->client_name = (string)$rs->client_name;
        $this->redirect_uri = isset($rs->redirect_uri) ? (string)$rs->redirect_uri : null;
        $this->is_trusted = (bool)$rs->is_trusted;
        $this->created_at = new DateTime((string)$rs->created_at);
        return $this;
    }

    /**
     * Save (insert or update) API client in database.
     * Hashes the secret if provided via setClientSecret() before calling this.
     */
    public function save(): bool
    {
        try {
            $data = [
                'client_id'         => $this->client_id,
                'client_secret_hash' => $this->client_secret_hash,
                'client_name'       => $this->client_name,
                'redirect_uri'      => $this->redirect_uri,
                'is_trusted'        => $this->is_trusted ? 1 : 0,
                'created_at'        => $this->created_at->format('Y-m-d H:i:s'),
            ];

            $select = $this->zdb->select(self::TABLE);
            $select->limit(1)->where([self::PK => $this->client_id]);
            $exists = $this->zdb->execute($select)->count() > 0;

            if ($exists) {
                $update = $this->zdb->update(self::TABLE);
                $update->set($data)->where([self::PK => $this->client_id]);
                $this->zdb->execute($update);
            } else {
                $insert = $this->zdb->insert(self::TABLE);
                $insert->values($data);
                $result = $this->zdb->execute($insert);
                if (!$result->count() > 0) {
                    Analog::log('API client not inserted.', Analog::ERROR);
                    return false;
                }
            }
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Error storing API client `' . $this->client_id . '`: ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Remove API client from database
     */
    public function remove(): bool
    {
        try {
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where([self::PK => $this->client_id]);
            $this->zdb->execute($delete);
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Error removing API client `' . $this->client_id . '`: ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Verify a plain-text secret against the stored hash
     */
    public function verifySecret(string $secret): bool
    {
        if (!isset($this->client_secret_hash)) {
            return false;
        }
        return password_verify($secret, $this->client_secret_hash);
    }

    /**
     * Check whether the entity was loaded successfully
     */
    public function isLoaded(): bool
    {
        return isset($this->client_id);
    }

    /**
     * Get client ID
     */
    public function getClientId(): string
    {
        return $this->client_id;
    }

    /**
     * Set client ID
     *
     * @param string $client_id Client identifier
     */
    public function setClientId(string $client_id): static
    {
        $this->client_id = $client_id;
        return $this;
    }

    /**
     * Set client secret — will be hashed with bcrypt
     *
     * @param string $secret Plain-text secret
     */
    public function setClientSecret(string $secret): static
    {
        $this->client_secret_hash = password_hash($secret, PASSWORD_BCRYPT);
        return $this;
    }

    /**
     * Get client name
     */
    public function getClientName(): string
    {
        return $this->client_name;
    }

    /**
     * Set client name
     *
     * @param string $client_name Client display name
     */
    public function setClientName(string $client_name): static
    {
        $this->client_name = $client_name;
        return $this;
    }

    /**
     * Get redirect URI
     */
    public function getRedirectUri(): ?string
    {
        return $this->redirect_uri;
    }

    /**
     * Set redirect URI
     *
     * @param string|null $redirect_uri OAuth2 redirect URI
     */
    public function setRedirectUri(?string $redirect_uri): static
    {
        $this->redirect_uri = $redirect_uri;
        return $this;
    }

    /**
     * Whether the client is trusted (admin-level access)
     */
    public function isTrusted(): bool
    {
        return $this->is_trusted;
    }

    /**
     * Set trusted flag
     *
     * @param bool $is_trusted True if this client should have admin-level access
     */
    public function setTrusted(bool $is_trusted): static
    {
        $this->is_trusted = $is_trusted;
        return $this;
    }

    /**
     * Get creation date
     */
    public function getCreatedAt(): DateTime
    {
        return $this->created_at;
    }

    /**
     * Set creation date
     *
     * @param DateTime $created_at Creation timestamp
     */
    public function setCreatedAt(DateTime $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }

    /**
     * Isset — required for Twig property access via __get
     */
    public function __isset(string $name): bool
    {
        return isset($this->$name);
    }
}
