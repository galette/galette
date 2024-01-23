<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Generates fake data as example
 *
 * PHP version 5
 *
 * Copyright © 2017-2024 The Galette Team
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
 *
 * @category  Util
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.9
 */

namespace Galette\Util;

use Analog\Analog;
use Galette\Core\Db;
use Galette\Core\I18n;
use Galette\Core\Preferences;
use Galette\Core\History;
use Galette\Core\Login;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Repository\Titles;
use Galette\Entity\Status;
use Galette\Entity\ContributionsTypes;
use Galette\Entity\Group;
use Galette\Entity\Transaction;
use Galette\Entity\PaymentType;

/**
 * Generate random data
 *
 * @category  Util
 * @name      FakeData
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @see       https://github.com/fzaninotto/Faker
 * @since     Available since 0.9dev - 2017-02-20
 */
class FakeData
{
    /**
     * @var array<string,array<string>>
     */
    private array $report = [
        'success'   => [],
        'errors'    => [],
        'warnings'  => []
    ];

    /**
     * Add photo to a member
     *
     * @param Adherent $member Member instance
     *
     * @return boolean
     */
    public function addPhoto(Adherent $member): bool
    {
        $file = GALETTE_TEMPIMAGES_PATH . 'fakephoto.jpg';
        if (!defined('GALETTE_TESTS')) {
            $url = 'https://loremflickr.com/800/600/people';
        } else {
            $url = GALETTE_ROOT . '../tests/fake_image.jpg';
        }

        if (copy($url, $file)) {
            $_FILES = array(
                'photo' => array(
                    'name'      => 'fakephoto.jpg',
                    'type'      => 'image/jpeg',
                    'size'      => filesize($file),
                    'tmp_name'  => $file,
                    'error'     => 0
                )
            );
            $res = $member->picture->store($_FILES['photo'], true);
            if ($res < 0) {
                $this->addError(
                    _T("Photo has not been stored!")
                );
            } else {
                return true;
            }
        } else {
            $this->addError(
                _T("Photo has not been copied!")
            );
        }
        return false;
    }

    /**
     * Add success message
     *
     * @param string $msg Message
     *
     * @return void
     */
    protected function addSuccess(string $msg): void
    {
        $this->report['success'][] = $msg;
    }

    /**
     * Add error message
     *
     * @param string $msg Message
     *
     * @return void
     */
    protected function addError(string $msg): void
    {
        $this->report['errors'][] = $msg;
    }

    /**
     * Add warning message
     *
     * @param string $msg Message
     *
     * @return void
     */
    protected function addWarning(string $msg): void
    {
        $this->report['warnings'][] = $msg;
    }

    /**
     * Get report
     *
     * @return array<string,array<string>>
     */
    public function getReport(): array
    {
        return $this->report;
    }
}
