<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

namespace Galette\IO;

use Galette\Core\Db;
use Galette\Core\Preferences;
use Galette\Core\PrintLogo;
use Galette\Entity\Adherent;
use Galette\Entity\PdfModel;
use Analog\Analog;

/**
 * Attendance sheet
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class PdfAttendanceSheet extends Pdf
{
    public const SHEET_FONT = self::FONT_SIZE - 2;
    public const ATT_SHEET_MODEL = 100;

    public ?string $doc_title = null;
    public ?string $sheet_title = null;
    public ?string $sheet_sub_title = null;
    public ?\DateTime $sheet_date = null;
    private bool $wimages = false;

    /**
     * Page header
     *
     * @return void
     */
    public function Header(): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        if ($this->PageNo() > 1) {
            $this->setTopMargin(15);
            $this->setY(10);
            $this->SetFont(Pdf::FONT, '', self::SHEET_FONT);
            $head_title = $this->doc_title;
            if ($this->sheet_title !== null) {
                $head_title .= ' - ' . $this->sheet_title;
            }
            /* Removed to prevent long lines */
            /*if ($this->sheet_sub_title !== null) {
                $head_title .= ' - ' . $this->sheet_sub_title;
            }*/
            if ($this->sheet_date !== null) {
                $head_title .= ' - ' . $this->sheet_date->format(__("Y-m-d"));
            }
            $this->Cell(0, 10, $head_title, 0, 0, 'C', false, '', 0, false, 'M', 'M');
        }
    }

    /**
     * Main constructor, set creator and author
     *
     * @param Db                  $zdb   Database instance
     * @param Preferences         $prefs Preferences
     * @param array<string,mixed> $data  Data to set
     */
    public function __construct(Db $zdb, Preferences $prefs, array $data = [])
    {
        $this->filename = __('attendance_sheet') . '.pdf';
        $class = PdfModel::getTypeClass(self::ATT_SHEET_MODEL);
        $model = new $class($zdb, $prefs);

        // Set document and model information
        $this->doc_title = $data['doc_title'];
        $this->SetTitle($data['doc_title']);

        if (isset($data['title']) && trim($data['title']) != '') {
            $this->sheet_title = $data['title'];
            $model->title = $this->sheet_title;
        }
        if (isset($data['subtitle']) && trim($data['subtitle']) != '') {
            $this->sheet_sub_title = $data['subtitle'];
            $model->subtitle = $this->sheet_sub_title;
        }
        if (isset($data['sheet_date']) && trim($data['sheet_date']) != '') {
            $dformat = __("Y-m-d");
            $date = \DateTime::createFromFormat(
                $dformat,
                $data['sheet_date']
            );
            $this->sheet_date = $date;
        }

        parent::__construct($prefs, $model);
        $this->init();
    }
    /**
     * Initialize PDF
     *
     * @return void
     */
    private function init(): void
    {
        // Set document information
        $this->SetSubject(_T("Generated by Galette"));
        $this->SetKeywords(_T("Attendance sheet"));

        // No hearders and footers
        $this->setHeaderMargin(10);

        // Set colors
        $this->SetDrawColor(160, 160, 160);
        $this->SetTextColor(0);
        $this->SetFont(Pdf::FONT, '', Pdf::FONT_SIZE - 2);
    }

    /**
     * Draw members cards
     *
     * @param array<Adherent> $members Members
     *
     * @return void
     */
    public function drawSheet(array $members): void
    {
        $this->Open();
        $this->AddPage();
        $this->PageHeader($this->doc_title);

        if ($this->sheet_date) {
            $format = __("MMMM, EEEE d y");
            $formatter = new \IntlDateFormatter(
                $this->i18n->getLongID(),
                \IntlDateFormatter::FULL,
                \IntlDateFormatter::NONE,
                \date_default_timezone_get(),
                \IntlDateFormatter::GREGORIAN,
                $format
            );
            $datetime = new \DateTimeImmutable($this->sheet_date->format('Y-m-d'));
            $date = \DateTime::createFromImmutable($datetime);
            $date_fmt = mb_convert_case($formatter->format($date), MB_CASE_TITLE);
            $this->Cell(190, 7, $date_fmt, 0, 1, 'C');
        }

        // Header
        $this->SetFont('', 'B');
        $this->SetFillColor(255, 255, 255);
        $this->Cell(110, 7, _T("Name"), 1, 0, 'C', true);
        $this->Cell(80, 7, _T("Signature"), 1, 1, 'C', true);

        // Data
        $this->SetFont('');
        $mcount = 0;
        foreach ($members as $m) {
            $mcount++;
            $this->Cell(10, 16, (string)$mcount, ($this->i18n->isRTL() ? 'R' : 'L') . 'TB', 0, 'R');

            if ($m->hasPicture() && $this->wimages) {
                $p = $m->picture->getPath();

                // Set logo size to max width 30 mm or max height 25 mm
                $ratio = $m->picture->getWidth() / $m->picture->getHeight();
                if ($ratio < 1) {
                    if ($m->picture->getHeight() > 14) {
                        $hlogo = 14;
                    } else {
                        $hlogo = $m->picture->getHeight();
                    }
                    $wlogo = round($hlogo * $ratio);
                } else {
                    if ($m->picture->getWidth() > 14) {
                        $wlogo = 14;
                    } else {
                        $wlogo = $m->picture->getWidth();
                    }
                    $hlogo = round($wlogo / $ratio);
                }

                $y = $this->getY() + 1;
                $x = $this->getX() + 1;
                $ximg = $x;
                if ($this->i18n->isRTL()) {
                    $ximg = $this->getPageWidth() - $x - $wlogo;
                }
                $this->Cell($wlogo + 2, 16, '', ($this->i18n->isRTL() ? 'R' : 'L') . 'TB', 0);
                $this->Image($p, $ximg, $y, $wlogo, $hlogo);
            } else {
                $x = $this->getX() + 1;
                $this->Cell(1, 16, '', ($this->i18n->isRTL() ? 'R' : 'L') . 'TB', 0);
            }

            $xs = $this->getX() - $x + 1;
            $this->Cell(100 - $xs, 16, $m->sname, ($this->i18n->isRTL() ? 'L' : 'R') . 'TB', 0, ($this->i18n->isRTL() ? 'R' : 'L'));
            $this->Cell(80, 16, '', 1, 1, ($this->i18n->isRTL() ? 'R' : 'L'));
        }
        $this->Cell(190, 0, '', 'T');
    }

    /**
     * Add images to file
     *
     * @return self
     */
    public function withImages(): self
    {
        $this->wimages = true;
        return $this;
    }
}
