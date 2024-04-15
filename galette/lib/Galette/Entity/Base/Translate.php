<?php

namespace Galette\Entity\Base;

class Translate
{
    public static function getFromLang(string $value): string
    {
        return _T(strip_tags($value), 'galette', false);
    }
}