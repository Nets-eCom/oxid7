<?php

namespace NexiCheckout\Application\Helper;

class StringSanitizer
{
    private const ALLOWED_CHARACTERS_PATTERN = '/[^\x{00A1}-\x{00AC}\x{00AE}-\x{00FF}\x{0100}-\x{017F}\x{0180}-\x{024F}\x{0250}-\x{02AF}\x{02B0}-\x{02FF}\x{0300}-\x{036F}A-Za-z0-9\!\#\$\%\(\)*\+\,\-\.\/\:\;\\=\?\@\[\]\\^\_\`\{\}\~ ]+/u';

    public static function sanitize(string $string): string
    {
        $sanitized = preg_replace(
            self::ALLOWED_CHARACTERS_PATTERN,
            '',
            substr($string, 0, 128)
        );

        if (empty($sanitized)) {
            return preg_replace('/[^A-Za-z0-9() -]/', '', $string);
        }

        return $sanitized;
    }
}