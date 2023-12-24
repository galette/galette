<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Socials feature
 *
 * PHP version 5
 *
 * Copyright © 2021-2023 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     2021-10-25
 */

namespace Galette\Features;

use Galette\Entity\Adherent;
use Galette\Entity\Social;

/**
 * Replacements feature
 *
 * @category  Features
 * @name      Replacements
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.eu
 * @since     2021-10-25
 */

trait Socials
{
    protected array $socials_input = [];

    /**
     * Check socials
     *
     * @param array $post User input
     *
     * @return void
     */
    protected function checkSocials(array $post): void
    {
        $this->socials_input = [];
        foreach ($post as $key => $value) {
            if (str_starts_with($key, 'social_')) {
                $this->socials_input[$key] = $value;
            }
        }
    }

    /**
     * Store social networks/contacts
     *
     * @param int|null $id ID
     *
     * @return bool
     */
    protected function storeSocials(int $id = null): bool
    {
        $existings = Social::getListForMember($id);
        foreach ($this->socials_input as $key => $value) {
            if (
                str_starts_with($key, 'social_new_type')
                && !empty($value)
                && isset($this->socials_input[str_replace('_type', '_value', $key)])
                && !empty($this->socials_input[str_replace('_type', '_value', $key)])
            ) {
                //new social network
                $new_index = (int)str_replace('social_new_type_', '', $key);
                $social = new Social($this->zdb);
                $social
                    ->setType($value)
                    ->setLinkedMember($id)
                    ->setUrl($this->socials_input['social_new_value_' . $new_index])
                    ->store();
            } elseif (str_starts_with($key, 'social_') && !str_starts_with($key, 'social_new_')) {
                //existing social network
                $social_id = (int)str_replace('social_', '', $key);
                $social = $existings[$social_id];
                if ($value != $social->url) {
                    $social
                        ->setUrl($value)
                        ->store();
                }
                unset($existings[$social_id]);
            }
        }

        if (count($existings)) {
            $social = new Social($this->zdb);
            $social->remove(array_keys($existings));
        }

        return true;
    }

    /**
     * Get core registered types
     * @return array
     */
    protected function getCoreRegisteredTypes(): array
    {
        return $this->getRegisteredTypes(true);
    }

    /**
     * Get member registered types
     *
     * @return array
     */
    public function getMemberRegisteredTypes(): array
    {
        return $this->getRegisteredTypes(false);
    }

    /**
     * Get registered types
     *
     * @param bool $core True for core type, false for members ones
     *
     * @return array
     */
    protected function getRegisteredTypes(bool $core): array
    {
        $select = $this->zdb->select(Social::TABLE, 's');
        $select->quantifier('DISTINCT')->columns(['type']);
        if ($core === true) {
            $select->where(Adherent::PK . ' IS NULL');
        } else {
            $select->where(Adherent::PK . ' IS NOT NULL');
        }

        $results = $this->zdb->execute($select);
        $types = [];
        foreach ($results as $result) {
            $types[$result->type] = $result->type;
        }

        return $types;
    }
}
