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

use ArrayObject;
use Galette\Core\Db;
use Galette\Entity\Attributes\Column;
use Galette\Entity\Attributes\SkipIdCheck;
use Safe\DateTime;
use Throwable;
use Analog\Analog;

/**
 * API client
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
    #[Column('client_secret')]
    private string $client_secret;
    #[Column('client_name')]
    private string $client_name;
    #[Column('redirect_uri')]
    private string $redirect_uri;
    #[Column('is_trusted')]
    private bool $is_trusted = false;
    #[Column('created_at')]
    private DateTime $created_at;

    /**
     * Main constructor
     *
     * @param int|ArrayObject<string, int|string>|null $args Arguments
     */
    public function __construct(int|ArrayObject|null $args = null)
    {
        if (is_int($args)) {
            $this->load($args);
        } elseif ($args instanceof ArrayObject) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Load an API Client from its identifier
     *
     * @param int $id Identifier
     */
    /*private function load(int $id): void
    {
        global $zdb;
        try {
            $select = $zdb->select(self::TABLE);
            $select->limit(1)->where([self::PK => $id]);

            $results = $zdb->execute($select);
            $res = $results->current();

            $this->loadFromRS($res);
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred loading API client #' . $id . "Message:\n"
                . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }*/

    /**
     * Load API client from a db ResultSet
     *
     * @param ArrayObject<string, int|string> $rs ResultSet
     */
    /*private function loadFromRS(ArrayObject $rs): void
    {
        $this->client_id = $rs->{self::PK};
        $this->client_secret = $rs->{self::PK};
        $this->client_name = $rs->{self::PK};
        $this->redirect_uri = $rs->{self::PK};
        $this->is_trusted = $rs->{self::PK};
        $this->created_at = new DateTime($rs->{self::PK});
    }*/

    /**
     * Store title in database
     *
     * @param Db $zdb Database instance
     */
    public function store(Db $zdb): bool
    {
        return false;
        /*$data = [
            'short_label'   => strip_tags($this->short),
            'long_label'    => strip_tags((string)$this->long)
        ];
        try {
            if (isset($this->id) && $this->id > 0) {
                $update = $zdb->update(self::TABLE);
                $update->set($data)->where([self::PK => $this->id]);
                $zdb->execute($update);
            } else {
                $insert = $zdb->insert(self::TABLE);
                $insert->values($data);
                $add = $zdb->execute($insert);
                if (!$add->count() > 0) {
                    Analog::log('Not stored!', Analog::ERROR);
                    return false;
                }

                $this->id = $zdb->getLastGeneratedValue($this);
            }
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred storing title: ' . $e->getMessage()
                . "\n" . print_r($data, true),
                Analog::ERROR
            );
            throw $e;
        }*/
    }

    /**
     * Remove current title
     *
     * @param Db $zdb Database instance
     */
    public function remove(Db $zdb): bool
    {
        return false;
        /*$id = (int)$this->id;
        if ($id === self::MR || $id === self::MRS) {
            throw new \RuntimeException(_T("You cannot delete Mr. or Mrs. titles!"));
        }

        try {
            $delete = $zdb->delete(self::TABLE);
            $delete->where([self::PK => $id]);
            $zdb->execute($delete);
            Analog::log(
                'Title #' . $id . ' (' . $this->short
                . ') deleted successfully.',
                Analog::INFO
            );
            return true;
        } catch (\RuntimeException $re) {
            throw $re;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to delete title ' . $id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }*/
    }

    /**
     * Getter
     *
     * @param string $name Property name
     */
    /*public function __get(string $name): mixed
    {
        global $lang;

        switch ($name) {
            case 'id':
                return $this->$name;
            case 'short':
            case 'long':
                if (
                    $name === 'long'
                    && ($this->long == null || trim($this->long) === '')
                ) {
                    $name = 'short';
                }
                return $this->$name;
            case 'tshort':
            case 'tlong':
                $rname = null;
                if ($name === 'tshort') {
                    $rname = 'short';
                } elseif ($this->long !== null && trim($this->long) !== '') {
                    $rname = 'long';
                } else {
                    //switch back to short version if long does not exists
                    $rname = 'short';
                }
                if (isset($lang) && isset($lang[$this->$rname])) {
                    return _T($this->$rname);
                } else {
                    return $this->$rname;
                }
        }

        throw new \RuntimeException(
            sprintf(
                'Unable to get property "%s::%s"!',
                static::class,
                $name
            )
        );
    }*/

    /**
     * Isset
     * Required for twig to access properties via __get
     *
     * @param string $name Property name
     */
    public function __isset(string $name): bool
    {
        return isset($this->$name);
    }

    /**
     * Setter
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     */
    /*public function __set(string $name, mixed $value): void
    {
        switch ($name) {
            case 'short':
            case 'long':
                if (trim((string)$value) === '') {
                    Analog::log(
                        'Trying to set empty value for title' . $name,
                        Analog::WARNING
                    );
                } else {
                    $this->$name = $value;
                }
                break;
            default:
                Analog::log(
                    'Unable to set property ' . $name,
                    Analog::WARNING
                );
                break;
        }
    }*/

    /**
     * Get ApiClient
     */
    public function getClientId(): string
    {
        return $this->client_id;
    }

    /**
     * @param string $client_id
     * @return ApiClient
     */
    public function setClientId(string $client_id): ApiClient
    {
        $this->client_id = $client_id;
        return $this;
    }

    /**
     * Get client_secret
     */
    public function getClientSecret(): string
    {
        return $this->client_secret;
    }

    /**
     * Set client_secret
     */
    public function setClientSecret(string $client_secret): static
    {
        $this->client_secret = $client_secret;
        return $this;
    }

    /**
     * Get client_name
     */
    public function getClientName(): string
    {
        return $this->client_name;
    }

    /**
     * Set client_name
     */
    public function setClientName(string $client_name): static
    {
        $this->client_name = $client_name;
        return $this;
    }

    /**
     * Get redirect_uri
     */
    public function getRedirectUri(): string
    {
        return $this->redirect_uri;
    }

    /**
     * Set redirect_uri
     */
    public function setRedirectUri(string $redirect_uri): static
    {
        $this->redirect_uri = $redirect_uri;
        return $this;
    }

    /**
     * Get is_trusted
     */
    public function isTrusted(): bool
    {
        return $this->is_trusted;
    }

    /**
     * Set is_trusted
     */
    public function setTrusted(bool $is_trusted): static
    {
        $this->is_trusted = $is_trusted;
        return $this;
    }

    /**
     * Get created_at
     */
    public function getCreatedAt(): DateTime
    {
        return $this->created_at;
    }

    /**
     * Set created_at
     */
    public function setCreatedAt(DateTime $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }
}
