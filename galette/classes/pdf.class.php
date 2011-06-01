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
 * Copyright © 2007-2011 The Galette Team
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
 * @category  Classes
 * @package   Galette
 *
 * @author    John Perr <johnperr@abul.org>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-07-21
 */

/**
 *  Require TCPDF class
 */
require_once WEB_ROOT . 'includes/tcpdf_' . TCPDF_VERSION . '/tcpdf.php';
require_once WEB_ROOT . 'classes/print_logo.class.php';

/**
 * PDF class for galette
 *
 * @category  Classes
 * @name      PDF
 * @package   Galette
 * @abstract  Class for expanding TCPDF.
 * @author    John Perr <johnperr@abul.org>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2007-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-07-21
 */

class PDF extends TCPDF
{

    const FONT='DejaVuSans';
    const FONT_SIZE=10;

    /**
    * Constructeur de la classe PDF
    */
    public function __construct()
    {
        global $preferences;

        parent::__construct();
        //set some values
        $this->SetCreator(PDF_CREATOR);
        $name = preg_replace(
            '/%s/',
            $preferences->pref_nom,
            _T("Association %s")
        );
        $this->SetAuthor(
            $name . ' (using Galette ' . GALETTE_VERSION .
            'and TCPDF ' . TCPDF_VERSION . ')'
        );
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
        /** FIXME: I do not really like this, we should find sthing better */
        $_SESSION['galette']['pdf_error'] = true;
        $_SESSION['galette']['pdf_error_msg'] = $msg;
        header("location:" . $_SESSION['galette']['caller']);
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
            if (Imagepng($data, WEB_ROOT . 'tempimages/gif2png.png') ) {
                return $this->_parsepng(WEB_ROOT.'tempimages/gif2png.png');
            } else {
                $this->Error(_T("Error creating temporary png file from ").$file);
            }
        } else {
            $this->Error(_T("Unable to convert GIF file ").$file);
        }
    }

    function Footer(){
        global $preferences;

        $this->SetY(-20);
        $this->SetFont(self::FONT, '', 10);
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
    }

    function PageHeader(){
        global $preferences;

        $print_logo = new PrintLogo();
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

        $this->SetFont(self::FONT, '', 14);
        $this->SetTextColor(0, 0, 0);

        $y = $this->GetY();
        $this->Ln(4);

        $name = preg_replace(
            '/%s/',
            $preferences->pref_nom,
            _T("Association %s")
        );
        $this->Cell(0, 6, $name, 0, 1, 'L', 0, $preferences->website);
        $this->SetFont(self::FONT,'',12);

        $this->Cell(0, 6, _T("Adhesion form"), 0, 0, 'L', 0);

        $this->setY($y);
        $x = 190 - $wlogo; //right align
        $this->Image($logofile, $x, $this->GetY(), $wlogo, $hlogo);
        $this->y += $hlogo;
    }

}
?>
