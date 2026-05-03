<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
    private readonly string $label;
    private string $image;

    /**
     * Default constructor
     *
     * @param string  $data      QR code data
     * @param ?string $label     Label for the QR code
     * @param ?string $url       URL to encode
     * @param ?string $logo_path Path to logo to embed in the QR code
     */
    public function __construct(
        private readonly string $data,
        ?string $label = null,
        private readonly ?string $url = null,
        private readonly ?string $logo_path = null
    ) {
        $this->label = $label ?? $this->data;

        $this->build();
    }

    /**
     * Build the QR code
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

        if (isset($this->logo_path)) {
            $logo = new Logo(
                path: $this->logo_path,
                resizeToWidth: 50,
                resizeToHeight: 50
            );
        }

        $result = $writer->write($qrcode, $logo ?? null);
        $this->image = $result->getDataUri();
    }

    /**
     * Get label
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Get URL
     */
    public function getURL(): ?string
    {
        return $this->url;
    }

    /**
     * Get image data
     */
    public function getImage(): string
    {
        return $this->image;
    }
}
