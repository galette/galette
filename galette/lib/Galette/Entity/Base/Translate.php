<?php

namespace Galette\Entity\Base;

/**
 * Translate helper
 *
 * @author Manuel <manuelh78dev@ik.me>
 */
class Translate
{
    /**
    * getFromLang
    *
    * @param string $value string to translate
    * @return string Translates string in current language
    */
    public static function getFromLang(string $value): string
    {
        return _T(strip_tags($value), 'galette', false);
    }
}
