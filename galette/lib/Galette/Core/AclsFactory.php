<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

/**
 * Builds the routes ACLs: core ones, then plugins ones, then local overrides
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AclsFactory
{
    /**
     * Create ACLs list
     *
     * @param Plugins $plugins Plugins instance
     *
     * @return array<string, string>
     */
    public function create(Plugins $plugins): array
    {
        include GALETTE_ROOT . 'includes/core_acls.php';
        /** @var array<string, string> $core_acls */
        $acls = $core_acls;

        foreach ($plugins->getActiveModules() as $plugin) {
            $acls[$plugin['route'] . 'Info'] = 'member';
        }

        //use + to be sure core ACLs are not overridden by plugins ones.
        $acls += $plugins->getAcls();

        //load user defined ACLs
        if (file_exists(GALETTE_CONFIG_PATH . 'local_acls.inc.php')) {
            include GALETTE_CONFIG_PATH . 'local_acls.inc.php';
            //use array_merge here, we want $local_acls to override core ones.
            /** @var array<string, string> $local_acls */
            $acls = array_merge($acls, $local_acls);
        }

        return $acls;
    }
}
