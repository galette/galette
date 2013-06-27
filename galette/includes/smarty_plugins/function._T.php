<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Getetxt Smarty plugin for Galette
 *
 * PHP version 5
 *
 * Copyright © 2008-2013 The Galette Team
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
 * @category  Smarty
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2008-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7-dev - 2008-07-17
 */

/**
 * Smarty translations for Galette
 *
 * @param array  $params  An array that can contains:
 *                        string: the string to translate
 *                        pattern: A pattern (optional - required if replace present)
 *                        replace: Replacement for pattern (optional - required
 *                        if pattern present)
 * @param Smarty &$smarty Smarty
 *
 * @return translated string
 */
function smarty_function__T($params, &$smarty)
{
    extract($params);
    if ( isset($pattern) && isset($replace) ) {
        $ret = preg_replace($pattern, $replace, _T($string));
    } else {
        $ret = _T($string);
    }
    if ( isset($escape) ) {
        //replace insecable spaces
        $ret = str_replace('&nbsp;', ' ', $ret);
        //for the moment, only 'js' type is know
        $ret = htmlspecialchars($ret, ENT_QUOTES, 'UTF-8');
    }
    return $ret;
}
