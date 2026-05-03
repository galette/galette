<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * @var \Galette\Core\I18n $i18n
 */
?>
<form id="adminform" action="installer.php" method="post" class="ui form">
    <h2><?php echo _T("Please chose the parameters of the admin account on Galette"); ?></h2>
    <div class="field required inline">
        <label for="install_adminlogin"><?php echo _T("Username:"); ?></label>
        <input type="text" name="install_adminlogin" id="install_adminlogin" value="<?php echo isset($_POST['install_adminlogin']) ? htmlspecialchars((string)$_POST['install_adminlogin']) : ''; ?>" required autofocus/>
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
