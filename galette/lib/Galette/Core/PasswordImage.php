<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Password image (captcha) for galette. The original code was found
 * in includes/functions.inc.php
 *
 * PHP version 5
 *
 * Copyright © 2013 The Galette Team
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
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.3.1 - 2012-01-03
 */

namespace Galette\Core;

use Analog\Analog as Analog;
use Galette\Entity\Adherent;

/**
 * Password image (captcha) for galette.
 *
 * @category  Core
 * @name      PasswordImage
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.3.1 - 2012-01-03
 */
class PasswordImage extends Password
{

    /**
     * Cleans any password image file older than 1 minute
     *
     * @return void
     */
    protected function cleanExpired()
    {
        $dh = @opendir(GALETTE_TEMPIMAGES_PATH);
        while ( $file=readdir($dh) ) {
            if (substr($file, 0, 3) == 'pw_'
                && time() - filemtime(GALETTE_TEMPIMAGES_PATH . '/' . $file) > 60
            ) {
                unlink(GALETTE_TEMPIMAGES_PATH . '/' . $file);
            }
        }
    }

    /**
     * Generates a new password
     *
     * @param null $none To be compatible with parent class
     *
     * @return boolean
     */
    public function generateNewPassword($none = null)
    {
        //second, generate a new password and store it in the database
        $password = $this->makeRandomPassword();
        $this->setPassword($password);

        $hash = null;
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $this->setHash($hash);

        return true;
    }

    /**
     * Get image name
     *
     * @return String
     */
    public function getImageName()
    {
        return 'pw_' . md5($this->getHash()) . '.png';
    }

    /**
     * Return base64 encoded image
     *
     * @return string
     */
    public function getImage()
    {
        $file = GALETTE_TEMPIMAGES_PATH . '/' . $this->getImageName();
        $image_type = false;
        if ( function_exists('exif_imagetype') ) {
            $image_type = exif_imagetype($file);
        } else {
            $image_size = getimagesize($file);
            if ( is_array($image_size) && isset($image_size[2]) ) {
                $image_type = $image_size[2];
            }
        }
        if ( $image_type ) {
            /*return str_replace(GALETTE_ROOT, '', $file);*/
            $filetype = pathinfo($file, PATHINFO_EXTENSION);
            $imgbinary = @file_get_contents($file);
            return 'data:image/' . $filetype . ';base64,' .
                base64_encode($imgbinary);
        }
    }

    /**
     * Outputs a png image for a random password
     * and a crypted string for it. The filename
     * for this image can be computed from the crypted
     * string by getPasswordImageName().
     *
     * @return String Crypted password
     */
    public function newImage()
    {
        $this->generateNewPassword();
        $pass = $this->getNewPassword();

        $png = imagecreate(10 + 7.5 * strlen($pass), 18);
        $bg = imagecolorallocate($png, 160, 160, 160);
        imagestring($png, 3, 5, 2, $pass, imagecolorallocate($png, 0, 0, 0));
        $file = GALETTE_TEMPIMAGES_PATH . '/' . $this->getImageName();

        imagepng($png, $file);
        // The perms of the file can be wrong, try to correct it
        // WARN : chmod() can be desacivated (i.e. : Free/Online)
        @chmod($file, 0644);
        return $this->getHash();
    }

    /**
     * Check for password validity
     *
     * @param string $pass  Clear password
     * @param string $crypt Crypted password
     *
     * @return boolean
     */
    public function check($pass, $crypt)
    {
        return crypt($pass, $crypt) == $crypt;
    }

}
