<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Getetxt Smarty plugin for Galette
 *
 * PHP version 5
 *
 * Copyright © 2008-2020 The Galette Team
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
 * @copyright 2008-2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7-dev - 2008-07-17
 */

/**
 * Smarty translations for Galette
 *
 * @param array  $params An array that can contains:
 *                       string: the string to translate
 *                       domain: a translation domain name
 *                       notrans: do not indicate not translated strings
 *                       pattern: A pattern (optional - required if replace present)
 *                       replace: Replacement for pattern (optional - required
 *                       if pattern present)
 *                       escaping: removes non breakable spaces and escape html
 *                       - 'html' for HTML escaping
 *                       - 'js' for javascript escaping
 *                       - 'url' for url escaping
 *                       plural: plural form of the string to translate
 *                       count: count for plural mode
 *                       context: gettext context
 * @param Smarty $smarty Smarty
 *
 * @return translated string
 */
function smarty_function__T($params, &$smarty)
{
    extract($params);

    if (!isset($domain)) {
        $domain = 'galette';
    }

    if (!isset($notrans)) {
        $notrans = true;
    }

    // use plural if required parameters are set
    if (isset($count) && isset($plural)) {
        // a context ha been specified
        if (isset($context)) {
            $ret = _Tnx($context, $string, $plural, $count, $domain, $notrans);
        } else {
            $ret = _Tn($string, $plural, $count, $domain, $notrans);
        }
    } else {
        // a context ha been specified
        if (isset($context)) {
            $ret = _Tx($context, $string, $domain, $notrans);
        } else {
            //$text = gettext($text);
            $ret = _T($string, $domain, $notrans);
        }
    }

    //handle replacements. Cannot be done on template side before they're
    //processed before string has been translated :/
    if (isset($pattern) && isset($replace)) {
        $ret = preg_replace($pattern, $replace, $ret);
    }

    if (isset($escape)) {
        //replace insecable spaces
        $ret = str_replace('&nbsp;', ' ', $ret);
        //for the moment, only 'js' type is know
        $ret = htmlspecialchars($ret, ENT_QUOTES, 'UTF-8');
    }

    return $ret;
}
