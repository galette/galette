<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette installation, super-admin settings
 *
 * PHP version 5
 *
 * Copyright © 2013-2014 The Galette Team
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
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.8 - 2013-01-12
 */

use Galette\Core\Install as GaletteInstall;
use Galette\Core\Db as GaletteDb;
?>
                <h2><?php echo $install->getStepTitle(); ?></h2>
                <form id="adminform" action="installer.php" method="post">
                    <fieldset class="cssform">
                        <legend class="ui-state-active ui-corner-top"><?php echo _T("Please chose the parameters of the admin account on Galette"); ?></legend>
                        <p>
                            <label for="install_adminlogin" class="bline"><?php echo _T("Username:"); ?></label>
                            <input type="text" name="install_adminlogin" id="install_adminlogin" value="<?php if (isset($_POST['install_adminlogin'])) echo $_POST['install_adminlogin']; ?>" required autofocus/>
                        </p>
                        <p>
                            <label for="install_adminpass" class="bline"><?php echo _T("Password:"); ?></label>
                            <input type="password" name="install_adminpass" id="install_adminpass" value="" required/>
                        </p>
                        <p>
                            <label for="install_adminpass_verif" class="bline"><?php echo _T("Retype password:"); ?></label>
                            <input type="password" name="install_adminpass_verif" id="install_adminpass_verif" value="" required/>
                        </p>
                    </fieldset>
                    <p id="btn_box">
                        <input id="next_btn" type="submit" value="<?php echo _T("Next step"); ?>"/>
                    </p>
                </form>
                <script type="text/javascript">
                    $(function(){
                        $('#adminform').submit(function(){
                            if ( $('#install_adminpass').val() == $('#install_adminpass_verif').val() ) {
                                return true;
                            } else {
                                alert("<?php echo _T("Password mismatch!") ?>");
                                return false;
                            }
                        });
                    });
                </script>
