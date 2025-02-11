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

use Galette\Core\Install as GaletteInstall;
use Galette\Core\Db as GaletteDb;
?>
<form id="adminform" action="installer.php" method="post" class="ui form">
    <h2><?php echo _T("Please chose the parameters of the admin account on Galette"); ?></h2>
    <div class="field required inline">
        <label for="install_adminlogin"><?php echo _T("Username:"); ?></label>
        <input type="text" name="install_adminlogin" id="install_adminlogin" value="<?php if (isset($_POST['install_adminlogin'])) echo htmlspecialchars($_POST['install_adminlogin']); ?>" required autofocus/>
    </div>
    <div class="field required inline">
        <label for="install_adminpass"><?php echo _T("Password:"); ?></label>
        <input type="password" name="install_adminpass" id="install_adminpass" value="" required/>
    </div>
    <div class="field required inline">
        <label for="install_adminpass_verif"><?php echo _T("Retype password:"); ?></label>
        <input type="password" name="install_adminpass_verif" id="install_adminpass_verif" value="" required/>
    </div>

    <div class="ui section divider"></div>

    <div class="ui equal width grid">
        <div class="right aligned column">
            <button type="submit" class="ui right labeled primary icon button"><i class="angle double <?php echo $i18n->isRtl() ? 'left' : 'right'; ?> icon" aria-hidden="true"></i> <?php echo _T("Next step"); ?></button>
        </div>
    </div>
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
