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

namespace Galette\IO;

use Galette\Core\PrintLogo;
use Galette\Entity\Adherent;
use Galette\Entity\Status;
use Galette\Repository\Members;

/**
 * Member card PDF adaptative
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @author Fabrice Santoni <fabrice@santoni.ch>
 */
class PdfMembersCardsAdaptative extends PdfMembersCards
{
    protected float $ratio;
    protected float $wphoto;
    protected float $hphoto;
    protected string $adh_nbr;
    protected float $cell_he;
    protected int $ban_max_he;
    protected float $email_y;
    protected int $max_text_size_full;
    protected int $max_text_size_top;
    protected int $max_text_size_center;

    /**
     * Initialize PDF
     *
     * @return void
     */
    public function init(): void
    {
        parent::init();
        // Card width
        $this->wi = self::getWidth();
        // Card height
        $this->he = self::getHeight();
        // Number of colons
        $this->nbcol = self::getCols();
        // Number of rows
        $this->nbrow = self::getRows();

        $print_logo = new PrintLogo();

        // Set logo size to max 33% width  or max 42% height
        $this->ratio = $print_logo->getWidth() / $print_logo->getHeight();

        if ($this->ratio <= 1) {
            if ($print_logo->getHeight() > 0.42 * $this->he * 3.78) {
                $this->hlogo = round(0.42 * $this->he, 0, PHP_ROUND_HALF_DOWN);
            } else {
                // Convert original pixels size to millimeters
                $this->hlogo = $print_logo->getHeight() / 3.78;
            }
            $this->wlogo = round($this->hlogo * $this->ratio);
        } else {
            if ($print_logo->getWidth() > 0.33 * $this->wi * 3.78) {
                $this->wlogo = round(0.33 * $this->wi, 0, PHP_ROUND_HALF_DOWN);
            } else {
                // Convert original pixels size to millimeters
                $this->wlogo = $print_logo->getWidth() / 3.78;
            }
            $this->hlogo = round($this->wlogo / $this->ratio);
            // check if the logo height is greater than 42%
            if ($this->hlogo > 0.42 * $this->he) {
                $this->hlogo = round(0.42 * $this->he, 0, PHP_ROUND_HALF_DOWN);
                $this->wlogo = round($this->hlogo * $this->ratio);
            }
        }
    }

