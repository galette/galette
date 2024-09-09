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

declare(strict_types=1);

namespace Galette\IO;

use Galette\Core\I18n;
use Galette\Core\Preferences;
use Galette\Entity\PdfModel;
use Analog\Analog;
use Slim\Routing\RouteParser;
use TCPDF;

/*
 * TCPDF configuration file for Galette
 */
require_once GALETTE_SYSCONFIG_PATH . 'galette_tcpdf_config.php';

/**
 * PDF class for galette
 *
 * @author John Perr <johnperr@abul.org>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class Pdf extends TCPDF
{
    public const FONT = 'DejaVuSans';
    public const FONT_SIZE = 10;

    protected Preferences $preferences;
    protected I18n $i18n;
    private PdfModel $model;
    private bool $paginated = false;
    protected string $filename;
    private bool $has_footer = true;
    protected float $footer_height;

    /**
     * Main constructor, set creator and author
     *
     * @param Preferences $prefs Preferences
     * @param ?PdfModel   $model Related model
     */
    public function __construct(Preferences $prefs, ?PdfModel $model = null)
    {
        global $i18n;

        $this->preferences = $prefs;
        $this->i18n = $i18n;
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8');
        //set some values
        $this->SetCreator(PDF_CREATOR);
        //add helvetica, hard-called from lib
        $this->SetFont('helvetica');
        //and then, set real font
        $this->SetFont(self::FONT, '', self::FONT_SIZE);
        $name = preg_replace(
            '/%s/',
            $this->preferences->pref_nom,
            _T("Association %s")
        );
        $this->SetAuthor(
            $name . ' (using Galette ' . GALETTE_VERSION . ')'
        );

        if ($this->i18n->isRTL()) {
            $this->setRTL(true);
        }

        if ($model !== null) {
            $this->model = $model;
            $this->SetTitle($this->model->htitle);
        }

        $this->init();
        if ($this->has_footer) {
            $this->calculateFooterHeight();
        }
    }

    /**
     * Initialize PDF
     *
     * @return void
     */
    public function init(): void
    {
        $this->Open();
        $this->AddPage();
    }

    /**
     * No header
     *
     * @return void
     */
    protected function setNoHeader(): void
    {
        $this->SetPrintHeader(false);
        $this->setHeaderMargin(0);
    }

    /**
     * No footer
     *
     * @return void
     */
    protected function setNoFooter(): void
    {
        $this->SetPrintFooter(false);
        $this->setFooterMargin(0);
        $this->has_footer = false;
    }

    /**
     * Calculate footer height
     *
     * @return void
     */
    private function calculateFooterHeight(): void
    {
        $pdf = clone $this;
        $y_orig = $pdf->getY();
        $this->Footer($pdf);
        $y_end = $pdf->getY();
        $this->footer_height = $y_end - $y_orig;
    }

    /**
     * Set show pagination
     *
     * @return void
     */
    public function showPagination(): void
    {
        $this->paginated = true;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        parent::__destruct();
    }

    /**
     * This method is automatically called in case of fatal error;
     * it simply outputs the message and halts the execution.
     * An inherited class may override it to customize the error
     * handling but should always halt the script, or the resulting
     * document would probably be invalid.
     * 2004-06-11 :: Nicola Asuni : changed bold tag with strong
     * 2007-07-21 :: John Perr : changed function to return error to session
     * 2017-02-14 :: Johan Cwiklinski : use slim's flash message; do not rely on session for redirect
     *
     * @param string $msg The error message
     *
     * @return void
     * @access public
     * @since 1.0
     */
    public function Error(mixed $msg): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        global $container;

        Analog::log(
            'PDF error: ' . $msg,
            Analog::ERROR
        );

        $container->get('flash')->addMessage(
            'error_detected',
            $msg
        );

        $redirect = (isset($_SERVER['HTTP_REFERER']) ?
            $_SERVER['HTTP_REFERER'] : $container->get(RouteParser::class)->urlFor('slash'));
        header('Location: ' . $redirect);
        die();
    }

    /**
     * Converts color from HTML format #RRVVBB
     * to RGB 3 colors array.
     *
     *  @param string $hex6 7 chars string #RRVVBB
     *
     * @return array<string,float|int>
     */
    public function colorHex2Dec(string $hex6): array
    {
        $dec = array(
            "R" => hexdec(substr($hex6, 1, 2)),
            "G" => hexdec(substr($hex6, 3, 2)),
            "B" => hexdec(substr($hex6, 5, 2))
        );
        return $dec;
    }

    /**
     * Draws PDF page Header
     *
     * @return void
     */
    public function Header(): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        //just override default header to prevent black line at top
    }

    /**
     * Draws PDF page footer
     *
     * @param ?TCPDF $pdf PDF instance
     *
     * @return void
     */
    public function Footer(?TCPDF $pdf = null): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        if ($pdf === null) {
            $pdf = $this;
            $pdf->SetY(-($this->footer_height + 15));
        }
        if (isset($this->model)) {
            $hfooter = '';
            if (trim($this->model->hstyles) !== '') {
                $hfooter .= "<style>\n" . $this->model->hstyles . "\n</style>\n\n";
            }
            $hfooter .= $this->model->hfooter;
            $pdf->writeHtml($hfooter);
        } else {
            $address = $this->preferences->getPostalAddress();
            $hfooter = '<style>div#pdf_footer {text-align: center;font-size: 0.7em;}</style>';
            $hfooter .= '<div id="pdf_footer">' . nl2br($address) . '</div>';
            $pdf->writeHTML($hfooter);
        }

        if ($this->paginated) {
            $pdf->SetFont(self::FONT, '', self::FONT_SIZE - 3);
            $pdf->Ln();
            $pdf->Cell(
                0,
                4,
                $this->getAliasRightShift() . $this->PageNo() .
                '/' . $this->getAliasNbPages(),
                0,
                1,
                ($this->i18n->isRTL() ? 'L' : 'R')
            );
        }
    }

    /**
     * Draws PDF page header
     *
     * @param ?string $title Additional title to display just after logo
     *
     * @return void
     */
    public function PageHeader(?string $title = null): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        if (isset($this->model)) {
            $this->modelPageHeader($title);
        } else {
            $this->standardPageHeader($title);
        }
    }

    /**
     * Draws models PDF page header
     *
     * @param ?string $title Additional title to display just after logo
     *
     * @return void
     */
    protected function modelPageHeader(?string $title = null): void
    {
        $html = null;
        if (trim($this->model->hstyles) !== '') {
            $html .= "<style>\n" . $this->model->hstyles . "\n</style>\n\n";
        }
        $html .= "<div dir=\"" . ($this->i18n->isRTL() ? 'rtl' : 'ltr') . "\">" . $this->model->hheader . "</div>";
        $this->writeHtml($html, true, false, true, false, '');

        if ($title !== null) {
            $this->writeHtml('<h2 style="text-align:center;">' . $title . '</h2>');
        }

        if (trim($this->model->title) !== '') {
            $htitle = '';
            if (trim($this->model->hstyles) !== '') {
                $htitle .= "<style>\n" . $this->model->hstyles .
                    "\n</style>\n\n";
            }
            $htitle .= '<div id="pdf_title">' . $this->model->htitle . '</div>';
            $this->writeHtml($htitle);
        }
        if (trim($this->model->subtitle) !== '') {
            $hsubtitle = '';
            if (trim($this->model->hstyles) !== '') {
                $hsubtitle .= "<style>\n" . $this->model->hstyles .
                    "\n</style>\n\n";
            }
            $hsubtitle .= '<div id="pdf_subtitle">' . $this->model->hsubtitle .
                '</div>';
            $this->writeHtml($hsubtitle);
        }
        if (
            trim($this->model->title) !== ''
            || trim($this->model->subtitle) !== ''
        ) {
            $this->Ln(5);
        }
    }

    /**
     * Draws standard PDF page header
     *
     * @param ?string $title Additional title to display just after logo
     *
     * @return void
     */
    protected function standardPageHeader(?string $title = null): void
    {
        //default header
        $print_logo = new \Galette\Core\PrintLogo();
        $logofile = $print_logo->getPath();

        // Set logo size to max width 30 mm or max height 25 mm
        $ratio = $print_logo->getWidth() / $print_logo->getHeight();
        if ($ratio < 1) {
            if ($print_logo->getHeight() > 16) {
                $hlogo = 25;
            } else {
                $hlogo = $print_logo->getHeight();
            }
            $wlogo = round($hlogo * $ratio);
        } else {
            if ($print_logo->getWidth() > 16) {
                $wlogo = 30;
            } else {
                $wlogo = $print_logo->getWidth();
            }
            $hlogo = round($wlogo / $ratio);
        }

        $this->SetFont(self::FONT, 'B', self::FONT_SIZE + 4);
        $this->SetTextColor(0, 0, 0);

        $y = $this->GetY();
        $this->Ln(2);
        $ystart = $this->GetY();

        $this->MultiCell(
            180 - $wlogo,
            6,
            $this->preferences->pref_nom,
            0,
            ($this->i18n->isRTL() ? 'R' : 'L')
        );
        $this->SetFont(self::FONT, 'B', self::FONT_SIZE + 2);

        if ($title !== null) {
            $this->Cell(0, 6, $title, 0, 1, ($this->i18n->isRTL() ? 'R' : 'L'), false);
        }
        $yend = $this->getY(); //store position at the end of the text

        $this->SetY($ystart);
        if ($this->i18n->isRTL()) {
            $x = $this->getX();
        } else {
            $x = 190 - $wlogo; //right align
        }
        $this->Image($logofile, $x, $this->GetY(), $wlogo, $hlogo);
        $this->y += $hlogo + 3;
        //if position after logo is < than position after text,
        //we have to change y
        if ($this->getY() < $yend) {
            $this->setY($yend);
        }
    }

    /**
     * Draws body from model
     *
     * @return void
     */
    public function PageBody(): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        $hbody = '';
        if (trim($this->model->hstyles) !== '') {
            $hbody .= "<style>\n" . $this->model->hstyles . "\n</style>\n\n";
        }
        $hbody .= $this->model->hbody;
        $this->writeHtml($hbody);
    }

    /**
     * Fix text size
     *
     * @param string  $text      Text content
     * @param integer $maxsize   Maximal size
     * @param integer $fontsize  Font size
     * @param string  $fontstyle Font style (defaults to '')
     * @param ?string $fontname  Font name (defaults to static::FONT)
     *
     * @return void
     */
    protected function fixSize(
        string $text,
        int $maxsize,
        int $fontsize,
        string $fontstyle = '',
        ?string $fontname = null
    ): void {
        if ($fontname === null) {
            $fontname = static::FONT;
        }
        $this->SetFontSize($fontsize);
        while ((int)$this->GetStringWidth($text, $fontname, $fontstyle, $fontsize) > $maxsize) {
            $fontsize--;
            $this->SetFontSize($fontsize);
        }
    }

    /**
     * Cut a string
     *
     * @param string  $str    Original string
     * @param integer $length Max length
     *
     * @return string
     */
    protected function cut(string $str, int $length): string
    {
        $length = $length - 2; //keep a margin
        if ((int)$this->GetStringWidth($str) > $length) {
            while ((int)$this->GetStringWidth($str . '...') > $length) {
                $str = mb_substr($str, 0, -1, 'UTF-8');
            }
            $str .= '...';
        }
        return $str;
    }

    /**
     * Stretch a header string
     *
     * @param string  $str    Original string
     * @param integer $length Max length
     *
     * @return string
     */
    protected function stretchHead(string $str, int $length): string
    {
        $this->SetFont(self::FONT, 'B', self::FONT_SIZE);
        $stretch = 100;
        if ((int)$this->GetStringWidth($str) > $length) {
            while ((int)$this->GetStringWidth($str) > $length) {
                $this->setFontStretching(--$stretch);
            }
        }
        return $str;
    }

    /**
     * Get filename
     *
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * Download PDF from browser
     *
     * @return string
     */
    public function download(): string
    {
        return $this->Output($this->filename, 'D');
    }
}
