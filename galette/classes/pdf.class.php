<?php

// Copyright © 2007 John Perr
// Copyright © 2007-2008 Johan Cwiklinski
//
// This file is part of Galette (http://galette.tuxfamily.org).
//
// Galette is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Galette is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Galette. If not, see <http://www.gnu.org/licenses/>.

/**
 * PDF class for galette
 * Traps tcpdf errors by overloading tcpdf::error method
 * Adds convenient method to convert color html codes
 * Adds a _parsegif function to convert gif to png
 *
 * @package Galette
 * 
 * @author     John Perr <johnperr@abul.org>
 * @copyright  2007 John Perr
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.63
 */

/**
 *  Require TCPDF class
 */
require_once (WEB_ROOT . 'includes/tcpdf_' . TCPDF_VERSION . '/tcpdf.php');

/**
 * PDF class for galette
 *
 * @name PDF
 * @package Galette
 * @abstract Class for expanding TCPDF.
 *
 */

class PDF extends TCPDF
{
/**
* Constructeur de la classe PDF
* 
*/
    public function __construct() {
        parent::__construct();
    }

/**
* Destructeur de la classe PDF
* 
*/
    public function __destruct() {
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
 * @access public
 * @param string $msg The error message
 * @since 1.0
 */
	public function Error($msg) {
        $_SESSION['galette']['pdf_error'] = TRUE;
        $_SESSION['galette']['pdf_error_msg'] = $msg;
        header("location:".$_SESSION['galette']['caller']);
        die();
	}
/**
 * Fonction de conversion d'une couleur au format HTML
 * #RRVVBB en un tableau de 3 valeurs comprises dans
 * l'interval [0;255]
 *
 * @param  chaine de 7 caratères #RRVVBB
 * @return tableau de 3 valeur R, G et B comprises entre 0 et 255
 * @access public
 */
    public function ColorHex2Dec($hex6) {
        $dec = array("R" => hexdec(substr($hex6,1,2)),
                     "G" => hexdec(substr($hex6,3,2)),
                     "B" => hexdec(substr($hex6,5,2)));
        return $dec;             
    }

/**
 * Extract info from a GIF file
 * (In fact: converts gif image to png and feeds it to _parsepng)
 * @access protected
 * @param path to the gif file
 */
	protected function _parsegif($file) {
		$a=GetImageSize($file);
		if(empty($a)) {
			$this->Error(_T("Missing or incorrect image file ").$file);
		}
		if($a[2]!=1) {
			$this->Error(_T("Not a GIF file ").$file);
		}

		// Tentative d'ouverture du fichier
		if(function_exists('gd_info')) {
			$data = @imagecreatefromgif ($file);

			// Test d'échec & Affichage d'un message d'erreur
			if (!$data) {
					$this->Error(_T("Error loading ").$file);
			}
			if (Imagepng($data,WEB_ROOT.'tempimages/gif2png.png')) {
				return $this->_parsepng(WEB_ROOT.'tempimages/gif2png.png');
			} else {
				$this->Error(_T("Error creating temporary png file from ").$file);
			}
		} else {
			$this->Error(_T("Unable to convert GIF file ").$file);
		}
	}
}
/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */
?>
