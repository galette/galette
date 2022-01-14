<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * PDF class for galette
 * Traps tcpdf errors by overloading tcpdf::error method
 * Adds convenient method to convert color html codes
 * Adds a _parsegif function to convert gif to png
 *
 * PHP version 5
 *
 * Copyright © 2007-2014 The Galette Team
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
 * @category  IO
 * @package   Galette
 *
 * @author    John Perr <johnperr@abul.org>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-07-21
 */

namespace Galette\IO;

use Galette\Core\Preferences;
use Galette\Entity\PdfModel;
use Analog\Analog;

/*
 * TCPDF configuration file for Galette
 */
require_once GALETTE_CONFIG_PATH . 'galette_tcpdf_config.php';

/**
 * PDF class for galette
 *
 * @category  IO
 * @name      PDF
 * @package   Galette
 * @abstract  Class for expanding TCPDF.
 * @author    John Perr <johnperr@abul.org>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-07-21
 */

class Pdf extends \TCPDF
{
    public const FONT = 'DejaVuSans';
    public const FONT_SIZE = 10;

    protected $preferences;
    protected $i18n;
    private $model;
    private $paginated = false;
    protected $filename;

    /**
     * Main constructor, set creator and author
     *
     * @param Preferences $prefs Preferences
     * @param PdfModel    $model Related model
     */
    public function __construct(Preferences $prefs, $model = null)
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
            if ($model instanceof PdfModel) {
                $this->model = $model;
                $this->SetTitle($this->model->htitle);
            } else {
                throw new \UnexpectedValueException(
                    'Provided model must be an instance of PdfModel!'
                );
            }
        }
    }

    /**
     * Set show pagination
     *
     * @return void
     */
    public function showPagination()
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
    public function Error($msg) // phpcs:ignore PSR1.Methods.CamelCapsMethodName
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
            $_SERVER['HTTP_REFERER'] : $container->get('router')->pathFor('slash'));
        header('Location: ' . $redirect);
        die();
    }

    /**
     * Converts color from HTML format #RRVVBB
     * to RGB 3 colors array.
     *
     *  @param string $hex6 7 chars string #RRVVBB
     *
     * @return array
     */
    public function colorHex2Dec($hex6)
    {
        $dec = array(
            "R" => hexdec(substr($hex6, 1, 2)),
            "G" => hexdec(substr($hex6, 3, 2)),
            "B" => hexdec(substr($hex6, 5, 2))
        );
        return $dec;
    }

    /** FIXME: is this function used somewhere? */
    /**
     * Extract info from a GIF file
     * (In fact: converts gif image to png and feeds it to _parsepng)
     *
     * @param string $file path to the gif file
     *
     * @return void
     * @access protected
     */
    protected function parsegif($file)
    {
        $a = getimagesize($file);
        if (empty($a)) {
            $this->Error(_T("Missing or incorrect image file ") . $file);
        }
        if ($a[2] != 1) {
            $this->Error(_T("Not a GIF file ") . $file);
        }

        // Tentative d'ouverture du fichier
        if (function_exists('gd_info')) {
            $data = @imagecreatefromgif($file);

            // Test d'échec & Affichage d'un message d'erreur
            if (!$data) {
                    $this->Error(_T("Error loading ") . $file);
            }
            if (imagepng($data, GALETTE_ROOT . 'tempimages/gif2png.png')) {
                return $this->_parsepng(GALETTE_ROOT . 'tempimages/gif2png.png');
            } else {
                $this->Error(_T("Error creating temporary png file from ") . $file);
            }
        } else {
            $this->Error(_T("Unable to convert GIF file ") . $file);
        }
    }

    /**
     * Draws PDF page Header
     *
     * @return void
     */
    public function Header() // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        //just ovverride default header to prevent black line at top
    }

    /**
     * Draws PDF page footer
     *
     * @return void
     */
    public function Footer() // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        $this->SetY(-20);
        if (isset($this->model)) {
            $hfooter = '';
            if (trim($this->model->hstyles) !== '') {
                $hfooter .= "<style>\n" . $this->model->hstyles . "\n</style>\n\n";
            }
            $hfooter .= $this->model->hfooter;
            $this->writeHtml($hfooter);
        } else {
            $this->SetFont(self::FONT, '', self::FONT_SIZE - 2);
            $this->SetTextColor(0, 0, 0);

            $name = preg_replace(
                '/%s/',
                $this->preferences->pref_nom,
                _T("Association %s")
            );

            $address = $this->preferences->getPostalAddress();

            $this->MultiCell(
                0,
                4,
                $address,
                0,
                'C'
            );

            if ($this->paginated) {
                $this->SetFont(self::FONT, '', self::FONT_SIZE - 3);
                $this->Ln();
                $this->Cell(
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
    }

    /**
     * Draws PDF page header
     *
     * @param string $title Additionnal title to display just after logo
     *
     * @return void
     */
    public function PageHeader($title = null) // phpcs:ignore PSR1.Methods.CamelCapsMethodName
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
     * @param string $title Additionnal title to display just after logo
     *
     * @return void
     */
    protected function modelPageHeader($title = null)
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
     * @param string $title Additionnal title to display just after logo
     *
     * @return void
     */
    protected function standardPageHeader($title = null)
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
            $this->Cell(0, 6, $title, 0, 1, ($this->i18n->isRTL() ? 'R' : 'L'), 0);
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
    public function PageBody() // phpcs:ignore PSR1.Methods.CamelCapsMethodName
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
     * @param string  $fontname  Font name (defaults to static::FONT)
     *
     * @return void
     */
    protected function fixSize($text, $maxsize, $fontsize, $fontstyle = '', $fontname = null)
    {
        if ($fontname === null) {
            $fontname = static::FONT;
        }
        $this->SetFontSize($fontsize);
        while ($this->GetStringWidth($text, $fontname, $fontstyle, $fontsize) > $maxsize) {
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
    protected function cut($str, $length)
    {
        $length = $length - 2; //keep a margin
        if ($this->GetStringWidth($str) > $length) {
            while ($this->GetStringWidth($str . '...') > $length) {
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
    protected function stretchHead($str, $length)
    {
        $this->SetFont(self::FONT, 'B', self::FONT_SIZE);
        $stretch = 100;
        if ($this->GetStringWidth($str) > $length) {
            while ($this->GetStringWidth($str) > $length) {
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
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Download PDF from browser
     *
     * @return string
     */
    public function download()
    {
        $this->Output($this->filename, 'D');
    }
}