    /**
     * Draw members cards
     *
     * @param array<Adherent> $members Members
     *
     * @return void
     */
    public function drawCards(array $members): void
    {
        global $zdb;

        $nb_card = 0;

        $status = new Status($zdb);
        $status_list = $status->getCompleteList();

        foreach ($members as $member) {
            // Detect page breaks
            if ($nb_card % ($this->nbcol * $this->nbrow) === 0) {
                $this->AddPage();
            }
            //maximum size for visible text. May vary with fonts.
            $this->max_text_size_full = self::getWidth();
            $this->year_font_size = (int)round(self::getWidth() / 7);

            // Compute card position on page
            $col = $nb_card % $this->nbcol;
            $row = (int)(($nb_card / $this->nbcol)) % $this->nbrow;

            // Set origin
            $x0 = $this->xorigin + $col * (round($this->wi) + round($this->hspacing));
            $y0 = $this->yorigin + $row * (round($this->he) + round($this->vspacing));

            // Logo X position
            $xl = round($x0 + $this->wi - $this->wlogo);

            // Baneer max height
            $this->ban_max_he = 12;

            // Get data
            $email = '';
            switch ($this->preferences->pref_card_address) {
                case 0:
                    $email .= $member->email;
                    break;
                case 5:
                    $email .= $member->zipcode . ' - ' . $member->town;
                    break;
                case 6:
                    $email .= $member->nickname;
                    break;
                case 7:
                    $email .= $member->job;
                    break;
                case 8:
                    $email .= $member->number;
                    break;
            }

            // Select strip color
            $fcol = ['R' => 0, 'G' => 0, 'B' => 0];
            if ($status_list[$member->status]['extra'] <= Members::NON_STAFF_MEMBERS) {
                $fcol = $this->bcol;
            } elseif (
                $member->status == 5 /*Benefactor member*/
                || $member->status === 6 /*Founder member*/
            ) {
                $fcol = $this->hcol;
            } elseif ($member->isActive()) {
                $fcol = $this->scol;
            }

            $nom_adh_ext = '';
            if ($this->preferences->pref_bool_display_title) {
                $nom_adh_ext .= $member->stitle . ' ';
            }
            $nom_adh_ext .= $member->sname;
            $photo = $member->picture;
            $photofile = $photo->getPath();


            // Photo 100x130 (Add a mask to crop 1/1.3)
            $this->hphoto = round($this->he * 0.75);
            $this->wphoto = $this->hphoto / 1.30;
            if ($this->wphoto > $this->wi / 3) {
                $this->wphoto = $this->wi / 3;
                $this->hphoto = $this->wphoto * 1.3;
            }
            $this->Rect($x0 + 1, $y0 + 1, $this->wphoto, $this->hphoto, 'F', ['width' => 0.0, 'cap' => 'butt', 'join' => 'miter', 'dash' => 4, 'color' => [255, 255, 255]], [255, 255, 255]);
            $this->Image($photofile, $x0 + 1, $y0 + 1, $this->wphoto, $this->hphoto, '', '', '', false, 300, '', false, false, 0, 'T', false, false);

            // Logo
            $this->Image($this->logofile, $xl - 1, $y0 + 1, round($this->wlogo));

            // Define max space for text
            $this->max_text_size_top = $this->max_text_size_full - intval($this->wlogo + $this->wphoto);
            $this->max_text_size_center = $this->max_text_size_full - intval($this->wphoto);

            // Color=#8C8C8C: Shadow of the year
            $this->SetTextColor(140);

            //Write shadow of the year, center of the card
            $an_cot = $this->an_cot;
            if ($an_cot === 'DEADLINE') {
                //get current member deadline
                $an_cot = $member->due_date ?? '';
            }
            $this->fixSize(
                $an_cot,
                intval(round($this->max_text_size_top * 0.80, PHP_ROUND_HALF_DOWN)),
                14,
                'B'
            );
            $this->year_font_size = intval(round($this->FontSizePt, PHP_ROUND_HALF_DOWN));
            $xan_cot = $x0 + $this->wphoto + $this->max_text_size_top / 2 - $this->GetStringWidth(
                $an_cot,
                self::FONT,
                'B',
                $this->year_font_size
            ) / 2;
            $this->SetXY($xan_cot, $y0 + 1);
            $this->writeHTML('<strong>' . $an_cot . '</strong>', false, false);

            // Colored Text (Big label, id, year)
            $this->SetTextColor($fcol['R'], $fcol['G'], $fcol['B']);

            //Write the year, center of the card
            $this->SetFontSize($this->year_font_size);
            $xan_cot = $xan_cot - 0.1;
            $this->SetXY($xan_cot, $y0 + 1 - 0.1);
            $this->writeHTML('<strong>' . $an_cot . '</strong>', false, false);

            //Write member number, center of available space
            $this->SetFontSize(8);
            $this->SetFont(self::FONT, 'B');
            $this->ratio = $this->wlogo / $this->hlogo;

            $member_id = (!empty($member->number)) ? $member->number : $member->id;
            $this->adh_nbr = _T("Member") . ' n° : ' . $member_id;
            if ($this->hlogo + 1 > 7) {
                $this->fixSize(
                    $this->adh_nbr,
                    intval(round($this->max_text_size_top * 0.9, PHP_ROUND_HALF_DOWN)),
                    8,
                    'B'
                );
                $this->SetFontSize(round($this->FontSizePt, 0, PHP_ROUND_HALF_DOWN));
                $xid = $x0 + $this->wi / 2 - $this->GetStringWidth($this->adh_nbr, $this->FontFamily, $this->FontStyle, $this->FontSizePt) / 2 + $this->wphoto / 2 - $this->wlogo / 2;
                $this->SetXY($xid, $y0 + 8);
            } else {
                $this->fixSize(
                    $this->adh_nbr,
                    intval(round($this->max_text_size_center * 0.9, PHP_ROUND_HALF_DOWN)),
                    10,
                    'B'
                );
                $xid = $x0 + $this->wi / 2 - $this->GetStringWidth($this->adh_nbr, $this->FontFamily, $this->FontStyle, $this->FontSizePt) / 2 + $this->wphoto / 2;
                $this->SetXY($xid, $y0 + 8);
            }
            $this->SetFontSize(round($this->FontSizePt, 0, PHP_ROUND_HALF_DOWN));
            $this->writeHTML('<strong>' . $this->adh_nbr . '  </strong>', false, false);

            // Abbrev: Adapt font size to text length
            if (13 < $this->hlogo + 1) {
                $this->fixSize(
                    $this->abrev,
                    intval(round($this->max_text_size_top * 0.9, PHP_ROUND_HALF_DOWN)),
                    12,
                    'B'
                );

                $this->SetFontSize(round($this->FontSizePt, 0, PHP_ROUND_HALF_DOWN));
                $xid = $x0 + $this->wi / 2 - $this->GetStringWidth($this->abrev, $this->FontFamily, $this->FontStyle, $this->FontSizePt) / 2 + $this->wphoto / 2 - $this->wlogo / 2;
            } else {
                $this->fixSize(
                    $this->abrev,
                    intval(round($this->max_text_size_center * 0.9, PHP_ROUND_HALF_DOWN)),
                    12,
                    'B'
                );
                $this->SetFontSize(round($this->FontSizePt, 0, PHP_ROUND_HALF_DOWN));
                $xid = $x0 + $this->wi / 2 - $this->GetStringWidth($this->abrev, $this->FontFamily, $this->FontStyle, $this->FontSizePt) / 2 + $this->wphoto / 2;
            }
            $this->SetXY($xid, $y0 + 13);
            $this->writeHTML('<strong>' . $this->abrev . '</strong>', true, false);

            // Name: Adapt font size to text length on one line, if font is to small on two lines
            $this->SetTextColor(0);
            $this->fixSize(
                $nom_adh_ext,
                intval(round($this->max_text_size_center * 0.9, PHP_ROUND_HALF_DOWN)),
                8,
                'B'
            );
            $this->SetFontSize(round($this->FontSizePt, 0, PHP_ROUND_HALF_DOWN));
            if ($this->hlogo + 2 >= + 20) {
                $this->SetXY($x0 + $this->wphoto + 2, $y0 + $this->hlogo + 2);
            } else {
                $this->SetXY($x0 + $this->wphoto + 2, $y0 + 20);
            }
            $this->SetFontSize(round($this->FontSizePt, 0, PHP_ROUND_HALF_DOWN));
            if ($this->FontSizePt <= 7) {
                $nom_adh_ext = '';
                if ($this->preferences->pref_bool_display_title) {
                    $nom_adh_ext .= $member->stitle . ' ';
                }
                $nom_adh_ext .= mb_strtoupper($member->name);
                $this->fixSize(
                    $nom_adh_ext,
                    intval(round($this->max_text_size_center * 0.9, PHP_ROUND_HALF_DOWN)),
                    7,
                    'B'
                );
                $this->SetFontSize(round($this->FontSizePt, 0, PHP_ROUND_HALF_DOWN));
                $this->writeHTML('<strong>' . $nom_adh_ext . '</strong>', true, false);
                $this->SetX($x0 + $this->wphoto + 2);
                $this->SetFontSize(7);

                $this->fixSize(
                    $member->surname,
                    intval(round($this->max_text_size_center * 0.9, PHP_ROUND_HALF_DOWN)),
                    7,
                    'B'
                );
                $this->SetFontSize(round($this->FontSizePt, 0, PHP_ROUND_HALF_DOWN));
                $this->writeHTML('<strong>' . $member->surname . '</strong>', true, false);
                if ($this->he < 44) {
                    $this->ban_max_he = 10;
                }
            } else {
                $this->writeHTML('<strong>' . $nom_adh_ext . '</strong>', true, false);
            }

            // Email (adapt too)
            $this->fixSize(
                $email,
                intval(round($this->max_text_size_center * 0.9, PHP_ROUND_HALF_DOWN)),
                6,
                'B'
            );
            $this->setX($x0 + $this->wphoto + 2);
            $this->writeHTML('<strong>' . $email . '</strong>', false, false);
            $this->email_y = $this->getY();

            // Lower colored strip with long text
            if ($this->wi < 73) {
                $this->max_text_size_full = (int)round($this->max_text_size_full * 0.95, PHP_ROUND_HALF_DOWN);
            }
            $this->SetFont(self::FONT, 'B');
            $this->fixSize(
                $this->preferences->pref_card_strip,
                $this->max_text_size_full,
                12,
                'B'
            );


            $this->SetFontSize(round($this->FontSizePt, 0, PHP_ROUND_HALF_DOWN));

            $this->SetFillColor($fcol['R'], $fcol['G'], $fcol['B']);
            $this->SetTextColor(
                $this->tcol['R'],
                $this->tcol['G'],
                $this->tcol['B']
            );
            //Set strip 20% height
            $this->cell_he = round($this->he * 0.20);
            if ($this->cell_he >= $this->ban_max_he) {
                $this->cell_he = $this->ban_max_he;
            }
            //Verify if strip is not over the last writen line
            if ($y0 + $this->he - $this->cell_he - 2.5 <= $this->email_y) {
                $this->cell_he = $y0 + $this->he - $this->email_y - 3;
            }
            //In case of a small strip adapt font size
            if ($this->cell_he < $this->FontSize) {
                $this->SetFontsize(round($this->cell_he * 2.83, 0, PHP_ROUND_HALF_DOWN));
            }

            $this->SetXY($x0, $y0 + $this->he - $this->cell_he);
            $this->Cell(
                $this->wi,
                $this->cell_he,
                $this->preferences->pref_card_strip,
                0,
                0,
                'C',
                true
            );

            // Draw a gray frame around the card
            $this->Rect($x0, $y0, $this->wi, $this->he);
            $nb_card++;
        }
    }

