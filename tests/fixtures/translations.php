<?php
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
