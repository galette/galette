<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Fixtures;

use Galette\Core\Picture;

/**
 * Picture exposing its protected helpers.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ExposedPicture extends Picture
{
    /**
     * Constructor
     *
     * @param string $store_path Storage path
     * @param ?int   $id         Picture identifier
     */
    public function __construct(string $store_path, ?int $id = null)
    {
        $this->store_path = $store_path;
        parent::__construct($id);
    }

    /**
     * Expose ensureStorePath()
     */
    public function publicEnsureStorePath(): bool
    {
        return $this->ensureStorePath();
    }

    /**
     * Expose resizeImage()
     *
     * @param string $source     Source image
     * @param string $ext        Extension
     * @param string $dest       Destination image
     * @param ?int   $max_width  Maximum width
     * @param ?int   $max_height Maximum height
     */
    public function publicResizeImage(
        string $source,
        string $ext,
        string $dest,
        ?int $max_width = null,
        ?int $max_height = null
    ): bool {
        return $this->resizeImage(
            source: $source,
            ext: $ext,
            dest: $dest,
            max_width: $max_width,
            max_height: $max_height
        );
    }
}
