<?php
/**
 * Copyright © 2003-2024 The Galette Team
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

/**
 * Test translation features.
 *
 * example (see galette/lan/Makefile for up to date command):
 * xgettext translations.php --keyword=_T --keyword=__ --keyword=_Tn:1,2 --keyword=_Tx:1c,2 --keyword=_Tnx:1c,2,3  -L PHP --from-code=UTF-8 --add-comments=TRANS --force-po -o php_translations.pot
 */

_T('Translation, no domain');
__('Another known syntax');
_T('Translation, galette domain', 'galette');
_T('Translation, other domain', 'other');

_Tn('I have a dream', 'I have several dreams', 1);
_Tx('button', 'Cancel');
_Tnx('button', 'Proceed action', 'Proceed actions', 3);
//TRANS: %s is user name
sprintf(_T('Hello %s'), 'you');
//TRANS: %1$s is the day name, %2$s the hour in the day
sprintf(_T('Day is %1$s, hour is %2$s'), 'tuesday', 9);