    /**
     * Get card width
     *
     * @return integer
     */
    public static function getWidth(): int
    {
        global $preferences;

        return $preferences->pref_card_hsize;
    }

    /**
     * Get card height
     *
     * @return integer
     */
    public static function getHeight(): int
    {
        global $preferences;

        return $preferences->pref_card_vsize;
    }

    /**
     * Get number of columns
     *
     * @return integer
     */
    public static function getCols(): int
    {
        global $preferences;

        $margins = $preferences->pref_card_marges_h * 2;

        $nbcols = (int)round(
            ((self::PAGE_WIDTH - $margins) / $preferences->pref_card_hsize),
            0,
            PHP_ROUND_HALF_DOWN
        );
        if ((($nbcols - 1) * $preferences->pref_card_hspace + $margins + $preferences->pref_card_hsize * $nbcols) > self::PAGE_WIDTH) {
            --$nbcols;
        }

        return $nbcols;
    }

    /**
     * Get number of rows
     *
     * @return integer
     */
    public static function getRows(): int
    {
        global $preferences;

        $margins = $preferences->pref_card_marges_v * 2;

        $nbrows = (int)round(
            ((self::PAGE_HEIGHT - $margins) / $preferences->pref_card_vsize),
            0,
            PHP_ROUND_HALF_DOWN
        );
        if ((($nbrows - 1) * $preferences->pref_card_vspace + $margins + $preferences->pref_card_vsize * $nbrows) > self::PAGE_HEIGHT) {
            --$nbrows;
        }
        // Put two times in case of sum of the pref_card_vspace is greater than a pref_card_vsize
        if ((($nbrows - 1) * $preferences->pref_card_vspace + $margins + $preferences->pref_card_vsize * $nbrows) > self::PAGE_HEIGHT) {
            --$nbrows;
        }

        return $nbrows;
    }
}
