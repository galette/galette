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

namespace Galette\Util;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * QR code generation
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class QrCode
{
    private string $data;
    private string $label;
    private ?string $url;
    private string $image;
    private ?string $logo;

    /**
     * Default constructor
     *
     * @param string  $data  QR code data
     * @param ?string $label Label for the QR code
     * @param ?string $url   URL to encode
     * @param ?string $logo  Path to logo to embed in the QR code
     */
    public function __construct(string $data, ?string $label = null, ?string $url = null, ?string $logo = null)
    {
        $this->data = $data;
        $this->label = $label ?? $data;
        $this->url = $url;
        $this->logo = $logo;

        $this->build();
    }

    /**
     * Build the QR code
     *
     * @return void
     */
    private function build(): void
    {
        $writer = new SvgWriter();

        $qrcode = new \Endroid\QrCode\QrCode(
            data: $this->data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        if (isset($this->logo)) {
            $logo = new Logo(
                path: $this->logo,
                resizeToWidth: 50,
                resizeToHeight: 50
            );
        }

        $result = $writer->write($qrcode, $logo ?? null);
        $this->image = $result->getDataUri();
    }

    /**
     * Get label
     *
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Get URL
     *
     * @return ?string
     */
    public function getURL(): ?string
    {
        return $this->url;
    }

    /**
     * Get image data
     *
     * @return string
     */
    public function getImage(): string
    {
        return $this->image;
    }
}
