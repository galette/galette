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
 * Copyright © 2007-2013 The Galette Team
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
 * @copyright 2007-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-07-21
 */

namespace Galette\IO;

use Galette\Entity\PdfModel;
use Analog\Analog;

/*
 * TCPDF configuration file for Galette
 */
require_once GALETTE_CONFIG_PATH . 'galette_tcpdf_config.php';

/**
 *  Require TCPDF class
 */
require_once GALETTE_TCPDF_PATH . '/tcpdf.php';

/**
 * PDF class for galette
 *
 * @category  IO
 * @name      PDF
 * @package   Galette
 * @abstract  Class for expanding TCPDF.
 * @author    John Perr <johnperr@abul.org>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-07-21
 */

class Pdf extends \TCPDF
{

    const FONT='DejaVuSans';
    const FONT_SIZE=10;

    private $_model;
    private $_paginated = false;

    /**
     * Main constructor, set creator and author
     *
     * @param PdfModel $model Related model
     */
    public function __construct($model = null)
    {
        global $preferences;

        parent::__construct('P', 'mm', 'A4', true, 'UTF-8');
        //set some values
        $this->SetCreator(PDF_CREATOR);
        $this->SetFont(self::FONT, '', self::FONT_SIZE);
        $name = preg_replace(
            '/%s/',
            $preferences->pref_nom,
            _T("Association %s")
        );
        $this->SetAuthor(
            $name . ' (using Galette ' . GALETTE_VERSION .
            'and TCPDF ' . TCPDF_VERSION . ')'
        );

        if ( $model !== null ) {
            if ( $model instanceof PdfModel ) {
                $this->_model = $model;
                $this->SetTitle($this->_model->htitle);
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
        $this->_paginated = true;
    }

    /**
    * Destructeur de la classe PDF
    *
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
    *
    * @param string $msg The error message
    *
    * @return void
    * @access public
    * @since 1.0
    */
    public function Error($msg)
    {
        global $session;
        /** FIXME: I do not really like this, we should find sthing better */
        $session['pdf_error'] = true;
        $session['pdf_error_msg'] = $msg;
        Analog::log(
            'PDF error: ' .$msg,
            Analog::ERROR
        );
        header("location:" . $session['caller']);
        die();
    }

    /**
    * Fonction de conversion d'une couleur au format HTML
    * #RRVVBB en un tableau de 3 valeurs comprises dans
    * l'interval [0;255]
    *
    * @param string $hex6 chaine de 7 caratères #RRVVBB
    *
    * @return tableau de 3 valeur R, G et B comprises entre 0 et 255
    * @access public
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
        $a=GetImageSize($file);
        if ( empty($a) ) {
            $this->Error(_T("Missing or incorrect image file ") . $file);
        }
        if ( $a[2]!=1 ) {
            $this->Error(_T("Not a GIF file ") . $file);
        }

        // Tentative d'ouverture du fichier
        if ( function_exists('gd_info') ) {
            $data = @imagecreatefromgif($file);

            // Test d'échec & Affichage d'un message d'erreur
            if (!$data) {
                    $this->Error(_T("Error loading ").$file);
            }
            if (Imagepng($data, GALETTE_ROOT . 'tempimages/gif2png.png') ) {
                return $this->_parsepng(GALETTE_ROOT . 'tempimages/gif2png.png');
            } else {
                $this->Error(_T("Error creating temporary png file from ").$file);
            }
        } else {
            $this->Error(_T("Unable to convert GIF file ").$file);
        }
    }

    /**
     * Draws PDF page Header
     *
     * @return void
     */
    function Header()
    {
        //just ovverride default header to prevent black line at top
    }

    /**
     * Draws PDF page footer
     *
     * @return void
     */
    function Footer()
    {
        global $preferences;

        $this->SetY(-20);
        if ( isset($this->_model) ) {
            $hfooter = '';
            if ( trim($this->_model->hstyles) !== '' ) {
                $hfooter .= "<style>\n" . $this->_model->hstyles . "\n</style>\n\n";
            }
            $hfooter .= $this->_model->hfooter;
            $this->writeHtml($hfooter);
        } else {
            $this->SetFont(self::FONT, '', self::FONT_SIZE);
            $this->SetTextColor(0, 0, 0);

            $name = preg_replace(
                '/%s/',
                $preferences->pref_nom,
                _T("Association %s")
            );

            $coordonnees_line1 = $name . ' - ' . $preferences->pref_adresse;
            /** FIXME: pref_adresse2 should be removed */
            if ( trim($preferences->pref_adresse2) != '' ) {
                $coordonnees_line1 .= ', ' . $preferences->pref_adresse2;
            }
            $coordonnees_line2 = $preferences->pref_cp . ' ' . $preferences->pref_ville;

            $this->Cell(0, 4, $coordonnees_line1, 0, 1, 'C', 0, $preferences->pref_website);
            $this->Cell(0, 4, $coordonnees_line2, 0, 0, 'C', 0, $preferences->pref_website);

            if ( $this->_paginated ) {
                $this->SetFont(self::FONT, '', self::FONT_SIZE - 3);
                $this->Ln();
                $this->Cell(0, 4, $this->getAliasRightShift().$this->PageNo() . '/' . $this->getAliasNbPages(), 0, 1, 'R');
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
    function PageHeader($title = null)
    {
        global $preferences;

        if ( isset($this->_model) ) {
            $html = null;
            if ( trim($this->_model->hstyles) !== '' ) {
                $html .= "<style>\n" . $this->_model->hstyles . "\n</style>\n\n";
            }
            $html .= $this->_model->hheader;
            $this->writeHtml($html, true, false, true, false, '');
            if ( trim($this->_model->title) !== '' ) {
                $htitle = '';
                if ( trim($this->_model->hstyles) !== '' ) {
                    $htitle .= "<style>\n" . $this->_model->hstyles .
                        "\n</style>\n\n";
                }
                $htitle .= '<div id="pdf_title">' . $this->_model->htitle . '</div>';
                $this->writeHtml($htitle);
            }
            if ( trim($this->_model->subtitle) !== '' ) {
                $hsubtitle = '';
                if ( trim($this->_model->hstyles) !== '' ) {
                    $hsubtitle .= "<style>\n" . $this->_model->hstyles .
                        "\n</style>\n\n";
                }
                $hsubtitle .= '<div id="pdf_subtitle">' . $this->_model->hsubtitle .
                    '</div>';
                $this->writeHtml($hsubtitle);
            }
            if ( trim($this->_model->title) !== ''
                || trim($this->_model->subtitle) !== ''
            ) {
                $this->Ln(5);
            }
        } else {
            //default header
            $print_logo = new \Galette\Core\PrintLogo();
            if ( $print_logo->hasPicture() ) {
                $logofile = $print_logo->getPath();

                // Set logo size to max width 30 mm or max height 25 mm
                $ratio = $print_logo->getWidth()/$print_logo->getHeight();
                if ( $ratio < 1 ) {
                    if ( $print_logo->getHeight() > 16 ) {
                        $hlogo = 25;
                    } else {
                        $hlogo = $print_logo->getHeight();
                    }
                    $wlogo = round($hlogo*$ratio);
                } else {
                    if ( $print_logo->getWidth() > 16 ) {
                        $wlogo = 30;
                    } else {
                        $wlogo = $print_logo->getWidth();
                    }
                    $hlogo = round($wlogo/$ratio);
                }
            }

            $this->SetFont(self::FONT, 'B', self::FONT_SIZE + 4);
            $this->SetTextColor(0, 0, 0);

            $y = $this->GetY();
            $this->Ln(2);
            $ystart = $this->GetY();

            $this->Cell(
                0,
                6,
                $preferences->pref_nom,
                0,
                1,
                'L',
                0,
                $preferences->pref_website
            );
            $this->SetFont(self::FONT, 'B', self::FONT_SIZE + 2);

            if ( $title !== null ) {
                $this->Cell(0, 6, $title, 0, 1, 'L', 0);
            }
            $yend = $this->getY();//store position at the end of the text

            $this->SetY($ystart);
            $x = 190 - $wlogo; //right align
            $this->Image($logofile, $x, $this->GetY(), $wlogo, $hlogo);
            $this->y += $hlogo + 3;
            //if position after logo is < than position after text,
            //we have to change y
            if ( $this->getY() < $yend ) {
                $this->setY($yend);
            }
        }
    }

    /**
     * Draws body from model
     *
     * @return void
     */
    public function PageBody()
    {
        $hbody = '';
        if ( trim($this->_model->hstyles) !== '' ) {
            $hbody .= "<style>\n" . $this->_model->hstyles . "\n</style>\n\n";
        }
        $hbody .= $this->_model->hbody;
        $this->writeHtml($hbody);
    }
}
