<?php

/**
 * Copyright © 2003-2025 The Galette Team
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
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Galette\Core\GaletteMail;
use Galette\Features\I18n;
use Laminas\Db\Sql\Expression;
use Throwable;
use Galette\Core\Db;
use Analog\Analog;

/**
 * Social networks/Contacts
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property string $url
 * @property string $type
 * @property int $id
 */
#[ORM\Entity]
#[ORM\Table(name: 'orm_socials')]
class Social
{
    use I18n;

    public const TABLE = 'socials';
    public const PK = 'id_social';

    public const MASTODON = 'mastodon';
    public const TWITTER = 'twitter';
    public const FACEBOOK = 'facebook';
    public const LINKEDIN = 'linkedin';
    public const VIADEO = 'viadeo';
    public const JABBER = 'jabber';
    public const ICQ = 'icq';
    public const WEBSITE = 'website';
    public const BLOG = 'blog';
    public const DISCORD = 'discord';

    private Db $zdb;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_social', type: Types::INTEGER, options: ['unsigned' => true])]
    private int $id;
    #[ORM\Column(type: Types::STRING, length: 250)]
    private string $type;
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $url;
    #[ORM\ManyToOne(targetEntity: Adherent::class)]
    #[ORM\JoinColumn(
        name: 'id_adh',
        referencedColumnName: 'id_adh',
        nullable: true,
        onDelete: 'restrict',
        options: [
            'default' => null,
            'unsigned' => true
        ]
    )]
    private ?int $id_adh;
    private ?Adherent $member = null;

    /**
     * Main constructor
     *
     * @param Db                                      $zdb  Database instance
     * @param int|ArrayObject<string,int|string>|null $args Arguments
     */
    public function __construct(Db $zdb, int|ArrayObject|null $args = null)
    {
        $this->zdb = $zdb;
        if (is_int($args)) {
            $this->load($args);
        } elseif ($args instanceof ArrayObject) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Load a social from its identifier
     *
     * @param integer $id Identifier
     *
     * @return void
     */
    private function load(int $id): void
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->limit(1)->where([self::PK => $id]);

            $results = $this->zdb->execute($select);
            /** @var ArrayObject<string, int|string> $res */
            $res = $results->current();
            $this->loadFromRS($res);
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred loading social #' . $id . "Message:\n" .
                $e->getMessage(),
                Analog::ERROR
            );
        }
    }

    /**
     * Get socials for a member
     *
     * @param int|null    $id_adh Member id
     * @param string|null $type   Type to retrieve
     *
     * @return array<int,Social>
     *
     * @throws Throwable
     */
    public static function getListForMember(?int $id_adh = null, ?string $type = null): array
    {
        global $zdb;

        try {
            $select = $zdb->select(self::TABLE);

            if ($id_adh === null) {
                $select->where(Adherent::PK . ' IS NULL');
            } else {
                $select->where([Adherent::PK => $id_adh]);
            }

            if ($type !== null) {
                $select->where(['type' => $type]);
            }

            $select->order(self::PK);

            $results = $zdb->execute($select);
            $socials = [];
            foreach ($results as $r) {
                $socials[$r->{self::PK}] = new Social($zdb, $r);
            }
            return $socials;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred loading socials for member #' . $id_adh . "Message:\n" .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Load social from a db ResultSet
     *
     * @param ArrayObject<string, int|string> $rs ResultSet
     *
     * @return void
     */
    private function loadFromRS(ArrayObject $rs): void
    {
        $this->id = (int)$rs->{self::PK};
        $this->setLinkedMember((int)$rs->{Adherent::PK});
        $this->type = $rs->type;
        $this->url = $rs->url;
    }

    /**
     * Store social in database
     *
     * @return boolean
     */
    public function store(): bool
    {
        try {
            if (isset($this->id) && $this->id > 0) {
                $update = $this->zdb->update(self::TABLE);
                $update->set(['url' => $this->url])->where(
                    [self::PK => $this->id]
                );
                $this->zdb->execute($update);
            } else {
                $insert = $this->zdb->insert(self::TABLE);
                $id_adh = $this->{Adherent::PK} > 0 ? $this->{Adherent::PK} : new Expression('NULL');
                $insert->values([
                    'type'          => $this->type,
                    'url'           => $this->url,
                    Adherent::PK    => $id_adh
                ]);
                $add = $this->zdb->execute($insert);
                if (!$add->count() > 0) {
                    Analog::log('Not stored!', Analog::ERROR);
                    return false;
                }

                $this->id = $this->zdb->getLastGeneratedValue($this);
                if (!in_array($this->type, $this->getSystemTypes(false))) {
                    $this->addTranslation($this->type);
                }
            }
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred storing social: ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Remove current social
     *
     * @param array<int>|null $ids IDs to remove, default to current id
     *
     * @return boolean
     */
    public function remove(?array $ids = null): bool
    {
        if ($ids == null) {
            $ids[] = $this->id;
        }

        try {
            $delete = $this->zdb->delete(self::TABLE);
            $delete->where([self::PK => $ids]);
            $this->zdb->execute($delete);
            Analog::log(
                'Social #' . implode(', #', $ids) . ' deleted successfully.',
                Analog::INFO
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to delete social #' . implode(', #', $ids) . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Getter
     *
     * @param string $name Property name
     *
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->$name;
    }

    /**
     * Isset
     * Required for twig to access properties via __get
     *
     * @param string $name Property name
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return property_exists($this, $name);
    }

    /**
     * Display URL the best way
     *
     * @return string
     */
    public function displayUrl(): string
    {
        if (isValidWebUrl($this->url)) {
            return sprintf('<a href="%1$s">%1$s</a>', $this->url);
        }

        if (GaletteMail::isValidEmail($this->url)) {
            return sprintf('<a href="mailto:%1$s">%1$s</a>', $this->url);
        }

        return $this->url;
    }

    /**
     * Set type
     *
     * @param string $type Type
     *
     * @return self
     */
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Set linked member
     *
     * @param int|null $id Member id
     *
     * @return self
     */
    public function setLinkedMember(?int $id = null): self
    {
        $this->{Adherent::PK} = $id;
        if ($this->{Adherent::PK} > 0) {
            $this->member = new Adherent($this->zdb, $this->{Adherent::PK});
        }
        return $this;
    }

    /**
     * Set URL
     *
     * @param string $url Value to set
     *
     * @return self
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Get system social types
     *
     * @param boolean $translated Return translated types (default) or not
     *
     * @return array<string,string>
     */
    public function getSystemTypes(bool $translated = true): array
    {
        if ($translated) {
            $systypes = [
                self::MASTODON => _T('Mastodon'),
                self::TWITTER => _T('Twitter'),
                self::FACEBOOK => _T('Facebook'),
                self::LINKEDIN => _T('LinkedIn'),
                self::VIADEO => _T('Viadeo'),
                self::JABBER => _T('Jabber'),
                self::ICQ => _T('ICQ'),
                self::WEBSITE => _T('Website'),
                self::BLOG => _T('Blog'),
                self::DISCORD => _T('Discord')
            ];
        } else {
            $systypes = [
                self::MASTODON => 'mastodon',
                self::TWITTER => 'twitter',
                self::FACEBOOK => 'facebook',
                self::LINKEDIN => 'linkedin',
                self::VIADEO => 'viadeo',
                self::JABBER => 'jabber',
                self::ICQ => 'icq',
                self::WEBSITE => 'website',
                self::BLOG => 'blog',
                self::DISCORD => 'discord'
            ];
        }
        return $systypes;
    }

    /**
     * Get system social types
     *
     * @param string  $type       Social type
     * @param boolean $translated Return translated types (default) or not
     *
     * @return string
     */
    public function getSystemType(string $type, bool $translated = true): string
    {
        return $this->getSystemTypes($translated)[$type] ?? _T($type);
    }
}
