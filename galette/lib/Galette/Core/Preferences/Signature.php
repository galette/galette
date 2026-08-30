<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Preferences;

use Galette\Core\Db;
use Galette\Entity\Social;

/**
 * What an email signature can carry
 *
 * The association name, its website and every social network it declares,
 * expressed twice: as the patterns a signature may contain, and as the values
 * they stand for. Both sides walk the same list of types, which is why they
 * belong together.
 *
 * Preferences remains the Replacements host - it is what a signature is
 * written against; only the two lists are built here.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final readonly class Signature
{
    /** Types that had their own pattern before social networks became a list */
    private const array LEGACY_TYPES = [
        Social::FACEBOOK,
        Social::TWITTER,
        Social::LINKEDIN,
        Social::VIADEO,
    ];

    /**
     * Constructor
     *
     * @param Db $zdb Db instance
     */
    public function __construct(private Db $zdb)
    {
    }

    /**
     * Patterns a signature may contain
     *
     * @param array<string, string>                $core_types    Social types the association declares
     * @param array<string, array<string, string>> $main_patterns Main patterns, only read for the legacy ones
     * @param bool                                 $legacy        Whether to add the patterns predating the list
     *
     * @return array<string, array<string, string>>
     */
    public function getPatterns(array $core_types, array $main_patterns, bool $legacy = true): array
    {
        $social = new Social($this->zdb);
        $patterns = [];

        foreach ($this->types($core_types) as $type) {
            $patterns['asso_social_' . strtolower($type)] = [
                'title' => $social->getSystemType($type),
                'pattern' => '/{ASSO_SOCIAL_' . strtoupper($type) . '}/'
            ];
        }

        if ($legacy === false) {
            return $patterns;
        }

        $patterns['_asso_name'] = [
            'title'     => $main_patterns['asso_name']['title'],
            'pattern'   => '/{NAME}/'
        ];

        $patterns['_asso_website'] = [
            'title'     => $main_patterns['asso_website']['title'],
            'pattern'   => '/{WEBSITE}/'
        ];

        foreach (self::LEGACY_TYPES as $legacy_type) {
            $patterns['_asso_social_' . $legacy_type] = [
                'title' => $patterns['asso_social_' . $legacy_type]['title'],
                'pattern' => '/{' . strtoupper($legacy_type) . '}/'
            ];
        }

        return $patterns;
    }

    /**
     * What those patterns stand for
     *
     * A type nobody filled in is replaced by null rather than an empty string,
     * so that the line it sits on can be dropped altogether.
     *
     * @param array<string, string> $core_types        Social types the association declares
     * @param array<string, string> $done_replacements Replacements already computed, for the association name
     * @param string                $website           Association website
     *
     * @return array<string, string|null>
     */
    public function getSocialReplacements(array $core_types, array $done_replacements, string $website): array
    {
        $replacements = [
            '_asso_name' => $done_replacements['asso_name'],
            'asso_website' => $website,
            '_asso_website' => $website,
        ];

        foreach ($this->types($core_types) as $type) {
            $urls = array_map(
                static fn(Social $social): string => $social->url,
                Social::getListForMember(null, $type)
            );

            $replacements['asso_social_' . strtolower($type)] = $urls === []
                ? null
                : implode(', ', $urls);
        }

        foreach (self::LEGACY_TYPES as $legacy_type) {
            $replacements['_asso_social_' . $legacy_type] = $replacements['asso_social_' . $legacy_type];
        }

        return $replacements;
    }

    /**
     * Every social type a signature knows about
     *
     * @param array<string, string> $core_types Social types the association declares
     *
     * @return array<string, string>
     */
    private function types(array $core_types): array
    {
        return $core_types + (new Social($this->zdb))->getSystemTypes(false);
    }
}
